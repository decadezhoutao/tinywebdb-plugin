<?php
/*
Plugin Name: Selective Import from TinyWebDB
Description: Allows selective importing of data from TinyWebDB into WordPress posts.
Version: 1.0
Author: Your Name
*/

// 添加一个新的菜单项
add_action('admin_menu', 'tinywebdb_importer_add_menu');
function tinywebdb_importer_add_menu() {
    add_menu_page('TinyWebDB Importer', 'TinyWebDB Importer', 'manage_options', 'tinywebdb-importer', 'tinywebdb_importer_page');
}

// 插件的主界面
function tinywebdb_importer_page() {
    ?>
    <h1>TinyWebDB Data Importer</h1>
    <form method="post">
        <input type="submit" name="load_data" value="Load Data from TinyWebDB">
    </form>
    <?php
    if (isset($_POST['load_data'])) {
        $data = fetch_data_from_tinywebdb(); // 获取数据
        if ($data) {
            echo '<form method="post">';
            echo '<select name="selected_data">';
            foreach ($data as $index => $item) {
                echo '<option value="' . $index . '">' . esc_html($item['title']) . '</option>';
            }
            echo '</select>';
            echo '<input type="submit" name="import_selected" value="Import Selected">';
            echo '</form>';
        }
    }

    if (isset($_POST['import_selected']) && !empty($_POST['selected_data'])) {
        $data = fetch_data_from_tinywebdb(); // 重新获取数据
        $selected_index = sanitize_text_field($_POST['selected_data']);
        $selected_item = $data[$selected_index];
        import_data_to_wordpress($selected_item);
        echo '<p>Imported: ' . esc_html($selected_item['title']) . '</p>';
    }
}

// 从TinyWebDB获取数据
function fetch_data_from_tinywebdb() {
    $url = 'http://tinywebdb.ditu.site/getvalue'; // 更改为实际的获取数据的URL
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        echo '<p>Error fetching data: ' . $response->get_error_message() . '</p>';
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true); // 假设返回的是JSON格式数据
}

// 将选定的数据导入到WordPress
function import_data_to_wordpress($item) {
    $post_data = array(
        'post_title'    => sanitize_text_field($item['title']),
        'post_content'  => sanitize_textarea_field($item['content']),
        'post_status'   => 'publish',
        'post_author'   => get_current_user_id(),
        'post_type'     => 'post'
    );
    wp_insert_post($post_data);
}
