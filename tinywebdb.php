<?php
/*
Plugin Name: Export Posts by Category to CSV
Description: Export all posts from a selected category as a CSV file for download.
Version: 1.0
Author: Tao Zhou
*/

if (!defined('ABSPATH')) {
    exit; // 禁止直接访问
}

// 添加后台菜单
add_action('admin_menu', 'export_posts_to_csv_menu');
function export_posts_to_csv_menu() {
    add_menu_page(
        'Export Posts to CSV',
        'Export Posts',
        'manage_options',
        'export-posts-to-csv',
        'export_posts_to_csv_page',
        'dashicons-download',
        20
    );
}

// 显示插件页面
function export_posts_to_csv_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // 如果提交表单，调用导出函数
    if (isset($_POST['export_posts_to_csv'])) {
        $category_id = intval($_POST['category']);
        export_posts_to_csv($category_id);
    }

    // 获取所有分类
    $categories = get_categories();

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
            <input type="submit" name="export_posts_to_csv" value="Export as CSV" class="button button-primary">
        </form>
    </div>
    <?php
}

// 导出函数
function export_posts_to_csv($category_id) {
    if (!$category_id) {
        return;
    }

    // 获取指定分类下的所有文章
    $posts = get_posts([
        'category' => $category_id,
        'numberposts' => -1,
    ]);

    if (empty($posts)) {
        echo '<div class="notice notice-warning"><p>No posts found in the selected category.</p></div>';
        return;
    }

    // 设置 CSV 文件内容
    $csv_data = [];
    $csv_data[] = ['ID', 'Title', 'Content', 'Author', 'Date', 'URL'];

    foreach ($posts as $post) {
        $csv_data[] = [
            $post->ID,
            $post->post_title,
            preg_replace('/\s+/', ' ', strip_tags($post->post_content)), // 去除多余空格和HTML标签
            get_the_author_meta('display_name', $post->post_author),
            $post->post_date,
            get_permalink($post),
        ];
    }

    // 设置文件头并触发下载
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="category-' . $category_id . '-posts.csv"');

    $output = fopen('php://output', 'w');

    // 写入数据
    foreach ($csv_data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit; // 终止脚本执行
}
