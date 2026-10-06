<?php

namespace CubePath\WHMCS\Addon;

use Cubepath\CubepathClient;

/**
 * Projects the API token has access to, for choosing where new VPS are created.
 */
class Projects
{
    /**
     * Short timeout so an unreachable API does not stall WHMCS admin pages.
     */
    const TIMEOUT = 10;

    /**
     * @return array<string, string> project id => project name, sorted by name
     */
    public static function fetch($token)
    {
        $client = new CubepathClient($token, array('timeout' => self::TIMEOUT, 'max_retries' => 0));

        return self::names($client->projects()->list());
    }

    /**
     * @param array $list GET /projects/ response: entries with a nested "project" object
     * @return array<string, string> project id => project name, sorted by name
     */
    public static function names(array $list)
    {
        $names = array();
        foreach ($list as $entry)
        {
            $project = isset($entry['project']) && is_array($entry['project']) ? $entry['project'] : $entry;
            if (isset($project['id']))
            {
                $id = (string)$project['id'];
                $names[$id] = isset($project['name']) && $project['name'] !== '' ? (string)$project['name'] : 'Project ' . $id;
            }
        }
        asort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    /**
     * Dropdown options for a project setting. A saved project that is no longer
     * listed is kept so saving the form does not silently change it.
     *
     * @param array<string, string> $names
     * @param string $current
     * @return array<string, string>
     */
    public static function options(array $names, $current)
    {
        $options = array('' => '-- Select a project --') + $names;
        if ($current !== '' && !isset($options[$current]))
        {
            $options[$current] = 'Project ' . $current . ' (not found)';
        }

        return $options;
    }
}
