/**
 * External Link Checker - Admin JavaScript
 *
 * @package External_Link_Checker
 */

(function($) {
    'use strict';

    var ELC_Admin = {

        /**
         * Current filter status
         */
        currentStatus: 'all',

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.loadLinks();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Scan button
            $('#elc-scan-btn').on('click', $.proxy(this.scanPosts, this));

            // Clear results button
            $('#elc-clear-btn').on('click', $.proxy(this.clearResults, this));

            // Filter tabs
            $('.elc-filter').on('click', $.proxy(this.changeFilter, this));

            // Recheck link
            $(document).on('click', '.elc-recheck', $.proxy(this.recheckLink, this));

            // Delete link
            $(document).on('click', '.elc-delete', $.proxy(this.deleteLink, this));

            // Edit post
            $(document).on('click', '.elc-edit-post', $.proxy(this.editPost, this));
        },

        /**
         * Scan all posts
         */
        scanPosts: function(e) {
            e.preventDefault();

            var $btn = $('#elc-scan-btn');
            var $status = $('#elc-scan-status');

            if ($btn.hasClass('disabled')) {
                return;
            }

            $btn.addClass('disabled').prop('disabled', true);
            $status.html('<span class="elc-spinner"></span>' + elcAjax.strings.scanning);

            $.ajax({
                url: elcAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elc_scan_posts',
                    nonce: elcAjax.nonce,
                    limit: 50
                },
                success: $.proxy(function(response) {
                    if (response.success) {
                        $status.html('<span class="elc-notice elc-notice-success">' + response.data.message + '</span>');
                        this.loadLinks();
                    } else {
                        $status.html('<span class="elc-notice elc-notice-error">' + (response.data.message || elcAjax.strings.error) + '</span>');
                    }
                }, this),
                error: function() {
                    $status.html('<span class="elc-notice elc-notice-error">' + elcAjax.strings.error + '</span>');
                },
                complete: function() {
                    $btn.removeClass('disabled').prop('disabled', false);
                }
            });
        },

        /**
         * Clear all scan results
         */
        clearResults: function(e) {
            e.preventDefault();

            if (!confirm(elcAjax.strings.confirmClear)) {
                return;
            }

            var $btn = $('#elc-clear-btn');

            $.ajax({
                url: elcAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elc_clear_results',
                    nonce: elcAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || elcAjax.strings.error);
                    }
                },
                error: function() {
                    alert(elcAjax.strings.error);
                }
            });
        },

        /**
         * Change filter status
         */
        changeFilter: function(e) {
            e.preventDefault();

            var $link = $(e.currentTarget);

            $('.elc-filter').removeClass('active');
            $link.addClass('active');

            this.currentStatus = $link.data('status');
            this.loadLinks();
        },

        /**
         * Load links table
         */
        loadLinks: function() {
            var $tbody = $('#elc-links-body');

            $.ajax({
                url: elcAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elc_load_links',
                    nonce: elcAjax.nonce,
                    status: this.currentStatus,
                    limit: 50,
                    offset: 0
                },
                success: $.proxy(function(response) {
                    if (response.success) {
                        this.renderLinks(response.data.links);
                    } else {
                        $tbody.html('<tr><td colspan="7">' + (response.data.message || elcAjax.strings.error) + '</td></tr>');
                    }
                }, this),
                error: function() {
                    $tbody.html('<tr><td colspan="7">' + elcAjax.strings.error + '</td></tr>');
                }
            });
        },

        /**
         * Render links in table
         */
        renderLinks: function(links) {
            var $tbody = $('#elc-links-body');
            var html = '';

            if (!links || links.length === 0) {
                $tbody.html('<tr><td colspan="7">No links found.</td></tr>');
                return;
            }

            $.each(links, $.proxy(function(index, link) {
                var statusClass = 'elc-status-' + link.status;
                var lastChecked = link.last_checked ? this.formatDate(link.last_checked) : 'Never';
                var httpCode = link.http_code > 0 ? link.http_code : '-';
                var postTitle = link.post_title || 'Untitled';
                var editUrl = elcAjax.postEditUrl.replace('%d', link.post_id);

                html += '<tr>';
                html += '<td>';
                html += '<strong><a href="' + editUrl + '" target="_blank">' + this.escapeHtml(postTitle) + '</a></strong><br>';
                html += '<small>' + this.escapeHtml(link.post_type) + '</small>';
                html += '</td>';
                html += '<td><a href="' + this.escapeHtml(link.external_url) + '" target="_blank" rel="noopener noreferrer">' + this.escapeHtml(link.external_url) + '</a></td>';
                html += '<td>' + this.escapeHtml(link.anchor_text || '-') + '</td>';
                html += '<td><span class="elc-status ' + statusClass + '">' + this.escapeHtml(link.status) + '</span></td>';
                html += '<td>' + httpCode + '</td>';
                html += '<td>' + lastChecked + '</td>';
                html += '<td class="elc-actions">';
                html += '<button type="button" class="button elc-recheck" data-link-id="' + link.id + '">Check Again</button>';
                html += '<a href="' + editUrl + '" class="button elc-edit-post" target="_blank">Edit Post</a>';
                html += '<button type="button" class="button elc-delete" data-link-id="' + link.id + '">Delete</button>';
                html += '</td>';
                html += '</tr>';
            }, this));

            $tbody.html(html);
        },

        /**
         * Recheck a single link
         */
        recheckLink: function(e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var linkId = $btn.data('link-id');
            var originalText = $btn.text();

            $btn.prop('disabled', true).text('Checking...');

            $.ajax({
                url: elcAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elc_recheck_link',
                    nonce: elcAjax.nonce,
                    link_id: linkId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || elcAjax.strings.error);
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert(elcAjax.strings.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Delete a link
         */
        deleteLink: function(e) {
            e.preventDefault();

            var $btn = $(e.currentTarget);
            var linkId = $btn.data('link-id');

            if (!confirm(elcAjax.strings.confirmDelete)) {
                return;
            }

            $.ajax({
                url: elcAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elc_delete_link',
                    nonce: elcAjax.nonce,
                    link_id: linkId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || elcAjax.strings.error);
                    }
                },
                error: function() {
                    alert(elcAjax.strings.error);
                }
            });
        },

        /**
         * Edit post (placeholder - handled by default link behavior)
         */
        editPost: function(e) {
            // Default link behavior handles this
        },

        /**
         * Format date string
         */
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },

        /**
         * Escape HTML entities
         */
        escapeHtml: function(text) {
            if (!text) {
                return '';
            }
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        ELC_Admin.init();
    });

})(jQuery);
