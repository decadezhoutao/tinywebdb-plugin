<?php
/*
Plugin Name: Export Category Posts to CSV
Description: Export all posts from a selected category into a properly formatted CSV file for download.
Version: 1.2
Author: Tao Zhou
*/

if (!defined('ABSPATH')) {
    exit; // 禁止直接访问
}

// 添加插件菜单
add_action('admin_menu', 'add_export_posts_csv_menu');
function add_export_posts_csv_menu() {
    add_menu_page(
        'Export Category Posts',
        'Export Posts CSV',
        'manage_options',
        'export-category-posts-csv',
        'render_export_posts_csv_page',
        'dashicons-download',
        20
    );
}

// 渲染插件页面
function render_export_posts_csv_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // 处理表单提交
    if (isset($_POST['export_posts_csv'])) {
        $category_id = intval($_POST['category']);
        if ($category_id) {
            generate_csv_file($category_id);
        }
    }

    // 获取所有分类
    $categories = get_categories(['hide_empty' => false]);

    ?>
    <div class="wrap">
        <h1>Export Posts by Category</h1>
        <form method="post">
            <label for="category">Select a Category:</label>
            <select name="category" id="category" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo esc_attr($category->term_id); ?>">
                        <?php echo esc_html($category->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <input type="submit" name="export_posts_csv" value="Export to CSV" class="button button-primary">
        </form>
    </div>
    <?php
}

// 生成 CSV 文件
function generate_csv_file($category_id) {
    // 获取分类下的所有文章
    $posts = get_posts([
        'category' => $category_id,
        'numberposts' => -1,
    ]);

    // 如果没有文章
    if (empty($posts)) {
        echo '<div class="notice notice-warning"><p>No posts found in the selected category.</p></div>';
        return;
    }

    // 设置 CSV 数据
    $csv_data = [];
    $csv_data[] = ['ID', 'Title', 'Content', 'Author', 'Date', 'URL']; // 表头

    foreach ($posts as $post) {
        $content = preg_replace('/\s+/', ' ', trim(strip_tags($post->post_content))); // 去掉多余空格、HTML标签
        $csv_data[] = [
            $post->ID,
            $post->post_title,
            $content,
            get_the_author_meta('display_name', $post->post_author),
            $post->post_date,
            get_permalink($post),
        ];
    }

    // 设置下载头
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="category-' . $category_id . '-posts.csv"');

    // 打开输出流
    $output = fopen('php://output', 'w');

    // 写入 CSV 数据
    foreach ($csv_data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}
