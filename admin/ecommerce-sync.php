<?php

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
            nbee_sync_product_catalog();
            break;
            // ...existing code...
    }

    wp_redirect(admin_url('admin.php?page=nbee_ecommerce_sync'));
    exit;
}

function nbee_sync_product_catalog()
{
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
            // Kiểm tra xem category đã tồn tại hay chưa
            $existing_term = get_term_by('slug', $category['category_slug'], 'product_cat');

            if ($existing_term) {
                // Nếu danh mục tồn tại, cập nhật thông tin
                wp_update_term(
                    $existing_term->term_id,
                    'product_cat',
                    array(
                        'name'        => $category['category_name'],
                        'description' => $category['category_excerpt'],
                        'slug'        => $category['category_slug'],
                    )
                );
                $term_id = $existing_term->term_id;
            } else {
                // Nếu danh mục chưa tồn tại, tạo mới
                $term = wp_insert_term(
                    $category['category_name'],
                    'product_cat',
                    array(
                        'description' => $category['category_excerpt'],
                        'slug'        => $category['category_slug'],
                    )
                );

                if (is_wp_error($term)) {
                    // Nếu có lỗi khi tạo danh mục, tiếp tục vòng lặp
                    continue;
                }
                $term_id = $term['term_id'];
            }

            // Cập nhật các metadata khác
            update_term_meta($term_id, 'order', $category['category_order']);
            update_term_meta($term_id, 'status', $category['category_status']);
            if (!empty($category['category_tags'])) {
                update_term_meta($term_id, 'tags', $category['category_tags']);
            }
            update_term_meta($term_id, 'createdAt', $category['createdAt']);
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
