<?php 
/*
 * Plugin Name:       nBee Bridge
 * Plugin URI:        https://nbee_bridge.jamviet.com/
 * Description:       helping you connect Wordpress website to nBee CRM, include basic function like: SSO sign-in, abandone-cart ...
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            mcjambi
 * Author URI:        https://jamviet.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://nbee_bridge.jamviet.com/update/
 * Text Domain:       nbee_bridge
 * Domain Path:       /languages
 */

    define("NBEE_PLUGIN_VERSION", '1.0.0');
    define('LOGGED_IN_KEY', 'jhgUYFUhg87658');


 include ( __DIR__ . "/formsaving.php");
 include ( __DIR__ . "/display.php");
 include ( __DIR__ . "/library/sso.php");
 include ( __DIR__ . "/library/user.tracking.php");
 include ( __DIR__ . "/library/referrer.php");
 
/**
 * Register a custom menu page.
 */
function nbee_admin_menu() {
	add_menu_page(
		__( 'nBee Bridge Setting', 'textdomain' ),
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
        __( 'SSO login setting', 'textdomain' ),
        __( 'SSO login', 'textdomain' ),
        'manage_options',
        'nbee_sso_login',
        'nbee_display_main'
    );

    add_submenu_page(
        'nbee_bridge',
        __( 'User tracking setting', 'textdomain' ),
        __( 'User tracking', 'textdomain' ),
        'manage_options',
        'nbee_user_tracking',
        'nbee_display_main'
    );
    add_submenu_page(
        'nbee_bridge',
        __( 'Referrer', 'textdomain' ),
        __( 'Referrer tracking', 'textdomain' ),
        'manage_options',
        'nbee_referrer_tracking',
        'nbee_display_main'
    );
}
add_action( 'admin_menu', 'nbee_admin_menu' );


function registerCustomAdminCss() {
    wp_enqueue_style('nbee-panel-css', plugins_url('media/panel.css', __FILE__) );
    wp_enqueue_style('nbee-css', plugins_url('media/style.css', __FILE__) );
    wp_enqueue_script('nbee-js', plugins_url('media/main.js', __FILE__), array('jquery'), NBEE_PLUGIN_VERSION );
}
add_action('admin_head', 'registerCustomAdminCss');




/**
 * Add something to header ...
 */
function nbee_add_general_script_to_header() {
    $backend_url = get_option("nbee_backend_crm_uri");
    echo "
        <script type='text/javascript'>
            const backend_root_url = '$backend_url';
            const backend_url = '$backend_url/activity/web_activity';
        </script>   
    ";
}

add_action("wp_head", "nbee_add_general_script_to_header", 1);
