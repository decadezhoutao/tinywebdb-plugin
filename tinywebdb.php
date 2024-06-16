<?php
/*
Plugin Name: TinyWebDB
Description: Allows importing data from TinyWebDB data files into WordPress posts.
Version: 1.0
Author: Tao Zhou
*/

// 创建设置菜单和页面
add_action('admin_menu', 'tinywebdb_importer_setup_menu');
function tinywebdb_importer_setup_menu(){
    add_menu_page('TinyWebDB Data Importer', 'TinyWebDB Importer', 'manage_options', 'tinywebdb_importer', 'tinywebdb_importer_init');
}

// 插件的初始化页面
function tinywebdb_importer_init(){
    echo '<h1>TinyWebDB Data Files</h1>';
    tinywebdb_list_data_files();
}

// 列出 _data 目录中的所有文件
function tinywebdb_list_data_files() {
    $data_path = '/path/to/tinywebdb/_data';  // 这里需要修改为你的 TinyWebDB _data 目录的实际路径
    if (!file_exists($data_path)) {
        echo "<p>Error: The data directory does not exist.</p>";
        return;
    }

    $files = scandir($data_path);
    echo '<ul>';
    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            echo '<li><a href="' . esc_url(add_query_arg('import_file', urlencode($file))) . '">' . esc_html($file) . '</a></li>';
        }
    }
    echo '</ul>';

    // 处理导入请求
    if (isset($_GET['import_file'])) {
        tinywebdb_import_data_file($data_path . '/' . sanitize_text_field($_GET['import_file']));
    }
}

// 导入指定文件的数据
function tinywebdb_import_data_file($file_path) {
    if (!file_exists($file_path)) {
        echo '<div class="error"><p>Error: The file does not exist.</p></div>';
        return;
    }

    $data = file_get_contents($file_path);
    $items = json_decode($data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo '<div class="error"><p>JSON decoding error: ' . json_last_error_msg() . '</p></div>';
        return;
    }

    $imported_count = 0;
    foreach ($items as $item) {
        if (!isset($item['title']) || !isset($item['content'])) {
            continue;
        }
        $post_data = array(
            'post_title'    => wp_strip_all_tags($item['title']),
            'post_content'  => $item['content'],
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
            'post_type'     => 'post'
        );
        if (wp_insert_post($post_data)) {
            $imported_count++;
        }
    }
    echo '<div class="updated"><p>Imported ' . $imported_count . ' posts successfully from ' . basename($file_path) . '.</p></div>';
}

