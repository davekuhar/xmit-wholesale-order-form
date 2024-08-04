<?php
/**
 * Applies the varying percentage discount based on cart subtotal for administrator and wholesale_customer roles.
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Apply varying percentage discount based on cart subtotal range for "administrator" and "wholesale_customer" roles
add_action('woocommerce_cart_calculate_fees', 'xdwof_apply_varying_discount_for_admin_and_wholesale_customer');
function xdwof_apply_varying_discount_for_admin_and_wholesale_customer() {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Check if the current user has the "administrator" or "wholesale_customer" role
    $current_user = wp_get_current_user();
    if (!in_array('administrator', (array)$current_user->roles, true) && !in_array('wholesale_customer', (array)$current_user->roles, true)) {
        return;
    }

    // Get wholesale price break points from ACF options page
    $break_points = get_field('wholesale_price_breaks', 'option');
    if (!is_array($break_points) || empty($break_points)) {
        return; // No break points set
    }

    $subtotal = WC()->cart->get_subtotal();
    $current_discount_percentage = 0;
    $next_discount_percentage = 0;
    $next_discount_break = null;
    $current_discount_amount = 0;
    $amount_to_add = 0;

    // Find the highest applicable discount tier and the next discount tier
    foreach ($break_points as $break_point) {
        $subtotal_min = $break_point['subtotal_min'];
        $subtotal_max = $break_point['subtotal_max'];

        if ($subtotal >= $subtotal_min) {
            $current_discount_percentage = max($current_discount_percentage, $break_point['discount_percentage']);
        }

        if ($next_discount_break === null && $subtotal < $subtotal_min) {
            $next_discount_percentage = $break_point['discount_percentage'];
            $next_discount_break = $subtotal_min;
        }
    }

    $current_discount_amount = $subtotal * ($current_discount_percentage / 100);

    // Apply the current discount
    if ($current_discount_percentage > 0) {
        WC()->cart->add_fee(
            sprintf(__('Discount (%s%%)', 'woocommerce'), $current_discount_percentage),
            -$current_discount_amount
        );
    }

    // Calculate the amount needed to reach the next discount break
    if ($next_discount_break !== null) {
        $amount_to_add = $next_discount_break - $subtotal;
    }

    // Display the notice
    $user_display_name = $current_user->display_name;
    if ($amount_to_add > 0 && $next_discount_percentage > 0) {
        $notice = sprintf(
            __('Hello, %s!<br>You\'re currently saving %s with your <span class="wholesale-percentage-discount">%s%%</span> wholesale discount.<br>Add %s to your cart to boost that to <span class="wholesale-percentage-discount">%s%%</span>!', 'woocommerce'),
            esc_html($user_display_name),
            wc_price($current_discount_amount),
            esc_html($current_discount_percentage),
            wc_price($amount_to_add),
            esc_html($next_discount_percentage)
        );
    } else {
        $notice = sprintf(
            __('Hello, %s!<br>You\'re at the highest discount rate, <span class="wholesale-percentage-discount">%s%%</span>!', 'woocommerce'),
            esc_html($user_display_name),
            esc_html($current_discount_percentage)
        );
    }

    WC()->session->set('next_discount_break_notice', wp_kses_post($notice));
}

// Display the notice about the next discount break in the cart totals section
add_action('woocommerce_cart_totals_before_order_total', 'xdwof_display_next_discount_break_notice');
function xdwof_display_next_discount_break_notice() {
    if ($notice = WC()->session->get('next_discount_break_notice')) {
        echo '<tr class="order-discount-break"><th>' . esc_html__('Next Discount Break', 'woocommerce') . '</th><td>' . wp_kses_post($notice) . '</td></tr>';
        echo '<tr><td colspan="2"><a href="/wholesale-product-grid/">Go back to wholesale order form &rarr;</a></td></tr>';
        // Clear the session variable after displaying the notice
        WC()->session->set('next_discount_break_notice', '');
    }
}
?>
