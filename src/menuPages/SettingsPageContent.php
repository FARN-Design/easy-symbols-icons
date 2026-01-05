<?php
namespace Farn\EasySymbolsIcons\menuPages;

use Farn\EasySymbolsIcons\database\Settings;
use Farn\EasySymbolsIcons\iconHandler\IconHandler;

require_once plugin_dir_path(__FILE__) . 'SettingsPageFunctions.php';
require_once plugin_dir_path(__FILE__) . 'SettingsPageComponents.php';

// Determine current tab safely
$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';

// Handle POST requests safely
if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    eics_handleFontRemoval();
    eics_handleCustomFontUpload();
    $eics_save_result = eics_handleFontSelectionSave();
    if (!empty($eics_save_result['notice'])) {
        echo wp_kses_post($eics_save_result['notice']);
    }
    eics_handleRefreshIconUsage();
}
?>

<div class="wrap">
    <h1><?php esc_html_e("Easy Symbols Icons Settings", "easy-symbols-icons"); ?></h1>

    <?php eics_displayTabNavigation($tab); ?>

    <div class="eics-tab-content">
        <?php
        switch ($tab) {
            case "fontselect":
                eics_displayFontSelectTab();
                break;

            case "availableicons":
                eics_displayAvailableIconsTab();
                break;

            case "general":
            default:
                eics_displayGeneralTab();
        }
        ?>
    </div>
</div>

<?php
/* -------------------------------
 * Tab Navigation
 * ------------------------------- */
function eics_displayTabNavigation($currentTab) {
    $tabs = [
        'general' => __('General', 'easy-symbols-icons'),
        'fontselect' => __('Font Select', 'easy-symbols-icons'),
        'availableicons' => __('Available Icons', 'easy-symbols-icons'),
    ];

    echo '<nav class="nav-tab-wrapper">';
    foreach ($tabs as $slug => $label) {
        $active = $slug === $currentTab ? 'nav-tab-active' : '';
        echo '<a href="?page=eics_settings-page&tab=' . esc_attr($slug) . '" class="nav-tab ' . esc_attr($active) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';
}

/* -------------------------------
 * General Tab
 * ------------------------------- */
function eics_displayGeneralTab() { ?>
    <h2><?php esc_html_e("Quick Start", "easy-symbols-icons"); ?></h2>
    <p><?php esc_html_e("Manage your icon fonts and use them easily.", "easy-symbols-icons"); ?></p>
    <ul>
        <li><?php esc_html_e("Upload and manage icon fonts on the Font Select tab.", "easy-symbols-icons"); ?></li>
        <li><?php esc_html_e("Add icons in posts and pages using the 'eics-icon' block.", "easy-symbols-icons"); ?></li>
        <li><?php esc_html_e("Supported font formats: TTF and OTF.", "easy-symbols-icons"); ?></li>
    </ul>

    <hr>

    <label>
        <input
            type="checkbox"
            id="eics-disable-dynamic-subsetting"
            <?php checked(get_option('eics_disable_dynamic_subsetting', false)); ?>
        >
        <?php esc_html_e("Disable dynamic font subsetting", "easy-symbols-icons"); ?>
    </label>

    <p class="description">
        <?php esc_html_e(
            "When enabled, the full icon font will be loaded instead of generating subsets based on used icons.",
            "easy-symbols-icons"
        ); ?>
    </p>

    <hr>
    
    <h2><?php esc_html_e("Refresh Icon Usage", "easy-symbols-icons"); ?></h2>
    <form method="post">
        <?php wp_nonce_field('refresh_easysymbolsicons_icons', 'refresh_icons_nonce'); ?>
        <input type="submit" class="button button-secondary" value="<?php esc_attr_e("Refresh All Used Icons", "easy-symbols-icons"); ?>">
    </form>
<?php }

/* -------------------------------
 * Font Select Tab
 * ------------------------------- */
function eics_displayFontSelectTab() { ?>
    <form method="post" class="select-font-form">
        <h2><?php esc_html_e("Choose Icon Fonts to Load", "easy-symbols-icons"); ?></h2>
        <?php eics_displayFontSelectionForm(); ?>
    </form>

    <form method="post" enctype="multipart/form-data" class="upload-font-form">
        <h2><?php esc_html_e("Upload Custom Font", "easy-symbols-icons"); ?></h2>
        <?php eics_displayCustomFontUploadForm(); ?>
    </form>
<?php }

/* -------------------------------
 * Available Icons Tab
 * ------------------------------- */
function eics_displayAvailableIconsTab() {
    $all_icons = IconHandler::getLoadedFontGlyphsMapping();

    if (empty($all_icons) || !is_array($all_icons)) {
        echo '<p>' . esc_html__('No loaded fonts found. Please load fonts in the Font Select tab.', 'easy-symbols-icons') . '</p>';
        return;
    }

    $font_names = array_keys($all_icons); ?>
    <div class="eics-sticky-filter-bar">
        <input type="search" id="eics-icon-search" placeholder="<?php esc_attr_e('Search by icon or font name...', 'easy-symbols-icons'); ?>">

        <nav id="eics-fonts-nav">
            <?php foreach ($font_names as $font): ?>
                <button class="eics-font-jump-btn" data-font="<?php echo esc_attr($font); ?>"><?php echo esc_html(ucfirst($font)); ?></button>
            <?php endforeach; ?>
        </nav>
    </div>

    <div id="eics-icons-wrapper">
        <?php foreach ($all_icons as $font => $glyphs):
            $icons_by_letter = [];
            foreach ($glyphs as $iconName => $_) {
                if (!is_string($iconName) || strlen($iconName) === 0) continue;
                $letter = strtoupper($iconName[0]);
                $icons_by_letter[$letter][$iconName] = $_;
            }
            ksort($icons_by_letter); ?>

            <section class="eics-font-section" id="font-<?php echo esc_attr($font); ?>" data-font="<?php echo esc_attr($font); ?>">
                <div class="eics-font-section-sticky-bar">
                    <h2><?php echo esc_html(ucfirst($font)); ?></h2>
                    <nav class="eics-alpha-nav" data-font="<?php echo esc_attr($font); ?>">
                        <?php foreach ($icons_by_letter as $letter => $_): ?>
                            <a href="#<?php echo esc_attr('alpha-' . $font . '-' . $letter); ?>" class="eics-alpha-link"><?php echo esc_html($letter); ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <div class="eics-icon-group">
                    <?php foreach ($icons_by_letter as $letter => $icons): ?>
                        <div class="eics-alpha-header-wrapper">
                            <h3 id="<?php echo esc_attr('alpha-' . $font . '-' . $letter); ?>" class="eics-alpha-header"><?php echo esc_html($letter); ?></h3>
                        </div>
                        <div class="eics-alpha-group">
                            <?php foreach ($icons as $iconName => $unicode): ?>
                                <div class="eics-icon-item"
                                    data-icon-name="<?php echo esc_attr($iconName); ?>"
                                    data-font-name="<?php echo esc_attr($font); ?>"
                                    data-shortcode='[eics-icon icon="<?php echo esc_attr($iconName); ?>"]'
                                    title="<?php esc_attr_e("Click to copy shortcode", "easy-symbols-icons"); ?>">
                                    <div class="eics-icon-clickable">
                                        <span class="eics-<?php echo esc_attr(strtolower($font) . '__' . strtolower($iconName)); ?>"></span>
                                        <span class="eics-icon-label"><?php echo esc_html($iconName); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php }
