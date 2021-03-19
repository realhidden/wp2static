<?php

namespace WP2Static;

class DetectCustomPostTypeURLs {

    /**
     * Detect Custom Post Type URLs
     *
     * @return string[] list of URLs
     */
    public static function detect( string $wp_site_url ) : array {
        global $wpdb;

        // get "non-public" post types
        $non_public_post_types = array_merge(
            get_post_types(array(
                'public' => false,
                '_builtin' => true
            ), 'names'),
            get_post_types(array(
                'public' => false,
                '_builtin' => false
            ), 'names'),
            array('revision', 'nav_menu_item'));


        $post_urls = [];
        // TODO: $non_public_post_types escape
        $post_ids = $wpdb->get_col(
            "SELECT ID
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type NOT IN ('".implode("','",$non_public_post_types)."')"
        );

        foreach ( $post_ids as $post_id ) {
            $permalink = get_post_permalink( $post_id );

            if ( ! is_string( $permalink ) ) {
                continue;
            }

            if ( strpos( $permalink, '?post_type' ) !== false ) {
                continue;
            }

            $post_urls[] = '/' . str_replace(
                    $wp_site_url,
                    '',
                    $permalink
                );
        }

        return $post_urls;
    }
}
