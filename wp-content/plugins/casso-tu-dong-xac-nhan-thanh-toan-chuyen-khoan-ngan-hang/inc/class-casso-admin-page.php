<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Create the admin page under wp-admin -> WooCommerce -> Casso
 *
 * @author   Casso Team
 * @since    
 *
 */
class Casso_Admin_Page
{

	/**
	 * @var string The message to display after saving settings
	 */
	var $message = '';
	/**
	 * casso_Admin_Page constructor.
	 */
	public function __construct()
	{

		$this->casso_get_list_banks =  CassoPayment::casso_get_list_banks();
		$this->casso_get_list_bin =  CassoPayment::casso_get_list_bin();
		$this->casso_get_status =  CassoPayment::casso_connect_status_banks();
		$this->settings = CassoPayment::get_settings();
		$this->oauth_settings = CassoPayment::casso_oauth_get_settings();
		if (isset($_REQUEST['oauth2_status'])) {
			$this->disconnectOAuth2();
		}
		if (isset($_REQUEST['casso_nonce']) && isset($_REQUEST['action']) && 'casso_save_settings' == $_REQUEST['action']) {
			$this->save_settings();
		}
		add_action('admin_menu', array($this, 'register_submenu_page'));
	}

	/**
	 * Save settings for the plugin
	 */
	public function save_settings()
	{
		if (wp_verify_nonce($_REQUEST['casso_nonce'], 'casso_save_settings') && is_array($_REQUEST['settings'])) {
			$settings = $this->sanitize_setting($_REQUEST['settings']);

			// $qr_template_arr = array('compact', 'compact2', 'qr_only');

			// if (!in_array($settings['bank_transfer']['vietqr_template'], $qr_template_arr))
			// {
			// 	$settings['bank_transfer']['vietqr_template'] = 'compact';
			// }

			if (is_numeric($settings['bank_transfer']['acceptable_difference'])) 
			{
				$settings['bank_transfer']['acceptable_difference'] = (string)((int)$settings['bank_transfer']['acceptable_difference']);
			}
			else 
			{
				$settings['bank_transfer']['acceptable_difference'] = '1000';
			}

			$settings['bank_transfer_accounts'] = $this->settings['bank_transfer_accounts'];
			
			// $this->console_log($this->settings);
			if (strlen($this->settings['bank_transfer']['secure_token']) <= 0) {
				$settings['bank_transfer']['secure_token'] = CassoPayment::generate_random_string(16);
			} else {
				$settings['bank_transfer']['secure_token'] = $this->settings['bank_transfer']['secure_token'];
			}

			$temp = $settings['bank_transfer']['authorization_code_force_delete'];
			unset($settings['bank_transfer']['authorization_code_force_delete']);
			// Xoá kí tự đặc biệt và xóa bớt nếu dài quá, xóa khoảng trắng
			$settings['bank_transfer']['transaction_prefix'] = $this->clean_prefix($settings['bank_transfer']['transaction_prefix']);
			update_option('casso', $settings);
			$settings['bank_transfer']['authorization_code_force_delete'] = $temp;
			// xử lí webhook!
			$this->message = $this->casso_oauth_process_webhook($settings);
			// Message for use
			$this->message .=
				'<div class="updated notice"><p><strong>' .
				__('Settings saved', 'casso') .
				'</p></strong></div>';
		} else {

			$this->message =
				'<div class="error notice"><p><strong>' .
				__('Can not save settings! Please refresh this page.', 'casso') .
				'</p></strong></div>';
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
	 * Register the sub-menu under "WooCommerce"
	 * Link: http://my-site.com/wp-admin/admin.php?page=casso
	 */
	public function register_submenu_page()
	{
		add_submenu_page(
			'woocommerce',
			__('Casso Settings', 'casso'),
			'Casso',
			'manage_options',
			'casso',
			array($this, 'admin_page_html')
		);
	}

	/**
	 * Generate the HTML code of the settings page
	 */
	public function admin_page_html()
	{
		$urlConnectOauth = CassoPayment::$connect_uri . '/oauth2-login';
		$dataQuery = base64_encode(json_encode(array(
			"redirect_uri" =>  WC()->api_request_url(CassoPayment::$webhook_oauth2),
			"client_id" => CassoPayment::$client_id,
		)));
		$urlConnectOauth .= '?code=' . $dataQuery;


		// check user capabilities
		if (!current_user_can('manage_options')) {
			return;
		}
		$settings = CassoPayment::get_settings();
?>
		<div class="wrap">
			<h1><?php esc_html(get_admin_page_title()); ?></h1>
			<form name="woocommerce_for_vietnam" method="post">
				<?php echo wp_kses_post($this->message) ?>
				<input type="hidden" id="action" name="action" value="casso_save_settings">
				<input type="hidden" id="casso_nonce" name="casso_nonce" value="<?php echo wp_create_nonce('casso_save_settings') ?>">
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><?php echo __('Enable/Disable', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?></th>
							<td>
								<input name="settings[bank_transfer][enabled]" type="hidden" value="no">
								<input name="settings[bank_transfer][enabled]" type="checkbox" id="bank_transfer" value="yes" <?php if ('yes' == $settings['bank_transfer']['enabled'])
																																	echo 'checked="checked"' ?>>
								<label for="bank_transfer" style="font-size: 13px; font-style: oblique;"><?php echo __('Turn on bank transfer', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?></label>
								<br />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo __('VietQR', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?></th>
							<td>
								<input name="settings[bank_transfer][viet_qr]" type="hidden" value="no">
								<input name="settings[bank_transfer][viet_qr]" type="checkbox" id="bank_transfer" value="yes" <?php if (!empty($settings['bank_transfer']['viet_qr']) && 'yes' == $settings['bank_transfer']['viet_qr'])
																																	echo 'checked="checked"' ?>>
								<label for="bank_transfer" style="font-size: 13px; font-style: oblique;"><?php echo __('Enable QR code display mode VietQR', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?></label>
								<br />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo __('VietQR Template', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>
							<td>
								<input name="settings[bank_transfer][vietqr_template]" type="text" value="<?php echo  isset($settings['bank_transfer']['vietqr_template']) ? $settings['bank_transfer']['vietqr_template'] : "compact"; ?>">
								<label for="bank_transfer" style="font-size: 13px; font-style: oblique;"><?php echo __('Access <a target="_blank" href="https://my.vietqr.io?ref=woo">my.vietqr.io</a> to make your own VietQR Template', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></label>
							</td>
						</tr>


						<tr style="<?php if ($this->oauth_settings['login_type'] == 1) echo "display: none;" ?>">
							<th scope="row"><?php echo __('Link Casso', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th><?php echo __('Set up a link with Casso', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?>
							<td>
								<a type="button" href="<?php echo $urlConnectOauth; ?>" style="border: none; background-color: #056fca; color: white; border-radius: 5px; padding: 6px; min-width: 210px !important; cursor:pointer;text-decoration: none;"><?php echo __('Set up automatic Casso link', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?></a>
								<?php echo __('or', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?>
								<a style='text-decoration: none;' href='http://www.google.com' onclick="return showAuthorizarion()"><?php echo __('Click here to show manual configuration', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?></a>


							</td>
						</tr>

						<tr style="<?php if ($this->oauth_settings['login_type'] != 1) echo "display: none;" ?>">
							<th scope="row"><?php echo __('Link Casso', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>
							<td>
								<a type="button" href="?page=casso&oauth2_status=false" style="    border: 1px solid;padding: 7px;border-radius: 3px;text-decoration: none;"><?php echo __('Disconnect', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?></a>
								<p class="description" style="margin-top: 10px;margin-left: 2px;">
									Connected as <?php echo get_bloginfo('name'); ?>
								</p>
							</td>
						</tr>


						<tr style=" <?php if (empty($settings['bank_transfer']['authorization_code'])) echo 'display: none;';
									else echo '';  ?>" id='link_authorization'>
							<th scope="row"><?php echo __('Authorization code', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>
							<td>
								<input id='authorization_code' name="settings[bank_transfer][authorization_code]" style='min-width: 310px;' type="text" style='min-width: 200px;' value="<?php echo  $settings['bank_transfer']['authorization_code']; ?>"> <a href="https://casso.vn/plugin-ket-noi-ngan-hang/" onclick="window.open(this.href); return false;" onkeypress="window.open(this.href); return false;" style="margin-left: 5px;text-decoration: none;">Hướng dẫn lấy Casso Auth Code</a>
								<input id='authorization_code_force_delete' name="settings[bank_transfer][authorization_code_force_delete]" type="text" style='display: none;' value="<?php echo  $settings['bank_transfer']['authorization_code']; ?>">
								</br>
								<input type="submit" name="submit" id="submit" class="button button-primary" value="Liên kết" style="border: none; background-color: #056fca; color: white; border-radius: 5px; min-width: 110px !important;margin-top: 8px;">
								<button onclick="handleUnlink()" type="" name="submit" id="submit" class="button button-primary" value="" style="margin-left: 5px; border: none; background-color: #0000007a; color: white; border-radius: 5px; min-width: 95px !important;margin-top: 8px;">Hủy liên kết</button>
							</td>
						</tr>





						<tr>
							<th scope="row"><?php echo __('Connection Information', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>
							<td>
								<button id='banks_list_user_button' onclick="showDetailBanks()" type="button" style="border: none; cursor:pointer; <?php if (empty($settings['bank_transfer']['authorization_code']) && $this->oauth_settings['login_type'] == 0) echo 'background-color: #af1818;';
																																					else echo 'background-color: #056fca;'  ?> color: white; border-radius: 5px; padding: 6px; min-width: 125px !important; margin-bottom: 10px;"><?php if (empty($settings['bank_transfer']['authorization_code']) && $this->oauth_settings['login_type'] == 0) echo __('No connection', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
																																																																									else echo __('Show details', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?></button>
								<div id='banks_list_user' style="padding: 20px; background-color: #bdbdbd; color: black; border: 1px solid; max-width: 500px; border-radius: 10px; display: none">
									<?php if (empty($settings['bank_transfer_accounts'])) __('No banks found', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?>
									<ul style="<?php if (empty($settings['bank_transfer_accounts'])) echo "display: none" ?>">
										<li><b><?php echo __('Bussiness name', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?>: </b><?php echo esc_html($this->oauth_settings['business_id']) . ' | ' . esc_html($this->oauth_settings['business_name']) ?></li>
										<li><b><?php echo __('User', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?>: </b><?php echo esc_html($this->oauth_settings['user_id']) . ' | ' . esc_html($this->oauth_settings['email']) ?></li>
										<li><b><?php echo __('List of bank accounts', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?>: </b>
											<ul style="padding: 5px;">
												<?php
												$html = '';
												$i = 0;
												foreach ($settings['bank_transfer_accounts'] as $account) {
													$i++;

													if (!isset($this->casso_get_list_banks[$account['bank_name']])){
														continue;
													}

													$color = 'color: red;';
													if ($account['connect_status']) 
													{
														$color = 'color: #127b00;';
													}

													$html .= '<li style="margin-left: 20px; margin-bottom: 2px;">' . $i . '. ' . $this->casso_get_list_banks[$account['bank_name']] . ' | ' . $account['account_number'] . ' | ' . $account['account_name'] . ' | <span style ="' . $color . '">' . __($this->casso_get_status[$account['connect_status']] == 0 ? $this->casso_get_status[$account['connect_status']] : $this->casso_get_status[$account['connect_status']][$account['plan_status']], 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') . '</span></li>';
												}
												
												$allowed_html = array(
													'input' => array(
														'type'      => array(),
														'name'      => array(),
														'value'     => array(),
														'checked'   => array()
													),
													'li' => array(
														'style' => array(),
													),
													'span'=> array(
														'style' => array()
													)
												);
												
												echo wp_kses($html,$allowed_html);	
												?>
											</ul>
										</li>
										<li><b><?php echo __('Security Key', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?>: </b>******</li>
										<li><b><?php echo __('Webhook URL', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?>: </b><?php echo esc_html($this->get_webhook_url()); ?></li>
									</ul>
								</div>
							</td>
							<td></td>
						</tr>


						<tr style="<?php if (empty($settings['bank_transfer']['authorization_code']) && $this->oauth_settings['login_type'] == 0) echo 'display: none' ?>">
							<th scope="row"><?php echo __('Account on/off', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>
							<td style="padding: inherit;">
								<ul>
									<?php
									$html = '';
									$i = 0;
									foreach ($settings['bank_transfer_accounts'] as $account) {
										$account_with_bin = $account['bin'] . '_' . $account['account_number'];
										$i++;
										$hasCheck = 'checked = checked';
										if (isset($account['is_show']) && $account['is_show'] == 'no') {
											$hasCheck = '';
										} 
										if (!isset($this->casso_get_list_banks[$account['bank_name']])){
											continue;
										}

										$html .= '<li style="margin-left: 20px; margin-bottom: 8px;">
										
										<input name="settings[is_show_account][' . $account_with_bin . ']" type="hidden" value="no">
										<input name="settings[is_show_account][' . $account_with_bin . ']" type="checkbox" id="bank_transfer" value="yes"  ' . $hasCheck . '>
										
											
										' . $this->casso_get_list_banks[$account['bank_name']] . ' - ' . $account['account_number'] . ' - ' . ($account['account_name'] ?: __('Updating account name', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'));
									}
									$allowed_html = array(
										'input' => array(
											'type'      => array(),
											'name'      => array(),
											'value'     => array(),
											'checked'   => array()
										),
										'li' => array(
											'style' => array(),
										)
									);
									
									echo wp_kses($html,$allowed_html);
									?>

								</ul>
							</td>
						</tr>


						<tr style="display: none;">
							<th scope="row"><?php echo __('Webhook URL', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>
							<td>
								<input name="settings[bank_transfer][webhook]" readonly type="text" style='min-width: 400px;' value="<?php echo $this->get_webhook_url(); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo __('Transaction prefix', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>
							<td>
								<input name="settings[bank_transfer][transaction_prefix]" type="text" value="<?php echo  $settings['bank_transfer']['transaction_prefix']; ?>">
								<label for="bank_transfer" style="font-size: 13px; font-style: oblique;"><?php echo __('Maximum 15 characters, no spaces and no special characters. If contained, it will be deleted', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo __('Turn on Case Sensitivity', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>
							<td>
								<input name="settings[bank_transfer][case_insensitive]" type="hidden" value="no">
								<input name="settings[bank_transfer][case_insensitive]" type="checkbox" id="bank_transfer" value="yes" <?php if ('yes' == $settings['bank_transfer']['case_insensitive']) echo 'checked="checked"';	?>>
								<label for="bank_transfer" style="font-size: 13px; font-style: oblique;"><?php echo __('Turn on Case Sensitivity', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></label>
								<br />
							</td>
						</tr>

						<tr>
							<th scope="row"><?php echo __('Acceptance difference', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>
							<td>
								<input name="settings[bank_transfer][acceptable_difference]" type="text" value="<?php echo  $settings['bank_transfer']['acceptable_difference']; ?>">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo __('Status after full payment or balance:', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>

							<td>
								<select name="settings[order_status][order_status_after_paid]" id="order_status_after_paid">
									<?php
									foreach ($this->casso_get_order_statuses_after_paid() as $key => $value) {
										if ($key == $settings['order_status']['order_status_after_paid'])
											echo '<option value="' . esc_attr($key) . '" selected>' . esc_attr($value) . '</option>';
										else 
											echo '<option value="' . esc_attr($key) . '" >' . esc_attr($value) . '</option>';
									}
									?>
								</select>
							</td>
						<tr>
						<tr>
							<th scope="row"><?php echo  __('Status if payment is missing:', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang') ?></th>

							<td>
								<select name="settings[order_status][order_status_after_underpaid]" id="order_status_after_underpaid">
									<?php
									foreach ($this->casso_get_order_statuses_after_underpaid() as $key => $value) {
										if ($key == $settings['order_status']['order_status_after_underpaid'])
											echo '<option value="' . esc_attr($key) . '" selected>' . esc_attr($value) . '</option>';
										else echo '<option value="' . esc_attr($key) . '">' . esc_attr($value) . '</option>';
									}
									?>
								</select>
							</td>
						<tr>

					</tbody>
				</table>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
				</p>

			</form>
			<div id="casso-admin-footer" style="border: 1px dotted; padding: 5px;">
				<?php
				printf(
					__('Wanna get support or give feedback? Please <a href="%1$s">rate Casso</a> or post questions <a href="%2$s">in the forum</a>!', 'casso'),
					'https://wordpress.org/support/plugin/casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang/reviews/',
					'https://wordpress.org/plugins/casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang/'
				)
				?>
			</div>
		</div>
		<script type="text/javascript">
			function showAuthorizarion() {
				document.getElementById("link_authorization").style.display = "revert";
				return false;
			}

			function showDetailBanks() {
				var dots = document.getElementById("banks_list_user");
				var button = document.getElementById("banks_list_user_button");

				if (dots.style.display === "none") {
					dots.style.display = "block";
					button.innerHTML = "<?php echo __('Show less', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?>"
				} else {
					dots.style.display = "none";
					button.innerHTML = "<?php if (empty($settings['bank_transfer']['authorization_code']) && $this->oauth_settings['login_type'] == 0) echo __('No connection', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
										else echo __('Show details', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang'); ?>";
				}


			}

			function handleUnlink() {
				document.getElementById("authorization_code").value = "";
			}
		</script>
		<!-- #wrap ->
        <?php
	}
	public function disconnectOAuth2()
	{
		if ($_REQUEST['oauth2_status'] == 'true' || $this->oauth_settings['login_type'] == 0) return;
		$response_token = CassoPayment::casso_oauth_get_token($this->oauth_settings['refresh_token'], $this->oauth_settings['login_type']);
		$response_token = json_decode($response_token);

		CassoPayment::casso_oauth_force_delete_all_webhook($response_token->access_token, $this->settings['bank_transfer']['webhook'], $this->oauth_settings['refresh_token'], $this->oauth_settings['login_type']);
		$accounts_emp = array();
		$settings['bank_transfer_accounts'] = $accounts_emp;
		update_option('casso_oauth', $accounts_emp);
		update_option('casso', $settings);
		// header('Location: ' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '?page=casso');
		header('Location: ' . admin_url('admin.php?page=casso'));

		die();
	}
	static function get_webhook_url()
	{
		return WC()->api_request_url(CassoPayment::$webhook_route);
	}

	public function casso_get_order_statuses_after_paid()
	{
		$wooDefaultStatuses = array(
			"wc-pending",
			"wc-processing",
			"wc-on-hold",
			// "wc-completed",
			"wc-cancelled",
			"wc-refunded",
			"wc-failed",
			// "wc-paid",
			"wc-underpaid"
		);
		$statuses =  wc_get_order_statuses();
		$statuses['wc-default'] = __('Default', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
		for ($i = 0; $i < count($wooDefaultStatuses); $i++) {
			$statusName = $wooDefaultStatuses[$i];
			if (isset($statuses[$statusName])) {
				unset($statuses[$statusName]);
			}
		}
		return $statuses;
	}

	public function casso_get_order_statuses_after_underpaid()
	{
		$wooDefaultStatuses = array(
			"wc-pending",
			// "wc-processing",
			"wc-on-hold",
			"wc-completed",
			"wc-cancelled",
			"wc-refunded",
			"wc-failed",
			"wc-paid",
			// "wc-underpaid"
		);
		$statuses =  wc_get_order_statuses();
		$statuses['wc-default'] =  __('Default', 'casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang');
		for ($i = 0; $i < count($wooDefaultStatuses); $i++) {
			$statusName = $wooDefaultStatuses[$i];
			if (isset($statuses[$statusName])) {
				unset($statuses[$statusName]);
			}
		}
		return $statuses;
	}

	private function casso_oauth_process_webhook($settings)
	{
		$AuthCode = $settings['bank_transfer']['authorization_code'];
		if (empty($AuthCode) && $this->oauth_settings['login_type'] == 0) {
			$accounts_emp = array();
			$settings['bank_transfer_accounts'] = $accounts_emp;
			update_option('casso_oauth', $accounts_emp);
			update_option('casso', $settings);
			if (!empty($settings['bank_transfer']['authorization_code_force_delete'])) {
				$response_token_temp = null;

				if (strlen($settings['bank_transfer']['authorization_code_force_delete']) <= 36) {
					$response_token_temp = CassoPayment::casso_oauth_get_token($settings['bank_transfer']['authorization_code_force_delete']);
					if (empty($response_token_temp)) {
						return;
					}
					$response_token_temp = json_decode($response_token_temp);
				}
				$token_delete = null;

				if (!empty($response_token_temp->access_token)) {
					$token_delete = $response_token_temp->access_token;
				}
				CassoPayment::casso_oauth_force_delete_all_webhook($token_delete, $settings['bank_transfer']['webhook'], $settings['bank_transfer']['authorization_code_force_delete']);
			}
			header("Refresh:0");
			return;
		}
		$loginType = false;
		if ($this->oauth_settings['login_type'] == 1) {
			$loginType = true;
			$AuthCode = $this->oauth_settings['refresh_token'];
		}
		$response_token = null;

		if (strlen($AuthCode) <= 36) {
			$response_token = CassoPayment::casso_oauth_get_token($AuthCode, $loginType);
			if (empty($response_token)) {
				return '<div class="error notice"><p><strong>' .
					__('Webhook creation failed', 'casso') .
					'</p></strong></div>';
			}

			//get token from casso
			$response_token = json_decode($response_token);
		}
		if (empty($response_token->access_token)) {
			$this->oauth_settings['refresh_token'] = null;
			$this->oauth_settings['access_token'] = null;
			$this->oauth_settings['expires_at'] = 0;
		} else {
			$this->oauth_settings['refresh_token'] = $loginType ? $AuthCode : $response_token->refresh_token;
			$this->oauth_settings['access_token'] = $response_token->access_token;
			$this->oauth_settings['expires_at'] = time() + $response_token->expires_in;
		}
		// delete all old webhook
		CassoPayment::casso_oauth_force_delete_all_webhook($this->oauth_settings['access_token'], $settings['bank_transfer']['webhook'], $AuthCode, $loginType);
		// register new webhook
		$response_create = CassoPayment::casso_oauth_create_webhook($this->oauth_settings['access_token'], $settings, $loginType);

		if (empty($response_create)) {
			return '<div class="error notice"><p><strong>' .
				__('Webhook creation failed', 'casso') .
				'</p></strong></div>';
		}
		$response_create = json_decode($response_create);
		$this->oauth_settings['webhook_id'] = $response_create->data->id;

		$response_user_info = CassoPayment::casso_oauth_get_user_infor($this->oauth_settings['access_token'], $AuthCode, $loginType);

		if (empty($response_user_info)) {
			return '<div class="error notice"><p><strong>' .
				__('Webhook creation failed', 'casso') .
				'</p></strong></div>';
		}
		$response_user_info = json_decode($response_user_info);
		$this->oauth_settings['user_id'] = $response_user_info->data->user->id;
		$this->oauth_settings['email'] = $response_user_info->data->user->email;
		$this->oauth_settings['business_id'] = $response_user_info->data->business->id;
		$this->oauth_settings['business_name'] = $response_user_info->data->business->name;
		$accounts = array();
		foreach ($response_user_info->data->bankAccs as $value) {
			$account_with_bin = $value->bank->bin . '_' . $value->bankSubAccId;
			$account = array(
				"account_name" => $value->bankAccountName,
				"account_number" => $value->bankSubAccId,
				"bank_name" => $this->casso_get_list_bin[$value->bank->bin ?: $value->bank->codeName],
				"bin" => $value->bank->bin ?: null,
				"connect_status" => $value->connectStatus,
				"plan_status" => $value->planStatus,
				"is_show" => empty($settings['is_show_account'][$account_with_bin]) ? 'yes' : $settings['is_show_account'][$account_with_bin]
			);
			$accounts[] = $account;
		}
		$settings['bank_transfer_accounts'] = $accounts;
		update_option('casso', $settings);
		update_option('casso_oauth', $this->oauth_settings);
		return '<div class="updated notice"><p><strong>' .
			__('Successful webhook registration', 'casso') .
			'</p></strong></div>';
	}
	public function clean_prefix($string)
	{
		$string = str_replace(' ', '', $string); // Replaces all spaces with hyphens.
		if (strlen($string) > 15) {
			$string = substr($string, 0, 15);
		}
		return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
	}

	public function sanitize_acceptable_amount($acceptable_different) 
	{
		$acceptable_different = str_replace(' ', '', $acceptable_different); // Replaces all spaces with hyphens.
		return preg_replace('/[^0-9]/', '', $acceptable_different); // Removes special chars.
	}

	public function sanitize_vietqr_template($template)
	{
		$template = str_replace(' ', '', $template); // Replaces all spaces with hyphens.
		return preg_replace('/[^a-z0-9_]/', '', $template); // Removes special chars.
	}

	public function sanitize_setting($settings) 
	{
		$settings['bank_transfer']['acceptable_difference'] = $this->sanitize_acceptable_amount($settings['bank_transfer']['acceptable_difference']);
		$settings['bank_transfer']['vietqr_template'] = $this->sanitize_vietqr_template($settings['bank_transfer']['vietqr_template']);
		$settings['bank_transfer']['transaction_prefix'] = $this->clean_prefix($settings['bank_transfer']['transaction_prefix']);
		return $settings;
	}
}
