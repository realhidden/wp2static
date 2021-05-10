<?php
/*
    Crawler

    Crawls URLs in WordPressSite, saving them to StaticSite

*/

namespace WP2Static;

use WP2StaticGuzzleHttp\Client;
use WP2StaticGuzzleHttp\Psr7\Request;
use WP2StaticGuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use WP2StaticGuzzleHttp\Exception\ClientException;


define( 'WP2STATIC_REDIRECT_CODES', [ 301, 302, 303, 307, 308 ] );

class Crawler {

    /**
     * @var Client
     */
    private $client;
    /**
     * @var string
     */
    private $site_path;

    /**
     * Crawler constructor
     */
    public function __construct() {
        $this->site_path = rtrim( SiteInfo::getURL( 'site' ), '/' );

        $port_override = apply_filters(
            'wp2static_curl_port',
            null
        );

        $base_uri = $this->site_path;

        if ( $port_override ) {
            $base_uri = "{$base_uri}:{$port_override}";
        }

        $this->client = new Client(
            [
                'base_uri' => $base_uri,
                'verify' => false,
                'http_errors' => false,
                'allow_redirects' => [
                    'max' => 1,
                    // required to get effective_url
                    'track_redirects' => true,
                ],
                'connect_timeout' => 30,
                'timeout' => 30,
                'read_timeout' => 30,
                'headers' => [
                    'User-Agent' => apply_filters(
                        'wp2static_curl_user_agent',
                        'WP2Static.com',
                    ),
                ],
            ]
        );
    }

    public static function wp2staticCrawl( string $static_site_path, string $crawler_slug ) : void {
        if ( 'wp2static' === $crawler_slug ) {
            $crawler = new Crawler();
            $crawler->crawlSite( $static_site_path );
        }
    }

    /**
     * Crawls URLs in WordPressSite, saving them to StaticSite
     */
    public function crawlSite( string $static_site_path ) : void {
        global $wpdb;
        $crawled = 0;
        $cache_hits = 0;

        // Add some limits for the crawler if present
        $offset = null;
        $limit = null;

        if (isset($_REQUEST['offset'])){
            $offset = (int)$_REQUEST['offset'];
        }
        if (isset($_REQUEST['limit'])){
            $limit = (int)$_REQUEST['limit'];
        }

        if (!is_null($offset) && !is_null($limit)) {
            WsLog::l("Starting to crawl detected URLs ($offset - $limit)");
        }else{
            WsLog::l('Starting to crawl detected URLs.');
        }

        $site_host = parse_url( $this->site_path, PHP_URL_HOST );
        $site_port = parse_url( $this->site_path, PHP_URL_PORT );
        $site_host = $site_port ? $site_host . ":$site_port" : $site_host;
        $site_urls = [ "http://$site_host", "https://$site_host" ];

        $use_crawl_cache = apply_filters(
            'wp2static_use_crawl_cache',
            CoreOptions::getValue( 'useCrawlCaching' )
        );

        WsLog::l( ( $use_crawl_cache ? 'Using' : 'Not using' ) . ' CrawlCache.' );

        // TODO: use some Iterable or other performance optimisation here
        // to help reduce resources for large URL sites

        /**
         * When you call method that executes database query in for loop
         * you are calling method and querying database for every loop iteration.
         * To avoid that you need to assing the result to a variable.
         */

        $crawlable_paths = CrawlQueue::getCrawlablePaths($offset, $limit);
        $site_url = SiteInfo::getUrl( 'site' );
        $site_dir = SiteInfo::getPath('site');

        // grab safe locations
        $safe_locations = array(
            array(
                "prefix" => str_replace($site_url, '/', SiteInfo::getUrl('uploads')),
                "dir" => $upload_dir = SiteInfo::getPath('uploads')
            ),
            array(
                "prefix" => str_replace($site_url, '/', SiteInfo::getUrl('includes')),
                "dir" => $upload_dir = SiteInfo::getPath('includes')
            ),
            array(
                "prefix" => str_replace($site_url, '/', SiteInfo::getUrl('plugins')),
                "dir" => $upload_dir = SiteInfo::getPath('plugins')
            ),
            array(
                "prefix" => str_replace($site_url, '/', SiteInfo::getUrl('muplugins')),
                "dir" => $upload_dir = SiteInfo::getPath('muplugins')
            ),
            array(
                "prefix" => str_replace($site_url, '/', SiteInfo::getUrl('themes_root')),
                "dir" => $upload_dir = SiteInfo::getPath('themes_root')
            )
        );
        // get difference between home and uploads URL

        foreach ( $crawlable_paths as $root_relative_path ) {
            $crawled_contents = '';
            $page_hash = '';

            $file_shortcut = null;

            // Shortcut for safe locations
            foreach ($safe_locations as $s1) {
                if (substr($root_relative_path, 0, strlen($s1['prefix'])) === $s1['prefix']) {
                    $file = $s1['dir'] . substr($root_relative_path, strlen($s1['prefix']));
                    if (file_exists($file)) {
                        $file_shortcut = $file;
                    }
                }
            }

            // preflight for shortcut
            if (!is_null($file_shortcut)){
                // just to doublecheck we are NOT loading a <?php file
                $crawled_contents = file_get_contents($file_shortcut);
                if (stripos('<?php',$crawled_contents) !== FALSE){
                    // fall back to URL based data grab
                    $file_shortcut = null;
                    $crawled_contents = '';
                }else {
                    $page_hash = md5_file($file_shortcut);
                }
            }

            if (!is_null($file_shortcut)){
                // WsLog::l('File shortcut for ' . $root_relative_path);
                $status_code = 200;
                $redirect_to = null;
            }else {
                $absolute_uri = new URL( $this->site_path . $root_relative_path );
                $url = $absolute_uri->get();
                $response = $this->crawlURL($url);

                if (!$response) {
                    WsLog::l('Error for URL ' . $root_relative_path);
                    continue;
                }

                $crawled_contents = (string)$response->getBody();
                $status_code = $response->getStatusCode();

                if ($status_code === 200) {
                    WsLog::l('Crawled ' . $root_relative_path);
                }
                if ($status_code === 404) {
                    WsLog::l('404 for URL ' . $root_relative_path);
                    CrawlCache::rmUrl($root_relative_path);
                    $crawled_contents = null;
                } elseif (in_array($status_code, WP2STATIC_REDIRECT_CODES)) {
                    $crawled_contents = null;
                }

                $redirect_to = null;

                if (in_array($status_code, WP2STATIC_REDIRECT_CODES)) {
                    $effective_url = $url;

                    // returns as string
                    $redirect_history =
                        $response->getHeaderLine('X-Guzzle-Redirect-History');

                    if ($redirect_history) {
                        $redirects = explode(', ', $redirect_history);
                        $effective_url = end($redirects);
                    }

                    $redirect_to =
                        (string)str_replace($site_urls, '', $effective_url);
                    $page_hash = md5($status_code . $redirect_to);
                } elseif (!is_null($crawled_contents)) {
                    $page_hash = md5($crawled_contents);
                } else {
                    $page_hash = md5((string)$status_code);
                }
            }

            // TODO: as John mentioned, we're only skipping the saving,
            // not crawling here. Let's look at improving that... or speeding
            // up with async requests, at least
            if ( $use_crawl_cache ) {
                // if not already cached
                if ( CrawlCache::getUrl( $root_relative_path, $page_hash ) ) {
                    $cache_hits++;

                    continue;
                }
            }

            $crawled++;

            if ( $crawled_contents ) {
                // do some magic here - naive: if URL ends in /, save to /index.html
                // TODO: will need love for example, XML files
                // check content type, serve .xml/rss, etc instead
                if ( mb_substr( $root_relative_path, -1 ) === '/' ) {
                    StaticSite::add( $root_relative_path . 'index.html', $crawled_contents );
                } else {
                    StaticSite::add( $root_relative_path, $crawled_contents );
                }
            }

            CrawlCache::addUrl(
                $root_relative_path,
                $page_hash,
                $status_code,
                $redirect_to
            );

            // incrementally log crawl progress
            if ( $crawled % 300 === 0 ) {
                $notice = "Crawling progress: $crawled / " . count($crawlable_paths) . " crawled, $cache_hits skipped (cached).";
                WsLog::l( $notice );
            }
        }

        WsLog::l(
            "Crawling complete. $crawled crawled, $cache_hits skipped (cached)."
        );

        $args = [
            'staticSitePath' => $static_site_path,
            'crawled' => $crawled,
            'cache_hits' => $cache_hits,
        ];

        do_action( 'wp2static_crawling_complete', $args );
    }

    /**
     * Crawls a string of full URL within WordPressSite
     *
     * @return ResponseInterface|null response object
     */
    public function crawlURL( string $url ) : ?ResponseInterface {
        $headers = [];

        $auth_user = CoreOptions::getValue( 'basicAuthUser' );

        if ( $auth_user ) {
            $auth_password = CoreOptions::getValue( 'basicAuthPassword' );

            if ( $auth_password ) {
                $headers['auth'] = [ $auth_user, $auth_password ];
            }
        }

        $request = new Request( 'GET', $url, $headers );
        try {
            $response = $this->client->send( $request );
        } catch (WP2StaticGuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
        } catch (\Exception $e) {
            return null;
        }
        return $response;
    }
}
