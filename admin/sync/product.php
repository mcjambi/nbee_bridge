<?php
function nbee_sync_product()
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

        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/product/admin?page=' . $page . '&limit=' . $limit . '&sort=createdAt:asc',
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

        $products = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($products)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($products as $product) {

            if (empty($product['product_slug']) || empty($product['product_name'])) {
                continue; // Bỏ qua nếu không đủ dữ liệu cơ bản
            }

            // Insert or update product in WooCommerce
            // Lấy `wp_id` từ bảng ánh xạ
            $wp_id = $wpdb->get_var($wpdb->prepare(
                "SELECT wp_id FROM $tbl_id_mapping WHERE nbee_id = %s AND type = 'product'",
                $product['product_id']
            ));



            if ($wp_id) {
                $wc_product = wc_get_product($wp_id); // Lấy đối tượng sản phẩm

                if ($wc_product->get_type() === 'simple' && $product['product_has_variants']) {
                    $wc_product = new WC_Product_Variable($wc_product->get_id());
                }
            } else {
                $wc_product = $product['product_has_variants'] ? new WC_Product_Variable() : new WC_Product_Simple();
                $wc_product->set_slug($product['product_slug']);
            }

            // Cập nhật thumbnail với link hình ảnh phù hợp
            $product_thumbnail_url = isset($product['product_thumbnail_to_media']['media_thumbnail']['scale-512'])
                ? $nbee_backend_media_uri . '/' . $product['product_thumbnail_to_media']['media_thumbnail']['scale-512']
                : $nbee_backend_media_uri . '/' . $product['product_thumbnail_to_media']['media_url'];

            if (!empty($product_thumbnail_url) && filter_var($product_thumbnail_url, FILTER_VALIDATE_URL)) {
                $media_id = upload_image_to_media_library($product_thumbnail_url);
                $wc_product->set_image_id($media_id);
            }

            // Cập nhật các thuộc tính cơ bản của sản phẩm
            $wc_product->set_name($product['product_name']);
            $wc_product->set_description($product['product_description']);
            $wc_product->set_short_description($product['product_excerpt']);
            $wc_product->set_status($product['product_status'] == 1 ? 'publish' : 'draft');
            $wc_product->set_regular_price($product['product_original_price']);
            $wc_product->set_sale_price($product['product_price']);
            $wc_product->set_price($product['product_price']);
            $wc_product->set_sku($product['product_sku']);
            $wc_product->set_weight($product['product_size_weight']);
            $wc_product->set_length($product['product_size_length']);
            $wc_product->set_width($product['product_size_width']);
            $wc_product->set_height($product['product_size_height']);
            $wc_product->set_total_sales($product['product_meta']['product_sold_quantity']);
            $wc_product->set_stock_status('instock');

            $wc_product->set_virtual($product['product_type'] === 'service');
            $wc_product->set_manage_stock(false); // Không quản lý tồn kho


            // Cập nhật attributes nếu sản phẩm có biến thể
            if ($product['product_has_variants']) {

                $response_variant_group = wp_remote_get(
                    $nbee_backend_crm_uri . '/product_variant_group?product_id=' . $product['product_id'],
                    array(
                        'headers' => array(
                            'x-authorization' => $token
                        ),
                    )
                );
                $variant_groups = json_decode(wp_remote_retrieve_body($response_variant_group), true);
                $attributes = array();
                foreach ($variant_groups as $variant_group) {

                    if (!empty($variant_group['variant_group_name']) && !empty($variant_group['variant_group_value'])) {

                        $attribute_name = sanitize_title($variant_group['variant_group_name']); // Slug cho thuộc tính
                        $taxonomy = 'pa_' . $attribute_name; // Taxonomy
                        $attribute_values = explode(',', $variant_group['variant_group_value']); // Các giá trị của thuộc tính

                        // Kiểm tra nếu thuộc tính chưa tồn tại, tạo mới
                        if (!taxonomy_exists($taxonomy)) {
                            $args = array(
                                'slug'        => $attribute_name,
                                'name'        => ucfirst($variant_group['variant_group_name']),
                                'type'        => 'select',
                                'order_by'    => 'menu_order',
                                'has_archives' => false,
                            );
                            wc_create_attribute($args);
                        }

                        // Tạo đối tượng WC_Product_Attribute
                        $wc_attribute = new WC_Product_Attribute();
                        $wc_attribute->set_name($taxonomy);
                        $wc_attribute->set_options($attribute_values);
                        $wc_attribute->set_position(0);
                        $wc_attribute->set_visible(true);
                        $wc_attribute->set_variation(true); // Gán thuộc tính cho variant

                        $attributes[$taxonomy] = $wc_attribute;
                    }
                }

                $wc_product->set_attributes($attributes);
            }

            // Lưu sản phẩm
            $wc_product->save();
            $product_id = $wc_product->get_id(); // Lấy ID sản phẩm sau khi lưu

            if (!$wp_id) {
                // Thêm ánh xạ vào bảng mapping
                $wpdb->insert(
                    $tbl_id_mapping,
                    array(
                        'wp_id'   => $product_id,
                        'nbee_id' => $product['product_id'],
                        'type'    => 'product',
                    ),
                    array('%d', '%s', '%s')
                );
            }

            // Tách chuỗi tags thành mảng
            $product_tags = array_map('trim', explode(',', $product['product_tags']));
            $product_tags = array_filter($product_tags); // Xóa phần tử rỗng nếu có
            wp_set_object_terms($product_id, $product_tags, 'product_tag');

            unset($product['product_description'], $product['product_excerpt']);
            update_post_meta($product_id, '_product_fields', json_encode($product, JSON_UNESCAPED_UNICODE));

            // Set product categories
            if (!empty($product['product_to_category'])) {
                $category_ids = array();
                foreach ($product['product_to_category'] as $category) {
                    $term = get_term_by('slug', $category['product_category']['category_slug'], 'product_cat');
                    if ($term) {
                        $category_ids[] = $term->term_id;
                    }
                }
                wp_set_object_terms($product_id, $category_ids, 'product_cat');
            }

            // Set product brand
            if (!empty($product['product_to_brand'])) {
                $brand_term = get_term_by('slug', $product['product_to_brand']['product_brand']['brand_slug'], 'product_brand');
                if ($brand_term) {
                    wp_set_object_terms($product_id, $brand_term->term_id, 'product_brand');
                }
            }


            // Cập nhật wp_wc_product_meta_lookup
            list($min_price, $max_price) = explode('-', $product['product_price_range']);
            // Kiểm tra nếu product_price_range có dữ liệu hợp lệ trước khi tách
            if (!empty($product['product_price_range']) && strpos($product['product_price_range'], '-') !== false) {
                list($min_price, $max_price) = explode('-', $product['product_price_range']);
            } else {
                // Gán giá trị mặc định nếu không có giá hoặc giá không hợp lệ
                $min_price = $product['product_price'];
                $max_price = $product['product_price'];
            }

            global $wpdb;
            $wpdb->replace(
                $wpdb->prefix . 'wc_product_meta_lookup',
                array(
                    'product_id'     => $product_id,
                    'sku'            => $product['product_sku'],
                    'min_price'      => $min_price,
                    'max_price'      => $max_price,
                    'virtual'        => ($product['product_type'] === 'service') ? 1 : 0,
                    'onsale'         => ($product['product_price'] < $product['product_original_price']) ? 1 : 0,
                    'rating_count'   => $product['product_meta']['product_review_count'],
                    'average_rating' => $product['product_meta']['product_review_point'],
                    'total_sales'    => $product['product_meta']['product_sold_quantity'],
                    'stock_status'   => 'instock',
                ),
                array(
                    '%d',
                    '%s',
                    '%f',
                    '%f',
                    '%d',
                    '%d',
                    '%d',
                    '%f',
                    '%d',
                    '%d'
                )
            );
        }
    }
}
