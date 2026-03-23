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

        self::registerIconUsageCallbacks();

        //Activation and Deactivation
        register_activation_hook( __FILE__, [self::class, "pluginActivation"] );
        register_deactivation_hook( __FILE__, [self::class, "pluginDeactivation"] );
    }

    public static function registerIconUsageCallbacks(): void {
        add_action('save_post', [IconHandler::class, 'update_icon_usage_per_post'], 10, 3);

        add_action('before_delete_post', [IconHandler::class, 'update_icon_usage_removal_post']);

        add_action('wp_update_nav_menu', function (int $menu_id) {
            ob_start();
            wp_nav_menu([
                'menu' => $menu_id,
                'echo' => true,
                'fallback_cb' => false,
            ]);
            $markup = ob_get_clean();
            IconHandler::update_icon_usage_per_object('nav_menu', $menu_id, $markup);
        });

        add_filter(
            'widget_update_callback',
            function ($instance, $new_instance, $old_instance, $widget) {
                ob_start();
                the_widget(get_class($widget), $new_instance);
                $markup = ob_get_clean();
                IconHandler::update_icon_usage_per_object('widget', $widget->id, $markup);
                return $instance;
            },
            10,
            4
        );

        add_filter('dynamic_sidebar_params', function ($params) {
            ob_start();
            the_widget($params[0]['widget_class'], $params[0]['widget_options'] ?? []);
            $markup = ob_get_clean();
            IconHandler::update_icon_usage_per_object('widget', $params[0]['widget_id'], $markup);
            return $params;
        });

        add_action('save_post', function ($post_id, $post, $update) {
            if (in_array($post->post_type, ['wp_template', 'wp_template_part'], true)) {
                IconHandler::update_icon_usage_per_post($post_id, $post, $update);
            }
        }, 10, 3);
    }

    public static function pluginActivation(): void {
        IconHandler::update_icon_usage_all();
    }

    public static function pluginDeactivation(): void {
    }
}
