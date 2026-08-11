<?php
/**
 * Scanner class for External Link Checker
 *
 * @package External_Link_Checker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ELC_Scanner Class
 */
class ELC_Scanner {

    /**
     * Excluded domains
     *
     * @var array
     */
    private $excluded_domains = array();

    /**
     * Batch size for scanning
     *
     * @var int
     */
    private $batch_size = 10;

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout = 15;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_excluded_domains();
        $this->load_settings();
    }

    /**
     * Load excluded domains from options
     */
    private function load_excluded_domains() {
        $excluded = get_option('elc_excluded_domains', array());
        if (is_array($excluded) && !empty($excluded)) {
            $this->excluded_domains = array_map('trim', $excluded);
        }
    }

    /**
     * Load scanner settings
     */
    private function load_settings() {
        $this->batch_size = (int) get_option('elc_batch_size', 10);
        $this->timeout    = (int) get_option('elc_timeout', 15);
    }

    /**
     * Check if domain is excluded
     *
     * @param string $url The URL to check.
     * @return bool True if excluded, false otherwise.
     */
    public function is_domain_excluded($url) {
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return false;
        }

        // Remove www. prefix for comparison
        $host = preg_replace('/^www\./i', '', $host);

        foreach ($this->excluded_domains as $excluded) {
            $excluded = preg_replace('/^www\./i', '', trim($excluded));

            if ($host === $excluded || strpos($host, '.' . $excluded) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan a single post for external links
     *
     * @param int $post_id Post ID.
     * @return array Array of external links found.
     */
    public function scan_post($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return array();
        }

        // Combine post content and excerpt
        $content = $post->post_content . ' ' . $post->post_excerpt;

        // Use main plugin class to extract links
        $plugin       = External_Link_Checker::get_instance();
        $all_links    = $plugin->extract_links($content);
        $external_links = array();

        foreach ($all_links as $link) {
            // Resolve relative URLs
            $url = $this->resolve_url($link['url']);

            // Check if external
            if ($plugin->is_external_url($url)) {
                // Check if domain is excluded
                if (!$this->is_domain_excluded($url)) {
                    $external_links[] = array(
                        'url'         => $url,
                        'anchor_text' => $link['anchor_text'],
                        'post_id'     => $post_id,
                        'post_title'  => $post->post_title,
                        'post_type'   => $post->post_type,
                    );
                }
            }
        }

        return $external_links;
    }

    /**
     * Resolve relative URLs to absolute
     *
     * @param string $url The URL to resolve.
     * @return string Absolute URL.
     */
    private function resolve_url($url) {
        // If already absolute, return as-is
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        // Handle protocol-relative URLs
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }

        // Handle root-relative URLs
        if (strpos($url, '/') === 0) {
            return home_url($url);
        }

        // For other relative URLs, use home URL as base
        return home_url('/' . ltrim($url, '/'));
    }

    /**
     * Check a single URL
     *
     * @param string $url The URL to check.
     * @return array Result array with status, http_code, etc.
     */
    public function check_url($url) {
        $result = array(
            'url'          => $url,
            'status'       => 'needs_review',
            'http_code'    => 0,
            'redirect_url' => '',
            'error_message' => '',
        );

        // Sanitize URL
        $url = esc_url_raw($url);

        if (empty($url)) {
            $result['status']       = 'broken';
            $result['http_code']    = 0;
            $result['error_message'] = 'Invalid URL';
            return $result;
        }

        // Check if domain is excluded
        if ($this->is_domain_excluded($url)) {
            $result['status']       = 'needs_review';
            $result['error_message'] = 'Domain is excluded from checking';
            return $result;
        }

        // Make HTTP request
        $response = wp_remote_get(
            $url,
            array(
                'timeout'         => $this->timeout,
                'redirection'     => 5,
                'user-agent'      => 'WordPress External Link Checker/' . ELC_VERSION,
                'sslverify'       => true,
                'limit_response_size' => 1024 * 10, // Limit to 10KB
            )
        );

        // Check for WP error
        if (is_wp_error($response)) {
            $result['status']        = 'needs_review';
            $result['error_message'] = $response->get_error_message();
            return $result;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $result['http_code'] = $http_code;

        // Check for redirects
        $redirect_count = wp_remote_retrieve_header($response, 'x-redirect-count');
        $location       = wp_remote_retrieve_header($response, 'location');

        if (!empty($location) && $http_code >= 300 && $http_code < 400) {
            $result['status']       = 'redirected';
            $result['redirect_url'] = $location;
            return $result;
        }

        // Determine status based on HTTP code
        if ($http_code >= 200 && $http_code < 300) {
            $result['status'] = 'working';
        } elseif ($http_code >= 300 && $http_code < 400) {
            $result['status']       = 'redirected';
            $result['redirect_url'] = $location;
        } elseif ($http_code >= 400 && $http_code < 500) {
            // Client errors - likely broken
            $result['status']        = 'broken';
            $result['error_message'] = $this->get_http_status_message($http_code);
        } elseif ($http_code >= 500) {
            // Server errors - might be temporary
            $result['status']        = 'needs_review';
            $result['error_message'] = $this->get_http_status_message($http_code);
        } else {
            $result['status']        = 'needs_review';
            $result['error_message'] = 'Unknown response';
        }

        return $result;
    }

    /**
     * Get human-readable HTTP status message
     *
     * @param int $code HTTP status code.
     * @return string Status message.
     */
    private function get_http_status_message($code) {
        $messages = array(
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            410 => 'Gone',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        );

        return isset($messages[$code]) ? $messages[$code] : "HTTP $code";
    }

    /**
     * Scan all published posts
     *
     * @param int $limit Maximum number of posts to scan.
     * @return int Number of posts scanned.
     */
    public function scan_all_posts($limit = 100) {
        $plugin = External_Link_Checker::get_instance();

        // Get published posts
        $args = array(
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
        );

        $posts = get_posts($args);
        $scanned = 0;

        foreach ($posts as $post_id) {
            $links = $this->scan_post($post_id);

            foreach ($links as $link) {
                // Check the URL
                $check_result = $this->check_url($link['url']);

                // Save to database
                $data = array(
                    'post_id'       => $link['post_id'],
                    'post_title'    => $link['post_title'],
                    'post_type'     => $link['post_type'],
                    'external_url'  => $link['url'],
                    'anchor_text'   => $link['anchor_text'],
                    'status'        => $check_result['status'],
                    'http_code'     => $check_result['http_code'],
                    'redirect_url'  => $check_result['redirect_url'],
                    'error_message' => $check_result['error_message'],
                );

                ELC_Database::upsert_link($data);
            }

            $scanned++;

            // Avoid timeout - process in batches
            if ($scanned % 10 === 0) {
                sleep(1);
            }
        }

        return $scanned;
    }

    /**
     * Recheck a specific link
     *
     * @param int $link_id Link ID from database.
     * @return array Updated result.
     */
    public function recheck_link($link_id) {
        global $wpdb;
        ELC_Database::init();

        $link = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . ELC_Database::$table_name . " WHERE id = %d",
                $link_id
            ),
            ARRAY_A
        );

        if (!$link) {
            return array('error' => 'Link not found');
        }

        // Check the URL
        $result = $this->check_url($link['external_url']);

        // Update database
        $updated = array(
            'status'        => $result['status'],
            'http_code'     => $result['http_code'],
            'redirect_url'  => $result['redirect_url'],
            'error_message' => $result['error_message'],
            'last_checked'  => current_time('mysql'),
        );

        $wpdb->update(
            ELC_Database::$table_name,
            $updated,
            array('id' => $link_id),
            array('%s', '%d', '%s', '%s', '%s'),
            array('%d')
        );

        return $result;
    }

    /**
     * Add a domain to exclusion list
     *
     * @param string $domain Domain to exclude.
     * @return bool True on success.
     */
    public function add_excluded_domain($domain) {
        $excluded = get_option('elc_excluded_domains', array());

        if (!is_array($excluded)) {
            $excluded = array();
        }

        $domain = trim($domain);
        $domain = preg_replace('/^www\./i', '', $domain);

        if (!empty($domain) && !in_array($domain, $excluded, true)) {
            $excluded[] = $domain;
            update_option('elc_excluded_domains', $excluded);
            $this->excluded_domains = $excluded;
            return true;
        }

        return false;
    }

    /**
     * Remove a domain from exclusion list
     *
     * @param string $domain Domain to remove.
     * @return bool True on success.
     */
    public function remove_excluded_domain($domain) {
        $excluded = get_option('elc_excluded_domains', array());

        if (!is_array($excluded)) {
            return false;
        }

        $domain = preg_replace('/^www\./i', '', trim($domain));
        $key    = array_search($domain, $excluded, true);

        if ($key !== false) {
            unset($excluded[$key]);
            update_option('elc_excluded_domains', array_values($excluded));
            $this->excluded_domains = array_values($excluded);
            return true;
        }

        return false;
    }

    /**
     * Get all excluded domains
     *
     * @return array Array of excluded domains.
     */
    public function get_excluded_domains() {
        return $this->excluded_domains;
    }
}
