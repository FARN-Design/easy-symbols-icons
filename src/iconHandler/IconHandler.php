<?php

namespace Farn\EasySymbolsIcons\iconHandler;

use EasySymbolsIcons;

class IconHandler {
    use IconHandlerFontsTrait;
    use IconHandlerUsageTrait;
    use IconHandlerUtilsTrait;
    use IconHandlerCssTrait;

    private static IconHandler $instance;

    public static string $iconsDir;
    public static string $iconsUrl;
    private static string $pluginAssetsDir;

    /**
     * Private constructor to initialize the IconHandler.
     *
     * Sets directory paths for icon storage and plugin assets,
     * and initializes icons by creating necessary folders and copying default fonts.
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        self::$iconsDir = $upload_dir['basedir'] . '/eics-icons';
        self::$iconsUrl = $upload_dir['baseurl'] . '/eics-icons';
        self::$pluginAssetsDir = EasySymbolsIcons::$pluginDirPath . 'assets/eics-icons/';

        self::generateUnifiedFontCSS();
        self::enqueueUnifiedFontCSS();
    }

    /**
     * Get the instance of the IconHandler class.
     *
     * @return IconHandler The instance of the IconHandler class.
     */
    public static function getInstance(): IconHandler {
        if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
    }

    /**
     * Initializes icons by creating required folders and copying font assets.
     * 
     * This method also generates and enqueues the unified CSS for icons.
     *
     * @return void
     */
    public static function initializeIcons(): void {
        if (!self::doesIconsDirectoryExist()) {
            self::createIconFolder();
        }

        self::generateUnifiedFontCSS();
        self::enqueueUnifiedFontCSS();
    }
}