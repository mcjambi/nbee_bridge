<?php
function nbee_sync_product_variant()
{
    global $wpdb; // Kết nối database WordPress
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping'; // Bảng ánh xạ ID

    $page = 1;
    $limit = 20;
    $is_more_data = true; // Biến kiểm tra có còn dữ liệu hay không
    $nbee_backend_media_uri = get_option('nbee_backend_media_uri');
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;

    while ($is_more_data && $token) {

        // Gửi request để lấy dữ liệu phân loại sản phẩm
        $response = wp_remote_get(
            // $nbee_backend_crm_uri . '/product_variant/admin?page=' . $page . '&limit=' . $limit . '&sort=createdAt:desc',
            $nbee_backend_crm_uri . '/product_variant/1',
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

        $variants = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($variants)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($variants as $variant) {

            if (empty($variant['variant_name'])) {
                continue; // Bỏ qua nếu không đủ dữ liệu cơ bản
            }

            // Lấy wp_id của sản phẩm liên quan từ bảng ánh xạ
            $product_wp_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'product'",
                $variant['product_id']
            ));

            if (!$product_wp_id) {
                continue; // Nếu không tìm thấy sản phẩm, bỏ qua variant này
            }

            // Kiểm tra nếu variant đã tồn tại, nếu chưa thì tạo mới
            $wp_variant_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'variant'",
                $variant['variant_id']
            ));

            if ($wp_variant_id) {
                // Nếu variant đã tồn tại, lấy thông tin variant
                $wc_variant = new WC_Product_Variation($wp_variant_id);
            } else {
                // Nếu variant chưa tồn tại, tạo mới
                $wc_variant = new WC_Product_Variation();
                $wc_variant->set_parent_id($product_wp_id); // Gán product_id cho variant
            }

            // Cập nhật thông tin của variant
            $wc_variant->set_name($variant['variant_name']);
            $wc_variant->set_slug($variant['variant_slug']);
            $wc_variant->set_sku($variant['variant_sku']);
            $wc_variant->set_description($variant['variant_excerpt']);
            $wc_variant->set_regular_price($variant['variant_original_price']);
            $wc_variant->set_sale_price($variant['variant_price']);
            $wc_variant->set_price($variant['variant_price']);
            $wc_variant->set_stock_status('instock');
            $wc_variant->set_status($variant['variant_status'] == 1 ? 'publish' : 'draft');


            // Cập nhật thông tin thumbnail cho variant
            $variant_thumbnail_url = isset($variant['variant_thumbnail_to_media']['media_thumbnail']['scale-512'])
                ? $nbee_backend_media_uri . '/' . $variant['variant_thumbnail_to_media']['media_thumbnail']['scale-512']
                : $nbee_backend_media_uri . '/' . $variant['variant_thumbnail_to_media']['media_url'];

            if (!empty($variant_thumbnail_url) && filter_var($variant_thumbnail_url, FILTER_VALIDATE_URL)) {
                $media_id = upload_image_to_media_library($variant_thumbnail_url);
                $wc_variant->set_image_id($media_id);
            }

            // Lưu variant
            $wc_variant->save();
            $variant_id = $wc_variant->get_id(); // Lấy ID variant sau khi lưu

            // Thêm ánh xạ ID nếu variant mới
            if (!$wp_variant_id) {
                $wpdb->insert(
                    $tbl_id_mapping,
                    array(
                        'wp_id'   => $variant_id,
                        'nbee_id' => $variant['variant_id'],
                        'type'    => 'variant',
                    ),
                    array('%d', '%s', '%s')
                );
            }

            // Cập nhật meta cho variant
            unset($variant['product_variant_commission'], $variant['product_variant_rebate'], $variant['product_variant_tiered_rebate'], $variant['variant_has_commission'], $variant['variant_has_rebate'], $variant['variant_has_tiered_rebate']);
            update_post_meta($variant_id, '_product_variant_fields', json_encode($variant, JSON_UNESCAPED_UNICODE));
        }

        // Tăng số trang lên để lấy dữ liệu tiếp theo
        $page++;
    }
}
