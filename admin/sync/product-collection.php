<?php
// Initialize new taxonomy for product collection
function nbee_register_product_collection_taxonomy()
{

    $labels = array(
        'name'              => _x('Bộ sưu tập', 'taxonomy general name', 'textdomain'),
        'singular_name'     => _x('Bộ sưu tập', 'taxonomy singular name', 'textdomain'),
        'search_items'      => __('Tìm kiếm bộ sưu tập', 'textdomain'),
        'all_items'         => __('Tất cả bộ sưu tập', 'textdomain'),
        'edit_item'         => __('Chỉnh sửa bộ sưu tập', 'textdomain'),
        'update_item'       => __('Cập nhật bộ sưu tập', 'textdomain'),
        'add_new_item'      => __('Tạo bộ sưu tập', 'textdomain'),
        'new_item_name'     => __('Tên bộ sưu tập mới', 'textdomain'),
        'menu_name'         => __('Bộ sưu tập', 'textdomain'),
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'product_collection'),
    );

    register_taxonomy('product_collection', array('product'), $args);
}
add_action('init', 'nbee_register_product_collection_taxonomy');

function nbee_sync_product_collection()
{
    $page = 1; // Start from page 1
    $limit = 100; // Number of collections per sync
    $is_more_data = true; // Check if there is more data
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');

    while ($is_more_data) {
        // Send request to API with pagination parameters
        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/sync/product_collections?page=' . $page . '&limit=' . $limit,
            array(
                'headers' => array(
                    'x-signed' => $nbee_backend_xsigned
                ),
            )
        );

        if (is_wp_error($response)) {
            error_log("API request error: " . $response->get_error_message());
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code != 200) {
            error_log("API response status code: " . $status_code);
            return;
        }

        $collections = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($collections)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($collections as $collection) {
            nbee_handle_product_collection($collection);
        }

        // Increase page number to get next data
        $page++;
    }
}


function nbee_sync_single_product_collection($collection_id)
{
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');

    // Send request to API with pagination parameters
    $response = wp_remote_get(
        $nbee_backend_crm_uri . '/sync/product_collection/' . $collection_id,
        array(
            'headers' => array(
                'x-signed' => $nbee_backend_xsigned
            ),
        )
    );

    if (is_wp_error($response)) {
        error_log("API request error: " . $response->get_error_message());
        return;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code != 200) {
        error_log("API response status code: " . $status_code);
        return;
    }

    $collection = json_decode(wp_remote_retrieve_body($response), true);

    if ($collection) {
        nbee_handle_product_collection($collection);
    }
}


function nbee_handle_product_collection($collection)
{
    global $wpdb; // Kết nối database WordPress
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping'; // Bảng ánh xạ ID

    // Check if collection already exists
    // Lấy `wp_id` từ bảng ánh xạ
    $wp_id = $wpdb->get_var($wpdb->prepare(
        "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'collection'",
        $collection['collection_id']
    ));

    if ($wp_id) {
        // If collection exists, update information
        wp_update_term(
            $wp_id,
            'product_collection',
            array(
                'name'        => $collection['collection_name'],
                'description' => $collection['collection_excerpt'],
                'slug'        => $collection['collection_slug'],
            )
        );
        $term_id = $wp_id;
    } else {
        // If collection does not exist, create new
        $term = wp_insert_term(
            $collection['collection_name'],
            'product_collection',
            array(
                'description' => $collection['collection_excerpt'],
                'slug'        => $collection['collection_slug'],
            )
        );

        if (is_wp_error($term)) {
            error_log("Error while creating collection: " . $term->get_error_message());
            return;
        }
        $term_id = $term['term_id'];

        // Thêm ánh xạ vào bảng mapping
        $wpdb->insert(
            $tbl_id_mapping,
            array(
                'wp_id'   => $term_id,
                'nbee_id' => $collection['collection_id'],
                'type'    => 'collection',
            ),
            array('%d', '%s', '%s')
        );
    }

    // Update other metadata
    update_term_meta($term_id, 'order', $collection['collection_order']);
    update_term_meta($term_id, 'status', $collection['collection_status']);
    update_term_meta($term_id, 'createdAt', $collection['createdAt']);
    update_term_meta($term_id, 'product_collection_fields', json_encode($collection, JSON_UNESCAPED_UNICODE));
}
