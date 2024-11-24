<?php
/*
Plugin Name: Category Posts Table Exporter
Description: Export posts from selected categories to CSV file with table preview
Version: 1.0
Author: Your Name
*/

// 防止直接访问此文件
if (!defined('ABSPATH')) {
    exit;
}

// 添加管理菜单
add_action('admin_menu', 'cpe_add_admin_menu');
function cpe_add_admin_menu() {
    add_menu_page(
        '导出分类文章', 
        '导出分类文章',
        'manage_options',
        'category-posts-exporter',
        'cpe_admin_page',
        'dashicons-download'
    );
}

// 添加必要的CSS和JavaScript
add_action('admin_enqueue_scripts', 'cpe_enqueue_scripts');
function cpe_enqueue_scripts($hook) {
    if ('toplevel_page_category-posts-exporter' !== $hook) {
        return;
    }
    
    wp_enqueue_style('cpe-style', plugins_url('css/style.css', __FILE__));
    wp_enqueue_script('cpe-script', plugins_url('js/script.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('cpe-script', 'cpe_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cpe_ajax_nonce')
    ));
}

// 创建必要的目录和文件
function cpe_create_plugin_files() {
    // 创建css目录和文件
    $css_dir = plugin_dir_path(__FILE__) . 'css';
    if (!file_exists($css_dir)) {
        mkdir($css_dir, 0755, true);
    }
    
    $css_file = $css_dir . '/style.css';
    if (!file_exists($css_file)) {
        file_put_contents($css_file, '
            .cpe-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .cpe-table th, .cpe-table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .cpe-table th {
                background-color: #f5f5f5;
            }
            .cpe-download-btn {
                margin-top: 20px !important;
                margin-bottom: 20px !important;
            }
        ');
    }
    
    // 创建js目录和文件
    $js_dir = plugin_dir_path(__FILE__) . 'js';
    if (!file_exists($js_dir)) {
        mkdir($js_dir, 0755, true);
    }
    
    $js_file = $js_dir . '/script.js';
    if (!file_exists($js_file)) {
        file_put_contents($js_file, '
            jQuery(document).ready(function($) {
                $("#category-select").change(function() {
                    var category_id = $(this).val();
                    if(category_id) {
                        $.ajax({
                            url: cpe_ajax.ajax_url,
                            type: "POST",
                            data: {
                                action: "get_category_posts",
                                category_id: category_id,
                                nonce: cpe_ajax.nonce
                            },
                            success: function(response) {
                                $("#posts-table").html(response);
                                $("#download-btn").show();
                            }
                        });
                    } else {
                        $("#posts-table").html("");
                        $("#download-btn").hide();
                    }
                });
            });
        ');
    }
}

// 在插件激活时创建文件
register_activation_hook(__FILE__, 'cpe_create_plugin_files');

// 创建管理页面
function cpe_admin_page() {
    ?>
    <div class="wrap">
        <h1>导出分类文章到CSV</h1>
        
        <select id="category-select" name="category_id">
            <option value="">请选择分类</option>
            <?php
            $categories = get_categories(array('hide_empty' => false));
            foreach ($categories as $category) {
                echo sprintf(
                    '<option value="%s">%s</option>',
                    esc_attr($category->term_id),
                    esc_html($category->name)
                );
            }
            ?>
        </select>

        <div id="posts-table"></div>
        
        <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="download-form" style="display:none;">
            <input type="hidden" name="action" value="download_csv">
            <input type="hidden" name="category_id" id="download-category-id">
            <?php wp_nonce_field('cpe_download_nonce', 'cpe_nonce'); ?>
            <button type="submit" class="button button-primary cpe-download-btn" id="download-btn">
                下载CSV文件
            </button>
        </form>
    </div>
    <?php
}

// AJAX处理函数：获取分类文章
add_action('wp_ajax_get_category_posts', 'cpe_get_category_posts');
function cpe_get_category_posts() {
    check_ajax_referer('cpe_ajax_nonce', 'nonce');
    
    $category_id = intval($_POST['category_id']);
    if ($category_id <= 0) {
        wp_send_json_error('Invalid category ID');
    }
    
    $posts = get_posts(array(
        'category' => $category_id,
        'numberposts' => -1,
        'post_status' => 'publish'
    ));
    
    ob_start();
    ?>
    <table class="cpe-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>标题</th>
                <th>发布日期</th>
                <th>作者</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $post): ?>
                <tr>
                    <td><?php echo esc_html($post->ID); ?></td>
                    <td><?php echo esc_html($post->post_title); ?></td>
                    <td><?php echo get_the_date('Y-m-d', $post); ?></td>
                    <td><?php echo esc_html(get_the_author_meta('display_name', $post->post_author)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <script>
        jQuery(document).ready(function($) {
            $("#download-form").show();
            $("#download-category-id").val(<?php echo $category_id; ?>);
        });
    </script>
    <?php
    
    echo ob_get_clean();
    wp_die();
}

// AJAX处理函数：下载CSV
add_action('wp_ajax_download_csv', 'cpe_download_csv');
function cpe_download_csv() {
    if (!check_admin_referer('cpe_download_nonce', 'cpe_nonce')) {
        wp_die('Invalid nonce');
    }
    
    $category_id = intval($_POST['category_id']);
    if ($category_id <= 0) {
        wp_die('Invalid category ID');
    }
    
    $category = get_term($category_id, 'category');
    $posts = get_posts(array(
        'category' => $category_id,
        'numberposts' => -1,
        'post_status' => 'publish'
    ));
    
    // 设置CSV文件头
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $category->slug . '-posts-' . date('Y-m-d') . '.csv');
    
    // 创建输出流
    $output = fopen('php://output', 'w');
    
    // 添加UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV头行
    fputcsv($output, array(
        'ID',
        '标题',
        '内容',
        '发布日期',
        '作者',
        '永久链接',
        '摘要',
        '标签'
    ));
    
    // 写入数据
    foreach ($posts as $post) {
        $tags = get_the_tags($post->ID);
        $tag_names = array();
        if ($tags) {
            foreach ($tags as $tag) {
                $tag_names[] = $tag->name;
            }
        }
        
        fputcsv($output, array(
            $post->ID,
            $post->post_title,
            wp_strip_all_tags($post->post_content),
            get_the_date('Y-m-d', $post),
            get_the_author_meta('display_name', $post->post_author),
            get_permalink($post->ID),
            wp_strip_all_tags($post->post_excerpt),
            implode(', ', $tag_names)
        ));
    }
    
    fclose($output);
    exit;
}
