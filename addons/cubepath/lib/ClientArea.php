<?php

namespace CubePath\WHMCS\Addon;

use WHMCS\Database\Capsule;

/**
 * The CubePath client area: the "cubepath" theme (templates/cubepath), the
 * "cubepath_cart" order form (templates/orderforms/cubepath_cart) and the
 * hooks that leave only what a VPS reseller sells, VPS and their management.
 *
 * Turning it on makes the theme the system theme and remembers the previous
 * one, so turning it off restores it.
 */
class ClientArea
{
    const THEME = 'cubepath';
    const ORDER_FORM = 'cubepath_cart';

    const SETTING = 'clientArea';
    const PREVIOUS_THEME = 'previousTheme';

    /** Location labels ("Barcelona, Spain") to the flags in modules/servers/cubepath/assets/flags. */
    const COUNTRIES = array(
        '/spain|españa|madrid|barcelona/i'                                                     => 'es',
        '/netherlands|amsterdam/i'                                                             => 'nl',
        '/germany|frankfurt|falkenstein|nuremberg/i'                                           => 'de',
        '/france|paris/i'                                                                      => 'fr',
        '/united kingdom|london|\buk\b/i'                                                      => 'gb',
        '/texas|florida|virginia|california|new york|illinois|oregon|ashburn|usa|united states/i' => 'us',
    );

    public static function enabled()
    {
        return Settings::get(self::SETTING) === 'on';
    }

    public static function themeInstalled()
    {
        return is_file(ROOTDIR . '/templates/' . self::THEME . '/theme.yaml');
    }

    public static function orderFormInstalled()
    {
        return is_file(ROOTDIR . '/templates/orderforms/' . self::ORDER_FORM . '/theme.yaml');
    }

    /**
     * Make the CubePath theme the client area theme and the CubePath order
     * form the one of every CubePath product group.
     */
    public static function enable()
    {
        if (!self::themeInstalled())
        {
            throw new \RuntimeException('The templates/cubepath folder is missing. Upload the templates folder of the release to the WHMCS root.');
        }

        $current = (string)Capsule::table('tblconfiguration')->where('setting', 'Template')->value('value');
        if ($current !== self::THEME)
        {
            Settings::set(self::PREVIOUS_THEME, $current);
        }

        self::setSystemTheme(self::THEME);
        self::applyOrderForm();
        Settings::set(self::SETTING, 'on');
    }

    /**
     * Go back to the theme used before. The order form of the product groups
     * is kept: it works with any theme.
     */
    public static function disable()
    {
        $current = (string)Capsule::table('tblconfiguration')->where('setting', 'Template')->value('value');
        if ($current === self::THEME)
        {
            $previous = (string)Settings::get(self::PREVIOUS_THEME);
            self::setSystemTheme($previous !== '' && $previous !== self::THEME ? $previous : 'twenty-one');
        }

        Settings::set(self::SETTING, '');
    }

    /**
     * Use the CubePath order form in every group that has CubePath products.
     */
    public static function applyOrderForm()
    {
        if (!self::orderFormInstalled())
        {
            return;
        }

        Capsule::table('tblproductgroups')
            ->whereIn('id', self::groupIds())
            ->update(array('orderfrmtpl' => self::ORDER_FORM));
    }

    /**
     * @return int[] Product groups with at least one visible CubePath product
     */
    public static function groupIds()
    {
        return array_map('intval', Capsule::table('tblproducts')
            ->where('servertype', Products::SERVER_MODULE)
            ->where('hidden', 0)
            ->where('retired', 0)
            ->distinct()
            ->pluck('gid')
            ->all());
    }

    /**
     * Visible product groups that sell CubePath VPS, in store order, with the
     * lowest monthly price of their products.
     *
     * @return array[] {id, name, tagline, url, count, from}
     */
    public static function groups($currencyId)
    {
        $groups = array();
        $rows = Capsule::table('tblproductgroups')
            ->whereIn('id', self::groupIds())
            ->where('hidden', 0)
            ->orderBy('order')
            ->orderBy('id')
            ->get(array('id', 'name', 'tagline'));

        foreach ($rows as $row)
        {
            $productIds = Capsule::table('tblproducts')
                ->where('gid', $row->id)
                ->where('servertype', Products::SERVER_MODULE)
                ->where('hidden', 0)
                ->where('retired', 0)
                ->pluck('id')
                ->all();

            // -1 is "cycle not offered"; a free product would show 0.00.
            $from = Capsule::table('tblpricing')
                ->where('type', 'product')
                ->where('currency', $currencyId)
                ->whereIn('relid', $productIds)
                ->where('monthly', '>=', 0)
                ->min('monthly');

            $groups[] = array(
                'id'      => (int)$row->id,
                'name'    => (string)$row->name,
                'tagline' => (string)$row->tagline,
                'url'     => self::groupUrl((int)$row->id),
                'count'   => count($productIds),
                'from'    => $from !== null ? self::money((float)$from, $currencyId) : '',
            );
        }

        return $groups;
    }

    /**
     * Store page of a product group relative to the WHMCS root: the friendly
     * /store/<slug> route when WHMCS has one, so the menu can match it.
     */
    private static function groupUrl($groupId)
    {
        $group = class_exists('\\WHMCS\\Product\\Group') ? \WHMCS\Product\Group::find($groupId) : null;
        if (!$group || !method_exists($group, 'getRoutePath'))
        {
            return 'cart.php?gid=' . $groupId;
        }

        $path = (string)$group->getRoutePath();
        $base = rtrim((string)parse_url(self::systemUrl(), PHP_URL_PATH), '/');
        if ($base !== '' && strpos($path, $base . '/') === 0)
        {
            $path = substr($path, strlen($base));
        }

        return ltrim($path, '/');
    }

    /**
     * The client's CubePath services, newest first.
     *
     * @param bool $closed Include terminated, cancelled and fraud services
     * @return array[] {id, product, hostname, ip, status, location, flag, nextDue, amount, cycle}
     */
    public static function services($clientId, $closed = false)
    {
        $query = Capsule::table('tblhosting as h')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('h.userid', $clientId)
            ->where('p.servertype', Products::SERVER_MODULE);
        if (!$closed)
        {
            $query->whereIn('h.domainstatus', array('Active', 'Suspended', 'Pending'));
        }

        $rows = $query
            ->orderByRaw("FIELD(h.domainstatus, 'Active', 'Suspended', 'Pending') = 0")
            ->orderBy('h.id', 'desc')
            ->get(array('h.id', 'h.domain', 'h.dedicatedip', 'h.assignedips', 'h.domainstatus', 'h.nextduedate', 'h.amount', 'h.billingcycle', 'p.name'));

        $currencyId = (int)Capsule::table('tblclients')->where('id', $clientId)->value('currency');
        $locations = self::serviceLocations($rows->pluck('id')->all());

        $services = array();
        foreach ($rows as $row)
        {
            $location = isset($locations[$row->id]) ? $locations[$row->id] : '';
            // IPv6 only VPS have no dedicated IP; the module stores their IPv6 in assignedips.
            $ip = trim((string)$row->dedicatedip);
            if ($ip === '')
            {
                $ip = trim(strtok((string)$row->assignedips, "\r\n") ?: '');
            }
            $services[] = array(
                'id'       => (int)$row->id,
                'product'  => (string)$row->name,
                'hostname' => (string)$row->domain,
                'ip'       => $ip,
                'status'   => strtolower((string)$row->domainstatus),
                'location' => $location,
                'flag'     => self::flag($location),
                'nextDue'  => $row->nextduedate && $row->nextduedate !== '0000-00-00' ? fromMySQLDate($row->nextduedate) : '',
                'amount'   => self::money((float)$row->amount, $currencyId),
                'cycle'    => (string)$row->billingcycle,
            );
        }

        return $services;
    }

    /**
     * Rebuild the top level menu: overview, VPS, deploy, billing and support
     * for clients; home, VPS plans and contact for visitors. Domains, website
     * security, announcements, knowledgebase and the rest are left out.
     */
    public static function primaryNavbar($navbar, $loggedIn)
    {
        $t = self::strings();

        foreach ($navbar->getChildren() as $child)
        {
            $navbar->removeChild($child->getName());
        }

        $navbar->addChild('cp-home', array(
            'label' => $loggedIn ? $t['overview'] : $t['home'],
            'uri'   => $loggedIn ? 'clientarea.php' : 'index.php',
            'icon'  => 'fas fa-th-large',
            'order' => 10,
        ));

        if ($loggedIn)
        {
            $navbar->addChild('cp-servers', array(
                'label' => $t['servers'],
                'uri'   => 'clientarea.php?action=services',
                'icon'  => 'fas fa-server',
                'order' => 20,
            ));
        }

        $deploy = $navbar->addChild('cp-deploy', array(
            'label' => $loggedIn ? $t['deploy'] : $t['plans'],
            'uri'   => 'cart.php',
            'icon'  => 'fas fa-plus-circle',
            'order' => 30,
        ));
        foreach (self::groups(self::currencyId()) as $i => $group)
        {
            $deploy->addChild('cp-group-' . $group['id'], array(
                'label' => $group['name'],
                'uri'   => $group['url'],
                'order' => $i + 1,
            ));
        }

        if ($loggedIn)
        {
            $billing = $navbar->addChild('cp-billing', array(
                'label' => $t['billing'],
                'uri'   => 'clientarea.php?action=invoices',
                'icon'  => 'fas fa-file-invoice-dollar',
                'order' => 40,
            ));
            $billing->addChild('cp-invoices', array('label' => $t['invoices'], 'uri' => 'clientarea.php?action=invoices', 'order' => 1));
            $billing->addChild('cp-payment-methods', array('label' => $t['paymentMethods'], 'uri' => routePath('account-paymentmethods'), 'order' => 2));
            if (!empty($GLOBALS['CONFIG']['AddFundsEnabled']))
            {
                $billing->addChild('cp-add-funds', array('label' => $t['addFunds'], 'uri' => 'clientarea.php?action=addfunds', 'order' => 3));
            }

            $support = $navbar->addChild('cp-support', array(
                'label' => $t['support'],
                'uri'   => 'supporttickets.php',
                'icon'  => 'fas fa-life-ring',
                'order' => 50,
            ));
            $support->addChild('cp-tickets', array('label' => $t['tickets'], 'uri' => 'supporttickets.php', 'order' => 1));
            $support->addChild('cp-new-ticket', array('label' => $t['newTicket'], 'uri' => 'submitticket.php', 'order' => 2));
        }
        else
        {
            $navbar->addChild('cp-contact', array(
                'label' => $t['contact'],
                'uri'   => 'contact.php',
                'icon'  => 'fas fa-envelope',
                'order' => 40,
            ));
        }
    }

    /**
     * Cart sidebar: only the CubePath groups and no domain actions.
     */
    public static function cartSidebar($sidebar)
    {
        $groups = self::groupIds();
        $slugs = Capsule::schema()->hasColumn('tblproductgroups', 'slug')
            ? Capsule::table('tblproductgroups')->whereIn('id', $groups)->pluck('slug')->all()
            : array();

        // Categories link to cart.php?gid=N or, with friendly URLs, to /store/<slug>.
        $categories = $sidebar->getChild('Categories');
        if ($categories)
        {
            foreach ($categories->getChildren() as $child)
            {
                $uri = (string)$child->getUri();
                $vps = (preg_match('/[?&]gid=(\d+)/', $uri, $m) && in_array((int)$m[1], $groups, true))
                    || (preg_match('#/store/([^/?&]+)#', $uri, $m) && in_array(urldecode($m[1]), $slugs, true));
                if (!$vps)
                {
                    $categories->removeChild($child->getName());
                }
            }
        }

        $actions = $sidebar->getChild('Actions');
        if ($actions)
        {
            foreach ($actions->getChildren() as $child)
            {
                if (preg_match('/domain|renew/i', (string)$child->getUri() . ' ' . $child->getName()))
                {
                    $actions->removeChild($child->getName());
                }
            }
        }
    }

    /**
     * Where to send a cart request for something that is not a CubePath VPS
     * (another product group, domains, other add-ons), or null to let it through.
     */
    public static function cartRedirect(array $query)
    {
        $groups = self::groupIds();
        $visible = self::groups(self::currencyId());
        if (!$visible)
        {
            // No VPS on sale yet: leave the cart as it is.
            return null;
        }
        $store = $visible[0]['url'];

        $action = isset($query['a']) ? (string)$query['a'] : '';
        if (in_array($action, array('add', 'confproduct', 'view', 'checkout', 'fraudcheck', 'complete', 'confdomains', 'addons', 'remove', 'empty', 'applypromo', 'removepromo', 'setstateandcountry', 'cyclechange', 'updateConfigurableOptions'), true))
        {
            if ($action === 'add' && !empty($query['domain']))
            {
                return $store;
            }
            if ($action === 'add' && !empty($query['pid']) && !in_array((int)$query['pid'], array_map('intval', Products::ids()), true))
            {
                return $store;
            }

            return null;
        }
        if ($action !== '')
        {
            // Domain registration, transfer, renewals and other non-VPS pages.
            return in_array($action, array('login'), true) ? null : $store;
        }

        if (!empty($query['gid']))
        {
            return in_array((int)$query['gid'], $groups, true) ? null : $store;
        }

        // cart.php on its own lists the first group of the store, which may not sell VPS.
        return $store;
    }

    /**
     * Where to send a request for a domain page served outside cart.php
     * (renewals, pricing), or null to let it through.
     *
     * @param string $route The rp parameter, or the path with friendly URLs
     */
    public static function routeRedirect($route)
    {
        if (!preg_match('#/(cart/domain|domain/pricing)(/|$)#', '/' . ltrim($route, '/')))
        {
            return null;
        }
        $visible = self::groups(self::currencyId());

        return $visible ? $visible[0]['url'] : null;
    }

    /**
     * Variables the theme reads as {$cp}.
     */
    public static function templateVars(array $vars)
    {
        $data = array(
            't'      => self::strings(),
            'flags'  => self::systemUrl() . 'modules/servers/cubepath/assets/flags/',
            'groups' => self::groups(self::currencyId()),
        );

        $clientId = isset($vars['clientsdetails']['userid']) ? (int)$vars['clientsdetails']['userid'] : 0;
        if (!$clientId && isset($_SESSION['uid']))
        {
            $clientId = (int)$_SESSION['uid'];
        }

        $template = isset($vars['templatefile']) ? $vars['templatefile'] : '';
        if ($clientId && in_array($template, array('clientareahome', 'clientareaproducts'), true))
        {
            $data['services'] = self::services($clientId, $template === 'clientareaproducts');
            $data['invoices'] = self::unpaidInvoices($clientId);
        }

        return $data;
    }

    /**
     * @return array[] {id, total, due, overdue}
     */
    private static function unpaidInvoices($clientId)
    {
        $currencyId = (int)Capsule::table('tblclients')->where('id', $clientId)->value('currency');
        $invoices = array();
        $rows = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->where('status', 'Unpaid')
            ->orderBy('duedate')
            ->limit(5)
            ->get(array('id', 'invoicenum', 'total', 'duedate'));

        foreach ($rows as $row)
        {
            $invoices[] = array(
                'id'      => (int)$row->id,
                'number'  => $row->invoicenum !== '' ? (string)$row->invoicenum : (string)$row->id,
                'total'   => self::money((float)$row->total, $currencyId),
                'due'     => fromMySQLDate($row->duedate),
                'overdue' => $row->duedate < date('Y-m-d'),
            );
        }

        return $invoices;
    }

    /**
     * @return array<int, string> service ID => location label
     */
    private static function serviceLocations(array $serviceIds)
    {
        if (!$serviceIds)
        {
            return array();
        }

        $rows = Capsule::table('tblhostingconfigoptions as hc')
            ->join('tblproductconfigoptions as o', 'o.id', '=', 'hc.configid')
            ->join('tblproductconfigoptionssub as s', 's.id', '=', 'hc.optionid')
            ->whereIn('hc.relid', $serviceIds)
            ->where('o.optionname', 'like', Products::OPTION_LOCATION . '|%')
            ->get(array('hc.relid', 's.optionname'));

        $locations = array();
        foreach ($rows as $row)
        {
            $parts = explode('|', (string)$row->optionname, 2);
            $locations[(int)$row->relid] = trim(isset($parts[1]) ? $parts[1] : $parts[0]);
        }

        return $locations;
    }

    private static function flag($location)
    {
        foreach (self::COUNTRIES as $pattern => $country)
        {
            if ($location !== '' && preg_match($pattern, $location))
            {
                return $country;
            }
        }

        return '';
    }

    private static function money($amount, $currencyId)
    {
        if (function_exists('formatCurrency'))
        {
            return (string)formatCurrency($amount, $currencyId ?: null);
        }

        return number_format($amount, 2);
    }

    /**
     * Currency the visitor or client sees prices in.
     */
    private static function currencyId()
    {
        if (!empty($_SESSION['uid']))
        {
            $id = (int)Capsule::table('tblclients')->where('id', (int)$_SESSION['uid'])->value('currency');
            if ($id)
            {
                return $id;
            }
        }
        if (!empty($_SESSION['currency']))
        {
            return (int)$_SESSION['currency'];
        }

        return (int)Capsule::table('tblcurrencies')->where('default', 1)->value('id');
    }

    /**
     * Theme texts WHMCS has no language key for, in the client's language.
     */
    public static function strings()
    {
        $language = '';
        if (class_exists('\Lang') && method_exists('\Lang', 'getName'))
        {
            $language = (string)\Lang::getName();
        }
        if ($language === '' && !empty($_SESSION['Language']))
        {
            $language = (string)$_SESSION['Language'];
        }
        if ($language === '' && !empty($GLOBALS['CONFIG']['Language']))
        {
            $language = (string)$GLOBALS['CONFIG']['Language'];
        }

        $es = stripos($language, 'spanish') === 0 || stripos($language, 'es') === 0;

        return $es ? array(
            'home'           => 'Inicio',
            'overview'       => 'Resumen',
            'servers'        => 'Mis VPS',
            'deploy'         => 'Desplegar VPS',
            'plans'          => 'Planes VPS',
            'billing'        => 'Facturación',
            'invoices'       => 'Facturas',
            'paymentMethods' => 'Métodos de pago',
            'addFunds'       => 'Añadir saldo',
            'support'        => 'Soporte',
            'tickets'        => 'Mis tickets',
            'newTicket'      => 'Abrir ticket',
            'contact'        => 'Contacto',
            'account'        => 'Cuenta',
            'welcome'        => 'Hola, %s',
            'welcomeSub'     => 'Este es el estado de tus servidores y tu cuenta.',
            'activeServers'  => 'VPS activas',
            'unpaid'         => 'Facturas pendientes',
            'openTickets'    => 'Tickets abiertos',
            'credit'         => 'Saldo',
            'noServers'      => 'Todavía no tienes ninguna VPS',
            'noServersSub'   => 'Elige un plan, una ubicación y un sistema operativo, y la tendrás lista en unos minutos.',
            'manage'         => 'Gestionar',
            'viewAll'        => 'Ver todas',
            'payNow'         => 'Pagar',
            'overdue'        => 'Vencida',
            'due'            => 'Vence el %s',
            'allPaid'        => 'No tienes facturas pendientes.',
            'from'           => 'Desde',
            'perMonth'       => '/mes',
            'plansCount'     => '%d planes',
            'heroTitle'      => 'Servidores VPS listos en minutos',
            'heroSub'        => 'Almacenamiento NVMe, IPv4 e IPv6, protección DDoS y backups diarios opcionales, en varias ubicaciones.',
            'heroCta'        => 'Ver planes',
            'login'          => 'Acceder',
            'featureNvme'    => 'Almacenamiento NVMe',
            'featureNvmeSub' => 'Discos NVMe en todos los planes.',
            'featureDdos'    => 'Protección DDoS',
            'featureDdosSub' => 'Filtrado de ataques incluido.',
            'featureDeploy'  => 'Despliegue al momento',
            'featureDeploySub' => 'Tu servidor se crea en cuanto se paga el pedido.',
            'featureBackups' => 'Backups diarios',
            'featureBackupsSub' => 'Activa copias automáticas cuando quieras.',
            'choosePlan'     => 'Elige el tipo de servidor',
            'hostname'       => 'Hostname',
            'ip'             => 'IP',
            'location'       => 'Ubicación',
            'status'         => 'Estado',
            'renews'         => 'Renovación',
            'price'          => 'Precio',
            'specs'          => 'Recursos',
            'order'          => 'Contratar',
            'menu'           => 'Menú',
        ) : array(
            'home'           => 'Home',
            'overview'       => 'Overview',
            'servers'        => 'My VPS',
            'deploy'         => 'Deploy VPS',
            'plans'          => 'VPS plans',
            'billing'        => 'Billing',
            'invoices'       => 'Invoices',
            'paymentMethods' => 'Payment methods',
            'addFunds'       => 'Add funds',
            'support'        => 'Support',
            'tickets'        => 'My tickets',
            'newTicket'      => 'Open a ticket',
            'contact'        => 'Contact',
            'account'        => 'Account',
            'welcome'        => 'Hi, %s',
            'welcomeSub'     => 'Here is how your servers and your account are doing.',
            'activeServers'  => 'Active VPS',
            'unpaid'         => 'Unpaid invoices',
            'openTickets'    => 'Open tickets',
            'credit'         => 'Credit',
            'noServers'      => 'You have no VPS yet',
            'noServersSub'   => 'Pick a plan, a location and an operating system, and it will be ready in a few minutes.',
            'manage'         => 'Manage',
            'viewAll'        => 'View all',
            'payNow'         => 'Pay',
            'overdue'        => 'Overdue',
            'due'            => 'Due %s',
            'allPaid'        => 'You have no unpaid invoices.',
            'from'           => 'From',
            'perMonth'       => '/mo',
            'plansCount'     => '%d plans',
            'heroTitle'      => 'VPS servers ready in minutes',
            'heroSub'        => 'NVMe storage, IPv4 and IPv6, DDoS protection and optional daily backups, in several locations.',
            'heroCta'        => 'See plans',
            'login'          => 'Log in',
            'featureNvme'    => 'NVMe storage',
            'featureNvmeSub' => 'NVMe disks on every plan.',
            'featureDdos'    => 'DDoS protection',
            'featureDdosSub' => 'Attack filtering included.',
            'featureDeploy'  => 'Instant deployment',
            'featureDeploySub' => 'Your server is created as soon as the order is paid.',
            'featureBackups' => 'Daily backups',
            'featureBackupsSub' => 'Turn on automatic backups whenever you want.',
            'choosePlan'     => 'Choose the type of server',
            'hostname'       => 'Hostname',
            'ip'             => 'IP',
            'location'       => 'Location',
            'status'         => 'Status',
            'renews'         => 'Renews',
            'price'          => 'Price',
            'specs'          => 'Resources',
            'order'          => 'Order',
            'menu'           => 'Menu',
        );
    }

    /**
     * WHMCS System URL with a trailing slash.
     */
    public static function systemUrl()
    {
        $url = (string)Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');

        return rtrim($url, '/') . '/';
    }

    private static function setSystemTheme($theme)
    {
        if (Capsule::table('tblconfiguration')->where('setting', 'Template')->exists())
        {
            Capsule::table('tblconfiguration')->where('setting', 'Template')->update(array('value' => $theme));
        }
        else
        {
            Capsule::table('tblconfiguration')->insert(array('setting' => 'Template', 'value' => $theme));
        }
    }
}
