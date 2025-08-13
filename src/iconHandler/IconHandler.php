<?php

namespace Farn\EasyIcon\iconHandler;

use EasyIcon;
use Farn\EasyIcon\database\Settings;
use FontLib\Font;

class IconHandler {
    private static IconHandler $instance;

    public static string $iconsDir;
    public static string $iconsUrl;
    private static string $pluginAssetsDir;

    /**
     * Private constructor to initialize the IconHandler.
     *
     * Sets directory paths for icon storage and plugin assets,
     * and initializes icons by creating necessary folders and copying default fonts.
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        self::$iconsDir = $upload_dir['basedir'] . '/ei-icons';
        self::$iconsUrl = $upload_dir['baseurl'] . '/ei-icons';
        self::$pluginAssetsDir = EasyIcon::$pluginDirPath . 'assets/ei-icons/';

        $this->initializeIcons();
    }

    /**
     * Get the instance of the IconHandler class.
     *
     * @return IconHandler The instance of the IconHandler class.
     */
    public static function getInstance(): IconHandler {
        if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
    }

    /**
     * Initializes icons by creating required folders and copying font assets.
     * 
     * This method also generates and enqueues the unified CSS for icons.
     *
     * @return void
     */
    public static function initializeIcons(): void {
        if (!self::doesIconsDirectoryExist()) {
            self::createIconFolder();
            self::copyFontsFromAssets();
        }

        self::generateUnifiedFontCSS();
        self::enqueueUnifiedFontCSS();
    }

    /**
     * Retrieves all plugin assets in the plugin assets directory.
     *
     * @return array An array of file paths for the plugin assets.
     */
    public static function getPluginAssets(): array {
        $assets = [];

        if (is_dir(self::$pluginAssetsDir)) {
            $assets = self::getAllFilesAndDirs(self::$pluginAssetsDir);
        } else {
            error_log('Plugin assets directory does not exist: ' . self::$pluginAssetsDir);
        }

        return $assets;
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
     * Removes a font from the system, deleting all its font files.
     *
     * @param string $fontFolder The name of the font folder to remove.
     * 
     * @return bool Returns true on success, false if the font folder does not exist.
     */
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

    /**
     * Retrieves all available fonts in the plugin's icon directory.
     * 
     * The method returns all fonts (directories containing .ttf or .otf files) found in the icon directory.
     *
     * @return array An associative array with font folder names as keys and folder names as values.
     */
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

    /**
     * Retrieves all fonts that are currently loaded (saved in the settings).
     *
     * @return array An array of loaded font folder names.
     */
    public static function getLoadedFonts(): array {
        $loadedFontsJson = Settings::getSettingFromDB('loaded_fonts');
        return json_decode($loadedFontsJson, true) ?? [];
    }

    /**
     * Retrieves a mapping of font glyphs (names and Unicode) for all loaded fonts.
     *
     * @return array An associative array where keys are font folder names and values are arrays of glyph mappings.
     */
    public static function getLoadedFontGlyphsMapping(): array {
        $font_mappings = [];

        $fonts = self::getLoadedFonts();

        foreach ($fonts as $fontFolder) {
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

                $glyphs_mapping = [];

                foreach ($char_map as $unicode => $glyphIndex) {
                    $glyph_name = isset($font_glyphs[$glyphIndex]) ? $font_glyphs[$glyphIndex] : 'uni' . strtoupper(dechex($unicode));

                    $glyphs_mapping[] = [strtolower($glyph_name), '\\' . dechex($unicode)];
                }

                $font_mappings[$fontFolder] = $glyphs_mapping;
            } catch (\Exception $e) {
                error_log("Error loading font icons for '{$fontFolder}': " . $e->getMessage());
            }
        }

        return $font_mappings;
    }

    // Private Helper Functions

    /**
     * Checks if the icons directory exists (uploads/ei-icons) and is not empty.
     *
     * @return bool True if the icons directory exists and is not empty, false otherwise.
     */
    private static function doesIconsDirectoryExist(): bool {
        // Check if the directory exists
        if (!is_dir(self::$iconsDir)) {
            return false;
        }

        // Use the getAllFilesAndDirs function to get all files and directories inside the icons directory
        $filesAndDirs = self::getAllFilesAndDirs(self::$iconsDir);

        // If the result is empty, the directory is considered empty
        return !empty($filesAndDirs);
    }

    /**
     * Validates if the font file is of a valid type (TTF or OTF).
     *
     * @param string $fontName The name of the font file.
     * 
     * @return bool True if the font type is valid, false otherwise.
     */
    private static function isValidFontType(string $fontName): bool {
        $allowedExtensions = ['ttf', 'otf'];
        return in_array(pathinfo($fontName, PATHINFO_EXTENSION), $allowedExtensions, true);
    }

    /**
     * Creates the icon directory if it doesn't exist.
     *
     * @return void
     */
    private static function createIconFolder(): void {
        if (!file_exists(self::$iconsDir)) {
            wp_mkdir_p(self::$iconsDir);
        }
    }

    /**
     * Copies the font files from the plugin's assets folder to the icons directory.
     *
     * @return void
     */
    private static function copyFontsFromAssets(): void {
        $plugin_assets_dir = self::$pluginAssetsDir;

        if (!is_dir($plugin_assets_dir)) {
            error_log('Error: Plugin assets directory does not exist.');
            return;
        }

        $files = self::getAllFilesAndDirs($plugin_assets_dir);

        foreach ($files as $file) {
            $fontName = basename($file);
            $fontBlob = file_get_contents($file);

            if ($fontBlob === false) {
                error_log("Failed to read font file: {$fontName}");
                continue;
            }
            if (empty($fontName) || empty($fontBlob)) {
                error_log("Invalid font data for file: {$fontName}");
                continue;
            }

            if (self::addFont($fontBlob, $fontName)) {
                error_log("Successfully copied font from assets: {$fontName}");
            } else {
                error_log("Failed to copy font from assets: {$fontName}");
            }
        }
    }

    /**
     * Recursively retrieves all files and directories in a given directory.
     *
     * @param string $dir The directory to scan.
     *
     * @return array An array of file paths for all items in the directory.
     */
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

    /**
     * Parses a font file to retrieve its glyphs (names and Unicode).
     *
     * @param string $fontFile The path to the font file.
     * 
     * @return array An associative array where keys are glyph names and values are Unicode hex codes.
     */
    private static function parseFontFile(string $fontFile): array {
        try {
            $font = Font::load($fontFile);
            $font->parse();

            $charMap = $font->getUnicodeCharMap();
            $fontGlyphs = $font->getData('post', 'names');
            
            $glyphs = [];
            foreach ($charMap as $unicode => $glyphIndex) {
                $glyphName = $fontGlyphs[$glyphIndex] ?? 'uni' . strtoupper(dechex($unicode));
                $glyphs[strtolower($glyphName)] = '\\' . dechex($unicode);
            }

            return $glyphs;
        } catch (\Exception $e) {
            error_log("Error loading font file '{$fontFile}': " . $e->getMessage());
        }

        return [];
    }

    /**
     * Generates a unified CSS file for the loaded fonts and their glyphs.
     *
     * @return void
     */
    private static function generateUnifiedFontCSS(): void {
        $enabled_fonts = self::getLoadedFonts();

        if (empty($enabled_fonts)) {
            error_log("No fonts enabled.");
            return;
        }

        $previous_loaded_fonts_json = get_option('ei_prev_loaded_fonts', '[]');
        $previous_loaded_fonts = json_decode($previous_loaded_fonts_json, true);

        if ($enabled_fonts === $previous_loaded_fonts) {
            error_log("No new loaded fonts, skipping css regeneration...");
            return;
        }

        $css_output = '';

        $font_mappings = self::getLoadedFontGlyphsMapping();
        foreach ($font_mappings as $fontFolder => $glyph_mappings) {
            if (empty($fontFolder)) {
                error_log("Skipping empty or invalid font folder" . $fontFolder);
                continue;
            }

            $font_dir = self::$iconsDir . '/' . $fontFolder;
            $font_files = glob($font_dir . '/*.{ttf,otf}', GLOB_BRACE);

            if (empty($font_files)) {
                error_log("No font files found in {$font_dir}");
                continue;
            }

            $font_file = $font_files[0];
            
            try {
                $font_name = Font::load($font_file)->getFontName();

                $css_output .= "@font-face{font-family:'{$font_name}';src:url('". self::$iconsUrl ."/{$fontFolder}/" . basename($font_file) ."') format('truetype');}";
                $css_output .= '[class^="ei-' . strtolower($fontFolder) . '-"]{font-family:"' . $font_name . '";}';
                error_log("t1");
                foreach ($font_mappings[$fontFolder] as $glyph_mapping) {
                    $glyph_name = $glyph_mapping[0];
                    $unicode_hex = $glyph_mapping[1];
                    $class = '.ei-' . strtolower($fontFolder) . '-' . strtolower($glyph_name);
                    $css_output .= "{$class}::before{content:\"{$unicode_hex}\";}";
                }
            } catch (\Exception $e) {
                error_log("Font parse error for '{$fontFolder}': " . $e->getMessage());
            }
        }
        if ($css_output) {
            $css_file = self::$iconsDir . '/generated-icons.css';
            if (file_put_contents($css_file, $css_output)) {
                error_log("CSS file generated successfully: {$css_file}");
            } else {
                error_log("Error writing CSS to file: {$css_file}");
            }
        }

        update_option('ei_prev_loaded_fonts', json_encode($enabled_fonts));
    }

    /**
     * Enqueues the unified CSS file for the loaded fonts.
     *
     * @return void
     */
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
