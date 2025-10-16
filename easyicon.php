<?php

/*
Plugin Name: Easy Icon
Plugin URI: https://github.com/FARN-Design/easyicon
Description: A plugin to load and use various icon fonts with ease.
Version: 1.0.0
Author: Farnlabs
Author URI: https://profiles.wordpress.org/farndesign/
License: GPLv3
Text Domain: easyvcard
Domain Path: src/resources/language
*/

require __DIR__ . '/vendor/autoload.php';

// Fallback autoloader for FontLib when Composer's autoload doesn't register it (e.g., custom package install without autoload metadata)
if (!class_exists(\FontLib\Font::class)) {
    spl_autoload_register(function ($class) {
        if (strpos($class, 'FontLib\\') === 0) {
            $baseDir = __DIR__ . '/vendor/phenx/php-font-lib/src/';
            $relativePath = str_replace('\\', '/', $class) . '.php';
            $file = $baseDir . $relativePath;
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

use Farn\EasyIcon\database\Settings;
use Farn\EasyIcon\menuPages\SettingsPage;
use Farn\EasyIcon\blocks\Blocks;
use Farn\EasyIcon\iconHandler\IconHandler;
use Farn\EasyIcon\restEndpoints\RestHandler;

if (! defined( 'ABSPATH' ) ) {
    die;
}

$plugin = new EasyIcon();

class EasyIcon
{
    public static string $prefix = "ei_";
    public static string $software = "EasyIcon";
    public static string $pluginSlug = "easyIcon";

    /**
     * @var string Path to the main plugin directory
     */
    public static string $pluginDirPath;

    public static string $pluginBaseName;

    public static string $pathToMainPluginFile;

    public function __construct() {

        self::$pluginDirPath = plugin_dir_path(__FILE__);
        self::$pluginBaseName = plugin_basename(__FILE__);
        self::$pathToMainPluginFile = EasyIcon::$pluginDirPath . EasyIcon::$pluginSlug . ".php";

        Settings::setup();
        SettingsPage::getInstance();
        Blocks::setup();
        IconHandler::getInstance();

        add_action('rest_api_init', [RestHandler::class, 'register_routes']);

        //Activation and Deactivation
        register_activation_hook( __FILE__, [self::class, "pluginActivation"] );
        register_deactivation_hook( __FILE__, [self::class, "pluginDeactivation"] );
    }

    public static function pluginActivation():void {

    }

    public static function pluginDeactivation():void {

    }
}
