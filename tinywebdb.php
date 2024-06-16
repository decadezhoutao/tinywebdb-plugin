<?php
/*
Plugin Name: Configurable TinyWebDB Importer
Description: Allows importing data from a configurable TinyWebDB URL into WordPress posts.
Version: 1.0
Author: Your Name
*/

// 添加后台菜单项
add_action('admin_menu', 'tinywebdb_importer_menu');
function tinywebdb_importer_menu() {
    add_menu_page('TinyWebDB Importer', 'TinyWebDB Importer', 'manage_options', 'tinywebdb-importer', 'tinywebdb_importer_admin_page');
    add_options_page('TinyWebDB Settings', 'TinyWebDB Settings', 'manage_options', 'tinywebdb-settings', 'tinywebdb_importer_settings_page');
}

// 插件的后台页面
function tinywebdb_importer_admin_page() {
    echo '<h1>TinyWebDB Data Importer</h1>';

    if (isset($_POST['import_data'])) {
        $imported_data = fetch_and_import_data();
        echo '<p>Imported Data: ' . esc_html($imported_data['title']) . '</p>';
    } else {
        echo '<form method="post">';
        echo '<input type="submit" name="import_data" value="Import Data">';
        echo '</form>';
    }
}

// 设置页面
function tinywebdb_importer_settings_page() {
    ?>
    <div>
        <h2>TinyWebDB URL Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('tinywebdb_importer_settings');
            do_settings_sections('tinywebdb_importer_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// 注册设置
add_action('admin_init', 'tinywebdb_importer_register_settings');
function tinywebdb_importer_register_settings() {
    register_setting('tinywebdb_importer_settings', 'tinywebdb_url');
    add_settings_section('tinywebdb_main', 'Main Settings', null, 'tinywebdb_importer_settings');
    add_settings_field('tinywebdb_url_field', 'TinyWebDB URL', 'tinywebdb_url_field_callback', 'tinywebdb_importer_settings', 'tinywebdb_main');
}

function tinywebdb_url_field_callback() {
    $url = get_option('tinywebdb_url', '');
    echo "<input type='text' id='tinywebdb_url' name='tinywebdb_url' value='" . esc_attr($url) . "' />";
}

// 从TinyWebDB获取数据并导入到WordPress
function fetch_and_import_data() {
    $url = get_option('tinywebdb_url');
    $response = wp_remote_get($url ? $url : 'http://default-tinywebdb-url.com/getdata');
    if (is_wp_error($response)) {
        return 'Error fetching data: ' . $response->get_error_message();
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($data['title']) && isset($data['content'])) {
        $post_data = [
            'post_title' => $data['title'],
            'post_content' => $data['content'],
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ];
        wp_insert_post($post_data);
        return $data;
    }
    return 'No valid data found';
}
