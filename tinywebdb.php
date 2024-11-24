<?php
/**
 * Plugin Name: Export Posts to CSV
 * Description: Select posts by category and export them to a CSV file.
 * Version: 1.0
 * Author: Tao Zhou
 */

if (!defined('ABSPATH')) {
    exit; // 防止直接访问文件
}

// 添加菜单到 WordPress 管理面板
add_action('admin_menu', 'export_posts_to_csv_menu');

function export_posts_to_csv_menu() {
    add_menu_page(
        'Export Posts to CSV',
        'Export to CSV',
        'manage_options',
        'export-posts-to-csv',
        'export_posts_to_csv_page',
        'dashicons-download',
        20
    );
}

// 插件主页面
function export_posts_to_csv_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // 获取所有分类
    $categories = get_categories(['hide_empty' => false]);

    ?>
    <div class="wrap">
        <h1>Export Posts to CSV</h1>
        <form method="POST" action="">
            <label for="category">Select a category:</label>
            <select name="category" id="category">
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo esc_attr($category->term_id); ?>">
                        <?php echo esc_html($category->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="submit" name="export_csv" value="Export CSV" class="button button-primary">
        </form>
    </div>
    <?php

    if (isset($_POST['export_csv'])) {
        $category_id = intval($_POST['category']);
        export_posts_by_category_to_csv($category_id);
    }
}

// 导出函数
function export_posts_by_category_to_csv($category_id) {
    if (!$category_id) {
        echo '<div class="error">Please select a valid category.</div>';
        return;
    }

    // 获取该分类下的文章
    $posts = get_posts([
        'category' => $category_id,
        'numberposts' => -1,
    ]);

    if (empty($posts)) {
        echo '<div class="error">No posts found in this category.</div>';
        return;
    }

    // 准备 CSV 文件头
    $csv_output = fopen('php://output', 'w');
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="posts.csv"');

    // 写入表头
    fputcsv($csv_output, ['Post ID', 'Title', 'Content', 'Date', 'Author']);

    // 写入文章数据
    foreach ($posts as $post) {
        fputcsv($csv_output, [
            $post->ID,
            $post->post_title,
            $post->post_content,
            $post->post_date,
            get_the_author_meta('display_name', $post->post_author),
        ]);
    }

    fclose($csv_output);
    exit;
}
