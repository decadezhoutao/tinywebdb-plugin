<?php
/**
 * Plugin Name: Gmail CSV Importer
 * Description: Import CSV files from Gmail and create WordPress posts.
 * Version: 1.0
 * Author: Your Name
 * Text Domain: gmail-csv-importer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// 插件激活时设置
function gmail_csv_importer_activate() {
    if (!wp_next_scheduled('gmail_csv_importer_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'gmail_csv_importer_cron_hook');
    }
}
register_activation_hook(__FILE__, 'gmail_csv_importer_activate');

// 插件停用时清除设置
function gmail_csv_importer_deactivate() {
    wp_clear_scheduled_hook('gmail_csv_importer_cron_hook');
}
register_deactivation_hook(__FILE__, 'gmail_csv_importer_deactivate');

// 添加定时任务的钩子
add_action('gmail_csv_importer_cron_hook', 'gmail_csv_importer_run');

// 插件的主功能
function gmail_csv_importer_run() {
    $client = get_gmail_client(); // 假设你已经设置好了 Google_Client 并完成了认证流程
    // 确认客户端已经认证
    if ($client) {
        $service = new Google_Service_Gmail($client);
        $userId = 'me';
        $query = 'from:sender@example.com subject:"CSV Attachment" has:attachment';
        $optParams = array(
            'q' => $query,
            'maxResults' => 10
        );
        $messages = $service->users_messages->listUsersMessages($userId, $optParams);

        foreach ($messages->getMessages() as $message) {
            process_message($service, $userId, $message->getId());
        }
    }
}

// 处理单个邮件
function process_message($service, $userId, $messageId) {
    $message = $service->users_messages->get($userId, $messageId, ['format' => 'full']);
    $parts = $message->getPayload()->getParts();
    foreach ($parts as $part) {
        if ($part->getFilename() && substr($part->getFilename(), -3) === 'csv') {
            $attachmentId = $part->getBody()->getAttachmentId();
            $attachment = $service->users_messages_attachments->get($userId, $messageId, $attachmentId);
            $data = base64_decode(strtr($attachment->getData(), '-_', '+/'));
            create_post_from_csv($data);
        }
    }
}

// 从 CSV 创建文章
function create_post_from_csv($csvData) {
    $rows = explode("\n", $csvData);
    array_shift($rows); // 假设第一行是标题行，需要移除

    foreach ($rows as $row) {
        $data = str_getcsv($row);
        // 假设 CSV 格式是: "Title", "Content"
        $new_post = array(
            'post_title'    => sanitize_text_field($data[0]),
            'post_content'  => sanitize_text_field($data[1]),
            'post_status'   => 'publish',
            'post_author'   => 1, // 或其他适当的用户ID
            'post_type'     => 'post',
        );
        wp_insert_post($new_post);
    }
}

// 谷歌认证和客户端设置
function get_gmail_client() {
    require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';
    $client = new Google_Client();
    $client->setAuthConfig(plugin_dir_path(__FILE__) . '/credentials.json');
    $client->addScope(Google_Service_Gmail::GMAIL_READONLY);

    // 根据实际情况调整认证流程，可能需要交互式网页授权等
    return $client;
}

?>
