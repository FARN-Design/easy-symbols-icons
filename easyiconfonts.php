<?php

/*
Plugin Name: Easy Icon Fonts
Plugin URI: https://github.com/FARN-Design/easyiconfonts
Description: A plugin to load and use various icon fonts with ease.
Version: 1.0.0
Author: Farnlabs
Author URI: https://profiles.wordpress.org/farndesign/
License: GPLv3
Text Domain: easyiconfont
Domain Path: /src/resources/language
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

use Farn\EasyIconFonts\database\Settings;
use Farn\EasyIconFonts\menuPages\SettingsPage;
use Farn\EasyIconFonts\blocks\Blocks;
use Farn\EasyIconFonts\iconHandler\IconHandler;
use Farn\EasyIconFonts\restEndpoints\RestHandler;

if (! defined( 'ABSPATH' ) ) {
    die;
}

$plugin = new EasyIcon();

class EasyIcon
{
    public static string $prefix = "eif_";
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
