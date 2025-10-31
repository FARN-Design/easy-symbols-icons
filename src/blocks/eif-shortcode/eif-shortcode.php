<?php

function render_eif_icon_shortcode($attributes) {
    $attributes = shortcode_atts([
        'icon' => '',
    ], $attributes);

    $iconKey = trim($attributes['icon']);
    if (empty($iconKey)) {
        return '<span class="eif-icon">?</span>';
    }

    $fontFamily = '';
    $iconName = '';

    if (strpos($iconKey, '__') !== false) {
        list($fontFamily, $iconName) = explode('__', $iconKey, 2);
        $fontFamily = sanitize_html_class(strtolower($fontFamily));
        $iconName = sanitize_html_class(strtolower($iconName));
    } else {
        $iconName = sanitize_html_class(strtolower($iconKey));
    }

    $response = wp_remote_get(home_url('/wp-json/easyiconfonts/v1/loaded-fonts'));

    if (is_wp_error($response)) {
        $response = wp_remote_get(home_url('/?rest_route=/easyiconfonts/v1/loaded-fonts'));
    }

    if (is_wp_error($response)) {
        return '<span class="eif-icon">?</span>';
    }

    $body = wp_remote_retrieve_body($response);
    $fonts = json_decode($body, true);

    if (!is_array($fonts)) {
        error_log('Failed to decode JSON response for loaded fonts: ' . $body);
        return '<span class="eif-icon">?</span>';
    }

    if (!empty($fontFamily)) {
        // find the actual key in $fonts that matches $fontFamily (case-insensitive)
        $realFontKey = null;
        foreach ($fonts as $key => $glyphs) {
            if (strcasecmp($key, $fontFamily) === 0) {
                $realFontKey = $key;
                break;
            }
        }

        if ($realFontKey !== null && isset($fonts[$realFontKey][$iconName])) {
            return '<span class="eif-' . esc_attr($fontFamily) . '__' . esc_attr($iconName) . '"></span>';
        }
    }

    if (empty($fontFamily)) {
        $matches = [];
        foreach ($fonts as $family => $glyphs) {
            if (isset($glyphs[$iconName])) {
                $matches[] = $family;
            }
        }

        if (count($matches) === 1) {
            $fontFamily = sanitize_html_class(strtolower($matches[0]));
            return '<span class="eif-' . esc_attr($fontFamily) . '__' . esc_attr($iconName) . '"></span>';
        } elseif (count($matches) > 1) {
            error_log("Icon '{$iconName}' found in multiple fonts: " . implode(', ', $matches));
        } else {
            error_log("Icon '{$iconName}' not found in any loaded font.");
        }
    }

    return '<span class="eif-icon">?</span>';
}

