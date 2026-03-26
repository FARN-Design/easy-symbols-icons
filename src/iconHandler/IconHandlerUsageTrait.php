<?php

namespace Farn\EasySymbolsIcons\iconHandler;

trait IconHandlerUsageTrait {
    /**
     * Extracts icon class names from the given content. HTML or Wordpress Markup
     *
     * @param string $content The content to extract icons from.
     * 
     * @return array An array of unique icon class names found in the content.
     */
    private static function extract_icons_from_content(string $content): array {
        if ($content === '') {
            return [];
        }

        // render content with shortcodes, blocks and filters
        $content = apply_filters( 'the_content', $content );
        
        $icons = [];

        // HTML class attributes
        if (preg_match_all('/class\s*=\s*["\']([^"\']+)["\']/i', $content, $classMatches)) {
            foreach ($classMatches[1] as $classString) {
                $classes = preg_split('/\s+/', trim($classString));
                foreach ($classes as $class) {
                    $class = strtolower($class);
                    if (
                        str_starts_with($class, 'eics-') &&
                        !str_ends_with($class, 'eics-icon-fonts') &&
                        !str_starts_with($class, 'wp-block-easy-symbols-icons')
                    ) {
                        $icons[] = $class;
                    }
                }
            }
        }

        return array_values(array_unique($icons));
    }

    /**
     * Extracts icon class names from the given post.
     *
     * @param int $post_id The ID of the post being saved.
     * @param \WP_Post $post The post object.
     * @param bool $update Whether this is an update operation.
     * 
     * @return array An array of unique icon class names found in the content.
     */
    public static function update_icon_usage_per_post(
        int $post_id,
        \WP_Post $post,
        bool $update
    ): void {
         // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (in_array($post->post_type, ['revision', 'nav_menu_item'], true)) {
            return;
        }

        $icon_usage = get_option('eics_icon_usage', []);
        $new_icons  = self::extract_icons_from_content($post->post_content ?? '');

        // Track icons currently referencing this post
        $existing_icons = [];

        foreach ($icon_usage as $icon => $post_ids) {
            if (in_array($post_id, $post_ids, true)) {
                $existing_icons[] = $icon;
            }
        }

        // Remove stale references
        $removed = array_diff($existing_icons, $new_icons);

        foreach ($removed as $icon) {
            $icon_usage[$icon] = array_values(
                array_diff($icon_usage[$icon], [$post_id])
            );

            if (empty($icon_usage[$icon])) {
                unset($icon_usage[$icon]);
            }
        }

        // Add new references
        $added = array_diff($new_icons, $existing_icons);

        foreach ($added as $icon) {
            if (!isset($icon_usage[$icon])) {
                $icon_usage[$icon] = [];
            }

            $icon_usage[$icon][] = $post_id;
        }

        update_option('eics_icon_usage', $icon_usage, false);
    }

    /**
     * Updates icon usage for generic objects (menus, widgets, templates).
     *
     * @param string $object_type
     * @param string|int $object_id
     * @param string $content
     */
    public static function update_icon_usage_per_object(
        string $object_type,
        string|int $object_id,
        string $content
    ): void {
        $key    = "{$object_type}:{$object_id}";

        $icon_usage = get_option('eics_icon_usage_objects', []);
        $icons      = self::extract_icons_from_content($content);

        if (empty($icons)) {
            unset($icon_usage[$key]);
        } else {
            $icon_usage[$key] = array_values(array_unique($icons));
        }

        update_option('eics_icon_usage_objects', $icon_usage, false);
    }

    /**
     * Retrieves a list of all used icon class names in the content across posts.
     *
     * @return array An array of unique icon class names found in the content (e.g., eics-materialicons__home).
     */
    public static function update_icon_usage_all_posts(): array {
        global $wpdb;

        $icon_usage = [];

        $posts = $wpdb->get_results("
            SELECT ID, post_content
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type NOT IN ('revision', 'nav_menu_item')
            AND post_content LIKE '%eics-%'
        ");

        foreach ($posts as $post) {
            $icons = self::extract_icons_from_content($post->post_content);

            foreach ($icons as $icon) {
                if (!isset($icon_usage[$icon])) {
                    $icon_usage[$icon] = [];
                }

                $icon_usage[$icon][] = (int) $post->ID;
            }
        }

        // Deduplicate + sort
        foreach ($icon_usage as &$post_ids) {
            $post_ids = array_values(array_unique($post_ids));
            sort($post_ids);
        }

        ksort($icon_usage);

        update_option('eics_icon_usage', $icon_usage, false);

        return $icon_usage;
    }

    /**
     * Updates icon usage for all dynamic objects: menus, templates, template parts, widgets.
     *
     * @return array Icon usage by object.
     */
    public static function update_icon_usage_all_objects(): array {
        $usage = [];

        // Menus
        foreach (wp_get_nav_menus() as $menu) {
            ob_start();
            wp_nav_menu(['menu' => $menu->term_id, 'echo' => true, 'fallback_cb' => false]);
            $icons = self::extract_icons_from_content(ob_get_clean());
            if (!empty($icons)) $usage["nav_menu:{$menu->term_id}"] = $icons;
        }

        // FSE templates and template parts
        $templates = get_posts([
            'post_type'      => ['wp_template', 'wp_template_part'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);
        foreach ($templates as $post) {
            $icons = self::extract_icons_from_content($post->post_content);
            if (!empty($icons)) $usage["{$post->post_type}:{$post->post_name}"] = $icons;
        }

        // // Widgets
        // $sidebars = wp_get_sidebars_widgets();
        // foreach ($sidebars as $sidebar_id => $widgets) {
        //     foreach ($widgets as $widget_id) {
        //         ob_start();
        //         the_widget(get_widget_class_by_id($widget_id));
        //         $icons = self::extract_icons_from_content(ob_get_clean());
        //         if (!empty($icons)) $usage["widget:{$widget_id}"] = $icons;
        //     }
        // }

        update_option('eics_icon_usage_objects', $usage, false);
        return $usage;
    }

    /**
     * Updates all icon usage (posts + objects) and returns a unified list.
     *
     * @return array Unique icons used across posts and objects.
     */
    public static function update_icon_usage_all(): array {
        $posts   = self::update_icon_usage_all_posts();
        $objects = self::update_icon_usage_all_objects();

        $icons = array_keys($posts);

        foreach ($objects as $icon_list) {
            $icons = array_merge($icons, $icon_list);
        }

        $icons = array_values(array_unique($icons));
        sort($icons);

        return $icons;
    }

    /**
     * Removes all references to a post from the icon usage tracking.
     *
     * @param int $post_id The ID of the post being deleted.
     * 
     * @return void
     */
    public static function update_icon_usage_removal_post(int $post_id): void {
        // Skip revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        $icon_usage = get_option('eics_icon_usage', []);

        if (empty($icon_usage)) {
            return;
        }

        $changed = false;

        foreach ($icon_usage as $icon => $post_ids) {
            if (!in_array($post_id, $post_ids, true)) {
                continue;
            }

            // Remove post reference
            $icon_usage[$icon] = array_values(
                array_diff($post_ids, [$post_id])
            );

            // Remove icon entirely if unused
            if (empty($icon_usage[$icon])) {
                unset($icon_usage[$icon]);
            }

            $changed = true;
        }

        if ($changed) {
            update_option('eics_icon_usage', $icon_usage, false);
        }
    }

    /**
     * Retrieves a list of all used icon class names in the content across posts.
     *
     * @return array An array of unique icon class names found in the content (e.g., eics-materialicons__home).
     */
    public static function get_used_icons(): array {
        $post_usage   = get_option('eics_icon_usage', []);
        $object_usage = get_option('eics_icon_usage_objects', []);
        $manual_icons = get_option('eics_manual_used_icons', []);

        $icons = array_keys($post_usage);

        foreach ($object_usage as $icon_list) {
            $icons = array_merge($icons, $icon_list);
        }

        foreach ($manual_icons as $icon) {
            $icons[] = 'eics-' . $icon;
        }

        $icons = array_values(array_unique($icons));
        sort($icons);

        update_option('eics_all_used_icons', $icons, false);

        return $icons;
    }
}