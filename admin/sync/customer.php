<?php

function nbee_sync_customers()
{
    global $wpdb; // Kết nối database WordPress
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping'; // Bảng ánh xạ ID

    $page = 1; // Start from page 1
    $limit = 50; // Number of customers per sync
    $is_more_data = true; // Variable to check if there is more data
    $nbee_backend_media_uri = get_option('nbee_backend_media_uri');
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;

    while ($is_more_data && $token) {
        // Send request to API with pagination parameters
        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/customer?page=' . $page . '&limit=' . $limit,
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

        $customers = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($customers)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($customers as $customer) {
            // Bỏ qua nếu không có email và số điện thoại
            if (empty($customer['user_email']) && empty($customer['user_phonenumber'])) {
                continue;
            }

            // Nếu không có email, tạo email tạm bằng số điện thoại
            $email = !empty($customer['user_email']) ? $customer['user_email'] : $customer['user_phonenumber'] . '@gmail.com';

            // Lấy `wp_id` từ bảng ánh xạ
            $wp_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'customer'",
                $customer['user_id']
            ));

            unset($customer['bio'], $customer['customer_to_user'], $customer['referrer']);
            error_log($customer['display_name']);

            if ($wp_id) {
                // Nếu user đã tồn tại, cập nhật dữ liệu
                $user_id = wp_update_user(array(
                    'ID' => $wp_id,
                    'user_pass' => wp_generate_password(),
                    'user_email' => $email, // Cập nhật email
                    'display_name' => $customer['display_name'],
                    'role' => 'customer',
                    'meta_input' => array(
                        'user_avatar' => $nbee_backend_media_uri . '/' . $customer['user_avatar'],
                        'user_gender' => $customer['user_gender'],
                        'user_phonenumber' => $customer['user_phonenumber'],
                        'referrer_code' => $customer['referrer_code'],
                        'customer_fields' => json_encode($customer, JSON_UNESCAPED_UNICODE),
                    )
                ));
            } else {
                // Nếu user chưa tồn tại, tạo mới user
                $user_id = wp_insert_user(array(
                    'user_login' => $customer['user_login'],
                    'user_pass' => wp_generate_password(),
                    'user_email' => $email, // Sử dụng email đã kiểm tra
                    'display_name' => $customer['display_name'],
                    'user_registered' => gmdate('Y-m-d H:i:s', intval($customer['createdAt'] / 1000)),
                    'role' => 'customer',
                    'meta_input' => array(
                        'user_avatar' => $nbee_backend_media_uri . '/' . $customer['user_avatar'],
                        'user_gender' => $customer['user_gender'],
                        'user_phonenumber' => $customer['user_phonenumber'],
                        'referrer_code' => $customer['referrer_code'],
                        'customer_fields' => json_encode($customer, JSON_UNESCAPED_UNICODE),
                    )
                ));

                // Thêm ánh xạ vào bảng mapping
                $wpdb->insert(
                    $tbl_id_mapping,
                    array(
                        'wp_id'   => $user_id,
                        'nbee_id' => $customer['user_id'],
                        'type'    => 'customer',
                    ),
                    array('%d', '%s', '%s')
                );
            }

            if (is_wp_error($user_id)) {
                error_log("User creation/update error: " . $user_id->get_error_message());
                continue;
            }
        }


        // Increment page number to get next set of data
        $page++;
    }
}
