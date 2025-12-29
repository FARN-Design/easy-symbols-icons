<?php
namespace Farn\EasySymbolsIcons\menuPages;

use Farn\EasySymbolsIcons\database\Settings;
use Farn\EasySymbolsIcons\iconHandler\IconHandler;
use EasySymbolsIcons;

$tab = isset($_GET['tab']) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'default';
?>


<div class="wrap">
    <h1><?php echo esc_html_e("Settings", "easy-symbols-icons"); ?></h1>
    <?php eics_displayTabNavigation($tab); ?>
    
    <?php
    switch ($tab) {
        case "fontselect":
            eics_displayFontSelectTab();
            break;
        case "availableicons":
            eics_displayAvailableIconsTab();
            break;
        case "default":
        default:
            eics_displayGeneralTab();
    }
    ?>
</div>

<?php

function eics_displayTabNavigation($currentTab) {
    ?>
    <nav class="nav-tab-wrapper">
        <a href="?page=eics_settings-page&tab=default" class="nav-tab <?php echo $currentTab === "default" ? "nav-tab-active" : ""; ?>">
            <?php echo esc_html__("General", "easy-symbols-icons"); ?>
        </a>
        <a href="?page=eics_settings-page&tab=fontselect" class="nav-tab <?php echo $currentTab === "fontselect" ? "nav-tab-active" : ""; ?>">
            <?php echo esc_html__("Font Select", "easy-symbols-icons"); ?>
        </a>
        <a href="?page=eics_settings-page&tab=availableicons" class="nav-tab <?php echo $currentTab === "availableicons" ? "nav-tab-active" : ""; ?>">
            <?php echo esc_html__("Available Icons", "easy-symbols-icons"); ?>
        </a>
    </nav>
    <?php
}

function eics_displayGeneralTab() {
    ?>
    <h2><?php echo esc_html__("EasySymbolsIcons", "easy-symbols-icons"); ?></h2>
    <hr class="wp-header-end">

    <p><?php echo esc_html__("Manage your icon fonts and use them easily.", "easy-symbols-icons"); ?></p>

    <section style="margin-top: 2em;">
        <h2><?php echo esc_html__("Quick Start", "easy-symbols-icons"); ?></h2>
        <ul>
            <li><?php echo esc_html__("Upload and manage icon fonts on the Font Select tab.", "easy-symbols-icons"); ?></li>
            <li><?php echo esc_html__("Add icons in posts and pages using the 'eics-icon' block.", "easy-symbols-icons"); ?></li>
            <li><?php echo esc_html__("Supported font formats: TTF and OTF.", "easy-symbols-icons"); ?></li>
        </ul>
    </section>
    <?php
}

function eics_displayAvailableIconsTab() {
    $all_icons = IconHandler::getLoadedFontGlyphsMapping();

    if (empty($all_icons) || !is_array($all_icons)) {
        echo '<p>' . esc_html__('No loaded fonts found. Please load fonts in the Font Select tab.', 'easy-symbols-icons') . '</p>';
        return;
    }

    $font_names = array_keys($all_icons);
    ?>

    <h2><?php echo esc_html__('Available Icons', 'easy-symbols-icons'); ?></h2>

    <input type="search" id="eics-icon-search" placeholder="<?php esc_attr_e('Search by icon or font name...', 'easy-symbols-icons'); ?>" style="width: 100%; padding: 0.5em; margin-bottom: 1em; font-size: 1rem;">

    <nav id="eics-fonts-nav" style="display: flex; gap: 1em; overflow-x: auto; margin-bottom: 1em;">
        <?php foreach ($font_names as $font): ?>
            <button class="eics-font-jump-btn" data-font="<?php echo esc_attr($font); ?>" style="padding: 0.5em 1em; cursor: pointer;">
                <?php echo esc_html(ucfirst($font)); ?>
            </button>
        <?php endforeach; ?>
    </nav>

    <div id="eics-icons-wrapper">
        <?php foreach ($all_icons as $font => $glyphs): 
            $icons_by_letter = [];
            foreach ($glyphs as $iconName => $_) {
                if (!is_string($iconName) || strlen($iconName) === 0) continue;
                $letter = strtoupper($iconName[0]);
                $icons_by_letter[$letter][$iconName] = $_;
            }
            ksort($icons_by_letter);
        ?>
        <section class="eics-font-section" id="font-<?php echo esc_attr($font); ?>" data-font="<?php echo esc_attr($font); ?>" style="margin-bottom: 3em;">
            <h2><?php echo esc_html(ucfirst($font)); ?></h2>

            <nav class="eics-alpha-nav" data-font="<?php echo esc_attr($font); ?>" style="margin-bottom: 1em;">
                <?php foreach ($icons_by_letter as $letter => $_): ?>
                    <a href="#<?php echo esc_attr('alpha-' . $font . '-' . $letter); ?>" class="eics-alpha-link"><?php echo esc_html($letter); ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="eics-icon-group">
                <?php foreach ($icons_by_letter as $letter => $icons): ?>
                    <h3 id="<?php echo esc_attr('alpha-' . $font . '-' . $letter); ?>" class="eics-alpha-header"><?php echo esc_html($letter); ?></h3>
                    <div class="eics-alpha-group" style="display: flex; flex-wrap: wrap; margin-bottom: 1em;">
                        <?php foreach ($icons as $iconName => $unicode): ?>
                            <div class="eics-icon-item"
                                data-icon-name="<?php echo esc_attr($iconName); ?>"
                                data-font-name="<?php echo esc_attr($font); ?>"
                                data-shortcode='[eics-icon icon="<?php echo esc_attr($iconName); ?>"]'
                                style="width: 120px; padding: 0.5em; text-align: center; box-sizing: border-box; cursor: pointer;"
                                title="Click to copy shortcode">

                                <div class="eics-icon-clickable" style="display: inline-block;">
                                    <span class="eics-<?php echo esc_attr(strtolower($font) . '__' . strtolower($iconName)); ?>"></span>
                                    <span class="eics-icon-label" style="font-size: 12px;"><?php echo esc_html($iconName); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div><?php
    }

    function eics_displayFontSelectTab() {
        $iconDirExists = IconHandler::doesIconsDirectoryExist();

        if (!$iconDirExists) {
            eics_displayFontPopup();
        }

        ?>
        <form method="post">
            <h2><?php echo esc_html__("Choose Icon Fonts to Load", "easy-symbols-icons"); ?></h2>
            <?php eics_displayFontSelectionForm(); ?>
        </form>

        <form method="post" enctype="multipart/form-data">
            <h2><?php echo esc_html__("Upload Custom Font", "easy-symbols-icons"); ?></h2>
            <?php eics_displayCustomFontUploadForm(); ?>
        </form>
        <?php
    }

function eics_displayFontSelectionForm() {
    $selected_fonts = json_decode(Settings::getSettingFromDB('loaded_fonts'), true) ?? [];
    $available_fonts = IconHandler::getAvailableFonts();

    if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        $nonce = isset($_POST['easysymbolsicons_fonts_nonce']) 
            ? sanitize_text_field( wp_unslash( $_POST['easysymbolsicons_fonts_nonce'] ) ) 
            : '';
        if ( wp_verify_nonce( sanitize_text_field($nonce), 'save_easysymbolsicons_fonts' ) ) {
            $selected_fonts = eics_handleFontSelectionSave($selected_fonts);
        }
    }

    wp_nonce_field('save_easysymbolsicons_fonts', 'easysymbolsicons_fonts_nonce');
    
    if (!empty($available_fonts)) {
        foreach ($available_fonts as $font_folder => $font_label) {
            eics_displayFontCheckbox($font_folder, $font_label, $selected_fonts);
        }
    } else {
        echo '<p>' . esc_html__("No available fonts found. Please upload font files.", "easy-symbols-icons") . '</p>';
    }

    echo '<p><input type="submit" class="button button-primary" value="' . esc_html__("Save Font Selection", "easy-symbols-icons") . '"></p>';
}

function eics_displayCustomFontUploadForm() {
    ?>
    <label for="custom_font_upload"><?php echo esc_html__("Select Font File (TTF or OTF)", "easy-symbols-icons"); ?>:</label>
    <input type="file" name="custom_font" id="custom_font_upload" accept=".ttf,.otf" required>

    <?php wp_nonce_field('upload_custom_font', 'upload_custom_font_nonce'); ?>
    <?php wp_nonce_field('remove_easysymbolsicons_font', 'remove_font_nonce'); ?>
    
    <p><input type="submit" class="button button-primary" value="<?php echo esc_html__("Upload Font", "easy-symbols-icons"); ?>"></p>
    <?php
}

function eics_handleFontSelectionSave($selected_fonts) {
    $nonce = isset($_POST['easysymbolsicons_fonts_nonce']) 
        ? sanitize_text_field( wp_unslash( $_POST['easysymbolsicons_fonts_nonce'] ) ) 
        : '';

    // if ( ! wp_verify_nonce( $nonce, 'save_easysymbolsicons_fonts' ) ) {
    //     wp_die( esc_html__('Invalid request. Please try again.', 'easy-symbols-icons') );
    // }

    $fonts_raw = isset($_POST['loaded_fonts']) 
    ? wp_unslash($_POST['loaded_fonts']) 
    : [];

    $fonts = !empty($fonts_raw) && is_array($fonts_raw) 
        ? array_map('sanitize_text_field', $fonts_raw) 
        : [];

    Settings::saveSettingInDB('loaded_fonts', wp_json_encode($fonts));

    echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'easy-symbols-icons') . '</p></div>';

    return $fonts;
}


function eics_displayFontCheckbox($font_folder, $font_label, $selected_fonts) {
    ?>
    <div>
        <label>
            <input type="checkbox" name="loaded_fonts[]" value="<?php echo esc_attr($font_folder); ?>" <?php checked(in_array($font_folder, $selected_fonts)); ?>>
            <?php echo esc_html($font_label); ?>
        </label><br>
        
        <button type="button" class="button button-secondary remove-font" data-font="<?php echo esc_attr($font_folder); ?>">
            <?php echo esc_html__("Remove", "easy-symbols-icons"); ?>
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

function eics_handleFontRemoval() {
    if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        $font_to_remove = isset($_POST['font_to_remove']) ? sanitize_text_field( wp_unslash( $_POST['font_to_remove'] ) ) : '';
        $remove_font_nonce = isset($_POST['remove_font_nonce']) ? sanitize_text_field( wp_unslash( $_POST['remove_font_nonce'] ) ) : '';

        if ( wp_verify_nonce( $remove_font_nonce, 'remove_easysymbolsicons_font' ) ) {

            if ( ! empty( $font_to_remove ) ) {
                $remove_result = IconHandler::removeFont( $font_to_remove );

                $message = $remove_result
                    ? esc_html__( "Font removed successfully.", "easy-symbols-icons" )
                    : esc_html__( "Failed to remove the font.", "easy-symbols-icons" );

                echo '<div class="updated notice"><p>' . esc_html__($message) . '</p></div>';
            }
        } else {
            // wp_die( esc_html__( 'Invalid request. Please try again.', 'easy-symbols-icons' ) );
        }
    }
}


function eics_handleCustomFontUpload() {
    if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {

        $uploaded_file = isset($_FILES['custom_font']) ? $_FILES['custom_font'] : null;
        $upload_custom_font_nonce = isset($_POST['upload_custom_font_nonce'])
            ? sanitize_text_field( wp_unslash( $_POST['upload_custom_font_nonce'] ) )
            : '';

        // if ( ! wp_verify_nonce( $upload_custom_font_nonce, 'upload_custom_font' ) ) {
        //     wp_die( esc_html__( 'Invalid request. Please try again.', 'easy-symbols-icons' ) );
        // }

        if ( $uploaded_file && isset($uploaded_file['tmp_name'], $uploaded_file['name']) ) {

            $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
            $valid_extensions = ['ttf', 'otf'];

            if ( ! in_array( strtolower($file_extension), $valid_extensions, true ) ) {
                echo '<div class="error notice"><p>' 
                    . esc_html__( "Invalid font type. Only TTF and OTF are supported.", "easy-symbols-icons" ) 
                    . '</p></div>';
            } else {

                $file_blob = file_get_contents( $uploaded_file['tmp_name'] );
                $font_name = sanitize_file_name( $uploaded_file['name'] );

                $font_added = IconHandler::addFont( $file_blob, $font_name );

                $message = $font_added
                    ? esc_html__( "Font uploaded and added successfully.", "easy-symbols-icons" )
                    : esc_html__( "Failed to add the font.", "easy-symbols-icons" );

                echo '<div class="updated notice"><p>' . esc_html__($message) . '</p></div>';
            }
        }
    }
}

eics_handleFontRemoval();
eics_handleCustomFontUpload();

?>
