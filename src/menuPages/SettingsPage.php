<?php

namespace Farn\EasySymbolsIcons\menuPages;

use EasySymbolsIcons;

class SettingsPage {
	
	private static SettingsPage $instance;
	
	private function __construct() {

		add_action( 'admin_menu', function(){
			add_menu_page(
				__( 'Easy Symbols & Icons Settings', 'easy-symbols-icons' ),
				__( 'Easy Symbols & Icons Settings', 'easy-symbols-icons' ),
				'manage_options',
				\EasySymbolsIcons::$prefix.'settings-page',
				function (){ include("SettingsPageContent.php"); },
				'',
				99
			);
		});

		add_action('admin_enqueue_scripts', function ($hook_suffix) {
			if (strpos($hook_suffix, 'eics_settings-page') === false) {
				return;
			}

			wp_enqueue_script(
				'SettingsPageContent.js',
				plugin_dir_url(EasySymbolsIcons::$pathToMainPluginFile) . 'assets/js/SettingsPageContent.js',
				[],
				'1.0',
				true
			);

			wp_localize_script('SettingsPageContent.js', 'EASYICONSYMBOLS', [
				'remove_nonce'     => wp_create_nonce('remove_easysymbolsicons_font'),
				'rest_nonce'       => wp_create_nonce('wp_rest'),
				'rest_url'         => esc_url_raw(rest_url('easysymbolsicons/v1/download-default-fonts')),
				'success_message'  => __('Default fonts downloaded successfully. Reloading...', 'easy-symbols-icons'),
				'error_message'    => __('Failed to download default fonts.', 'easy-symbols-icons'),
			]);

			wp_enqueue_style(
				'SettingsPageContent.css',
				plugin_dir_url(EasySymbolsIcons::$pathToMainPluginFile) . '/assets/css/SettingsPageContent.css',
				[],
				'1.0'
			);
		});
	}
	
	public static function getInstance(){
		
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}