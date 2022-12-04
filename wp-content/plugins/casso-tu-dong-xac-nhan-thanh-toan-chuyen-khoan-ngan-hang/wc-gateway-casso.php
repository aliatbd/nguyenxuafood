<?php

/**
 * Plugin Name: Casso - Tự động xác nhận thanh toán chuyển khoản ngân hàng
 * Plugin URI: https://casso.vn/plugin-ket-noi-ngan-hang/
 * Description: Casso plugin developed to connect Vietnamese banks with Wordpress. Help automatically confirm the order has been paid by wire transfer in Vietnam.
 * Author: Casso Team
 * Author URI: https://casso.vn/
 * Text Domain: casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang
 * Version: 2.10.1
 * License: GNU General Public License v3.0
 */

use CassoPayment as GlobalCassoPayment;

if (!defined('ABSPATH')) {
	exit;
}
define('CASSO_DIR', plugin_dir_path(__FILE__));
define('CASSO_URL', plugins_url('/', __FILE__));

new CassoPayment();
class CassoPayment
{
	static $connect_uri = "https://pay.casso.vn";
	static $webhook_oauth2 = "oauth2-callback";
	static $webhook_route = "bank_transfer_handler";
	static $CASSO_OAUTH2_URL = "https://oauth.casso.vn/auth";
	static $CASSO_OAUTH_URL_V2 = "https://oauth.casso.vn/v2";
	static $client_id = "3cd60064-543c-4cc5-9384-8433267c4f56";
	static $client_secret = "21df0883-4758-41e7-b694-d54bc6d5e59f";

	static $default_settings = array(

		'bank_transfer'         =>
		array(
			'case_insensitive' => 'yes',
			'enabled' => 'yes',
			'title' => 'Chuyển khoản ngân hàng 24/7',
			'secure_token' => '',
			'transaction_prefix' => 'DH',
			'acceptable_difference' => 1000,
			'vietqr_template' => 'compact',
			'authorization_code' => '',
			'viet_qr' => 'yes',

		),
		'bank_transfer_accounts' =>
		array(
			// array(
			// 	'account_name'   => '',
			// 	'account_number' => '',
			// 	'bank_name'      => '',
			// 	'bin'      => 0,
			// 	'connect_status'      => 0,
			// 	'plan_status'      => 0,
			// 	'is_show'      => 'yes',
			// ),
		),
		'order_status' =>
		array(
			'order_status_after_paid'   => 'wc-completed',
			'order_status_after_underpaid' => 'wc-processing',
		),

	);
	static $casso_oauth_settings = array(
		'login_type' => 0, // 0 là API key, 1 oauth2 
		'refresh_token' => '',
		'access_token' => '',
		'expires_at' => 0,
		'webhook_id' => 0,
		'user_id' => '',
		'email' => '',
		"business_id" => '',
		"business_name" => ''
	);

	public function console_log($output, $with_script_tags = true)
	{
		// $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) .
		// 	');';
		// if ($with_script_tags) {
		// 	$js_code = '<script>' . $js_code . '</script>';
		// }
		// echo $js_code;
	}
	
	public function __construct()
	{
		// get the settings of the old version
		$this->domain = 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang';
		add_action('plugins_loaded', array($this, 'casso_load_plugin_textdomain'));

		add_action('init', array($this, 'init'));

		$this->settings = self::get_settings();
	}
	/*
	public function update_settings_option()
	{
		// get the settings of the old version

		$settings_casso = self::get_settings();

		// $settings_casso['bank_transfer']['enabled'];
		if (isset($settings_casso['bank_transfer']["vietqr_template"])){
			$settings_casso['bank_transfer']["vietqr_template"] = "compact";
		}
		
		update_option('casso', $settings_casso);
		return;
	}

	*/
	public function init()
	{
		if (class_exists('WooCommerce')) {
			// Run this plugin normally if WooCommerce is active
			// Load the localization featureUnderpaid

			$this->main();
			// Add "Settings" link when the plugin is active
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'casso_add_settings_link'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
				$settings = array('<a href="https://casso.vn/plugin-ket-noi-ngan-hang/" target="_blank">' . __('Docs', 'woocommerce') . '</a>');
				$links    = array_reverse(array_merge($links, $settings));

				return $links;
			});
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
				$settings = array('<a href="https://wordpress.org/support/plugin/casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang/reviews/" target="_blank">' . __('Review', 'woocommerce') . '</a>');
				$links    = array_reverse(array_merge($links, $settings));
				return $links;
			});
			// Đăng kí thêm trạng thái 
			add_filter('wc_order_statuses', array($this, 'add_casso_order_statuses'));
			register_post_status('wc-paid', array(
				'label'                     => __('Paid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(__('Paid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . ' (%s)', __('Paid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . ' (%s)')
			));
			register_post_status('wc-underpaid', array(
				'label'                     =>  __('Underpaid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop(__('Underpaid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . ' (%s)', __('Underpaid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . ' (%s)')
			));
			wp_enqueue_style('casso-style', plugins_url('assets/css/style.css', __FILE__), array(), false, 'all');
			// wp_enqueue_script('casso-js', plugins_url('assets/js/easy.qrcode.js', __FILE__), array('jquery'), '', true);
			add_action('wp_ajax_nopriv_fetch_order_status_casso', array($this, 'fetch_order_status_casso'));
			add_action('wp_ajax_fetch_order_status_casso', array($this, 'fetch_order_status_casso'));
			add_action('wp_ajax_nopriv_fetch_sync_order_casso', array($this, 'fetch_sync_order_casso'));
			add_action('wp_ajax_fetch_sync_order_casso', array($this, 'fetch_sync_order_casso'));
		} else {
			// Throw a notice if WooCommerce is NOT active
			add_action('admin_notices', array($this, 'notice_if_not_woocommerce'));
		}
	}
	public function fetch_sync_order_casso()
	{

		$this->casso_plugin_settings = self::get_settings();
		$this->oauth_settings = self::casso_oauth_get_settings();
		$authorizationCode = $this->casso_plugin_settings['bank_transfer']['authorization_code'];
		$bank_id =  sanitize_key($_REQUEST['bank_id']);
		$response = null;
		if (empty($bank_id)) die();

		if ($this->oauth_settings['login_type'] == 0) {
			//v2
			$url  = self::$CASSO_OAUTH_URL_V2 . '/sync';
			$body = array(
				"bank_acc_id" => $bank_id
			);
			$args = array(
				'body'        => json_encode($body),
				'headers' => array(
					"content-type" => "application/json",
					"Authorization" => "Apikey " . $authorizationCode
				),
				'timeout'     => 5,
			);
			$response = wp_remote_post($url, $args);
		} else {
			$url  = self::$CASSO_OAUTH_URL_V2 . '/sync';
			$body = array(
				"bank_acc_id" => $bank_id
			);
			$args = array(
				'body'        => json_encode($body),
				'headers' => array(
					"content-type" => "application/json",
					"Authorization" => "Bearer " . $this->oauth_settings['access_token']
				),
				'timeout'     => 5,
			);
			$response = wp_remote_post($url, $args);
		}

		if (is_wp_error($response)) {
			die();
		}
		if ($response['response']['code'] == 200 || $response['response']['code'] == 201) {
			$body     = wp_remote_retrieve_body($response);
			echo 'ok';
		}
		die();
	}
	public function fetch_order_status_casso()
	{
		$order_id = sanitize_key($_REQUEST['order_id']);
		if (empty($order_id)) die();

		$order = wc_get_order($order_id );
		$order_data = $order->get_data();
		$status = $order_data['status'];
		echo esc_html('wc-' . $status);
		die();
	}
	public function add_casso_order_statuses($order_statuses)
	{
		$new_order_statuses = array();
		// add new order status after processing
		foreach ($order_statuses as $key => $status) {
			$new_order_statuses[$key] = $status;
		}
		$new_order_statuses['wc-paid'] = __('Paid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
		$new_order_statuses['wc-underpaid'] = __('Underpaid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
		return $new_order_statuses;
	}
	//Hàm này có thể giúp tạo ra một class Bank mới.
	public function gen_payment_gateway($gatewayName)
	{
		// $newClass = new class extends WC_Gateway_Casso_Base
		// {
		// }; //create an anonymous class
		// $newClassName = get_class($newClass); //get the name PHP assigns the anonymous class
		// class_alias($newClassName, $gatewayName); //alias the anonymous class with your class name
	}


	public function main()
	{

		if (is_admin()) {
			include(CASSO_DIR . 'inc/class-casso-admin-page.php');
			$this->Admin_Page = new Casso_Admin_Page();
		}
		$settings = self::get_settings();
		$this->settings = $settings;
		add_action('woocommerce_api_' . self::$webhook_oauth2, array($this, 'casso_oauth2_handler'));
		add_action('woocommerce_api_' . self::$webhook_route, array($this, 'casso_payment_handler'));

		if ('yes' == $settings['bank_transfer']['enabled']) {
			// chỗ này e tách ra ngoài code cho clean mà nó k nhận (gộp woocommerce_payment_gateways)
			foreach ($settings['bank_transfer_accounts'] as $account) {
				if (isset($account['is_show']) && $account['is_show'] == 'yes') {
					if (strtolower($account['bank_name']) == 'acb')
						require_once(CASSO_DIR . 'inc/banks/class-casso-acb.php');
					if (strtolower($account['bank_name']) == 'mbbank')
						require_once(CASSO_DIR . 'inc/banks/class-casso-mbbank.php');
					if (strtolower($account['bank_name']) == 'techcombank')
						require_once(CASSO_DIR . 'inc/banks/class-casso-techcombank.php');
					if (strtolower($account['bank_name']) == 'timoplus')
						require_once(CASSO_DIR . 'inc/banks/class-casso-timoplus.php');
					if (strtolower($account['bank_name']) == 'vpbank')
						require_once(CASSO_DIR . 'inc/banks/class-casso-vpbank.php');
					if (strtolower($account['bank_name']) == 'vietinbank')
						require_once(CASSO_DIR . 'inc/banks/class-casso-vietinbank.php');
					if (strtolower($account['bank_name']) == 'ocb')
						require_once(CASSO_DIR . 'inc/banks/class-casso-ocb.php');
					if (strtolower($account['bank_name']) == 'tpbank')
						require_once(CASSO_DIR . 'inc/banks/class-casso-tpbank.php');
					if (strtolower($account['bank_name']) == 'vietcombank')
						require_once(CASSO_DIR . 'inc/banks/class-casso-vietcombank.php');
					if (strtolower($account['bank_name']) == 'bidv')
						require_once(CASSO_DIR . 'inc/banks/class-casso-bidv.php');
					if (strtolower($account['bank_name']) == 'agribank')
						require_once(CASSO_DIR . 'inc/banks/class-casso-agribank.php');
				}
			}
			add_filter('woocommerce_payment_gateways', function ($gateways) {
				$settings = self::get_settings();
				foreach ($settings['bank_transfer_accounts'] as $account) {
					if (strtolower($account['bank_name']) == 'acb')
						$gateways[] = 'WC_Gateway_Casso_ACB';
					if (strtolower($account['bank_name']) == 'mbbank')
						$gateways[] = 'WC_Gateway_Casso_Mbbank';
					if (strtolower($account['bank_name']) == 'techcombank')
						$gateways[] = 'WC_Gateway_Casso_Techcombank';
					if (strtolower($account['bank_name']) == 'timoplus')
						$gateways[] = 'WC_Gateway_Casso_TimoPlus';
					if (strtolower($account['bank_name']) == 'vpbank')
						$gateways[] = 'WC_Gateway_Casso_Vpbank';
					if (strtolower($account['bank_name']) == 'vietinbank')
						$gateways[] = 'WC_Gateway_Casso_Vietinbank';
					if (strtolower($account['bank_name']) == 'ocb')
						$gateways[] = 'WC_Gateway_Casso_OCB';
					if (strtolower($account['bank_name']) == 'tpbank')
						$gateways[] = 'WC_Gateway_Casso_TPbank';
					if (strtolower($account['bank_name']) == 'vietcombank')
						$gateways[] = 'WC_Gateway_Casso_Vietcombank';
					if (strtolower($account['bank_name']) == 'bidv')
						$gateways[] = 'WC_Gateway_Casso_BIDV';
					if (strtolower($account['bank_name']) == 'agribank')
						$gateways[] = 'WC_Gateway_Casso_Agribank';
				}
				// print_r ($gateways);
				return $gateways;
			});
		}
	}
	public function notice_if_not_woocommerce()
	{
		$class = 'notice notice-warning';

		$message = __(
			'Casso is not running because WooCommerce is not active. Please activate both plugins.',
			'casso-wordpress-plugin'
		);
		printf('<div class="%1$s"><p><strong>%2$s</strong></p></div>', $class, $message);
	}
	static function get_settings()
	{
		$settings = get_option('casso', self::$default_settings);
		$settings = wp_parse_args($settings, self::$default_settings);
		return $settings;
	}
	static function casso_oauth_get_settings()
	{
		$settings = get_option('casso_oauth', self::$casso_oauth_settings);
		$settings = wp_parse_args($settings, self::$casso_oauth_settings);
		return $settings;
	}
	static function casso_get_list_banks()
	{
		$banks = array(
			'acb' => 'ACB',
			'bidv' => 'BIDV',
			'mbbank' => 'MB Bank',
			'ocb' => 'OCB',
			'timoplus' => 'Timo Plus',
			'tpbank' => 'TPBank',
			'vietcombank' => 'Vietcombank',
			'vpbank' => 'VPBank',
			'vietinbank' => 'Vietinbank',
			'techcombank' => 'Techcombank',
			'agribank' => 'Agribank',
			'msb' => 'MSB',
			'eximbank' => 'Eximbank'
		);
		return $banks;
	}
	static function casso_get_list_bin()
	{
		$banks = array(
			'970416' => 'acb',
			'970418' => 'bidv',
			'970422' => 'mbbank',
			'970448' => 'ocb',
			'970454' => 'timoplus',
			'970423' => 'tpbank',
			'970436' => 'vietcombank',
			'970432' => 'vpbank',
			'970415' => 'vietinbank',
			'970407' => 'techcombank',
			'970405' => 'agribank',
			'970426' => 'msb',
			'970431' => 'eximbank',
		);
		return $banks;
	}
	static function casso_connect_status_banks()
	{
		$status = array(
			'0' => __('Inactive', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'),
			'1' =>  array(
				'0' => __('Active', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'),
				'1' => __('Trial', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'),
				'2' => __('Out of money', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang')
			)
		);
		return $status;
	}
	public function casso_add_settings_link($links)
	{
		$settings = array('<a href="' . admin_url('admin.php?page=casso') . '">' . __('Settings', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . '</a>');
		$links    = array_reverse(array_merge($links, $settings));

		return $links;
	}

	public function parse_order_id($des)
	{
		if ($this->settings['bank_transfer']['case_insensitive'] == 'yes') {
			$re = '/' . $this->settings['bank_transfer']['transaction_prefix'] . '\d+/m';
		} else {
			$re = '/' . $this->settings['bank_transfer']['transaction_prefix'] . '\d+/mi';
		}
		preg_match_all($re, $des, $matches, PREG_SET_ORDER, 0);
		if (count($matches) == 0)
			return null;
		// Print the entire match result
		$orderCode = $matches[0][0];
		$prefixLength = strlen($this->settings['bank_transfer']['transaction_prefix']);

		$orderId = intval(substr($orderCode, $prefixLength));
		return $orderId;
	}

	//ham de get url
	static function get_webhook_url()
	{
		return WC()->api_request_url(self::$webhook_route);
	}
	// public function getHeader()
	// {
	// 	$headers = array();

	// 	$copy_server = array(
	// 		'CONTENT_TYPE'   => 'Content-Type',
	// 		'CONTENT_LENGTH' => 'Content-Length',
	// 		'CONTENT_MD5'    => 'Content-Md5',
	// 	);
	// 	foreach ($_SERVER as $key => $value) {
	// 		if (substr($key, 0, 5) === 'HTTP_') {
	// 			$key = substr($key, 5);
	// 			if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
	// 				$key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
	// 				$headers[$key] = $value;
	// 			}
	// 		} elseif (isset($copy_server[$key])) {
	// 			$headers[$copy_server[$key]] = $value;
	// 		}
	// 	}

	// 	if (!isset($headers['Authorization'])) {
	// 		if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
	// 			$headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
	// 		} elseif (isset($_SERVER['PHP_AUTH_USER'])) {
	// 			$basic_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
	// 			$headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
	// 		} elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
	// 			$headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
	// 		}
	// 	}

	// 	return $headers;
	// }
	
	public function casso_oauth2_handler()
	{

		$txtBody = file_get_contents('php://input');
		// parse_str($_SERVER['QUERY_STRING'], $queries);
		$oauthCode = $_GET['code'];
		$oauthCode = base64_decode($oauthCode);
		$oauthCode  = substr($oauthCode, 5, -5);
		//echo $oauthCode;
		$resData = self::casso_oauth2_get_token($oauthCode);


		//get token from casso
		$resData = json_decode($resData);
		$accessToken = $resData->access_token;
		$settings = self::get_settings();
		$oauth_settings = self::casso_oauth_get_settings();
		$oauth_settings['refresh_token'] = $resData->refresh_token;
		$oauth_settings['access_token'] = $resData->access_token;
		$oauth_settings['expires_at'] = time() + $resData->expires_in;
		$oauth_settings['login_type'] = 1;
		$casso_get_list_bin =  CassoPayment::casso_get_list_bin();
		if (strlen($settings['bank_transfer']['secure_token']) <= 0) {
			$settings['bank_transfer']['secure_token'] = self::generate_random_string(16);
		}

		// delete all old webhook
		self::casso_oauth_force_delete_all_webhook($accessToken, self::get_webhook_url(), '', true);
		// // register new webhook
		$response_create = self::casso_oauth_create_webhook($accessToken, $settings, true);

		$response_create = json_decode($response_create);
		$oauth_settings['webhook_id'] = $response_create->data->id;


		$response_user_info = self::casso_oauth_get_user_infor($accessToken, '', true);
		//	$this->console_log($response_user_info);

		$response_user_info = json_decode($response_user_info);
		$oauth_settings['user_id'] = $response_user_info->data->user->id;
		$oauth_settings['email'] = $response_user_info->data->user->email;
		$oauth_settings['business_id'] = $response_user_info->data->business->id;
		$oauth_settings['business_name'] = $response_user_info->data->business->name;
		$accounts = array();
		foreach ($response_user_info->data->bankAccs as $value) {
			$account_with_bin = $value->bank->bin . '_' . $value->bankSubAccId;
			$account = array(
				"account_name" => $value->bankAccountName,
				"account_number" => $value->bankSubAccId,
				"bank_name" => $casso_get_list_bin[$value->bank->bin ?: $value->bank->codeName],
				"bin" => $value->bank->bin ?: null,
				"connect_status" => $value->connectStatus,
				"plan_status" => $value->planStatus,
				"is_show" => empty($settings['is_show_account'][$account_with_bin]) ? 'yes' : $settings['is_show_account'][$account_with_bin]
			);
			$accounts[] = $account;
		}
		$settings['bank_transfer_accounts'] = $accounts;
		update_option('casso', $settings);
		update_option('casso_oauth', $oauth_settings);
		header('Location: ' . admin_url('admin.php?page=casso'));
		die();
	}
	static public function generate_random_string($length = 10)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
	static public function casso_oauth_create_webhook($access_token, $settings, $isOAuth2 = false)
	{

		$codeAuth = $settings['bank_transfer']['authorization_code'];
		$response = null;
		$body = array(
			"webhook" =>  self::get_webhook_url(),
			"secure_token" => $settings['bank_transfer']['secure_token'],
			"income_only" => true,
		);

		if (!$isOAuth2) {
			$url  = CassoPayment::$CASSO_OAUTH_URL_V2 . '/webhooks';
			$args = array(
				'body'        => json_encode($body),
				'headers' => array(
					"content-type" => "application/json",
					"Authorization" => "Apikey " . $codeAuth
				)
			);
			$response = wp_remote_post($url, $args);
		} else  {
			$url  = CassoPayment::$CASSO_OAUTH_URL_V2 . '/webhooks';
			$args = array(
				'body'        => json_encode($body),
				'headers' => array(
					"content-type" => "application/json",
					"Authorization" => "Bearer " . $access_token
				)
			);
			$response = wp_remote_post($url, $args);
		}

		if (is_wp_error($response)) {
			return null;
		}
		if ($response['response']['code'] == 200 || $response['response']['code'] == 201) {
			$body     = wp_remote_retrieve_body($response);
			return $body;
		}
		return null;
	}
	
	static public function casso_oauth_get_user_infor($access_token, $code = '', $isOAuth2 = false)
	{
		$response = null;
		
		if (!$isOAuth2) {
			$url  = CassoPayment::$CASSO_OAUTH_URL_V2 . '/userInfo';
			$response = wp_remote_get($url, array(
				'headers' => array(
					"content-type" => "application/json",
					"User-Agent" => "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)",
					"Authorization" => "Apikey " . $code
				)
			));
		} else {
			$url  = CassoPayment::$CASSO_OAUTH_URL_V2 . '/userInfo';
			$response = wp_remote_get($url, array(
				'headers' => array(
					"content-type" => "application/json",
					"User-Agent" => "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)",
					"Authorization" => "Bearer " . $access_token
				)
			));
		}
		if (is_wp_error($response)) {
			return null;
		}
		if ($response['response']['code'] == 200 || $response['response']['code'] == 201) {
			$body     = wp_remote_retrieve_body($response);
			return $body;
		}
		return null;
	}

	static public function casso_oauth_force_delete_all_webhook($access_token, $webhook, $code = '', $isOAuth2 = false)
	{

		$response = null;
		
		if (!$isOAuth2) {
			$url = add_query_arg(array('webhook' => $webhook), CassoPayment::$CASSO_OAUTH_URL_V2 . '/webhooks');
			$response = wp_remote_request(
				$url,
				array(
					'method'     => 'DELETE',
					'headers' => array(
						"content-type" => "application/json",
						"User-Agent" => "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)",
						"Authorization" => "Apikey " . $code
					)
				)
			);
		} else {
			$url = add_query_arg(array('webhook' => $webhook), CassoPayment::$CASSO_OAUTH_URL_V2 . '/webhooks');
			$response = wp_remote_request(
				$url,
				array(
					'method'     => 'DELETE',
					'headers' => array(
						"content-type" => "application/json",
						"User-Agent" => "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)",
						"Authorization" => "Bearer " . $access_token
					)
				)
			);
		}

		if (is_wp_error($response)) {
			return null;
		}
		if ($response['response']['code'] == 200 || $response['response']['code'] == 201) {
			$body     = wp_remote_retrieve_body($response);
			return $body;
		}
		return null;
	}
	public function casso_payment_handler()
	{
		$txtBody = file_get_contents('php://input');
		$jsonBody = json_decode($txtBody); //convert JSON into array
		if (!$txtBody || !$jsonBody) {
			echo "Missing body";
			die();
		}
		if ($jsonBody->error != 0) {
			echo "An error occurred";
			die();
		}
		// $header = $this->getHeader();
		// print_r($_SERVER);
		$token = sanitize_key($_SERVER["HTTP_SECURE_TOKEN"]);
		if (strcasecmp($token, $this->settings['bank_transfer']['secure_token']) !== 0) {
			echo "Missing secure_token or wrong secure_token";
			die();
		}
		foreach ($jsonBody->data as $key => $transaction) {
			$des = $transaction->description;
			$order_id = $this->parse_order_id($des);
			if (is_null($order_id)) {
				echo ("Order ID not found from transaction content: " .wp_kses_post($des) . "\n");
				continue;
			}
			echo ("Start processing orders with transaction code " . wp_kses_post($order_id) . "...\n");
			$order = wc_get_order($order_id);
			if (!$order) {
				continue;
			}
			//echo(var_dump(wc_get_order_statuses()));
			$money = $order->get_total();
			$paid = $transaction->amount;
			$today = date_create(date("Y-m-d"));
			$date_transaction = date_create($transaction->when);
			$interval = date_diff($today, $date_transaction);
			if ($interval->format('%R%a') < -2) {
				# code...
				echo (__('Transaction is too old, not processed', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'));
				die();
			}
			$total = number_format($transaction->amount, 0);
			$order_note = sprintf(__('Casso announces received <b>%s</b> VND, content <B>%s</B> has been moved to <b>Account number %s</b>', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'), $total, $des, $transaction->subAccId);
			$order->add_order_note($order_note);

			// $order_note_overpay = "Casso thông báo <b>{$total}</b> VND, nội dung <b>$des</b> chuyển khoản dư vào <b>STK {$transaction->subAccId}</b>";
			$acceptable_difference = abs($this->settings['bank_transfer']['acceptable_difference']);
			if ($paid < $money  - $acceptable_difference) {
				$order->add_order_note(__('The order is underpaid so it is not completed', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'));
				$status_after_underpaid = $this->settings['order_status']['order_status_after_underpaid'];

				if ($status_after_underpaid && $status_after_underpaid != "wc-default") {
					$status = substr($this->settings['order_status']['order_status_after_underpaid'], 3);
					$order->update_status($status);
				}
			} else {
				$order->payment_complete();
				wc_reduce_stock_levels($order_id);
				$status_after_paid = $this->settings['order_status']['order_status_after_paid'];

				if ($status_after_paid && $status_after_paid != "wc-default") {
					$order->update_status($status_after_paid);
				}
				//NEU THANH TOAN DU THI GHI THEM 1 cai NOTE 
				if ($paid > $money + $acceptable_difference) {
					$order->add_order_note(__('Order has been overpaid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'));
				}
			}
			echo ("Transaction processing  " .wp_kses_post( $order_id) . " success\n");
		}
		die();
		//TODO: Nghiên cứu việc gửi mail thông báo đơn hàng thanh toán hoàn tất.
	}
	static function casso_oauth_get_token($code, $isOAuth2 = false)
	{
		$response = null;
		if ($isOAuth2) {
			$url  = self::$CASSO_OAUTH2_URL . '/token';
			$body = array(
				"grant_type" => 'refresh_token',
				"client_id" => self::$client_id,
				"redirect_uri" => self::$connect_uri,
				"refresh_token" => $code,
			);
			$args = array(
				'body'        => $body,
				'headers' => array(
					'Content-type' => 'application/x-www-form-urlencoded',
					'Authorization' => 'Basic' . base64_encode(self::$client_id . ':' . self::$client_secret)
				)
			);
			$response = wp_remote_post($url, $args);
		}

		if (is_wp_error($response)) {
			return null;
		}
		if ($response['response']['code'] == 200 || $response['response']['code'] == 201) {
			$body     = wp_remote_retrieve_body($response);
			return $body;
		}
		return null;
	}
	static function casso_oauth2_get_token($code)
	{

		$url  = self::$CASSO_OAUTH2_URL . '/token';
		$body = array(
			"grant_type" => 'authorization_code',
			"client_id" => self::$client_id,
			"redirect_uri" => self::$connect_uri,
			"code" => $code,
		);
		$args = array(
			'body'        => $body,
			'headers' => array(
				'Content-type' => 'application/x-www-form-urlencoded',
				'Authorization' => 'Basic' . base64_encode(self::$client_id . ':' . self::$client_secret)
			)
		);
		$response = wp_remote_post($url, $args);
		if (is_wp_error($response)) {
			return null;
		}
		if ($response['response']['code'] == 200 || $response['response']['code'] == 201) {
			$body     = wp_remote_retrieve_body($response);
			return $body;
		}
		return null;
	}
	function casso_load_plugin_textdomain()
	{
		load_plugin_textdomain($this->domain, false, dirname(plugin_basename(__FILE__))  . '/languages');
	}
}
