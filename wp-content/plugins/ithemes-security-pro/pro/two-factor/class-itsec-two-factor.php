<?php

use iThemesSecurity\User_Groups\Matcher;
use iThemesSecurity\User_Groups;

/**
 * Two-Factor Execution
 *
 * Handles all two-factor execution once the feature has been
 * enabled by the user.
 *
 * @since   1.2.0
 *
 * @package iThemes_Security
 */
class ITSEC_Two_Factor {
	private static $instance = false;

	const REMEMBER_COOKIE = 'itsec_remember_2fa';
	const REMEMBER_META_KEY = '_itsec_remember_2fa';

	/**
	 * Helper class
	 *
	 * @access private
	 * @var ITSEC_Two_Factor_Helper
	 */
	private $helper;

	/** @var User_Groups\Matcher */
	private $matcher;

	/**
	 * The user meta provider key.
	 *
	 * @access private
	 * @var string
	 */
	private $_provider_user_meta_key = '_two_factor_provider';

	/**
	 * The user meta enabled providers key.
	 *
	 * @access private
	 * @var string
	 */
	private $_enabled_providers_user_meta_key = '_two_factor_enabled_providers';

	private function __construct() {
		add_action( 'itsec_login_interstitial_init', array( $this, 'register_interstitial' ) );
		add_action( 'updated_post_meta', array( $this, 'clear_remember_on_password_change' ), 10, 3 );

		add_action( 'show_user_profile', array( $this, 'user_two_factor_options' ) );
		add_action( 'edit_user_profile', array( $this, 'user_two_factor_options' ) );
		add_action( 'personal_options_update', array( $this, 'user_two_factor_options_update' ) );
		add_action( 'edit_user_profile_update', array( $this, 'user_two_factor_options_update' ) );

		add_action( 'itsec_register_user_group_settings', [ $this, 'register_group_setting' ] );

		add_action( 'ithemes_sync_register_verbs', array( $this, 'register_sync_verbs' ) );
		add_filter( 'itsec-filter-itsec-get-everything-verbs', array( $this, 'register_sync_get_everything_verbs' ) );

		add_action( 'load-profile.php', array( $this, 'add_profile_page_styling' ) );
		add_action( 'load-user-edit.php', array( $this, 'add_profile_page_styling' ) );

		add_filter( 'itsec_notifications', array( $this, 'register_notifications' ) );
		add_filter( 'itsec_two-factor-email_notification_strings', array( $this, 'two_factor_email_method_strings' ) );
		add_filter( 'itsec_two-factor-confirm-email_notification_strings', array( $this, 'two_factor_confirm_email_method_strings' ) );

		$this->matcher = ITSEC_Modules::get_container()->get( Matcher::class );
		$this->load_helper();
	}

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	private function load_helper() {
		if ( ! isset( $this->helper ) ) {
			require_once( dirname( __FILE__ ) . '/class-itsec-two-factor-helper.php' );
			$this->helper = ITSEC_Two_Factor_Helper::get_instance();
		}
	}

	/**
	 * Register verbs for Sync.
	 *
	 * @since 3.6.0
	 *
	 * @param Ithemes_Sync_API Sync API object.
	 */
	public function register_sync_verbs( $api ) {
		$api->register( 'itsec-get-two-factor-users', 'Ithemes_Sync_Verb_ITSEC_Get_Two_Factor_Users', dirname( __FILE__ ) . '/sync-verbs/itsec-get-two-factor-users.php' );
		$api->register( 'itsec-override-two-factor-user', 'Ithemes_Sync_Verb_ITSEC_Override_Two_Factor_User', dirname( __FILE__ ) . '/sync-verbs/itsec-override-two-factor-user.php' );
		$api->register( 'itsec-authorize-two-factor-user', 'Ithemes_Sync_Verb_ITSEC_Authorize_Two_Factor_User', dirname( __FILE__ ) . '/sync-verbs/itsec-authorize-two-factor-user.php' );
	}

	/**
	 * Filter to add verbs to the response for the itsec-get-everything verb.
	 *
	 * @since 3.6.0
	 *
	 * @param array Array of verbs.
	 *
	 * @return array Array of verbs.
	 */
	public function register_sync_get_everything_verbs( $verbs ) {
		$verbs['two_factor'][] = 'itsec-get-two-factor-users';

		return $verbs;
	}

	/**
	 * Add user profile fields.
	 *
	 * This executes during the `show_user_profile` & `edit_user_profile` actions.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function user_two_factor_options( $user ) {
		$this->load_helper();

		$allowed_providers = $this->get_allowed_provider_instances_for_user( $user );

		if ( ! $allowed_providers ) {
			return;
		}

		$enabled_providers = get_user_meta( $user->ID, $this->_enabled_providers_user_meta_key, true );

		if ( ! $enabled_providers ) {
			$enabled_providers = array();
		}

		$primary_provider = get_user_meta( $user->ID, $this->_provider_user_meta_key, true );

		wp_nonce_field( 'user_two_factor_options', '_nonce_user_two_factor_options', false );
		?>
		<h3 id="two-factor-user-options"><?php esc_html_e( 'Two-Factor Authentication Options', 'it-l10n-ithemes-security-pro' ); ?></h3>
		<p><?php esc_html_e( 'Enabling two-factor authentication greatly increases the security of your user account on this site. With two-factor authentication enabled, after you login with your username and password, you will be asked for an authentication code before you can successfully log in.' ); ?>
			<strong> <?php esc_html_e( 'Two-factor authentication codes can come from an app that runs on your mobile device, an email that is sent to you after you login with your username and password, or from a pre-generated list of codes.' ); ?></strong> <?php esc_html_e( 'The settings below allow you to configure which of these authentication code providers are enabled for your user.', 'it-l10n-ithemes-security-pro' ); ?>
		</p>

		<table class="two-factor-methods-table widefat wp-list-table striped">
			<thead>
			<tr>
				<th scope="col" class="manage-column column-primary column-method"><?php esc_html_e( 'Provider', 'it-l10n-ithemes-security-pro' ); ?></th>
				<th scope="col" class="manage-column column-enable"><?php esc_html_e( 'Enabled', 'it-l10n-ithemes-security-pro' ); ?></th>
				<th scope="col" class="manage-column column-make-primary"><?php esc_html_e( 'Primary', 'it-l10n-ithemes-security-pro' ); ?></th>
			</tr>
			</thead>
			<tbody id="the-list">
			<?php foreach ( $allowed_providers as $class => $object ) : ?>
				<tr>
					<td class="column-method column-primary" style="width:60%;vertical-align:top;">
						<strong><?php $object->print_label(); ?></strong>
						<?php do_action( 'two-factor-user-options-' . $class, $user ); ?>
						<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
					</td>
					<td class="column-enable" style="width:20%;vertical-align:top;">
						<input type="checkbox" name="<?php echo esc_attr( $this->_enabled_providers_user_meta_key ); ?>[]"
							   id="<?php echo esc_attr( $this->_enabled_providers_user_meta_key . '-' . $class ); ?>"
							   value="<?php echo esc_attr( $class ); ?>" <?php checked( in_array( $class, $enabled_providers ) ); ?> />
						<label for="<?php echo esc_attr( $this->_enabled_providers_user_meta_key . '-' . $class ); ?>">
							<?php esc_html_e( 'Enable', 'it-l10n-ithemes-security-pro' ) ?>
							<?php
							if ( $object->recommended ) {
								echo ' <strong>' . __( '(recommended)', 'it-l10n-ithemes-security-pro' ) . '</strong>';
							}
							?>
						</label>
					</td>
					<td class="column-make-primary" style="width:20%;vertical-align:top;">
						<input type="radio" name="<?php echo esc_attr( $this->_provider_user_meta_key ); ?>" value="<?php echo esc_attr( $class ); ?>"
							   id="<?php echo esc_attr( $this->_provider_user_meta_key . '-' . $class ); ?>" <?php checked( $class, $primary_provider ); ?> />
						<label for="<?php echo esc_attr( $this->_provider_user_meta_key . '-' . $class ); ?>">
							<?php esc_html_e( 'Make Primary', 'it-l10n-ithemes-security-pro' ) ?>
							<?php
							if ( $object->recommended ) {
								echo ' <strong>' . __( '(recommended)', 'it-l10n-ithemes-security-pro' ) . '</strong>';
							}
							?>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot>
			<tr>
				<th scope="col" class="manage-column column-primary column-method"><?php esc_html_e( 'Method', 'it-l10n-ithemes-security-pro' ); ?></th>
				<th scope="col" class="manage-column column-enable"><?php esc_html_e( 'Enabled', 'it-l10n-ithemes-security-pro' ); ?></th>
				<th scope="col" class="manage-column column-make-primary"><?php esc_html_e( 'Primary', 'it-l10n-ithemes-security-pro' ); ?></th>
			</tr>
			</tfoot>
		</table>
		<?php
		/**
		 * Fires after the Two Factor methods table.
		 *
		 * To be used by Two Factor methods to add settings UI.
		 */
		do_action( 'show_user_security_settings', $user );
	}

	/**
	 * Update the user meta value.
	 *
	 * This executes during the `personal_options_update` & `edit_user_profile_update` actions.
	 *
	 * @param int $user_id User ID.
	 */
	public function user_two_factor_options_update( $user_id ) {
		$this->load_helper();

		if ( isset( $_POST['_nonce_user_two_factor_options'] ) ) {
			check_admin_referer( 'user_two_factor_options', '_nonce_user_two_factor_options' );
			$providers = $this->helper->get_enabled_provider_instances();
			// If there are no providers enabled for the site, then let's not worry about this.
			if ( empty( $providers ) ) {
				return;
			}

			$enabled_providers = isset( $_POST[ $this->_enabled_providers_user_meta_key ] ) ? $_POST[ $this->_enabled_providers_user_meta_key ] : array();
			$this->set_enabled_providers_for_user( $enabled_providers, $user_id );

			// Whitelist the new values to only the available classes and empty.
			$primary_provider = isset( $_POST[ $this->_provider_user_meta_key ] ) ? $_POST[ $this->_provider_user_meta_key ] : '';
			$this->set_primary_provider_for_user( $primary_provider, $user_id );
		}
	}

	/**
	 * Register the user group settings.
	 *
	 * @param User_Groups\Settings_Registry $registry
	 */
	public function register_group_setting( User_Groups\Settings_Registry $registry ) {
		$registry->register( new User_Groups\Settings_Registration( 'two-factor', 'protect_user_group', User_Groups\Settings_Registration::T_MULTIPLE, static function () {
			return [
				'title'       => __( 'Force Two Factor', 'it-l10n-ithemes-security-pro' ),
				'description' => __( 'Require users in a group to use Two-Factor authentication. We highly recommended forcing any user that can make changes to the site to use two-factor authentication.', 'it-l10n-ithemes-security-pro' ),
			];
		} ) );
		$registry->register( new User_Groups\Settings_Registration( 'two-factor', 'exclude_group', User_Groups\Settings_Registration::T_MULTIPLE, static function () {
			return [
				'title'       => __( 'Disable Two-Factor Onboarding', 'it-l10n-ithemes-security-pro' ),
				'description' => __( 'Disable the two-factor authentication on-boarding for certain users. Users can still manually enroll in two-factor through their WordPress admin profile. This setting will override forced two-factor authentication for Vulnerable User Protection and Vulnerable Site Protection for the selected users.', 'it-l10n-ithemes-security-pro' ),
			];
		} ) );
		$registry->register( new User_Groups\Settings_Registration( 'two-factor', 'remember_group', User_Groups\Settings_Registration::T_MULTIPLE, static function () {
			return [
				'title'       => __( 'Allow Remembering Device', 'it-l10n-ithemes-security-pro' ),
				'description' => __( 'Allow users to check a "Remember this Device" box that, if checked, will not prompt the user for a Two-Factor code for the next 30 days on the current device. Requires the Trusted Devices feature.', 'it-l10n-ithemes-security-pro' ),
			];
		} ) );
		$registry->register( new User_Groups\Settings_Registration( 'two-factor', 'application_passwords_group', User_Groups\Settings_Registration::T_MULTIPLE, static function () {
			return [
				'title'       => __( 'Application Passwords', 'it-l10n-ithemes-security-pro' ),
				'description' => __( 'Use Application Passwords to allow authentication without providing your actual password when using non-traditional login methods such as XML-RPC or the REST API. They can be easily revoked, and can never be used for traditional logins to your website.', 'it-l10n-ithemes-security-pro' ),
			];
		} ) );
	}

	/**
	 * Update the list of enabled Two Factor providers for a user.
	 *
	 * @param array    $enabled_providers
	 * @param int|null $user_id
	 */
	public function set_enabled_providers_for_user( $enabled_providers, $user_id = null ) {
		$this->load_helper();

		$providers = $this->helper->get_enabled_providers();
		// If there are no providers enabled for the site, then let's not worry about this.
		if ( empty( $providers ) ) {
			return;
		}
		if ( ! isset( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( ! is_array( $enabled_providers ) ) {
			// Make sure enabled providers is an array
			$enabled_providers = array();
		} else {
			// Only site-enabled providers can be enabled for a user
			$enabled_providers = array_intersect( $enabled_providers, array_keys( $providers ) );
		}
		update_user_meta( $user_id, $this->_enabled_providers_user_meta_key, $enabled_providers );
	}

	/**
	 * Set the primary provider for a user.
	 *
	 * @param string   $primary_provider
	 * @param int|null $user_id
	 */
	public function set_primary_provider_for_user( $primary_provider, $user_id = null ) {
		$this->load_helper();

		$providers = $this->helper->get_enabled_providers();
		// If there are no providers enabled for the site, then let's not worry about this.
		if ( empty( $providers ) ) {
			return;
		}
		if ( ! isset( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( empty( $primary_provider ) || array_key_exists( $primary_provider, $providers ) ) {
			update_user_meta( $user_id, $this->_provider_user_meta_key, $primary_provider );
		}
	}

	/**
	 * Get a list of the allowed providers for a user.
	 *
	 * @param WP_User $user
	 *
	 * @return string[]
	 */
	public function get_allowed_providers_for_user( $user = null ) {
		if ( ! $user instanceof WP_User ) {
			$user = wp_get_current_user();
		}

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return array();
		}

		$this->load_helper();

		$providers = array_keys( $this->helper->get_enabled_providers() );

		/**
		 * Filter the list of allowed providers for a user.
		 *
		 * @param string[] $providers Provider classes.
		 * @param WP_User  $user
		 */
		return apply_filters( 'itsec_two_factor_allowed_providers_for_user', $providers, $user );
	}

	/**
	 * Get the allowed provider instances for a given user.
	 *
	 * @param WP_User|null $user
	 *
	 * @return Two_Factor_Provider[]
	 */
	public function get_allowed_provider_instances_for_user( $user = null ) {
		$classes   = $this->get_allowed_providers_for_user( $user );
		$instances = array();

		foreach ( $classes as $class ) {
			if ( $provider = $this->helper->get_provider_instance( $class ) ) {
				$instances[ $class ] = $provider;
			}
		}

		return $instances;
	}

	/**
	 * Get all Two-Factor Auth providers that are enabled for the specified|current user.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 *
	 * @return string[]
	 */
	public function get_enabled_providers_for_user( $user = null ) {
		$this->load_helper();

		if ( ! $user instanceof WP_User ) {
			$user = wp_get_current_user();
		}

		$allowed = $this->get_allowed_providers_for_user( $user );
		$enabled = get_user_meta( $user->ID, $this->_enabled_providers_user_meta_key, true );

		if ( ! $enabled ) {
			$enabled = array();
		}

		$enabled = array_intersect( $enabled, $allowed );

		return $enabled;
	}

	/**
	 * Get all Two-Factor Auth providers that are both enabled and configured for the specified|current user.
	 *
	 * @param WP_User $user         WP_User object of the logged-in user.
	 * @param bool    $add_enforced Whether to add in the email provider if 2fa is enforced for the user's account.
	 *
	 * @return Two_Factor_Provider[]
	 */
	public function get_available_providers_for_user( $user = null, $add_enforced = true ) {
		$this->load_helper();

		if ( ! $user instanceof WP_User ) {
			$user = wp_get_current_user();
		}

		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return array();
		}

		$enabled    = $this->get_enabled_providers_for_user( $user );
		$configured = array();

		foreach ( $enabled as $classname ) {
			$provider = $this->helper->get_provider_instance( $classname );

			if ( $provider && $provider->is_available_for_user( $user ) ) {
				$configured[ $classname ] = $provider;
			}
		}

		if (
			$add_enforced &&
			! isset( $configured['Two_Factor_Email'] ) &&
			array_key_exists( 'Two_Factor_Email', $this->helper->get_enabled_providers() ) &&
			$this->user_requires_two_factor( $user->ID )
		) {
			$configured['Two_Factor_Email'] = $this->helper->get_provider_instance( 'Two_Factor_Email' );
		}

		/**
		 * Filters all of the available providers for a given user.
		 *
		 * @param string  $configured
		 * @param WP_User $user
		 * @param bool    $add_enforced
		 */
		return apply_filters( 'itsec_two_factor_available_providers_for_user', $configured, $user, $add_enforced );
	}

	/**
	 * Get the reason that two factor is required for a given user.
	 *
	 * 'user_type' - Required because all users are required, their role requires it, or they are a privileged user.
	 * 'vulnerable_users' - Requried because they have a weak password.
	 * 'vulnerable_site' - Required because the site is running outdated versions of plugins.
	 *
	 * @param int|null $user_id
	 *
	 * @return string|false
	 */
	public function get_two_factor_requirement_reason( $user_id = null ) {
		$this->load_helper();

		if ( empty( $user_id ) || ! is_numeric( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$providers = $this->helper->get_enabled_provider_instances();

		if ( ! isset( $providers['Two_Factor_Email'] ) ) {
			// Two-factor can't be a requirement if the Email method is not available.
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! ( $user instanceof WP_User ) ) {
			return false;
		}

		$settings = ITSEC_Modules::get_settings( 'two-factor' );

		if ( $this->matcher->matches( User_Groups\Match_Target::for_user( $user ), $settings['protect_user_group'] ) ) {
			return 'user_type';
		}

		if ( $settings['protect_vulnerable_users'] && ! $this->is_user_excluded( $user ) ) {
			$password_strength = get_user_meta( $user_id, 'itsec-password-strength', true );

			if ( ( is_string( $password_strength ) || is_int( $password_strength ) ) && $password_strength >= 0 && $password_strength <= 2 ) {
				return 'vulnerable_users';
			}
		}

		if ( $settings['protect_vulnerable_site'] && ITSEC_Modules::is_active( 'version-management' ) && ! $this->is_user_excluded( $user ) ) {
			$version_management_settings = ITSEC_Modules::get_settings( 'version-management' );

			if ( $version_management_settings['is_software_outdated'] ) {
				return 'vulnerable_site';
			}
		}
	}

	/**
	 * Is the user excluded from Two-Factor authentication.
	 *
	 * @param int|WP_User|string $user
	 *
	 * @return bool
	 */
	public function is_user_excluded( $user ) {
		$groups = ITSEC_Modules::get_setting( 'two-factor', 'exclude_group' );

		return $this->matcher->matches( User_Groups\Match_Target::for_user( ITSEC_Lib::get_user( $user ) ), $groups );
	}

	/**
	 * Get a description for the reason Two Factor is required.
	 *
	 * @param string $reason
	 *
	 * @return string
	 */
	public function get_reason_description( $reason ) {
		switch ( $reason ) {
			case 'user_type':
				return esc_html__( 'Your user requires two-factor in order to log in.', 'it-l10n-ithemes-security-pro' );
			case 'vulnerable_users':
				return esc_html__( 'The site requires any user with a weak password to use two-factor in order to log in.', 'it-l10n-ithemes-security-pro' );
			case 'vulnerable_site':
				return esc_html__( 'This site requires two-factor in order to log in.', 'it-l10n-ithemes-security-pro' );
			default:
				return '';
		}
	}

	/**
	 * Does the given user require Two Factor to be enabled.
	 *
	 * @param int|null $user_id
	 *
	 * @return bool
	 */
	public function user_requires_two_factor( $user_id = null ) {
		$reason = $this->get_two_factor_requirement_reason( $user_id );

		return (bool) $reason;
	}

	/**
	 * Gets the Two-Factor Auth provider for the specified|current user.
	 *
	 * @param int $user_id Optional. User ID. Default is 'null'.
	 *
	 * @return Two_Factor_Provider|null
	 */
	public function get_primary_provider_for_user( $user_id = null ) {
		$this->load_helper();

		if ( ! $user_id || ! is_numeric( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$user_providers = $this->get_available_providers_for_user( get_userdata( $user_id ) );

		if ( ! $user_providers ) {
			return null;
		}

		if ( 1 === count( $user_providers ) ) {
			$provider = key( $user_providers );
		} else {
			$provider = get_user_meta( $user_id, $this->_provider_user_meta_key, true );

			// If the provider specified isn't enabled, just grab the first one that is.
			if ( ! $provider || ! isset( $user_providers[ $provider ] ) ) {
				$provider = key( $user_providers );
			}
		}

		/**
		 * Filter the two-factor authentication provider used for this user.
		 *
		 * @param string $provider The provider currently being used.
		 * @param int    $user_id  The user ID.
		 */
		$provider = apply_filters( 'two_factor_primary_provider_for_user', $provider, $user_id );

		return $this->helper->get_provider_instance( $provider );
	}

	/**
	 * Quick boolean check for whether a given user is using two-step.
	 *
	 * @param int $user_id Optional. User ID. Default is 'null'.
	 *
	 * @return bool|null True if they are using it. False if not using it. Null if disabled site-wide.
	 */
	public function is_user_using_two_factor( $user_id = null ) {
		if ( defined( 'ITSEC_DISABLE_TWO_FACTOR' ) && ITSEC_DISABLE_TWO_FACTOR ) {
			return null;
		}

		return (bool) $this->get_primary_provider_for_user( $user_id );
	}

	/**
	 * Determine if a Sync Two-Factor override is active.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool True if the override is active. False otherwise.
	 */
	public function is_sync_override_active( $user_id ) {
		$sync_override = (int) get_user_option( 'itsec_two_factor_override', $user_id );

		if ( 1 !== $sync_override ) {
			return false;
		}

		$override_expires = (int) get_user_option( 'itsec_two_factor_override_expires', $user_id );

		if ( current_time( 'timestamp' ) > $override_expires ) {
			return false;
		}

		$post_data = $_POST;
		ITSEC_Log::add_debug( 'two_factor', "sync_override::$user_id", compact( 'user_id', 'sync_override', 'override_expires', 'post_data' ), compact( 'user_id' ) );

		return true;
	}

	/**
	 * Register the 2fa interstitial.
	 *
	 * @param ITSEC_Lib_Login_Interstitial $lib
	 */
	public function register_interstitial( $lib ) {
		require_once( dirname( __FILE__ ) . '/class-itsec-two-factor-interstitial.php' );
		require_once( dirname( __FILE__ ) . '/class-itsec-two-factor-on-board.php' );

		$interstitial = new ITSEC_Two_Factor_Interstitial( $this );
		$interstitial->run();
		$lib->register( '2fa', $interstitial );
		$lib->register( '2fa-on-board', new ITSEC_Two_Factor_On_Board( $this ) );
	}

	/**
	 * Set the remember 2fa cookie.
	 *
	 * @param WP_User $user
	 *
	 * @return bool
	 */
	public function set_remember_cookie( $user ) {

		if ( ! $token = ITSEC_Lib::generate_token() ) {
			return false;
		}

		if ( ! $hashed = ITSEC_Lib::hash_token( $token ) ) {
			return false;
		}

		$expires = ITSEC_Core::get_current_time_gmt() + MONTH_IN_SECONDS;

		if ( ! add_user_meta( $user->ID, self::REMEMBER_META_KEY, $data = compact( 'hashed', 'expires' ) ) ) {
			return false;
		}

		ITSEC_Log::add_debug( 'two_factor', 'remember_generated', $data, array( 'user_id' => $user->ID ) );

		return setcookie( self::REMEMBER_COOKIE, $token, $expires, ITSEC_Lib::get_home_root(), COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Clear the remember 2fa cookie.
	 *
	 * @return bool
	 */
	public function clear_remember_cookie() {
		return setcookie( self::REMEMBER_COOKIE, ' ', ITSEC_Core::get_current_time_gmt() - YEAR_IN_SECONDS, ITSEC_Lib::get_home_root(), COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Is the user allowed to remember 2fa.
	 *
	 * @param WP_User $user
	 *
	 * @return bool
	 */
	public function is_remember_allowed( $user ) {
		return $this->matcher->matches( User_Groups\Match_Target::for_user( $user ), ITSEC_Modules::get_setting( 'two-factor', 'remember_group' ) );
	}

	/**
	 * When a user's password is updated, clear any remember me meta keys.
	 *
	 * @param int    $meta_id
	 * @param int    $user_id
	 * @param string $meta_key
	 */
	public function clear_remember_on_password_change( $meta_id, $user_id, $meta_key ) {
		if ( 'itsec_last_password_change' === $meta_key ) {
			delete_user_meta( $user_id, self::REMEMBER_META_KEY );
		}
	}

	/**
	 * Enqueue the css/profile-page.css file.
	 */
	public function add_profile_page_styling() {
		wp_enqueue_style( 'itsec-two-factor-profile-page', plugins_url( 'css/profile-page.css', __FILE__ ), array(), ITSEC_Core::get_plugin_build() );

		$this->load_helper();
		$this->helper->get_enabled_provider_instances();
	}

	/**
	 * Register the Two Factor Email method notification.
	 *
	 * @param array $notifications
	 *
	 * @return array
	 */
	public function register_notifications( $notifications ) {

		$notifications['two-factor-email'] = array(
			'slug'             => 'two-factor-email',
			'schedule'         => ITSEC_Notification_Center::S_NONE,
			'recipient'        => ITSEC_Notification_Center::R_USER,
			'subject_editable' => true,
			'message_editable' => true,
			'tags'             => array( 'username', 'display_name', 'site_title' ),
			'module'           => 'two-factor',
		);

		$notifications['two-factor-confirm-email'] = array(
			'slug'             => 'two-factor-confirm-email',
			'schedule'         => ITSEC_Notification_Center::S_NONE,
			'recipient'        => ITSEC_Notification_Center::R_USER,
			'subject_editable' => true,
			'message_editable' => true,
			'tags'             => array( 'username', 'display_name', 'site_title' ),
			'module'           => 'two-factor',
			'optional'         => true,
		);

		return $notifications;
	}

	/**
	 * Provide translated strings for the Two Factor Email method notification.
	 *
	 * @return array
	 */
	public function two_factor_email_method_strings() {
		/* translators: Do not translate the curly brackets or their contents, those are placeholders. */
		$message = esc_html__( 'Hi {{ $display_name }},

Click the button to continue or manually enter the authentication code below to finish logging in.', 'it-l10n-ithemes-security-pro' );

		return array(
			'label'       => esc_html__( 'Two-Factor Email', 'it-l10n-ithemes-security-pro' ),
			'description' => sprintf( esc_html__( 'The %1$sTwo-Factor Authentication%2$s module sends an email containing the Authentication Code for users using email as their two-factor provider.', 'it-l10n-ithemes-security-pro' ), '<a href="#" data-module-link="two-factor">', '</a>' ),
			'subject'     => esc_html__( 'Login Authentication Code', 'it-l10n-ithemes-security-pro' ),
			'message'     => $message,
			'tags'        => array(
				'username'     => esc_html__( "The recipient's WordPress username.", 'it-l10n-ithemes-security-pro' ),
				'display_name' => esc_html__( "The recipient's WordPress display name.", 'it-l10n-ithemes-security-pro' ),
				'site_title'   => esc_html__( 'The WordPress Site Title. Can be changed under Settings -> General -> Site Title', 'it-l10n-ithemes-security-pro' ),
			)
		);
	}

	/**
	 * Provide translated strings for the Two Factor Confirm Email method notification.
	 *
	 * @return array
	 */
	public function two_factor_confirm_email_method_strings() {
		/* translators: Do not translate the curly brackets or their contents, those are placeholders. */
		$message = esc_html__( 'Hi {{ $display_name }},

Click the button to continue or manually enter the authentication code below to finish setting up Two-Factor.', 'it-l10n-ithemes-security-pro' );

		$desc = sprintf(
			esc_html__( 'The %1$sTwo-Factor Authentication%2$s module sends an email containing the Authentication Code for users when they are setting up Two-Factor. Try to keep the email similar to the Two Factor Email.', 'it-l10n-ithemes-security-pro' ),
			'<a href="#" data-module-link="two-factor">', '</a>'
		);
		$desc .= ' ' . esc_html__( 'Disabling this email will disable the Two-Factor Email Confirmation flow.', 'it-l10n-ithemes-security-pro' );

		return array(
			'label'       => esc_html__( 'Two-Factor Email Confirmation', 'it-l10n-ithemes-security-pro' ),
			'description' => $desc,
			'subject'     => esc_html__( 'Login Authentication Code', 'it-l10n-ithemes-security-pro' ),
			'message'     => $message,
			'tags'        => array(
				'username'     => esc_html__( "The recipient's WordPress username.", 'it-l10n-ithemes-security-pro' ),
				'display_name' => esc_html__( "The recipient's WordPress display name.", 'it-l10n-ithemes-security-pro' ),
				'site_title'   => esc_html__( 'The WordPress Site Title. Can be changed under Settings -> General -> Site Title', 'it-l10n-ithemes-security-pro' ),
			)
		);
	}

	public function get_helper() {
		return $this->helper;
	}
}
