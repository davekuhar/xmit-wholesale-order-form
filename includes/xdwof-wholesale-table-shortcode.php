<?php
/**
 * Displays the wholesale discount table using the shortcode [xdwof_wholesale_discount_table].
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Shortcode to display the wholesale discount table
add_shortcode('xdwof_wholesale_discount_table', 'display_wholesale_discount_table');

function display_wholesale_discount_table() {
    // Get wholesale price break points from ACF options page
    $break_points = get_field('wholesale_price_breaks', 'option');

    // Ensure $break_points is an array
    if (!is_array($break_points) || empty($break_points)) {
        return '<p>No wholesale discount breaks available.</p>';
    }

    // Start the table
    $output = '<table class="wholesale-discount-table">';
    $output .= '<thead><tr><th>Tier</th><th>Discount Percentage</th></tr></thead><tbody>';

    // Loop through break points to create table rows
    foreach ($break_points as $index => $break_point) {
        $subtotal_min = number_format($break_point['subtotal_min'], 2);
        $discount_percentage = $break_point['discount_percentage'];

        // Check if this is the last break point
        $is_last_break_point = !isset($break_points[$index + 1]);

        // Create table row
        if ($is_last_break_point) {
            $output .= sprintf(
                '<tr><td>$%s and up</td><td>%s%%</td></tr>',
                esc_html($subtotal_min),
                esc_html($discount_percentage)
            );
        } else {
            $subtotal_max = number_format($break_points[$index + 1]['subtotal_min'] - 0.01, 2);
            $output .= sprintf(
                '<tr><td>$%s - $%s</td><td>%s%%</td></tr>',
                esc_html($subtotal_min),
                esc_html($subtotal_max),
                esc_html($discount_percentage)
            );
        }
    }

    // Close the table
    $output .= '</tbody></table>';

    return $output;
}
?>
