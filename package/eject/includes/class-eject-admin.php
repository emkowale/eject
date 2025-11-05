<?php
/*
 * File: includes/class-eject-admin.php
 * Description: Registers the Eject admin menu and loads screen views + single order-level notes on PO creation.
 * Plugin: Eject
 * Author: Eric Kowalewski
 * Last Updated: 2025-11-04 EDT
 */

if (!defined('ABSPATH')) exit;

class Eject_Admin {

    /** Remove WP/admin/plugin notices on Eject screens only */
    public static function suppress_admin_notices() {
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('network_admin_notices');
        remove_all_actions('user_admin_notices');
        add_filter('screen_options_show_screen', '__return_false');
        add_filter('admin_body_class', function ($classes) { return $classes . ' eject-admin-stable'; });
    }

    /** Register top-level menu and subpages */
    public static function register_menu() {
        $cap  = 'manage_woocommerce';
        $hooks = [];

        // Top-level page (defaults to Runs)
        $hooks[] = add_menu_page(
            'Eject',
            'Eject',
            $cap,
            'eject',
            [self::class, 'render_runs'],
            'dashicons-clipboard',
            56
        );

        // Kill WP’s auto-duplicate submenu entry
        add_action('admin_head', function () { remove_submenu_page('eject', 'eject'); });

        // Explicit submenus
        $hooks[] = add_submenu_page('eject', 'Queue',    'Queue',    $cap, 'eject-queue',    [self::class, 'render_queue']);
        $hooks[] = add_submenu_page('eject', 'Runs',     'Runs',     $cap, 'eject-runs',     [self::class, 'render_runs']);
        $hooks[] = add_submenu_page('eject', 'POs',      'POs',      $cap, 'eject-pos',      [self::class, 'render_pos']);
        $hooks[] = add_submenu_page('eject', 'Settings', 'Settings', 'administrator', 'eject-settings', [self::class, 'render_settings']);

        foreach ($hooks as $hook) {
            if ($hook) add_action("load-{$hook}", [self::class, 'suppress_admin_notices']);
        }

        // === Notes wiring ===
        // 1) When a PO is created, log ONE order-level note per vendor per order.
        add_action('eject/po_created', [self::class, 'on_po_created'], 10, 5);

        // 2) Backstop: suppress old per-item Eject notes if they still get called elsewhere.
        add_filter('woocommerce_new_order_note_data', [self::class, 'suppress_legacy_per_item_notes'], 10, 2);
    }

    /** Load view helpers (strict path to /includes/views/) */
    private static function load_view($view) {
        $path = trailingslashit(dirname(__DIR__)) . "includes/views/view-{$view}.php";
        if (file_exists($path)) { include $path; return; }
        $alt = plugin_dir_path(__FILE__) . "views/view-{$view}.php";
        if (file_exists($alt)) { include $alt; return; }
        echo "<div class='wrap'><h2>Missing view: {$view}</h2><p>Expected at:</p><code>{$path}</code><br><code>{$alt}</code></div>";
    }

    /** Page renders */
    public static function render_queue()    { self::load_view('queue'); }
    public static function render_runs()     { self::load_view('runs'); }
    public static function render_pos()      { self::load_view('pos'); }
    public static function render_settings() { self::load_view('settings'); }

    /* ---------------------------------------------------------------------
     * ONE note per vendor per order, when a PO is created
     * ---------------------------------------------------------------------
     *
     * Fire this from your PO creation flow (right after you create the PO):
     *   do_action('eject/po_created', $order_id, $po_id, $vendor_name_or_id, $assigned_item_ids, $run_id);
     *
     * Idempotency: a meta key _eject_vendor_note_{vendorKey} prevents duplicates
     * within the same order, regardless of repeated PO creations for that vendor.
     */

    /**
     * Action handler: write a single order-level note when PO is created.
     *
     * @param int|WC_Order $order_or_id      Order or ID
     * @param int          $po_id            PO ID/number
     * @param string|int   $vendor           Vendor name or ID
     * @param array        $line_items       Item IDs or WC_Order_Item objects for this vendor
     * @param int|null     $run_id           Optional run ID
     */
    public static function on_po_created($order_or_id, int $po_id, $vendor, array $line_items = [], ?int $run_id = null): void {
        $order = self::normalize_order($order_or_id);
        if (!$order || $po_id <= 0) return;

        $vendor_label = is_numeric($vendor) ? (string)$vendor : (string)$vendor;
        $vendor_key   = sanitize_key( $vendor_label !== '' ? $vendor_label : 'vendor' );

        // Per-vendor-per-order guard (prevents duplicates)
        $meta_key = "_eject_vendor_note_{$vendor_key}";
        if ($order->get_meta($meta_key)) {
            return;
        }

        $summary   = self::summarize_items_for_note($order, $line_items, 12);
        $run_suffix = $run_id ? sprintf(' via Run #%d', (int)$run_id) : '';

        $message = sprintf(
            'Eject: PO #%d created for %s%s. Items: %s',
            $po_id,
            ($vendor_label !== '' ? $vendor_label : 'Vendor'),
            $run_suffix,
            ($summary !== '' ? $summary : '—')
        );

        // Public note, do not email
        $order->add_order_note($message, false, true);
        $order->update_meta_data($meta_key, (string)$po_id);
        $order->save();
    }

    /** Defensive: block legacy per-item notes that match our old phrasing */
    public static function suppress_legacy_per_item_notes($data, $order) {
        // If another part of the plugin still tries to write "Eject: ... line item ..." notes, drop them.
        if (!empty($data['content']) && preg_match('/^Eject:\s.*line item/i', $data['content'])) {
            // Return an empty message to cancel; Woo will ignore empty notes.
            $data['content'] = '';
        }
        return $data;
    }

    /** Resolve order from ID or instance */
    private static function normalize_order($order_or_id): ?WC_Order {
        if ($order_or_id instanceof WC_Order) return $order_or_id;
        $id = absint($order_or_id);
        if ($id <= 0) return null;
        $order = wc_get_order($id);
        return ($order instanceof WC_Order) ? $order : null;
    }

    /**
     * Build compact items summary like: "2× Tee – Charcoal/L; 1× Hoodie – Black/XL"
     *
     * @param WC_Order $order
     * @param array    $line_items
     * @param int      $limit
     * @return string
     */
    private static function summarize_items_for_note(WC_Order $order, array $line_items, int $limit = 12): string {
        $parts = [];
        foreach ($line_items as $li) {
            $item = ($li instanceof WC_Order_Item) ? $li : $order->get_item($li);
            if (!$item || !method_exists($item, 'get_quantity')) continue;

            $qty  = max(1, (int)$item->get_quantity());
            $name = trim((string)$item->get_name());

            // Pull a few useful attrs (Color, Size, Print Location) if present
            $attrs = [];
            if (method_exists($item, 'get_formatted_meta_data')) {
                $meta = $item->get_formatted_meta_data('_', true);
                foreach ($meta as $m) {
                    $label = trim(wp_strip_all_tags($m->display_key));
                    $value = trim(wp_strip_all_tags($m->display_value));
                    if ($label === '' || $value === '') continue;
                    if (preg_match('/^(color|size|print[\s_-]?location)$/i', $label)) {
                        $attrs[] = $value;
                    }
                }
            }

            $label = $attrs ? ($name . ' – ' . implode('/', $attrs)) : $name;
            $parts[] = sprintf('%d× %s', $qty, $label);
        }

        if (empty($parts)) return '';
        $out = implode('; ', array_slice($parts, 0, $limit));
        if (count($parts) > $limit) {
            $out .= sprintf(' … (+%d more)', count($parts) - $limit);
        }
        return $out;
    }
}
