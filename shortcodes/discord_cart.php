<?php





function dlxedd_discord_cart_init_js() {
    wp_register_script("discord_cart", "/wp-content/plugins/discord-linker-x-easy-digital-downloads/shortcodes/discord_cart.js", array(), false, true);
}





function dlxedd_cart_item_number() {
    $user = get_current_user_id();

    $items_number = 0;
    if($user != 0) {
        $cart = dlxedd_get_cart($user);
        $cart_items = count($cart);

        for($i = 0; $i < $cart_items; $i++) {
            $items_number += $cart[$i]["quantity"];
        }
    }
    return $items_number;
}


function dlxedd_cart_add_items_button() {
    wp_enqueue_script("discord_cart");

    $dlxedd_add_items_callback_id = get_option("dlxedd_add_items_callback_id", 0);
    return "<div id='dlxedd-add-items-button' class='edd-submit blue button' page_id='{$dlxedd_add_items_callback_id}'>Add Items From Discord</div>";
}




function dlxedd_add_items_from_discord() {
    $user = get_current_user_id();


    if($user != 0) {
        $items = dlxedd_get_cart($user);
        $items_number = count($items);

        for($i = 0; $i < $items_number; $i++) {
            $item = $items[$i];

            $item_id = $item["id"];
            $item_options = $item["options"];
            $item_options["quantity"] = $item["quantity"];

            unset($item);


            edd_add_to_cart($item_id, $item_options);
            dlxedd_update_cart($user, array());
        }
    }
}






add_action("init", "dlxedd_discord_cart_init_js");

add_shortcode("dlxedd_add_items_from_discord", 'dlxedd_add_items_from_discord');
add_shortcode("dlxedd_cart_add_items_button", 'dlxedd_cart_add_items_button');
add_shortcode("dlxedd_cart_item_number", "dlxedd_cart_item_number");






?>