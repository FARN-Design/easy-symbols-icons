<?php

namespace Farn\EasySymbolsIcons\iconHandler;

use FontLib\Font;
use Farn\EasySymbolsIcons\iconFontSubsetter\IconFontSubsetter;

trait IconHandlerCssTrait {
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