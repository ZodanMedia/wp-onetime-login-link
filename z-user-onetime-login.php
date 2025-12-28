<?php
/**
 * Plugin Name: Z User Onetime Login
 * Contributors: martenmoolenaar, zodannl
 * Plugin URI: https://plugins.zodan.nl/wordpress-user-onetime-login/
 * Tags: user, login, direct login, theme development, development
 * Requires at least: 5.5
 * Tested up to: 6.9
 * Description: Let users login once without a password
 * Version: 0.0.3
 * Author: Zodan
 * Author URI: https://zodan.nl
 * Text Domain: z-user-onetime-login
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 */



// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}

/**
 * Start: create an instance after the plugins have loaded
 * 
 */
add_action( 'plugins_loaded', function() {
	$instance = Z_User_Onetime_Login::get_instance();
	$instance->plugin_setup();
} );



class Z_User_Onetime_Login {

	protected static $instance = NULL;
	public $plugin_version = '0.0.3';
	public $plugin_url = '';
	public $plugin_path = '';

	public static function get_instance() {
		NULL === self::$instance and self::$instance = new self;
		return self::$instance;
	}

	public function __construct() {}

	public function plugin_setup() {

		if ( ! defined( 'Z_USER_ONETIME_LOGIN_VERSION' ) ) {
			define( 'Z_USER_ONETIME_LOGIN_VERSION', $this->plugin_version  );
		}

		$this->plugin_url = plugins_url( '/', __FILE__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );

		if ( ! defined( 'Z_USER_ONETIME_LOGIN_VERSION' ) ) {
			define( 'Z_USER_ONETIME_LOGIN_VERSION', $this->plugin_version  );
		}

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_plugin_settings_link' ] );

		add_filter( 'login_message', [ $this, 'add_request_link_to_login' ] );
		add_action( 'login_form_zloginonce', [ $this, 'render_zloginonce_form' ] );	

		add_action( 'init', [ $this, 'handle_self_login_request' ] );


		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'handle_send_login_once_mail' ] );
			include( $this->plugin_path . 'admin.php' );
			add_filter ('user_row_actions', [ $this, 'add_send_zloginonce_link_mail' ], 10, 2) ;
			add_action( 'admin_notices', function() {
				if ( filter_has_var( INPUT_GET, 'zloginonce_sent' ) ) {
					echo '<div class="notice notice-success"><p>'.esc_html(__('One time login link sent.','z-user-onetime-login')).'</p></div>';
				}
			});
		}

		// Admin front end
		if ( ! is_admin() && ! is_user_logged_in() ) {
			add_action( 'init', [ $this, 'handle_login_from_url' ] );
		}
	}










	public static function user_has_excluded_roles( $user_id ) {

		$options = get_option( 'z_user_onetime_login_plugin_options' );
		$roles = $options['roles'];
		if ( empty( $roles ) ) return false;

		$user = get_user_by('id', intval( $user_id ) );
		
		return ! empty( array_intersect( $roles, (array) $user->roles ) );
	}


	public static function add_plugin_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=z_user_onetime_login">' . __( 'Settings','z-user-onetime-login' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}


	public function handle_login_from_url() {

		// Already logged in? Bail out.
		if ( is_user_logged_in() ) {
			return;
		}

		/**
 		 * Login token from email link.
 		 * This is NOT a WordPress nonce but a cryptographically secure, single-use token.
		 * 
		 * No login parameter present or an empty token → bail out
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['zloginonce'] ) ) { 
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
    	$token = sanitize_text_field( wp_unslash( $_GET['zloginonce'] ) );
		if ( empty( $token ) ) {
			return;
		}

		
		// Find user by nonce (one-time mapping)
		$users = get_users(
			array(
				'fields'     => array( 'ID' ),
				'meta_key' => 'z_login_once_nonce', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key  -- This is a one-time operation, and there is no more appropriate functionality available
				'meta_value' => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value  -- This is a one-time operation, and there is no more appropriate functionality available
				'number'     => 1,
			)
		);

		if ( empty( $users ) ) {
			wp_die(
				esc_html__( 'Invalid or expired login link.', 'z-user-onetime-login' ),
				esc_html__( 'Login error', 'z-user-onetime-login' ),
				[ 'response' => 403 ]
			);
		}

		$user_id = (int) $users[0]->ID;
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			wp_die(
				esc_html__( 'Invalid user.', 'z-user-onetime-login' ),
				esc_html__( 'Login error', 'z-user-onetime-login' ),
				[ 'response' => 403 ]
			);
		}

		if ( self::user_has_excluded_roles( $user_id ) ) {
			wp_die(
				esc_html__( 'Your role is excluded from fast login, please contact your website administrator.', 'z-user-onetime-login' ),
				esc_html__( 'Login error', 'z-user-onetime-login' ),
				[ 'response' => 403 ]
			);
		}

		$expires = (int) get_user_meta( $user_id, 'z_login_once_expires', true );

		if ( empty( $expires ) || time() > $expires ) {
			wp_die(
				esc_html__( 'Your login token has expired.', 'z-user-onetime-login' ),
				esc_html__( 'Login error', 'z-user-onetime-login' ),
				[ 'response' => 403 ]
			);
		}

		// Verify token
		$stored_hash = get_user_meta( $user_id, 'z_login_once_token', true );
		if ( empty( $stored_hash ) || ! hash_equals( $stored_hash, hash( 'sha256', $token ) ) ) {
			wp_die(
				esc_html__( 'This login link is invalid or ahs already been used.', 'z-user-onetime-login' ),
				esc_html__( 'Login error', 'z-user-onetime-login' ),
				[ 'response' => 403 ]
			);
		}
		

		if( ! empty( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			// use fingerprinting
			$fingerprint = get_user_meta( $user_id, 'z_login_once_fingerprint', true );
			if ( $fingerprint !== hash( 'sha256', sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) . sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) ) ) {
				wp_die(
					esc_html__( 'Login link environment mismatch. Use the same browser/platform for both requesting and using the link.', 'z-user-onetime-login' ),
					esc_html__( 'Login error', 'z-user-onetime-login' ),
					[ 'response' => 403 ]
				);
			}
		}


		/**
		 * =====================================================
		 * 🌐 MULTISITE: switch after validation
		 * =====================================================
		 */
		$switched = false;

		if ( is_multisite() && ! is_network_admin() ) {

			$blogs = get_blogs_of_user( $user_id );

			if ( ! empty( $blogs ) ) {
				$blog_ids = array_keys( $blogs );

				if ( ! in_array( get_current_blog_id(), $blog_ids, true ) ) {
					switch_to_blog( (int) $blog_ids[0] );
					$switched = true;
				}
			}
		}


		/**
		 * =====================================================
		 * 🔐 LOGIN
		 * =====================================================
		 */

		// Set WP authorisation cookie
		wp_set_auth_cookie( $user_id, true, is_multisite() );

		// if you want is_user_logged_in to work you should set `wp_set_current_user` explicityly
		wp_set_current_user( $user_id );

		// The WP login action
		do_action( 'wp_login', $user->user_login, $user );	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- We are not invoking a plugin-specific action, but actually activating wp_login

		/**
		 * =====================================================
		 * 🔄 Delete the token → link is now invalid
		 * =====================================================
		 */
		delete_user_meta( $user_id, 'z_login_once_token' );
		delete_user_meta( $user_id, 'z_login_once_expires' );


		/**
		 * =====================================================
		 * 🌐 MULTISITE: restore context
		 * =====================================================
		 */
		if ( $switched ) {
			restore_current_blog();
		}

		wp_safe_redirect(
			is_multisite() ? network_home_url() : home_url()
		);
		exit;
	}


	public function add_send_zloginonce_link_mail( $actions, $user ) {

		if ( self::user_has_excluded_roles( $user->ID ) ) {
			return $actions;
		}

		// Setup the mail link
		$action_url = add_query_arg( array( 
			'user_id' => $user->ID, 
			'action' => 'add_send_zloginonce_link_mail', 
		), admin_url( 'users.php' ) );

		$url = wp_nonce_url(
			$action_url,
			'zsendloginoncelink',
			'z_send_login_once_link_nonce'
		);

		$actions['zloginonce'] =
			'<a href="'.esc_url($url).'">'.__('Send login once link','z-user-onetime-login').'</a>';

		return $actions;

	}


	public function handle_send_login_once_mail() {

		if (
			empty($_GET['action']) ||
			$_GET['action'] !== 'add_send_zloginonce_link_mail' ||
			empty($_GET['user_id']) ||
			empty($_GET['z_send_login_once_link_nonce'])
		) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET['z_send_login_once_link_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'zsendloginoncelink' ) ) {
			return;
		}
		// Bail out if the user does not exist
		$user = get_user_by( 'id', intval($_GET['user_id']) );
		if ( ! $user ) {
			return;
		}
		// Bail out if the user has a role that prohibits fast login
		if ( self::user_has_excluded_roles( $user->ID ) ) {
			return;
		}

		// Create login token for the user (and first delete the old ones, if any)
		delete_user_meta( $user->ID, 'z_login_once_token' );
		delete_user_meta( $user->ID, 'z_login_once_expires' );

		$token = bin2hex( random_bytes( 32 ) ); // 64 chars
		$hash  = hash( 'sha256', $token );
		update_user_meta( $user->ID, 'z_login_once_token', $hash );
		update_user_meta( $user->ID, 'z_login_once_expires', time() + HOUR_IN_SECONDS );
		if( ! empty( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			// use fingerprinting
			update_user_meta(
				$user->ID,
				'z_login_once_fingerprint',
				hash( 'sha256', sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) . sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) )
			);
		}

		// The login link
		$link_url = add_query_arg(
			[ 'zloginonce' => $token ],
			is_multisite() ? network_home_url() : home_url()
		);


		$options = get_option( 'z_user_onetime_login_plugin_options' );

		$subject = $options['mail_subject'];
		$linktext = $options['mail_linktext'] ?: $link_url;

		$link_html = '<a href="'.esc_url($link_url).'">'.$linktext.'</a>';

		$content = str_replace(
			['{{displayname}}','{{firstname}}','{{zloginlink}}'],
			[$user->display_name, $user->first_name, $link_html],
			wpautop($options['mail_content'])
		);

		wp_mail(
			$user->user_email,
			$subject,
			$content,
			['Content-Type: text/html; charset=UTF-8']
		);

		$redirect_url = admin_url( 'users.php?zloginonce_sent' );
		wp_safe_redirect($redirect_url);
		exit;
	}


	public function add_request_link_to_login( $message ) {

		$options = get_option( 'z_user_onetime_login_plugin_options' );

		if ( empty( $options['allow_user_request'] ) ) {
			return $message;
		}

		// $url = wp_lostpassword_url() . '&zloginonce=1';
		$url = wp_login_url() . '?action=zloginonce';

		$message .= '<p class="message zloginonce-link">
			<a href="' . esc_url( $url ) . '">' .
			esc_html__( 'Request a one-time login link', 'z-user-onetime-login' ) .
			'</a>
		</p>';

		return $message;
	}



	protected function is_rate_limited( $email ) {

		$options = get_option( 'z_user_onetime_login_plugin_options' );

		if ( empty( $options['use_rate_limit'] ) ) {
			return false;
		}

		$ip   = ! empty($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
		$key  = 'zloginonce_rl_' . md5( $ip . $email );

		if ( get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, 1, MINUTE_IN_SECONDS * 10 ); // 10 min
		return false;
	}


	public function render_zloginonce_form() {

		if ( is_user_logged_in() ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		login_header(
			__( 'Email login link', 'z-user-onetime-login' ),
			'<p class="message">' . __( 'Enter your email address to receive a one-time login link.', 'z-user-onetime-login' ) . '</p>'
		);
		?>

		<form method="post" action="<?php echo esc_url( wp_login_url() . '?action=zloginonce' ); ?>">
			<p>
				<label for="zloginonce_email">
					<?php esc_html_e( 'Email address', 'z-user-onetime-login' ); ?>
				</label>
				<input type="email" name="zloginonce_email" id="zloginonce_email" class="input" required>
			</p>

			<?php wp_nonce_field( 'zloginonce_request', 'zloginonce_nonce' ); ?>

			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Send login link', 'z-user-onetime-login' ); ?>">
			</p>
		</form>

		<p id="nav">
			<a href="<?php echo esc_url( wp_login_url() ); ?>">
				<?php esc_html_e( '← Back to login', 'z-user-onetime-login' ); ?>
			</a>
		</p>

		<?php
		login_footer();
		exit;
	}


	public function handle_self_login_request() {

		if ( empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty( $_POST['zloginonce_email'] ) || empty( $_POST['zloginonce_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zloginonce_nonce'] ) ), 'zloginonce_request' ) ) {
			wp_die( 'Nonce not verified' );
		}

		$email = sanitize_email( wp_unslash( $_POST['zloginonce_email'] ) );
		$user  = get_user_by( 'email', $email );

		if ( $this->is_rate_limited( $email ) ) {
    		wp_safe_redirect( add_query_arg( 'requested', '1', wp_get_referer() ) );
    		exit;
		}

		// Always generic answer → no user enumeration
		if ( ! $user || self::user_has_excluded_roles( $user->ID ) ) {
			wp_safe_redirect( add_query_arg( 'requested', '1', wp_get_referer() ) );
			exit;
		}

		// Create login token for the user (and first delete the old ones, if any)
		delete_user_meta( $user->ID, 'z_login_once_token' );
		delete_user_meta( $user->ID, 'z_login_once_expires' );

		$token = bin2hex( random_bytes( 32 ) ); // 64 chars
		$hash  = hash( 'sha256', $token );
		update_user_meta( $user->ID, 'z_login_once_token', $hash );
		update_user_meta( $user->ID, 'z_login_once_expires', time() + HOUR_IN_SECONDS );
		if( ! empty( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			// use fingerprinting
			update_user_meta(
				$user->ID,
				'z_login_once_fingerprint',
				hash( 'sha256', sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) . sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) )
			);
		}

		// The login link
		$link_url = add_query_arg(
			[ 'zloginonce' => $token ],
			is_multisite() ? network_home_url() : home_url()
		);

		// Mail
		$options = get_option( 'z_user_onetime_login_plugin_options' );

		$subject = $options['mail_subject'];
		$content = str_replace(
			['{{displayname}}','{{firstname}}','{{zloginlink}}'],
			[$user->display_name, $user->first_name, esc_url( $link_url )],
			wpautop( $options['mail_content'] )
		);

		wp_mail(
			$user->user_email,
			$subject,
			$content,
			['Content-Type: text/html; charset=UTF-8']
		);

		// wp_safe_redirect( add_query_arg( 'requested', '1', wp_get_referer() ) );
		wp_safe_redirect( wp_login_url() . '?checkemail=confirm' );
		exit;
	}


}
