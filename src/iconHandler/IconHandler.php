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

     /**
     * Adds a custom font by accepting a font file blob.
     * 
     * @param string $fontBlob The content of the font file (binary data).
     * @param string $fontName The desired name for the font file.
     * 
     * @return bool Returns true on success, false on failure.
     */
    public static function addFont(string $fontBlob, string $fontName): bool {
        if (!self::isValidFontType($fontName)) {
            error_log("Invalid Font Type");
            return false;
        }

        $fontName = sanitize_file_name($fontName);

        $fontDir = self::$iconsDir . '/' . (preg_replace('/\.(otf|ttf)$/i', '', $fontName));
        if (!file_exists($fontDir)) {
            mkdir($fontDir, 0755, true);
        }

        $fontPath = $fontDir . '/' . $fontName;

        if (file_put_contents($fontPath, $fontBlob) !== false) {
            error_log("Successfully created file at" . $fontPath);
            return true;
        }
        
        error_log("Failed to write to file");
        return false;
    }

    /**
     * Validates whether the font file type is either .ttf or .otf.
     * 
     * @param string $fontName The font file name.
     * @return bool True if it's a valid font type, false otherwise.
     */
    private static function isValidFontType(string $fontName): bool {
        $extension = strtolower(pathinfo($fontName, PATHINFO_EXTENSION));
        return in_array($extension, ['ttf', 'otf']);
    }

    public static function removeFont(string $fontFolder): bool {
        $font_dir = self::$iconsDir . '/' . $fontFolder;

        if (!is_dir($font_dir)) {
            return false;
        }

        $font_files = glob($font_dir . '/*.{ttf,otf}', GLOB_BRACE);

        foreach ($font_files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        rmdir($font_dir);

        $loaded_fonts = json_decode(Settings::getSettingFromDB('loaded_fonts'), true) ?? [];
        $loaded_fonts = array_diff($loaded_fonts, [$fontFolder]);

        Settings::saveSettingInDB('loaded_fonts', json_encode($loaded_fonts));

        return true;
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

    public static function getLoadedFonts(): array {
        $loaded_fonts_json = Settings::getSettingFromDB('loaded_fonts');
        
        $loaded_fonts = json_decode($loaded_fonts_json, true) ?? [];
    
        return $loaded_fonts;
    }

    public static function getLoadedFontGlyphsMapping(): array {
        $font_mappings = [];

        $fonts = self::getLoadedFonts();

        foreach ($fonts as $fontFolder) {
            error_log($fontFolder);
            $font_dir = self::$iconsDir . '/' . $fontFolder;
            if (!is_dir($font_dir)) {
                continue;
            }

            $font_files = glob($font_dir . '/*.{ttf,otf}', GLOB_BRACE);
            if (empty($font_files)) {
                continue;
            }

            $font_file = $font_files[0];
            try {
                $font = Font::load($font_file);
                $font->parse();

                $char_map = $font->getUnicodeCharMap();
                $font_glyphs = $font->getData('post', "names");

                // Initialize an array for the current font
                $glyphs_mapping = [];

                foreach ($char_map as $unicode => $glyphIndex) {
                    $glyph_name = isset($font_glyphs[$glyphIndex]) ? $font_glyphs[$glyphIndex] : 'uni' . strtoupper(dechex($unicode));

                    // Append the glyph name and its unicode to the mapping
                    $glyphs_mapping[] = [strtolower($glyph_name), '\\' . dechex($unicode)];
                }

                // Add the mapping to the font folder key in the result array
                $font_mappings[$fontFolder] = $glyphs_mapping;
            } catch (\Exception $e) {
                error_log("Error loading font icons for '{$fontFolder}': " . $e->getMessage());
            }
        }

        return $font_mappings;
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

            add_action('admin_init', function() use ($css_file_url) {
                add_editor_style(
                    $css_file_url
                );
            });
        } else {
            error_log("No unified CSS file found to enqueue.");
        }
    }

}
