<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 
 *
 *
 * @author   Casso Team
 * @since   
 *
 */

require_once('class-casso-base.php');
class WC_Gateway_Casso_Vpbank extends WC_Base_Casso {
	public function __construct() {
		$this->bank_id 			  = 'vpbank';
		$this->bank_name 		  = 'VPbank';

		// $this->icon               = apply_filters( 'woocommerce_payleo_icon', plugins_url('../assets/vpbank.png', __FILE__ ) );
		$this->has_fields         = false;
		$this->method_title       = sprintf(__('Payment via bank %s', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'), $this->bank_name);
		$this->method_description = __('Payment by bank transfer. The system automatically confirms the paid order after the transfer is completed.', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
		$this->title        = sprintf(__('Payment via bank %s', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'), $this->bank_name);
		parent::__construct();
	}
	public function configure_payment()
	{
		$this->method_title       = sprintf(__('Payment via bank %s', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'), $this->bank_name);
		$this->method_description = __('Make payment by bank transfer.', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
	}
}