<?php
/**
 * Handles AJAX requests for updating discount information and adding products to the cart.
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('wp_ajax_xdwof_update_discount_info', 'xdwof_update_discount_info');
add_action('wp_ajax_nopriv_xdwof_update_discount_info', 'xdwof_update_discount_info');

function xdwof_update_discount_info() {
    check_ajax_referer('xdwof_nonce', 'security');

    $cart_total = floatval($_POST['cart_total']);
    $break_points = get_field('wholesale_price_breaks', 'option');

    if (!is_array($break_points) || empty($break_points)) {
        wp_send_json_error(array('message' => 'No discount breaks available.'));
    }

    $current_discount = 0;
    $next_discount_percentage = 0;
    $amount_to_next_level = 0;

    foreach ($break_points as $break_point) {
        if ($cart_total >= $break_point['subtotal_min']) {
            $current_discount = max($current_discount, $break_point['discount_percentage']);
        }

        if ($next_discount_percentage === 0 && $cart_total < $break_point['subtotal_min']) {
            $next_discount_percentage = $break_point['discount_percentage'];
            $amount_to_next_level = $break_point['subtotal_min'] - $cart_total;
        }
    }

    $response = array(
        'current_discount' => $current_discount,
        'next_discount_percentage' => $next_discount_percentage,
        'amount_to_next_level' => $amount_to_next_level,
        'message' => sprintf('You are currently at a %d%% discount level.', $current_discount),
    );

    if ($next_discount_percentage > 0) {
        $response['message'] .= sprintf(' Add $%.2f to reach the next discount level at %d%%.', $amount_to_next_level, $next_discount_percentage);
    } else {
        $response['message'] = sprintf('You are at the highest discount level at %d%%.', $current_discount);
    }

    wp_send_json_success($response);
}

add_action('wp_ajax_xdwof_add_to_cart', 'xdwof_add_to_cart');
add_action('wp_ajax_nopriv_xdwof_add_to_cart', 'xdwof_add_to_cart');

function xdwof_add_to_cart() {
    check_ajax_referer('xdwof_nonce', 'security');

    $products = json_decode(stripslashes($_POST['products']), true);

    if (empty($products) || !is_array($products)) {
        wp_send_json_error(array('message' => 'Invalid products data.'));
    }

    foreach ($products as $product) {
        $product_id = intval($product['product_id']);
        $quantity = intval($product['quantity']);

        // Remove existing product from cart if any, then add with new quantity
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] === $product_id) {
                WC()->cart->remove_cart_item($cart_item_key);
            }
        }

        if ($quantity > 0) {
            $result = WC()->cart->add_to_cart($product_id, $quantity);
            if (!$result) {
                wp_send_json_error(array('message' => 'Failed to add product to cart.'));
            }
        }
    }

    wp_send_json_success(array('cart_url' => wc_get_cart_url()));
}
?>
