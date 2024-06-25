<?php 

/**
 * Referrer
 */


 function nbee_user_referrer() {
    if ( !isset($_GET["user_referrer"]) ) return;
    $status = get_option("nbee_referrer_tracking_status");
    if ( $status != "1") {
        return;
    }
    // last_click first_click
    $referrer = sanitize_text_field(trim($_GET["user_referrer"]));
    if ( ! isset( $_COOKIE['user_referrer'] ) ) {
        return setcookie('user_referrer', $referrer, time() + 86400 * 30, '/', '', true );
    }
    $mode = get_option("nbee_referrer_tracking_mode");
    // case last_click, overwrite old one
    switch( $mode ) {
        case "last_click":
            return setcookie('user_referrer', $referrer, time() + 86400 * 30, '/', '', true );
    }
 }

 add_action("after_setup_theme","nbee_user_referrer");