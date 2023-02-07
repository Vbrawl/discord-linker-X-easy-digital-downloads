

function cart_restore_page_reload() {
    page_id = this.getAttribute("page_id");

    if(page_id !== null) {
        fetch('/?p=' + page_id)
            .then((response) => {
                document.location.reload();
            });
    }
    else {
        alert("There was an unexpected error.");
    }

}



add_items_button = document.getElementById("dlxedd-add-items-button");
add_items_button.addEventListener('click', cart_restore_page_reload);