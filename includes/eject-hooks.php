<?php
/*
 * File: includes/eject-hooks.php
 * Description: Hooks into WooCommerce order lifecycle for Eject.
 * Plugin: Eject
 * Author: Eric Kowalewski
 * Last Updated: 2025-11-03 EDT
 */
if (!defined('ABSPATH')) exit;

/**
 * Placeholder: detect vendor items when order becomes processing.
 * (Do not mark _eject_processed here; that happens when a run is Ordered.)
 */
add_action('woocommerce_order_status_processing', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    // Optional: could enqueue a background flag if needed later.
}, 20, 1);

/** Future: live recompute hook (edits while Not Ordered) */
add_action('woocommerce_before_order_object_save', function ($order) {
    if (!$order instanceof WC_Order) return;
    if ($order->get_status() !== 'processing') return;
    // TODO: detect qty/attr edits and update active runs (Option A)
});
