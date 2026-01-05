<?php

namespace Farn\EasySymbolsIcons\restEndpoints;

use WP_REST_Response;
use Farn\EasySymbolsIcons\iconHandler\IconHandler;

/**
 * Class Rest
 *
 * Handles REST API routes for EasySymbolsIcons.
 */
class RestHandler {

    /**
     * Register all REST API routes.
     */
    public static function register_routes() {
        register_rest_route('easysymbolsicons/v1', '/loaded-fonts', [
            'methods'  => 'GET',
            'callback' => [self::class, 'get_loaded_fonts'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('easysymbolsicons/v1', '/used-icons', [
            'methods'  => 'GET',
            'callback' => [self::class, 'get_used_icons'],
            'permission_callback' => '__return_true',
        ]);

        // register_rest_route('easysymbolsicons/v1', '/download-default-fonts', [
        //     'methods'  => 'POST',
        //     'callback' => [self::class, 'download_default_fonts'],
        //     'permission_callback' => function () {
        //         return current_user_can('manage_options');
        //     },
        // ]);
    }

    /**
     * REST API callback: Retrieves glyph mappings for all loaded fonts.
     *
     * @return WP_REST_Response
     */
    public static function get_loaded_fonts() {
        $fontGlyphs = IconHandler::getLoadedFontGlyphsMapping();

        if (is_array($fontGlyphs)) {
            return new WP_REST_Response($fontGlyphs, 200);
        }

        return new WP_REST_Response([], 200);
    }

    /**
     * Get used icons
     * 
     * @return WP_REST_Response
     */
    public static function get_used_icons() {
        $icons = IconHandler::get_used_icons();

        if (is_array($icons)) {
            return new WP_REST_Response($icons, 200);
        } else {
            return new WP_REST_Response([
                'error' => 'Invalid font data',
            ], 500);
        }
    }


    /**
     * REST API callback: Initializes default fonts.
     *
     * @return WP_REST_Response
     */
    // public static function download_default_fonts() {
    //     try {
    //         IconHandler::initializeIcons();

    //         return new WP_REST_Response([
    //             'success' => true,
    //             'message' => 'Default fonts installed successfully.',
    //         ], 200);
    //     } catch (\Throwable $e) {
    //         return new WP_REST_Response([
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
}
