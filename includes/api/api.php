<?php
require_once NBEE_PLUGIN_PATH . "includes/api/sync.php";

// Tạo API Key trong Cài đặt WordPress
add_action('admin_init', function () {
    if (!get_option('sync_api_key')) {
        $api_key = wp_generate_password(32, false, false);
        update_option('sync_api_key', $api_key);
    }
});

// Hiển thị API Key trong trang Cài đặt
add_action('admin_menu', function () {
    add_options_page(
        'WooCommerce Sync API Key',
        'Sync API Key',
        'manage_options',
        'sync-api-key',
        'sync_api_key_page'
    );
});

function sync_api_key_page()
{
    $api_key = get_option('sync_api_key');
    echo '<div class="wrap">';
    echo '<h1>WooCommerce Sync API Key</h1>';
    echo '<p><strong>Your API Key:</strong> ' . esc_html($api_key) . '</p>';
    echo '</div>';
}
