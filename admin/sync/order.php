<?php
function nbee_sync_orders()
{
    $page = 1;
    $limit = 10;
    $is_more_data = true;
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');

    while ($is_more_data) {
        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/sync/orders?page=' . $page . '&limit=' . $limit,
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

        $orders = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($orders)) {
            $is_more_data = false;
            continue;
        }

        foreach ($orders as $order) {
            nbee_handle_order($order);
        }

        $page++;
    }
}

function nbee_sync_single_order($order_id)
{
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');

    $response = wp_remote_get(
        $nbee_backend_crm_uri . '/sync/order/' . $order_id,
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

    $order = json_decode(wp_remote_retrieve_body($response), true);

    if ($order) {
        nbee_handle_order($order);
    }
}

function nbee_handle_order($order)
{
    global $wpdb; // Kết nối database WordPress
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping'; // Bảng ánh xạ ID

    $wp_order_id = $wpdb->get_var($wpdb->prepare(
        "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'order'",
        $order['order_id']
    ));

    if ($wp_order_id) {
        $wc_order = wc_get_order($wp_order_id);
    } else {
        $wc_order = wc_create_order();
        $wc_order->set_order_key($order['order_pnr']);
    }

    // Lấy user_id từ bảng ánh xạ
    $customer_id = $wpdb->get_var($wpdb->prepare(
        "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'customer'",
        $order['user_id']
    ));

    if (empty($customer_id)) {
        return;
    }

    $wc_order->set_customer_id($customer_id);

    // Ánh xạ trạng thái đơn hàng
    $order_status_mapping = array(
        '9' => 'pending',
        '10' => 'processing',
        '11' => 'on-hold',
        '12' => 'on-hold',
        '13' => 'on-hold',
        '14' => 'completed',
        '15' => 'refunded',
        '19' => 'cancelled',
        '20' => 'cancelled',
    );
    $order_status = isset($order_status_mapping[$order['order_status']])
        ? $order_status_mapping[$order['order_status']]
        : 'pending';

    $wc_order->set_status($order_status);

    // Ánh xạ trạng thái thanh toán
    $payment_status_mapping = array(
        '0' => 'pending',
        '1' => 'on-hold',
        '2' => 'completed',
        '3' => 'processing',
        '4' => 'refunded',
    );
    $payment_status = isset($payment_status_mapping[$order['payment_status']])
        ? $payment_status_mapping[$order['payment_status']]
        : 'pending';

    $wc_order->update_meta_data('_payment_status', $payment_status);

    // Ánh xạ loại thanh toán
    $payment_method_mapping = array(
        'cod' => 'cod',
        'bank' => 'bacs',
        'cash' => 'cheque',
    );

    $payment_method = isset($payment_method_mapping[$order['payment_type']])
        ? $payment_method_mapping[$order['payment_type']]
        : 'cod';

    $wc_order->set_payment_method($payment_method);

    $payment_method_titles = array(
        'cod' => 'Thanh toán khi nhận hàng (COD)',
        'bank' => 'Chuyển khoản',
        'cash' => 'Tiền mặt',
    );
    $payment_method_title = isset($payment_method_titles[$order['payment_type']])
        ? $payment_method_titles[$order['payment_type']]
        : 'Thanh toán khi nhận hàng (COD)';

    $wc_order->set_payment_method_title($payment_method_title);
    $wc_order->set_currency('VND');
    $wc_order->set_created_via('nbee_sync');
    $wc_order->set_date_created(gmdate('Y-m-d H:i:s', intval($order['createdAt'] / 1000)));
    $wc_order->set_date_modified(gmdate('Y-m-d H:i:s', intval($order['updatedAt'] / 1000)));

    // Cập nhật meta data vào đơn hàng
    $wc_order->update_meta_data('_order_total_price', $order['order_total_price']);
    $wc_order->update_meta_data('_order_total_original_price', $order['order_total_original_price']);
    $wc_order->update_meta_data('_order_total_fee', $order['order_total_fee']);
    $wc_order->update_meta_data('_order_total_paid', $order['order_total_paid']);
    $wc_order->update_meta_data('_order_total_mustpay', $order['order_total_mustpay']);

    if (!empty($order['order_note'])) {
        $wc_order->set_customer_note($order['order_note']);
    }


    // Thêm sản phẩm vào đơn hàng (tránh trùng lặp)
    /** @var WC_Order_Item_Product[] $existing_items */
    $existing_items = $wc_order->get_items('line_item');

    foreach ($order['order_product'] as $product) {
        // Lấy product_id từ bảng ánh xạ
        $product_id = null;
        $is_variant = false;

        if (!empty($product['variant_id'])) {
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'variant'",
                $product['variant_id']
            ));
            $is_variant = true;
        }

        if (empty($product_id)) {
            $is_variant = false;
            $product_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'product'",
                $product['product_id']
            ));
        }

        if (empty($product_id)) {
            continue;
        }

        // Kiểm tra nếu sản phẩm đã tồn tại trong đơn hàng
        $exists = false;
        foreach ($existing_items as $item) {
            error_log($order['order_id']);
            error_log($wp_order_id);
            error_log($product_id);
            error_log($item->get_product_id());
            if ($item->get_product_id() == $product_id || ($is_variant && $item->get_variation_id() == $product_id)) {
                $item->set_quantity($product['quantity']);
                $item->set_subtotal((!empty($product['original_price']) ? $product['original_price'] : $product['price']) * $product['quantity']);
                $item->set_total($product['price'] * $product['quantity']);
                $item->save();
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            error_log("------");
            error_log($product_id);
            $wc_product = wc_get_product($product_id);
            error_log(json_encode($wc_product));
            error_log(json_encode($wc_product->get_id()));
            if ($wc_product) {
                $wc_order->add_product(
                    $wc_product,
                    $product['quantity'],
                    array(
                        'subtotal' => (!empty($product['original_price']) ? $product['original_price'] : $product['price']) * $product['quantity'],
                        'total' => $product['price'] * $product['quantity']
                    )
                );
            } else {
                error_log("Product ID {$product['product_id']} không tồn tại.");
            }
        }
    }


    // Thêm và cập nhật chi phí bổ sung
    if (!empty($order['order_fee'])) {

        /** @var WC_Order_Item_Fee[] $existing_fees */
        $existing_fees = $wc_order->get_items('fee');

        // Xử lý phí vận chuyển (SHIPPING_CHARGES)
        /** @var WC_Order_Item_Shipping[] $existing_shipping_items */
        $existing_shipping_items = $wc_order->get_items('shipping');
        $has_shipping_charge = false;

        foreach ($order['order_fee'] as $fee) {
            if ($fee['order_fee_name'] === 'SHIPPING_CHARGES') {
                $wc_order->set_shipping_total($fee['order_fee_value']);
                foreach ($existing_shipping_items as $item) {
                    if ($item->get_name() === 'Shipping Charges') {
                        // Cập nhật phí vận chuyển nếu đã tồn tại
                        $item->set_total($fee['order_fee_value']);
                        $item->save();
                        $has_shipping_charge = true;
                        break;
                    }
                }

                if (!$has_shipping_charge) {
                    // Thêm phí vận chuyển nếu chưa tồn tại
                    $shipping = new WC_Order_Item_Shipping();
                    $shipping->set_name('Shipping Charges');
                    $shipping->set_method_title('Shipping Charges');
                    $shipping->set_total($fee['order_fee_value']);
                    $wc_order->add_item($shipping);
                }
            } else {
                // Xử lý các phí bổ sung khác
                $fee_found = false;

                foreach ($existing_fees as $item_fee) {
                    if ($item_fee->get_name() === $fee['order_fee_name']) {
                        // Cập nhật phí bổ sung nếu đã tồn tại
                        $item_fee->set_amount($fee['order_fee_value']);
                        $item_fee->save();
                        $fee_found = true;
                        break;
                    }
                }

                if (!$fee_found) {
                    // Thêm phí bổ sung nếu chưa tồn tại
                    $new_item_fee = new WC_Order_Item_Fee();
                    $new_item_fee->set_name($fee['order_fee_name']);
                    $new_item_fee->set_amount($fee['order_fee_value']);
                    $new_item_fee->set_tax_class('');
                    $wc_order->add_item($new_item_fee);
                }
            }
        }
    }


    // Thêm mã giảm giá (tránh áp dụng trùng lặp)
    if (!empty($order['order_to_voucher'])) {
        // Lấy danh sách các mã giảm giá đã được áp dụng
        $applied_coupons = $wc_order->get_coupon_codes();

        foreach ($order['order_to_voucher'] as $voucher) {
            if (!in_array($voucher['voucher_code'], $applied_coupons)) {
                // Áp dụng mã giảm giá nếu chưa được sử dụng
                $wc_order->apply_coupon($voucher['voucher_code']);
            }
        }
    }

    global $city_data, $ward_data;

    // Thêm thông tin vận chuyển
    if (!empty($order['order_fulfillment'])) {
        $fulfillment = $order['order_fulfillment'];
        $address = isset($ward_data[$fulfillment['receiver_address_ward']]) ? $ward_data[$fulfillment['receiver_address_ward']]['path'] : '';
        $city_name = isset($city_data[$fulfillment['receiver_address_city']]) ? $city_data[$fulfillment['receiver_address_city']]['name'] : '';

        $wc_order->set_shipping_first_name($fulfillment['receiver_fullname']);
        $wc_order->set_shipping_phone($fulfillment['receiver_phonenumber']);
        $wc_order->set_shipping_address_1($address);
        $wc_order->set_shipping_address_2($fulfillment['receiver_address']);
        $wc_order->set_shipping_city($city_name);
        $wc_order->set_shipping_country("VN");
    }

    // $wc_order->calculate_totals();

    $wc_order->update_meta_data('_order_total_price', $order['order_total_price']);
    $wc_order->update_meta_data('_order_total_original_price', $order['order_total_original_price']);
    $wc_order->update_meta_data('_order_total_fee', $order['order_total_fee']);
    $wc_order->update_meta_data('_order_total_paid', $order['order_total_paid']);
    $wc_order->update_meta_data('_order_total_mustpay', $order['order_total_mustpay']);

    // Update order totals
    $wc_order->set_discount_total(($order['order_total_original_price'] + $order['order_total_fee']) - $order['order_total_mustpay']);
    $wc_order->set_total($order['order_total_mustpay']);
    $wc_order->save();

    // Insert new order ID mapping if not exists

    if (!$wp_order_id) {
        $wpdb->insert(
            $tbl_id_mapping,
            array(
                'wp_id' => $wc_order->get_id(),
                'nbee_id' => $order['order_id'],
                'type' => 'order',
            ),
            array('%d', '%s', '%s')
        );
    }

    update_post_meta($wc_order->get_id(), 'order_fields', json_encode($order, JSON_UNESCAPED_UNICODE));
}
