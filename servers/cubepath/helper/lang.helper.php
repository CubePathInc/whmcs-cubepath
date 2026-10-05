<?php
/**
 * CubePath Cloud WHMCS Module - Language Helper
 *
 * Singleton helper that loads and retrieves translated strings from
 * language files in the lang/ directory. Supports dot-notation keys
 * and variable substitution.
 */

class LangHelper
{
    /** @var LangHelper|null Singleton instance */
    private static $instance;

    /** @var array Loaded language strings */
    public $langs = array();

    /** @var string Path to the lang/ directory */
    private $dir;

    /** @var string Currently loaded language */
    private $currentLang;

    /**
     * Retrieve a translated string by dot-notation key.
     *
     * @param string|null $key Dot-notation key (e.g., 'core.client.action_not_found'), or null for all strings
     * @param string|false $var Optional variable to substitute for %var% placeholder
     * @return mixed Translated string, array of all strings, or empty string if key not found
     */
    public static function T($key = null, $var = false)
    {
        $lang = self::getInstance(CUBEPATHDIR . 'lang')->langs;

        if ($key === null)
        {
            return $lang;
        }

        $keyPath = explode('.', $key);
        foreach ($keyPath as $segment)
        {
            if (isset($lang[$segment]))
            {
                $lang = $lang[$segment];
            }
            else
            {
                return '';
            }
        }

        if ($var !== false && is_string($lang))
        {
            $lang = str_replace('%var%', $var, $lang);
        }

        return $lang;
    }

    /**
     * Get the singleton instance, initializing with the given directory if needed.
     *
     * @param string|null $dir  Path to the lang/ directory
     * @param string|null $lang Language name override
     * @return LangHelper
     */
    public static function getInstance($dir = null, $lang = null)
    {
        if (self::$instance === null)
        {
            self::$instance = new self();
            self::$instance->dir = $dir;
            self::$instance->loadLang('english');

            if (!$lang)
            {
                $lang = self::getLang();
            }

            if ($lang && $lang != 'english')
            {
                self::$instance->loadLang($lang);
            }
        }
        return self::$instance;
    }

    /**
     * Load a language file and merge its strings into the current set.
     *
     * @param string $lang Language name (filename without extension)
     */
    public static function loadLang($lang)
    {
        $file = self::getInstance()->dir . DS . $lang . '.php';
        if (file_exists($file))
        {
            include $file;
            if (isset($_LANG) && is_array($_LANG))
            {
                self::getInstance()->langs = array_merge(self::getInstance()->langs, $_LANG);
                self::getInstance()->currentLang = $lang;
            }
        }
    }

    /**
     * Detect the current language from session, client settings, or system default.
     *
     * @return string Language name (lowercase)
     */
    public static function getLang()
    {
        $language = '';

        if (isset($_SESSION['Language']))
        {
            $language = strtolower($_SESSION['Language']);
        }
        elseif (isset($_SESSION['uid']))
        {
            try
            {
                $result = \Illuminate\Database\Capsule\Manager::table('tblclients')
                    ->where('id', $_SESSION['uid'])
                    ->value('language');
                if ($result)
                {
                    $language = strtolower($result);
                }
            }
            catch (\Exception $e)
            {
                // Fallback if database query fails
            }
        }

        if (!$language)
        {
            try
            {
                $result = \Illuminate\Database\Capsule\Manager::table('tblconfiguration')
                    ->where('setting', 'Language')
                    ->value('value');
                if ($result)
                {
                    $language = strtolower($result);
                }
            }
            catch (\Exception $e)
            {
                // Fallback if database query fails
            }
        }

        if (!$language)
        {
            $language = 'english';
        }

        return $language;
    }
}
