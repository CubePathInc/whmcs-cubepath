<?php

namespace CubePath\WHMCS\Addon;

use RuntimeException;
use WHMCS\Database\Capsule;

/**
 * Create and maintain WHMCS products that use the CubePath server module.
 *
 * A product is provisioned by the server module from:
 *  - its server group's CubePath server, or an API token in configoption1
 *  - configoption2: plan name
 *  - configoption3: project ID
 *  - configurable options "location|..." and "template|...", whose
 *    sub-options are named "<api value>|<label>"
 *  - admin-only custom fields vps_id, ip_address and project_id
 */
class Products
{
    const SERVER_MODULE = 'cubepath';

    const OPTION_LOCATION = 'location';
    const OPTION_TEMPLATE = 'template';
    const CUSTOM_FIELDS = array(
        'vps_id|VPS ID',
        'ip_address|IP Address',
        'project_id|Project ID',
    );

    const BILLING_CYCLES = array('monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially');

    /**
     * @return \Illuminate\Support\Collection
     */
    public static function all()
    {
        return Capsule::table('tblproducts as p')
            ->leftJoin('tblproductgroups as g', 'p.gid', '=', 'g.id')
            ->where('p.servertype', self::SERVER_MODULE)
            ->orderBy('g.name')
            ->orderBy('p.order')
            ->orderBy('p.name')
            ->select(
                'p.id',
                'p.name',
                'p.paytype',
                'p.hidden',
                'p.retired',
                'p.configoption2 as plan_name',
                'g.name as group_name',
                Capsule::raw('(SELECT COUNT(*) FROM tblhosting h WHERE h.packageid = p.id) AS services')
            )
            ->get();
    }

    public static function ids()
    {
        return Capsule::table('tblproducts')
            ->where('servertype', self::SERVER_MODULE)
            ->pluck('id')
            ->all();
    }

    /**
     * Create a product for a CubePath plan.
     *
     * @param array  $plan    Plan from Catalog::plans()
     * @param array  $pricing currency id => cycle => price, as accepted by the AddProduct API
     * @return int Product ID
     */
    public static function create(Catalog $catalog, array $plan, $name, $groupId, $payType, array $pricing)
    {
        $serverGroupId = Servers::groupId();
        if (!$serverGroupId)
        {
            throw new RuntimeException('Add a CubePath server in System Settings > Servers before creating products.');
        }

        $result = localAPI('AddProduct', array(
            'type'              => 'server',
            'gid'               => (int)$groupId,
            'name'              => $name,
            'description'       => Catalog::describePlan($plan),
            'paytype'           => $payType,
            'module'            => self::SERVER_MODULE,
            'servergroupid'     => $serverGroupId,
            'autosetup'         => 'payment',
            'showdomainoptions' => false,
            'configoption2'     => $plan['plan_name'],
            'configoption3'     => Settings::defaultProjectId(),
            'pricing'           => $pricing,
        ));

        if (!isset($result['result']) || $result['result'] !== 'success')
        {
            throw new RuntimeException(isset($result['message']) ? $result['message'] : 'AddProduct failed');
        }

        $productId = (int)$result['pid'];

        // AddProduct leaves the order at 0, so the store would list the group by name
        // (hd.large before hd.nano). Put it last instead: Create all goes from the cheapest plan up.
        $order = (int)Capsule::table('tblproducts')->where('gid', (int)$groupId)->where('id', '!=', $productId)->max('order');
        Capsule::table('tblproducts')->where('id', $productId)->update(array('order' => $order + 1));

        self::ensureCustomFields($productId);
        self::syncConfigurableOptions($productId, $catalog);

        return $productId;
    }

    /**
     * Point existing products at a project. Products with their own API token
     * may belong to another CubePath account, so they are left alone.
     *
     * @return int Number of products updated
     */
    public static function setProject($projectId)
    {
        return Capsule::table('tblproducts')
            ->where('servertype', self::SERVER_MODULE)
            ->where(function ($query) {
                $query->whereNull('configoption1')->orWhere('configoption1', '');
            })
            ->update(array('configoption3' => $projectId));
    }

    /**
     * Product group with the given name, created at the end of the list if it does not exist.
     *
     * WHMCS has no local API for product groups, so the row is inserted directly.
     *
     * @return int Product group ID
     */
    public static function findOrCreateGroup($name)
    {
        $groupId = Capsule::table('tblproductgroups')->where('name', $name)->value('id');
        if ($groupId)
        {
            return (int)$groupId;
        }

        $row = array(
            'name'             => $name,
            'headline'         => '',
            'tagline'          => '',
            'orderfrmtpl'      => '',
            'disabledgateways' => '',
            'hidden'           => 0,
            'order'            => (int)Capsule::table('tblproductgroups')->max('order') + 1,
        );

        // Columns added in WHMCS 8.
        $schema = Capsule::schema();
        if ($schema->hasColumn('tblproductgroups', 'slug'))
        {
            $row['slug'] = self::uniqueGroupSlug($name);
        }
        if ($schema->hasColumn('tblproductgroups', 'created_at'))
        {
            $row['created_at'] = $row['updated_at'] = date('Y-m-d H:i:s');
        }

        return (int)Capsule::table('tblproductgroups')->insertGetId($row);
    }

    /**
     * Delete an empty product group.
     */
    public static function deleteGroup($groupId)
    {
        if (Capsule::table('tblproducts')->where('gid', $groupId)->exists())
        {
            throw new RuntimeException('The product group still has products. Move or delete them first.');
        }

        Capsule::table('tblproductgroups')->where('id', $groupId)->delete();
        Capsule::table('tblproduct_group_features')->where('product_group_id', $groupId)->delete();
    }

    /**
     * Delete a product without services, with its pricing, custom fields and
     * the configurable option group the addon created for it.
     *
     * WHMCS has no local API to delete products, so the rows are removed directly.
     */
    public static function delete($productId)
    {
        if (!Capsule::table('tblproducts')->where('id', $productId)->where('servertype', self::SERVER_MODULE)->exists())
        {
            throw new RuntimeException(sprintf('Product #%d is not a CubePath product.', $productId));
        }
        if (Capsule::table('tblhosting')->where('packageid', $productId)->exists())
        {
            throw new RuntimeException('The product has services. Terminate or move them before deleting it.');
        }

        Capsule::connection()->transaction(function () use ($productId) {
            // Option groups only linked to this product go with it.
            foreach (Capsule::table('tblproductconfiglinks')->where('pid', $productId)->pluck('gid')->all() as $groupId)
            {
                if (Capsule::table('tblproductconfiglinks')->where('gid', $groupId)->where('pid', '!=', $productId)->exists())
                {
                    continue;
                }

                $optionIds = Capsule::table('tblproductconfigoptions')->where('gid', $groupId)->pluck('id')->all();
                $subIds = Capsule::table('tblproductconfigoptionssub')->whereIn('configid', $optionIds)->pluck('id')->all();
                Capsule::table('tblpricing')->where('type', 'configoptions')->whereIn('relid', $subIds)->delete();
                Capsule::table('tblproductconfigoptionssub')->whereIn('configid', $optionIds)->delete();
                Capsule::table('tblproductconfigoptions')->where('gid', $groupId)->delete();
                Capsule::table('tblproductconfiggroups')->where('id', $groupId)->delete();
            }
            Capsule::table('tblproductconfiglinks')->where('pid', $productId)->delete();

            $fieldIds = Capsule::table('tblcustomfields')->where('type', 'product')->where('relid', $productId)->pluck('id')->all();
            Capsule::table('tblcustomfieldsvalues')->whereIn('fieldid', $fieldIds)->delete();
            Capsule::table('tblcustomfields')->whereIn('id', $fieldIds)->delete();

            $slugIds = Capsule::table('tblproducts_slugs')->where('product_id', $productId)->pluck('id')->all();
            Capsule::table('tblproducts_slugs_tracking')->whereIn('slug_id', $slugIds)->delete();
            Capsule::table('tblproducts_slugs')->whereIn('id', $slugIds)->delete();

            Capsule::table('tblpricing')->where('type', 'product')->where('relid', $productId)->delete();
            Capsule::table('tblproduct_upgrade_products')->where('product_id', $productId)->orWhere('upgrade_product_id', $productId)->delete();
            Capsule::table('tblproduct_recommendations')->where('product_id', $productId)->delete();
            Capsule::table('tblproduct_downloads')->where('product_id', $productId)->delete();
            Capsule::table('tblproducteventactions')->where('entity_type', 'product')->where('entity_id', $productId)->delete();
            Capsule::table('tblproducts')->where('id', $productId)->delete();
        });
    }

    private static function uniqueGroupSlug($name)
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if ($base === '')
        {
            $base = 'group';
        }

        $slug = $base;
        for ($i = 2; Capsule::table('tblproductgroups')->where('slug', $slug)->exists(); $i++)
        {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    /**
     * Pricing that disables every billing cycle except the ones given.
     *
     * @param array $monthlyByCurrency currency id => monthly price
     */
    public static function monthlyPricing(array $monthlyByCurrency)
    {
        $pricing = array();
        foreach ($monthlyByCurrency as $currencyId => $monthly)
        {
            foreach (self::BILLING_CYCLES as $cycle)
            {
                $pricing[$currencyId][$cycle] = $cycle === 'monthly' ? $monthly : -1;
            }
        }

        return $pricing;
    }

    public static function ensureCustomFields($productId)
    {
        foreach (self::CUSTOM_FIELDS as $fieldName)
        {
            $exists = Capsule::table('tblcustomfields')
                ->where('type', 'product')
                ->where('relid', $productId)
                ->where('fieldname', $fieldName)
                ->exists();

            if (!$exists)
            {
                Capsule::table('tblcustomfields')->insert(array(
                    'type'      => 'product',
                    'relid'     => $productId,
                    'fieldname' => $fieldName,
                    'fieldtype' => 'text',
                    'adminonly' => 'on',
                ));
            }
        }
    }

    /**
     * Product group for a plan family ("General Purpose"), created the first
     * time and reused afterwards even if the admin renames it.
     *
     * @return int Product group ID
     */
    public static function familyGroupId($family)
    {
        $family = trim((string)$family) !== '' ? trim((string)$family) : 'VPS';
        $groups = Settings::familyGroups();

        if (isset($groups[$family]) && Capsule::table('tblproductgroups')->where('id', $groups[$family])->exists())
        {
            return $groups[$family];
        }

        $groupId = self::findOrCreateGroup($family);
        Settings::setFamilyGroup($family, $groupId);

        return $groupId;
    }

    /**
     * Make sure the product has location and template configurable options
     * with one sub-option per value the plan can use. Existing sub-options
     * are kept, so prices set by the admin are preserved.
     */
    public static function syncConfigurableOptions($productId, Catalog $catalog)
    {
        $planName = Capsule::table('tblproducts')->where('id', $productId)->value('configoption2');
        $plan = $planName ? $catalog->plan($planName) : null;
        if (!$plan)
        {
            throw new RuntimeException(sprintf('Plan "%s" of product #%d is not offered by CubePath', $planName, $productId));
        }

        // Every location of the plan gets an option; the sold out ones stay hidden until restocked.
        $locations = array_intersect_key($catalog->locations(), array_flip($plan['offered_in']));
        $templates = array_map(function ($template) {
            return $template['label'];
        }, $catalog->templatesForPlan($plan));
        Settings::setStock($catalog);

        Capsule::connection()->transaction(function () use ($productId, $locations, $templates) {
            $groupId = self::configGroupId($productId);
            self::syncOption($groupId, self::OPTION_LOCATION, 'Location', $locations);
            self::syncOption($groupId, self::OPTION_TEMPLATE, 'Operating System', $templates);
        });

        self::applyVisibility();
    }

    /**
     * Sync the options of every CubePath product with the catalog: new
     * locations and templates, and the stock of each location.
     *
     * @return string[] One error per product that could not be synced
     */
    public static function syncAll(Catalog $catalog)
    {
        Settings::setStock($catalog);

        $errors = array();
        foreach (self::ids() as $productId)
        {
            try
            {
                self::ensureCustomFields($productId);
                self::syncConfigurableOptions($productId, $catalog);
            }
            catch (\Exception $e)
            {
                $errors[] = '#' . $productId . ': ' . $e->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Hide the location and template sub-options disabled in the addon, and
     * the locations where a product's plan is sold out; show the rest.
     */
    public static function applyVisibility()
    {
        $products = Capsule::table('tblproductconfiglinks as l')
            ->join('tblproducts as p', 'p.id', '=', 'l.pid')
            ->where('p.servertype', self::SERVER_MODULE)
            ->get(array('l.gid', 'p.configoption2 as plan'));

        $disabled = array(
            self::OPTION_LOCATION => Settings::disabledLocations(),
            self::OPTION_TEMPLATE => Settings::disabledTemplates(),
        );
        $stock = Settings::stock();

        foreach ($products as $product)
        {
            foreach ($disabled as $option => $values)
            {
                $subOptions = Capsule::table('tblproductconfigoptionssub as s')
                    ->join('tblproductconfigoptions as o', 's.configid', '=', 'o.id')
                    ->where('o.gid', $product->gid)
                    ->where('o.optionname', 'like', $option . '|%')
                    ->select('s.id', 's.optionname', 's.hidden')
                    ->get();

                foreach ($subOptions as $subOption)
                {
                    $value = self::optionValue($subOption->optionname);
                    $soldOut = $option === self::OPTION_LOCATION
                        && isset($stock[$product->plan])
                        && !in_array($value, $stock[$product->plan], true);

                    $hidden = in_array($value, $values, true) || $soldOut ? 1 : 0;
                    if ((int)$subOption->hidden !== $hidden)
                    {
                        Capsule::table('tblproductconfigoptionssub')->where('id', $subOption->id)->update(array('hidden' => $hidden));
                    }
                }
            }
        }
    }

    /**
     * Value stored before the pipe of a WHMCS option name ("value|Label").
     */
    public static function optionValue($optionName)
    {
        $parts = explode('|', $optionName, 2);

        return trim($parts[0]);
    }

    private static function configGroupId($productId)
    {
        $groupId = Capsule::table('tblproductconfiglinks as l')
            ->join('tblproductconfigoptions as o', 'o.gid', '=', 'l.gid')
            ->where('l.pid', $productId)
            ->where('o.optionname', 'like', self::OPTION_LOCATION . '|%')
            ->value('l.gid');

        if ($groupId)
        {
            return (int)$groupId;
        }

        $productName = Capsule::table('tblproducts')->where('id', $productId)->value('name');
        $groupId = Capsule::table('tblproductconfiggroups')->insertGetId(array(
            'name'        => sprintf('CubePath #%d %s', $productId, $productName),
            'description' => 'Location and operating system for the CubePath plan. Managed by the CubePath addon.',
        ));
        Capsule::table('tblproductconfiglinks')->insert(array('gid' => $groupId, 'pid' => $productId));

        return (int)$groupId;
    }

    /**
     * @param array<string, string> $values API value => label
     */
    private static function syncOption($groupId, $key, $label, array $values)
    {
        $optionId = Capsule::table('tblproductconfigoptions')
            ->where('gid', $groupId)
            ->where('optionname', 'like', $key . '|%')
            ->value('id');

        if (!$optionId)
        {
            $optionId = Capsule::table('tblproductconfigoptions')->insertGetId(array(
                'gid'        => $groupId,
                'optionname' => $key . '|' . $label,
                'optiontype' => 1,
                'order'      => $key === self::OPTION_LOCATION ? 1 : 2,
            ));
        }

        $existing = Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $optionId)
            ->pluck('optionname')
            ->map(function ($name) {
                return self::optionValue($name);
            })
            ->all();

        $sortOrder = count($existing);
        foreach ($values as $value => $valueLabel)
        {
            if (in_array((string)$value, $existing, true))
            {
                continue;
            }

            $subOptionId = Capsule::table('tblproductconfigoptionssub')->insertGetId(array(
                'configid'   => $optionId,
                'optionname' => $value . '|' . $valueLabel,
                'sortorder'  => ++$sortOrder,
                'hidden'     => 0,
            ));
            self::insertFreePricing($subOptionId);
        }
    }

    private static function insertFreePricing($subOptionId)
    {
        foreach (Capsule::table('tblcurrencies')->pluck('id') as $currencyId)
        {
            Capsule::table('tblpricing')->insert(array(
                'type'     => 'configoptions',
                'currency' => $currencyId,
                'relid'    => $subOptionId,
            ));
        }
    }
}
