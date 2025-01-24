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


if (! defined('ABSPATH')) {
    exit;
}

// Định nghĩa hằng số plugin
define("NBEE_PLUGIN_VERSION", '1.0.0');
define('NBEE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NBEE_PLUGIN_URL', plugin_dir_url(__FILE__));


if (! defined('LOGGED_IN_KEY')) {
    define('LOGGED_IN_KEY', 'jhgUYFUhg87658');
}


// Bao gồm các tệp chính
require_once NBEE_PLUGIN_PATH . 'includes/includes.php';

// Kích hoạt plugin
register_activation_hook(__FILE__, array('NBee_Bridge', 'activate'));

// Vô hiệu hóa plugin
register_deactivation_hook(__FILE__, array('NBee_Bridge', 'deactivate'));

// Khởi tạo plugin
add_action('plugins_loaded', array('NBee_Bridge', 'init'));
