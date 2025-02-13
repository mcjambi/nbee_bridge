<?php
function nbee_sync_product()
{
    $page = 1;
    $limit = 10;
    $is_more_data = true; // Biến kiểm tra có còn dữ liệu hay không
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');

    while ($is_more_data) {

        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/sync/products?page=' . $page . '&limit=' . $limit . '&sort=createdAt:asc',
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

        $products = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($products)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($products as $product) {
            nbee_handle_product($product);
        }

        // Tăng số trang lên 1 để lấy dữ liệu tiếp theo
        $page++;
    }
}

function nbee_sync_single_product($product_id)
{
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');

    $response = wp_remote_get(
        $nbee_backend_crm_uri . '/sync/product/' . $product_id,
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

    $product = json_decode(wp_remote_retrieve_body($response), true);

    if ($product) {
        nbee_handle_product($product);
    }
}

function nbee_handle_product($product)
{
    global $wpdb; // Kết nối database WordPress
    $tbl_id_mapping = $wpdb->prefix . 'nbee_id_mapping'; // Bảng ánh xạ ID
    $nbee_backend_media_uri = get_option('nbee_backend_media_uri');

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
    if (isset($product['product_thumbnail_to_media'])) {
        $product_thumbnail_url = isset($product['product_thumbnail_to_media']['media_thumbnail']['scale-512'])
            ? $nbee_backend_media_uri . '/' . $product['product_thumbnail_to_media']['media_thumbnail']['scale-512']
            : $nbee_backend_media_uri . '/' . $product['product_thumbnail_to_media']['media_url'];

        if (!empty($product_thumbnail_url) && filter_var($product_thumbnail_url, FILTER_VALIDATE_URL)) {
            $media_id = upload_image_to_media_library($product_thumbnail_url);
            $wc_product->set_image_id($media_id);
        }
    }

    // Cập nhật các thuộc tính cơ bản của sản phẩm
    $wc_product->set_name($product['product_name']);
    $wc_product->set_description($product['product_description']);
    $wc_product->set_short_description($product['product_excerpt']);
    $wc_product->set_status($product['product_status'] == 1 ? 'publish' : 'draft');
    $wc_product->set_regular_price(!empty($product['product_original_price']) ? $product['product_original_price'] : $product['product_price']);
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
    if (isset($product['product_variant_group'])) {
        $attributes = $wc_product->get_attributes(); // Lấy danh sách thuộc tính hiện có

        foreach ($product["product_variant_group"] as $variant_group) {
            if (!empty($variant_group['variant_group_name']) && !empty($variant_group['variant_group_value'])) {
                $attribute_name = sanitize_title($variant_group['variant_group_name']); // Slug cho thuộc tính
                $taxonomy = 'pa_' . $attribute_name; // Taxonomy
                $name_group = ucfirst($variant_group['variant_group_name']);

                // Kiểm tra nếu thuộc tính chưa tồn tại trong hệ thống, tạo mới
                if (!wc_attribute_taxonomy_id_by_name($name_group)) {
                    wc_create_attribute([
                        'slug'         => $taxonomy,
                        'name'         => $name_group,
                        'type'         => 'select',
                        'order_by'     => 'menu_order',
                        'has_archives' => false,
                    ]);
                }

                // Lấy danh sách giá trị thuộc tính từ dữ liệu đầu vào
                $new_attribute_values = array_map(function ($attr) {
                    return $attr['attribute_name'];
                }, $variant_group['product_variant_group_attribute']);

                // Nếu thuộc tính đã tồn tại, cập nhật giá trị
                if (array_key_exists($taxonomy, $attributes)) {
                    $existing_attribute = $attributes[$taxonomy];

                    // Kết hợp giá trị hiện có và giá trị mới, loại bỏ trùng lặp
                    $merged_values = array_unique(array_merge($existing_attribute->get_options(), $new_attribute_values));

                    // Cập nhật giá trị thuộc tính
                    $existing_attribute->set_options($merged_values);
                    $attributes[$taxonomy] = $existing_attribute;
                } else {
                    // Tạo mới thuộc tính nếu chưa tồn tại
                    $wc_attribute = new WC_Product_Attribute();
                    $wc_attribute->set_name($name_group);
                    $wc_attribute->set_options($new_attribute_values);
                    $wc_attribute->set_visible(true);
                    $wc_attribute->set_variation(true); // Gán thuộc tính cho variant

                    $attributes[$taxonomy] = $wc_attribute;
                }
            }
        }

        // Cập nhật lại danh sách thuộc tính cho sản phẩm
        $wc_product->set_attributes($attributes);
    }

    // Lưu sản phẩm
    $wc_product->save();
    $product_id = $wc_product->get_id(); // Lấy ID sản phẩm


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
    update_post_meta($product_id, 'product_fields', json_encode($product, JSON_UNESCAPED_UNICODE));

    // Cập nhật phân loại sản phẩm
    if (isset($product['product_variant']) && $product['product_has_variants']) {
        foreach ($product['product_variant'] as $variant) {
            if (empty($variant['variant_name'])) {
                continue; // Bỏ qua nếu không đủ dữ liệu cơ bản
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
                $wc_variant->set_parent_id($product_id); // Gán product_id cho variant
            }

            // Cập nhật thông tin của variant
            $wc_variant->set_name($variant['variant_name']);
            $wc_variant->set_slug($variant['variant_slug']);
            $wc_variant->set_sku($variant['variant_sku']);
            $wc_variant->set_description($variant['variant_excerpt']);
            $wc_variant->set_regular_price(!empty($variant['variant_original_price']) ? $variant['variant_original_price'] : $variant['variant_price']);
            $wc_variant->set_sale_price($variant['variant_price']);
            $wc_variant->set_price($variant['variant_price']);
            $wc_variant->set_stock_status('instock');
            $wc_variant->set_status($variant['variant_status'] == 1 ? 'publish' : 'draft');

            // Gán thuộc tính cho variant
            $attributes = [];
            foreach ($variant['product_variant_option'] as $option) {
                foreach ($product['product_variant_group'] as $variant_group) {
                    if ($variant_group['id'] === $option['product_variant_group_id']) {
                        foreach ($variant_group['product_variant_group_attribute'] as $attribute) {
                            if ($attribute['id'] === $option['product_variant_group_attribute_id']) {
                                $taxonomy = sanitize_title($variant_group['variant_group_name']);
                                $attributes[$taxonomy] = $attribute['attribute_name'];
                            }
                        }
                    }
                }
            }
            $wc_variant->set_attributes($attributes);


            // Cập nhật thông tin thumbnail cho variant
            if (isset($variant['variant_thumbnail_to_media'])) {
                $variant_thumbnail_url = isset($variant['variant_thumbnail_to_media']['media_thumbnail']['scale-512'])
                    ? $nbee_backend_media_uri . '/' . $variant['variant_thumbnail_to_media']['media_thumbnail']['scale-512']
                    : $nbee_backend_media_uri . '/' . $variant['variant_thumbnail_to_media']['media_url'];

                if (!empty($variant_thumbnail_url) && filter_var($variant_thumbnail_url, FILTER_VALIDATE_URL)) {
                    $media_id = upload_image_to_media_library($variant_thumbnail_url);
                    $wc_variant->set_image_id($media_id);
                }
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
            update_post_meta($variant_id, 'product_variant_fields', json_encode($variant, JSON_UNESCAPED_UNICODE));
        }
    }

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

    // Set product collections
    if (!empty($product['product_to_collection'])) {
        $collection_ids = array();
        foreach ($product['product_to_collection'] as $collection) {
            $term = get_term_by('slug', $collection['product_collection']['collection_slug'], 'product_collection');
            if ($term) {
                $collection_ids[] = $term->term_id;
            }
        }
        wp_set_object_terms($product_id, $collection_ids, 'product_collection');
    }

    // Set product brand
    if (!empty($product['product_to_brand'])) {
        $brand_term = get_term_by('slug', $product['product_to_brand']['product_brand']['brand_slug'], 'product_brand');
        if ($brand_term) {
            wp_set_object_terms($product_id, $brand_term->term_id, 'product_brand');
        }
    }
}
