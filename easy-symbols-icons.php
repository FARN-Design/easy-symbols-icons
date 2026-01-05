<?php

/*
Plugin Name: Easy Symbols & Icons
Plugin URI: https://github.com/FARN-Design/easysymbolsicons
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

$plugin = new EasySymbolsIcons();

class EasySymbolsIcons
{
    public static string $prefix = "eics_";
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
        self::$pathToMainPluginFile = EasySymbolsIcons::$pluginDirPath . EasySymbolsIcons::$pluginSlug . ".php";

        Settings::setup();
        SettingsPage::getInstance();
        Blocks::setup();
        IconHandler::getInstance();

        add_action('rest_api_init', [RestHandler::class, 'register_routes']);

        // Hook to save post and update icon usage
        add_action('save_post', [IconHandler::class, 'update_icon_usage_per_post'], 10, 3);

        add_action('before_delete_post', [IconHandler::class, 'update_icon_usage_removal_post']);

        //Activation and Deactivation
        register_activation_hook( __FILE__, [self::class, "pluginActivation"] );
        register_deactivation_hook( __FILE__, [self::class, "pluginDeactivation"] );
    }

    public static function pluginActivation():void {
        IconHandler::update_icon_usage_all();
    }

    public static function pluginDeactivation():void {
    }
}
