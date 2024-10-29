<?php
/*
 * Plugin Name: AdGoals Tracking
 * Plugin URI: https://adgoals.net/wordpress-plugin/
 * Description: This plugin lets you connect WooCommerce with AdGoals.
 * Version: 1.0.0
 * Author: AdGoals International
 * Author URI: https://adgoals.net
 * License: GPL2
*/

/**
 * Check if WooCommerce is active
 **/
 if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'wp_head', 'adgoals_tracker' );
    function adgoals_tracker() {
        if (site_url() === home_url()) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            if(!empty($_GET)) {
                $parameters = preg_replace('/[^-a-zA-Z0-9_]/', '', $_GET);
                if(isset($parameters['src']) && $parameters['src'] === 'adg' && isset($parameters['adgClickId'])) {
                    $_SESSION['adgoals_src'] = $parameters['src'];
                    $_SESSION['adgoals_click_id'] = $parameters['adgClickId'];
                }
            }
        }
    }

    add_action( 'admin_init', 'adgoals_settings' );
    
    function adgoals_settings() {
        register_setting( 'adgoals-settings-group', 'postback_url' );
    }

    add_action( 'admin_menu', 'adgoals_menu' );

    function adgoals_menu() {
        add_submenu_page( 'woocommerce', 'AdGoals Tracking Settings', 'AdGoals Tracking', 'manage_options', 'adgoals', 'adgoals_options' );
    }

    function adgoals_options() {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        } ?>
        <div class="wrap">
        <h2>AdGoals Tracking Settings</h2>
        
        <form method="post" action="options.php">
            <?php settings_fields( 'adgoals-settings-group' ); ?>
            <?php do_settings_sections( 'adgoals-settings-group' ); ?>
            <table class="form-table">
                <tr valign="top">
                <th scope="row">Tracking URL</th>
                <td><input type="text" style="width:400px;" value="<?php echo home_url(); ?>/?src=adg" readonly/></td>
                </tr>

                <tr valign="top">
                <th scope="row">Postback URL</th>
                <td><input type="url" style="width:400px;" name="postback_url" value="<?php echo htmlspecialchars( get_option('postback_url') ); ?>" /></td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        
        </form>
        </div>
        <?php
    }
    add_action( 'woocommerce_order_status_processing', 'adgoals_postback' );
    function adgoals_postback($order_id) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if(isset($_SESSION['adgoals_src']) && $_SESSION['adgoals_src'] === 'adg' && isset($_SESSION['adgoals_click_id'])) {
            $order = new WC_Order($order_id);
            $total = $order->get_total();
            $postback_url = esc_attr( get_option('postback_url') );
            $postback_url = str_replace("{{CLICKID}}", $_SESSION['adgoals_click_id'], $postback_url);
            $postback_url = str_replace("{{PRICE}}", $total, $postback_url);

            for ($i=0;$i<5;$i++) {
                $response = wp_remote_get(htmlspecialchars_decode($postback_url));
                if (wp_remote_retrieve_response_code($response) === 200) {
                    break;
                }
                sleep(1);  
            
            }
            update_post_meta( $order_id, 'src', 'AdGoals' );
        }
    }
    add_filter( 'manage_edit-shop_order_columns', 'adgoals_custom_shop_order_column',20);
    function adgoals_custom_shop_order_column($columns) {
        $new_columns = array();
        
            foreach ( $columns as $column_name => $column_info ) {
        
                $new_columns[ $column_name ] = $column_info;
        
                if ( 'order_total' === $column_name ) {
                    $new_columns['adg-source'] = __( 'Source','adgoals' );
                }
            }
        
            return $new_columns;
    }
    add_action( 'manage_shop_order_posts_custom_column' , 'adgoals_custom_orders_list_column_content', 10, 2 );
    function adgoals_custom_orders_list_column_content( $column, $post_id ) {
        switch ( $column )
        {
            case 'adg-source' :
                $source = get_post_meta( $post_id, 'src', true );
                if( $source === 'AdGoals' ) {
                    echo '<img src="'.plugins_url('img/adgoals.png', __FILE__).'">';
                } else {
                    echo '<span class="na">–</span>';
                }
                break;
        }
    }
    function adgoals_sv_wc_cogs_add_order_source_column_style() {        
        $css = '.widefat .column-order_date, .widefat .column-adg-source { width: 6%; }';
        wp_add_inline_style( 'woocommerce_admin_styles', $css );
    }
    add_action( 'admin_print_styles', 'adgoals_sv_wc_cogs_add_order_source_column_style' );
}