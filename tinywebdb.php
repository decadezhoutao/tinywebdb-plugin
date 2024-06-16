<?php
/**
 * Plugin Name: Gmail CSV Importer
 * Description: Import CSV files from Gmail to WordPress posts.
 * Version: 1.0
 * Author: Tao Zhou
 */

defined('ABSPATH') or die('Direct script access disallowed.');

require_once __DIR__ . '/vendor/autoload.php';

function gmail_csv_importer_menu() {
    add_menu_page('Gmail CSV Importer', 'Gmail CSV Importer', 'manage_options', 'gmail-csv-importer', 'gmail_csv_importer_options_page');
}

add_action('admin_menu', 'gmail_csv_importer_menu');

function gmail_csv_importer_options_page() {
    ?>
    <div class="wrap">
        <h1>Gmail CSV Importer</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('gmail_csv_importer_options_group');
            do_settings_sections('gmail_csv_importer');
            submit_button();
            ?>
        </form>
        <form method="post">
            <?php submit_button('Import CSV from Gmail', 'primary', 'import_csv'); ?>
        </form>
    </div>
    <?php
}

function gmail_csv_importer_settings() {
    register_setting('gmail_csv_importer_options_group', 'gmail_csv_importer_client_id');
    register_setting('gmail_csv_importer_options_group', 'gmail_csv_importer_client_secret');
    register_setting('gmail_csv_importer_options_group', 'gmail_csv_importer_redirect_uri');

    add_settings_section('gmail_csv_importer_settings_section', 'Google API Settings', null, 'gmail_csv_importer');

    add_settings_field('gmail_csv_importer_client_id', 'Client ID', 'gmail_csv_importer_client_id_render', 'gmail_csv_importer', 'gmail_csv_importer_settings_section');
    add_settings_field('gmail_csv_importer_client_secret', 'Client Secret', 'gmail_csv_importer_client_secret_render', 'gmail_csv_importer', 'gmail_csv_importer_settings_section');
    add_settings_field('gmail_csv_importer_redirect_uri', 'Redirect URI', 'gmail_csv_importer_redirect_uri_render', 'gmail_csv_importer', 'gmail_csv_importer_settings_section');
}

add_action('admin_init', 'gmail_csv_importer_settings');

function gmail_csv_importer_client_id_render() {
    ?>
    <input type="text" name="gmail_csv_importer_client_id" value="<?php echo esc_attr(get_option('gmail_csv_importer_client_id')); ?>" />
    <?php
}

function gmail_csv_importer_client_secret_render() {
    ?>
    <input type="text" name="gmail_csv_importer_client_secret" value="<?php echo esc_attr(get_option('gmail_csv_importer_client_secret')); ?>" />
    <?php
}

function gmail_csv_importer_redirect_uri_render() {
    ?>
    <input type="text" name="gmail_csv_importer_redirect_uri" value="<?php echo esc_attr(get_option('gmail_csv_importer_redirect_uri')); ?>" />
    <?php
}

function gmail_csv_importer_authenticate() {
    if (isset($_POST['import_csv'])) {
        $client = new Google_Client();
        $client->setClientId(get_option('gmail_csv_importer_client_id'));
        $client->setClientSecret(get_option('gmail_csv_importer_client_secret'));
        $client->setRedirectUri(get_option('gmail_csv_importer_redirect_uri'));
        $client->addScope(Google_Service_Gmail::GMAIL_READONLY);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        if (isset($_GET['code'])) {
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            update_option('gmail_csv_importer_access_token', $token);
            wp_redirect(admin_url('admin.php?page=gmail-csv-importer'));
            exit;
        }

        $accessToken = get_option('gmail_csv_importer_access_token');
        if ($accessToken) {
            $client->setAccessToken($accessToken);
        }

        if ($client->isAccessTokenExpired()) {
            $authUrl = $client->createAuthUrl();
            echo '<a href="' . $authUrl . '">Click here to authorize access to Gmail</a>';
        } else {
            gmail_csv_importer_import_csv($client);
        }
    }
}

add_action('admin_init', 'gmail_csv_importer_authenticate');

function gmail_csv_importer_import_csv($client) {
    $service = new Google_Service_Gmail($client);
    $user = 'me';
    $query = 'from:paiza@ditu.jp subject:"「Python3入門編」 講座の学習状況レポート" has:attachment filename:zip is:unread';
    $messages = $service->users_messages->listUsersMessages($user, ['q' => $query]);

    foreach ($messages->getMessages() as $message) {
        $msg = $service->users_messages->get($user, $message->getId(), ['format' => 'full']);
        foreach ($msg->getPayload()->getParts() as $part) {
            if ($part->getFilename() && $part->getMimeType() === 'application/zip') {
                $data = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                $zip = new ZipArchive;
                $res = $zip->open($data);
                if ($res === TRUE) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (pathinfo($filename, PATHINFO_EXTENSION) === 'csv') {
                            $file = $zip->getFromIndex($i);
                            $csvData = convertShiftJISToUTF8($file);
                            $data = str_getcsv($csvData, "\n");
                            foreach ($data as $row) {
                                $row = str_getcsv($row);
                                $title = $row[0];
                                $content = $row[1];
                                $post_data = array(
                                    'post_title'   => wp_strip_all_tags($title),
                                    'post_content' => $content,
                                    'post_status'  => 'publish',
                                    'post_author'  => 1,
                                );
                                wp_insert_post($post_data);
                            }
                        }
                    }
                    $zip->close();
                }
                $service->users_messages->modify($user, $message->getId(), new Google_Service_Gmail_ModifyMessageRequest(['removeLabelIds' => ['UNREAD']]));
            }
        }
    }
}

function convertShiftJISToUTF8($data) {
    return mb_convert_encoding($data, 'UTF-8', 'SJIS');
}
?>
