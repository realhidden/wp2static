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
        WsLog::setAllItemCount(30);
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

        // 1
        if ( CoreOptions::getValue( 'detectPosts' ) ) {
            WsLog::l( 'DetectPostURLs::detect',WP2STATIC_PHASES::URL_DETECT,1);
            $arrays_to_merge[] = DetectPostURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        // 2
        if ( CoreOptions::getValue( 'detectPages' ) ) {
            WsLog::l( 'DetectPageURLs::detect' ,WP2STATIC_PHASES::URL_DETECT,2);
            $arrays_to_merge[] = DetectPageURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        // 3
        if ( CoreOptions::getValue( 'detectCustomPostTypes' ) ) {
            WsLog::l( 'DetectCustomPostTypeURLs::detect',WP2STATIC_PHASES::URL_DETECT,3);
            $arrays_to_merge[] = DetectCustomPostTypeURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        // 4
        if ( CoreOptions::getValue( 'detectUploads' ) ) {
            WsLog::l( 'FilesHelper::getListOfLocalFilesByDir uploads' ,WP2STATIC_PHASES::URL_DETECT,4);
            $arrays_to_merge[] =
                FilesHelper::getListOfLocalFilesByDir( SiteInfo::getPath( 'uploads' ) );
        }

        // 5
        $detect_sitemaps = apply_filters( 'wp2static_detect_sitemaps', 1 );

        if ( $detect_sitemaps ) {
            WsLog::l( 'DetectSitemapsURLs::detect' ,WP2STATIC_PHASES::URL_DETECT,5);
            $arrays_to_merge[] = DetectSitemapsURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        // 6
        $detect_parent_theme = apply_filters( 'wp2static_detect_parent_theme', 1 );

        // 7
        if ( $detect_parent_theme ) {
            WsLog::l( 'DetectThemeAssets::detect parent' ,WP2STATIC_PHASES::URL_DETECT,7);
            $arrays_to_merge[] = DetectThemeAssets::detect( 'parent' );
        }

        // 8
        $detect_child_theme = apply_filters( 'wp2static_detect_child_theme', 1 );

        if ( $detect_child_theme ) {
            WsLog::l( 'DetectThemeAssets::detect child'  ,WP2STATIC_PHASES::URL_DETECT,9);
            // 9
            $arrays_to_merge[] = DetectThemeAssets::detect( 'child' );
        }

        // 10
        $detect_plugin_assets = apply_filters( 'wp2static_detect_plugin_assets', 1 );

        // 11
        if ( $detect_plugin_assets ) {
            WsLog::l( 'DetectPluginAssets::detect'  ,WP2STATIC_PHASES::URL_DETECT,11);
            $arrays_to_merge[] = DetectPluginAssets::detect();
        }

        // 12
        $detect_wpinc_assets = apply_filters( 'wp2static_detect_wpinc_assets', 1 );

        if ( $detect_wpinc_assets ) {
            WsLog::l( 'DetectWPIncludesAssets::detect'  ,WP2STATIC_PHASES::URL_DETECT,12);
            // 13
            $arrays_to_merge[] = DetectWPIncludesAssets::detect();
            WsLog::l( 'DetectWPAdminAssets::detect'  ,WP2STATIC_PHASES::URL_DETECT,13);
            // 14
            $arrays_to_merge[] = DetectWPAdminAssets::detect();
        }

        // 15
        $detect_vendor_cache = apply_filters( 'wp2static_detect_vendor_cache', 1 );

        if ( $detect_vendor_cache ) {
            WsLog::l( 'DetectVendorFiles::detect'  ,WP2STATIC_PHASES::URL_DETECT,15);
            // 16
            $arrays_to_merge[] = DetectVendorFiles::detect( SiteInfo::getURL( 'site' ) );
        }

        // 17
        $detect_posts_pagination = apply_filters( 'wp2static_detect_posts_pagination', 1 );

        if ( $detect_posts_pagination ) {
            WsLog::l( 'DetectPostsPaginationURLs::detect'  ,WP2STATIC_PHASES::URL_DETECT,17);
            // 18
            $arrays_to_merge[] = DetectPostsPaginationURLs::detect( SiteInfo::getURL( 'site' ), SiteInfo::getURL( 'home' ) );
        }

        // 19
        $detect_archives = apply_filters( 'wp2static_detect_archives', 1 );

        if ( $detect_archives ) {
            WsLog::l( 'DetectArchiveURLs::detect'  ,WP2STATIC_PHASES::URL_DETECT,20);
            // 20
            $arrays_to_merge[] = DetectArchiveURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        // 21
        $detect_categories = apply_filters( 'wp2static_detect_categories', 1 );

        if ( $detect_categories ) {
            WsLog::l( 'DetectCategoryURLs::detect' ,WP2STATIC_PHASES::URL_DETECT,21);
            // 22
            $arrays_to_merge[] = DetectCategoryURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        // 23
        $detect_category_pagination = apply_filters( 'wp2static_detect_category_pagination', 1 );

        if ( $detect_category_pagination ) {
            WsLog::l( 'DetectCategoryPaginationURLs::detect' ,WP2STATIC_PHASES::URL_DETECT,24);
            // 24
            $arrays_to_merge[] = DetectCategoryPaginationURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        // 25
        $detect_authors = apply_filters( 'wp2static_detect_authors', 1 );

        if ( $detect_authors ) {
            WsLog::l( 'DetectAuthorsURLs::detect' ,WP2STATIC_PHASES::URL_DETECT,26);
            // 26
            $arrays_to_merge[] = DetectAuthorsURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        // 27
        $detect_authors_pagination = apply_filters( 'wp2static_detect_authors_pagination', 1 );

        if ( $detect_authors_pagination ) {
            WsLog::l( 'DetectAuthorPaginationURLs::detect' ,WP2STATIC_PHASES::URL_DETECT,27);
            // 28
            $arrays_to_merge[] = DetectAuthorPaginationURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        WsLog::l( 'Merge and clean urls' ,WP2STATIC_PHASES::URL_DETECT,28);
        $url_queue = call_user_func_array( 'array_merge', $arrays_to_merge );
        // 29
        $url_queue = FilesHelper::cleanDetectedURLs( $url_queue );

        $url_queue = apply_filters(
            'wp2static_modify_initial_crawl_list',
            $url_queue
        );

        $unique_urls = array_unique( $url_queue );

        // No longer truncate before adding
        // addUrls is now doing INSERT IGNORE based on URL hash to be
        // additive and not error on duplicate

        // 30
        CrawlQueue::addUrls( $unique_urls );

        $total_detected = (string) count( $unique_urls );

        WsLog::l(
            "Detection complete. $total_detected URLs added to Crawl Queue."
            ,WP2STATIC_PHASES::URL_DETECT,30);

        WsLog::setPhase(WP2STATIC_PHASES::NO_PHASE);
        return $total_detected;
    }
}

