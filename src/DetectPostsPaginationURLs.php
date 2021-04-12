<?php

namespace WP2Static;

class DetectPostsPaginationURLs {

    /**
     * Detect Post pagination URLs
     *
     * @return string[] list of URLs
     */
    public static function detect( string $wp_site_url, string $wp_home_url ) : array {
        global $wpdb, $wp_rewrite;

        // remove trailing slash from site_url
        $site_url_notrail = strlen($wp_site_url) > 0 ? substr($wp_site_url, 0, strlen($wp_site_url) - 1) : $wp_site_url;

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

        $posts = $wpdb->get_results(
            sprintf("SELECT post_type, COUNT(id) as count FROM %s WHERE post_status = '%s'
            AND post_type NOT IN ('" . implode("','", $non_public_post_types) . "')
            GROUP BY post_type",
                $wpdb->posts,
                'publish'
            )
        );

        // grab currently registered unique post types
        $public_post_types = array_merge(
            get_post_types(array(
                'public' => true,
                '_builtin' => true
            ), 'names'),
            get_post_types(array(
                'public' => true,
                '_builtin' => false
            ), 'names'));

        // also grab the types for DB
        $postcount = [];
        foreach ($posts as $post) {
            // capture all post types
            $public_post_types[] = $post->post_type;
            $postcount[$post->post_type] = $post->count;
        }

        // get all pagination links for each post_type
        $public_post_types = array_unique($public_post_types);
        $pagination_base = $wp_rewrite->pagination_base;
        $default_posts_per_page = get_option('posts_per_page');

        $urls_to_include = [];
        foreach ($public_post_types as $post_type) {
            $post_type_obj = get_post_type_object($post_type);
            $post_type_total = isset($postcount[$post_type]) ? (int)$postcount[$post_type] : 0;

            // cannot find the post object, maybe it was deleted (but still in DB)
            if (!$post_type_obj) {
                continue;
            }

            $post_archive_link = get_post_type_archive_link($post_type);
            // post_type_archive link returns false if there is no archive page
            if ($post_archive_link === false) {
                continue;
            }
            $total_pages = ceil($post_type_total / $default_posts_per_page);
            for ($page = 0; $page <= $total_pages; $page++) {
                $newurl = '';
                if ($page === 0) {
                    $newurl = str_replace($site_url_notrail, "", $post_archive_link);
                } else {
                    $newurl = str_replace($site_url_notrail, "", $post_archive_link) . "{$pagination_base}/{$page}/";
                }

                // Make sure url starts with "/"
                if (strlen($newurl) === 0 || $newurl[0] !== '/') {
                    $newurl = "/" . $newurl;
                }
                $urls_to_include[] = $newurl;
            }
        }

        return $urls_to_include;
    }
}
