<?php
/*
 * Plugin Name: Eject
 * Version: 2.0.3
 * Description: Build vendor purchase orders from WooCommerce on-hold orders.
 * Author: Eric Kowalewski
 * Plugin URI: https://github.com/emkowale/eject
 */

if (!defined('ABSPATH')) exit;


define('PLUGIN_VERSION', '2.0.3');
define('EJECT_VERSION','1.0.5');
define('EJECT_DIR', plugin_dir_path(__FILE__));
define('EJECT_URL', plugin_dir_url(__FILE__));

require_once EJECT_DIR . 'includes/class-eject-cpt.php';
require_once EJECT_DIR . 'includes/class-eject-service.php';
require_once EJECT_DIR . 'includes/class-eject-admin.php';
require_once EJECT_DIR . 'includes/class-eject-ajax.php';
require_once EJECT_DIR . 'includes/class-eject-workorders.php';

// Register custom order status "On Order".
add_action('init', function () {
    register_post_status('wc-on-order', [
        'label'                     => _x('On Order', 'Order status', 'eject'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('On Order <span class="count">(%s)</span>', 'On Order <span class="count">(%s)</span>', 'eject'),
    ]);
});
add_filter('wc_order_statuses', function ($statuses) {
    $new = [];
    foreach ($statuses as $key => $label) {
        $new[$key] = $label;
        if ($key === 'wc-on-hold') {
            $new['wc-on-order'] = _x('On Order', 'Order status', 'eject');
        }
    }
    return $new;
});

register_activation_hook(__FILE__, function () {
    Eject_CPT::register();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>Eject</strong> requires WooCommerce to be active.</p></div>';
        });
        return;
    }

    add_action('init', ['Eject_CPT', 'register']);
    add_action('admin_menu', ['Eject_Admin', 'register_menu']);
    add_action('init', ['Eject_Ajax', 'register']);
    Eject_Workorders::register();
});

add_action('admin_enqueue_scripts', function ($hook) {
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    $is_eject_screen = ($page === 'eject') || strpos((string)$hook, 'eject') !== false;

    if (!$is_eject_screen) return;

    wp_enqueue_style('eject-admin', EJECT_URL . 'assets/css/admin.css', [], EJECT_VERSION);
    wp_enqueue_script('eject-admin', EJECT_URL . 'assets/js/admin.js', ['jquery'], EJECT_VERSION, true);

    wp_localize_script('eject-admin', 'EJECT_ADMIN', [
        'nonce'    => wp_create_nonce('eject_admin'),
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
});
