<?php
namespace Farn\EasyIcon\menuPages;


use Farn\Core\License;
use Farn\EasyIcon\database\Settings;
use Farn\EasyIcon\iconHandler\IconHandler;


$tab = $_GET['tab'] ?? "default";

$iconDirExists = IconHandler::doesIconsDirectoryExist();

if (!$iconDirExists): ?>
    <div id="default-fonts-popup" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:#fff; padding:2em; border-radius:8px; max-width:400px; text-align:center;">
            <h2><?php echo __("No Icon Fonts Available", "easyicon"); ?></h2>
            <p><?php echo __("No icon fonts are currently installed. You can choose to download a set of default fonts from external sources, or upload your own custom fonts instead.", "easyicon"); ?></p>
            <div style="background:#f1f1f1; color:#555; font-size:0.85em; padding:0.75em; margin:1em 0; border-radius:4px; text-align:left;">
                <?php echo __("Note: Default fonts will be downloaded from trusted sources only – JSDelivr CDN and the official WordPress Dashicons GitHub repository.", "easyicon"); ?>
            </div>
            <button id="download-default-fonts" class="button button-primary">
                <?php echo __("Download Default Fonts", "easyicon"); ?>
            </button>
            <button id="close-popup" class="button">
                <?php echo __("I'll Upload My Own", "easyicon"); ?>
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const popup = document.getElementById('default-fonts-popup');
            popup.style.display = 'flex';

            document.getElementById('close-popup').addEventListener('click', function () {
                popup.style.display = 'none';
            });

            document.getElementById('download-default-fonts').addEventListener('click', function () {
                fetch('<?php echo esc_url(rest_url('easyicon/v1/download-default-fonts')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Network response was not ok");
                    }
                    return response.json();
                })
                .then(data => {
                    alert('<?php echo __("Default fonts downloaded successfully. Reloading...", "easyicon"); ?>');
                    window.location.reload();
                })
                .catch(error => {
                    alert('<?php echo __("Failed to download default fonts.", "easyicon"); ?>');
                    console.error(error);
                });
            });
        });
    </script>
<?php endif; ?>


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
        <h1><?php echo esc_html__("EasyIcon", "easyicon"); ?></h1>
        <hr class="wp-header-end">

        <p><?php echo esc_html__("Manage your icon fonts and use them easily.", "easyicon"); ?></p>

        <section style="margin-top: 2em;">
            <h2><?php echo esc_html__("Quick Start"); ?></h2>
            <ul>
                <li><?php echo esc_html__("Upload and manage icon fonts on the Font Select tab."); ?></li>
                <li><?php echo esc_html__("Add icons in posts and pages using the 'ei-icon' block."); ?></li>
                <li><?php echo esc_html__("Supported font formats: TTF and OTF."); ?></li>
            </ul>
        </section>
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
                        <button type="button" class="button button-secondary remove-font" data-font="<?php echo esc_attr($font_folder); ?>">
                                <?php echo __("Remove", "easyicon"); ?>
                        </button>
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