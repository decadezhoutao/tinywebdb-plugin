<?php
/*
Plugin Name: TinyWebDB
Description: Imports data from TinyWebDB into WordPress posts.
Version: 1.0
Author: Tao Zhou
*/

// 创建设置菜单
add_action('admin_menu', 'tinywebdb_importer_setup_menu');

function tinywebdb_importer_setup_menu(){
    add_menu_page('TinyWebDB Importer', 'TinyWebDB Importer', 'manage_options', 'tinywebdb_importer', 'tinywebdb_importer_init');
}

// 设置页面的 HTML
function tinywebdb_importer_init(){
    ?>
    <h1>TinyWebDB Importer Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('tinywebdb_importer_options');
        do_settings_sections('tinywebdb_importer');
        submit_button();
        ?>
    </form>
    <?php
}

// 注册和设置字段
add_action('admin_init', 'tinywebdb_importer_settings');

function tinywebdb_importer_settings(){
    register_setting('tinywebdb_importer_options', 'tinywebdb_importer_options', 'tinywebdb_importer_options_validate');
    add_settings_section('tinywebdb_importer_main', 'Main Settings', 'tinywebdb_importer_section_text', 'tinywebdb_importer');
    add_settings_field('tinywebdb_url', 'TinyWebDB URL', 'tinywebdb_importer_url', 'tinywebdb_importer', 'tinywebdb_importer_main');
}

function tinywebdb_importer_section_text(){
    echo '<p>Enter your TinyWebDB URL here.</p>';
}

function tinywebdb_importer_url(){
    $options = get_option('tinywebdb_importer_options');
    echo "<input id='tinywebdb_url' name='tinywebdb_importer_options[url]' size='40' type='text' value='". esc_attr($options['url']) ."' />";
}

function tinywebdb_importer_options_validate($input) {
    $newinput['url'] = trim($input['url']);
    if(!preg_match('/^http(s)?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?$/', $newinput['url'])) {
        add_settings_error('tinywebdb_url', 'invalid-url', 'Invalid URL');
    }
    return $newinput;
}

// 导入数据到文章的按钮和函数
add_action('admin_notices', 'tinywebdb_importer_notices');

function tinywebdb_importer_notices(){
    $screen = get_current_screen();
    if ($screen->id !== "toplevel_page_tinywebdb_importer") {
        return;
    }

    echo '<form method="post"><input type="submit" name="import_now" value="Import Now" class="button button-primary"></form>';

    if (isset($_POST['import_now'])) {
        tinywebdb_import_posts();
    }
}

function tinywebdb_import_posts() {
    $options = get_option('tinywebdb_importer_options');
    $response = wp_remote_get($options['url'] . '/getvalue');
    if (is_wp_error($response)) {
        echo '<div class="error"><p>Error fetching data: ' . $response->get_error_message() . '</p></div>';
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($data)) {
        echo '<div class="error"><p>Failed to import posts. Check the TinyWebDB URL or response format.</p></div>';
        return;
    }

    foreach ($data as $item) {
        if (!isset($item['title']) || !isset($item['content'])) {
            continue; // Skip items missing title or content
        }
        $post_data = array(
            'post_title'    => wp_strip_all_tags($item['title']),
            'post_content'  => $item['content'],
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
            'post_type'     => 'post'
        );
        wp_insert_post($post_data);
    }
    echo '<div class="updated"><p>Imported posts successfully.</p></div>';
}
?>
