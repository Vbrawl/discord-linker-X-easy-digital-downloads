<?php





function dlxedd_product_in_cart($cart, $product_id) {
    $cart_length = count($cart);

    foreach($cart as $i => $var) {
        if($var["id"] == $product_id) {
            return $i;
        }
    }
    return false;
}


function dlxedd_product_exists($product_id) {
    global $wpdb;

    $exists = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'download' AND ID = %d;", $product_id));

    return ($exists !== null);
}



function dlxedd_get_cart($user_id) {
    $cart = get_user_meta($user_id, "dl_x_edd_saved_cart", true);
    if(! $cart) {
        $cart = array();
    }

    return $cart;
}


function dlxedd_update_cart($user_id, $new_cart) {
    if(empty($new_cart)) {
        delete_user_meta($user_id, "dl_x_edd_saved_cart");
    }
    else {
        update_user_meta($user_id, "dl_x_edd_saved_cart", $new_cart, false);
    }
}


function dlxedd_get_product($product_id) {
    $product = get_post($product_id, ARRAY_A);

    if($product !== null) {
        $product["edd_price"] = floatval(get_post_meta($product_id, "edd_price", true));
        $product["edd_download_files"] = get_post_meta($product_id, "edd_download_files", true);

        $product["_edd_bundled_products"] = get_post_meta($product_id, "_edd_bundled_products", true);
        $product["_edd_download_earnings"] = get_post_meta($product_id, "_edd_download_earnings", true);
        $product["_edd_download_sales"] = get_post_meta($product_id, "_edd_download_sales", true);
        $product["_edd_download_gross_sales"] = get_post_meta($product_id, "_edd_download_gross_sales", true);
        $product["_edd_download_gross_earnings"] = get_post_meta($product_id, "_edd_download_gross_earnings", true);
        $product["_thumbnail_id"] = get_post_meta($product_id, "_thumbnail_id", true);
    }


    return $product;
}


function dlxedd_get_thumbnail_link($thumbnail_id) {
    $thumbnail = get_post($thumbnail_id, ARRAY_A);

    if($thumbnail !== null) {
        return $thumbnail["guid"];
    }
}


function dlxedd_get_product_categories($product_id) {
    global $wpdb;

    $product_categories = $wpdb->get_results(
        $wpdb->prepare("SELECT {$wpdb->prefix}terms.name FROM {$wpdb->prefix}terms JOIN {$wpdb->prefix}term_relationships ON term_taxonomy_id = term_id WHERE object_id = %d;", $product_id),
        ARRAY_A
    );

    $categories = array();
    foreach($product_categories as $category) {
        array_push($categories, $category["name"]);
    }

    return $categories;
}




function  dlxedd_get_filtered_product($product_id, $quantity = null, $old_quantity = null) {
    $product_data = dlxedd_get_product($product_id);

    return array(
        "id" => $product_id,
        "title" => $product_data["post_title"],
        "categories" => dlxedd_get_product_categories($product_data["ID"]),
        "price" => $product_data["edd_price"],
        "product_link" => $product_data["guid"],
        "thumbnail" => dlxedd_get_thumbnail_link($product_data["_thumbnail_id"]),
        "upload_date_gmt" => $product_data["post_date_gmt"],
        "quantity" => $quantity,
        "old_quantity" => $old_quantity
    );
}