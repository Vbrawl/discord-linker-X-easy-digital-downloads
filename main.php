<?php

/**
 * Plugin Name: Discord Linker X Easy Digital Downloads
 * 
 * Plugin URI: https://localhost/
 * 
 * Description: This plugin extends "Discord Linker" and allows it to integrate with Easy Digital Downloads
 * 
 * Version: 0.1.0
 * 
 * Author: Vbrawl
 */


define("DISCORD_LINKER", "discord_linker/discord_linker.php");
define("EASY_DIGITAL_DOWNLOADS", "easy-digital-downloads/easy-digital-downloads.php");


require_once("helpers.php");
require_once("shortcodes/initialize_shortcodes.php");




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


    // Get or Generate cart
    $cart = dlxedd_get_cart($IMPERSONATED_WP_ID);


    $product_in_cart = dlxedd_product_in_cart($cart, $product_id);
    if($product_in_cart === false) { // NOT FOUND
        // Create Product
        $new_product = array(
            "id" => intval($product_id),
            "options" => array(),
            "quantity" => 1
        );

        // Add created product to cart
        array_push($cart, $new_product);
    }
    else { // FOUND
        // Add 1 to the cart's product quantity
        $cart[$product_in_cart]["quantity"] += 1;
    }


    // Save changes to the database
    update_user_meta($IMPERSONATED_WP_ID, "dl_x_edd_saved_cart", $cart, false);


    // Reset everything and go back
    $account_link->reset_impersonation();
    return array('code' => "SUCCESS");
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


    // Get cart
    $cart = dlxedd_get_cart($IMPERSONATED_WP_ID);


    // Find item
    $product_in_cart = dlxedd_product_in_cart($cart, $product_id);
    if($product_in_cart !== false) {

        if($cart[$product_in_cart]["quantity"] > 1) {
            // Remove 1 from the quantity
            $cart[$product_in_cart]["quantity"] -= 1;
        }
        else {
            // Remove the item entirely
            unset($cart[$product_in_cart]);
        }
    }
    else {
        $account_link->reset_impersonation();
        return new WP_Error("PRODUCT_NOT_IN_CART", "Your cart doesn't contain a product with this ProductID", array("id" => $product_id));
    }


    // Save changes
    dlxedd_update_cart($IMPERSONATED_WP_ID, $cart);


    // Reset everything and go back
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
        // get product details
        $product_data = dlxedd_get_product($product_cart["id"]);

        // store the data to the list
        array_push($product_list, array(
            "id" => $product_cart["id"],
            "title" => $product_data["post_title"],
            "price" => $product_data["edd_price"],
            "product_link" => $product_data["guid"],
            "thumbnail_link" => dlxedd_get_thumbnail_link($product_data["_thumbnail_id"]),
            "upload_date_gmt" => $product_data["post_date_gmt"],
            "quantity" => $product_cart["quantity"]
        ));
    }

    // Reset everything and go back
    $account_link->reset_impersonation();
    return array("code" => "SUCCESS", "data" => $product_list);
}









function is_product_id($value) {
    $dot_position = strpos($value, '.');
    if(is_numeric($value) === false) {
        $id_type = $dot_position !== false ? "float" : "string";

        return new WP_Error("INCORRECT_PRODUCT_ID_TYPE", "Product ID must be integer", array("Type" => $id_type, "given id" => $value));
    }

    // check database
    global $wpdb;

    if(!dlxedd_product_exists($value)) {
        return new WP_Error("PRODUCT_NOT_FOUND", "Product ID Must Exist!");
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
                'validate_callback' => 'is_product_id'
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
                "validate_callback" => 'is_product_id'
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