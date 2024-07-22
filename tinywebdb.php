<?php
/*
Plugin Name: WordPress TinyWebDB
Description: A TinyWebDB implementation for WordPress
Version: 1.0.0
Author: Tao Zhou
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

    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'wp_tinywebdb_getvalue') {
        $tag = isset($_REQUEST['tag']) ? sanitize_text_field($_REQUEST['tag']) : '';
        if (!empty($tag)) {
            $value = $wpdb->get_var($wpdb->prepare("SELECT value FROM $table_name WHERE tag = %s", $tag));
            wp_send_json(array("VALUE", $tag, $value));
        }
    } elseif (isset($_REQUEST['action']) && $_REQUEST['action'] === 'wp_tinywebdb_storeavalue') {
        $tag = isset($_REQUEST['tag']) ? sanitize_text_field($_REQUEST['tag']) : '';
        $value = isset($_REQUEST['value']) ? sanitize_text_field($_REQUEST['value']) : '';
        if (!empty($tag)) {
            if (empty($value)) {
                $result = $wpdb->delete($table_name, ['tag' => $tag]);
                wp_send_json($result !== false ? "REMOVED" : "ERROR: " . $wpdb->last_error);
            } else {
                $result = $wpdb->replace($table_name, ['tag' => $tag, 'value' => $value]);
                wp_send_json($result !== false ? "STORED" : "ERROR: " . $wpdb->last_error);
            }
        }
    }
    wp_die();
}
add_action('wp_ajax_wp_tinywebdb_getvalue', 'wp_tinywebdb_api_handler');
add_action('wp_ajax_nopriv_wp_tinywebdb_getvalue', 'wp_tinywebdb_api_handler');
add_action('wp_ajax_wp_tinywebdb_storeavalue', 'wp_tinywebdb_api_handler');
add_action('wp_ajax_nopriv_wp_tinywebdb_storeavalue', 'wp_tinywebdb_api_handler');

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
    if ($results === null) {
        echo '<p>Error: ' . $wpdb->last_error . '</p>';
    } else {
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
    }

    echo '</div>';
}

// 添加短代码以在前端显示表单
function wp_tinywebdb_shortcode() {
    ob_start();
    ?>
    <h2>App Inventor (TinyWebDB) Web Database Service</h2>
    <h3>Search database for a tag</h3>
    <form id="tinywebdb-get-form">
        <p>Tag: <input type="text" name="tag" /></p>
        <input type="submit" value="Get value">
    </form>
    <div id="get-result"></div>

    <h3>Store a tag-value pair in the database</h3>
    <form id="tinywebdb-store-form">
        <p>Tag: <input type="text" name="tag" /></p>
        <p>Value: <input type="text" name="value" /></p>
        <input type="submit" value="Store a value">
    </form>
    <div id="store-result"></div>

    <script>
    jQuery(document).ready(function($) {
        $('#tinywebdb-get-form').submit(function(e) {
            e.preventDefault();
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'GET',
                data: {
                    action: 'wp_tinywebdb_getvalue',
                    tag: $('input[name="tag"]', this).val()
                },
                success: function(response) {
                    $('#get-result').text(JSON.stringify(response));
                }
            });
        });

        $('#tinywebdb-store-form').submit(function(e) {
            e.preventDefault();
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'wp_tinywebdb_storeavalue',
                    tag: $('input[name="tag"]', this).val(),
                    value: $('input[name="value"]', this).val()
                },
                success: function(response) {
                    $('#store-result').text(response);
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('tinywebdb_form', 'wp_tinywebdb_shortcode');

// 确保 jQuery 被加载
function wp_tinywebdb_enqueue_scripts() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'wp_tinywebdb_enqueue_scripts');
