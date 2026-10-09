<?php
/**
 * CubePath Cloud WHMCS Module - Autoloader
 *
 * Registers class autoloading for the CubePath server module and
 * loads the Composer autoloader from the CubePath addon.
 */

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

defined('CUBEPATHDIR') or define('CUBEPATHDIR', __DIR__ . DS);

if (!function_exists('cubepathClassLoader'))
{
    /**
     * Autoload classes from the module's class/, controller/, and helper/ directories.
     *
     * Class name mapping examples:
     *   Cubepath        -> class/cubepath.class.php
     *   PanelController -> controller/panel.controller.php
     *   CubepathHelper  -> helper/cubepath.helper.php
     *   ActivityHelper  -> helper/activity.helper.php
     *
     * @param string $classname The class name to load
     * @return bool True if the class file was found and loaded
     */
    function cubepathClassLoader($classname)
    {
        $extensions = array('.php', '.class.php', '.helper.php', '.controller.php');

        if (class_exists($classname, false))
        {
            return false;
        }

        // Split CamelCase into dot-separated segments (e.g. PanelController -> panel.controller)
        $class = explode('.', strtolower(strval(strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $classname)))));
        $file = __DIR__;

        if (isset($class[1]))
        {
            // Two-part name: second segment is the subdirectory, first is the filename
            // e.g. PanelController -> controller/panel
            $file .= DS . $class[1] . DS . $class[0];
        }
        else
        {
            // Single-part name: look in class/ directory
            $file .= DS . 'class' . DS . $class[0];
        }

        foreach ($extensions as $ext)
        {
            $fileClass = $file . $ext;
            if (file_exists($fileClass) && is_readable($fileClass) && !class_exists($classname, false))
            {
                require_once($fileClass);
                return true;
            }
        }

        return false;
    }

    spl_autoload_register('cubepathClassLoader');

    // Load Composer autoloader from the CubePath addon (provides the CubePath PHP SDK)
    $composerAutoload = dirname(dirname(__DIR__)) . DS . 'addons' . DS . 'cubepath' . DS . 'vendor' . DS . 'autoload.php';
    if (file_exists($composerAutoload))
    {
        require_once $composerAutoload;
    }
}
