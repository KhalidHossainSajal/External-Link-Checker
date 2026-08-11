<?php
/**
 * Plugin Name: External Link Checker
 * Plugin URI: https://github.com/khalihossain/external-link-checker
 * Description: Find broken external links in your WordPress website before your visitors do. A lightweight plugin that scans your content for external links and identifies broken, redirected, or unavailable URLs.
 * Version: 1.0.0
 * Author: Md. Khalid Hossain
 * Author URI: https://khalihossain.com.bd
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: external-link-checker
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ELC_VERSION', '1.0.0');
define('ELC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ELC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ELC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once ELC_PLUGIN_DIR . 'includes/class-external-link-checker.php';
require_once ELC_PLUGIN_DIR . 'includes/class-elc-scanner.php';
require_once ELC_PLUGIN_DIR . 'includes/class-elc-database.php';
require_once ELC_PLUGIN_DIR . 'includes/class-elc-admin.php';

/**
 * Initialize the plugin
 */
function elc_init() {
    $plugin = External_Link_Checker::get_instance();
    $plugin->init();
}
add_action('plugins_loaded', 'elc_init');

/**
 * Activation hook
 */
function elc_activate() {
    require_once ELC_PLUGIN_DIR . 'includes/class-elc-database.php';
    ELC_Database::create_tables();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'elc_activate');

/**
 * Deactivation hook
 */
function elc_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'elc_deactivate');
