<?php
/**
 * Database handler for External Link Checker
 *
 * @package External_Link_Checker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ELC_Database Class
 */
class ELC_Database {

    /**
     * Table name for scanned links
     *
     * @var string
     */
    public static $table_name;

    /**
     * Table name for scan options
     *
     * @var string
     */
    public static $options_table_name;

    /**
     * Initialize table names
     */
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'elc_links';
        self::$options_table_name = $wpdb->prefix . 'elc_options';
    }

    /**
     * Create database tables on activation
     */
    public static function create_tables() {
        global $wpdb;

        self::init();

        $charset_collate = $wpdb->get_charset_collate();

        $sql_links = "CREATE TABLE " . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            post_title varchar(500) DEFAULT '',
            post_type varchar(50) DEFAULT 'post',
            external_url varchar(2048) NOT NULL,
            anchor_text varchar(500) DEFAULT '',
            status varchar(50) DEFAULT 'pending',
            http_code int(11) DEFAULT 0,
            redirect_url varchar(2048) DEFAULT '',
            error_message text,
            last_checked datetime DEFAULT '0000-00-00 00:00:00',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY last_checked (last_checked)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_links);

        // Add option to track plugin version
        add_option('elc_db_version', ELC_VERSION);
    }

    /**
     * Get all links by status
     *
     * @param string $status The status to filter by.
     * @param int    $limit  Number of results to return.
     * @param int    $offset Offset for pagination.
     * @return array Array of link records.
     */
    public static function get_links_by_status($status = 'all', $limit = 50, $offset = 0) {
        global $wpdb;
        self::init();

        if ('all' === $status) {
            $where = '1=1';
        } else {
            $where = $wpdb->prepare('status = %s', $status);
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_name . "
                WHERE $where
                ORDER BY last_checked DESC
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return $results;
    }

    /**
     * Get link by URL and post ID
     *
     * @param string $url     The external URL.
     * @param int    $post_id The post ID.
     * @return array|false Link record or false if not found.
     */
    public static function get_link($url, $post_id) {
        global $wpdb;
        self::init();

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_name . "
                WHERE external_url = %s AND post_id = %d
                LIMIT 1",
                $url,
                $post_id
            ),
            ARRAY_A
        );

        return $result;
    }

    /**
     * Insert or update a link record
     *
     * @param array $data Link data.
     * @return int|false Inserted/updated ID or false on failure.
     */
    public static function upsert_link($data) {
        global $wpdb;
        self::init();

        $existing = self::get_link($data['external_url'], $data['post_id']);

        if ($existing) {
            $updated = $wpdb->update(
                self::$table_name,
                array(
                    'post_title'    => $data['post_title'],
                    'post_type'     => $data['post_type'],
                    'anchor_text'   => $data['anchor_text'],
                    'status'        => $data['status'],
                    'http_code'     => $data['http_code'],
                    'redirect_url'  => $data['redirect_url'],
                    'error_message' => $data['error_message'],
                    'last_checked'  => current_time('mysql'),
                ),
                array(
                    'id' => $existing['id'],
                )
            );

            return $updated ? $existing['id'] : false;
        } else {
            $inserted = $wpdb->insert(
                self::$table_name,
                array(
                    'post_id'       => $data['post_id'],
                    'post_title'    => $data['post_title'],
                    'post_type'     => $data['post_type'],
                    'external_url'  => $data['external_url'],
                    'anchor_text'   => $data['anchor_text'],
                    'status'        => $data['status'],
                    'http_code'     => $data['http_code'],
                    'redirect_url'  => $data['redirect_url'],
                    'error_message' => $data['error_message'],
                    'last_checked'  => current_time('mysql'),
                    'created_at'    => current_time('mysql'),
                )
            );

            return $inserted ? $wpdb->insert_id : false;
        }
    }

    /**
     * Delete link by ID
     *
     * @param int $id Link ID.
     * @return bool True on success, false on failure.
     */
    public static function delete_link($id) {
        global $wpdb;
        self::init();

        return $wpdb->delete(
            self::$table_name,
            array('id' => $id),
            array('%d')
        );
    }

    /**
     * Delete all links for a post
     *
     * @param int $post_id Post ID.
     * @return bool True on success, false on failure.
     */
    public static function delete_links_by_post($post_id) {
        global $wpdb;
        self::init();

        return $wpdb->delete(
            self::$table_name,
            array('post_id' => $post_id),
            array('%d')
        );
    }

    /**
     * Clear all scan results
     *
     * @return bool True on success, false on failure.
     */
    public static function clear_all_links() {
        global $wpdb;
        self::init();

        return $wpdb->query("TRUNCATE TABLE " . self::$table_name);
    }

    /**
     * Get statistics
     *
     * @return array Statistics array.
     */
    public static function get_statistics() {
        global $wpdb;
        self::init();

        $stats = array(
            'total_links'   => 0,
            'working'       => 0,
            'broken'        => 0,
            'redirected'    => 0,
            'needs_review'  => 0,
            'pending'       => 0,
            'posts_scanned' => 0,
        );

        // Total links
        $stats['total_links'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::$table_name);

        // Count by status
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
            FROM " . self::$table_name . "
            GROUP BY status",
            ARRAY_A
        );

        foreach ($results as $row) {
            $status = $row['status'];
            $count  = (int) $row['count'];

            switch ($status) {
                case 'working':
                    $stats['working'] = $count;
                    break;
                case 'broken':
                    $stats['broken'] = $count;
                    break;
                case 'redirected':
                    $stats['redirected'] = $count;
                    break;
                case 'needs_review':
                    $stats['needs_review'] = $count;
                    break;
                case 'pending':
                    $stats['pending'] = $count;
                    break;
            }
        }

        // Unique posts scanned
        $stats['posts_scanned'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM " . self::$table_name
        );

        return $stats;
    }

    /**
     * Get pending links count
     *
     * @return int Number of pending links.
     */
    public static function get_pending_count() {
        global $wpdb;
        self::init();

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . self::$table_name . " WHERE status = 'pending'"
        );
    }

    /**
     * Get links that need rechecking (old results)
     *
     * @param int    $limit Number of links to return.
     * @param string $older_than How old the results should be.
     * @return array Array of link records.
     */
    public static function get_old_links($limit = 50, $older_than = '-7 days') {
        global $wpdb;
        self::init();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_name . "
                WHERE last_checked < DATE_SUB(NOW(), INTERVAL 7 DAY)
                OR status = 'pending'
                ORDER BY last_checked ASC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }
}
