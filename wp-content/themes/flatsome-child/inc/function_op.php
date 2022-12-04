<?php
if(function_exists('acf_add_options_page')){
    acf_add_options_page(
        array(
            'page_title' => 'Option',
            'menu_title' => 'Cài đặt chung',
            'menu_slug' => 'web-settings',
            'capability' => 'edit_posts',
            'icon_url' => 'dashicons-hammer',
        )
    );
    acf_add_options_sub_page(
        array(
            'page_title' => 'General Setting',
            'menu_title' => 'General',
            'parent_slug' => 'web-settings',
            'menu_slug' => 'general-pack',
        )
    );
}

/*Checkout*/
add_filter('woocommerce_bacs_accounts', '__return_false');
add_action( 'woocommerce_email_before_order_table', 'devvn_email_instructions', 10, 3 );
function devvn_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
    if ( ! $sent_to_admin && 'bacs' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
        devvn_bank_details( $order->get_id() );
    }
}
add_action( 'woocommerce_thankyou_bacs', 'devvn_thankyou_page' );
function devvn_thankyou_page($order_id){
    devvn_bank_details($order_id);
}
function devvn_bank_details( $order_id = '' ) {
    $bacs_accounts = get_option('woocommerce_bacs_accounts');
    if ( ! empty( $bacs_accounts ) ) {
        ob_start();
        echo '<table style=" border: 1px solid #ddd; border-collapse: collapse; width: 100%; ">';
        ?>
        <tr>
            <td colspan="2" style="border: 1px solid #eaeaea;padding: 6px 10px;"><strong>Thông tin chuyển khoản</strong></td>
        </tr>
        <?php
        foreach ( $bacs_accounts as $bacs_account ) {
            $bacs_account = (object) $bacs_account;
            $account_name = $bacs_account->account_name;
            $bank_name = $bacs_account->bank_name;
            $stk = $bacs_account->account_number;
            $icon = $bacs_account->iban;
            ?>
            <tr>
                <td style="width: 200px;border: 1px solid #eaeaea;padding: 6px 10px;"><?php if($icon):?><img src="<?php echo $icon;?>" alt=""/><?php endif;?></td>
                <td style="border: 1px solid #eaeaea;padding: 6px 10px;">
                    <strong>STK:</strong> <?php echo $stk;?><br>
                    <strong>Chủ tài khoản:</strong> <?php echo $account_name;?><br>
                    <strong>Chi Nhánh:</strong> <?php echo $bank_name;?><br>
                    <strong>Nội dung chuyển khoản:</strong> DH<?php echo $order_id .' _ Họ và tên';?>
                </td>
            </tr>
            <?php
        }
        echo '</table>';
        echo ob_get_clean();;
    }
}

/*Merge checkout*/
add_action( 'woocommerce_before_checkout_form', 'add_cart_on_checkout', 5 );
 
function add_cart_on_checkout() {
 if ( is_wc_endpoint_url( 'order-received' ) ) return;
 echo do_shortcode('[woocommerce_cart]'); // WooCommerce cart page shortcode
}
//***
add_action( 'template_redirect', function() {
// Replace "cart"  and "checkout" with cart and checkout page slug if needed
    if ( is_page( 'cart' ) ) {
        wp_redirect( '/checkout/' );
        die();
    }
} );
//***
add_action( 'template_redirect', 'redirect_empty_checkout' );
 
function redirect_empty_checkout() {
    if ( is_checkout() && 0 == WC()->cart->get_cart_contents_count() && ! is_wc_endpoint_url( 'order-pay' ) && ! is_wc_endpoint_url( 'order-received' ) ) {
   wp_safe_redirect( get_permalink( wc_get_page_id( 'shop' ) ) ); 
        exit;
    }
}
//***
add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );
add_filter( 'woocommerce_checkout_update_order_review_expired', '__return_false' );
//** Thong bao
add_action( 'woocommerce_email_before_order_table', 'devvn_woocommerce_email_before_order_table', 5 );
add_action( 'woocommerce_thankyou_bacs', 'devvn_woocommerce_email_before_order_table', 5 );
function devvn_woocommerce_email_before_order_table($order){
    if(is_numeric($order)) $order = wc_get_order($order);
    if($order->get_payment_method() == 'bacs') {
        echo '<p style="color:#3e3c3c;font-size:14px;border:1px dashed #ff0000;padding:5px 10px;margin-top:20px;background:#fffdf3;line-height:20px"><strong style="color:red">Lưu ý:</strong> Sau khi chuyển khoản hãy nhắn tin qua số Zalo: <a style="color:#42a2cd;font-weight:700" href="http://zalo.me/0983145155">0983.145.155</a> cho mình nhé</p>';
    }
}

/* ========== FIELDS ========== */
add_filter('woocommerce_checkout_fields', 'dms_custom_override_checkout_fields', 9999999);

function dms_custom_override_checkout_fields($fields) {
    //billing
    $fields['billing']['billing_first_name'] = array(
        'label' => __('Họ và tên', 'devvn'),
        'placeholder' => _x('Họ và tên', 'placeholder', 'devvn'),
        'required' => true,
        'class' => array('form-row-first'),
        'clear' => true,
        'priority' => 10
    );
    unset($fields['billing']['billing_last_name']);
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_address_2']);

    $fields['billing']['billing_phone']['priority'] = 20;
    $fields['billing']['billing_phone']['class'] = array('form-row-last');
    $fields['billing']['billing_phone']['placeholder'] = _x('Số điện thoại', 'placeholder', 'devvn');

    $fields['billing']['billing_address_1']['class'] = array('form-row-wide');
    $fields['billing']['billing_address_1']['priority'] = 22;

    $fields['billing']['billing_email']['priority'] = 25;
    $fields['billing']['billing_email']['class'] = array('form-row-wide');
    $fields['billing']['billing_email']['required'] = false;

    //shipping
    $fields['shipping']['shipping_first_name'] = array(
        'label' => __('Họ và tên', 'devvn'),
        'placeholder' => _x('Họ và tên', 'placeholder', 'devvn'),
        'required' => true,
        'class' => array('form-row-first'),
        'clear' => true,
        'priority' => 10
    );
    unset($fields['shipping']['shipping_last_name']);
    unset($fields['shipping']['shipping_company']);
    unset($fields['shipping']['shipping_country']);
    unset($fields['shipping']['shipping_state']);
    unset($fields['shipping']['shipping_postcode']);
    unset($fields['shipping']['shipping_city']);
    unset($fields['shipping']['shipping_address_2']);

    $fields['shipping']['shipping_address_1']['class'] = array('form-row-wide');
    $fields['shipping']['shipping_phone'] = array(
        'label' => __('Số điện thoại', 'devvn'),
        'placeholder' => _x('Số điện thoại', 'placeholder', 'devvn'),
        'required' => true,
        'class' => array('form-row-last'),
        'clear' => true,
        'priority' => 20
    );

    uasort($fields['billing'], 'dms_sort_fields_by_order');
    uasort($fields['shipping'], 'dms_sort_fields_by_order');

    return $fields;
}

if (!function_exists('dms_sort_fields_by_order')) {
    function dms_sort_fields_by_order($a, $b) {
        if (!isset($b['priority']) || !isset($a['priority']) || $a['priority'] == $b['priority']) {
            return 0;
        }
        return ($a['priority'] < $b['priority']) ? -1 : 1;
    }
}

add_action('woocommerce_admin_order_data_after_shipping_address', 'my_custom_checkout_field_display_admin_order_meta', 10, 1);

function my_custom_checkout_field_display_admin_order_meta($order) {
    echo '<p><strong>'.__("Số ĐT người nhận"). ':</strong> <br>'.get_post_meta($order -> id, '_shipping_phone', true). '</p>';
}
/* ========== END FIELDS ========== */
