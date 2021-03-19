<?php

namespace WP2Static;

class DetectAuthorsURLs {

    /**
     * Detect Authors URLs
     *
     * @return string[] list of URLs
     */
    public static function detect( string $wp_site_url ) : array {
        global $wp_rewrite, $wpdb;

        $authors_urls = [];
        $users = get_users();

        foreach ( $users as $author ) {
            $author_link = get_author_posts_url( $author->ID );

            if ( ! is_string( $author_link ) ) {
                continue;
            }

            $permalink = trim( $author_link );

            $authors_urls[] = '/' . str_replace(
                    $wp_site_url,
                    '',
                    $permalink
                );
        }

        return $authors_urls;
    }
}
