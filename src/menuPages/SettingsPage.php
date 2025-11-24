<?php

namespace Farn\EasySymbolsIcons\menuPages;

class SettingsPage {
	
	private static SettingsPage $instance;
	
	private function __construct() {

		add_action( 'admin_menu', function(){
			add_menu_page(
				__( 'Easy Icon Fonts Settings', 'easyiconfonts' ),
				__( 'Easy Icon Fonts Settings', 'easyiconfonts' ),
				'manage_options',
				\EasyIcon::$prefix.'settings-page',
				function (){ include("SettingsPageContent.php"); },
				'',
				99
			);
		});

		add_action('admin_enqueue_scripts', function ($hook_suffix) {
			if (strpos($hook_suffix, 'esi_settings-page') === false) {
				return;
			}

			wp_enqueue_script(
				'SettingsPageContent.js',
				plugin_dir_url( dirname(__DIR__, 2) ) . 'easyiconfonts/assets/js/SettingsPageContent.js',
				[],
				'1.0',
				true
			);

			wp_localize_script('SettingsPageContent.js', 'EASYICON', [
				'remove_nonce'     => wp_create_nonce('remove_easyiconfonts_font'),
				'rest_nonce'       => wp_create_nonce('wp_rest'),
				'rest_url'         => esc_url_raw(rest_url('easyiconfonts/v1/download-default-fonts')),
				'success_message'  => __('Default fonts downloaded successfully. Reloading...', 'easyiconfonts'),
				'error_message'    => __('Failed to download default fonts.', 'easyiconfonts'),
			]);

			wp_enqueue_style(
				'SettingsPageContent.css',
				plugin_dir_url( dirname(__DIR__, 2) ) . 'easyiconfonts/assets/css/SettingsPageContent.css',
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