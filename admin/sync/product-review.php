<?php
function nbee_sync_product_review()
{
    global $wpdb; // Kết nối database WordPress
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping'; // Bảng ánh xạ ID

    $page = 1; // Bắt đầu từ trang 1
    $limit = 30; // Số lượng reviews mỗi lần đồng bộ
    $is_more_data = true; // Biến kiểm tra có còn dữ liệu hay không
    $nbee_backend_media_uri = get_option('nbee_backend_media_uri');
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');
    $token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;

    while ($is_more_data && $token) {
        // Gửi request tới API với tham số phân trang
        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/sync/reviews?page=' . $page . '&limit=' . $limit,
            array(
                'headers' => array(
                    'x-authorization' => $token,
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

        $reviews = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($reviews)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($reviews as $review) {
            // Lấy `wp_id` từ bảng ánh xạ
            $wp_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'review'",
                $review['review_id']
            ));

            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'product'",
                $review['review']['product_id']
            ));

            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'customer'",
                $review['review']['user_id']
            ));


            if (!$product_id || !$user_id) {
                continue;
            }

            if ($wp_id) {
                // Nếu đã ánh xạ, cập nhật review
                $comment_id = $wp_id;
                wp_update_comment(array(
                    'comment_ID' => $comment_id,
                    'comment_content' => $review['review_content'],
                    'comment_approved' => $review['review_status'],
                    'comment_post_ID' => $product_id,
                    'comment_author' => $review['review']['user']['display_name'] ?? "",
                    'comment_author_email' => $review['review']['user']['user_email'] ?? "",
                    'user_id' => $user_id,
                ));
            } else {

                // Nếu chưa ánh xạ, tạo mới review
                $comment_id = wp_insert_comment(array(
                    'comment_post_ID' => $product_id,
                    'comment_author' => $review['review']['user']['display_name'] ?? "",
                    'comment_author_email' => $review['review']['user']['user_email'] ?? "",
                    'comment_content' => $review['review_content'],
                    'comment_approved' => $review['review_status'],
                    'user_id' => $user_id,
                    'comment_date' => date('Y-m-d H:i:s', intval($review['createdAt'] / 1000)),
                ));

                if (is_wp_error($comment_id)) {
                    // Nếu có lỗi khi tạo review, tiếp tục vòng lặp
                    continue;
                }

                // Thêm ánh xạ vào bảng mapping
                $wpdb->insert(
                    $tbl_id_mapping,
                    array(
                        'wp_id' => $comment_id,
                        'nbee_id' => $review['review_id'],
                        'type' => 'review',
                    ),
                    array('%d', '%s', '%s')
                );
            }

            // Cập nhật các metadata khác
            update_comment_meta($comment_id, 'rating', $review['review_point']);
            update_comment_meta($comment_id, 'verified', 1);
            update_comment_meta($comment_id, 'review_title', $review['review_title']);
            update_comment_meta($comment_id, 'review_fields', json_encode($review, JSON_UNESCAPED_UNICODE));

            // Cập nhật media liên quan đến review
            if (!empty($review['review_media'])) {
                foreach ($review['review_media'] as $media) {
                    if (!empty($media['review_to_media'])) {
                        $media_url = $nbee_backend_media_uri . '/' . $media['review_to_media']['media_url'];
                        // Kiểm tra URL hợp lệ
                        if (!empty($media_url) && filter_var($media_url, FILTER_VALIDATE_URL)) {
                            // Tải ảnh lên thư viện media nếu chưa tồn tại
                            $media_id = upload_image_to_media_library($media_url);

                            // Kiểm tra xem media đã được thêm vào comment hay chưa
                            $existing_media_ids = get_comment_meta($comment_id, 'review_media', false);

                            if (!in_array($media_id, $existing_media_ids)) {
                                // Thêm media vào comment nếu chưa tồn tại
                                add_comment_meta($comment_id, 'review_media', $media_id);
                            }
                        }
                    }
                }
            }
        }

        // Tăng số trang lên 1 để lấy dữ liệu tiếp theo
        $page++;
    }
}
