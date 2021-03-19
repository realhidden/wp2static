<?php

namespace WP2Static;

use WP2StaticGuzzleHttp\Client;
use WP2StaticGuzzleHttp\Psr7\Request;
use WP2StaticGuzzleHttp\Psr7\Response;

class DetectSitemapsURLs {

    /**
     * Detect Authors URLs
     *
     * @return string[] list of URLs
     * @throws WP2StaticException
     */
    public static function detect( string $wp_site_url ) : array {
        $sitemaps_urls = [];
        $parser = new SitemapParser( 'WP2Static.com', [ 'strict' => false ] );

        // for multisite we cannot crawl the site, since it won't point to the proper home url
        $home_uri = rtrim( SiteInfo::getURL( 'home' ), '/' );

        $port_override = apply_filters(
            'wp2static_curl_port',
            null
        );

        // TODO: port override won't work if we have a subdir
        if ( $port_override ) {
            $home_uri = "{$home_uri}:{$port_override}";
        }

        $client = new Client(
            [
                'verify' => false,
                'http_errors' => false,
                'allow_redirects' => [
                    'max' => 1,
                    // required to get effective_url
                    'track_redirects' => true,
                ],
                'connect_timeout'  => 0,
                'timeout' => 600,
                'headers' => [
                    'User-Agent' => apply_filters(
                        'wp2static_curl_user_agent',
                        'WP2Static.com',
                    ),
                ],
            ]
        );

        $headers = [];

        $auth_user = CoreOptions::getValue( 'basicAuthUser' );

        if ( $auth_user ) {
            $auth_password = CoreOptions::getValue( 'basicAuthPassword' );

            if ( $auth_password ) {
                $headers['auth'] = [ $auth_user, $auth_password ];
            }
        }

        $request = new Request( 'GET', $home_uri . '/robots.txt', $headers );

        $response = $client->send( $request );

        $robots_exists = $response->getStatusCode() === 200;

        try {
            $sitemaps = [];

            // if robots exists, parse for possible sitemaps
            if ( $robots_exists ) {
                $sitemaps_urls[] = '/' . str_replace($wp_site_url,'',$home_uri . '/robots.txt');
                $parser->parseRecursive( $home_uri . 'robots.txt' );
                $sitemaps = $parser->getSitemaps();
            }

            // if no sitemaps add known sitemaps
            if ( $sitemaps === [] ) {
                $sitemaps = [
                    // we're assigning empty arrays to match sitemaps library
                    '/sitemap.xml' => [], // normal sitemap
                    '/sitemap_index.xml' => [], // yoast sitemap
                    '/wp_sitemap.xml' => [], // wp 5.5 sitemap
                ];
            }

            foreach ( array_keys( $sitemaps ) as $sitemap ) {
                if ( ! is_string( $sitemap ) ) {
                    continue;
                }

                $request = new Request( 'GET', $home_uri . $sitemap, $headers );
                $response = $client->send( $request );

                $status_code = $response->getStatusCode();
                if ( $status_code === 200 ) {
                    // add the newly found sitemap
                    $sitemaps_urls[] = '/' . str_replace($wp_site_url,'',$home_uri . $sitemap);

                    $parser->parse( $home_uri . $sitemap );
                    $extract_sitemaps = $parser->getSitemaps();

                    foreach ( $extract_sitemaps as $url => $tags ) {
                        $sitemaps_urls[] = '/' . str_replace(
                            $wp_site_url,
                            '',
                            $url
                        );
                    }
                }
            }
        } catch ( WP2StaticException $e ) {
            WsLog::l( $e->getMessage() );
            throw new WP2StaticException( $e->getMessage(), 0, $e );
        }

        return $sitemaps_urls;
    }
}
