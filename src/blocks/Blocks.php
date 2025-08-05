<?php

namespace Farn\EasyIcon\blocks;
use EasyIcon\database\Settings;
use EasyIcon\farnTools\farnLog;
use Farn\EasyIcon\iconHandler\IconHandler;
use WP_Post;


class Blocks {

	public static function setup(){
		add_action( 'init', function (){
			register_block_type( __DIR__ . '/ei-icon/build/ei-icon' );
		} );

		add_action('enqueue_block_editor_assets', [self::class, 'enqueue_block_assets']);
    }

	public static function enqueue_block_assets() {
        wp_enqueue_script(
            'easyicon-block-editor-js',
            plugins_url('blocks/ei-icon/build/ei-icon.js', __FILE__),
            ['wp-blocks', 'wp-element', 'wp-editor'], 
            filemtime(__DIR__ . '/ei-icon/build/ei-icon.js'), 
            true
        );

        wp_enqueue_style(
            'easyicon-block-editor-css',
            plugins_url('blocks/ei-icon/build/style.css', __FILE__),
            ['wp-edit-blocks'],
            filemtime(__DIR__ . '/ei-icon/build/style.css')
        );

        $available_fonts = IconHandler::getAvailableFonts();

        $fonts_js_data = json_encode($available_fonts);

        wp_add_inline_script(
            'easyicon-block-editor-js',
            "const eiIconData = { fonts: {$fonts_js_data}, defaultFont: 'dashicons' };",
            'before'
        );
    }
}