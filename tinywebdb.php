<?php
/*
Plugin Name: WordPress TinyWebDB
Description: A TinyWebDB implementation for WordPress
Version: 1.0
Author: Your Name
*/

if (!class_exists('WP_TinyWebDB')) {

class WP_TinyWebDB {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'handle_api_request'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_shortcode('tinywebdb_form', array($this, 'shortcode_tinywebdb_form'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function activate() {
        // 创建一个默认页面
        $page_title = 'TinyWebDB Interface';
        $page_content = '[tinywebdb_form]';
        $page_check = get_page_by_title($page_title);
        if(!$page_check) {
            wp_insert_post(
                array(
                    'post_type' => 'page',
                    'post_title' => $page_title,
                    'post_content' => $page_content,
                    'post_status' => 'publish',
                    'post_author' => 1,
                )
            );
        }
    }

    public function handle_api_request() {
        $action = isset($_REQUEST['tinywebdb_action']) ? $_REQUEST['tinywebdb_action'] : '';
        
        if ($action === 'getvalue' || $action === 'storeavalue') {
            $this->handle_api_action($action);
            exit;
        }
    }

    private function handle_api_action($action) {
        $tag = isset($_REQUEST['tag']) ? sanitize_text_field($_REQUEST['tag']) : '';
        
        if ($action === 'getvalue') {
            $value = $this->get_value($tag);
            $this->json_response(array("VALUE", $tag, $value));
        } elseif ($action === 'storeavalue') {
            $value = isset($_REQUEST['value']) ? sanitize_text_field($_REQUEST['value']) : '';
            $this->store_value($tag, $value);
            $this->json_response(array("STORED", $tag, $value));
        }
    }

    private function get_value($tag) {
        $post = get_page_by_path($tag, OBJECT, 'tinywebdb');
        return $post ? $post->post_content : null;
    }

    private function store_value($tag, $value) {
        $post = get_page_by_path($tag, OBJECT, 'tinywebdb');
        
        $post_data = array(
            'post_title' => $tag,
            'post_content' => $value,
            'post_type' => 'tinywebdb',
            'post_status' => 'publish'
        );

        if ($post) {
            $post_data['ID'] = $post->ID;
            wp_update_post($post_data);
        } else {
            wp_insert_post($post_data);
        }
    }

    private function json_response($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function add_admin_menu() {
        add_menu_page('TinyWebDB', 'TinyWebDB', 'manage_options', 'tinywebdb', array($this, 'admin_page'), 'dashicons-database', 6);
    }

    public function admin_page() {
        $posts = get_posts(array('post_type' => 'tinywebdb', 'posts_per_page' => -1));
        ?>
        <div class="wrap">
            <h1>TinyWebDB Data</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td><?php echo esc_html($post->post_title); ?></td>
                            <td><?php echo esc_html($post->post_content); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function shortcode_tinywebdb_form() {
        ob_start();
        ?>
        <div id="tinywebdb-container">
            <h2>TinyWebDB Interface</h2>
            <h3>Get Value</h3>
            <form id="tinywebdb-get-form">
                <input type="hidden" name="tinywebdb_action" value="getvalue">
                <label>Tag: <input type="text" name="tag"></label>
                <input type="submit" value="Get Value">
            </form>
            <div id="get-result"></div>

            <h3>Store Value</h3>
            <form id="tinywebdb-store-form">
                <input type="hidden" name="tinywebdb_action" value="storeavalue">
                <label>Tag: <input type="text" name="tag"></label>
                <label>Value: <input type="text" name="value"></label>
                <input type="submit" value="Store Value">
            </form>
            <div id="store-result"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#tinywebdb-get-form, #tinywebdb-store-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var resultDiv = form.attr('id') === 'tinywebdb-get-form' ? '#get-result' : '#store-result';
                
                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: form.serialize() + '&action=tinywebdb_ajax',
                    success: function(response) {
                        $(resultDiv).text(JSON.stringify(response));
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

// 初始化插件
function wp_tinywebdb_init() {
    WP_TinyWebDB::get_instance();
}
add_action('plugins_loaded', 'wp_tinywebdb_init');

// 注册自定义文章类型
function wp_tinywebdb_register_post_type() {
    register_post_type('tinywebdb', array(
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => false,
    ));
}
add_action('init', 'wp_tinywebdb_register_post_type');

// 处理 AJAX 请求
function wp_tinywebdb_ajax_handler() {
    WP_TinyWebDB::get_instance()->handle_api_request();
    wp_die();
}
add_action('wp_ajax_tinywebdb_ajax', 'wp_tinywebdb_ajax_handler');
add_action('wp_ajax_nopriv_tinywebdb_ajax', 'wp_tinywebdb_ajax_handler');

}
