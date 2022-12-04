<?php
/*
*
* WC Base Payment Gateway
*
*/

if (!defined('ABSPATH')) exit;

if (!class_exists('WC_Payment_Gateway')) return;


abstract class WC_Base_Casso extends WC_Payment_Gateway
{
	abstract public function configure_payment();

	/**
	 * Array of locales
	 *
	 * @var array
	 */
	public $locale;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{

		$this->id                 = 'casso_up_' . $this->bank_id;
		$this->icon =  apply_filters('woocommerce_icon_' . $this->bank_id, plugins_url('../../assets/' . $this->bank_id . '.png', __FILE__));
		$this->has_fields         = false;
		$this->init_form_fields();
		$this->init_settings();
		// Define user set variables.
		$this->title        = $this->get_option('title');
		$this->description  = $this->get_option('description');
		$this->instructions = $this->get_option('instructions');
		$this->order_content = '';
		global $wp_session;
		// handling cache and order information
		if (!isset($wp_session['casso_banks_setting'])) {
			$this->casso_plugin_settings = CassoPayment::get_settings();
			$this->oauth_settings = CassoPayment::casso_oauth_get_settings();
			$wp_session['casso_banks_setting'] = $this->casso_plugin_settings;
		} else {
			$this->casso_plugin_settings = $wp_session['casso_banks_setting'];
		}
		// BACS account fields shown on the thanks page and in emails.
		$this->account_details =
			array_filter($this->casso_plugin_settings['bank_transfer_accounts'], function ($account, $k) {
				return $account['bank_name'] == $this->bank_id && $account['is_show'] == 'yes';
			}, ARRAY_FILTER_USE_BOTH);
		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
		add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);


		// Customer Emails.

	}
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __('Enable/Disable', 'woocommerce'),
				'type'    => 'checkbox',
				'label'   => __('Enable bank transfer', 'woocommerce'),
				'default' => 'yes',
			),
			'title'           => array(
				'title'       => __('Title', 'woocommerce'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
				'default'     => __('Transfer', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __('Description', 'woocommerce'),
				'type'        => 'textarea',
				'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
				'default'     => sprintf(__("Transfer money to our account<b> %s</b>. The order will be confirmed immediately after the transfer", 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'), $this->bank_name),
				//'default'     => __('Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'woocommerce'),
				'desc_tip'    => true,
			),
			'instructions'    => array(
				'title'       => __('Instructions', 'woocommerce'),
				'type'        => 'textarea',
				'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce'),
				'default'     => '',
				'desc_tip'    => true,
			)
		);
	}

	/**
	 * Generate account details html.
	 *
	 * @return string
	 */
	public function generate_account_details_html()
	{
		ob_start();
		$country = WC()->countries->get_base_country();
		$locale  = $this->get_country_locale();
		// Get sortcode label in the $locale array and use appropriate one.
		$sortcode = isset($locale[$country]['sortcode']['label']) ? $locale[$country]['sortcode']['label'] : __('Sort code', 'woocommerce');

?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e('Account details:', 'woocommerce'); ?></th>
			<td class="forminp" id="bacs_accounts">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
							<tr>
								<th class="sort">&nbsp;</th>
								<th><?php esc_html_e('Account name', 'woocommerce'); ?></th>
								<th><?php esc_html_e('Account number', 'woocommerce'); ?></th>
								<th><?php esc_html_e('Bank name', 'woocommerce'); ?></th>
							</tr>
						</thead>
						<tbody class="accounts">
							<?php
							$i = -1;
							if ($this->account_details) {
								foreach ($this->account_details as $account) {
									$i++;
									echo '<tr class="account">
										<td class="sort"></td>
										<td><input type="text" value="' . esc_attr(wp_unslash($account['account_name'])) . '" name="bacs_account_name[' . esc_attr($i) . ']" /></td>
										<td><input type="text" value="' . esc_attr($account['account_number']) . '" name="bacs_account_number[' . esc_attr($i) . ']" /></td>
										<td><input type="text" value="' . esc_attr(wp_unslash($account['bank_name'])) . '" name="bacs_bank_name[' . esc_attr($i) . ']" /></td>
									</tr>';
								}
							}
							?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="7"><a href="#" class="add button"><?php esc_html_e('+ Add account', 'woocommerce'); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e('Remove selected account(s)', 'woocommerce'); ?></a></th>
							</tr>
						</tfoot>
					</table>
				</div>
				<script type="text/javascript">
					jQuery(function() {
						jQuery('#bacs_accounts').on('click', 'a.add', function() {

							var size = jQuery('#bacs_accounts').find('tbody .account').length;

							jQuery('<tr class="account">\
									<td class="sort"></td>\
									<td><input type="text" name="bacs_account_name[' + size + ']" /></td>\
									<td><input type="text" name="bacs_account_number[' + size + ']" /></td>\
									<td><input type="text" name="bacs_bank_name[' + size + ']" /></td>\
								</tr>').appendTo('#bacs_accounts table tbody');

							return false;
						});
					});
				</script>
			</td>
		</tr>
<?php
		return ob_get_clean();
	}


	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page($order_id)
	{

		if ($this->instructions) {
			echo wp_kses_post(wpautop(wptexturize(wp_kses_post($this->instructions))));
		}
		// $this->console_log($this->account_details);
		global $wp_session;
		if (isset($wp_session['input_thank'])) {
		} else {
			$wp_session['input_thank'] = true;
			$this->bank_details($order_id, false);
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions($order, $sent_to_admin, $plain_text = false)
	{
		if (!$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('on-hold')) {
			if ($this->instructions) {
				echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
			}
			global $wp_session;
			if (isset($wp_session['input_thank'])) {
			} else {
				$wp_session['input_thank'] = true;
				$this->bank_details($order->get_id(), true);
			}
		}
	}

	/**
	 * Get bank details and place into a list format.
	 *
	 * @param int $order_id Order ID.
	 */
	private function bank_details($order_id = '', $is_sent_email = false)
	{
		// if (!$is_sent_email) {
		// 	$this->console_log($this->account_details);
		// }
		if (empty($this->account_details)) {
			return;
		}
		// Get order and store in $order.
		$order = wc_get_order($order_id);
		$order_status  = $order->get_status();
		$to = $order->get_billing_email();
		$subject = 'Thanh Toán đơn hàng';
		// Get the order country and country $locale.
		$country = $order->get_billing_country();
		$locale  = $this->get_country_locale();
		// Get sortcode label in the $locale array and use appropriate one.
		$sortcode = isset($locale[$country]['sortcode']['label']) ? $locale[$country]['sortcode']['label'] : __('Sort code', 'woocommerce');
		$bacs_accounts = $this->account_details; //apply_filters( 'woocommerce_bacs_accounts', $this->account_details, $order_id );
		$is_payment = false;
		if ("wc-{$order_status}" ==  $this->casso_plugin_settings['order_status']['order_status_after_paid']) {
			$is_payment = true;
		}
		if (!empty($bacs_accounts)) {
			$account_html = '';
			$has_details  = false;

			foreach ($bacs_accounts as $bacs_account) {
				$bacs_account = (object) $bacs_account;

				if ($bacs_account->account_name) {
					$account_html .= '<h3 class="wc-bacs-bank-details-account-name">' . wp_kses_post(wp_unslash($bacs_account->account_name)) . ':</h3>' . PHP_EOL;
				}
				$account_html .= '<ul class="wc-bacs-bank-details order_details bacs_details">' . PHP_EOL;
				// BACS account fields shown on the thanks page and in emails.
				$account_fields = apply_filters(
					'woocommerce_bacs_account_fields',
					array(
						'bank_name'      => array(
							'label' => __('Bank', 'woocommerce'),
							'value' => $bacs_account->bank_name,
						),
						'account_number' => array(
							'label' => __('Account number', 'woocommerce'),
							'value' => $bacs_account->account_number,
						),
						'account_name' => array(
							'label' => __('Account name', 'woocommerce'),
							'value' => $bacs_account->account_name,
						),
						'bin' => array(
							'label' => __('Bin', 'woocommerce'),
							'value' => $bacs_account->bin,
						),
						'amount'            => array(
							'label' => __('Amount', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'),
							'value' => number_format($order->get_total(), 0),
						),
						'content'            => array(
							'label' => __('Content', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'),
							'value' =>	$this->casso_plugin_settings['bank_transfer']['transaction_prefix'] . '' . $order_id,
						),
					),
					$order_id
				);
				$qrcode_url = "";
				$qrcode_page = "";
				$disabled = '';
				//check đã thah toán chưa
				if (!$is_payment) {


					// if (!$is_sent_email) {
					// 	$dataQR = $this->get_qrcode_vietqr($account_fields);
					// 	if (isset($dataQR)) {
					// 		$dataQR = json_decode($dataQR);
					// 		$qrcode_url = $dataQR->data->qrDataURL;
					// 	}
					// } else {
					$template = isset($this->casso_plugin_settings['bank_transfer']['vietqr_template'])? $this->casso_plugin_settings['bank_transfer']['vietqr_template']: "compact";
					$data = $this->get_qrcode_vietqr_img_url($account_fields, $template);
					$qrcode_url  = $data['img_url'];
					$qrcode_page = $data['pay_url'];
					// }
					if (empty($this->order_content)) {
						$order_content = __('I have already paid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
					} else {
						$order_content = $this->order_content;
					}
				} else {
					$order_content = __('You paid', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
					$disabled .= 'disabled';
				}
				$banks_list = CassoPayment::casso_get_list_banks();
				$account_fields['bank_name']['value'] = $banks_list[$account_fields['bank_name']['value']];
				foreach ($account_fields as $field) {
					if (!empty($field['value'])) {
						$has_details   = true;
					}
				}

				$account_html .= '</ul>';

				//hiển thị nút tải trên điện thoại và ko phải email
				$show_download  = wp_is_mobile();;
				if ($has_details) {
					$showPayment = '';
					if (!$is_payment && $this->casso_plugin_settings['bank_transfer']['viet_qr'] == 'yes' && (($is_sent_email == false) || $is_sent_email == true)) {
						$showPayment .= '
					<section class="woocommerce-casso-wordpress-plugin-qr-scan">

					<!-- QR TTILE-->
					<h2 class="wc-casso-wordpress-plugin-bank-details-heading" style="text-align: center; margin-top: 20px">' . __('Bank transfer QR code', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang')  . '</h2>


					<!-- QR IMAGE HERE-->
					<div style="">
						<div id="qrcode">
							<img src="' . esc_html($qrcode_url) . '"  alt="casso-wordpress-plugin QR Image" width="400px" style="display:block; margin: 0 auto;"/>
						</div>

						<!--buton download on email.-->
						<a style="max-width:200px; margin: 0 auto;background-color: limegreen; color:white; display:' . ($is_sent_email ? 'block' : 'none') . '" href="' . esc_html($qrcode_page) . '" target="_blank" >
							Tải QR Code             
						</a>

						<a id="downloadQR" download="casso-wordpress-plugin_' . esc_html($account_fields['account_number']['value']) . '.jpg"  href="' . esc_html($qrcode_url) . '">
							<button id="btnDownloadQR">
							<div style="width: 100%;display: flex;align-items: center;justify-content: flex-start;">	
							<svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M3 3H11V1H1V11H3V3Z" fill="white"/>
							<path d="M11 11V5H5V11H11ZM7 7H9V9H7V7Z" fill="white"/>
							<path d="M20.5 3H29V11H31V1H20.5V3Z" fill="white"/>
							<path d="M27 11V5H21V11H27ZM23 7H25V9H23V7Z" fill="white"/>
							<path d="M11 29H3V21H1V31H11V29Z" fill="white"/>
							<path d="M11 21H5V27H11V21ZM9 25H7V23H9V25Z" fill="white"/>
							<path d="M29 29H20.5V31H31V21H29V29Z" fill="white"/>
							<path d="M17 19H25V23H27V17H17V19Z" fill="white"/>
							<path d="M27 27V25H15V17H5V19H13V27H27Z" fill="white"/>
							<path d="M13 5H15V11H13V5Z" fill="white"/>
							<path d="M5 15H19V5H17V13H5V15Z" fill="white"/>
							<path d="M21 13H27V15H21V13Z" fill="white"/>
							<path d="M21 21H23V23H21V21Z" fill="white"/>
							<path d="M17 21H19V23H17V21Z" fill="white"/>
							</svg>	
							<div style="margin-left: 16px;text-align: left;">
							<span style="display:block; font-size:16px; font-weight:bold;">' . __('SAVE IMAGE QR', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang')  . '</span>
							<span style="font-size: 12px;"><i>' . __('Then open the banking App and scan the transfer QR', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang')  . '</i></span>
							</div>
							</div>
							</button>
						</a>
					</div>	
					</section>';
					}
					$showPayment .= '<section class="woocommerce-casso-wordpress-plugin-bank-details">
						<!-- BANK DETAIL TITLE-->
						<h2 class="wc-casso-wordpress-plugin-bank-details-heading" style="text-align: center;">' . esc_html__('Our bank details', 'woocommerce') . '</h2>';
					if (!$is_payment)
						$showPayment .= '<div><h4 style="color: #856404; max-width: 750px; margin: auto; margin-bottom: 20px; background-color: #ffeeba; padding: 15px; border-radius: 7px;">' .  sprintf(__("Please transfer the correct content <b style='font-size: 20px;'>%s</b> for we can confirm the payment", 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'), esc_html($this->casso_plugin_settings['bank_transfer']['transaction_prefix'] . '' . $order_id)) . '</h4></div>

								';
					else {
						$showPayment .= '<img src="' . plugins_url('../../assets/success-icon.png', __FILE__) . '"  style = "width: 100px; margin: 20px" id =""/>';
					}
					$showPayment .= '
						<!-- BANK DETAIL INFO TABLE-->
						<table class="table table-bordered" style="font-size: 15px;max-width: 800px;margin-left: auto;margin-right: auto;">
						<tbody>
						<tr class="" >
								<td class="text-right"  style="text-align: right;">
									<strong style="color: black;">' . __('Account name', 'woocommerce') . ':</strong>
									<br>
								</td>
								<td class="text-left payment-instruction" style="text-align: left;">
									<div>
										<span style="color: black;">' . esc_html($account_fields['account_name']['value']) . '</span>
										<br>
									</div>
								</td>
							</tr>
							<tr class="" style="background-color:#FBFBFB;">
								<td class="text-right"  style="text-align: right;">
									<strong style="color: black;">' . __('Account number', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . ':</strong>
								</td>
								<td class="text-left payment-instruction" style="text-align: left;">
									<span style="color: black;">' . esc_html($account_fields['account_number']['value']) . '</span>
								</td>
							</tr>
							<tr class="" style="">
								<td class="text-right" style="text-align: right;">
									<strong style="color: black;">' . __('Bank', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . ':</strong>
									<br>
								</td>
								<td class="text-left payment-instruction" style="text-align: left;">
									<div>
										<span style="color: black;">' . esc_html($account_fields['bank_name']['value']) . '</span>
										<br>
									</div>
								</td>
							</tr>
							<tr class="" style="">
								<td class="text-right"  style="text-align: right;">
									<strong style="color: black;">' . __('Amount', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . ':</strong>
									<br>
								</td>
								<td class="text-left payment-instruction" style="text-align: left;">
									<div ng-switch-when="vcb" class="ng-scope">
										<span style="color: black;">' . esc_html($account_fields['amount']['value']) . ' <sup>vnđ</sup></span>
										<br>
									</div>
								</td>
							</tr>
							<tr class="" >
								<td class="text-right" style="text-align: right;">
									<strong style="color: black;">' . __('Content', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . '*:</strong>
								</td>
								<td class="text-left payment-instruction" style="text-align: left;">
									<strong style="font-size: 20px;">
									' . esc_html($account_fields['content']['value']) . '
									</strong>
								</td>
							</tr>
						</tbody>
						</table>
						<center style="margin-top: 20px;font-size: 15px;">
						<form method="post" id = "form-submit-pay">
						<input name="bank_id" type="hidden" value="' . $account_fields['account_number']['value'] . '">
						<input name="order_id" type="hidden" value="' . $order_id . '">

						<img src="' . plugins_url('../../assets/clock.gif', __FILE__) . '"  style = "width: 100px; display:none;" id ="image_loading"/>
						<button  name="submit_paid" id="input_casso" style="margin-bottom: 20px;" onclick="fetchStatus()" type="button"
						class="button1"  >' . $order_content  . '</button>
						</form>
						</center>
						<h5 style="color: red; display: none;" id="noTransaction">' .  __("No matching transfers were found. The system is still checking the transaction", 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . '</h5>
					</section>
					<script>
						function fetchStatus()
						{
							if("wc-' . $order_status . '" == "' . $this->casso_plugin_settings['order_status']['order_status_after_paid'] . '"){
								return;
							}
							document.getElementById("input_casso").disabled = true;
							document.getElementById("image_loading").style.display = "block";
							let timeTemp = 0;
							jQuery.ajax({
								url : "' . site_url() . '/wp-admin/admin-ajax.php?action=fetch_sync_order_casso&bank_id=' . esc_html($account_fields['account_number']['value']) . '",
								type : "post",      
								error : function(response){
								},
								success : function( response ){
									console.log(response);
								}
							});
							let fetchInterval = setInterval(function(){ 	
								jQuery.ajax({
									url : "' . site_url() . '/wp-admin/admin-ajax.php?action=fetch_order_status_casso&order_id=' . esc_html($order_id) . '",
									type : "post",      
									error : function(response){
									},
									success : function( response ){
										if(response == "' .esc_html( $this->casso_plugin_settings['order_status']['order_status_after_paid']) . '"){
											window.location.reload(false);
										}
									}
								});
								if(timeTemp == 60000){ 
									document.getElementById("noTransaction").style.display = "block";
									document.getElementById("image_loading").style.display = "none";
								}
								if(timeTemp >= 120000){ 
									document.getElementById("input_casso").disabled = false;
									clearInterval(fetchInterval);
								}
								timeTemp = timeTemp + 3000;
							}, 3000);
						}
					</script>
					<style>
					  	#image_loading{

							margin-left: auto;
							margin-right: auto;
							width: 35%;  
						}
						#downloadQR{
							z-index:333;
							position: fixed;
							left: 0;
							right: 0;
							bottom: 0;
							display:' . ($show_download ? 'block' : 'none') . '
						}
						#btnDownloadQR{
							width:100%;
							border-radius: 0;
							padding-left: 10px !important;
							padding-right: 10px !important;
							border-color: #0274be;
							background-color: #0274be;
							color: #ffffff;
							line-height: 1;
						}
						td{
							width: 25% ;
						}
						#qrcode canvas {
							border: 2px solid #ccc;
							padding: 20px;
						}
						.woocommerce-casso-wordpress-plugin-qr-scan{
							text-align: center;
							margin-top: 0px;
						}
						.woocommerce-casso-wordpress-plugin-bank-details{
							text-align: center;
							margin-top: 10px;
						}
					</style>';
					// if (!$is_payment && !$is_sent_email) {
					// 	$arrays = array(
					// 		"api_sync_orders" => site_url() . '/wp-admin/admin-ajax.php?action=fetch_sync_order_casso&bank_id=' . esc_html($account_fields['account_number']['value']),
					// 		"order_status_after_paid" => $this->casso_plugin_settings['order_status']['order_status_after_paid'],
					// 		"amount" => (int)preg_replace("/([^0-9\\.])/i", "", $account_fields['amount']['value']),
					// 		"account_number" => $account_fields['account_number']['value'],
					// 		"account_name" => $account_fields['account_name']['value'],
					// 		"bank_name" => $account_fields['bank_name']['value'],
					// 		"content" => $account_fields['content']['value'],
					// 		"api_check_orders" => site_url() . '/wp-admin/admin-ajax.php?action=fetch_order_status_casso&order_id=' . $order_id
					// 	);
					// 	$codebase = base64_encode(json_encode($arrays));
					// 	$showPayment = '<div class="thank_you_additional_content__casso"></div>
					// <script src="https://pay.casso.vn/casso-pay/casso-plugin.js?p=' . $codebase . '"></script>
					// ';
					// }

					echo $showPayment;
					if ($is_sent_email) {
						echo '
					<style>
						.table-bordered {
							border: 1px solid rgba(0,0,0,.1);
						}
					</style>
					';
					}
				}
			}
		}
	}
	public function console_log($output, $with_script_tags = true)
	{
		// $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) .
		// 	');';
		// if ($with_script_tags) {
		// 	$js_code = '<script>' . $js_code . '</script>';
		// }
		// echo $js_code;
	}
	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id)
	{

		$order = wc_get_order($order_id);

		if ($order->get_total() > 0) {
			// Mark as on-hold (we're awaiting the payment).
			$order->update_status(apply_filters('woocommerce_bacs_process_payment_order_status', 'on-hold', $order), __('Awaiting BACS payment', 'woocommerce'));
		} else {
			$order->payment_complete();
		}
		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url($order),
		);
	}
	/**
	 * Get country locale if localized.
	 *
	 * @return array
	 */
	public function get_country_locale()
	{

		if (empty($this->locale)) {

			// Locale information to be used - only those that are not 'Sort Code'.
			$this->locale = apply_filters(
				'woocommerce_get_bacs_locale',
				array(
					'AU' => array(
						'sortcode' => array(
							'label' => __('BSB', 'woocommerce'),
						),
					),
					'CA' => array(
						'sortcode' => array(
							'label' => __('Bank transit number', 'woocommerce'),
						),
					),
					'IN' => array(
						'sortcode' => array(
							'label' => __('IFSC', 'woocommerce'),
						),
					),
					'IT' => array(
						'sortcode' => array(
							'label' => __('Branch sort', 'woocommerce'),
						),
					),
					'NZ' => array(
						'sortcode' => array(
							'label' => __('Bank code', 'woocommerce'),
						),
					),
					'SE' => array(
						'sortcode' => array(
							'label' => __('Bank code', 'woocommerce'),
						),
					),
					'US' => array(
						'sortcode' => array(
							'label' => __('Routing number', 'woocommerce'),
						),
					),
					'ZA' => array(
						'sortcode' => array(
							'label' => __('Branch code', 'woocommerce'),
						),
					),
				)
			);
		}

		return $this->locale;
	}
	public function get_qrcode_vietqr_img_url($account_fields, $template)
	{
		$accountNo = $account_fields['account_number']['value'];
		$accountName = $account_fields['account_name']['value'];
		$acqId = $account_fields['bin']['value'];
		$addInfo = $account_fields['content']['value'];
		$amount = (int)preg_replace("/([^0-9\\.])/i", "", $account_fields['amount']['value']);
		// $format = "vietqr_net_2";
		$template = $template ? $template : "vietqr_net_2";
		$img_url = "https://img.vietqr.io/image/{$acqId}-{$accountNo}-{$template}.jpg?amount={$amount}&addInfo={$addInfo}&accountName={$accountName}";
		$pay_url = "https://api.vietqr.io/{$acqId}/{$accountNo}/{$amount}/{$addInfo}";
		return array(
			"img_url" => $img_url,
			"pay_url" => $pay_url,
		);
	}
	public function get_description()
	{
		$des = apply_filters('woocommerce_gateway_description', $this->description, $this->id);
		$des .= __(" <div class='power_by'>Automatic transaction checking by Casso Robot</div>", 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
		return $des;
	}
}
