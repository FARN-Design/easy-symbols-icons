<?php

namespace Farn\EasySymbolsIcons\blocks;

/**
 * Class Blocks
 *
 * Handles block registration.
 */
class Blocks {

    /**
     * Initializes block registration and sets up a custom REST API route.
     *
     * Registers the "eics-icon" block during the 'init' action.
     * If registration is successful, it also registers a REST API endpoint
     * at /wp-json/easysymbolsicons/v1/fonts to return loaded font glyph mappings.
     *
     * @return void
     */
	public static function setup() {
		add_action( 'init', function () {
			register_block_type( __DIR__ . '/eics-icon/build/eics-icon' );

			require_once __DIR__ . '/eics-shortcode/eics-shortcode.php';
			add_shortcode('eics-icon', 'eics_render_icon_shortcode');
		} );
    }
}