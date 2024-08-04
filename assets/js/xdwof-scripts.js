document.addEventListener('DOMContentLoaded', function() {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const wholesaleMessage = document.getElementById('discount-message');
    const totalRetailElement = document.querySelector('.total-order-table td[data-type="retail"]');
    const totalWholesaleElement = document.querySelector('.total-order-table td[data-type="wholesale"]');
    const wholesaleSavingsElement = document.querySelector('.total-order-table td[data-type="savings"]');
    const updateCartButton = document.getElementById('add-all-to-cart');

    // Set initial values from data attributes
    let initialTotalRetail = parseFloat(totalRetailElement.getAttribute('data-initial')) || 0;
    let initialTotalWholesale = parseFloat(totalWholesaleElement.getAttribute('data-initial')) || 0;

    // Track initial quantities
    const initialQuantities = {};
    quantityInputs.forEach(input => {
        const productId = input.getAttribute('data-product-id');
        initialQuantities[productId] = parseInt(input.value) || 0;
    });

    async function fetchDiscountInfo(cartTotal) {
        const response = await fetch(xdwof_vars.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'xdwof_update_discount_info',
                security: xdwof_vars.nonce,
                cart_total: cartTotal
            })
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const data = await response.json();
        if (data.success) {
            return data.data;
        } else {
            throw new Error(data.data.message || 'Failed to fetch discount info');
        }
    }

    async function updateCartTotal() {
        let totalRetail = 0;
        let subtotal = 0;
        let totalWholesale = 0;

        quantityInputs.forEach(input => {
            const price = parseFloat(input.closest('tr').querySelector('.retail-price').getAttribute('data-price'));
            const quantity = parseInt(input.value) || 0; // Default to 0 if not a number
            if (!isNaN(price)) {
                subtotal += price * quantity;
                totalRetail += price * quantity;
            }
        });

        try {
            const discountInfo = await fetchDiscountInfo(subtotal);
            const currentDiscount = discountInfo.current_discount;
            let discountMessage = `You are currently at the ${currentDiscount}% discount level.`;
            
            if (discountInfo.next_discount_percentage > 0) {
                discountMessage += ` Add $${discountInfo.amount_to_next_level.toFixed(2)} to reach the next discount level at ${discountInfo.next_discount_percentage}%.`;
            } else {
                discountMessage = `You are at the highest discount level at ${currentDiscount}%.`;
            }
            
            wholesaleMessage.innerHTML = discountMessage;

            totalWholesale = 0; // Reset totalWholesale to initial value

            quantityInputs.forEach(input => {
                const price = parseFloat(input.closest('tr').querySelector('.retail-price').getAttribute('data-price'));
                const quantity = parseInt(input.value) || 0; // Default to 0 if not a number
                if (!isNaN(price)) {
                    const discountedPrice = parseFloat((price - (price * (currentDiscount / 100))).toFixed(2));
                    totalWholesale += discountedPrice * quantity;
                }
            });

            const wholesaleSavings = totalRetail - totalWholesale;

            totalRetailElement.textContent = '$' + totalRetail.toFixed(2);
            totalWholesaleElement.textContent = '$' + totalWholesale.toFixed(2);
            wholesaleSavingsElement.textContent = '$' + wholesaleSavings.toFixed(2);
        } catch (error) {
            console.error('Error updating cart total:', error);
        }
    }

    async function addAllToCart() {
        updateCartButton.textContent = "Processing...Please wait";
        updateCartButton.style.backgroundColor = "red";
        updateCartButton.disabled = true;

        const products = [];
        quantityInputs.forEach(input => {
            const productId = input.getAttribute('data-product-id');
            const newQuantity = parseInt(input.value) || 0; // Default to 0 if not a number
            const initialQuantity = initialQuantities[productId] || 0;

            if (newQuantity !== initialQuantity) {
                const quantityChange = newQuantity - initialQuantity;
                products.push({
                    product_id: productId,
                    quantity: newQuantity // Send the new quantity directly
                });
            }
        });

        const formData = new FormData();
        formData.append('action', 'xdwof_add_to_cart');
        formData.append('security', xdwof_vars.nonce);
        formData.append('products', JSON.stringify(products));

        try {
            const response = await fetch(xdwof_vars.ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            if (data.success) {
                window.location.href = data.data.cart_url;
            } else {
                alert(data.data.message);
                updateCartButton.textContent = "Update and go to Cart";
                updateCartButton.style.backgroundColor = "";
                updateCartButton.disabled = false;
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            alert('There was an error adding the products to the cart.');
            updateCartButton.textContent = "Update and go to Cart";
            updateCartButton.style.backgroundColor = "";
            updateCartButton.disabled = false;
        }
    }

    updateCartButton.addEventListener('click', addAllToCart);

    quantityInputs.forEach(input => {
        input.addEventListener('change', updateCartTotal);
    });

    updateCartTotal(); // Initialize total order amount on page load
});
