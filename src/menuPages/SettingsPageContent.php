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

function displayGeneralTab() {
    ?>
    <div class="wrap">
        <hr class="wp-header-end">
        <h2><?php echo __("General Settings", "easyicon"); ?></h2>
        <p><?php echo __("With EasyIcon, you can easily manage icon fonts for your website. Select from preloaded fonts or upload your own custom fonts in TTF or OTF format.", "easyicon"); ?></p>

        <h3><?php echo __("Upload Custom Fonts", "easyicon"); ?></h3>
        <p><?php echo __("You can upload your custom fonts by heading to the Font Select tab. Once uploaded, your fonts will be available for use within the site.", "easyicon"); ?></p>

        <h3><?php echo __("Using Icons", "easyicon"); ?></h3>
        <p><?php echo __("To add an icon to your content, use the 'ei-icon' block. When you click on the block, an icon select menu will appear, allowing you to choose from your selected or custom fonts.", "easyicon"); ?></p>

        <h3><?php echo __("Tips", "easyicon"); ?></h3>
        <ul>
            <li><?php echo __("Custom fonts can only be TTF or OTF format.", "easyicon"); ?></li>
            <li><?php echo __("Don't forget to save your settings after uploading or selecting your fonts.", "easyicon"); ?></li>
        </ul>
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

            $selected_fonts = json_decode(Settings::getSettingFromDB('loaded_fonts'), true) ?? [];

            $available_fonts = IconHandler::getAvailableFonts();

            $plugin_assets = IconHandler::getPluginAssets();
            $plugin_fonts = [];
            foreach ($plugin_assets as $asset) {
                $extension = pathinfo($asset, PATHINFO_EXTENSION);
                if (in_array(strtolower($extension), ['ttf', 'otf'])) {
                    $plugin_fonts[] = basename($asset);
                }
            }

            $font_mappings = IconHandler::getLoadedFontGlyphsMapping();

            $unicode_map = [];
            $conflicts = [];

            foreach ($font_mappings as $font_folder => $glyphs) {
                foreach ($glyphs as $glyph) {
                    $unicode = $glyph[1];

                    if (isset($unicode_map[$unicode])) {
                        $unicode_map[$unicode][] = $font_folder;
                    } else {
                        $unicode_map[$unicode] = [$font_folder];
                    }
                }
            }

            foreach ($unicode_map as $unicode => $fonts) {
                if (count($fonts) > 1) {
                    $conflicts[$unicode] = $fonts;
                }
            }

            if (!empty($conflicts)) {
                echo '<div class="error notice"><p>';
                echo __("There are icon Unicode conflicts in the following fonts:", "easyicon") . '<br>';

                foreach ($conflicts as $unicode => $conflicting_fonts) {
                    echo "<strong>Unicode $unicode:</strong> " . implode(', ', $conflicting_fonts) . '<br>';
                }

                echo '</p></div>';
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['easyicon_fonts_nonce']) && wp_verify_nonce($_POST['easyicon_fonts_nonce'], 'save_easyicon_fonts')) {
                $fonts = $_POST['loaded_fonts'] ?? [];
                Settings::saveSettingInDB('loaded_fonts', json_encode($fonts));
                $selected_fonts = $fonts;
                echo '<div class="updated notice"><p>' . __("Settings saved.", "easyicon") . '</p></div>';
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_font_nonce']) && wp_verify_nonce($_POST['remove_font_nonce'], 'remove_easyicon_font')) {
                $font_to_remove = sanitize_text_field($_POST['font_to_remove']);
                if (!empty($font_to_remove)) {
                    IconHandler::removeFont($font_to_remove);
                    echo '<div class="updated notice"><p>' . __("Font removed successfully.", "easyicon") . '</p></div>';
                }
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['custom_font']) && isset($_POST['upload_custom_font_nonce']) && wp_verify_nonce($_POST['upload_custom_font_nonce'], 'upload_custom_font')) {
                $uploaded_file = $_FILES['custom_font'];

                $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
                $valid_extensions = ['ttf', 'otf'];

                if (!in_array(strtolower($file_extension), $valid_extensions)) {
                    echo '<div class="error notice"><p>' . __("Invalid font type. Only TTF and OTF are supported.", "easyicon") . '</p></div>';
                } else {
                    $file_blob = file_get_contents($uploaded_file['tmp_name']);
                    $font_name = $uploaded_file['name'];

                    $font_added = IconHandler::addFont($file_blob, $font_name);

                    if ($font_added) {
                        echo '<div class="updated notice"><p>' . __("Font uploaded and added successfully.", "easyicon") . '</p></div>';
                        $available_fonts = IconHandler::getAvailableFonts();
                    } else {
                        echo '<div class="error notice"><p>' . __("Failed to add the font.", "easyicon") . '</p></div>';
                    }
                }
            }

            wp_nonce_field('save_easyicon_fonts', 'easyicon_fonts_nonce');
            wp_nonce_field('remove_easyicon_font', 'remove_font_nonce');
            ?>

            <?php if (!empty($available_fonts)): ?>
                <?php foreach ($available_fonts as $font_folder => $font_label): ?>
                    <div>
                        <label>
                            <input type="checkbox" name="loaded_fonts[]" value="<?php echo esc_attr($font_folder); ?>" <?php checked(in_array($font_folder, $selected_fonts)); ?>>
                            <?php echo esc_html($font_label); ?>
                        </label><br>
                        <?php
                        if (!in_array($font_folder . '.ttf', $plugin_fonts) && !in_array($font_folder . '.otf', $plugin_fonts)):
                        ?>
                            <button type="button" class="button button-secondary remove-font" data-font="<?php echo esc_attr($font_folder); ?>">
                                <?php echo __("Remove", "easyicon"); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p><?php echo __("No available fonts found. Please upload font files.", "easyicon"); ?></p>
            <?php endif; ?>
            <p><input type="submit" class="button button-primary" value="<?php echo __("Save", "easyicon"); ?>"></p>
        </form>
        <form method="post" enctype="multipart/form-data">
            <h2><?php echo __("Upload Custom Font", "easyicon"); ?></h2>

            <label for="custom_font_upload"><?php echo __("Select Font File (TTF or OTF)", "easyicon"); ?>:</label>
            <input type="file" name="custom_font" id="custom_font_upload" accept=".ttf,.otf" required>

            <?php wp_nonce_field('upload_custom_font', 'upload_custom_font_nonce'); ?>

            <p><input type="submit" class="button button-primary" value="<?php echo __("Upload Font", "easyicon"); ?>"></p>
        </form>
    </div>
    <?php
}
?>

<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const removeButtons = document.querySelectorAll('.remove-font');
        
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const fontToRemove = button.getAttribute('data-font');
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;
                
                const fontInput = document.createElement('input');
                fontInput.type = 'hidden';
                fontInput.name = 'font_to_remove';
                fontInput.value = fontToRemove;
                form.appendChild(fontInput);
                
                const nonceInput = document.createElement('input');
                nonceInput.type = 'hidden';
                nonceInput.name = 'remove_font_nonce';
                nonceInput.value = '<?php echo wp_create_nonce("remove_easyicon_font"); ?>';
                form.appendChild(nonceInput);
                
                document.body.appendChild(form);
                form.submit();
            });
        });
    });
</script>