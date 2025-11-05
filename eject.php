<?php
/*
 * Plugin Name: Eject
 * Description: Vendor ordering and purchase-order management for WooCommerce.
 * Author: Eric Kowalewski
 * Version: 1.0.0
 * Last Updated: 2025-11-04 21:05 EDT
 * Plugin URI: https://github.com/emkowale/eject
 */
if (!defined('ABSPATH')) exit;

define('EJECT_DIR', plugin_dir_path(__FILE__));
define('EJECT_URL', plugin_dir_url(__FILE__));
define('EJECT_VER', '1.0.0');


require_once EJECT_DIR . 'includes/class-eject-cpt.php';

/* --- Activation / Deactivation --- */
register_activation_hook(__FILE__, function () {
    Eject_CPT::register();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () { flush_rewrite_rules(); });

/* --- Bootstrap once WooCommerce is present --- */
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>Eject</strong> requires WooCommerce to be active.</p></div>';
        });
        deactivate_plugins(plugin_basename(__FILE__));
        return;
    }

    // Centralized includes
    require_once EJECT_DIR . 'includes/class-eject-admin.php';
    require_once EJECT_DIR . 'includes/data/class-eject-data.php';
    require_once EJECT_DIR . 'includes/class-eject-ajax.php';
    require_once EJECT_DIR . 'includes/eject-hooks.php';

    // Wire CPT + menus
    add_action('init',       ['Eject_CPT',   'register']);
    add_action('admin_menu', ['Eject_Admin', 'register_menu']);

    // Register all AJAX endpoints
    add_action('init', ['Eject_Ajax', 'register_endpoints']);
});

/* --- Admin assets (only on Eject pages) --- */
add_action('admin_enqueue_scripts', function ($hook) {
    $is_eject_screen =
        (strpos($hook, 'toplevel_page_eject') === 0) ||
        (strpos($hook, 'eject_page_eject-') === 0);

    if (!$is_eject_screen) {
        $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
        if (strpos($page, 'eject') === 0) $is_eject_screen = true;
    }

    if (!$is_eject_screen) return;

    wp_enqueue_style ('eject-admin', EJECT_URL . 'assets/css/admin.css', [], '1.0.0');
    wp_enqueue_script('eject-admin', EJECT_URL . 'assets/js/admin.js',   ['jquery'], '1.0.0', true);

    // Nonce for JS
    wp_localize_script('eject-admin', 'EJECT_QUEUE', [
        'nonce' => wp_create_nonce('eject_admin'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});