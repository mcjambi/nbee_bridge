<?php 


/**
 * user tracking ...
 */
function nbee_add_user_referrer_to_header() {
    $user_email = '';
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
    }
    echo "
        <script type='text/javascript'>
            const user_referrer = '$user_email';
        </script>   
    ";
}

add_action("wp_head", "nbee_add_user_referrer_to_header", 1);


function nbee_add_tracking_to_header() {
    $nbee_user_tracking_status = get_option("nbee_user_tracking_status", 0);
    if ( $nbee_user_tracking_status != "0") {
        wp_enqueue_script('nbee-passport', plugins_url('nbee_bridge/media/passport.js', ''), array('jquery'), NBEE_PLUGIN_VERSION );
        wp_enqueue_script('nbee-requestee', plugins_url('nbee_bridge/media/requestee.js', ''), array('nbee-passport'), NBEE_PLUGIN_VERSION );        
    }

    /**
     * Gooogle Analytics config
     */
    $nbee_google_tracking_status = get_option("nbee_google_tracking_status", 0);
    if ( $nbee_google_tracking_status ) {
        $nbee_google_analytics_key = get_option("nbee_google_analytics_key", '');
        if ( $nbee_google_analytics_key ) {
            printf("
            <!-- Google tag (gtag.js) -->
            <script async src='https://www.googletagmanager.com/gtag/js?id=%s'></script>  
            <script type='text/javascript'>
            window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '%s');
            </script>
            ", $nbee_google_analytics_key,  $nbee_google_analytics_key );    
        }
    }

}
 
 add_action("wp_head","nbee_add_tracking_to_header", 99);