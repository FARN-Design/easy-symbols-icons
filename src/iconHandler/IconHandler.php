<?php

namespace Farn\EasyIcon\iconHandler;

use EasyIcon;
use Farn\EasyIcon\database\Settings;
use FontLib\Font;

class IconHandler {
    private static IconHandler $instance;
    private static string $iconsDir;
    private static string $iconsUrl;

    private function __construct() {
        $upload_dir = wp_upload_dir();
        self::$iconsDir = $upload_dir['basedir'] . '/ei-icons';
        self::$iconsUrl = $upload_dir['baseurl'] . '/ei-icons';

        $this->initializeIcons();
    }

    public static function getInstance(): IconHandler {
        if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
    }

    public static function initializeIcons(): void {
        self::createIconFolder();

        self::copyFontsFromAssets();

        self::generateUnifiedFontCSS();
        self::enqueueUnifiedFontCSS();
    }

    public static function getFontIcons(string $fontFolder): array {
        $icons = [];

        $font_dir = self::$iconsDir . '/' . $fontFolder;
        if (!is_dir($font_dir)) {
            return $icons;
        }

        $font_files = glob($font_dir . '/*.{ttf,otf}', GLOB_BRACE);
        if (empty($font_files)) {
            return $icons;
        }

        $font_file = $font_files[0];

        try {
            $font = Font::load($font_file);
            $font->parse();

            $char_map = $font->getUnicodeCharMap();
            $font_glyphs = $font->getData('post', "names");

            foreach ($char_map as $unicode => $glyphIndex) {
                $glyph_name = isset($font_glyphs[$glyphIndex]) ? $font_glyphs[$glyphIndex] : 'uni' . strtoupper(dechex($unicode));
                $icons[] = strtolower($glyph_name);  // Collect glyph names for the font
            }
        } catch (\Exception $e) {
            error_log("Error loading font icons: " . $e->getMessage());
        }

        return $icons;
    }


    public static function getAvailableFonts(): array {
        $fonts = [];

        if (!is_dir(self::$iconsDir)) {
            return $fonts;
        }

        $font_folders = scandir(self::$iconsDir);

        foreach ($font_folders as $folder) {
            if ($folder === '.' || $folder === '..') {
                continue;
            }

            $font_dir = self::$iconsDir . '/' . $folder;

            $font_files = glob($font_dir . '/*.{ttf,otf}', GLOB_BRACE);

            if (is_dir($font_dir) && !empty($font_files)) {
                $fonts[$folder] = $folder;
            }
        }

        return $fonts;
    }


    private static function createIconFolder(): void {
        if (!file_exists(self::$iconsDir)) {
            wp_mkdir_p(self::$iconsDir);
        }
    }

    private static function copyFontsFromAssets(): void {
        $plugin_assets_dir = EasyIcon::$pluginDirPath . 'assets/ei-icons/';

        if (!is_dir($plugin_assets_dir)) {
            error_log('Error: Plugin assets directory does not exist.');
            return;
        }

        $files = self::getAllFilesAndDirs($plugin_assets_dir);

        foreach ( $files as $file ) {
            $relative_path = substr( $file, strlen( $plugin_assets_dir ) );
            $destination = self::$iconsDir . '/' . $relative_path;

            if ( is_dir( $file ) ) {
                if ( ! is_dir( $destination ) ) {
                    mkdir( $destination, 0755, true );
                }
            } else {
                $destination_dir = dirname( $destination );
                if ( ! is_dir( $destination_dir ) ) {
                    mkdir( $destination_dir, 0755, true );
                }

                if ( ! file_exists( $destination ) ) {
                    if ( copy( $file, $destination ) ) {
                        break;
                    } else {
                        error_log('Error copying file: ' . $file);
                    }
                }
            }
        }
    }

    private static function getAllFilesAndDirs(string $dir): array {
        $results = [];

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $results[] = $path;

            if (is_dir($path)) {
                $results = array_merge($results, self::getAllFilesAndDirs($path));
            }
        }

        return $results;
    }

    private static function generateUnifiedFontCSS(): void {
        $enabled_fonts_json = Settings::getSettingFromDB('loaded_fonts');
        $enabled_fonts = json_decode($enabled_fonts_json, true);

        if (empty($enabled_fonts)) {
            error_log("No fonts enabled.");
            return;
        }

        $css_output = '';

        foreach ($enabled_fonts as $fontFolder) {
            $font_dir = self::$iconsDir . '/' . $fontFolder . '/';
            if (!is_dir($font_dir)) {
                continue;
            }

            // Only support ttf and otf as of right now
            $font_files = glob($font_dir . '*.{ttf,otf}', GLOB_BRACE);
            if (empty($font_files)) {
                continue;
            }

            $font_file = $font_files[0];
            try {
                $font = Font::load($font_file);
                $font->parse();

                $font_name = $font->getFontName();
                $char_map = $font->getUnicodeCharMap();
                $font_glyphs = $font->getData('post', "names");

                $css_output .= "@font-face{font-family:'{$font_name}';src:url('". self::$iconsUrl ."/{$fontFolder}/" . basename($font_file) ."') format('truetype');}";
                $css_output .= '[class^="ei-' . strtolower($fontFolder) . '-"]{font-family:"' . $font_name . '";}';


                foreach ($char_map as $unicode => $glyphIndex) {
                    if (isset($font_glyphs[$glyphIndex])) {
                        $glyph = $font_glyphs[$glyphIndex];
                        $glyph_name = $glyph;
                    } else {
                        // Fallback if no glyph found for this Unicode, use ligature approach
                        $glyph_name = 'uni' . strtoupper(dechex($unicode));
                    }

                    $unicode_hex = sprintf('\\%04x', $unicode);
                    $class = '.ei-' . strtolower($fontFolder) . '-' . strtolower($glyph_name);
                    $css_output .= "{$class}::before{content:\"{$unicode_hex}\";}";
                }

                $rel_path = self::$iconsUrl . '/' . $fontFolder . '/' . basename($font_file);

            } catch (\Exception $e) {
                error_log("Font parse error: " . $e->getMessage());
            }
        }
        if ($css_output) {
            $css_file = self::$iconsDir . '/generated-icons.css';
            file_put_contents($css_file, $css_output);
        }
    }

    private static function enqueueUnifiedFontCSS(): void {
        $upload_dir = wp_upload_dir();
        $css_file_url = self::$iconsUrl . '/generated-icons.css';
        $css_file_dir = self::$iconsDir . '/generated-icons.css';

        if (file_exists($css_file_dir)) {
            add_action('wp_enqueue_scripts', function() use ($css_file_url, $css_file_dir) {
                wp_enqueue_style(
                    'easyicon-unified-css',
                    $css_file_url,
                    [],
                    filemtime($css_file_dir)
                );
            });
        } else {
            error_log("No unified CSS file found to enqueue.");
        }
    }

}
