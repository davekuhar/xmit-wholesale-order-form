<?php
/**
 * Displays the wholesale order form using the shortcode [xdwof_wholesale_order_form].
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_shortcode('xdwof_wholesale_order_form', 'xdwof_display_wholesale_order_form');
function xdwof_display_wholesale_order_form() {
    // Check if WooCommerce cart is initialized
    if (null === WC()->cart) {
        return '<p>Please add some products to your cart first.</p>';
    }

    // Get existing cart data
    $cart_contents = WC()->cart->get_cart();
    $existing_quantities = [];
    $total_retail = 0;
    $total_wholesale = 0;

    foreach ($cart_contents as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
        $quantity = $cart_item['quantity'];
        $price = $cart_item['data']->get_regular_price();
        $subtotal = $price * $quantity;
        $total_retail += $subtotal;
        $discounted_price = xdwof_calculate_discounted_price($price);
        $total_wholesale += $discounted_price * $quantity;

        $key = $variation_id ? $variation_id : $product_id;
        $existing_quantities[$key] = $quantity;
    }

    // Get wholesale price break points from ACF options page
    $break_points = get_field('wholesale_price_breaks', 'option');
    if (!is_array($break_points) || empty($break_points)) {
        return '<p>No wholesale discount breaks available.</p>';
    }

    $output = '<table class="xdwof-table"><thead><tr><th>Product</th><th>Variation Value</th><th>SKU</th><th>Retail Price</th>';
    foreach ($break_points as $break_point) {
        $output .= sprintf('<th>%s%% Discount <span class="header-small">Minimum Order: $%s</span></th>', esc_html($break_point['discount_percentage']), number_format($break_point['subtotal_min'], 2));
    }
    $output .= '<th>Quantity</th></tr></thead><tbody>';

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => 'bundles',
                'operator' => 'NOT IN'
            ),
        )
    );
    $products = get_posts($args);
    if (empty($products)) {
        return '<p>No products available.</p>';
    }

    foreach ($products as $product_post) {
        $product = wc_get_product($product_post->ID);
        $product_link = get_permalink($product->get_id());
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $variation_id = $variation['variation_id'];
                $variation_product = wc_get_product($variation_id);
                $retail_price = $variation_product->get_regular_price();
                $sku = $variation_product->get_sku();
                $variation_values = array_map(function($attribute) {
                    return is_array($attribute) ? $attribute['value'] : $attribute;
                }, $variation['attributes']);
                $variation_description = implode(', ', $variation_values);

                if ($retail_price !== '') {
                    $quantity = isset($existing_quantities[$variation_id]) ? $existing_quantities[$variation_id] : 0;
                    $output .= '<tr>';
                    $output .= sprintf('<td><a href="%s">%s</a></td>', esc_url($product_link), esc_html($product->get_name()));
                    $output .= sprintf('<td>%s</td>', esc_html($variation_description));
                    $output .= sprintf('<td>%s</td>', esc_html($sku));
                    $output .= sprintf('<td class="retail-price" data-price="%s">%s</td>', esc_attr($retail_price), wc_price($retail_price));
                    foreach ($break_points as $break_point) {
                        $discount_price = $retail_price - ($retail_price * ($break_point['discount_percentage'] / 100));
                        $output .= sprintf('<td>%s</td>', wc_price($discount_price));
                    }
                    $output .= sprintf('<td><input type="number" name="quantity" value="%d" min="0" step="1" class="quantity-input" data-product-id="%s" style="width: 80px;"></td>', esc_attr($quantity), esc_attr($variation_id));
                    $output .= '</tr>';
                }
            }
        } else {
            $retail_price = $product->get_regular_price();
            $sku = $product->get_sku();
            if ($retail_price !== '') {
                $quantity = isset($existing_quantities[$product->get_id()]) ? $existing_quantities[$product->get_id()] : 0;
                $output .= '<tr>';
                $output .= sprintf('<td><a href="%s">%s</a></td>', esc_url($product_link), esc_html($product->get_name()));
                $output .= '<td>N/A</td>';
                $output .= sprintf('<td>%s</td>', esc_html($sku));
                $output .= sprintf('<td class="retail-price" data-price="%s">%s</td>', esc_attr($retail_price), wc_price($retail_price));
                foreach ($break_points as $break_point) {
                    $discount_price = $retail_price - ($retail_price * ($break_point['discount_percentage'] / 100));
                    $output .= sprintf('<td>%s</td>', wc_price($discount_price));
                }
                $output .= sprintf('<td><input type="number" name="quantity" value="%d" min="0" step="1" class="quantity-input" data-product-id="%s" style="width: 80px;"></td>', esc_attr($quantity), esc_attr($product->get_id()));
                $output .= '</tr>';
            }
        }
    }

    $output .= '</tbody></table>';

    // Calculate current discount info
    $current_discount_percentage = 0;
    $next_discount_break = null;
    $subtotal = WC()->cart->get_subtotal();

    foreach ($break_points as $break_point) {
        if ($subtotal >= $break_point['subtotal_min']) {
            $current_discount_percentage = max($current_discount_percentage, $break_point['discount_percentage']);
        }

        if ($next_discount_break === null && $subtotal < $break_point['subtotal_min']) {
            $next_discount_break = $break_point;
        }
    }

    $amount_to_add = $next_discount_break ? $next_discount_break['subtotal_min'] - $subtotal : 0;
    $next_discount_percentage = $next_discount_break ? $next_discount_break['discount_percentage'] : 0;

    $wholesale_savings = $total_retail - $total_wholesale;
    
    $discount_message = sprintf('You are currently at the %d%% discount level.', $current_discount_percentage);
    if ($next_discount_break) {
        $discount_message .= sprintf(' Add $%s to reach the next discount level at %d%%.', number_format($amount_to_add, 2), $next_discount_percentage);
    } else {
        $discount_message = sprintf('You are at the highest discount level at %d%%.', $current_discount_percentage);
    }

    $output .= '<div id="discount-message">' . $discount_message . '</div>';
    $output .= '<table class="total-order-table">
        <tr><td>Total (retail)</td><td data-type="retail" data-initial="' . esc_attr($total_retail) . '">' . wc_price($total_retail) . '</td></tr>
        <tr><td>Wholesale savings</td><td data-type="savings">' . wc_price($wholesale_savings) . '</td></tr>
        <tr><td>Total (wholesale)</td><td data-type="wholesale" data-initial="' . esc_attr($total_wholesale) . '">' . wc_price($total_wholesale) . '</td></tr>
    </table>';
    $output .= '<button id="add-all-to-cart" data-nonce="' . wp_create_nonce('xdwof_nonce') . '">Update and go to Cart</button>';

    return $output;
}
?>
