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
	}
	
	public static function getInstance(){
		
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}