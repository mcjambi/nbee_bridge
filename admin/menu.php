<?php

/**
 * Register a custom menu page.
 */
function nbee_admin_menu()
{
    add_menu_page(
        __('nBee Bridge Setting', 'textdomain'),
        'nBee Bridge',
        'manage_options',
        'nbee_bridge',
        'nbee_display_main',
        'dashicons-buddicons-replies',
        // plugins_url( 'nbee_bridge/media/icon.png' ),
        2
    );

    add_submenu_page(
        'nbee_bridge',
        __('SSO login setting', 'textdomain'),
        __('SSO login', 'textdomain'),
        'manage_options',
        'nbee_sso_login',
        'nbee_display_main'
    );

    add_submenu_page(
        'nbee_bridge',
        __('User tracking setting', 'textdomain'),
        __('User tracking', 'textdomain'),
        'manage_options',
        'nbee_user_tracking',
        'nbee_display_main'
    );
    add_submenu_page(
        'nbee_bridge',
        __('Referrer', 'textdomain'),
        __('Referrer tracking', 'textdomain'),
        'manage_options',
        'nbee_referrer_tracking',
        'nbee_display_main'
    );
    add_submenu_page(
        'nbee_bridge',
        __('E-commerce Sync', 'textdomain'),
        __('E-commerce Sync', 'textdomain'),
        'manage_options',
        'nbee_ecommerce_sync',
        'nbee_display_main'
    );
}
add_action('admin_menu', 'nbee_admin_menu');


function registerCustomAdminCss()
{
    wp_enqueue_style('nbee-panel-css', NBEE_PLUGIN_PATH . 'admin/css/panel.css', __FILE__);
    wp_enqueue_style('nbee-css', NBEE_PLUGIN_PATH . 'admin/css/style.css', __FILE__);
    wp_enqueue_script('nbee-js', NBEE_PLUGIN_PATH . 'admin/css/main.js', __FILE__, array('jquery'), NBEE_PLUGIN_VERSION);
}
add_action('admin_head', 'registerCustomAdminCss');




/**
 * Add something to header ...
 */
function nbee_add_general_script_to_header()
{
    $backend_url = get_option("nbee_backend_crm_uri");
    echo "
        <script type='text/javascript'>
            const backend_root_url = '$backend_url';
            const backend_url = '$backend_url/activity/web_activity';
        </script>   
    ";
}

add_action("wp_head", "nbee_add_general_script_to_header", 1);
