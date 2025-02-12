<?php
require_once NBEE_PLUGIN_PATH . "admin/sync/product-category.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product-collection.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product-brand.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product-review.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/customer.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/coupon.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/order.php";

add_action('admin_post_nbee_login_admin', 'nbee_handle_login');
add_action('admin_post_nbee_ecommerce_sync', 'nbee_ecommerce_sync_handler');


add_action('delete_post', 'nbee_delete_mapping_on_product_delete', 10, 2);
add_action('delete_user', 'nbee_delete_mapping_on_user_delete', 10, 1);
add_action('delete_term', 'nbee_delete_mapping_on_term_delete', 10, 4);


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
        setcookie('access_token', $data['access_token'], intval($data['expires_at'] / 1000), COOKIEPATH, COOKIE_DOMAIN);
        wp_redirect(admin_url('admin.php?page=nbee_ecommerce_sync'));
        exit;
    } else {
        wp_die(__('Đăng nhập thất bại. Vui lòng thử lại.'));
    }
}



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
        case 'sync_customers':
            nbee_sync_customers();
            break;
        case 'sync_products':
            nbee_sync_product();
            break;
        case 'sync_promotions':
            nbee_sync_coupons();
            break;
        case 'sync_orders':
            nbee_sync_orders();
            break;
        case 'sync_product_reviews':
            nbee_sync_product_review();
            break;
            // ...existing code...
    }

    wp_redirect(admin_url('admin.php?page=nbee_ecommerce_sync'));
    exit;
}



//Xoá các mapping khi các bản ghi WP bị xoá
function nbee_delete_mapping_on_term_delete($term_id, $tt_id, $taxonomy, $deleted_term)
{
    global $wpdb;
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping';

    // Danh sách các taxonomy được hỗ trợ và loại tương ứng
    $taxonomy_map = array(
        'product_cat' => 'category',
        'product_brand' => 'brand',
        'product_collection' => 'collection',
    );

    // Kiểm tra nếu taxonomy nằm trong danh sách
    if (isset($taxonomy_map[$taxonomy])) {
        $type = $taxonomy_map[$taxonomy];

        // Xóa bản ghi mapping liên quan đến term_id này
        $wpdb->delete(
            $tbl_id_mapping,
            array('wp_id' => $term_id, 'type' => $type),
            array('%d', '%s')
        );
    }
}


function nbee_delete_mapping_on_user_delete($user_id)
{
    global $wpdb;
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping';

    // Xóa bản ghi mapping liên quan đến user_id này
    $wpdb->delete(
        $tbl_id_mapping,
        array('wp_id' => $user_id, 'type' => 'user'),
        array('%d', '%s')
    );
}


function nbee_delete_mapping_on_product_delete($post_id, $post)
{
    // Kiểm tra xem đây có phải là một sản phẩm WooCommerce không
    if ($post->post_type === 'product') {
        global $wpdb;
        $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping';

        // Xóa bản ghi mapping với wp_id là post_id và type là 'product'
        $wpdb->delete(
            $tbl_id_mapping,
            array('wp_id' => $post_id, 'type' => 'product'),
            array('%d', '%s')
        );
    }
}
