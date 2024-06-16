<?php
/*
Plugin Name: TinyWebDB Integration
Description: Allows managing TinyWebDB entries from WordPress admin.
Version: 1.0
Author: Your Name
*/

// Add a new menu item in the WordPress admin
add_action('admin_menu', 'tinywebdb_admin_menu');

function tinywebdb_admin_menu() {
    add_menu_page('TinyWebDB Integration', 'TinyWebDB', 'manage_options', 'tinywebdb', 'tinywebdb_admin_page');
}

// Admin page content
function tinywebdb_admin_page() {
    ?>
    <h1>TinyWebDB Integration</h1>
    <h2>Store a Value</h2>
    <form method="post">
        Tag: <input type="text" name="tag" required>
        Value: <input type="text" name="value" required>
        <input type="submit" name="store_value" value="Store Value">
    </form>

    <h2>Retrieve a Value</h2>
    <form method="post">
        Tag: <input type="text" name="retrieve_tag" required>
        <input type="submit" name="retrieve_value" value="Retrieve Value">
    </form>

    <?php
    // Handle post request for storing values
    if (isset($_POST['store_value'])) {
        $tag = sanitize_text_field($_POST['tag']);
        $value = sanitize_text_field($_POST['value']);
        $store_response = tinywebdb_store_value($tag, $value);
        echo '<p>' . esc_html($store_response) . '</p>';
    }

    // Handle post request for retrieving values
    if (isset($_POST['retrieve_value'])) {
        $tag = sanitize_text_field($_POST['retrieve_tag']);
        $retrieve_response = tinywebdb_get_value($tag);
        echo '<p>Value for ' . esc_html($tag) . ': ' . esc_html($retrieve_response) . '</p>';
    }
}

// Function to store a value in TinyWebDB
function tinywebdb_store_value($tag, $value) {
    $url = 'http://tinywebdb.ditu.site/storeavalue'; // Change this URL to your TinyWebDB store URL
    $response = wp_remote_post($url, [
        'body' => [
            'tag' => $tag,
            'value' => $value
        ]
    ]);

    if (is_wp_error($response)) {
        return 'Failed to store value: ' . $response->get_error_message();
    } else {
        return 'Value stored successfully!';
    }
}

// Function to retrieve a value from TinyWebDB
function tinywebdb_get_value($tag) {
    $url = 'http://tinywebdb.ditu.site/getvalue'; // Change this URL to your TinyWebDB retrieve URL
    $response = wp_remote_post($url, [
        'body' => ['tag' => $tag]
    ]);

    if (is_wp_error($response)) {
        return 'Failed to retrieve value: ' . $response->get_error_message();
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data['value'] ?? 'No value found for this tag';
    }
}
