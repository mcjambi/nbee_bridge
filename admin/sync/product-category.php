<?php
function nbee_sync_product_category()
{
    global $wpdb; // Kết nối database WordPress
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping'; // Bảng ánh xạ ID

    $page = 1; // Bắt đầu từ trang 1
    $limit = 100; // Số lượng danh mục mỗi lần đồng bộ
    $is_more_data = true; // Biến kiểm tra có còn dữ liệu hay không
    $nbee_backend_media_uri = get_option('nbee_backend_media_uri');
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;

    while ($is_more_data && $token) {
        // Gửi request tới API với tham số phân trang
        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/product_category/admin?page=' . $page . '&limit=' . $limit . '&sort=category_order:asc',
            array(
                'headers' => array(
                    'x-authorization' => $token
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

        $categories = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($categories)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($categories as $category) {
            // Lấy `wp_id` từ bảng ánh xạ
            $wp_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'category'",
                $category['category_id']
            ));

            if ($wp_id) {
                // Nếu đã ánh xạ, cập nhật danh mục
                wp_update_term(
                    $wp_id,
                    'product_cat',
                    array(
                        'name'        => $category['category_name'],
                        'description' => isset($category['category_description']) ? $category['category_description'] : '',
                        'slug'        => $category['category_slug'],
                    )
                );
                $term_id = $wp_id;
            } else {
                // Nếu chưa ánh xạ, tạo mới danh mục
                $term = wp_insert_term(
                    $category['category_name'],
                    'product_cat',
                    array(
                        'description' => isset($category['category_description']) ? $category['category_description'] : '',
                        'slug'        => $category['category_slug'],
                    )
                );

                if (is_wp_error($term)) {
                    error_log("Error while creating category: " . $term->get_error_message());
                    // Nếu có lỗi khi tạo danh mục, tiếp tục vòng lặp
                    continue;
                }
                $term_id = $term['term_id'];

                // Thêm ánh xạ vào bảng mapping
                $wpdb->insert(
                    $tbl_id_mapping,
                    array(
                        'wp_id'   => $term_id,
                        'nbee_id' => $category['category_id'],
                        'type'    => 'category',
                    ),
                    array('%d', '%s', '%s')
                );
            }

            // Cập nhật các metadata khác
            update_term_meta($term_id, 'order', $category['category_order']);
            update_term_meta($term_id, 'status', $category['category_status']);
            if (!empty($category['category_tags'])) {
                update_term_meta($term_id, 'tags', $category['category_tags']);
            }
            update_term_meta($term_id, 'createdAt', $category['createdAt']);
            // Xóa thuộc tính 'category_description' khỏi mảng $category
            unset($category['category_description']);
            update_term_meta($term_id, 'product_category_fields', json_encode($category, JSON_UNESCAPED_UNICODE));

            // Cập nhật thumbnail với link hình ảnh phù hợp
            $category_thumbnail_url = isset($category['category_thumbnail_to_media']['media_thumbnail']['scale-512'])
                ? $nbee_backend_media_uri . '/' . $category['category_thumbnail_to_media']['media_thumbnail']['scale-512']
                : $nbee_backend_media_uri . '/' . $category['category_thumbnail_to_media']['media_url'];

            if (!empty($category_thumbnail_url) && filter_var($category_thumbnail_url, FILTER_VALIDATE_URL)) {
                $media_id = upload_image_to_media_library($category_thumbnail_url);
                update_term_meta($term_id, 'thumbnail_id', $media_id);
            }
        }

        // Tăng số trang lên 1 để lấy dữ liệu tiếp theo
        $page++;
    }
}
