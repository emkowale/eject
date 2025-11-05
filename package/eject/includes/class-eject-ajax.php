<?php
/*
 * File: includes/class-eject-ajax.php
 * Description: AJAX router + guard.
 */
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'ajax/class-eject-ajax-scan.php';
require_once plugin_dir_path(__FILE__) . 'ajax/class-eject-ajax-run.php';
require_once plugin_dir_path(__FILE__) . 'ajax/class-eject-ajax-settings.php';

class Eject_Ajax {
    public static function register_endpoints() {
        $map = [
            // Queue
            'eject_scan_orders'       => ['Eject_Ajax_Scan', 'scan_orders'],
            'eject_dismiss_item'      => ['Eject_Ajax_Scan', 'dismiss_item'],
            'eject_dismiss_bulk'      => ['Eject_Ajax_Scan', 'dismiss_bulk'],

            // Runs / POs
            'eject_add_to_run'        => ['Eject_Ajax_Run', 'add_to_run'],
            'eject_mark_ordered'      => ['Eject_Ajax_Run', 'mark_ordered'],
            'eject_mark_not_ordered'  => ['Eject_Ajax_Run', 'mark_not_ordered'],
            'eject_remove_line'       => ['Eject_Ajax_Run', 'remove_line'],
            'eject_add_exception'     => ['Eject_Ajax_Run', 'add_exception'],
            'eject_reopen_po'         => ['Eject_Ajax_Run', 'reopen_po'],
            'eject_delete_or_revert_po' => ['Eject_Ajax_Run', 'delete_or_revert_po'],

            // Settings / utilities
            'eject_save_settings'     => ['Eject_Ajax_Settings', 'save_settings'],
            'eject_clear_runs'        => ['Eject_Ajax_Settings', 'clear_runs'],
            'eject_clear_exceptions'  => ['Eject_Ajax_Settings', 'clear_exceptions'],
            'eject_export_pos'        => ['Eject_Ajax_Settings', 'export_pos'],
            'eject_unsuppress_queue'  => ['Eject_Ajax_Settings', 'unsuppress_queue'],
            'eject_repair_flags'      => ['Eject_Ajax_Settings', 'repair_flags'], // NEW
        ];
        foreach ($map as $action => $cb) add_action("wp_ajax_{$action}", $cb);
    }

    public static function guard($cap = 'manage_woocommerce', $nonce_action = 'eject_admin') {
        if (!current_user_can($cap)) wp_send_json_error(['message' => 'Permission denied'], 403);
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, $nonce_action)) wp_send_json_error(['message' => 'Bad nonce'], 400);
    }

    public static function get_blacklist(): array {
        $opts = get_option('eject_options', []);
        $raw  = isset($opts['blacklist']) ? $opts['blacklist'] : '';
        $list = array_filter(array_map('trim', explode(',', $raw)));
        return array_map('strtolower', $list);
    }
}
