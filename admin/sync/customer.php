<?php

add_filter('get_avatar', 'custom_user_avatar', 10, 5);

function custom_user_avatar($avatar, $id_or_email, $size, $default, $alt)
{
    // Get user by ID or email
    $user = false;
    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', $id_or_email);
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
    } elseif ($id_or_email instanceof WP_User) {
        $user = $id_or_email;
    }

    if ($user) {
        $user_id = $user->ID;
        $avatar_url = get_user_meta($user_id, 'user_avatar', true);

        // Use custom avatar if available
        if (!empty($avatar_url)) {
            $avatar = sprintf(
                '<img src="%s" alt="%s" width="%d" height="%d" class="avatar avatar-%d photo" />',
                esc_url($avatar_url),
                esc_attr($alt),
                (int) $size,
                (int) $size,
                (int) $size
            );
        }
    }

    return $avatar;
}

function nbee_sync_customers()
{
    $page = 1; // Start from page 1
    $limit = 50; // Number of customers per sync
    $is_more_data = true; // Variable to check if there is more data
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');

    while ($is_more_data) {
        // Send request to API with pagination parameters
        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/sync/users?page=' . $page . '&limit=' . $limit,
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

        $customers = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($customers)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($customers as $customer) {
            nbee_handle_customer($customer);
        }

        // Increment page number to get next set of data
        $page++;
    }
}

function nbee_sync_single_customer($user_id)
{
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');

    // Send request to API with pagination parameters
    $response = wp_remote_get(
        $nbee_backend_crm_uri . '/sync/user/' . $user_id,
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

    $customer = json_decode(wp_remote_retrieve_body($response), true);

    if ($customer) {
        nbee_handle_customer($customer);
    }
}

function nbee_handle_customer($customer)
{
    global $wpdb; // Connect to WordPress database
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping'; // ID mapping table
    $nbee_backend_media_uri = get_option('nbee_backend_media_uri');

    // If no email, create a temporary email using phone number
    $email = !empty($customer['user_email']) ? $customer['user_email'] : $customer['user_phonenumber'] . '@gmail.com';

    // Get `wp_id` from mapping table
    $wp_id = $wpdb->get_var($wpdb->prepare(
        "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'customer'",
        $customer['user_id']
    ));

    unset($customer['bio'], $customer['customer_to_user'], $customer['referrer']);

    if ($wp_id) {
        // If user exists, update data
        $user_id = wp_update_user(array(
            'ID' => $wp_id,
            'user_pass' => $customer['user_password'] ?? "",
            'user_email' => $email, // Update email
            'display_name' => $customer['display_name'],
            'first_name' => $customer['display_name'],
            'role' => $customer['user_role'],
            'meta_input' => array(
                'user_avatar' => (!empty($customer['user_avatar']) ? $nbee_backend_media_uri . '/' . $customer['user_avatar'] : ""),
                'user_gender' => $customer['user_gender'],
                'user_phonenumber' => $customer['user_phonenumber'],
                'referrer_code' => $customer['referrer_code'],
                'customer_fields' => json_encode($customer, JSON_UNESCAPED_UNICODE),
            )
        ));
    } else {
        // If user does not exist, create new user
        $user_id = wp_insert_user(array(
            'user_login' => $customer['user_login'],
            'user_pass' => $customer['user_password'] ?? "",
            'user_email' => $email, // Use checked email
            'display_name' => $customer['display_name'],
            'first_name' => $customer['display_name'],
            'user_registered' => gmdate('Y-m-d H:i:s', intval($customer['createdAt'] / 1000)),
            'role' => $customer['user_role'],
            'meta_input' => array(
                'user_avatar' => (!empty($customer['user_avatar']) ? $nbee_backend_media_uri . '/' . $customer['user_avatar'] : ""),
                'user_gender' => $customer['user_gender'],
                'user_phonenumber' => $customer['user_phonenumber'],
                'referrer_code' => $customer['referrer_code'],
                'customer_fields' => json_encode($customer, JSON_UNESCAPED_UNICODE),
            )
        ));

        // Add mapping to table
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
}
