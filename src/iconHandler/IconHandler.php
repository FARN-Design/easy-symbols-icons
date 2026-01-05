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

        $fontName = strtolower(sanitize_file_name($fontName));

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
                        $glyph_name = isset($font_glyphs[$glyphIndex])
                            ? $font_glyphs[$glyphIndex]
                            : 'uni' . dechex($unicode); // remove strtoupper
                        $glyphs_mapping[strtolower($glyph_name)] = '\\' . dechex($unicode);
                    }
                } else {
                    $ligature_map = self::extractLigatureMapping($font);

                    if (!empty($ligature_map)) {
                        foreach ($ligature_map as $seq => $unicode) {
                            $glyphs_mapping[strtolower($seq)] = $unicode;
                        }
                    } else {
                        error_log("Both char_map and ligature_map are empty for font '{$fontFolder}'");
                        foreach ($char_map as $unicode => $glyphIndex) {
                            $glyph_name = 'uni' . dechex($unicode);
                            $glyphs_mapping[strtolower($glyph_name)] = '\\' . dechex($unicode);
                        }
                    }
                }

                if (!empty($glyphs_mapping)) {
                    $font_mappings[strtolower($fontFolder)] = $glyphs_mapping; // lowercase font name
                }

            } catch (\Exception |\Error $e) {
                error_log("Error loading font icons for '{$fontFolder}': " . $e->getMessage());
                var_dump($e->getTraceAsString());
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
     * Extracts icon class names from the given content.
     *
     * @param string $content The content to extract icons from.
     * 
     * @return array An array of unique icon class names found in the content.
     */
    private static function extract_icons_from_content(string $content): array {
        $icons = [];

        // Shortcodes
        if (preg_match_all(
            '/\[eics-icon\s+icon=["\'](eics-[a-zA-Z0-9_-]+|[a-zA-Z0-9_-]+)["\']\]/i',
            $content,
            $matches
        )) {
            foreach ($matches[1] as $icon) {
                if (strpos($icon, 'eics-') !== 0) {
                    $icon = 'eics-' . $icon;
                }
                $icons[] = $icon;
            }
        }

        // CSS classes
        if (preg_match_all(
            '/class=["\'][^"\']*?(eics-[a-zA-Z0-9_-]+)[^"\']*?["\']/i',
            $content,
            $matches
        )) {
            foreach ($matches[1] as $icon) {
                if (
                    preg_match('/eics-icon-fonts$/i', $icon) ||
                    preg_match('/^wp-block-easy-symbols-icons-eics-symbols-icons/i', $icon)
                ) {
                    continue;
                }

                $icons[] = $icon;
            }
        }

        return array_values(array_unique($icons));
    }


    /**
     * Extracts icon class names from the given post.
     *
     * @param int $post_id The ID of the post being saved.
     * @param \WP_Post $post The post object.
     * @param bool $update Whether this is an update operation.
     * 
     * @return array An array of unique icon class names found in the content.
     */
    public static function update_icon_usage_per_post(
        int $post_id,
        \WP_Post $post,
        bool $update
    ): void {
         // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (in_array($post->post_type, ['revision', 'nav_menu_item'], true)) {
            return;
        }

        $icon_usage = get_option('eics_icon_usage', []);
        $new_icons  = self::extract_icons_from_content($post->post_content ?? '');

        // Track icons currently referencing this post
        $existing_icons = [];

        foreach ($icon_usage as $icon => $post_ids) {
            if (in_array($post_id, $post_ids, true)) {
                $existing_icons[] = $icon;
            }
        }

        // Remove stale references
        $removed = array_diff($existing_icons, $new_icons);

        foreach ($removed as $icon) {
            $icon_usage[$icon] = array_values(
                array_diff($icon_usage[$icon], [$post_id])
            );

            if (empty($icon_usage[$icon])) {
                unset($icon_usage[$icon]);
            }
        }

        // Add new references
        $added = array_diff($new_icons, $existing_icons);

        foreach ($added as $icon) {
            if (!isset($icon_usage[$icon])) {
                $icon_usage[$icon] = [];
            }

            $icon_usage[$icon][] = $post_id;
        }

        update_option('eics_icon_usage', $icon_usage, false);
    }

    /**
     * Retrieves a list of all used icon class names in the content across posts.
     *
     * @return array An array of unique icon class names found in the content (e.g., eics-materialicons__home).
     */
    public static function update_icon_usage_all(): array {
        global $wpdb;

        $icon_usage = [];

        $posts = $wpdb->get_results("
            SELECT ID, post_content
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type NOT IN ('revision', 'nav_menu_item')
            AND post_content LIKE '%eics-%'
        ");

        foreach ($posts as $post) {
            $icons = self::extract_icons_from_content($post->post_content);

            foreach ($icons as $icon) {
                if (!isset($icon_usage[$icon])) {
                    $icon_usage[$icon] = [];
                }

                $icon_usage[$icon][] = (int) $post->ID;
            }
        }

        // Deduplicate + sort
        foreach ($icon_usage as &$post_ids) {
            $post_ids = array_values(array_unique($post_ids));
            sort($post_ids);
        }

        ksort($icon_usage);

        update_option('eics_icon_usage', $icon_usage, false);

        return $icon_usage;
    }

    /**
     * Removes all references to a post from the icon usage tracking.
     *
     * @param int $post_id The ID of the post being deleted.
     * 
     * @return void
     */
    public static function update_icon_usage_removal_post(int $post_id): void {
        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        $icon_usage = get_option('eics_icon_usage', []);

        if (empty($icon_usage)) {
            return;
        }

        $changed = false;

        foreach ($icon_usage as $icon => $post_ids) {
            if (!in_array($post_id, $post_ids, true)) {
                continue;
            }

            // Remove post reference
            $icon_usage[$icon] = array_values(
                array_diff($post_ids, [$post_id])
            );

            // Remove icon entirely if unused
            if (empty($icon_usage[$icon])) {
                unset($icon_usage[$icon]);
            }

            $changed = true;
        }

        if ($changed) {
            update_option('eics_icon_usage', $icon_usage, false);
        }
    }

    /**
     * Retrieves a list of all used icon class names in the content across posts.
     *
     * @return array An array of unique icon class names found in the content (e.g., eics-materialicons__home).
     */
    public static function get_used_icons(): array {
        $icon_usage = get_option('eics_icon_usage', []);

        $icons = array_keys($icon_usage);
        sort($icons);

        return $icons;
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
     * Retrieves the font file path for a given font folder.
     *
     * @param string $fontFolder The name of the font folder.
     * 
     * @return string|null The path to the font file, or null if not found.
     */
    private static function getFontFilePath(string $fontFolder): ?string {
        $fontDir = self::$iconsDir . '/' . $fontFolder;
        if (!is_dir($fontDir)) return null;

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
        $enabled_fonts = array_map('strtolower', self::getLoadedFonts()); // normalize loaded fonts
        if (empty($enabled_fonts)) {
            update_option('eics_prev_used_icons', self::get_used_icons(), false);
            update_option('eics_prev_loaded_fonts', [], false);
            return;
        }

        $previous_loaded_fonts = array_map('strtolower', get_option('eics_prev_loaded_fonts', []));
        $used_icons = array_map('strtolower', self::get_used_icons());
        $previous_used_icons = array_map('strtolower', get_option('eics_prev_used_icons', []));

        sort($used_icons);
        sort($previous_used_icons);

        // Exit early if nothing changed
        if ($enabled_fonts === $previous_loaded_fonts && $used_icons === $previous_used_icons) {
            return;
        }

        $disable_subsetting = get_option('eics_disable_dynamic_subsetting', false);

        $font_mappings = array_change_key_case(self::getLoadedFontGlyphsMapping(), CASE_LOWER); // lowercase keys
        $frontend_css = '';
        $backend_css = [];

        foreach ($enabled_fonts as $fontFolder) {
            if (empty($fontFolder) || !isset($font_mappings[$fontFolder])) {
                error_log("Skipping empty or invalid font folder: " . $fontFolder);
                continue;
            }

            $font_dir = self::$iconsDir . '/' . $fontFolder;
            $font_file = self::getFontFilePath($fontFolder);

            if (empty($font_file)) {
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

            // --- Backend CSS ---
            $backend_css_temp = "@font-face{font-family:'{$font_name}';src:url('" . self::$iconsUrl . "/{$fontFolder}/" . basename($original_font_path) . "?v=" . filemtime($original_font_path) . "') format('truetype');}";
            $backend_css_temp .= '[class*="eics-' . strtolower($fontFolder) . '__"]::before{font-family:"' . $font_name . '";}';

            foreach ($font_mappings[$fontFolder] as $glyph_name => $unicode_hex) {
                $class = '.eics-' . strtolower($fontFolder) . '__' . strtolower($glyph_name);
                $backend_css_temp .= "{$class}::before{content:\"{$unicode_hex}\";}";
            }

            $backend_css[] = $backend_css_temp;

            if ($disable_subsetting) {
                $frontend_css .= $backend_css_temp;
                continue;
            }

            // --- Frontend Subsetting ---
            $frontend_font_path = $font_dir . '/' . $fontFolder . '-frontend.ttf';
            $icon_map = [];

            foreach ($used_icons as $icon_class) {
                if (preg_match('/^eics-([^\s]+?)__([^\s]+)$/i', $icon_class, $matches)) {
                    $matchedFontFolder = strtolower(trim($matches[1]));
                    if ($matchedFontFolder === strtolower($fontFolder)) {
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

                if (!empty($unicodeGlyphs)) {
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

                    // Frontend CSS for subset font
                    $frontend_css .= "@font-face{font-family:'{$font_name}';src:url('" . self::$iconsUrl . "/{$fontFolder}/" . basename($frontend_font_path) . "?v=" . filemtime($frontend_font_path) . "') format('truetype');}";
                    $frontend_css .= '[class*="eics-' . strtolower($fontFolder) . '__"]::before{font-family:"' . $font_name . '";}';

                    foreach ($icon_map[$fontFolder] as $glyph_name) {
                        if (isset($font_mappings[$fontFolder][$glyph_name])) {
                            $unicode_hex = $font_mappings[$fontFolder][$glyph_name];
                            $class = '.eics-' . strtolower($fontFolder) . '__' . strtolower($glyph_name);
                            $frontend_css .= "{$class}::before{content:\"{$unicode_hex}\";}";
                        }
                    }
                } else {
                    // No glyphs used → fallback to backend
                    $frontend_css .= $backend_css_temp;
                }
            } else {
                // No icons in this font used → fallback
                $frontend_css .= $backend_css_temp;
            }
        }

        file_put_contents(self::$iconsDir . '/frontend.css', $frontend_css);
        file_put_contents(self::$iconsDir . '/backend.css', implode('', $backend_css));

        update_option('eics_prev_used_icons', $used_icons, false);
        update_option('eics_prev_loaded_fonts', $enabled_fonts, false);
    }

    /**
     * Enqueues the frontend and backend icon CSS files.
     *
     * - frontend.css: Loaded on the frontend of the site.
     * - backend.css: Loaded in the WordPress block/classic editor as editor styles.
     *
     * @return void
     */
    public static function enqueueUnifiedFontCSS(): void {
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
