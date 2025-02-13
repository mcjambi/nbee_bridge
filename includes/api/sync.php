<?php
require_once NBEE_PLUGIN_PATH . "admin/sync/product.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/coupon.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/customer.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/order.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product-brand.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product-category.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product-collection.php";
require_once NBEE_PLUGIN_PATH . "admin/sync/product-review.php";

// Đăng ký route REST API với PATCH method
add_action('rest_api_init', function () {
    register_rest_route('sync/v1', '/entity', [
        'methods' => 'PATCH',
        'callback' => 'handle_single_sync_request',
        'permission_callback' => '__return_true',
    ]);
});

function handle_single_sync_request($request)
{
    $headers = getallheaders();
    $provided_key = isset($headers['Authorization']) ? $headers['Authorization'] : null;

    $nbee_wp_access_token = get_option('nbee_wp_access_token');
    if (!$provided_key || $provided_key !== "Bearer $nbee_wp_access_token") {
        return new WP_REST_Response(null, 200);
    }


    $data = $request->get_json_params();
    if (empty($data['type']) || empty($data['id'])) {
        return new WP_REST_Response(null, 200);
    }

    switch ($data['type']) {
        case 'product':
            nbee_sync_single_product($data['id']);
            break;
        case 'voucher':
            nbee_sync_single_coupon($data['id']);
            break;
        case 'user':
            nbee_sync_single_customer($data['id']);
            break;
        case 'order':
            nbee_sync_single_order($data['id']);
            break;
        case 'product_brand':
            nbee_sync_single_product_brand($data['id']);
            break;
        case 'product_category':
            nbee_sync_single_product_category($data['id']);
            break;
        case 'product_collection':
            nbee_sync_single_product_collection($data['id']);
            break;
        case 'product_review':
            nbee_sync_single_product_review($data['id']);
            break;
        default:
            return new WP_REST_Response(null, 200);
    }

    return new WP_REST_Response(null, 200);
}
