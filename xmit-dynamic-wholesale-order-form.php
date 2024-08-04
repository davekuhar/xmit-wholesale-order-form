<?php
/**
 * Plugin Name: Xmit Dynamic Wholesale Order Form
 * Plugin URI: https://transmitstudio.com
 * Description: A dynamic wholesale order form for WooCommerce. Shortcodes: [xdwof_wholesale_order_form] - Displays the wholesale order form, [xdwof_wholesale_discount_table] - Displays the wholesale discount table.
 * Version: 1.5
 * Author: Dave Kuhar
 * Author URI: https://davekuhar.com
 * Text Domain: xdwof
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include necessary files
include_once plugin_dir_path(__FILE__) . 'includes/xdwof-wholesale-form-shortcode.php';
include_once plugin_dir_path(__FILE__) . 'includes/xdwof-ajax.php';
include_once plugin_dir_path(__FILE__) . 'includes/xdwof-discount.php';
include_once plugin_dir_path(__FILE__) . 'includes/xdwof-wholesale-table-shortcode.php';

// Enqueue necessary scripts and styles
add_action('wp_enqueue_scripts', 'xdwof_enqueue_scripts');
function xdwof_enqueue_scripts() {
    wp_enqueue_style('xdwof-styles', plugin_dir_url(__FILE__) . 'assets/css/xdwof-styles.css');
    wp_enqueue_script('xdwof-scripts', plugin_dir_url(__FILE__) . 'assets/js/xdwof-scripts.js', array('jquery'), '1.5', true);

    wp_localize_script('xdwof-scripts', 'xdwof_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('xdwof_nonce'),
    ));
}

// Function to calculate the discounted price
function xdwof_calculate_discounted_price($price) {
    $break_points = get_field('wholesale_price_breaks', 'option');
    if (!is_array($break_points) || empty($break_points)) {
        return $price; // No break points set
    }

    $subtotal = WC()->cart->get_subtotal();
    $current_discount_percentage = 0;

    foreach ($break_points as $break_point) {
        $subtotal_min = $break_point['subtotal_min'];
        if ($subtotal >= $subtotal_min) {
            $current_discount_percentage = max($current_discount_percentage, $break_point['discount_percentage']);
        }
    }

    return $price - ($price * ($current_discount_percentage / 100));
}
?>
