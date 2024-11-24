<?php
/*
Plugin Name: Category Posts Exporter
Description: Export posts from selected categories to CSV file
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

// 创建管理页面
function cpe_admin_page() {
    ?>
    <div class="wrap">
        <h1>导出分类文章到CSV</h1>
        <form method="post" action="">
            <?php
            wp_nonce_field('cpe_export_action', 'cpe_nonce');
            $categories = get_categories(array('hide_empty' => false));
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">选择分类</th>
                    <td>
                        <select name="category_id" required>
                            <option value="">请选择分类</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->term_id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="export_posts" class="button button-primary" value="导出CSV">
            </p>
        </form>
    </div>
    <?php
    
    // 处理导出请求
    if (isset($_POST['export_posts']) && check_admin_referer('cpe_export_action', 'cpe_nonce')) {
        $category_id = intval($_POST['category_id']);
        if ($category_id > 0) {
            cpe_export_posts_to_csv($category_id);
        }
    }
}

// 导出文章到CSV的函数
function cpe_export_posts_to_csv($category_id) {
    // 获取分类信息
    $category = get_term($category_id, 'category');
    if (is_wp_error($category)) {
        wp_die('分类不存在');
    }
    
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
    
    // 获取分类下的文章
    $posts = get_posts(array(
        'category' => $category_id,
        'numberposts' => -1,
        'post_status' => 'publish'
    ));
    
    // 遍历文章并写入CSV
    foreach ($posts as $post) {
        // 获取文章标签
        $tags = wp_get_post_tags($post->ID);
        $tag_names = array();
        foreach ($tags as $tag) {
            $tag_names[] = $tag->name;
        }
        
        // 获取作者信息
        $author = get_user_by('id', $post->post_author);
        
        // 写入CSV行
        fputcsv($output, array(
            $post->ID,
            $post->post_title,
            wp_strip_all_tags($post->post_content),
            $post->post_date,
            $author->display_name,
            get_permalink($post->ID),
            $post->post_excerpt,
            implode(', ', $tag_names)
        ));
    }
    
    fclose($output);
    exit;
}

// 添加插件激活时的操作
register_activation_hook(__FILE__, 'cpe_activate');
function cpe_activate() {
    // 检查WordPress版本
    if (version_compare(get_bloginfo('version'), '4.0', '<')) {
        wp_die('此插件需要WordPress 4.0或更高版本');
    }
    
    // 添加其他初始化操作（如果需要）
    flush_rewrite_rules();
}

// 添加插件停用时的操作
register_deactivation_hook(__FILE__, 'cpe_deactivate');
function cpe_deactivate() {
    // 清理插件数据（如果需要）
    flush_rewrite_rules();
}
