<?php
class NBee_Bridge
{
    public static function activate()
    {
        // Logic khi plugin được kích hoạt
    }

    public static function deactivate()
    {
        // Logic khi plugin bị vô hiệu hóa
    }

    public static function init()
    {
        require_once NBEE_PLUGIN_PATH . 'includes/helpers/helper.php';

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
