<?php

namespace CubePath\WHMCS\Addon;

use RuntimeException;
use WHMCS\Database\Capsule;

/**
 * Create and maintain WHMCS products that use the CubePath server module.
 *
 * A product is provisioned by the server module from:
 *  - configoption1: API token
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
        $result = localAPI('AddProduct', array(
            'type'              => 'server',
            'gid'               => (int)$groupId,
            'name'              => $name,
            'description'       => Catalog::describePlan($plan),
            'paytype'           => $payType,
            'module'            => self::SERVER_MODULE,
            'showdomainoptions' => false,
            'configoption1'     => Settings::apiToken(),
            'configoption2'     => $plan['plan_name'],
            'configoption3'     => Settings::defaultProjectId(),
            'pricing'           => $pricing,
        ));

        if (!isset($result['result']) || $result['result'] !== 'success')
        {
            throw new RuntimeException(isset($result['message']) ? $result['message'] : 'AddProduct failed');
        }

        $productId = (int)$result['pid'];
        self::ensureCustomFields($productId);
        self::syncConfigurableOptions($productId, $catalog);

        return $productId;
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

        $locations = array_intersect_key($catalog->locations(), array_flip($plan['locations']));
        $templates = array_map(function ($template) {
            return $template['label'];
        }, $catalog->templatesForPlan($plan));

        Capsule::connection()->transaction(function () use ($productId, $locations, $templates) {
            $groupId = self::configGroupId($productId);
            self::syncOption($groupId, self::OPTION_LOCATION, 'Location', $locations);
            self::syncOption($groupId, self::OPTION_TEMPLATE, 'Operating System', $templates);
        });

        self::applyVisibility();
    }

    /**
     * Hide the location and template sub-options disabled in the addon from
     * every CubePath product, and show the rest.
     */
    public static function applyVisibility()
    {
        $groupIds = Capsule::table('tblproductconfiglinks')
            ->whereIn('pid', self::ids())
            ->pluck('gid')
            ->all();

        if (!$groupIds)
        {
            return;
        }

        $disabled = array(
            self::OPTION_LOCATION => Settings::disabledLocations(),
            self::OPTION_TEMPLATE => Settings::disabledTemplates(),
        );

        foreach ($disabled as $option => $values)
        {
            $subOptions = Capsule::table('tblproductconfigoptionssub as s')
                ->join('tblproductconfigoptions as o', 's.configid', '=', 'o.id')
                ->whereIn('o.gid', $groupIds)
                ->where('o.optionname', 'like', $option . '|%')
                ->select('s.id', 's.optionname', 's.hidden')
                ->get();

            foreach ($subOptions as $subOption)
            {
                $hidden = in_array(self::optionValue($subOption->optionname), $values, true) ? 1 : 0;
                if ((int)$subOption->hidden !== $hidden)
                {
                    Capsule::table('tblproductconfigoptionssub')->where('id', $subOption->id)->update(array('hidden' => $hidden));
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
