<?php

namespace WP2Static;

class WP2STATIC_PHASES
{
    const URL_DETECT = 'URL_DETECT';
    const CRAWL = 'CRAWL';
    const POST_PROCESS = 'POST_PROCESS';
    const DEPLOY = 'DEPLOY';
    const POST_DEPLOY = 'POST_DEPLOY';
    const NO_PHASE = '';
};

class WPSTATIC_PHASE_MARKERS
{
    const START = 'WPSTATIC_PHASE_MARKERS_START';
    const END = 'WPSTATIC_PHASE_MARKERS_END';
    const DEPLOY_START = 'WPSTATIC_PHASE_MARKERS_DEPLOYSTART';
    const DEPLOY_END = 'WPSTATIC_PHASE_MARKERS_DEPLOYEND';
}

// TODO: add option in UI to also write to PHP error_log
class WsLog {
    public static $currentPhase = '';
    public static $allItemCount = -1;

    public static function createTable() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_log';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            log TEXT NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function setPhase ($phase) {
        self::$currentPhase = $phase;
        self::$allItemCount = -1;
    }

    public static function setAllItemCount($newCount){
        self::$allItemCount = $newCount;
    }

    public static function l( string $text, string $phase = "", int $currentItem = -1 ) : void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp2static_log';
        $prefix = ($phase === "" ? self::$currentPhase : $phase);
        if (self::$allItemCount !== -1 && $currentItem !== -1) {
            $prefix .= " | " . $currentItem . "/" . self::$allItemCount;
        }
        $logline = "[" . $prefix . "] " . $text;
        $wpdb->insert(
            $table_name,
            [
                'log' => $logline,
            ]
        );

        if ( defined( 'WP_CLI' ) ) {
            $date = current_time( 'c' );
            \WP_CLI::log(
                \WP_CLI::colorize( "%W[$date] %n$text" )
            );
        }
    }

    /**
     * Log multiple lines at once
     *
     * @param string[] $lines List of lines to log
     */
    public static function lines( array $lines ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_log';
        $current_time = current_time( 'mysql' );
        $query = "INSERT INTO $table_name (log) VALUES " .
            implode(
                ',',
                array_fill( 0, count( $lines ), '(%s)' )
            );

        $wpdb->query( $wpdb->prepare( $query, $lines ) );
    }

    /**
     * Get all log lines
     *
     * @return mixed[] array of Log items
     */
    public static function getAll($withFiltering = false) : array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp2static_log';
        $logs = array();

        // find last deploy start
        $lastLog = $wpdb->get_results("SELECT id FROM $table_name WHERE log LIKE '%".WPSTATIC_PHASE_MARKERS::DEPLOY_START."%' ORDER BY id DESC LIMIT 1",ARRAY_A);
        $lastid = 0;
        if (count($lastLog)>0){
            $lastid = $lastLog[0]['id'];
        }

        if ($withFiltering) {
            $logs = $wpdb->get_results("SELECT time, log FROM $table_name WHERE log NOT LIKE '%WPSTATIC_PHASE_MARKERS_%' AND id >= ".(int)$lastid." ORDER BY id DESC LIMIT 5000");
        }else{
            $logs = $wpdb->get_results("SELECT time, log FROM $table_name WHERE id >= ".(int)$lastid." ORDER BY id DESC");
        }
        return $logs;
    }

    public static function pollPhases()
    {
        // this is a list of phases (+ DEPLOY START + DEPLOY END) each key contains the time + progress + finished as bool
        $ret = array();
        $loglines = self::getAll(false);
        // parse loglines and try to figure out what to keep
        $lastPhase = '';

        foreach ($loglines as $line) {
            $l = $line->log;
            if (strpos($l, WPSTATIC_PHASE_MARKERS::DEPLOY_END) !== FALSE) {
                // deploy ended
                $ret[WPSTATIC_PHASE_MARKERS::DEPLOY_END] = array("time" => $line->time, "finished" => true);
                continue;
            }
            if (strpos($l, WPSTATIC_PHASE_MARKERS::DEPLOY_START) !== FALSE) {
                // deploy started
                $ret[WPSTATIC_PHASE_MARKERS::DEPLOY_START] = array("time" => $line->time, "finished" => true);
                continue;
            }
            // try to parse line if it starts with a phase
            if (strlen($l) == 0 || $l[0] !== '[') {
                continue;
            }
            $parsed = explode('] ', substr($l, 1), 2);
            if (count($parsed) != 2) {
                continue;
            }
            if ($parsed[0] === ''){
                continue;
            }
            $markers = explode(" | ", $parsed[0], 2);

            if ($markers[0] === $lastPhase){
                continue;
            }
            $lastPhase = $markers[0];

            // parsed + markers are the last lines per phase
            if ($parsed[1] === WPSTATIC_PHASE_MARKERS::END){
                $ret[$lastPhase] = array("time" => $line->time, "finished" => true);
                continue;
            }
            if ($parsed[1] === WPSTATIC_PHASE_MARKERS::START){
                $ret[$lastPhase] = array("time" => $line->time, "finished" => false);
                continue;
            }
            // we need to pass the percentage as well
            $ret[$lastPhase] = array("time" => $line->time, "finished" => false, "progress"=>count($markers) < 2 ? false : $markers[1]);
        }

        return $ret;
    }

    /**
     * Poll latest log lines
     */
    public static function poll() {
        return self::getAll(true);
    }

    /**
     *  Clear Log via truncation
     */
    public static function truncate() : void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp2static_log';
        $wpdb->query( "TRUNCATE TABLE $table_name" );
        self::l( 'Deleted all Logs' );
    }
}

