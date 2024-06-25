<?php

/**
 * SSO function
 * $selected_page = get_option( 'nbee_sso_page' );
 */

 /**
  * Config CURL ...
  */
 function my_http_api_curl($handle) {
    curl_setopt( $handle, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1 ); 
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0); // Skip SSL Verification
 }
    
 add_action('http_api_curl', 'my_http_api_curl');



 add_action( 'pre_get_posts', 'nbee_check_post_if_in_sso_page' );
 function nbee_check_post_if_in_sso_page($query) {
    $auth_callback = isset($_GET['auth_callback']) ? true : false;
    $oauth_access_token = isset($_GET['oauth_access_token']) ? sanitize_text_field($_GET['oauth_access_token']) : false;

    /**
     * if i have a token ...
     */
    if ($auth_callback && $oauth_access_token) {
        $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
        $nbee_client_public_key = get_option('nbee_client_public_key');
        $response = wp_remote_get(
            esc_url_raw( $nbee_backend_crm_uri . '/user' ),
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Authorization' => $oauth_access_token,
                    'referer' => home_url(),
                    'X-Client-Key' => $nbee_client_public_key
                )
            )
        );
        try {
            $body = wp_remote_retrieve_body( $response );
            $userObject = @json_decode($body);
            /**
             * user_id
             * user_role
             * user_login
             * user_email
             * display_name
             * bio
             * user_status
             * user_numberphone
             * user_avatar
             */
            /**
             * Check if user is exist 
             */
            $checkUser = get_user_by('email', $userObject->user_email );
            $password = ( $userObject->user_email . LOGGED_IN_KEY );
            $user_login = sanitize_user( $userObject->user_email );
            if ( ! $checkUser ) {
                $checkUser = wp_insert_user( array(
                    'user_email' => $userObject->user_email,
                    'user_login' => $user_login,
                    'user_pass' => $password,
                    'display_name' => $userObject->display_name,
                    'role' => $userObject->user_role || 'user',
                    'description' => $userObject->bio,
                ));
            } else {
                /**
                 * Khi hai bên đều tồn tại một tài khoản, thì cần phải reset mật khẩu để có thể đăng nhập tự động...
                 */
                @wp_set_password( $password, $checkUser->ID );
            }

            $checkUser = get_user_by('email', $userObject->user_email );
            $user = wp_signon( array(
                'user_login'    => $checkUser->user_login,
                'user_password' => $password,
                'remember'      => true,
            ) );

            if ( is_wp_error( $user ) ) {
                echo $user->get_error_message();
                die();
            } else {
                wp_redirect( home_url() . '#sso_login_success' );
                die();
            }


        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    // normal redirect to SSO ...
    if ( $query->is_page && ! is_admin() && $auth_callback == false ) {
        $selected_page = get_option( 'nbee_sso_page' );
        if( $query->get_queried_object_id() == $selected_page ) {
            $nbee_frontend_crm_uri = get_option('nbee_frontend_crm_uri');
            $referrer = '';
            $status = get_option("nbee_referrer_tracking_status");
            if ( $status == "1") {
                $referrer = isset($_COOKIE['user_referrer']) ? sanitize_text_field($_COOKIE['user_referrer']) : '';
            }
            $url = sprintf('%s/login/sso?user_referrer=%s&redirect_to=%s', $nbee_frontend_crm_uri, $referrer, get_permalink( $query->get_queried_object_id()) . '?auth_callback=true' );
            wp_redirect( $url );
            exit;
        }
    }
 }



 /**
  * Avoid display something strange, remove content, display loading text only ...
  */
 add_filter('the_content','nbee_check_post_if_in_sso_page_content');
 function nbee_check_post_if_in_sso_page_content( $content ) {
    global $post;
    $selected_page = get_option( 'nbee_sso_page' );
    if ( $post->ID == $selected_page) {
        return 'LOADING...';
    }
    return $content;
 }





function nbee_sso_enqueue_styles() {
    wp_enqueue_script( 'nbee-sso-login', plugins_url('nbee_bridge/media') . '/sso.js', array(), NBEE_PLUGIN_VERSION );
}
add_action('wp_enqueue_scripts', 'nbee_sso_enqueue_styles');

