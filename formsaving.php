<?php 

add_action( 'admin_post_nbee_general_setting', 'save_nbee_general_setting' );
add_action( 'admin_post_nbee_sso_setting', 'save_nbee_sso_setting' );
add_action( 'admin_post_nbee_user_tracking', 'save_nbee_user_tracking' );
add_action( 'admin_post_nbee_referrer_tracking', 'save_nbee_referrer_tracking' );

function save_nbee_general_setting() {
    // Handle request then generate response using echo or leaving PHP and using HTML
    check_admin_referer( 'nbee_general_setting' );
    
    if ( isset( $_POST['nbee_backend_crm_uri'] ) && wp_http_validate_url($_POST['nbee_backend_crm_uri']) ) {
        update_option('nbee_backend_crm_uri', sanitize_text_field( $_POST['nbee_backend_crm_uri'] ) );
    }

    if ( isset( $_POST['nbee_frontend_crm_uri'] ) && wp_http_validate_url($_POST['nbee_frontend_crm_uri']) ) {
        update_option('nbee_frontend_crm_uri', sanitize_text_field( $_POST['nbee_frontend_crm_uri'] ) );
    }

    if ( isset( $_POST['nbee_client_key'] ) && sanitize_title($_POST['nbee_client_key']) !== '' ) {
        update_option('nbee_client_key', strtoupper( sanitize_title( trim($_POST['nbee_client_key']) ) ) );
    }

    wp_redirect( admin_url('admin.php?page=nbee_bridge') );
}


function save_nbee_sso_setting() {
    check_admin_referer( 'nbee_sso_setting' );
    if ( isset( $_POST['nbee_sso_page'] ) ) {
        update_option('nbee_sso_page', sanitize_text_field( $_POST['nbee_sso_page'] ) );
    }
    wp_redirect( admin_url('admin.php?page=nbee_sso_login') );
}

function save_nbee_user_tracking() {
    check_admin_referer( 'nbee_user_tracking' );
    $turn_nbee_user_tracking_status_on = 0;
    $turn_nbee_google_analytics_status_on = 0;

    if ( isset( $_POST['nbee_user_tracking_status'] ) ) {
        $turn_nbee_user_tracking_status_on = 1;
    }

    if ( isset( $_POST['nbee_google_tracking_status'] ) ) {
        $turn_nbee_google_analytics_status_on = 1;
    }

    if ( isset( $_POST['nbee_google_analytics_key'] ) ) {
        $nbee_google_analytics_key = sanitize_text_field( $_POST['nbee_google_analytics_key'] );
        update_option('nbee_google_analytics_key', $nbee_google_analytics_key );
    }


    update_option('nbee_user_tracking_status', $turn_nbee_user_tracking_status_on );
    update_option('nbee_google_tracking_status', $turn_nbee_google_analytics_status_on );
    wp_redirect( admin_url('admin.php?page=nbee_user_tracking') );
}

function save_nbee_referrer_tracking() {
    check_admin_referer( 'nbee_referrer_tracking' );
    $turn_on = 0;
    if ( isset( $_POST['nbee_referrer_tracking'] ) ) {
        $turn_on = 1;
    }
    update_option('nbee_referrer_tracking_status', $turn_on );
    if ( isset( $_POST['nbee_referrer_tracking_mode'] ) ) {
        update_option('nbee_referrer_tracking_mode', sanitize_text_field( $_POST['nbee_referrer_tracking_mode'] ) );
    }
    wp_redirect( admin_url('admin.php?page=nbee_referrer_tracking') );
}



/**
 * On uninstall
 */
function nbee_on_uninstall() {
    delete_option('nbee_backend_crm_uri');
    delete_option('nbee_frontend_crm_uri');
    delete_option('nbee_sso_page');
    delete_option('nbee_client_key');
    delete_option('nbee_user_tracking_status');
    delete_option('nbee_referrer_tracking_status');
}
register_uninstall_hook(__DIR__, 'nbee_on_uninstall');