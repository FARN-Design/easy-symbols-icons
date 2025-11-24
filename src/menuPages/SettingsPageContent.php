<?php
namespace Farn\EasySymbolsIcons\menuPages;

use Farn\EasySymbolsIcons\database\Settings;
use Farn\EasySymbolsIcons\iconHandler\IconHandler;

$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'default';
?>

<div class="wrap">
    <h1><?php echo esc_html__("Settings", "easyiconfonts"); ?></h1>
    <?php displayTabNavigation($tab); ?>
    
    <?php
    switch ($tab) {
        case "fontselect":
            displayFontSelectTab();
            break;
        case "default":
        default:
            displayGeneralTab();
    }
    ?>
</div>

<?php

function displayTabNavigation($currentTab) {
    ?>
    <nav class="nav-tab-wrapper">
        <a href="?page=esi_settings-page&tab=default" class="nav-tab <?php echo $currentTab === "default" ? "nav-tab-active" : ""; ?>">
            <?php echo esc_html__("General", "easyiconfonts"); ?>
        </a>
        <a href="?page=esi_settings-page&tab=fontselect" class="nav-tab <?php echo $currentTab === "fontselect" ? "nav-tab-active" : ""; ?>">
            <?php echo esc_html__("Font Select", "easyiconfonts"); ?>
        </a>
    </nav>
    <?php
}

function displayGeneralTab() {
    ?>
    <h2><?php echo esc_html__("EasyIcon", "easyiconfonts"); ?></h2>
    <hr class="wp-header-end">

    <p><?php echo esc_html__("Manage your icon fonts and use them easily.", "easyiconfonts"); ?></p>

    <section style="margin-top: 2em;">
        <h2><?php echo esc_html__("Quick Start", "easyiconfonts"); ?></h2>
        <ul>
            <li><?php echo esc_html__("Upload and manage icon fonts on the Font Select tab.", "easyiconfonts"); ?></li>
            <li><?php echo esc_html__("Add icons in posts and pages using the 'eif-icon' block.", "easyiconfonts"); ?></li>
            <li><?php echo esc_html__("Supported font formats: TTF and OTF.", "easyiconfonts"); ?></li>
        </ul>
    </section>
    <?php
}

function displayFontSelectTab() {
    $iconDirExists = IconHandler::doesIconsDirectoryExist();

    if (!$iconDirExists) {
        displayFontPopup();
    }

    ?>
    <form method="post">
        <h2><?php echo esc_html__("Choose Icon Fonts to Load", "easyiconfonts"); ?></h2>
        <?php displayFontSelectionForm(); ?>
    </form>

    <form method="post" enctype="multipart/form-data">
        <h2><?php echo esc_html__("Upload Custom Font", "easyiconfonts"); ?></h2>
        <?php displayCustomFontUploadForm(); ?>
    </form>
    <?php
}

function displayFontSelectionForm() {
    $selected_fonts = json_decode(Settings::getSettingFromDB('loaded_fonts'), true) ?? [];
    $available_fonts = IconHandler::getAvailableFonts();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['easyiconfonts_fonts_nonce']) && wp_verify_nonce($_POST['easyiconfonts_fonts_nonce'], 'save_easyiconfonts_fonts')) {
        $selected_fonts = handleFontSelectionSave($selected_fonts);
    }

    wp_nonce_field('save_easyiconfonts_fonts', 'easyiconfonts_fonts_nonce');
    
    if (!empty($available_fonts)) {
        foreach ($available_fonts as $font_folder => $font_label) {
            displayFontCheckbox($font_folder, $font_label, $selected_fonts);
        }
    } else {
        echo '<p>' . esc_html__("No available fonts found. Please upload font files.", "easyiconfonts") . '</p>';
    }

    echo '<p><input type="submit" class="button button-primary" value="' . esc_html__("Save Font Selection", "easyiconfonts") . '"></p>';
}

function displayCustomFontUploadForm() {
    ?>
    <label for="custom_font_upload"><?php echo esc_html__("Select Font File (TTF or OTF)", "easyiconfonts"); ?>:</label>
    <input type="file" name="custom_font" id="custom_font_upload" accept=".ttf,.otf" required>

    <?php wp_nonce_field('upload_custom_font', 'upload_custom_font_nonce'); ?>
    
    <p><input type="submit" class="button button-primary" value="<?php echo esc_html__("Upload Font", "easyiconfonts"); ?>"></p>
    <?php
}

function handleFontSelectionSave($selected_fonts) {
    $fonts_raw = isset($_POST['loaded_fonts']) ? wp_unslash($_POST['loaded_fonts']) : [];
    $fonts = !empty($fonts_raw) && is_array($fonts_raw) ? array_map('sanitize_text_field', $fonts_raw) : [];

    Settings::saveSettingInDB('loaded_fonts', json_encode($fonts));
    echo '<div class="updated notice"><p>' . esc_html__("Settings saved.", "easyiconfonts") . '</p></div>';

    return $fonts;
}

function displayFontCheckbox($font_folder, $font_label, $selected_fonts) {
    ?>
    <div>
        <label>
            <input type="checkbox" name="loaded_fonts[]" value="<?php echo esc_attr($font_folder); ?>" <?php checked(in_array($font_folder, $selected_fonts)); ?>>
            <?php echo esc_html($font_label); ?>
        </label><br>
        
        <button type="button" class="button button-secondary remove-font" data-font="<?php echo esc_attr($font_folder); ?>">
            <?php echo esc_html__("Remove", "easyiconfonts"); ?>
        </button>
    </div>
    <?php
}

function displayFontPopup() {
    ?>
    <div id="default-fonts-popup" style="display:none;">
        <div>
            <h2><?php echo esc_html__("No Icon Fonts Available", "easyiconfonts"); ?></h2>
            <p><?php echo esc_html__("No icon fonts are currently installed. You can choose to download a set of default fonts from external sources, or upload your own custom fonts instead.", "easyiconfonts"); ?></p>
            <div>
                <?php echo esc_html__("Note: Default fonts will be downloaded from trusted sources only – JSDelivr CDN and the official WordPress Dashicons GitHub repository.", "easyiconfonts"); ?>
            </div>
            <button id="download-default-fonts" class="button button-primary">
                <?php echo esc_html__("Download Default Fonts", "easyiconfonts"); ?>
            </button>
            <button id="close-popup" class="button">
                <?php echo esc_html__("I'll Upload My Own", "easyiconfonts"); ?>
            </button>
        </div>
    </div>
    <?php
}

function handleFontRemoval() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['font_to_remove']) && isset($_POST['remove_font_nonce'])) {
        $font_to_remove = wp_unslash($_POST['font_to_remove']);
        $remove_font_nonce = wp_unslash($_POST['remove_font_nonce']);
        
        if (wp_verify_nonce($remove_font_nonce, 'remove_easyiconfonts_font')) {
            $font_to_remove = sanitize_text_field($font_to_remove);
            if (!empty($font_to_remove)) {
                $remove_result = IconHandler::removeFont($font_to_remove);

                echo '<div class="updated notice"><p>';
                echo $remove_result
                    ? esc_html__("Font removed successfully.", "easyiconfonts")
                    : esc_html__("Failed to remove the font.", "easyiconfonts");
                echo '</p></div>';
            }
        }
    }
}

function handleCustomFontUpload() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['custom_font']) && isset($_POST['upload_custom_font_nonce'])) {
        $uploaded_file = $_FILES['custom_font'];
        $upload_custom_font_nonce = wp_unslash($_POST['upload_custom_font_nonce']);
        
        if (wp_verify_nonce($upload_custom_font_nonce, 'upload_custom_font')) {
            $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
            $valid_extensions = ['ttf', 'otf'];
            
            if (!in_array(strtolower($file_extension), $valid_extensions)) {
                echo '<div class="error notice"><p>' . esc_html__("Invalid font type. Only TTF and OTF are supported.", "easyiconfonts") . '</p></div>';
            } else {
                $file_blob = file_get_contents($uploaded_file['tmp_name']);
                $font_name = sanitize_file_name($uploaded_file['name']);
                $font_added = IconHandler::addFont($file_blob, $font_name);

                echo '<div class="updated notice"><p>';
                echo $font_added
                    ? esc_html__("Font uploaded and added successfully.", "easyiconfonts")
                    : esc_html__("Failed to add the font.", "easyiconfonts");
                echo '</p></div>';
            }
        }
    }
}

handleFontRemoval();
handleCustomFontUpload();

?>
