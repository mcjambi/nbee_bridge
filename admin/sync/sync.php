<?php
require_once NBEE_PLUGIN_PATH . "admin/sync/product-category.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product-collection.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product-brand.php";

add_action('admin_post_nbee_login_admin', 'nbee_handle_login');

function nbee_handle_login()
{
    $user_input = $_POST['user_input'];
    $password = $_POST['password'];
    $device_type = $_POST['device_type'];
    $device_signature = $_POST['device_signature'];
    $device_uuid = $_POST['device_uuid'];
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');

    $response = wp_remote_post($nbee_backend_crm_uri . '/login', array(
        'body' => json_encode(array(
            'user_input' => $user_input,
            'password' => $password,
            'device_type' => $device_type,
            'device_signature' => $device_signature,
            'device_uuid' => $device_uuid
        )),
        'headers' => array(
            'Content-Type' => 'application/json'
        )
    ));

    if (is_wp_error($response)) {
        wp_die(__('Đăng nhập thất bại. Vui lòng thử lại.'));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['access_token']) && isset($data['expires_at'])) {
        setcookie('access_token', $data['access_token'], $data['expires_at'] / 1000, COOKIEPATH, COOKIE_DOMAIN);
        wp_redirect(admin_url('admin.php?page=nbee_ecommerce_sync'));
        exit;
    } else {
        wp_die(__('Đăng nhập thất bại. Vui lòng thử lại.'));
    }
}


add_action('admin_post_nbee_ecommerce_sync', 'nbee_ecommerce_sync_handler');

function nbee_ecommerce_sync_handler()
{
    if (!isset($_POST['sync_action']) || !check_admin_referer('nbee_ecommerce_sync')) {
        wp_die(__('Security check failed!', 'textdomain'));
        return;
    }

    $sync_action = sanitize_text_field($_POST['sync_action']);

    switch ($sync_action) {
        case 'sync_product_catalog':
            nbee_sync_product_category();
            break;
        case 'sync_product_collection':
            nbee_sync_product_collection();
            break;
        case 'sync_product_brand':
            nbee_sync_product_brand();
            break;
            // ...existing code...
    }

    wp_redirect(admin_url('admin.php?page=nbee_ecommerce_sync'));
    exit;
}
