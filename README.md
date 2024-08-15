# xmit-wholesale-order-form

Custom WordPress plugin for Nutra Health Products.

* Displays products from all but the "bundles" category in a table
* User can add/remove items to their cart by incrementing a number field
* Total amount in cart is calculated dynamically
* Displays the current price break and the amount to add to the cart to reach the next break point
* Shortcode provides a way to display the discount breaks table
* Adds function to WooCommerce cart to update discount breaks and total wholesale cost as the user makes changes to the cart
* Changes to the cart can be carried over to the table order form and back again
* User role check prevents anyone not a `wholesale_customer` from seeing or using the price breaks
