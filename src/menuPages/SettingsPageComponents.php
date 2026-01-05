<?php
namespace Farn\EasySymbolsIcons\menuPages;

use Farn\EasySymbolsIcons\database\Settings;
use Farn\EasySymbolsIcons\iconHandler\IconHandler;
require_once plugin_dir_path(__FILE__) . 'SettingsPageFunctions.php';

function eics_displayFontSelectionForm() {
    $selected_fonts = json_decode(Settings::getSettingFromDB('loaded_fonts'), true) ?? [];
    $available_fonts = IconHandler::getAvailableFonts();

    // Note: saving is handled in the main page POST handler (SettingsPageContent).
    // Here we only render the form using the current saved selection.

    wp_nonce_field('save_easysymbolsicons_fonts', 'easysymbolsicons_fonts_nonce');

    if (!empty($available_fonts)) {
        foreach ($available_fonts as $font_folder => $font_label) {
            eics_displayFontCheckbox($font_folder, $font_label, $selected_fonts);
        }
    } else {
        echo '<p>' . esc_html__("No available fonts found. Please upload font files.", "easy-symbols-icons") . '</p>';
    }

    echo '<input type="submit" class="button button-primary" value="' . esc_html__("Save Font Selection", "easy-symbols-icons") . '">';
}


function eics_displayCustomFontUploadForm() {
    ?>
    <label for="custom_font_upload"><?php echo esc_html__("Select Font File (TTF or OTF)", "easy-symbols-icons"); ?>:</label>
    <input type="file" name="custom_font" id="custom_font_upload" accept=".ttf,.otf" required>

    <?php wp_nonce_field('upload_custom_font', 'upload_custom_font_nonce'); ?>
    
    <input type="submit" class="button button-primary" value="<?php echo esc_html__("Upload Font", "easy-symbols-icons"); ?>">
    <?php
}

function eics_displayFontCheckbox($font_folder, $font_label, $selected_fonts) {
    ?>
    <div class="uploaded-font-item">
        <label>
            <input type="checkbox" name="loaded_fonts[]" value="<?php echo esc_attr($font_folder); ?>" <?php checked(in_array($font_folder, $selected_fonts)); ?>>
            <?php echo esc_html($font_label); ?>
        </label>
        <button type="button" class="button button-secondary remove-font" data-font="<?php echo esc_attr($font_folder); ?>">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </div>
    <?php
}

function eics_displayFontPopup() {
    ?>
    <div id="default-fonts-popup" style="display:none;">
        <div>
            <h2><?php echo esc_html__("No Icon Fonts Available", "easy-symbols-icons"); ?></h2>
            <p><?php echo esc_html__("No icon fonts are currently installed. You can choose to download a set of default fonts from external sources, or upload your own custom fonts instead.", "easy-symbols-icons"); ?></p>
            <div>
                <?php echo esc_html__("Note: Default fonts will be downloaded from trusted sources only – JSDelivr CDN and the official WordPress Dashicons GitHub repository.", "easy-symbols-icons"); ?>
            </div>
            <button id="download-default-fonts" class="button button-primary">
                <?php echo esc_html__("Download Default Fonts", "easy-symbols-icons"); ?>
            </button>
            <button id="close-popup" class="button">
                <?php echo esc_html__("I'll Upload My Own", "easy-symbols-icons"); ?>
            </button>
        </div>
    </div>
    <?php
}
