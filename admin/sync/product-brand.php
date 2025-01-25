<?php
// Initialize new taxonomy for product brand
function nbee_register_product_brand_taxonomy()
{
    $labels = array(
        'name'              => _x('Thương hiệu', 'taxonomy general name', 'textdomain'),
        'singular_name'     => _x('Thương hiệu', 'taxonomy singular name', 'textdomain'),
        'search_items'      => __('Tìm kiếm thương hiệu', 'textdomain'),
        'all_items'         => __('Tất cả thương hiệu', 'textdomain'),
        'edit_item'         => __('Chỉnh sửa thương hiệu', 'textdomain'),
        'update_item'       => __('Cập nhật thương hiệu', 'textdomain'),
        'add_new_item'      => __('Tạo thương hiệu', 'textdomain'),
        'new_item_name'     => __('Tên thương hiệu mới', 'textdomain'),
        'menu_name'         => __('Thương hiệu', 'textdomain'),
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'product_brand'),
    );

    register_taxonomy('product_brand', array('product'), $args);
}
add_action('init', 'nbee_register_product_brand_taxonomy');

// Form Thêm Brand
function add_brand_image_field()
{
?>
    <div class="form-field term-group">
        <label for="brand-thumbnail"><?php _e('Brand Thumbnail', 'textdomain'); ?></label>
        <input type="hidden" id="brand-thumbnail-id" name="brand-thumbnail-id" value="" />
        <div id="brand-thumbnail-preview"></div>
        <button class="button upload-thumbnail-button"><?php _e('Upload Thumbnail', 'textdomain'); ?></button>
        <button class="button remove-thumbnail-button hidden"><?php _e('Remove Thumbnail', 'textdomain'); ?></button>
    </div>
<?php
}
// add_action('product_brand_add_form_fields', 'add_brand_image_field', 10, 2);

/**
// Form Sửa Brand
function edit_brand_image_field($term)
{
    $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
    $thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
?>
    <tr class="form-field term-group-wrap">
        <th scope="row">
            <label for="brand-thumbnail"><?php _e('Brand Thumbnail', 'textdomain'); ?></label>
        </th>
        <td>
            <input type="hidden" id="brand-thumbnail-id" name="brand-thumbnail-id" value="<?php echo esc_attr($thumbnail_id); ?>" />
            <div id="brand-thumbnail-preview">
                <?php if ($thumbnail_url): ?>
                    <img src="<?php echo esc_url($thumbnail_url); ?>" alt="" style="max-width: 100px; height: auto;" />
                <?php endif; ?>
            </div>
            <button class="button upload-thumbnail-button"><?php _e('Upload Thumbnail', 'textdomain'); ?></button>
            <button class="button remove-thumbnail-button <?php echo $thumbnail_id ? '' : 'hidden'; ?>"><?php _e('Remove Thumbnail', 'textdomain'); ?></button>
        </td>
    </tr>
<?php
}
add_action('product_brand_edit_form_fields', 'edit_brand_image_field', 10, 2);

// Lưu Attachment ID vào term_meta
function save_brand_thumbnail($term_id)
{
    if (isset($_POST['brand-thumbnail-id'])) {
        $thumbnail_id = intval($_POST['brand-thumbnail-id']);
        update_term_meta($term_id, 'thumbnail_id', $thumbnail_id);
    } else {
        delete_term_meta($term_id, 'thumbnail_id');
    }
}
add_action('create_product_brand', 'save_brand_thumbnail', 10, 2);
add_action('edited_product_brand', 'save_brand_thumbnail', 10, 2);


// Hiển thị Thumbnail trong Danh sách Brand
function add_brand_thumbnail_column($columns)
{
    $columns['brand_thumbnail'] = __('Thumbnail', 'textdomain');
    return $columns;
}
add_filter('manage_edit-product_brand_columns', 'add_brand_thumbnail_column');

function display_brand_thumbnail_column($content, $column_name, $term_id)
{
    if ('brand_thumbnail' === $column_name) {
        $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
        if ($thumbnail_id) {
            $thumbnail_url = wp_get_attachment_url($thumbnail_id);
            $content = '<img src="' . esc_url($thumbnail_url) . '" alt="" style="max-width: 50px; height: auto;" />';
        } else {
            $content = __('No Thumbnail', 'textdomain');
        }
    }
    return $content;
}
add_filter('manage_product_brand_custom_column', 'display_brand_thumbnail_column', 10, 3);
 */


// Sync brand về
function nbee_sync_product_brand()
{
    $page = 1; // Start from page 1
    $limit = 100; // Number of brands per sync
    $is_more_data = true; // Check if there is more data
    $nbee_backend_media_uri = get_option('nbee_backend_media_uri');
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;

    while ($is_more_data && $token) {
        // Send request to API with pagination parameters
        $response = wp_remote_get(
            $nbee_backend_crm_uri . '/product_brand/admin?page=' . $page . '&limit=' . $limit . '&sort=brand_order:asc',
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

        $brands = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($brands)) {
            $is_more_data = false; // No more data, stop
            continue;
        }

        foreach ($brands as $brand) {
            // Check if brand already exists
            $existing_term = get_term_by('slug', $brand['brand_slug'], 'product_brand');

            if ($existing_term) {
                // If brand exists, update information
                wp_update_term(
                    $existing_term->term_id,
                    'product_brand',
                    array(
                        'name'        => $brand['brand_name'],
                        'description' => $brand['brand_description'],
                        'slug'        => $brand['brand_slug'],
                    )
                );
                $term_id = $existing_term->term_id;
            } else {
                // If brand does not exist, create new
                $term = wp_insert_term(
                    $brand['brand_name'],
                    'product_brand',
                    array(
                        'description' => $brand['brand_description'],
                        'slug'        => $brand['brand_slug'],
                    )
                );

                if (is_wp_error($term)) {
                    // If error occurs while creating brand, continue loop
                    continue;
                }
                $term_id = $term['term_id'];
            }

            // Update other metadata
            update_term_meta($term_id, 'order', $brand['brand_order']);
            update_term_meta($term_id, 'status', $brand['brand_status']);
            update_term_meta($term_id, 'createdAt', $brand['createdAt']);
            update_term_meta($term_id, 'product_brand_fields', json_encode($brand, JSON_UNESCAPED_UNICODE));

            // Cập nhật thumbnail với link hình ảnh phù hợp
            $brand_thumbnail_url = isset($brand['product_brand_to_media']['media_thumbnail']['scale-512'])
                ? $nbee_backend_media_uri . '/' . $brand['product_brand_to_media']['media_thumbnail']['scale-512']
                : $nbee_backend_media_uri . '/' . $brand['product_brand_to_media']['media_url'];

            if (!empty($brand_thumbnail_url) && filter_var($brand_thumbnail_url, FILTER_VALIDATE_URL)) {
                $media_id = upload_image_to_media_library($brand_thumbnail_url);
                update_term_meta($term_id, 'thumbnail_id', $media_id);
            }
        }

        // Increase page number to get next data
        $page++;
    }
}
