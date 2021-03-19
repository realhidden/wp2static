<?php

namespace WP2Static;

class DetectCategoryURLs {

    /**
     * Detect Category URLs
     *
     * @return string[] list of URLs
     */
    public static function detect( string $wp_site_url ) : array {
        global $wp_rewrite, $wpdb;

        $args = [ 'public' => true ];

        $taxonomies = get_taxonomies( $args, 'objects' );

        $category_urls = [];

        foreach ( $taxonomies as $taxonomy ) {
            /** @var list<\WP_Term> $terms */
            $terms = get_terms(
                $taxonomy->name,
                [ 'hide_empty' => true ]
            );

            foreach ( $terms as $term ) {
                $term_link = get_term_link( $term );

                if ( ! is_string( $term_link ) ) {
                    continue;
                }

                $permalink = trim( $term_link );

                $category_urls[] = '/' . str_replace(
                        $wp_site_url,
                        '',
                        $permalink
                    );
            }
        }

        return $category_urls;
    }
}
