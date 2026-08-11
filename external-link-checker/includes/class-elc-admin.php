<?php
/**
 * Admin interface for External Link Checker
 *
 * @package External_Link_Checker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ELC_Admin Class
 */
class ELC_Admin {

    /**
     * Page hook suffix
     *
     * @var string
     */
    private $page_hook;

    /**
     * Scanner instance
     *
     * @var ELC_Scanner
     */
    private $scanner;

    /**
     * Initialize admin
     */
    public function init() {
        $this->scanner = new ELC_Scanner();

        // Add menu page
        add_action('admin_menu', array($this, 'add_menu_page'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Handle AJAX actions
        add_action('wp_ajax_elc_scan_posts', array($this, 'ajax_scan_posts'));
        add_action('wp_ajax_elc_recheck_link', array($this, 'ajax_recheck_link'));
        add_action('wp_ajax_elc_delete_link', array($this, 'ajax_delete_link'));
        add_action('wp_ajax_elc_clear_results', array($this, 'ajax_clear_results'));
        add_action('wp_ajax_elc_add_excluded_domain', array($this, 'ajax_add_excluded_domain'));
        add_action('wp_ajax_elc_remove_excluded_domain', array($this, 'ajax_remove_excluded_domain'));
        add_action('wp_ajax_elc_load_links', array($this, 'ajax_load_links'));
    }

    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        $this->page_hook = add_menu_page(
            __('External Link Checker', 'external-link-checker'),
            __('External Links', 'external-link-checker'),
            'manage_options',
            'external-link-checker',
            array($this, 'render_dashboard'),
            'dashicons-external',
            80
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('elc_settings', 'elc_batch_size', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 10,
        ));

        register_setting('elc_settings', 'elc_timeout', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 15,
        ));

        register_setting('elc_settings', 'elc_excluded_domains', array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize_excluded_domains'),
            'default'           => array(),
        ));
    }

    /**
     * Sanitize excluded domains
     *
     * @param array $domains Array of domains.
     * @return array Sanitized domains.
     */
    public function sanitize_excluded_domains($domains) {
        if (!is_array($domains)) {
            return array();
        }

        $sanitized = array();
        foreach ($domains as $domain) {
            $domain = trim($domain);
            $domain = preg_replace('/^www\./i', '', $domain);
            $domain = sanitize_text_field($domain);

            if (!empty($domain)) {
                $sanitized[] = $domain;
            }
        }

        return array_unique($sanitized);
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook) {
        if ('toplevel_page_external-link-checker' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'elc-admin-css',
            ELC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ELC_VERSION
        );

        wp_enqueue_script(
            'elc-admin-js',
            ELC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ELC_VERSION,
            true
        );

        wp_localize_script('elc-admin-js', 'elcAjax', array(
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('elc_admin_nonce'),
            'postEditUrl'     => admin_url('post.php?post=%d&action=edit'),
            'strings'         => array(
                'confirmDelete' => __('Are you sure you want to delete this link?', 'external-link-checker'),
                'confirmClear'  => __('Are you sure you want to clear all scan results? This cannot be undone.', 'external-link-checker'),
                'scanning'      => __('Scanning...', 'external-link-checker'),
                'complete'      => __('Scan complete!', 'external-link-checker'),
                'error'         => __('An error occurred.', 'external-link-checker'),
            ),
        ));
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $stats = ELC_Database::get_statistics();
        ?>
        <div class="wrap elc-dashboard">
            <h1><?php echo esc_html__('External Link Checker', 'external-link-checker'); ?></h1>

            <!-- Statistics Cards -->
            <div class="elc-stats-grid">
                <div class="elc-stat-card">
                    <div class="elc-stat-value"><?php echo number_format_i18n($stats['posts_scanned']); ?></div>
                    <div class="elc-stat-label"><?php echo esc_html__('Posts Scanned', 'external-link-checker'); ?></div>
                </div>
                <div class="elc-stat-card">
                    <div class="elc-stat-value"><?php echo number_format_i18n($stats['total_links']); ?></div>
                    <div class="elc-stat-label"><?php echo esc_html__('External Links', 'external-link-checker'); ?></div>
                </div>
                <div class="elc-stat-card elc-stat-working">
                    <div class="elc-stat-value"><?php echo number_format_i18n($stats['working']); ?></div>
                    <div class="elc-stat-label"><?php echo esc_html__('Working', 'external-link-checker'); ?></div>
                </div>
                <div class="elc-stat-card elc-stat-broken">
                    <div class="elc-stat-value"><?php echo number_format_i18n($stats['broken']); ?></div>
                    <div class="elc-stat-label"><?php echo esc_html__('Broken', 'external-link-checker'); ?></div>
                </div>
                <div class="elc-stat-card elc-stat-redirected">
                    <div class="elc-stat-value"><?php echo number_format_i18n($stats['redirected']); ?></div>
                    <div class="elc-stat-label"><?php echo esc_html__('Redirected', 'external-link-checker'); ?></div>
                </div>
                <div class="elc-stat-card elc-stat-review">
                    <div class="elc-stat-value"><?php echo number_format_i18n($stats['needs_review']); ?></div>
                    <div class="elc-stat-label"><?php echo esc_html__('Needs Review', 'external-link-checker'); ?></div>
                </div>
            </div>

            <!-- Scan Controls -->
            <div class="elc-controls">
                <button type="button" id="elc-scan-btn" class="button button-primary button-large">
                    <?php echo esc_html__('Scan All Posts', 'external-link-checker'); ?>
                </button>
                <button type="button" id="elc-clear-btn" class="button button-secondary">
                    <?php echo esc_html__('Clear Results', 'external-link-checker'); ?>
                </button>
                <span id="elc-scan-status"></span>
            </div>

            <!-- Filter Tabs -->
            <div class="elc-filters">
                <a href="#" class="elc-filter active" data-status="all"><?php echo esc_html__('All Links', 'external-link-checker'); ?></a>
                <a href="#" class="elc-filter" data-status="broken"><?php echo esc_html__('Broken', 'external-link-checker'); ?></a>
                <a href="#" class="elc-filter" data-status="redirected"><?php echo esc_html__('Redirected', 'external-link-checker'); ?></a>
                <a href="#" class="elc-filter" data-status="needs_review"><?php echo esc_html__('Needs Review', 'external-link-checker'); ?></a>
                <a href="#" class="elc-filter" data-status="working"><?php echo esc_html__('Working', 'external-link-checker'); ?></a>
            </div>

            <!-- Links Table -->
            <table class="wp-list-table widefat fixed striped" id="elc-links-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Post', 'external-link-checker'); ?></th>
                        <th><?php echo esc_html__('External URL', 'external-link-checker'); ?></th>
                        <th><?php echo esc_html__('Anchor Text', 'external-link-checker'); ?></th>
                        <th><?php echo esc_html__('Status', 'external-link-checker'); ?></th>
                        <th><?php echo esc_html__('HTTP Code', 'external-link-checker'); ?></th>
                        <th><?php echo esc_html__('Last Checked', 'external-link-checker'); ?></th>
                        <th><?php echo esc_html__('Actions', 'external-link-checker'); ?></th>
                    </tr>
                </thead>
                <tbody id="elc-links-body">
                    <tr>
                        <td colspan="7"><?php echo esc_html__('Loading...', 'external-link-checker'); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Settings Section -->
            <div class="elc-settings-section">
                <h2><?php echo esc_html__('Settings', 'external-link-checker'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('elc_settings'); ?>
                    <?php do_settings_sections('elc_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="elc_batch_size"><?php echo esc_html__('Batch Size', 'external-link-checker'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="elc_batch_size" id="elc_batch_size" value="<?php echo esc_attr(get_option('elc_batch_size', 10)); ?>" min="1" max="100" class="small-text" />
                                <p class="description"><?php echo esc_html__('Number of links to check per batch.', 'external-link-checker'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="elc_timeout"><?php echo esc_html__('Timeout (seconds)', 'external-link-checker'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="elc_timeout" id="elc_timeout" value="<?php echo esc_attr(get_option('elc_timeout', 15)); ?>" min="5" max="60" class="small-text" />
                                <p class="description"><?php echo esc_html__('Timeout for each HTTP request.', 'external-link-checker'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php echo esc_html__('Excluded Domains', 'external-link-checker'); ?></label>
                            </th>
                            <td>
                                <textarea name="elc_excluded_domains_text" id="elc_excluded_domains_text" rows="5" cols="50" class="large-text" placeholder="youtube.com&#10;facebook.com&#10;instagram.com"><?php echo esc_textarea(implode("\n", get_option('elc_excluded_domains', array()))); ?></textarea>
                                <p class="description"><?php echo esc_html__('Enter one domain per line. These domains will not be checked.', 'external-link-checker'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('Save Settings', 'external-link-checker')); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Scan posts
     */
    public function ajax_scan_posts() {
        check_ajax_referer('elc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'external-link-checker')));
        }

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;

        $scanned = $this->scanner->scan_all_posts($limit);

        wp_send_json_success(array(
            'scanned' => $scanned,
            'message' => sprintf(
                /* translators: %d: number of posts scanned */
                _n('Scanned %d post', 'Scanned %d posts', $scanned, 'external-link-checker'),
                $scanned
            ),
        ));
    }

    /**
     * AJAX: Recheck a link
     */
    public function ajax_recheck_link() {
        check_ajax_referer('elc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'external-link-checker')));
        }

        $link_id = isset($_POST['link_id']) ? intval($_POST['link_id']) : 0;

        if (!$link_id) {
            wp_send_json_error(array('message' => __('Invalid link ID', 'external-link-checker')));
        }

        $result = $this->scanner->recheck_link($link_id);

        if (isset($result['error'])) {
            wp_send_json_error(array('message' => $result['error']));
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Delete a link
     */
    public function ajax_delete_link() {
        check_ajax_referer('elc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'external-link-checker')));
        }

        $link_id = isset($_POST['link_id']) ? intval($_POST['link_id']) : 0;

        if (!$link_id) {
            wp_send_json_error(array('message' => __('Invalid link ID', 'external-link-checker')));
        }

        $deleted = ELC_Database::delete_link($link_id);

        if ($deleted) {
            wp_send_json_success(array('message' => __('Link deleted', 'external-link-checker')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete link', 'external-link-checker')));
        }
    }

    /**
     * AJAX: Clear all results
     */
    public function ajax_clear_results() {
        check_ajax_referer('elc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'external-link-checker')));
        }

        $cleared = ELC_Database::clear_all_links();

        if ($cleared) {
            wp_send_json_success(array('message' => __('All results cleared', 'external-link-checker')));
        } else {
            wp_send_json_error(array('message' => __('Failed to clear results', 'external-link-checker')));
        }
    }

    /**
     * AJAX: Add excluded domain
     */
    public function ajax_add_excluded_domain() {
        check_ajax_referer('elc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'external-link-checker')));
        }

        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';

        if (empty($domain)) {
            wp_send_json_error(array('message' => __('Domain is required', 'external-link-checker')));
        }

        $added = $this->scanner->add_excluded_domain($domain);

        if ($added) {
            wp_send_json_success(array(
                'message'   => __('Domain added to exclusion list', 'external-link-checker'),
                'domains'   => $this->scanner->get_excluded_domains(),
            ));
        } else {
            wp_send_json_error(array('message' => __('Domain already exists or is invalid', 'external-link-checker')));
        }
    }

    /**
     * AJAX: Remove excluded domain
     */
    public function ajax_remove_excluded_domain() {
        check_ajax_referer('elc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'external-link-checker')));
        }

        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';

        if (empty($domain)) {
            wp_send_json_error(array('message' => __('Domain is required', 'external-link-checker')));
        }

        $removed = $this->scanner->remove_excluded_domain($domain);

        if ($removed) {
            wp_send_json_success(array(
                'message' => __('Domain removed from exclusion list', 'external-link-checker'),
                'domains' => $this->scanner->get_excluded_domains(),
            ));
        } else {
            wp_send_json_error(array('message' => __('Domain not found in exclusion list', 'external-link-checker')));
        }
    }

    /**
     * Load links via AJAX
     */
    public function ajax_load_links() {
        check_ajax_referer('elc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'external-link-checker')));
        }

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
        $limit  = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        $links = ELC_Database::get_links_by_status($status, $limit, $offset);

        wp_send_json_success(array('links' => $links));
    }
}
