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
 * Admin area pages of the addon (Addons > CubePath).
 */
class Controller
{
    const PAGES = array(
        'dashboard' => 'Dashboard',
        'creator'   => 'Product Creator',
        'products'  => 'Products',
        'locations' => 'Locations',
        'templates' => 'Templates',
    );

    const FLASH_KEY = 'cubepath_addon_flash';

    /** @var string */
    private $moduleLink;

    /** @var array */
    private $lang;

    /** @var Catalog|null */
    private $catalog;

    /** @var string|null */
    private $apiError;

    public function __construct($moduleLink, array $lang)
    {
        $this->moduleLink = $moduleLink;
        $this->lang = $lang;
    }

    public function dispatch(array $query, array $post)
    {
        $page = isset($query['page']) && array_key_exists($query['page'], self::PAGES) ? $query['page'] : 'dashboard';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($post['action']))
        {
            check_token('WHMCS.admin.default');
            $this->handlePost($page, (string)$post['action'], $post);

            return '';
        }

        $method = 'page' . ucfirst($page);

        return $this->render('layout', array(
            'page'    => $page,
            'content' => $this->$method(),
            'flash'   => $this->takeFlash(),
        ));
    }

    private function pageDashboard()
    {
        $catalog = $this->catalog();

        return $this->render('dashboard', array(
            'apiError'   => $this->apiError,
            'tokenSet'   => Settings::apiToken() !== '',
            'server'     => Servers::first(),
            'projectId'  => Settings::defaultProjectId(),
            'project'    => $catalog ? $this->projectName(Settings::defaultProjectId()) : null,
            'planCount'  => $catalog ? count($catalog->plans()) : 0,
            'locations'  => $catalog ? count($catalog->locations()) : 0,
            'templates'  => $catalog ? count($catalog->templates()) : 0,
            'products'   => count(Products::ids()),
        ));
    }

    private function pageCreator()
    {
        $catalog = $this->catalog();

        return $this->render('creator', array(
            'apiError'   => $this->apiError,
            'projectId'  => Settings::defaultProjectId(),
            'plans'      => $catalog ? $catalog->plans() : array(),
            'existing'   => Capsule::table('tblproducts')
                ->where('servertype', Products::SERVER_MODULE)
                ->pluck('configoption2')
                ->unique()
                ->all(),
            'groups'     => Capsule::table('tblproductgroups')->orderBy('order')->orderBy('name')->pluck('name', 'id')->all(),
            'currencies' => Capsule::table('tblcurrencies')->orderBy('default', 'desc')->orderBy('code')->get(),
        ));
    }

    private function pageProducts()
    {
        return $this->render('products', array(
            'products' => Products::all(),
        ));
    }

    private function pageLocations()
    {
        $catalog = $this->catalog();

        return $this->render('toggles', array(
            'apiError' => $this->apiError,
            'kind'     => 'locations',
            'intro'    => $this->t('locationsIntro'),
            'items'    => $catalog ? $catalog->locations() : array(),
            'disabled' => Settings::disabledLocations(),
        ));
    }

    private function pageTemplates()
    {
        $catalog = $this->catalog();
        $items = array();
        if ($catalog)
        {
            foreach ($catalog->templates() as $name => $template)
            {
                $items[$name] = $template['label'] . ($template['type'] === 'app' ? ' (' . $this->t('application') . ')' : '');
            }
        }

        return $this->render('toggles', array(
            'apiError' => $this->apiError,
            'kind'     => 'templates',
            'intro'    => $this->t('templatesIntro'),
            'items'    => $items,
            'disabled' => Settings::disabledTemplates(),
        ));
    }

    private function handlePost($page, $action, array $post)
    {
        try
        {
            switch ($action)
            {
                case 'createProduct':
                    $this->createProduct($post);
                    break;
                case 'createAllProducts':
                    $this->createAllProducts($post);
                    break;
                case 'syncProducts':
                    $this->syncProducts();
                    break;
                case 'saveLocations':
                    Settings::setDisabledLocations($this->disabledFromPost($post));
                    Products::applyVisibility();
                    $this->flash('success', $this->t('locationsSaved'));
                    break;
                case 'saveTemplates':
                    Settings::setDisabledTemplates($this->disabledFromPost($post));
                    Products::applyVisibility();
                    $this->flash('success', $this->t('templatesSaved'));
                    break;
                default:
                    $this->flash('danger', $this->t('unknownAction'));
            }
        }
        catch (Exception $e)
        {
            logModuleCall('cubepath', 'addon:' . $action, $post, $e->getMessage());
            $this->flash('danger', $e->getMessage());
        }

        $this->redirect($page);
    }

    private function createProduct(array $post)
    {
        $catalog = $this->requireCatalog();
        $plan = $catalog->plan(isset($post['plan']) ? (string)$post['plan'] : '');
        if (!$plan)
        {
            throw new Exception($this->t('planNotFound'));
        }
        if (!$plan['available'])
        {
            throw new Exception($this->t('planOutOfStock'));
        }

        $name = trim(isset($post['name']) ? (string)$post['name'] : '');
        $payType = isset($post['paytype']) && in_array($post['paytype'], array('free', 'onetime', 'recurring'), true)
            ? $post['paytype']
            : 'recurring';

        $pricing = array();
        if ($payType !== 'free')
        {
            foreach ($this->currencyIds() as $currencyId)
            {
                $monthly = isset($post['price'][$currencyId]) ? str_replace(',', '.', trim($post['price'][$currencyId])) : '';
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
            $this->groupFromPost($post),
            $payType,
            Products::monthlyPricing($pricing)
        );

        $this->flash('success', sprintf($this->t('productCreated'), $productId));
    }

    private function createAllProducts(array $post)
    {
        $catalog = $this->requireCatalog();
        $groupId = $this->groupFromPost($post);
        $markup = isset($post['markup']) ? (float)str_replace(',', '.', $post['markup']) : 0.0;

        $existing = Capsule::table('tblproducts')
            ->where('servertype', Products::SERVER_MODULE)
            ->pluck('configoption2')
            ->all();

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

            Products::create($catalog, $plan, $plan['plan_name'], $groupId, 'recurring', Products::monthlyPricing($pricing));
            $created++;
        }

        $this->flash('success', sprintf($this->t('productsCreated'), $created));
    }

    private function syncProducts()
    {
        $catalog = $this->requireCatalog();
        $errors = array();

        foreach (Products::ids() as $productId)
        {
            try
            {
                Products::ensureCustomFields($productId);
                Products::syncConfigurableOptions($productId, $catalog);
            }
            catch (Exception $e)
            {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors)
        {
            $this->flash('warning', implode('<br>', array_map('htmlspecialchars', $errors)), true);
        }
        else
        {
            $this->flash('success', $this->t('productsSynced'));
        }
    }

    /**
     * Toggle forms post the enabled values; everything else is disabled.
     */
    private function disabledFromPost(array $post)
    {
        $all = isset($post['all']) && is_array($post['all']) ? $post['all'] : array();
        $enabled = isset($post['enabled']) && is_array($post['enabled']) ? $post['enabled'] : array();

        return array_values(array_diff($all, $enabled));
    }

    private function groupFromPost(array $post)
    {
        if (isset($post['gid']) && $post['gid'] === 'new')
        {
            $name = trim(isset($post['new_group']) ? (string)$post['new_group'] : '');
            if ($name === '')
            {
                throw new Exception($this->t('groupRequired'));
            }

            return Products::createGroup($name);
        }

        $groupId = isset($post['gid']) ? (int)$post['gid'] : 0;
        if (!Capsule::table('tblproductgroups')->where('id', $groupId)->exists())
        {
            throw new Exception($this->t('groupRequired'));
        }

        return $groupId;
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

    private function requireCatalog()
    {
        $catalog = $this->catalog();
        if (!$catalog)
        {
            throw new Exception($this->apiError);
        }

        return $catalog;
    }

    private function flash($type, $message, $html = false)
    {
        $_SESSION[self::FLASH_KEY] = array('type' => $type, 'message' => $message, 'html' => $html);
    }

    private function takeFlash()
    {
        $flash = isset($_SESSION[self::FLASH_KEY]) ? $_SESSION[self::FLASH_KEY] : null;
        unset($_SESSION[self::FLASH_KEY]);

        return $flash;
    }

    private function redirect($page)
    {
        header('Location: ' . $this->url($page));
        exit;
    }

    public function url($page, array $params = array())
    {
        return $this->moduleLink . '&' . http_build_query(array('page' => $page) + $params);
    }

    public function t($key)
    {
        return isset($this->lang[$key]) ? $this->lang[$key] : $key;
    }

    private function render($view, array $data)
    {
        $e = function ($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        };
        $t = array($this, 't');
        $url = array($this, 'url');
        $token = generate_token('form');
        $pages = self::PAGES;

        extract($data, EXTR_SKIP);
        ob_start();
        include dirname(dirname(__DIR__)) . '/views/admin/' . $view . '.php';

        return ob_get_clean();
    }
}
