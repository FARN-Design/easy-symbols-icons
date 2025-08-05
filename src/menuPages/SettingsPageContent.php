<?php
namespace Farn\EasyIcon\menuPages;


use Farn\Core\License;
use Farn\EasyIcon\database\Settings;
use Farn\EasyIcon\iconHandler\IconHandler;


$tab = $_GET['tab'] ?? "default";

?>
<div class="wrap">
    <h1><?php echo __("Settings", "easyicon"); ?></h1>
    <nav class="nav-tab-wrapper">
        <a href="?page=ei_settings-page&tab=default" class="nav-tab <?php echo $tab === "default" ? "nav-tab-active" : ""; ?>">
            <?php echo __("General", "easyicon"); ?>
        </a>
        <a href="?page=ei_settings-page&tab=fontSelect" class="nav-tab <?php echo $tab === "fontSelect" ? "nav-tab-active" : ""; ?>">
            <?php echo __("Font Select", "easyicon"); ?>
        </a>
    </nav>
<?php

switch ($tab) {
   case "fontSelect":{
       displayFontSelectTab();
       break;
   }
    case "default":
    default:{
        displayGeneralTab();
    }
}

function displayGeneralTab(){
    ?>
    <div class="wrap">
        <hr class="wp-header-end">
        <form method="post" name="general_setting" id="general_setting" action="">
            <table class="form-table">
                <thead>
<!--                    TODO-->
                </thead>
                <tbody>
<!--                    TODO-->
                </tbody>
            </table>
        </form>
    </div>
<?php
}
    function displayFontSelectTab() {
    ?>
    <div class="wrap">
        <hr class="wp-header-end">
        <form method="post">
            <h2><?php echo __("Choose Icon Fonts to Load", "easyicon"); ?></h2>

            <?php

            // Get selected fonts from the database
            $selected_fonts = json_decode(Settings::getSettingFromDB('loaded_fonts'), true) ?? [];

            // Get available fonts from the IconHandler (scanned from the uploads directory)
            $available_fonts = IconHandler::getAvailableFonts();

            // If fonts are selected or saved
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['easyicon_fonts_nonce']) && wp_verify_nonce($_POST['easyicon_fonts_nonce'], 'save_easyicon_fonts')) {
                $fonts = $_POST['loaded_fonts'] ?? [];
                Settings::saveSettingInDB('loaded_fonts', json_encode($fonts));
                $selected_fonts = $fonts;
                echo '<div class="updated notice"><p>' . __("Settings saved.", "easyicon") . '</p></div>';
            }

            // Add nonce field for security
            wp_nonce_field('save_easyicon_fonts', 'easyicon_fonts_nonce');
            ?>

            <?php if (!empty($available_fonts)): ?>
                <?php foreach ($available_fonts as $font_folder => $font_label): ?>
                    <label>
                        <input type="checkbox" name="loaded_fonts[]" value="<?php echo esc_attr($font_folder); ?>" <?php checked(in_array($font_folder, $selected_fonts)); ?>>
                        <?php echo esc_html($font_label); ?>
                    </label><br>
                <?php endforeach; ?>
            <?php else: ?>
                <p><?php echo __("No available fonts found. Please upload font files.", "easyicon"); ?></p>
            <?php endif; ?>

            <p><input type="submit" class="button button-primary" value="<?php echo __("Save", "easyicon"); ?>"></p>
        </form>
    </div>
    <?php
}
?>
