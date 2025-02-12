<?php
function nbee_sync_coupons()
{
    global $wpdb; // Kết nối database WordPress
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping'; // Bảng ánh xạ ID

    $page = 1; // Bắt đầu từ trang 1
    $limit = 40; // Số lượng voucher mỗi lần đồng bộ
    $is_more_data = true; // Biến kiểm tra có còn dữ liệu hay không
    $nbee_backend_media_uri = get_option('nbee_backend_media_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;

    while ($is_more_data && $token) {
        // Gửi request tới API với tham số phân trang
        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/voucher?page=' . $page . '&limit=' . $limit . '&sort=createdAt:desc',
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

        $vouchers = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($vouchers)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($vouchers as $voucher) {
            // Lấy `wp_id` từ bảng ánh xạ
            $wp_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'voucher'",
                $voucher['voucher_id']
            ));

            if ($wp_id) {
                // Nếu đã ánh xạ, cập nhật coupon
                $coupon = new WC_Coupon($wp_id);
            } else {
                // Nếu chưa ánh xạ, tạo mới coupon
                $coupon = new WC_Coupon();
            }

            // Cập nhật thông tin coupon
            $coupon->set_code($voucher['voucher_code']);
            $coupon->set_description($voucher['voucher_description']);
            $coupon->set_discount_type('percent' === $voucher['voucher_value_unit'] ? 'percent' : 'fixed_cart');
            $coupon->set_amount($voucher['voucher_value']);
            $coupon->set_date_expires(date('Y-m-d H:i:s', intval($voucher['voucher_valid_to'] / 1000)));
            $coupon->set_usage_limit($voucher['voucher_count_max']);
            $coupon->set_usage_limit_per_user($voucher['voucher_count_max_per_user']);
            $coupon->set_individual_use($voucher['is_multiple'] == 0);
            $coupon->set_free_shipping($voucher['voucher_category'] === 'shipping');
            $coupon->set_limit_usage_to_x_items(1);
            $coupon->set_status('publish');

            // Tìm và thiết lập các role
            foreach ($voucher['voucher_rule'] as $rule) {
                if ($rule['voucher_rule_key'] === 'min_totalpay_in_order' && !empty($rule['voucher_rule_value'])) {
                    $coupon->set_minimum_amount(floatval($rule['voucher_rule_value'])); // Gắn giá trị
                    continue;
                }

                // kiểm tra nếu có voucher_rule_key là voucher_rule_key và có giá trị (sẽ là dạng "1,2,3") thì tiến hành tách các giá trị (là id của sản phẩm "nbee_id" tương ứng trong bảng tbl_id_mapping), dựa vào nbee_id để lấy wp_id ra và set_product_ids cho coupon
                if ($rule['voucher_rule_key'] === 'product_match_list' && !empty($rule['voucher_rule_value'])) {
                    $product_ids = explode(',', $rule['voucher_rule_value']);
                    $wp_product_ids = array();
                    foreach ($product_ids as $product_id) {
                        $wp_product_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'product'",
                            $product_id
                        ));
                        if ($wp_product_id) {
                            $wp_product_ids[] = $wp_product_id;
                        }
                    }
                    $coupon->set_product_ids($wp_product_ids);
                    continue;
                }

                //kiểm tra nếu có voucher_rule_key là product_category_match_list và có giá trị (sẽ là dạng "1,2,3") thì tiến hành tách các giá trị (là id của category "nbee_id" tương ứng trong bảng tbl_id_mapping), dựa vào nbee_id để lấy wp_id ra và set_product_categories cho coupon
                if ($rule['voucher_rule_key'] === 'product_category_match_list' && !empty($rule['voucher_rule_value'])) {
                    $category_ids = explode(',', $rule['voucher_rule_value']);
                    $wp_category_ids = array();
                    foreach ($category_ids as $category_id) {
                        $wp_category_id = $wpdb->get_var($wpdb->prepare(
                            "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'category'",
                            $category_id
                        ));
                        if ($wp_category_id) {
                            $wp_category_ids[] = $wp_category_id;
                        }
                    }
                    $coupon->set_product_categories($wp_category_ids);
                    continue;
                }
            }

            // Lưu coupon
            $coupon->save();

            // Lưu các trường không quản lý bởi WooCommerce vào meta
            unset($voucher['voucher_description']);
            update_post_meta($coupon->get_id(), 'coupon_fields', json_encode($voucher, JSON_UNESCAPED_UNICODE));

            // Cập nhật thumbnail với link hình ảnh phù hợp
            $voucher_thumbnail_url = isset($voucher['voucher_thumbnail_to_media']['media_thumbnail']['scale-512'])
                ? $nbee_backend_media_uri . '/' . $voucher['voucher_thumbnail_to_media']['media_thumbnail']['scale-512']
                : $nbee_backend_media_uri . '/' . $voucher['voucher_thumbnail_to_media']['media_url'];

            if (!empty($voucher_thumbnail_url) && filter_var($voucher_thumbnail_url, FILTER_VALIDATE_URL)) {
                $media_id = upload_image_to_media_library($voucher_thumbnail_url);
                update_post_meta($coupon->get_id(), 'thumbnail_id', $media_id);
            }

            // Thêm ánh xạ vào bảng mapping nếu chưa có
            if (!$wp_id) {
                $wpdb->insert(
                    $tbl_id_mapping,
                    array(
                        'wp_id'   => $coupon->get_id(),
                        'nbee_id' => $voucher['voucher_id'],
                        'type'    => 'voucher',
                    ),
                    array('%d', '%s', '%s')
                );
            }
        }

        // Tăng số trang lên 1 để lấy dữ liệu tiếp theo
        $page++;
    }
}
