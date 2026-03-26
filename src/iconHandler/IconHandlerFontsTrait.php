<?php

namespace Farn\EasySymbolsIcons\iconHandler;

use Farn\EasySymbolsIcons\database\Settings;
use FontLib\Font;

trait IconHandlerFontsTrait {
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

        $font_files = [];

        foreach (scandir($font_dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $font_dir . DIRECTORY_SEPARATOR . $file;

            if (is_file($path) && preg_match('/\.(ttf|otf)$/i', $file)) {
                $font_files[] = $path;
            }
        }

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

            $font_files = [];

            if (is_dir($font_dir)) {
                $files = scandir($font_dir);

                foreach ($files as $file) {
                    if (preg_match('/\.(ttf|otf)$/i', $file)) {
                        $font_files[] = $font_dir . '/' . $file;
                    }
                }
            }

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
}