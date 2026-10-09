<?php

namespace CubePath\WHMCS\Addon\Admin;

use CubePath\WHMCS\Addon\Catalog;
use CubePath\WHMCS\Addon\Products;
use CubePath\WHMCS\Addon\Projects;
use CubePath\WHMCS\Addon\Servers;
use CubePath\WHMCS\Addon\Settings;
use Cubepath\CubepathClient;
use Exception;
use WHMCS\Database\Capsule;

/**
 * Actions behind the pages of the addon panel (Addons > CubePath): each one
 * returns the data a page shows or applies a change. Messages are translated
 * by the panel; errors use the addon language file.
 */
class AddonApi
{
    const PAGES = array('dashboard', 'vps', 'creator', 'products', 'locations', 'templates', 'settings');

    /** Pages from before the panel, still reachable from old bookmarks. */
    const PAGE_ALIASES = array('servers' => 'vps');

    /** @var array */
    private $lang;

    /** @var Catalog|null */
    private $catalog;

    /** @var string|null */
    private $apiError;

    public function __construct(array $lang)
    {
        $this->lang = $lang;
    }

    /**
     * Page to open first, from the page query parameter.
     */
    public static function page($requested)
    {
        $requested = (string)$requested;
        if (isset(self::PAGE_ALIASES[$requested]))
        {
            return self::PAGE_ALIASES[$requested];
        }

        return in_array($requested, self::PAGES, true) ? $requested : 'dashboard';
    }

    /**
     * @param string $action addon action without the "addon." prefix
     * @return array
     * @throws Exception
     */
    public function handle($action, array $data)
    {
        switch ($action)
        {
            case 'dashboard':
                return $this->dashboard();
            case 'creator':
                return $this->creator();
            case 'createProduct':
                return $this->createProduct($data);
            case 'createAllProducts':
                return $this->createAllProducts($data);
            case 'deleteGroup':
                Products::deleteGroup($this->groupFromData($data));

                return array();
            case 'products':
                return $this->products();
            case 'deleteProduct':
                Products::delete(isset($data['id']) ? (int)$data['id'] : 0);

                return array();
            case 'syncProducts':
                return $this->syncProducts();
            case 'toggles':
                return $this->toggles($this->kind($data));
            case 'saveToggles':
                return $this->saveToggles($this->kind($data), $data);
            case 'settings':
                return $this->settings();
            case 'saveToken':
                return $this->saveToken($data);
            case 'saveProject':
                return $this->saveProject($data);
        }

        throw new Exception($this->t('unknownAction'));
    }

    private function dashboard()
    {
        $catalog = $this->catalog();
        $server = Servers::first();
        $projectId = Settings::defaultProjectId();

        return array(
            'apiError'    => $this->apiError,
            'server'      => $server ? array('id' => (int)$server->id, 'name' => (string)$server->name) : null,
            'legacyToken' => !$server && Settings::apiToken() !== '',
            'projectId'   => $projectId,
            'projectName' => $catalog ? $this->projectName($projectId) : null,
            'plans'       => $catalog ? count($catalog->plans()) : 0,
            'locations'   => $catalog ? count($catalog->locations()) : 0,
            'templates'   => $catalog ? count($catalog->templates()) : 0,
            'products'    => count(Products::ids()),
            'services'    => (int)Capsule::table('tblhosting as h')
                ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
                ->where('p.servertype', Products::SERVER_MODULE)
                ->whereIn('h.domainstatus', PanelApi::LISTED_STATUSES)
                ->count(),
        );
    }

    private function creator()
    {
        $catalog = $this->requireCatalog();
        $existing = $this->existingPlans();

        $plans = array();
        foreach ($catalog->plans() as $plan)
        {
            $plans[] = array(
                'name'       => $plan['plan_name'],
                'family'     => $plan['family'],
                'cpu'        => $plan['cpu'],
                'ramMb'      => $plan['ram_mb'],
                'storageGb'  => $plan['storage_gb'],
                'transferTb' => $plan['bandwidth_tb'],
                'monthly'    => $plan['price_per_month'],
                'locations'  => $plan['locations'],
                'available'  => $plan['available'],
                'exists'     => in_array($plan['plan_name'], $existing, true),
            );
        }

        $groups = array();
        $groupNames = array();
        foreach (Capsule::table('tblproductgroups')->orderBy('order')->orderBy('name')->get(array('id', 'name')) as $group)
        {
            $groups[] = array('id' => (int)$group->id, 'name' => (string)$group->name);
            $groupNames[(int)$group->id] = (string)$group->name;
        }

        $familyGroups = array();
        foreach (Settings::familyGroups() as $family => $groupId)
        {
            if (isset($groupNames[$groupId]))
            {
                $familyGroups[$family] = $groupNames[$groupId];
            }
        }

        $currencies = array();
        foreach (Capsule::table('tblcurrencies')->orderBy('default', 'desc')->orderBy('code')->get() as $currency)
        {
            $currencies[] = array(
                'id'      => (int)$currency->id,
                'code'    => (string)$currency->code,
                'prefix'  => (string)$currency->prefix,
                'default' => (bool)$currency->default,
            );
        }

        return array(
            'projectId'  => Settings::defaultProjectId(),
            'plans'      => $plans,
            'groups'       => $groups,
            'familyGroups' => (object)$familyGroups,
            'currencies'   => $currencies,
        );
    }

    private function createProduct(array $data)
    {
        $catalog = $this->requireCatalog();
        $plan = $catalog->plan(isset($data['plan']) ? (string)$data['plan'] : '');
        if (!$plan)
        {
            throw new Exception($this->t('planNotFound'));
        }
        if (!$plan['available'])
        {
            throw new Exception($this->t('planOutOfStock'));
        }

        $name = trim(isset($data['name']) ? (string)$data['name'] : '');
        $payType = isset($data['paytype']) && in_array($data['paytype'], array('free', 'onetime', 'recurring'), true)
            ? $data['paytype']
            : 'recurring';

        $pricing = array();
        if ($payType !== 'free')
        {
            foreach ($this->currencyIds() as $currencyId)
            {
                $monthly = isset($data['prices'][$currencyId]) ? str_replace(',', '.', trim((string)$data['prices'][$currencyId])) : '';
                if (!is_numeric($monthly) || $monthly < 0)
                {
                    throw new Exception($this->t('invalidPrice'));
                }
                $pricing[$currencyId] = $monthly;
            }
        }

        $productId = Products::create(
            $catalog,
            $plan,
            $name !== '' ? $name : $plan['plan_name'],
            $this->autoGroups($data) ? Products::familyGroupId($plan['family']) : $this->groupFromData($data),
            $payType,
            Products::monthlyPricing($pricing)
        );

        return array('productId' => $productId);
    }

    private function createAllProducts(array $data)
    {
        $catalog = $this->requireCatalog();
        $markup = isset($data['markup']) ? (float)str_replace(',', '.', (string)$data['markup']) : 0.0;

        $existing = $this->existingPlans();
        $defaultCurrency = Capsule::table('tblcurrencies')->where('default', 1)->first();
        $created = 0;

        foreach ($catalog->plans() as $plan)
        {
            if (!$plan['available'] || in_array($plan['plan_name'], $existing, true))
            {
                continue;
            }

            // Prices from the API are in USD; only prefill them when that is the default currency.
            $pricing = array();
            foreach ($this->currencyIds() as $currencyId)
            {
                $usd = $defaultCurrency && $defaultCurrency->code === 'USD' && (int)$defaultCurrency->id === $currencyId;
                $pricing[$currencyId] = $usd ? round($plan['price_per_month'] * (1 + $markup / 100), 2) : 0;
            }

            // Each family gets its own group: gp.* in General Purpose, rz.* in High Frecuency...
            Products::create($catalog, $plan, $plan['plan_name'], Products::familyGroupId($plan['family']), 'recurring', Products::monthlyPricing($pricing));
            $created++;
        }

        return array('created' => $created);
    }

    private function products()
    {
        $products = array();
        foreach (Products::all() as $product)
        {
            $products[] = array(
                'id'       => (int)$product->id,
                'name'     => (string)$product->name,
                'group'    => (string)$product->group_name,
                'plan'     => (string)$product->plan_name,
                'paytype'  => (string)$product->paytype,
                'hidden'   => (bool)$product->hidden,
                'retired'  => (bool)$product->retired,
                'services' => (int)$product->services,
            );
        }

        return $products;
    }

    private function syncProducts()
    {
        $errors = Products::syncAll($this->requireCatalog());
        if ($errors)
        {
            throw new Exception(implode('; ', $errors));
        }

        return array();
    }

    private function toggles($kind)
    {
        $catalog = $this->requireCatalog();

        $items = array();
        if ($kind === 'locations')
        {
            foreach ($catalog->locations() as $value => $label)
            {
                $items[] = array('value' => (string)$value, 'label' => $label, 'type' => 'location');
            }
        }
        else
        {
            foreach ($catalog->templates() as $value => $template)
            {
                $items[] = array('value' => (string)$value, 'label' => $template['label'], 'type' => $template['type']);
            }
        }

        return array(
            'items'    => $items,
            'disabled' => $kind === 'locations' ? Settings::disabledLocations() : Settings::disabledTemplates(),
        );
    }

    private function saveToggles($kind, array $data)
    {
        $disabled = isset($data['disabled']) && is_array($data['disabled']) ? $data['disabled'] : array();

        if ($kind === 'locations')
        {
            Settings::setDisabledLocations($disabled);
        }
        else
        {
            Settings::setDisabledTemplates($disabled);
        }
        Products::applyVisibility();

        return array();
    }

    private function settings()
    {
        $token = Settings::apiToken();
        $server = Servers::first();
        $projectId = Settings::defaultProjectId();

        $projects = array();
        $projectsError = null;
        if ($token !== '')
        {
            try
            {
                foreach (Projects::options(Projects::fetch($token), $projectId) as $id => $name)
                {
                    if ($id !== '')
                    {
                        $projects[] = array('id' => (string)$id, 'name' => $name);
                    }
                }
            }
            catch (Exception $e)
            {
                $projectsError = $e->getMessage();
            }
        }

        return array(
            'server'        => $server ? array('id' => (int)$server->id, 'name' => (string)$server->name) : null,
            'tokenHint'     => $token !== '' ? substr($token, -4) : '',
            'projectId'     => $projectId,
            'projects'      => $projects,
            'projectsError' => $projectsError,
            'products'      => count(Products::ids()),
        );
    }

    /**
     * Save a new API token after checking it against the API.
     */
    private function saveToken(array $data)
    {
        $token = trim(isset($data['token']) ? (string)$data['token'] : '');
        if ($token === '')
        {
            throw new Exception($this->t('tokenRequired'));
        }

        try
        {
            $projects = Projects::fetch($token);
        }
        catch (Exception $e)
        {
            throw new Exception(sprintf($this->t('tokenRejected'), $e->getMessage()));
        }

        Settings::setApiToken($token);
        $projectId = Settings::defaultProjectId();

        return array('projectMissing' => $projectId !== '' && !isset($projects[$projectId]));
    }

    private function saveProject(array $data)
    {
        $projectId = trim(isset($data['projectId']) ? (string)$data['projectId'] : '');
        if ($projectId === '')
        {
            throw new Exception($this->t('projectRequired'));
        }

        Settings::setDefaultProjectId($projectId);

        return array('updated' => !empty($data['applyToProducts']) ? Products::setProject($projectId) : null);
    }

    /**
     * Whether a creator form puts each plan in its family's group instead of the chosen one.
     */
    private function autoGroups(array $data)
    {
        return isset($data['grouping']) && $data['grouping'] === 'auto';
    }

    private function kind(array $data)
    {
        return isset($data['kind']) && $data['kind'] === 'templates' ? 'templates' : 'locations';
    }

    /**
     * Product group chosen in a form; "new" creates one named by newGroup.
     */
    private function groupFromData(array $data)
    {
        $gid = isset($data['gid']) ? (string)$data['gid'] : '';
        if ($gid === 'new')
        {
            $name = trim(isset($data['newGroup']) ? (string)$data['newGroup'] : '');
            if ($name === '')
            {
                throw new Exception($this->t('groupNameRequired'));
            }

            return Products::findOrCreateGroup($name);
        }

        if (!ctype_digit($gid) || !Capsule::table('tblproductgroups')->where('id', (int)$gid)->exists())
        {
            throw new Exception($this->t('groupRequired'));
        }

        return (int)$gid;
    }

    /**
     * @return string[] Plan names that already have a product.
     */
    private function existingPlans()
    {
        return Capsule::table('tblproducts')
            ->where('servertype', Products::SERVER_MODULE)
            ->pluck('configoption2')
            ->unique()
            ->values()
            ->all();
    }

    private function currencyIds()
    {
        return array_map('intval', Capsule::table('tblcurrencies')->pluck('id')->all());
    }

    private function catalog()
    {
        if ($this->catalog === null && $this->apiError === null)
        {
            $token = Settings::apiToken();
            if ($token === '')
            {
                $this->apiError = $this->t('tokenMissing');

                return null;
            }

            try
            {
                $this->catalog = Catalog::fetch(new CubepathClient($token));
            }
            catch (Exception $e)
            {
                $this->apiError = $e->getMessage();
            }
        }

        return $this->catalog;
    }

    private function requireCatalog()
    {
        $catalog = $this->catalog();
        if (!$catalog)
        {
            throw new Exception($this->apiError);
        }

        return $catalog;
    }

    /**
     * Name of a project, or null when it cannot be looked up.
     */
    private function projectName($projectId)
    {
        if ($projectId === '')
        {
            return null;
        }

        try
        {
            $names = Projects::fetch(Settings::apiToken());
        }
        catch (Exception $e)
        {
            return null;
        }

        return isset($names[$projectId]) ? $names[$projectId] : sprintf($this->t('projectNotFound'), $projectId);
    }

    private function t($key)
    {
        return isset($this->lang[$key]) ? $this->lang[$key] : $key;
    }
}
