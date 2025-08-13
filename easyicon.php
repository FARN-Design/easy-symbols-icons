<?php

/*
Plugin Name: Easyicon
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A plugin to load and use various icon fonts with ease.
Version: 1.0
Author: jonte
Author URI: https://farn.de/
License: A "Slug" license name e.g. GPL2
*/

require 'vendor/autoload.php';

use Farn\Core\Log;
use Farn\Core\Update;
use Farn\Core\License;
use Farn\EasyIcon\database\Settings;
use Farn\EasyIcon\menuPages\SettingsPage;
use Farn\EasyIcon\blocks\Blocks;
use Farn\EasyIcon\iconHandler\IconHandler;

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
        Log::setup();
        SettingsPage::getInstance();

        if (is_admin()){
            //TODO Enable License if needed
            //License::initLicence(self::$software);
            Update::setup(self::$pluginSlug, self::$software, self::$pathToMainPluginFile, self::$pluginBaseName);
        }

        Blocks::setup();
        IconHandler::getInstance();

        //Activation and Deactivation
        register_activation_hook( __FILE__, [self::class, "pluginActivation"] );
        register_deactivation_hook( __FILE__, [self::class, "pluginDeactivation"] );
    }

    public static function pluginActivation():void {

    }

    public static function pluginDeactivation():void {

    }
}
