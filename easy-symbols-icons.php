<?php

/*
Plugin Name: Easy Symbols & Icons
Plugin URI: https://github.com/FARN-Design/easyiconfonts
Description: A plugin to load and use various icon fonts with ease.
Version: 1.0.0
Author: Farnlabs
Author URI: https://profiles.wordpress.org/farndesign/
License: GPLv3
Text Domain: easy-symbols-icons
Domain Path: /src/resources/language
*/

if (! defined( 'ABSPATH' ) ) {
	die;
}

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

use Farn\EasySymbolsIcons\database\Settings;
use Farn\EasySymbolsIcons\menuPages\SettingsPage;
use Farn\EasySymbolsIcons\blocks\Blocks;
use Farn\EasySymbolsIcons\iconHandler\IconHandler;
use Farn\EasySymbolsIcons\restEndpoints\RestHandler;

$plugin = new EasyIcon();

class EasyIcon
{
    public static string $prefix = "esi_";
    public static string $software = "EasySymbolsIcon";
    public static string $pluginSlug = "easy-symbols-icons";

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
