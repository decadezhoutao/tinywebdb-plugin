<?php
/*
Plugin Name: WordPress TinyWebDB
Description: A TinyWebDB implementation for WordPress
Version: 1.0
Author: Tao zhou
*/

// 激活插件时创建数据表
function wp_tinywebdb_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tinywebdb';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tag varchar(255) NOT NULL,
        value longtext NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY tag (tag)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'wp_tinywebdb_activate');

// 处理 API 请求
function wp_tinywebdb_api_handler() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tinywebdb';

    if (isset($_GET['action']) && $_GET['action'] === 'getvalue') {
        $tag = isset($_GET['tag']) ? sanitize_text_field($_GET['tag']) : '';
        if (!empty($tag)) {
            $value = $wpdb->get_var($wpdb->prepare("SELECT value FROM $table_name WHERE tag = %s", $tag));
            header('Content-Type: application/json');
            echo json_encode(array("VALUE", $tag, $value));
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'storeavalue') {
        $tag = isset($_POST['tag']) ? sanitize_text_field($_POST['tag']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
        if (!empty($tag)) {
            if (empty($value)) {
                $wpdb->delete($table_name, ['tag' => $tag]);
                echo "REMOVED";
            } else {
                $wpdb->replace($table_name, ['tag' => $tag, 'value' => $value]);
                echo "STORED";
            }
            exit;
        }
    }
}
add_action('init', 'wp_tinywebdb_api_handler');

// 添加管理页面
function wp_tinywebdb_menu() {
    add_menu_page('TinyWebDB', 'TinyWebDB', 'manage_options', 'wp-tinywebdb', 'wp_tinywebdb_admin_page');
}
add_action('admin_menu', 'wp_tinywebdb_menu');

// 管理页面内容
function wp_tinywebdb_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tinywebdb';

    echo '<div class="wrap">';
    echo '<h1>TinyWebDB Data</h1>';

    // 显示数据表
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC");
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Tag</th><th>Value</th><th>Updated At</th></tr></thead>';
    echo '<tbody>';
    foreach ($results as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row->tag) . '</td>';
        echo '<td>' . esc_html($row->value) . '</td>';
        echo '<td>' . esc_html($row->updated_at) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '</div>';
}

// 添加短代码以在前端显示表单
function wp_tinywebdb_shortcode() {
    ob_start();
    ?>
    <h2>App Inventor (TinyWebDB) Web Database Service</h2>
    <h3>Search database for a tag</h3>
    <form action="<?php echo esc_url(home_url('/')); ?>" method="get">
        <input type="hidden" name="action" value="getvalue">
        <p>Tag: <input type="text" name="tag" /></p>
        <input type="submit" value="Get value">
    </form>

    <h3>Store a tag-value pair in the database</h3>
    <form action="<?php echo esc_url(home_url('/')); ?>" method="post">
        <input type="hidden" name="action" value="storeavalue">
        <p>Tag: <input type="text" name="tag" /></p>
        <p>Value: <input type="text" name="value" /></p>
        <input type="submit" value="Store a value">
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('tinywebdb_form', 'wp_tinywebdb_shortcode');
