<?php
class NBee_Bridge
{
    public static function activate()
    {
        // Logic khi plugin được kích hoạt

        global $wpdb;

        // Tạo bảng lưu trữ id mapping giữa 2 hệ thống
        $table_name = $wpdb->prefix . 'nbee_id_mapping';
        $charset_collate = $wpdb->get_charset_collate();

        // Câu lệnh SQL để tạo bảng.
        $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        wp_id BIGINT(20) NOT NULL,
        nbee_id VARCHAR(255) NOT NULL,
        type ENUM('product', 'category', 'brand', 'collection', 'customer', 'variant', 'voucher', 'order') NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY unique_wp_nbee (wp_id, nbee_id, type)
    ) $charset_collate;";

        // Gọi hàm dbDelta để thực thi SQL.
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function deactivate()
    {
        // Logic khi plugin bị vô hiệu hóa
    }

    public static function init()
    {
        require_once NBEE_PLUGIN_PATH . 'includes/helpers/helper.php';
        require_once NBEE_PLUGIN_PATH . 'includes/constants/constants.php';

        // Tải các tệp admin và public
        if (is_admin()) {
            require_once NBEE_PLUGIN_PATH . 'admin/admin.php';
        } else {
            require_once NBEE_PLUGIN_PATH . 'public/public.php';
        }

        // Tải textdomain
        add_action('init', array(__CLASS__, 'load_textdomain'));
    }

    public static function load_textdomain()
    {
        load_plugin_textdomain('nbee-bridge', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}
