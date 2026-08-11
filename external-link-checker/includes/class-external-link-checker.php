<?php
/**
 * Main plugin class
 *
 * @package External_Link_Checker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * External_Link_Checker Class
 */
class External_Link_Checker {

    /**
     * Single instance of the class
     *
     * @var External_Link_Checker
     */
    private static $instance = null;

    /**
     * Get instance of the class
     *
     * @return External_Link_Checker
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain
        add_action('init', array($this, 'load_textdomain'));

        // Initialize admin
        if (is_admin()) {
            $admin = new ELC_Admin();
            $admin->init();
        }

        // Register activation/deactivation hooks are in main plugin file
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'external-link-checker',
            false,
            dirname(ELC_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Check if URL is external
     *
     * @param string $url The URL to check.
     * @return bool True if external, false if internal.
     */
    public function is_external_url($url) {
        $site_url = site_url();
        $home_url = home_url();

        // Parse the URL
        $parsed = parse_url($url);

        // If no host, it's a relative URL (internal)
        if (!isset($parsed['host'])) {
            return false;
        }

        $host = $parsed['host'];

        // Check against site URL and home URL
        $site_host = parse_url($site_url, PHP_URL_HOST);
        $home_host = parse_url($home_url, PHP_URL_HOST);

        // Also check www subdomain variations
        $hosts_to_check = array($site_host, $home_host);
        foreach ($hosts_to_check as $h) {
            if ($h && "www.$h" !== $h) {
                $hosts_to_check[] = "www.$h";
            }
        }
        $hosts_to_check = array_unique(array_filter($hosts_to_check));

        // If host matches any of our hosts, it's internal
        if (in_array($host, $hosts_to_check, true)) {
            return false;
        }

        return true;
    }

    /**
     * Extract links from content
     *
     * @param string $content The content to scan.
     * @return array Array of links with anchor text.
     */
    public function extract_links($content) {
        $links = array();

        // Match all <a> tags with href attributes
        preg_match_all('/<a\s+(?:[^>]*?\s+)?href=["\']([^"\']*?)["\'](?:[^>]*?)>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $url = trim($match[1]);
            $anchor_text = trim(strip_tags($match[2]));

            // Skip empty URLs
            if (empty($url)) {
                continue;
            }

            // Skip mailto, tel, javascript, etc.
            if (preg_match('/^(mailto:|tel:|javascript:|#)/i', $url)) {
                continue;
            }

            $links[] = array(
                'url'         => $url,
                'anchor_text' => $anchor_text,
            );
        }

        return $links;
    }
}
