<?php
/*
Plugin Name: Export Posts to CSV
Description: Export all posts from a selected category to a CSV file.
Version: 1.0
Author: Tao Zhou
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add an admin menu page for the plugin
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

// Display the plugin admin page
function export_posts_to_csv_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission and export
    if (isset($_POST['export_posts_to_csv'])) {
        $category_id = intval($_POST['category']);
        export_posts_to_csv($category_id);
    }

    // Fetch all categories
    $categories = get_categories();
    ?>
    <div class="wrap">
        <h1>Export Posts to CSV</h1>
        <form method="post">
            <label for="category">Select Category:</label>
            <select name="category" id="category" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo esc_attr($category->term_id); ?>">
                        <?php echo esc_html($category->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <input type="submit" name="export_posts_to_csv" value="Export to CSV" class="button button-primary">
        </form>
    </div>
    <?php
}

// Export posts to CSV
function export_posts_to_csv($category_id) {
    if (!$category_id) {
        return;
    }

    // Fetch posts from the selected category
    $posts = get_posts([
        'category' => $category_id,
        'numberposts' => -1,
    ]);

    if (empty($posts)) {
        echo '<div class="notice notice-warning"><p>No posts found in the selected category.</p></div>';
        return;
    }

    // Prepare CSV data
    $csv_data = [];
    $csv_data[] = ['ID', 'Title', 'Content', 'Author', 'Date', 'URL'];

    foreach ($posts as $post) {
        $csv_data[] = [
            $post->ID,
            $post->post_title,
            strip_tags($post->post_content),
            get_the_author_meta('display_name', $post->post_author),
            $post->post_date,
            get_permalink($post),
        ];
    }

    // Output CSV for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=posts-category-' . $category_id . '.csv');

    $output = fopen('php://output', 'w');

    foreach ($csv_data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);

    exit;
}
