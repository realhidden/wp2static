<?php
/*
    URLDetector

    Detects URLs from WordPress DB, filesystem and user input

    Users can control detection levels

    Saves URLs to CrawlQueue

*/

namespace WP2Static;

class URLDetector {

    /**
     * Detect URLs within site
     */
    public static function detectURLs() : string {
        WsLog::setPhase(WP2STATIC_PHASES::URL_DETECT);
        WsLog::l( 'Starting to detect WordPress site URLs.' );

        do_action(
            'wp2static_detect'
        );

        $arrays_to_merge = [];

        // for multisite we cannot crawl the site, since it won't point to the proper home url
        $site_uri = SiteInfo::getURL( 'site' );
        $home_uri = rtrim( SiteInfo::getURL( 'home' ), '/' );

        // TODO: detect /favicon.ico + /
        $arrays_to_merge[] = [
            '/' . str_replace($site_uri, '', $home_uri . '/'),
            '/' . str_replace($site_uri, '', $home_uri . '/favicon.ico')
        ];

        /*
            TODO: reimplement detection for URLs:
                'detectCommentPagination',
                'detectComments',
                'detectFeedURLs',

        // other options:

         - robots
         - favicon
         - sitemaps

        */

        if ( CoreOptions::getValue( 'detectPosts' ) ) {
            WsLog::l( 'DetectPostURLs::detect' );
            $arrays_to_merge[] = DetectPostURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        if ( CoreOptions::getValue( 'detectPages' ) ) {
            WsLog::l( 'DetectPageURLs::detect' );
            $arrays_to_merge[] = DetectPageURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        if ( CoreOptions::getValue( 'detectCustomPostTypes' ) ) {
            WsLog::l( 'DetectCustomPostTypeURLs::detect' );
            $arrays_to_merge[] = DetectCustomPostTypeURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        if ( CoreOptions::getValue( 'detectUploads' ) ) {
            WsLog::l( 'FilesHelper::getListOfLocalFilesByDir uploads' );
            $arrays_to_merge[] =
                FilesHelper::getListOfLocalFilesByDir( SiteInfo::getPath( 'uploads' ) );
        }

        $detect_sitemaps = apply_filters( 'wp2static_detect_sitemaps', 1 );

        if ( $detect_sitemaps ) {
            WsLog::l( 'DetectSitemapsURLs::detect' );
            $arrays_to_merge[] = DetectSitemapsURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        $detect_parent_theme = apply_filters( 'wp2static_detect_parent_theme', 1 );

        if ( $detect_parent_theme ) {
            WsLog::l( 'DetectThemeAssets::detect parent' );
            $arrays_to_merge[] = DetectThemeAssets::detect( 'parent' );
        }

        $detect_child_theme = apply_filters( 'wp2static_detect_child_theme', 1 );

        if ( $detect_child_theme ) {
            WsLog::l( 'DetectThemeAssets::detect child' );
            $arrays_to_merge[] = DetectThemeAssets::detect( 'child' );
        }

        $detect_plugin_assets = apply_filters( 'wp2static_detect_plugin_assets', 1 );

        if ( $detect_plugin_assets ) {
            WsLog::l( 'DetectPluginAssets::detect' );
            $arrays_to_merge[] = DetectPluginAssets::detect();
        }

        $detect_wpinc_assets = apply_filters( 'wp2static_detect_wpinc_assets', 1 );

        if ( $detect_wpinc_assets ) {
            WsLog::l( 'DetectWPIncludesAssets::detect' );
            $arrays_to_merge[] = DetectWPIncludesAssets::detect();
            WsLog::l( 'DetectWPAdminAssets::detect' );
            $arrays_to_merge[] = DetectWPAdminAssets::detect();
        }

        $detect_vendor_cache = apply_filters( 'wp2static_detect_vendor_cache', 1 );

        if ( $detect_vendor_cache ) {
            WsLog::l( 'DetectVendorFiles::detect' );
            $arrays_to_merge[] = DetectVendorFiles::detect( SiteInfo::getURL( 'site' ) );
        }

        $detect_posts_pagination = apply_filters( 'wp2static_detect_posts_pagination', 1 );

        if ( $detect_posts_pagination ) {
            WsLog::l( 'DetectPostsPaginationURLs::detect' );
            $arrays_to_merge[] = DetectPostsPaginationURLs::detect( SiteInfo::getURL( 'site' ), SiteInfo::getURL( 'home' ) );
        }

        $detect_archives = apply_filters( 'wp2static_detect_archives', 1 );

        if ( $detect_archives ) {
            WsLog::l( 'DetectArchiveURLs::detect' );
            $arrays_to_merge[] = DetectArchiveURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        $detect_categories = apply_filters( 'wp2static_detect_categories', 1 );

        if ( $detect_categories ) {
            WsLog::l( 'DetectCategoryURLs::detect' );
            $arrays_to_merge[] = DetectCategoryURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        $detect_category_pagination = apply_filters( 'wp2static_detect_category_pagination', 1 );

        if ( $detect_category_pagination ) {
            WsLog::l( 'DetectCategoryPaginationURLs::detect' );
            $arrays_to_merge[] = DetectCategoryPaginationURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        $detect_authors = apply_filters( 'wp2static_detect_authors', 1 );

        if ( $detect_authors ) {
            WsLog::l( 'DetectAuthorsURLs::detect' );
            $arrays_to_merge[] = DetectAuthorsURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        $detect_authors_pagination = apply_filters( 'wp2static_detect_authors_pagination', 1 );

        if ( $detect_authors_pagination ) {
            WsLog::l( 'DetectAuthorPaginationURLs::detect' );
            $arrays_to_merge[] = DetectAuthorPaginationURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        WsLog::l( 'Merge and clean urls' );
        $url_queue = call_user_func_array( 'array_merge', $arrays_to_merge );
        $url_queue = FilesHelper::cleanDetectedURLs( $url_queue );

        $url_queue = apply_filters(
            'wp2static_modify_initial_crawl_list',
            $url_queue
        );

        $unique_urls = array_unique( $url_queue );

        // No longer truncate before adding
        // addUrls is now doing INSERT IGNORE based on URL hash to be
        // additive and not error on duplicate

        CrawlQueue::addUrls( $unique_urls );

        $total_detected = (string) count( $unique_urls );

        WsLog::l(
            "Detection complete. $total_detected URLs added to Crawl Queue."
        );

        WsLog::setPhase(WP2STATIC_PHASES::NO_PHASE);
        return $total_detected;
    }
}

