<?php







function dlxedd_error_INCORRECT_PRODUCT_ID_TYPE() {
    return new WP_Error("INCORRECT_PRODUCT_ID_TYPE", "Product ID must be integer!");
}


function dlxedd_error_PRODUCT_NOT_FOUND() {
    return new WP_Error("PRODUCT_NOT_FOUND", "There are no products with this ID!");
}


function dlxedd_error_PRODUCT_NOT_IN_CART($product_id) {
    return new WP_Error("PRODUCT_NOT_IN_CART", "Your cart doesn't contain a product with this ID!", array("id" => $product_id));
}

function dlxedd_error_INCORRECT_DATE_TYPE() {
    return new WP_Error("INCORRECT_DATE_TYPE", "A Date must be an epoch value!");
}