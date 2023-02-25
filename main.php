<?php

/**
 * Plugin Name: Discord Linker X Easy Digital Downloads
 * 
 * Plugin URI: https://localhost/
 * 
 * Description: This plugin extends "Discord Linker" and allows it to integrate with Easy Digital Downloads
 * 
 * Version: 0.1.2
 * 
 * Author: Vbrawl
 */


define("DISCORD_LINKER", "discord_linker/discord_linker.php");
define("EASY_DIGITAL_DOWNLOADS", "easy-digital-downloads/easy-digital-downloads.php");


require_once("helpers.php");
require_once("shortcodes/initialize_shortcodes.php");
require_once("errors.php");




function dlxedd_setup() {
    global $wpdb;


    $post_exists = true;


    $callback_id = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'dlxedd_add_items_callback_id'");
    if($callback_id === null) {
        $post_exists = false;
    }


    if($post_exists) {
        $post_id = get_post($callback_id);
        if($post_id === null) {
            $post_exists = false;
        }
    }



    if(!$post_exists) {
        $mypost = array(
            "post_title" => "Add Items From Discord",
            "post_content" => "<!-- wp:shortcode -->[dlxedd_add_items_from_discord]<!-- /wp:shortcode -->",
            "post_type" => "dlxedd_code_caller",
            "post_status" => "publish"
        );


        $id = wp_insert_post($mypost, /*wp_error*/false, /*fire_after_hooks*/false);



        $wpdb->query(
            $wpdb->prepare("INSERT INTO {$wpdb->prefix}options (option_name, option_value, autoload) VALUES ('dlxedd_add_items_callback_id', %d, 'yes')", $id)
        );
    }

}



function dlxedd_custom_type_setup() {
    register_post_type("dlxedd_code_caller", array('public' => true));
}









function dlxedd_check_dependencies() {
    if (is_plugin_active(DISCORD_LINKER) && is_plugin_active(EASY_DIGITAL_DOWNLOADS)) {

    }
    else {
        echo "<div class='notice notice-error'><b>Discord Linker X Easy Digital Downloads:</b> For this plugin to be usable you need to install and activate both <b>\"Discord Linker\"</b> and <b>\"Easy Digital Downloads\"</b> plugins.</div>";
    }

}



function dlxedd_add_to_cart($request) {
    global $IMPERSONATED_WP_ID;

    $discord_id = $request->get_param('discord_id');
    $product_id = $request->get_param('product_id');


    $account_link = new dlAccountLink(null, $discord_id);
    $error = $account_link->impersonate();
    if(is_wp_error($error)) {
        return $error;
    }

    // Prepare quantity holders
    $old_quantity = 0;
    $new_quantity = 0;


    // Get or Generate cart
    $cart = dlxedd_get_cart($IMPERSONATED_WP_ID);


    $product_in_cart = dlxedd_product_in_cart($cart, $product_id);
    if($product_in_cart === false) { // NOT FOUND
        $old_quantity = 0;
        // Create Product
        $new_product = array(
            "id" => intval($product_id),
            "options" => array(),
            "quantity" => 1
        );

        // Add created product to cart
        array_push($cart, $new_product);
        $new_quantity = 1;
    }
    else { // FOUND
        $old_quantity = $cart[$product_in_cart]["quantity"];
        // Add 1 to the cart's product quantity
        $cart[$product_in_cart]["quantity"] += 1;
        $new_quantity = $cart[$product_in_cart]["quantity"];
    }


    // Save changes to the database
    update_user_meta($IMPERSONATED_WP_ID, "dl_x_edd_saved_cart", $cart, false);


    // Reset everything and go back
    $account_link->reset_impersonation();
    return array('code' => "SUCCESS", "data" => dlxedd_get_filtered_product($product_id, $new_quantity, $old_quantity));
}



function dlxedd_remove_from_cart($request) {
    global $IMPERSONATED_WP_ID;
    $discord_id = $request->get_param("discord_id");
    $product_id = $request->get_param("product_id");

    $account_link = new dlAccountLink(null, $discord_id);
    $error = $account_link->impersonate();
    if(is_wp_error($error)) {
        return $error;
    }


    // Prepare quantity holders
    $old_quantity = 0;
    $new_quantity = 0;


    // Get cart
    $cart = dlxedd_get_cart($IMPERSONATED_WP_ID);


    // Find item
    $product_in_cart = dlxedd_product_in_cart($cart, $product_id);
    if($product_in_cart !== false) {
        $old_quantity = $cart[$product_in_cart]["quantity"];

        if($cart[$product_in_cart]["quantity"] > 1) {
            // Remove 1 from the quantity
            $cart[$product_in_cart]["quantity"] -= 1;
            $new_quantity = $cart[$product_in_cart]["quantity"];
        }
        else {
            // Remove the item entirely
            unset($cart[$product_in_cart]);
            $new_quantity = 0;
        }
    }
    else {
        $account_link->reset_impersonation();
        return dlxedd_error_PRODUCT_NOT_IN_CART($product_id);
    }


    // Save changes
    dlxedd_update_cart($IMPERSONATED_WP_ID, $cart);


    // Reset everything and go back
    $account_link->reset_impersonation();
    return array('code' => "SUCCESS", 'data' => dlxedd_get_filtered_product($product_id, $new_quantity, $old_quantity));
}




function dlxedd_clear_cart_contents($request) {
    global $IMPERSONATED_WP_ID;
    $discord_id = $request->get_param("discord_id");

    $account_link = new dlAccountLink(null, $discord_id);
    $error = $account_link->impersonate();
    if(is_wp_error($error)) {
        return $error;
    }

    dlxedd_update_cart($IMPERSONATED_WP_ID, array());

    $account_link->reset_impersonation();
    return array('code' => "SUCCESS");
}







function dlxedd_get_cart_contents($request) {
    global $IMPERSONATED_WP_ID;
    $discord_id = $request->get_param("discord_id");

    $account_link = new dlAccountLink(null, $discord_id);
    $error = $account_link->impersonate();
    if(is_wp_error($error)) {
        return $error;
    }

    // Prepare to parse the whole cart
    $cart = dlxedd_get_cart($IMPERSONATED_WP_ID);
    $product_list = array();


    // for each product store the following: ID, Title, Price, LinkToPost, LinkToThumbnail, GMT Upload Time
    foreach($cart as $product_cart) {
        // store the data to the list
        array_push($product_list, dlxedd_get_filtered_product($product_cart["id"], $product_cart["quantity"]));
    }

    // Reset everything and go back
    $account_link->reset_impersonation();
    return array("code" => "SUCCESS", "data" => $product_list);
}




function dlxedd_get_products($request) {
    global $wpdb;

    $epoch = $request->get_param("epoch");



    if($epoch !== 0) {
        $products = $wpdb->get_results(
            $wpdb->prepare("SELECT id FROM {$wpdb->prefix}posts WHERE post_type = 'download' AND post_date_gmt > FROM_UNIXTIME(%d) ORDER BY post_date_gmt;", $epoch),
            ARRAY_A
        );
    }
    else {
        $products = $wpdb->get_results(
            $wpdb->prepare("SELECT id FROM {$wpdb->prefix}posts WHERE post_type = 'download';"),
            ARRAY_A
        );
    }

    $product_details = array();

    foreach($products as $prod) {
        // Store the data to the list
        array_push($product_details, dlxedd_get_filtered_product($prod["id"]));
    }

    return array("code" => "SUCCESS", "data" => $product_details);
}





function dlxedd_is_epoch($value) {
    $dot_position = strpos($value, '.');
    if(is_numeric($value) === false || $dot_position !== false) {
        return dlxedd_error_INCORRECT_DATE_TYPE();
    }

    return true;
}




function dlxedd_is_product_id($value) {
    $dot_position = strpos($value, '.');
    if(is_numeric($value) === false || $dot_position !== false) {
        return dlxedd_error_INCORRECT_PRODUCT_ID_TYPE();
    }

    // check database
    global $wpdb;

    if(!dlxedd_product_exists($value)) {
        return dlxedd_error_PRODUCT_NOT_FOUND();
    }

    return true;
}





function dlxedd_rest_api_init() {

    register_rest_route("dlxedd/v1/cart", "/add/(?P<discord_id>.*)/(?P<product_id>.*)", array(
        "methods" => "GET",
        "callback" => "dlxedd_add_to_cart",
        "args" => array(
            "discord_id" => array(
                'validate_callback' => 'dl_is_discord_id'
            ),
            'product_id' => array(
                'validate_callback' => 'dlxedd_is_product_id'
            )
        )
    ));




    register_rest_route("dlxedd/v1/cart", "/remove/(?P<discord_id>.*)/(?P<product_id>.*)", array(
        "methods" => "GET",
        "callback" => "dlxedd_remove_from_cart",
        "args" => array(
            "discord_id" => array(
                "validate_callback" => 'dl_is_discord_id'
            ),
            'product_id' => array(
                "validate_callback" => 'dlxedd_is_product_id'
            )
        )
    ));



    register_rest_route("dlxedd/v1/cart", "/list/(?P<discord_id>.*)", array(
        "methods" => "GET",
        "callback" => "dlxedd_get_cart_contents",
        "args" => array(
            "discord_id" => array(
                "validate_callback" => 'dl_is_discord_id'
            ),
        )
    ));




    register_rest_route("dlxedd/v1/cart", "/clear/(?P<discord_id>.*)", array(
        "methods" => "GET",
        "callback" => "dlxedd_clear_cart_contents",
        "args" => array(
            "discord_id" => array(
                "validate_callback" => 'dl_is_discord_id'
            )
        )
    ));


    register_rest_route("dlxedd/v1/products", "/get_products/(?P<epoch>.*)", array(
        "methods" => "GET",
        "callback" => "dlxedd_get_products",
        "args" => array(
            "epoch" => array(
                "validate_callback" => 'dlxedd_is_epoch'
            )
        )
    ));



}







register_activation_hook(
    __FILE__,
    'dlxedd_setup'
);

// register_deactivation_hook(
//     __FILE__,
//     'dl_x_edd_unset'
// );


add_action("init", "dlxedd_custom_type_setup");
add_action("admin_notices", "dlxedd_check_dependencies");
// add_action("wp_loaded", "dlxedd_get_cart_contents");
add_action("rest_api_init", "dlxedd_rest_api_init");


?>