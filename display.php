<?php

/**
 * Admin of nBee
 */

 function nbee_admin_tab() {
    $admin_page = isset($_GET['page']) ? $_GET['page'] : '';

    ?>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo admin_url('admin.php?page=nbee_bridge') ?>" class="nav-tab <?php echo $admin_page === 'nbee_bridge' ? 'nav-tab-active' : 'nav-tab' ?>">
                <?php _e('General setting') ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=nbee_sso_login') ?>" class="nav-tab <?php echo $admin_page === 'nbee_sso_login' ? 'nav-tab-active' : 'nav-tab' ?>">
                <?php _e('SSO setting') ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=nbee_user_tracking') ?>" class="nav-tab <?php echo $admin_page === 'nbee_user_tracking' ? 'nav-tab-active' : 'nav-tab' ?>">
                <?php _e('User Tracking') ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=nbee_referrer_tracking') ?>" class="nav-tab <?php echo $admin_page === 'nbee_referrer_tracking' ? 'nav-tab-active' : 'nav-tab' ?>">
                <?php _e('Referrer Tracking') ?>
            </a>
        </h2>
    <?php
 }
 

 function nbee_display_main() {
    $admin_page = isset($_GET['page']) ? $_GET['page'] : '';
    ?>
        <div class="flatsome-panel">
            <div class="wrap about-wrap " id="nbee_wrap">
                <h1 class="wp-heading-inline"><?php _e( 'nBee setting' ); ?></h1>
                <div class="about-text">
                    <?php _e('Thanks for Choosing nBee - The worlds most powerful CRM. This page will help you quickly get up and running with nBee.') ?>
                    <br><br>
                </div>
                <hr class="wp-header-end" />
                <?php nbee_admin_tab() ?>
                <div id="tab-activate" class="col cols panel flatsome-panel">
                        <div class="inner-panel">
                            <?php 

                            switch( $admin_page ) {
                                case 'nbee_bridge':
                                    nbee_nbee_bridge();
                                break;
                                case 'nbee_sso_login':
                                    nbee_sso_login();
                                break;
                                case 'nbee_user_tracking':
                                    nbee_user_tracking();
                                break;
                                case 'nbee_referrer_tracking':
                                    nbee_referrer_tracking();
                                break;
                            }

                            ?>
                        </div>
                </div>
            </div>
        </div>
    <?php
 }


 function nbee_nbee_bridge() {
    ?>
        <h3><?php _e('Site registration') ?></h3>
        <form action="<?php echo admin_url('admin-post.php') ?>" method="POST" autocomplete="off">
            <input type="hidden" name="action" value="nbee_general_setting">
            <?php wp_nonce_field( 'nbee_general_setting' ); ?>
            
            <div class="flatsome-registration-form">
                <p class="flatsome-registration-form__code">
                    <label class="nbee_label"><?php _e('Front-end') ?></label>
                    <input type="text" placeholder="https://" value="<?php echo get_option('nbee_frontend_crm_uri') ?>" name="nbee_frontend_crm_uri" class="code" style="width:100%;padding:10px 16px;">
                </p>
                <p class="flatsome-registration-form__code">
                    <label class="nbee_label"><?php _e('Back-end') ?></label>
                    <input type="text" placeholder="https://" value="<?php echo get_option('nbee_backend_crm_uri') ?>" name="nbee_backend_crm_uri" class="code" style="width:100%;padding:10px 16px;">
                </p>
                <p class="description" id="tagline-description"><?php _e('Please double-check the server address of the CRM, or you can ask the website administrator') ?></p>


                <p class="flatsome-registration-form__code">
                    <label class="nbee_label"><?php _e('Application code') ?></label>
                    <input type="text" placeholder="XXXX-XXXX-XXXX-XXXX" value="<?php echo get_option('nbee_client_key') ?>" name="nbee_client_key" class="code" style="width:100%;padding:10px 16px;">
                </p>
                <p class="description" id="tagline-description"><?php _e('The application code is a code obtained from the CRM. You access it using the administrator account on nBee CRM, then go to Settings > SSO.') ?></p>

                <p>
                    <input type="checkbox" checked="" readonly="" onclick="return false;">
                    <label for="flatsome_envato_terms">
                        <?php _e('I know that when i uninstall this plugin, all data that relate to this plugin will be delete.') ?>
                    </label>
                </p>
                <p>
                    <!-- <a class="button button-large" href="https://account.uxthemes.com" target="_blank" rel="noopener noreferrer">Manage your licenses<span style="font-size:16px;width:auto;height:auto;vertical-align:middle;" class="dashicons dashicons-external"></span>
                    </a> -->
                    
                    <button class="button button-large button-primary" type="submit"><?php _e('Save setting') ?></button>
                </p>
            </div>

        </form>
    <?php 
 }


 function nbee_sso_login() {
    $nbee_frontend_crm_uri = get_option('nbee_frontend_crm_uri');
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    ?>
        <h3><?php _e('SSO config') ?></h3>
        <?php 
            if ( ! $nbee_frontend_crm_uri || ! $nbee_backend_crm_uri ) {
        ?>
        <div class="notice notice-warning notice-alt inline" style="display:block!important">
            <p><?php _e('Switch to the General Settings tab and configure all the fields before setting up SSO.') ?></p>
        </div>
        <?php 
            }
        ?>

        <br />
        <form action="<?php echo admin_url('admin-post.php') ?>" method="POST" autocomplete="off">
            <input type="hidden" name="action" value="nbee_sso_setting">
            <?php wp_nonce_field( 'nbee_sso_setting' ); ?>
            <label class="nbee_label"><?php _e('Select the login processing page.') ?></label>
            <select name="nbee_sso_page"> 
                <option selected="selected" disabled="disabled" value=""><?php echo esc_attr( __( 'Select page' ) ); ?></option> 
                <?php
                    $selected_page = get_option( 'nbee_sso_page' );
                    $pages = get_pages(); 
                    foreach ( $pages as $page ) {
                        $option = '<option value="' . $page->ID . '" ';
                        $option .= ( $page->ID == $selected_page ) ? 'selected="selected"' : '';
                        $option .= '>';
                        $option .= $page->post_title;
                        $option .= '</option>';
                        echo $option;
                    }
                ?>
            </select>
            <p class="help-text"><?php _e('Do not choose pages with content. In case there is no suitable page, create a page with <code>/login</code> and return to this page.') ?></p>
            <p class="help-text"><?php _e('The page you choose will be redirected to nBee CRM, and this page will not display any content.') ?></p>
            <button class="button button-large button-primary" type="submit"><?php _e('Save setting') ?></button>
        </form>
    <?php 
 }



 function nbee_user_tracking() {
    ?>
        <h3><?php _e('User tracking config') ?></h3>
        <div class="notice notice-warning notice-alt inline" style="display:block!important">
            <p><?php _e('The User Tracking function is not the same as Google Analytics and cannot replace Google Analytics.') ?></p>
        </div>
        <form action="<?php echo admin_url('admin-post.php') ?>" method="POST" autocomplete="off">
            <input type="hidden" name="action" value="nbee_user_tracking">
            <?php wp_nonce_field( 'nbee_user_tracking' ); ?>

            <h4><?php _e('Google Analytics') ?></h4>

            <p class="flatsome-registration-form__code">
                <label class="nbee_label"><?php _e('Google Analytics code. Don\'t have it yet?') ?> <a href="https://analytics.google.com/analytics/web/" target="_blank"><?php _e('Click here!') ?></a></label>
                <input type="text" placeholder="GA-XXXX-XXXX" value="<?php echo get_option('nbee_google_analytics_key') ?>" name="nbee_google_analytics_key" class="code" style="width:100%;padding:10px 16px;">
            </p>
            <p>
                <label for="nbee_google_tracking_status">
                    <input type="checkbox" <?php echo get_option('nbee_google_tracking_status') == 1 ? 'checked' : '' ?> name="nbee_google_tracking_status" id="nbee_google_tracking_status" />
                    <?php _e('Turn on Google Analytics.') ?>
                </label>
            </p>
            <p class="description help-text"><?php _e('The Google Analytics function helps track and analyze website traffic.') ?></p>


            <h4><?php _e('nBee Tracking feature') ?></h4>
            <p>
                <label for="nbee_user_tracking_status">
                    <input type="checkbox" <?php echo get_option('nbee_user_tracking_status') == 1 ? 'checked' : '' ?> name="nbee_user_tracking_status" id="nbee_user_tracking_status" />
                    <?php _e('Turn on Tracking feature for website.') ?>
                </label>
            </p>
            <p class="description help-text"><?php _e('The User Tracking function monitors user activities, collects device information for analysis, and helps prevent spam.') ?></p>

            <button class="button button-large button-primary" type="submit"><?php _e('Save setting') ?></button>

        </form>
    <?php 
 }


 function nbee_referrer_tracking() {
    ?>
    <h3><?php _e('Referrer tracking config') ?></h3>
    <br />
    <p class="description help-text"><?php _e('Record user orders, account registrations. Commissions can be set up in nBee CRM') ?></p>
    <br />
    <form action="<?php echo admin_url('admin-post.php') ?>" method="POST" autocomplete="off">
        <input type="hidden" name="action" value="nbee_referrer_tracking">
        <?php wp_nonce_field( 'nbee_referrer_tracking' ); ?>

        <p>
            <label for="nbee_referrer_tracking">
                <input type="checkbox" <?php echo get_option('nbee_referrer_tracking_status') == 1 ? 'checked' : '' ?> name="nbee_referrer_tracking" id="nbee_referrer_tracking" />
                <?php _e('Turn on Referrer tracking') ?>
            </label>
        </p>

        <h4><?php _e('Commission calculation mode upon recording') ?></h4>
        <p>
            <label for="nbee_referrer_tracking_mode_first">
                <input type="radio" <?php echo get_option('nbee_referrer_tracking_mode') == 'first_click' ? 'checked' : '' ?> value="first_click" name="nbee_referrer_tracking_mode" id="nbee_referrer_tracking_mode_first" />
                <?php _e('Calculate based on the first_click mode') ?>
            </label>
        </p>
        <p>
            <label for="nbee_referrer_tracking_mode_last">
                <input type="radio" <?php echo get_option('nbee_referrer_tracking_mode') == 'last_click' ? 'checked' : '' ?> value="last_click" name="nbee_referrer_tracking_mode" id="nbee_referrer_tracking_mode_last" />
                <?php _e('Calculate based on the last_click mode') ?>
            </label>
        </p>
        <button class="button button-large button-primary" type="submit"><?php _e('Save setting') ?></button>

    </form>
    <?php 
 }