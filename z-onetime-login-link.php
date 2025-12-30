<?php
/**
 * Plugin Name: Z One-time Login Link
 * Contributors: martenmoolenaar, zodannl
 * Plugin URI: https://plugins.zodan.nl/wordpress-onetime-login-link/
 * Tags: direct login, fast login, no password, theme development, development
 * Requires at least: 5.5
 * Tested up to: 6.9
 * Description: Let users login once without a password
 * Version: 0.0.4
 * Author: Zodan
 * Author URI: https://zodan.nl
 * Text Domain: z-onetime-login-link
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
	$instance = z_onetime_login_link::get_instance();
	$instance->plugin_setup();
} );



class z_onetime_login_link {

	protected static $instance = NULL;
	public $plugin_version = '0.0.4';
	public $plugin_url = '';
	public $plugin_path = '';
	public $expire_time = HOUR_IN_SECONDS; 
	public $rate_limit_value = MINUTE_IN_SECONDS * 10; // wait 10 min   



    public static function get_instance() {
		NULL === self::$instance and self::$instance = new self;
		return self::$instance;
	}   



    public function __construct() {}   



    public function plugin_setup() {

		if ( ! defined( 'Z_ONETIME_LOGIN_LINK_VERSION' ) ) {
			define( 'Z_ONETIME_LOGIN_LINK_VERSION', $this->plugin_version  );
		}

		$this->plugin_url = plugins_url( '/', __FILE__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );

		$options = get_option( 'z_onetime_login_link_plugin_options' );
		if ( ! empty( $options['expire_time'] ) ) {
			$this->expire_time = intval( $options['expire_time'] );
		}
		if ( ! empty( $options['rate_limit_value'] ) ) {
			$this->rate_limit_value = intval( $options['rate_limit_value'] );
		}

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_plugin_settings_link' ] );

		add_action('login_enqueue_scripts', [ $this, 'add_request_link_after_login_nav' ] );

		add_action( 'login_form_zloginonce', [ $this, 'render_zloginonce_form' ] );	

		add_action( 'init', [ $this, 'handle_self_login_request' ] );


		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'handle_send_login_once_mail' ] );
			include( $this->plugin_path . 'admin.php' );
			add_filter ('user_row_actions', [ $this, 'add_send_zloginonce_link_mail' ], 10, 2) ;
			add_action( 'admin_notices', function() {
				if ( filter_has_var( INPUT_GET, 'zloginonce_sent' ) ) {
					echo '<div class="notice notice-success"><p>'.esc_html(__('One time login link sent.','z-onetime-login-link')).'</p></div>';
				}
			});
		}

		// Admin front end
		if ( ! is_admin() && ! is_user_logged_in() ) {
			add_action( 'init', [ $this, 'handle_login_from_url' ] );
		}
	}   



    public static function user_has_excluded_roles( $user_id ) {

		$options = get_option( 'z_onetime_login_link_plugin_options' );
		$roles = $options['roles'];
		if ( empty( $roles ) ) return false;

		$user = get_user_by('id', intval( $user_id ) );
		
		return ! empty( array_intersect( $roles, (array) $user->roles ) );
	}   



    public static function add_plugin_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=z_onetime_login_link">' . __( 'Settings','z-onetime-login-link' ) . '</a>';
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

		$hash = hash( 'sha256', $token );
		// Find user by nonce (one-time mapping)
		$users = get_users(
			array(
				'fields'     => array( 'ID' ),
				'meta_key' => 'z_login_once_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key  -- This is a one-time operation, and there is no more appropriate functionality available
				'meta_value' => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value  -- This is a one-time operation, and there is no more appropriate functionality available
			)
		);

		if ( empty( $users ) ) {
			wp_die(
				esc_html__( 'Invalid or expired login link.', 'z-onetime-login-link' ),
				esc_html__( 'Login error', 'z-onetime-login-link' ),
				[ 'response' => 403 ]
			);
		}

		// Bail out if more users exist
		// TODO in next version: separate, indexed table
		if ( count( $users ) !== 1 ) {
			wp_die(
				esc_html__( 'Invalid or expired login link.', 'z-onetime-login-link' ),
				esc_html__( 'Login error', 'z-onetime-login-link' ),
				[ 'response' => 403 ]
			);
		}


		$user_id = (int) $users[0]->ID;
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			wp_die(
				esc_html__( 'Invalid user.', 'z-onetime-login-link' ),
				esc_html__( 'Login error', 'z-onetime-login-link' ),
				[ 'response' => 403 ]
			);
		}

		if ( self::user_has_excluded_roles( $user_id ) ) {
			wp_die(
				esc_html__( 'Your role is excluded from fast login, please contact your website administrator.', 'z-onetime-login-link' ),
				esc_html__( 'Login error', 'z-onetime-login-link' ),
				[ 'response' => 403 ]
			);
		}

		$expires = (int) get_user_meta( $user_id, 'z_login_once_expires', true );

		if ( empty( $expires ) || time() > $expires ) {
			wp_die(
				esc_html__( 'Your login token has expired.', 'z-onetime-login-link' ),
				esc_html__( 'Login error', 'z-onetime-login-link' ),
				[ 'response' => 403 ]
			);
		}


		if( ! empty( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			// use fingerprinting
			$fingerprint = get_user_meta( $user_id, 'z_login_once_fingerprint', true );
			if ( $fingerprint !== hash( 'sha256', sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) . sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) ) ) {
				wp_die(
					esc_html__( 'Login link environment mismatch. Use the same browser/platform for both requesting and using the link.', 'z-onetime-login-link' ),
					esc_html__( 'Login error', 'z-onetime-login-link' ),
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
			'<a href="'.esc_url($url).'">'.__('Send login once link','z-onetime-login-link').'</a>';

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
		update_user_meta( $user->ID, 'z_login_once_expires', time() + $this->expire_time);
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


		$options = get_option( 'z_onetime_login_link_plugin_options' );

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



    public function add_request_link_after_login_nav() {

		$options = get_option( 'z_onetime_login_link_plugin_options' );
		if ( empty( $options['allow_user_request'] ) ) {
			return;
		}
				
		$url = wp_login_url() . '?action=zloginonce';

		?><script>
		document.addEventListener('DOMContentLoaded', function () {
        	var nav = document.getElementById('nav');
        	if (nav) {
            	var p = document.createElement('p');
            	p.id = 'zloginonce-request-link';
            	p.innerHTML = '<a href="<?php echo esc_url( $url ); ?>"><?php
					esc_html_e( 'Request a one-time login link', 'z-onetime-login-link' );
				?></a>';
            	nav.insertAdjacentElement('afterend', p);
        	}
    	});</script><style>#zloginonce-request-link{font-size:13px;margin-top: 10px;}</style><?php
	}   



    protected function is_rate_limited( $email ) {

		$options = get_option( 'z_onetime_login_link_plugin_options' );

		if ( empty( $options['use_rate_limit'] ) ) {
			return false;
		}

		$ip   = ! empty($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
		$key  = 'zloginonce_rl_' . md5( $ip . $email );

		if ( get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, 1, $this->rate_limit_value );
		return false;
	}   



    public function render_zloginonce_form() {

		if ( is_user_logged_in() ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		login_header(
			__( 'Email login link', 'z-onetime-login-link' ),
			'<p class="message">' . __( 'Enter your email address to receive a one-time login link.', 'z-onetime-login-link' ) . '</p>'
		);
		?>

		<form method="post" action="<?php echo esc_url( wp_login_url() . '?action=zloginonce' ); ?>">
			<p>
				<label for="zloginonce_email">
					<?php esc_html_e( 'Email address', 'z-onetime-login-link' ); ?>
				</label>
				<input type="email" name="zloginonce_email" id="zloginonce_email" class="input" required>
			</p>

			<?php wp_nonce_field( 'zloginonce_request', 'zloginonce_nonce' ); ?>

			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Send login link', 'z-onetime-login-link' ); ?>">
			</p>
		</form>

		<p id="nav">
			<a href="<?php echo esc_url( wp_login_url() ); ?>">
				<?php esc_html_e( '← Back to login', 'z-onetime-login-link' ); ?>
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
		update_user_meta( $user->ID, 'z_login_once_expires', time() + $this->expire_time );
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
		$options = get_option( 'z_onetime_login_link_plugin_options' );

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
