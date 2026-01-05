<?php
namespace Farn\EasySymbolsIcons\menuPages;

use Farn\EasySymbolsIcons\database\Settings;
use Farn\EasySymbolsIcons\iconHandler\IconHandler;

/**
 * Handles removal of fonts.
 */
function eics_handleFontRemoval() {
    if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        $font_to_remove = isset($_POST['font_to_remove']) ? sanitize_text_field(wp_unslash($_POST['font_to_remove'])) : '';
        $remove_font_nonce = isset($_POST['remove_font_nonce']) ? sanitize_text_field(wp_unslash($_POST['remove_font_nonce'])) : '';

        if (wp_verify_nonce($remove_font_nonce, 'remove_easysymbolsicons_font') && !empty($font_to_remove)) {
            $remove_result = IconHandler::removeFont($font_to_remove);
            $message = $remove_result
                ? esc_html__("Font removed successfully.", "easy-symbols-icons")
                : esc_html__("Failed to remove the font.", "easy-symbols-icons");
            echo '<div class="updated notice"><p>' . esc_html($message) . '</p></div>';
        }
    }
}

/**
 * Handles custom font uploads.
 */
function eics_handleCustomFontUpload() {
    if (
        isset($_SERVER['REQUEST_METHOD'], $_POST['upload_custom_font_nonce']) &&
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        wp_verify_nonce(wp_unslash($_POST['upload_custom_font_nonce']), 'upload_custom_font')
    ) {
        // Make sure a file was uploaded
        if ( ! empty($_FILES['custom_font']) && is_array($_FILES['custom_font']) ) {
            $uploaded_file = $_FILES['custom_font'];

            // Sanitize the original file name
            $font_name = sanitize_file_name( $uploaded_file['name'] ?? '' );

            // Validate file extension
            $file_extension = pathinfo( $font_name, PATHINFO_EXTENSION );
            $valid_extensions = [ 'ttf', 'otf' ];

            if ( ! in_array( strtolower($file_extension), $valid_extensions, true ) ) {
                echo '<div class="error notice"><p>' . esc_html__( "Invalid font type. Only TTF and OTF are supported.", "easy-symbols-icons" ) . '</p></div>';
                return;
            }

            // Read file contents safely
            if ( is_uploaded_file( $uploaded_file['tmp_name'] ?? '' ) ) {
                $file_blob = file_get_contents( $uploaded_file['tmp_name'] );

                // Add font
                $font_added = IconHandler::addFont( $file_blob, $font_name );

                $message = $font_added
                    ? esc_html__( "Font uploaded and added successfully.", "easy-symbols-icons" )
                    : esc_html__( "Failed to add the font.", "easy-symbols-icons" );

                echo '<div class="updated notice"><p>' . esc_html( $message ) . '</p></div>';
            } else {
                echo '<div class="error notice"><p>' . esc_html__( "No valid file uploaded.", "easy-symbols-icons" ) . '</p></div>';
            }
        }
    }
}

/**
 * Handles saving font selection.
 */
function eics_handleFontSelectionSave() {
    if (
        isset($_SERVER['REQUEST_METHOD'], $_POST['easysymbolsicons_fonts_nonce']) &&
        $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {
        $nonce = wp_unslash($_POST['easysymbolsicons_fonts_nonce']);

        if (!wp_verify_nonce($nonce, 'save_easysymbolsicons_fonts')) {
            return [];
        }

        $fonts_raw = isset($_POST['loaded_fonts']) ? wp_unslash($_POST['loaded_fonts']) : [];
        $fonts = is_array($fonts_raw)
            ? array_map('sanitize_text_field', $fonts_raw)
            : [];

        Settings::saveSettingInDB('loaded_fonts', wp_json_encode($fonts));

        return [
            'fonts'  => $fonts,
            'notice' => sprintf(
                '<div class="updated notice"><p>%s</p></div>',
                esc_html__('Settings saved.', 'easy-symbols-icons')
            ),
        ];
    }

    return [];
}

/**
 * Refresh all used icons / font usage.
 */
function eics_handleRefreshIconUsage() {
    if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_icons_nonce']) ) {
        $nonce = sanitize_text_field(wp_unslash($_POST['refresh_icons_nonce']));
        if (wp_verify_nonce($nonce, 'refresh_easysymbolsicons_icons')) {
            IconHandler::update_icon_usage_all();
            echo '<div class="updated notice"><p>' . esc_html__('Icon usage refreshed.', 'easy-symbols-icons') . '</p></div>';
        }
    }
}