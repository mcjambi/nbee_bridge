<?php
function upload_image_to_media_library($image_url)
{
    // Lấy nội dung ảnh từ URL
    $image_data = file_get_contents($image_url);
    if (!$image_data) {
        return false; // Không lấy được nội dung ảnh
    }

    // Đặt tên file từ URL
    $filename = basename($image_url);

    // Kiểm tra xem ảnh đã tồn tại trong thư viện Media chưa
    global $wpdb;
    $query = $wpdb->prepare("
        SELECT ID 
        FROM $wpdb->posts 
        WHERE post_type = 'attachment' 
        AND post_title = %s 
        LIMIT 1
    ", sanitize_file_name($filename));
    $attachment_id = $wpdb->get_var($query);

    if ($attachment_id) {
        return $attachment_id; // Trả về ID nếu ảnh đã tồn tại
    }

    // Nếu ảnh chưa tồn tại, tiến hành upload
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;

    // Ghi dữ liệu ảnh vào file tạm thời
    file_put_contents($file_path, $image_data);

    $filetype = wp_check_filetype($filename, null);

    // Tạo post attachment
    $attachment_id = wp_insert_attachment(array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ), $file_path);

    // Kiểm tra và xử lý lỗi khi không tạo được attachment
    if (is_wp_error($attachment_id) || !$attachment_id) {
        @unlink($file_path); // Xóa file nếu có lỗi
        return false;
    }

    // Tạo metadata cho attachment
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
    wp_update_attachment_metadata($attachment_id, $attach_data);

    return $attachment_id; // Trả về ID của attachment
}
