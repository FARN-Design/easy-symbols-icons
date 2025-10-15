<?php

namespace Farn\EasyIcon\blocks;
use EasyIcon\database\Settings;
use EasyIcon\farnTools\farnLog;
use Farn\EasyIcon\iconHandler\IconHandler;
use WP_Post;
use WP_REST_Response;

/**
 * Class Blocks
 *
 * Handles block registration.
 */
class Blocks {

    /**
     * Initializes block registration and sets up a custom REST API route.
     *
     * Registers the "ei-icon" block during the 'init' action.
     * If registration is successful, it also registers a REST API endpoint
     * at /wp-json/easyicon/v1/fonts to return loaded font glyph mappings.
     *
     * @return void
     */
	public static function setup() {
		add_action( 'init', function () {
			$block_registered = register_block_type(__DIR__ . '/ei-icon/build/ei-icon');
		} );
    }
}