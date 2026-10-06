<?php

namespace CubePath\WHMCS\Addon;

use Cubepath\CubepathClient;

/**
 * VPS plans, locations and templates offered by the CubePath API,
 * normalised from the GET /pricing response.
 *
 * /pricing nests plans under locations and clusters
 * (vps.locations[].clusters[].plans[]), so the same plan appears once per
 * location that offers it. This class flattens that tree into one entry
 * per plan with the list of locations where it can be ordered.
 */
class Catalog
{
    /** Plan status values returned by the API. */
    const PLAN_OUT_OF_STOCK = 1;
    const PLAN_AVAILABLE = 2;

    const HOURS_PER_MONTH = 730;

    /** @var array */
    private $vps;

    public function __construct(array $pricing)
    {
        $this->vps = isset($pricing['vps']) && is_array($pricing['vps']) ? $pricing['vps'] : array();
    }

    public static function fetch(CubepathClient $client)
    {
        return new self($client->pricing()->get());
    }

    /**
     * @return array<string, string> location_name => description
     */
    public function locations()
    {
        $locations = array();
        foreach ($this->listOf($this->vps, 'locations') as $location)
        {
            if (empty($location['location_name']))
            {
                continue;
            }

            $name = (string)$location['location_name'];
            $locations[$name] = !empty($location['description']) ? (string)$location['description'] : $name;
        }

        ksort($locations);

        return $locations;
    }

    /**
     * @return array<string, array> plan_name => plan details, sorted by price;
     *                              "locations" only lists where it is in stock
     */
    public function plans()
    {
        $plans = array();
        foreach ($this->listOf($this->vps, 'locations') as $location)
        {
            $locationName = isset($location['location_name']) ? (string)$location['location_name'] : '';

            foreach ($this->listOf($location, 'clusters') as $cluster)
            {
                foreach ($this->listOf($cluster, 'plans') as $plan)
                {
                    if (empty($plan['plan_name']))
                    {
                        continue;
                    }

                    $name = (string)$plan['plan_name'];
                    if (!isset($plans[$name]))
                    {
                        $plans[$name] = array(
                            'plan_name'      => $name,
                            'cpu'            => isset($plan['cpu']) ? (int)$plan['cpu'] : 0,
                            'ram_mb'         => isset($plan['ram']) ? (int)$plan['ram'] : 0,
                            'storage_gb'     => isset($plan['storage']) ? (int)$plan['storage'] : 0,
                            'bandwidth_tb'   => isset($plan['bandwidth']) ? (int)$plan['bandwidth'] : 0,
                            'price_per_hour' => isset($plan['price_per_hour']) ? (float)$plan['price_per_hour'] : 0.0,
                            'locations'      => array(),
                            'available'      => false,
                        );
                    }

                    // Out of stock plans are listed but cannot be created there.
                    $status = isset($plan['status']) ? (int)$plan['status'] : self::PLAN_AVAILABLE;
                    if ($status !== self::PLAN_AVAILABLE)
                    {
                        continue;
                    }

                    $plans[$name]['available'] = true;
                    if ($locationName !== '' && !in_array($locationName, $plans[$name]['locations'], true))
                    {
                        $plans[$name]['locations'][] = $locationName;
                    }
                }
            }
        }

        foreach ($plans as &$plan)
        {
            sort($plan['locations']);
            $plan['price_per_month'] = round($plan['price_per_hour'] * self::HOURS_PER_MONTH, 2);
        }
        unset($plan);

        uasort($plans, function ($a, $b) {
            return $a['price_per_hour'] == $b['price_per_hour']
                ? strcmp($a['plan_name'], $b['plan_name'])
                : ($a['price_per_hour'] < $b['price_per_hour'] ? -1 : 1);
        });

        return $plans;
    }

    public function plan($planName)
    {
        $plans = $this->plans();

        return isset($plans[$planName]) ? $plans[$planName] : null;
    }

    /**
     * Operating systems and one-click applications that can be deployed.
     *
     * @return array<string, array> template_name => {label, type, min_ram_mb}
     */
    public function templates()
    {
        $templates = array();

        foreach ($this->listOf($this->vps, 'templates') as $os)
        {
            if (empty($os['template_name']))
            {
                continue;
            }

            $templates[(string)$os['template_name']] = array(
                'label'      => !empty($os['os_name']) ? (string)$os['os_name'] : (string)$os['template_name'],
                'type'       => 'os',
                'min_ram_mb' => 0,
            );
        }

        foreach ($this->listOf($this->vps, 'apps') as $app)
        {
            if (empty($app['template_name']))
            {
                continue;
            }

            $label = !empty($app['app_name']) ? (string)$app['app_name'] : (string)$app['template_name'];
            if (!empty($app['version']))
            {
                $label .= ' ' . $app['version'];
            }

            $templates[(string)$app['template_name']] = array(
                'label'      => $label,
                'type'       => 'app',
                'min_ram_mb' => isset($app['min_ram']) ? (int)$app['min_ram'] : 0,
            );
        }

        uasort($templates, function ($a, $b) {
            return $a['type'] === $b['type'] ? strcasecmp($a['label'], $b['label']) : ($a['type'] === 'os' ? -1 : 1);
        });

        return $templates;
    }

    /**
     * Templates that fit in the given plan's memory.
     */
    public function templatesForPlan(array $plan)
    {
        return array_filter($this->templates(), function ($template) use ($plan) {
            return $template['min_ram_mb'] <= $plan['ram_mb'];
        });
    }

    public static function describePlan(array $plan)
    {
        $ram = $plan['ram_mb'] >= 1024 ? round($plan['ram_mb'] / 1024, 1) . ' GB' : $plan['ram_mb'] . ' MB';

        return sprintf(
            '%s: %d vCPU, %s RAM, %d GB disk, %d TB transfer',
            $plan['plan_name'],
            $plan['cpu'],
            $ram,
            $plan['storage_gb'],
            $plan['bandwidth_tb']
        );
    }

    private function listOf($array, $key)
    {
        return isset($array[$key]) && is_array($array[$key]) ? $array[$key] : array();
    }
}
