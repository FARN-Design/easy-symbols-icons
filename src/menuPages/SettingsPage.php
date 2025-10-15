<?php

namespace Farn\EasyIcon\menuPages;

class SettingsPage {
	
	private static SettingsPage $instance;
	
	private function __construct() {

		add_action( 'admin_menu', function(){
			add_menu_page(
				__( 'Easy Icon Settings', 'easyicon' ),
				__( 'Easy Icon Settings', 'easyicon' ),
				'manage_options',
				\EasyIcon::$prefix.'settings-page',
				function (){ include("SettingsPageContent.php"); },
				'',
				99
			);
		});

		add_action('admin_enqueue_scripts', function ($hook_suffix) {
			if (strpos($hook_suffix, 'ei_settings-page') === false) {
				return;
			}

			wp_enqueue_script(
				'SettingsPageContent.js',
				plugin_dir_url( dirname(__DIR__, 2) ) . 'easyicon/assets/js/SettingsPageContent.js',
				[],
				'1.0',
				true
			);

			wp_localize_script('SettingsPageContent.js', 'EASYICON', [
				'remove_nonce'     => wp_create_nonce('remove_easyicon_font'),
				'rest_nonce'       => wp_create_nonce('wp_rest'),
				'rest_url'         => esc_url_raw(rest_url('easyicon/v1/download-default-fonts')),
				'success_message'  => __('Default fonts downloaded successfully. Reloading...', 'easyicon'),
				'error_message'    => __('Failed to download default fonts.', 'easyicon'),
			]);

			wp_enqueue_style(
				'SettingsPageContent.css',
				plugin_dir_url( dirname(__DIR__, 2) ) . 'easyicon/assets/css/SettingsPageContent.css',
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