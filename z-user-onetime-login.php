<?php
/**
 * Plugin Name: Z User Onetime Login
 * Contributors: martenmoolenaar, zodannl
 * Plugin URI: https://plugins.zodan.nl/wordpress-user-onetime-login/
 * Tags: user, login, direct login, theme development, development
 * Requires at least: 5.5
 * Tested up to: 6.9
 * Description: Let users login once without a password
 * Version: 0.0.2
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
	public $plugin_version = '0.0.2';
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

		add_filter( 'login_message', [ $this, 'add_login_request_link' ] );
		add_action( 'login_init', [ $this, 'redirect_from_password_flow' ] );
		
		add_shortcode( 'zloginonce_request_form', [ $this, 'loginonce_request_form_shortcode' ] );

		// add_action( 'admin_post_nopriv_zloginonce_request',  [ Z_User_Onetime_Login::get_instance(), 'handle_self_login_request' ]);
		add_action( 'admin_post_nopriv_zloginonce_request',  [ $this, 'handle_self_login_request' ]);

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

		// No login parameter → bail out
		if ( empty( $_GET['zloginonce'] ) ) {
			return;
		}
		// Already logged in? Bail out.
		if ( is_user_logged_in() ) {
			return;
		}

    	$nonce = sanitize_text_field( wp_unslash( $_GET['zloginonce'] ) );
		// Bail out if the nonce is not ok
		if ( empty( $nonce ) ) {
			return;
		}

		// Find user by nonce (one-time mapping)
		$users = get_users(
			array(
				'fields'     => array( 'ID' ),
				'meta_key' => 'z_login_once_nonce', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key  -- This is a one-time operation, and there is no more appropriate functionality available
				'meta_value' => $nonce, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value  -- This is a one-time operation, and there is no more appropriate functionality available
				'number'     => 1,
			)
		);

		if ( empty( $users ) ) {
			return;
		}

		$user_id = (int) $users[0]->ID;
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		if ( self::user_has_excluded_roles( $user_id ) ) {
			return;
		}

		$expires = (int) get_user_meta( $user_id, 'z_login_once_expires', true );

		if ( empty( $expires ) || time() > $expires ) {
			return;
		}

		// Verify WP nonce 
		if ( ! wp_verify_nonce( $nonce, 'z_login_once' ) ) {
			return;
		}



		/**
		 * =====================================================
		 * 🌐 MULTISITE: switch after validation
		 * =====================================================
		 */
		if ( is_multisite() ) {
			$primary_blog = (int) get_user_meta( $user_id, 'primary_blog', true );

			if ( $primary_blog ) {
				switch_to_blog( $primary_blog );
			}
		}


		/**
		 * =====================================================
		 * 🔐 LOGIN
		 * =====================================================
		 */

		// Set WP authorisation cookie
		// wp_set_auth_cookie( $user_id, true );
		wp_set_auth_cookie( $user_id, true, is_multisite() );

		// if you want is_user_logged_in to work you should set `wp_set_current_user` explicityly
		wp_set_current_user( $user_id );

		// The WP login action
		do_action( 'wp_login', $user->user_login, $user );	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- We are not invoking a plugin-specific action, but actually activating wp_login

		/**
		 * =====================================================
		 * 🔄 Renew the nonce → link is now invalid
		 * =====================================================
		 */
		update_user_meta( $user_id, 'z_login_once_nonce', wp_create_nonce( 'z_login_once' ) );
		delete_user_meta( $user_id, 'z_login_once_expires' );


		/**
		 * =====================================================
		 * 🌐 MULTISITE: restore context
		 * =====================================================
		 */
		if ( is_multisite() ) {
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

		$nonce = wp_create_nonce( 'z_login_once' );
		update_user_meta( $user->ID, 'z_login_once_nonce', $nonce );
		update_user_meta( $user->ID, 'z_login_once_expires', time() + HOUR_IN_SECONDS );


		// $link_url = add_query_arg( ['zloginonce' => $nonce], home_url() );
		$link_url = add_query_arg(
    		[ 'zloginonce' => $nonce ],
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


	public function add_login_request_link ( $message ) {

		$options = get_option( 'z_user_onetime_login_plugin_options' );

		if ( empty( $options['allow_user_request'] ) ) {
			return $message;
		}

		$url = wp_lostpassword_url() . '&zloginonce=1';

		$message .= '<p class="message zloginonce-link">
			<a href="' . esc_url( $url ) . '">' .
			esc_html__( 'Loginlink aanvragen', 'z-user-onetime-login' ) .
			'</a>
		</p>';

		return $message;
	}


	public function redirect_from_password_flow() {
		if ( isset( $_GET['zloginonce'] ) ) {
			wp_safe_redirect( site_url( '/onetime-login/' ) );
			exit;
		}
	}


	protected function is_rate_limited( $email ) {

		$ip   = ! empty($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
		$key  = 'zloginonce_rl_' . md5( $ip . $email );

		if ( get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, 1, MINUTE_IN_SECONDS * 10 ); // 10 min
		return false;
	}


	public function loginonce_request_form_shortcode() {

		if ( is_user_logged_in() ) {
			return '<p>' . __( 'Je bent al ingelogd.', 'z-user-onetime-login' ) . '</p>';
		}

   		ob_start();
   		?>
			<form method="post">
				<p>
					<label for="zloginonce_email">
						<?php esc_html_e( 'Email address', 'z-user-onetime-login' ); ?>
					</label>
					<input type="email" name="zloginonce_email" required>
				</p>

				<?php wp_nonce_field( 'zloginonce_request', 'zloginonce_nonce' ); ?>

				<input type="hidden" name="action" value="zloginonce_request">

				<p>
					<button type="submit">
						<?php esc_html_e( 'Send me a login link', 'z-user-onetime-login' ); ?>
					</button>
				</p>
			</form>
		<?php
		
		return ob_get_clean();
	}


	public function handle_self_login_request() {

		if (
			empty( $_POST['zloginonce_email'] ) ||
			empty( $_POST['zloginonce_nonce'] )
		) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zloginonce_nonce'] ) ), 'zloginonce_request' ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		$email = sanitize_email( wp_unslash( $_POST['zloginonce_email'] ) );
		$user  = get_user_by( 'email', $email );

		if ( $this->is_rate_limited( $email ) ) {
    		wp_safe_redirect( add_query_arg( 'requested', '1', wp_get_referer() ) );
    		exit;
		}

		// Altijd generiek antwoord → geen user enumeration
		if ( ! $user || self::user_has_excluded_roles( $user->ID ) ) {
			wp_safe_redirect( add_query_arg( 'requested', '1', wp_get_referer() ) );
			exit;
		}

		// Genereer one-time nonce
		$nonce = wp_create_nonce( 'z_login_once' );
		update_user_meta( $user->ID, 'z_login_once_nonce', $nonce );

		$link_url = add_query_arg(
			[ 'zloginonce' => $nonce ],
			home_url()
		);

		// Mail (hergebruik je bestaande opties)
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

		wp_safe_redirect( add_query_arg( 'requested', '1', wp_get_referer() ) );
		exit;
	}


}



