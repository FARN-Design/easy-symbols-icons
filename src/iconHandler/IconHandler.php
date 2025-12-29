<?php

namespace Farn\EasySymbolsIcons\iconHandler;

use EasySymbolsIcons;
use Farn\EasySymbolsIcons\database\Settings;
use FontLib\Font;
use Farn\EasySymbolsIcons\iconFontSubsetter\IconFontSubsetter;

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
        self::$iconsDir = $upload_dir['basedir'] . '/eics-icons';
        self::$iconsUrl = $upload_dir['baseurl'] . '/eics-icons';
        self::$pluginAssetsDir = EasySymbolsIcons::$pluginDirPath . 'assets/eics-icons/';

        self::generateUnifiedFontCSS();
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

            $font_file = self::getFontFilePath($fontFolder);

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
     * Checks if the icons directory exists (uploads/eics-icons) and is not empty.
     *
     * @return bool True if the icons directory exists and is not empty, false otherwise.
     */
    public static function doesIconsDirectoryExist(): bool {
        // Check if the directory exists
        if (!is_dir(self::$iconsDir)) {
            return false;
        }

        return true;
    }

    /**
     * Retrieves a list of all used icon class names in the content across posts.
     *
     * @return array An array of unique icon class names found in the content (e.g., eics-materialicons-home).
     */
    public static function get_used_icons(): array {
        global $wpdb;

        $like_pattern = '%eics-%';

        $results = $wpdb->get_results("
            SELECT ID, post_name, post_content
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type NOT IN ('revision', 'nav_menu_item')
            AND post_content LIKE '{$like_pattern}'
        ");

        $used_icons = [];

        if (!empty($results)) {
            foreach ($results as $post) {
                $content = $post->post_content;

                if (preg_match_all('/\[eics-icon\s+icon=["\'](eics-[a-zA-Z0-9_-]+|[a-zA-Z0-9_-]+)["\']\]/i', $content, $shortcode_matches)) {
                    foreach ($shortcode_matches[1] as $icon_class) {
                        if (strpos($icon_class, 'eics-') !== 0) {
                            $icon_class = 'eics-' . $icon_class;
                        }
                        $used_icons[] = $icon_class;
                    }
                }

                if (preg_match_all('/class=["\'][^"\']*?(eics-[a-zA-Z0-9_-]+)[^"\']*?["\']/i', $content, $class_matches)) {
                    foreach ($class_matches[1] as $icon_class) {
                        if (
                            preg_match('/eics-icon-fonts$/i', $icon_class) ||
                            preg_match('/^wp-block-easy-symbols-icons-eics-symbols-icons/i', $icon_class)
                        ) {
                            continue;
                        }

                        $used_icons[] = $icon_class;
                    }
                }
            }
        }

        $used_icons = array_unique($used_icons);
        sort($used_icons);

        return $used_icons;
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
     * Retrieves the font file path for a given font folder.
     *
     * @param string $fontFolder The name of the font folder.
     * 
     * @return string|null The path to the font file, or null if not found.
     */
    private static function getFontFilePath(string $fontFolder): ?string {
        $fontDir = self::$iconsDir . '/' . $fontFolder;

        $fontFileTtf = $fontDir . '/' . $fontFolder . '.ttf';
        $fontFileOtf = $fontDir . '/' . $fontFolder . '.otf';

        if (file_exists($fontFileTtf)) {
            $font_file = $fontFileTtf;
        } elseif (file_exists($fontFileOtf)) {
            $font_file = $fontFileOtf;
        } else {
            $font_file = null;
        }
        
        return $font_file;
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

        $previous_loaded_fonts_json = get_option('eics_prev_loaded_fonts', '[]');
        $previous_loaded_fonts = json_decode($previous_loaded_fonts_json, true);

        $used_icons = self::get_used_icons();
        $previous_used_icons_json = get_option('eics_prev_used_icons', '[]');
        $previous_used_icons = json_decode($previous_used_icons_json, true);

        if ($enabled_fonts === $previous_loaded_fonts && $used_icons === $previous_used_icons) {
            return;
        }

        $font_mappings = self::getLoadedFontGlyphsMapping();

        $frontend_css = '';
        $backend_css = '';

        foreach ($enabled_fonts as $fontFolder) {
            if (empty($fontFolder) || !isset($font_mappings[$fontFolder])) {
                error_log("Skipping empty or invalid font folder" . $fontFolder);
                continue;
            }

            $font_dir = self::$iconsDir . '/' . $fontFolder;
            $font_file = self::getFontFilePath($fontFolder);

            if (empty($font_file )) {
                error_log("No valid font file found in {$font_dir}");
                continue;
            }

            $original_font_path = $font_file;

            try {
                $font = Font::load($original_font_path);
                $font->parse();
                $font_name = $font->getFontName();
            } catch (\Exception $e) {
                error_log("Font parse error for '{$fontFolder}': " . $e->getMessage());
                continue;
            }
            
            // temporary iterative assignment of backend css in case of subsetting failure to use as backup
            $backend_css_temp = '';
            $backend_css_temp .= "@font-face{font-family:'{$font_name}';src:url('". self::$iconsUrl ."/{$fontFolder}/" . basename($original_font_path) ."') format('truetype');}";
            $backend_css_temp .= '[class^="eics-' . strtolower($fontFolder) . '__"]{font-family:"' . $font_name . '";}';

            foreach ($font_mappings[$fontFolder] as $glyph_name => $unicode_hex) {
                $class = '.eics-' . strtolower($fontFolder) . '__' . strtolower($glyph_name);
                $backend_css_temp .= "{$class}::before{content:\"{$unicode_hex}\";}";
            }

            $backend_css .= $backend_css_temp;

            if (!empty($used_icons)) {
                $frontend_font_path = "{$font_dir}/{$fontFolder}-frontend.ttf";

                foreach ($used_icons as $icon_class) {
                    if (preg_match('/^eics-([^\s]+?)__([^\s]+)$/i', $icon_class, $matches)) {
                        $matchedFontFolder = strtolower(trim($matches[1]));
                        $fontFolderNormalized = strtolower(trim($fontFolder));

                        if ($matchedFontFolder === $fontFolderNormalized) {
                            if (!isset($icon_map[$fontFolder])) {
                                $icon_map[$fontFolder] = [];
                            }
                            $icon_map[$fontFolder][] = $matches[2];
                        }
                    }
                }
                
                if (!empty($icon_map[$fontFolder])) {
                    $unicodeGlyphs = [];

                    foreach ($icon_map[$fontFolder] as $glyphName) {
                        if (isset($font_mappings[$fontFolder][$glyphName])) {
                            $unicodeGlyphs[] = $font_mappings[$fontFolder][$glyphName];
                        }
                    }

                    try {
                        $subsetter = new IconFontSubsetter($original_font_path);
                        if (!$subsetter->subset($unicodeGlyphs, $frontend_font_path)) {
                            error_log("Font subsetting failed for {$fontFolder}");
                            $frontend_css .= $backend_css_temp;
                            continue;
                        }
                    } catch (\Exception $e) {
                        error_log("Subset error for {$fontFolder}: " . $e->getMessage());
                        $frontend_css .= $backend_css_temp;
                        continue;
                    }

                    // Generate frontend CSS
                    $frontend_css .= "@font-face{font-family:'{$font_name}';src:url('" . self::$iconsUrl . "/{$fontFolder}/" . basename($frontend_font_path) . "') format('truetype');}";
                    $frontend_css .= '[class^="eics-' . strtolower($fontFolder) . '__"]{font-family:"' . $font_name . '";}';

                    foreach ($icon_map[$fontFolder] as $glyph_name) {
                        if (isset($font_mappings[$fontFolder][$glyph_name])) {
                            $unicode_hex = $font_mappings[$fontFolder][$glyph_name];
                            $class = '.eics-' . strtolower($fontFolder) . '__' . strtolower($glyph_name);
                            $frontend_css .= "{$class}::before{content:\"{$unicode_hex}\";}";
                        }
                    }
                }
            }
        }
        file_put_contents(self::$iconsDir . '/frontend.css', $frontend_css);
        file_put_contents(self::$iconsDir . '/backend.css', $backend_css);

        self::enqueueUnifiedFontCSS();

        update_option('eics_prev_loaded_fonts', json_encode($enabled_fonts));
    }

    /**
     * Enqueues the frontend and backend icon CSS files.
     *
     * - frontend.css: Loaded on the frontend of the site.
     * - backend.css: Loaded in the WordPress block/classic editor as editor styles.
     *
     * @return void
     */
    private static function enqueueUnifiedFontCSS(): void {
        $frontend_css_url  = self::$iconsUrl . '/frontend.css';
        $frontend_css_path = self::$iconsDir . '/frontend.css';

        $backend_css_url  = self::$iconsUrl . '/backend.css';
        $backend_css_path = self::$iconsDir . '/backend.css';

        // Enqueue frontend CSS
        if (file_exists($frontend_css_path)) {
            add_action('wp_enqueue_scripts', function () use ($frontend_css_url, $frontend_css_path) {
                wp_enqueue_style(
                    'easysymbolsicons-frontend-css',
                    $frontend_css_url,
                    [],
                    filemtime($frontend_css_path)
                );
            });
        }

        // Enqueue backend/editor CSS
        if (file_exists($backend_css_path)) {
            $backend_version = filemtime($backend_css_path);

            add_action('admin_init', function() use ($backend_css_url, $backend_version) {
                add_editor_style($backend_css_url . '?ver=' . $backend_version);
            });

            add_action('admin_enqueue_scripts', function() use ($backend_css_url, $backend_css_path) {
                wp_enqueue_style(
                    'easysymbolsicons-backend-css',
                    $backend_css_url,
                    [],
                    filemtime($backend_css_path)
                );
            });
        }
    }
}
