<?php
/*
 * File: includes/class-eject-cpt.php
 * Description: Registers the Eject custom post type for vendor runs/POs + publish hook to add one simple order note per vendor per order.
 * Plugin: Eject
 * Author: Eric Kowalewski
 * Last Updated: 2025-11-04 EDT
 */
if (!defined('ABSPATH')) exit;

class Eject_CPT {
    public static function register() {
        $labels = [
            'name'               => 'Vendor Runs',
            'singular_name'      => 'Vendor Run',
            'menu_name'          => 'Eject POs',
            'name_admin_bar'     => 'Vendor Run',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Run',
            'new_item'           => 'New Run',
            'edit_item'          => 'Edit Run',
            'view_item'          => 'View Run',
            'all_items'          => 'All Runs',
            'search_items'       => 'Search Runs',
            'not_found'          => 'No runs found.',
            'not_found_in_trash' => 'No runs found in Trash.',
        ];

        register_post_type('eject_run', [
            'labels'             => $labels,
            'public'             => false,
            'exclude_from_search'=> true,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // custom menu elsewhere
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => ['title'],
        ]);

        // When a run transitions to publish, add ONE simple note per vendor per order.
        add_action('transition_post_status', [__CLASS__, 'on_transition_status'], 10, 3);
    }

    /**
     * On first publish of eject_run:
     * Adds: "{vendorName} PO#: {poNumber} created"
     * One note per vendor per order (no line items).
     */
    public static function on_transition_status($new_status, $old_status, $post) {
        if ($post->post_type !== 'eject_run') return;
        if ($old_status === 'publish' || $new_status !== 'publish') return;

        $po_id   = (int) $post->ID;
        $vendor  = (string) get_post_meta($po_id, '_vendor_name', true);
        $po_no   = (string) get_post_meta($po_id, '_po_number', true);
        $po_date = (string) get_post_meta($po_id, '_po_date', true);

        if ($po_no === '') {
            if (!class_exists('Eject_Data')) return;
            $po_no = Eject_Data::next_po_number($vendor ?: 'Vendor');
            update_post_meta($po_id, '_po_number', $po_no);
        }
        if ($po_date === '') {
            $po_date = date_i18n('Y-m-d');
            update_post_meta($po_id, '_po_date', $po_date);
        }

        // Prevent double-write if toggled publish again.
        if (get_post_meta($po_id, '_eject_notes_written', true)) return;

        // Read run items map to determine impacted orders.
        $raw   = get_post_meta($po_id, '_items', true);
        $items = $raw ? json_decode($raw, true) : [];
        if (!is_array($items)) $items = [];

        // Collect distinct order IDs referenced by this run.
        $order_ids = [];
        foreach ($items as $rec) {
            if (!empty($rec['order_ids']) && is_array($rec['order_ids'])) {
                foreach ($rec['order_ids'] as $oid) {
                    $oid = (int) $oid;
                    if ($oid > 0) $order_ids[$oid] = true;
                }
            }
        }
        $order_ids = array_keys($order_ids);

        $vendor_label = ($vendor !== '' ? $vendor : 'Vendor');
        $vendor_key   = sanitize_key($vendor_label);
        $guard_suffix = sanitize_key($po_no !== '' ? $po_no : ('po_' . $po_id));

        // Final message (NO line items).
        $msg = sprintf('%s PO#: %s created', $vendor_label, ($po_no !== '' ? $po_no : ('#' . $po_id)));

        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;

            // Idempotent per vendor+PO per order.
            $guard_key = "_eject_vendor_po_note_{$vendor_key}_{$guard_suffix}";
            if ($order->get_meta($guard_key)) continue;

            $order->add_order_note($msg, false, true); // public, no email
            $order->update_meta_data($guard_key, 'yes');
            $order->save();
        }

        update_post_meta($po_id, '_eject_notes_written', 'yes');
    }
}
