<?php

namespace Farn\EasySymbolsIcons\iconHandler;

trait IconHandlerUtilsTrait {
    /**
     * Checks if the icons directory exists (uploads/eics-icons) and is not empty.
     *
     * @return bool True if the icons directory exists and is not empty, false otherwise.
     */
    public static function doesIconsDirectoryExist(): bool {
        // Check if the directory exists
        if (!is_dir(self::$iconsDir)) {
            return false;
        }

        return true;
    }

    /**
     * Creates the icon directory if it doesn't exist.
     *
     * @return void
     */
    private static function createIconFolder(): void {
        if (!file_exists(self::$iconsDir)) {
            wp_mkdir_p(self::$iconsDir);
        }
    }

    /**
     * Recursively retrieves all files and directories in a given directory.
     *
     * @param string $dir The directory to scan.
     *
     * @return array An array of file paths for all items in the directory.
     */
    private static function getAllFilesAndDirs(string $dir): array {
        $results = [];

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $results[] = $path;

            if (is_dir($path)) {
                $results = array_merge($results, self::getAllFilesAndDirs($path));
            }
        }

        return $results;
    }
}