<?php

namespace Farn\EasyIcon\blocks;
use EasyIcon\database\Settings;
use EasyIcon\farnTools\farnLog;
use Farn\EasyIcon\iconHandler\IconHandler;
use WP_Post;
use WP_REST_Response;


class Blocks {
	public static function setup() {
		add_action( 'init', function () {
			$block_registered = register_block_type(__DIR__ . '/ei-icon/build/ei-icon');

            if ($block_registered) {
                error_log("Block registered successfully: " . $block_registered->name);
                add_action('rest_api_init', function() {
                    register_rest_route('easyicon/v1', '/fonts', [
                        'methods' => 'GET',
                        'callback' => [self::class, 'get_available_fonts'],
                        'permission_callback' => '__return_true',
                    ]);
                });
            } else {
                error_log("Block registration failed.");
            }
		} );
    }

    public static function get_available_fonts() {
        $fontGlyphs = IconHandler::getLoadedFontGlyphsMapping();

        if (is_array($fontGlyphs)) {
            return new WP_REST_Response($fontGlyphs, 200);
        } else {
            return new WP_REST_Response([
                'error' => 'Invalid font data',
            ], 500);
        }
    }
}