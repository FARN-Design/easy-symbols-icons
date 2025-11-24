<?php

namespace Farn\EasySymbolsIcons\iconHandler;

use EasyIcon;
use Farn\EasySymbolsIcons\database\Settings;
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
        self::$iconsDir = $upload_dir['basedir'] . '/eif-icons';
        self::$iconsUrl = $upload_dir['baseurl'] . '/eif-icons';
        self::$pluginAssetsDir = EasyIcon::$pluginDirPath . 'assets/eif-icons/';

        self::generateUnifiedFontCSS();
        self::enqueueUnifiedFontCSS();
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
            self::addDefaultFonts();
        }

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

        $fontDir = trailingslashit(self::$iconsDir) . preg_replace('/\.(otf|ttf)$/i', '', $fontName);

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            WP_Filesystem();
        }

        if (!$wp_filesystem->is_dir(self::$iconsDir)) {
            if (!$wp_filesystem->mkdir(self::$iconsDir)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Failed to create directory: " . self::$iconsDir);
                }
                return false;
            }
        }

        if (!$wp_filesystem->is_dir($fontDir)) {
            if (!$wp_filesystem->mkdir($fontDir)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Failed to create directory: " . $fontDir);
                }
                return false;
            }
        }

        $fontPath = trailingslashit($fontDir) . $fontName;

        if ($wp_filesystem->put_contents($fontPath, $fontBlob, FS_CHMOD_FILE)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Successfully created file at " . $fontPath);
            }
            return true;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Failed to write to file: " . $fontPath);
        }

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
                wp_delete_file($file);
            }
        }

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        global $wp_filesystem;

        if ( ! $wp_filesystem->rmdir( $font_dir, true ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "Failed to delete directory: " . $font_dir );
            }
            return false;
        }

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

                if (!empty($char_map) && !empty($font_glyphs)) {
                    foreach ($char_map as $unicode => $glyphIndex) {
                        $glyph_name = isset($font_glyphs[$glyphIndex]) ? $font_glyphs[$glyphIndex] : 'uni' . strtoupper(dechex($unicode));
                        $glyphs_mapping[strtolower($glyph_name)] = '\\' . dechex($unicode);
                    }
                } else {
                    $ligature_map = self::extractLigatureMapping($font);

                    if (!empty($ligature_map)) {
                        foreach ($ligature_map as $seq => $unicode) {
                            $glyphs_mapping[$seq] = $unicode;
                        }
                    } else {
                        error_log("Both char_map and ligature_map are empty for font '{$fontFolder}'");
                        foreach ($char_map as $unicode => $glyphIndex) {
                            $glyphs_mapping['uni' . strtoupper(dechex($unicode))] = '\\' . dechex($unicode);
                        }
                    }
                }

                if (!empty($glyphs_mapping)) {
                    $font_mappings[$fontFolder] = $glyphs_mapping;
                }

            } catch (\Exception |\Error $e) {
                error_log("Error loading font icons for '{$fontFolder}': " . $e->getMessage());
                var_dump ($e->getTraceAsString());
            }
        }

        return $font_mappings;
    }

    /**
     * Checks if the icons directory exists (uploads/eif-icons) and is not empty.
     *
     * @return bool True if the icons directory exists and is not empty, false otherwise.
     */
    public static function doesIconsDirectoryExist(): bool {
        // Check if the directory exists
        if (!is_dir(self::$iconsDir)) {
            return false;
        }

        // Use the getAllFilesAndDirs function to get all files and directories inside the icons directory
        $filesAndDirs = self::getAllFilesAndDirs(self::$iconsDir);

        // If the result is empty, the directory is considered empty
        return !empty($filesAndDirs);
    }

    // Private Helper Functions

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
     * Downloads default fonts from remote URLs and saves them to the icons directory.
     * This only happens if the user manually clicks the download default fonts from external sources button.
     *
     * @return bool
     */
    private static function addDefaultFonts(): bool {
        $fontUrls = [
            'fa-solid-900.ttf' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/fa-solid-900.ttf',
            'MaterialIcons-Regular.ttf' => 'https://cdnjs.cloudflare.com/ajax/libs/material-design-icons/4.0.0/font/MaterialIcons-Regular.ttf',
            'fa-solid-900-line-awesome.ttf' => 'https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/font-awesome-line-awesome/webfonts/fa-solid-900.ttf',
            'dashicons.ttf' => 'https://github.com/WordPress/dashicons/raw/628951563b9c0f0d293af8e40c9b0b3da5e2880d/icon-font/fonts/dashicons.ttf',
        ];

        $allSuccess = true;

        foreach ($fontUrls as $filename => $url) {
            try {
                $fontBlob = file_get_contents($url);
                if ($fontBlob === false) {
                    error_log("EasyIcon: Failed to download font from URL: $url");
                    $allSuccess = false;
                    continue;
                }

                if (!self::addFont($fontBlob, $filename)) {
                    $allSuccess = false;
                }
            } catch (\Throwable $e) {
                error_log("EasyIcon Exception when downloading $filename: " . $e->getMessage());
                $allSuccess = false;
            }
        }

        return $allSuccess;
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
     * Extracts ligature mappings from a font file.
     * 
     * @param Font $font The loaded font object.
     * @return array Mapping of actual character sequences to Unicode strings (e.g., "icon_name" => "U+E001").
     */
    private static function extractLigatureMapping($font): array {
        $cmap = $font->getData("cmap")['subtables'][0]['glyphIndexArray'] ?? [];
        $glyphIDtoChar = [];
        $glyphIDtoUnicode = [];
        foreach ($cmap as $unicode => $gid) {
            if ($gid !== 0) {
                $glyphIDtoChar[$gid] = mb_chr($unicode, 'UTF-8');
                $glyphIDtoUnicode[$gid] = '\\' . strtoupper(dechex($unicode));
            }
        }

        $ligatureMap = [];
        $gsub = $font->getData("GSUB");

        foreach ($gsub['lookupList']['lookups'] as $lookup) {
            if ($lookup['lookupType'] !== 4) continue;

            foreach ($lookup['subtables'] as $subtable) {
                if (!isset($subtable['ligSets'])) continue;

                $leadingGlyphs = [];
                if (!empty($subtable['coverage']['rangeRecords'])) {
                    foreach ($subtable['coverage']['rangeRecords'] as $range) {
                        for ($gid = $range['start']; $gid <= $range['end']; $gid++) {
                            $leadingGlyphs[] = $gid;
                        }
                    }
                }
                if (!empty($subtable['coverageGlyphs'])) {
                    foreach ($subtable['coverageGlyphs'] as $gid) {
                        $leadingGlyphs[] = $gid;
                    }
                }

                foreach ($subtable['ligSets'] as $index => $ligSet) {
                    $baseGid = $leadingGlyphs[$index] ?? null;
                    if ($baseGid === null) continue;

                    foreach ($ligSet['ligatures'] as $lig) {
                        $componentChars = array_map(fn($gid) => $glyphIDtoChar[$gid] ?? '', $lig['components']);
                        array_unshift($componentChars, $glyphIDtoChar[$baseGid] ?? '');
                        $seqStr = implode('', $componentChars);

                        $ligatureGlyph = $glyphIDtoUnicode[$lig['ligatureGlyph']] ?? null;
                        if ($ligatureGlyph !== null) {
                            $ligatureMap[$seqStr] = $ligatureGlyph;
                        }
                    }
                }
            }
        }

        return $ligatureMap;
    }

    /**
     * Generates a unified CSS file for the loaded fonts and their glyphs.
     *
     * @return void
     */
    private static function generateUnifiedFontCSS(): void {
        $enabled_fonts = self::getLoadedFonts();

        if (empty($enabled_fonts)) {
            return;
        }

        $previous_loaded_fonts_json = get_option('esi_prev_loaded_fonts', '[]');
        $previous_loaded_fonts = json_decode($previous_loaded_fonts_json, true);

        if ($enabled_fonts === $previous_loaded_fonts) {
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
                $css_output .= '[class^="eif-' . strtolower($fontFolder) . '-"]{font-family:"' . $font_name . '";}';
                foreach ($font_mappings[$fontFolder] as $glyph_name => $unicode_hex) {
                    $class = '.eif-' . strtolower($fontFolder) . '-' . strtolower($glyph_name);
                    $css_output .= "{$class}::before{content:\"{$unicode_hex}\";}";
                }
            } catch (\Exception $e) {
                error_log("Font parse error for '{$fontFolder}': " . $e->getMessage());
            }
        }
        if ($css_output) {
            $css_file = self::$iconsDir . '/generated-icons.css';
            if (file_put_contents($css_file, $css_output)) {
            } else {
                error_log("Error writing CSS to file: {$css_file}");
            }
        }

        update_option('esi_prev_loaded_fonts', json_encode($enabled_fonts));
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
                    'easyiconfonts-unified-css',
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
