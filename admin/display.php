<?php

/**
 * Admin of nBee
 */

function nbee_admin_tab()
{
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
        <a href="<?php echo admin_url('admin.php?page=nbee_ecommerce_sync') ?>" class="nav-tab <?php echo $admin_page === 'nbee_ecommerce_sync' ? 'nav-tab-active' : 'nav-tab' ?>">
            <?php _e('E-commerce Sync') ?>
        </a>
    </h2>
<?php
}



function nbee_display_main()
{
    $admin_page = isset($_GET['page']) ? $_GET['page'] : '';
?>
    <div class="flatsome-panel">
        <div class="wrap about-wrap " id="nbee_wrap">
            <h1 class="wp-heading-inline"><?php _e('nBee setting'); ?></h1>
            <div class="about-text">
                <?php _e('Thanks for Choosing nBee - The worlds most powerful CRM. This page will help you quickly get up and running with nBee.') ?>
                <br><br>
            </div>
            <hr class="wp-header-end" />
            <?php nbee_admin_tab() ?>
            <div id="tab-activate" class="col cols panel flatsome-panel">
                <div class="inner-panel">
                    <?php

                    switch ($admin_page) {
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
                        case 'nbee_ecommerce_sync':
                            nbee_ecommerce_sync();
                            break;
                    }

                    ?>
                </div>
            </div>
        </div>
    </div>
<?php
}


function nbee_nbee_bridge()
{
?>
    <h3><?php _e('Site registration') ?></h3>
    <form action="<?php echo admin_url('admin-post.php') ?>" method="POST" autocomplete="off">
        <input type="hidden" name="action" value="nbee_general_setting">
        <?php wp_nonce_field('nbee_general_setting'); ?>

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
                <label class="nbee_label"><?php _e('Back-end-media') ?></label>
                <input type="text" placeholder="https://" value="<?php echo get_option('nbee_backend_media_uri') ?>" name="nbee_backend_media_uri" class="code" style="width:100%;padding:10px 16px;">
            </p>

            <p class="flatsome-registration-form__code">
                <label class="nbee_label"><?php _e('Back-end-xsinged') ?></label>
                <input type="text" value="<?php echo get_option('nbee_backend_xsigned') ?>" name="nbee_backend_xsigned" class="code" style="width:100%;padding:10px 16px;">
            </p>

            <p class="flatsome-registration-form__code">
                <label class="nbee_label"><?php _e('Client public key') ?></label>
                <input type="text" placeholder="XXXX-XXXX-XXXX-XXXX" value="<?php echo get_option('nbee_client_public_key') ?>" name="nbee_client_public_key" class="code" style="width:100%;padding:10px 16px;">
            </p>
            <p class="description" id="tagline-description"><?php _e('The "Client public key" is a key obtained from the CRM. You access it using the administrator account on nBee CRM, then go to Settings > SSO.') ?></p>

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


function nbee_sso_login()
{
    $nbee_frontend_crm_uri = get_option('nbee_frontend_crm_uri');
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
?>
    <h3><?php _e('SSO config') ?></h3>
    <?php
    if (! $nbee_frontend_crm_uri || ! $nbee_backend_crm_uri) {
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
        <?php wp_nonce_field('nbee_sso_setting'); ?>
        <label class="nbee_label"><?php _e('Select the login processing page.') ?></label>
        <select name="nbee_sso_page">
            <option selected="selected" disabled="disabled" value=""><?php echo esc_attr(__('Select page')); ?></option>
            <?php
            $selected_page = get_option('nbee_sso_page');
            $pages = get_pages();
            foreach ($pages as $page) {
                $option = '<option value="' . $page->ID . '" ';
                $option .= ($page->ID == $selected_page) ? 'selected="selected"' : '';
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



function nbee_user_tracking()
{
?>
    <h3><?php _e('User tracking config') ?></h3>
    <div class="notice notice-warning notice-alt inline" style="display:block!important">
        <p><?php _e('The User Tracking function is not the same as Google Analytics and cannot replace Google Analytics.') ?></p>
    </div>
    <form action="<?php echo admin_url('admin-post.php') ?>" method="POST" autocomplete="off">
        <input type="hidden" name="action" value="nbee_user_tracking">
        <?php wp_nonce_field('nbee_user_tracking'); ?>

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


        <h4><?php _e('nBee Advance User Tracking') ?></h4>
        <p>
            <label for="nbee_advance_user_tracking_status">
                <input type="checkbox" <?php echo get_option('nbee_advance_user_tracking_status') == 1 ? 'checked' : '' ?> name="nbee_advance_user_tracking_status" id="nbee_advance_user_tracking_status" />
                <?php _e('Turn on Advance User Tracking feature for website.') ?>
            </label>
        </p>
        <p class="description help-text"><?php _e('The "Advance User Tracking" function will add USER_ID to Google Analytics, but you have to turn this feature on in Google Analytics. Go to Gooogle and Search with keyword: "Enable User ID Tracking in Google Analytics" ') ?></p>




        <button class="button button-large button-primary" type="submit"><?php _e('Save setting') ?></button>

    </form>
<?php
}


function nbee_referrer_tracking()
{
?>
    <h3><?php _e('Referrer tracking config') ?></h3>
    <br />
    <p class="description help-text"><?php _e('Record user orders, account registrations. Commissions can be set up in nBee CRM') ?></p>
    <br />
    <form action="<?php echo admin_url('admin-post.php') ?>" method="POST" autocomplete="off">
        <input type="hidden" name="action" value="nbee_referrer_tracking">
        <?php wp_nonce_field('nbee_referrer_tracking'); ?>

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


function nbee_ecommerce_sync()
{
    $nbee_backend_media_uri = get_option('nbee_backend_media_uri');
    $nbee_backend_crm_uri = get_option('nbee_backend_crm_uri');
    $nbee_backend_xsigned = get_option('nbee_backend_xsigned');
    $token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;

    if ($nbee_backend_media_uri && $nbee_backend_crm_uri && $nbee_backend_xsigned) {
        if ($token) {
    ?>
            <div class="sync-box" style="border: 1px solid #ccc; padding: 20px; margin-bottom: 20px;">
                <form action="<?php echo admin_url('admin-post.php') ?>" method="POST" autocomplete="off" id="nbee_sync_form" style="display: flex; flex-wrap: wrap; gap: 12px;">
                    <input type="hidden" name="action" value="nbee_ecommerce_sync">
                    <input type="hidden" name="sync_action" value="">
                    <?php wp_nonce_field('nbee_ecommerce_sync'); ?>
                    <h4><?php _e('Đồng bộ dữ liệu') ?></h4>
                    <div class="notice notice-info notice-alt inline" style="display:block!important; margin-bottom: 20px;">
                        <p><?php _e('Chỉ dành cho kỹ thuật viên. Chỉ thao tác khi thực sự cần thiết và bạn biết là bạn đang làm cái gì. OK!') ?></p>
                    </div>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_store_settings')"><?php _e('Cài đặt cửa hàng') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_product_catalog')"><?php _e('Danh mục sản phẩm') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_product_brand')"><?php _e('Thương hiệu sản phẩm') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_product_collection')"><?php _e('Bộ sưu tập sản phẩm') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_products')"><?php _e('Sản phẩm') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_product_reviews')"><?php _e('Đánh giá') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_payment_methods')"><?php _e('Phương thức thanh toán') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_shipping_methods')"><?php _e('Phương thức vận chuyển') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_promotions')"><?php _e('Khuyến mãi') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_customers')"><?php _e('Khách hàng') ?></button>
                    <button class="button button-large button-primary" type="button" onclick="confirmSync('sync_orders')"><?php _e('Đơn hàng') ?></button>
                </form>
            </div>

            <div id="nbee_loading_modal" style="display:none;">
                <div class="nbee_loading_content">
                    <p><?php _e('Đang đồng bộ...'); ?></p>
                </div>
            </div>

            <div id="nbee_confirm_modal" style="display:none;">
                <div class="nbee_confirm_content">
                    <p id="nbee_confirm_message"></p>
                    <button class="button button-large button-primary" id="nbee_confirm_yes"><?php _e('Có, đồng bộ') ?></button>
                    <button class="button button-large" id="nbee_confirm_no"><?php _e('Không, tôi ấn nhầm') ?></button>
                </div>
            </div>

            <style>
                #nbee_loading_modal,
                #nbee_confirm_modal {
                    position: fixed;
                    z-index: 9999;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }

                .nbee_loading_content,
                .nbee_confirm_content {
                    background: #fff;
                    padding: 20px;
                    border-radius: 5px;
                    text-align: center;
                }
            </style>

            <script>
                function confirmSync(action) {
                    var message = '';
                    switch (action) {
                        case 'sync_product_catalog':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Danh mục sản phẩm?'); ?>';
                            break;
                        case 'sync_product_brand':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Thương hiệu sản phẩm?'); ?>';
                            break;
                        case 'sync_product_collection':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Bộ sưu tập sản phẩm?'); ?>';
                            break;
                        case 'sync_products':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Sản phẩm?'); ?>';
                            break;
                        case 'sync_product_reviews':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Đánh giá?'); ?>';
                            break;
                        case 'sync_payment_methods':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Phương thức thanh toán?'); ?>';
                            break;
                        case 'sync_shipping_methods':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Phương thức vận chuyển?'); ?>';
                            break;
                        case 'sync_promotions':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Khuyến mãi?'); ?>';
                            break;
                        case 'sync_customers':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Khách hàng?'); ?>';
                            break;
                        case 'sync_orders':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Đơn hàng?'); ?>';
                            break;
                        case 'sync_store_settings':
                            message = '<?php _e('Bạn có chắc chắn muốn đồng bộ Cài đặt cửa hàng?'); ?>';
                            break;
                    }
                    document.getElementById('nbee_confirm_message').innerText = message;
                    document.getElementById('nbee_confirm_modal').style.display = 'flex';
                    document.getElementById('nbee_confirm_yes').onclick = function() {
                        document.getElementById('nbee_confirm_modal').style.display = 'none';
                        document.getElementById('nbee_loading_modal').style.display = 'flex';
                        document.getElementById('nbee_sync_form').sync_action.value = action;
                        document.getElementById('nbee_sync_form').submit();
                    };
                    document.getElementById('nbee_confirm_no').onclick = function() {
                        document.getElementById('nbee_confirm_modal').style.display = 'none';
                    };
                }

                document.getElementById('nbee_sync_form').addEventListener('submit', function() {
                    document.getElementById('nbee_loading_modal').style.display = 'flex';
                });
            </script>
        <?php
        } else {
        ?>
            <form action="<?php echo admin_url('admin-post.php?action=nbee_login_admin'); ?>" method="POST" autocomplete="off" id="nbee_login_form">
                <h4><?php _e('Đăng nhập tài khoản quản trị viên') ?></h4>
                <p>
                    <label for="user_input"><?php _e('Email hoặc SDT') ?></label>
                    <input type="email" name="user_input" id="user_input" required>
                </p>
                <p>
                    <label for="password"><?php _e('Mật khẩu') ?></label>
                    <input type="password" name="password" id="password" required>
                </p>
                <input type="hidden" name="device_type" value="website">
                <input type="hidden" name="device_signature" value="abc">
                <input type="hidden" name="device_uuid" value="abc">
                <button class="button button-large button-primary" type="submit"><?php _e('Đăng nhập') ?></button>
            </form>
        <?php
        }
    } else {
        ?>
        <div class="notice notice-warning notice-alt inline" style="display:block!important">
            <p><?php _e('Vui lòng cấu hình Cài đặt chung trước khi sử dụng tính năng Đồng bộ hóa thương mại điện tử.') ?></p>
        </div>
<?php
    }
}
