<?php
/**
 * Plugin Name: Zodan One-time Login Link
 * Contributors: martenmoolenaar, zodannl
 * Plugin URI: https://plugins.zodan.nl/wordpress-onetime-login-link/
 * Tags: direct login, fast login, no password, theme development, development
 * Requires at least: 5.5
 * Tested up to: 6.9
 * Description: Let users login once without a password
 * Version: 0.0.10
 * Author: Zodan
 * Author URI: https://zodan.nl
 * Text Domain: zodan-one-time-login-link
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 */



// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}


/**
 * Some constants
 * 
 */
if ( ! defined( 'ZODAN_ONETIME_LOGIN_LINK_VERSION' ) ) {
	define( 'ZODAN_ONETIME_LOGIN_LINK_VERSION', '0.0.10' );
}
if ( ! defined( 'ZODAN_ONETIME_LOGIN_LINK_PLUGIN_FILE' ) ) {
	define( 'ZODAN_ONETIME_LOGIN_LINK_PLUGIN_FILE', __FILE__ );
}


/**
 * Start: create an instance after the plugins have loaded
 * 
 */
add_action( 'plugins_loaded', function() {
	$instance = zodan_onetime_login_link::get_instance();
	$instance->plugin_setup();
} );



class zodan_onetime_login_link {

	protected static $instance = NULL;
	public $plugin_version = ZODAN_ONETIME_LOGIN_LINK_VERSION;
	public $plugin_name = 'zodan_onetime_login_link';
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

		$this->plugin_url = plugins_url( '/', __FILE__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );

		$options = get_option( 'zodan_onetime_login_link_plugin_options' );

		if ( ! empty( $options['expire_time'] ) ) {
			$this->expire_time = intval( $options['expire_time'] );
		}
		if ( ! empty( $options['rate_limit_value'] ) ) {
			$this->rate_limit_value = intval( $options['rate_limit_value'] );
		}

		/**
		 * Add a link to the settings on the plugins overview page
		 */
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_plugin_settings_link' ] );

		/**
		 * Modifying the login screen
		 */
		add_action('login_enqueue_scripts', [ $this, 'add_add_login_assets' ] );
		add_action('login_enqueue_scripts', [ $this, 'add_request_link_after_login_nav' ] );
		add_action( 'login_form_zodanloginonce', [ $this, 'render_zodanloginonce_form' ] );	

		/**
		 * Handle the request from the login screen
		 */
		add_action( 'init', [ $this, 'handle_self_login_request' ] );

		/**
		 * Process any scheduled batches
		 */
		add_action( 'zodanloginonce_process_batch', [ $this, 'process_login_link_batch' ] );

		/**
		 * The main login action: see if we have a user trying to login without a password
		 */
		if ( ! is_admin() && ! is_user_logged_in() ) {
			add_action( 'init', [ $this, 'handle_login_from_url' ] );
		}



		if ( is_admin() ) {
			
			/**
			 * Add a send login link to each user on the user screen
			 */
			add_filter ('user_row_actions', [ $this, 'add_send_zodanloginonce_link_mail' ], 10, 2) ;

			/**
			 * Handle actions, if set
			 */
			add_action( 'admin_init', [ $this, 'handle_send_login_once_mail' ] );
			add_action( 'admin_init', [ $this, 'handle_send_all_active_users' ] );
			add_action( 'admin_init', array( $this, 'handle_clear_log_request' ) );

			/**
			 * Bulk actions for the users.php screen
			 */
			add_filter( 'bulk_actions-users', [ $this, 'register_bulk_action' ] );
			add_filter( 'handle_bulk_actions-users', [ $this, 'handle_bulk_action' ], 10, 3 );

			/**
			 * Add a button to the notices part of the users screen to send all users a mail
			 */
			add_action( 'admin_notices', [ $this, 'render_send_all_active_users_button' ] );

			/**
			 * Enqueue scripts and styles
			 */
		    add_action( 'admin_enqueue_scripts', [ $this, 'zodan_onetime_login_link_add_admin_scripts' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_bulk_confirm_scripts' ] );

			/**
			 * Handle admin notices
			 */
			add_action( 'admin_notices', function() {
				if ( filter_has_var( INPUT_GET, 'zodanloginonce_sent' ) ) {
					echo '<div class="notice notice-success"><p>'.esc_html(__('One time login link sent.','zodan-one-time-login-link')).'</p></div>';
				}
			});
			add_action( 'admin_notices', function() {
				$queued = filter_input( INPUT_GET, 'zodanloginonce_queued', FILTER_VALIDATE_INT );
				if ( $queued !== null ) {
					echo '<div class="notice notice-success"><p>';
					printf(
						// translators: $d is the number of users that were added to the mail queue
						esc_html__( '%d users queued for one-time login emails.', 'zodan-one-time-login-link' ),
						(int) $queued
					);
					echo '</p></div>';
				}
				$none = filter_input( INPUT_GET, 'zodanloginonce_none', FILTER_VALIDATE_BOOLEAN );
				if ( $none ) {
					echo '<div class="notice notice-warning"><p>';
					echo esc_html__( 'No active users selected.', 'zodan-one-time-login-link' );
					echo '</p></div>';
				}
			});
			add_action( 'admin_notices', function() {
				$log = get_option( 'zodanloginonce_log', [] );
				if ( empty( $log ) ) {
					return;
				}
				echo '<div class="notice notice-info"><p><strong>';
				echo esc_html__( 'One-time login log:', 'zodan-one-time-login-link' );
				echo '</strong>';
				
				$action_url = add_query_arg( array( 
					'page' => $this->plugin_name, 
					'action' => 'zodan_onetimelogin_clear_log', 
				), admin_url( 'options-general.php' ) );
				$clear_log_url = wp_nonce_url(
					$action_url,
					'zodan_onetimelogin_clear_log_action',
					'zodan_onetimelogin_clear_log_action'
				);
				echo ' | <a href="'.esc_url($clear_log_url).'">'. esc_html__( 'Clear log', 'zodan-one-time-login-link' ).'</a>';
				echo '</p><ul>';

				foreach ( array_reverse( $log ) as $entry ) {
					echo '<li>';
					printf(
						// translators: $s, $s and $d are respectively the time of logging, the type of logging and the number of logged items
						'%s — %s (%d)',
						esc_html( $entry['time'] ),
						esc_html( ucfirst( $entry['type'] ) ),
						(int) $entry['count']
					);
					echo '</li>';
				}
				echo '</ul></div>';
			});
			add_action( 'admin_notices', function() {
				$cleared = filter_input( INPUT_GET, 'zodanloginonce_log_cleared', FILTER_VALIDATE_INT );
				if ( $cleared !== null ) {
					echo '<div class="notice notice-success"><p>';
					esc_html_e( 'Log cleared', 'zodan-one-time-login-link' );
					echo '</p></div>';
				}
			});

			/**
			 * Include the admin settings screens
			 */
			include( $this->plugin_path . 'admin.php' );

		}
	} 


	/**
	 * Check if the user has any of the roles than cannot log in without a password
	 * 
	 * @param (int) $user_id
	 * @return (boolean)
	 */  
    public static function user_has_excluded_roles( $user_id ) {

		$options = get_option( 'zodan_onetime_login_link_plugin_options' );
		$roles = $options['roles'];
		if ( empty( $roles ) ) return false;

		$user = get_user_by('id', intval( $user_id ) );
		
		return ! empty( array_intersect( $roles, (array) $user->roles ) );
	} 


	/**
	 * Add a link to the plugin settings on the plugins overview screen
	 * 
	 * @param (array) $links
	 * @return (array) $links
	 */  
    public function add_plugin_settings_link( $links ) {

		$settings_link = '<a href="'.admin_url( 'options-general.php?page='.esc_attr($this->plugin_name)).'">' . __( 'Settings','zodan-one-time-login-link' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	} 


	/**
	 * =========================================================
	 * Handle the login based on a login url with the logintoken
	 * =========================================================
	 * 
	 */  
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
		if ( empty( $_GET['zodanloginonce'] ) ) { 
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
    	$token = sanitize_text_field( wp_unslash( $_GET['zodanloginonce'] ) );
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
				esc_html__( 'Invalid or expired login link.', 'zodan-one-time-login-link' ),
				esc_html__( 'Login error', 'zodan-one-time-login-link' ),
				[ 'response' => 403 ]
			);
		}

		// Bail out if more users exist
		// TODO in next version: separate, indexed table
		if ( count( $users ) !== 1 ) {
			wp_die(
				esc_html__( 'Invalid or expired login link.', 'zodan-one-time-login-link' ),
				esc_html__( 'Login error', 'zodan-one-time-login-link' ),
				[ 'response' => 403 ]
			);
		}


		$user_id = (int) $users[0]->ID;
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			wp_die(
				esc_html__( 'Invalid user.', 'zodan-one-time-login-link' ),
				esc_html__( 'Login error', 'zodan-one-time-login-link' ),
				[ 'response' => 403 ]
			);
		}

		if ( self::user_has_excluded_roles( $user_id ) ) {
			wp_die(
				esc_html__( 'Your role is excluded from fast login, please contact your website administrator.', 'zodan-one-time-login-link' ),
				esc_html__( 'Login error', 'zodan-one-time-login-link' ),
				[ 'response' => 403 ]
			);
		}

		$expires = (int) get_user_meta( $user_id, 'z_login_once_expires', true );

		if ( empty( $expires ) || time() > $expires ) {
			wp_die(
				esc_html__( 'Your login token has expired.', 'zodan-one-time-login-link' ),
				esc_html__( 'Login error', 'zodan-one-time-login-link' ),
				[ 'response' => 403 ]
			);
		}

		// use fingerprinting
		if ( ! empty($options['use_fingerprinting']) ) {
			if( ! empty( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
				$fingerprint = get_user_meta( $user_id, 'z_login_once_fingerprint', true );
				if( ! empty( $fingerprint ) ) {
					if ( $fingerprint !== hash( 'sha256', sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) . sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) ) ) {
						wp_die(
							esc_html__( 'Login link environment mismatch. Use the same browser/platform for both requesting and using the link.', 'zodan-one-time-login-link' ),
							esc_html__( 'Login error', 'zodan-one-time-login-link' ),
							[ 'response' => 403 ]
						);
					}
				}
			}
		}

		/**
		 * MULTISITE: switch after validation
		 * ---------
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
		 * LOGIN
		 * -----
		 */
		// Set WP authorisation cookie
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $user_id );

		// if you want is_user_logged_in to work you should set `wp_set_current_user` explicityly
		wp_set_current_user( $user_id );

		// The WP login action
		do_action( 'wp_login', $user->user_login, $user );	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- We are not invoking a plugin-specific action, but actually activating wp_login

		/**
		 * Delete the token → link is now invalid
		 * ----------------
		 */
		delete_user_meta( $user_id, 'z_login_once_token' );
		delete_user_meta( $user_id, 'z_login_once_expires' );

		/**
		 * MULTISITE: restore context
		 * ---------
		 */
		if ( $switched ) {
			restore_current_blog();
		}

		wp_safe_redirect(
			is_multisite() ? network_home_url() : home_url()
		);
		exit;
	}   



    public function add_send_zodanloginonce_link_mail( $actions, $user ) {

		if ( self::user_has_excluded_roles( $user->ID ) ) {
			return $actions;
		}

		// Setup the mail link
		$action_url = add_query_arg( array( 
			'user_id' => $user->ID, 
			'action' => 'add_send_zodanloginonce_link_mail', 
		), admin_url( 'users.php' ) );

		$url = wp_nonce_url(
			$action_url,
			'zsendloginoncelink',
			'z_send_login_once_link_nonce'
		);

		$actions['zodanloginonce'] =
			'<a href="'.esc_url($url).'">'.__('Send login once link','zodan-one-time-login-link').'</a>';

		return $actions;

	}   



    public function handle_send_login_once_mail() {

		if (
			empty($_GET['action']) ||
			$_GET['action'] !== 'add_send_zodanloginonce_link_mail' ||
			empty($_GET['user_id']) ||
			empty($_GET['z_send_login_once_link_nonce'])
		) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET['z_send_login_once_link_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'zsendloginoncelink' ) ) {
			return;
		}

		$this->send_login_link_to_user(intval($_GET['user_id']) );

		$redirect_url = admin_url( 'users.php?zodanloginonce_sent' );
		wp_safe_redirect($redirect_url);
		exit;
	}   



    public function add_request_link_after_login_nav() {

		$options = get_option( 'zodan_onetime_login_link_plugin_options' );
		if ( empty( $options['allow_user_request'] ) ) {
			return;
		}
		$url = wp_login_url() . '?action=zodanloginonce';
		$vars = array(
			'requestLinkURL' => esc_url($url),
			'requestLinkText' => esc_html__( 'Request a One-time Login Link', 'zodan-one-time-login-link' )
		);
		wp_localize_script( 'zodan-onetime-login-login-scripts', 'zOnetimeLoginLinkVars', $vars );
	}



    public function add_add_login_assets() {
        $plugin_url = plugins_url( '/', __FILE__ );
        $login_css = $plugin_url . 'assets/login-styles.css';
		wp_enqueue_style(
			'zodan-onetime_login_css',
			esc_url($login_css),
			array(), ZODAN_ONETIME_LOGIN_LINK_VERSION
		);
		$login_script = $plugin_url . 'assets/login-scripts.js';
		wp_enqueue_script(
			'zodan-onetime-login-login-scripts',
			esc_url($login_script),
			array(),
			ZODAN_ONETIME_LOGIN_LINK_VERSION,
			true
		);
	}  



    protected function is_rate_limited( $email ) {

		$options = get_option( 'zodan_onetime_login_link_plugin_options' );

		if ( empty( $options['use_rate_limit'] ) ) {
			return false;
		}

		$ip   = ! empty($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
		$key  = 'zodanloginonce_rl_' . md5( $ip . $email );

		if ( get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, 1, $this->rate_limit_value );
		return false;
	}   



    public function render_zodanloginonce_form() {

		if ( is_user_logged_in() ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		login_header(
			__( 'Email login link', 'zodan-one-time-login-link' ),
			'<p class="message">' . __( 'Enter your email address to receive a One-time Login Link.', 'zodan-one-time-login-link' ) . '</p>'
		);
		?>

		<form method="post" action="<?php echo esc_url( wp_login_url() . '?action=zodanloginonce' ); ?>">
			<p>
				<label for="zodanloginonce_email">
					<?php esc_html_e( 'Email address', 'zodan-one-time-login-link' ); ?>
				</label>
				<input type="email" name="zodanloginonce_email" id="zodanloginonce_email" class="input" required>
			</p>

			<?php wp_nonce_field( 'zodanloginonce_request', 'zodanloginonce_nonce' ); ?>

			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Send login link', 'zodan-one-time-login-link' ); ?>">
			</p>
		</form>

		<p id="nav">
			<a href="<?php echo esc_url( wp_login_url() ); ?>">
				<?php esc_html_e( '← Back to login', 'zodan-one-time-login-link' ); ?>
			</a>
		</p>

		<?php
		login_footer();
		exit;
	}   



    public function handle_self_login_request() {

		if ( empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty( $_POST['zodanloginonce_email'] ) || empty( $_POST['zodanloginonce_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zodanloginonce_nonce'] ) ), 'zodanloginonce_request' ) ) {
			wp_die( 'Nonce not verified' );
		}

		$email = sanitize_email( wp_unslash( $_POST['zodanloginonce_email'] ) );
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

		$this->send_login_link_to_user( $user->ID, true );

		wp_safe_redirect( wp_login_url() . '?checkemail=confirm' );
		exit;
	}  



    public function render_send_all_active_users_button() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( empty( $screen ) || $screen->id !== 'users' ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'users.php?action=zodanloginonce_send_all_active' ),
			'zodanloginonce_send_all_active'
		);

		echo '<div class="notice notice-warning">';
		echo '<p><strong>' . esc_html__( 'Global action:', 'zodan-one-time-login-link' ) . '</strong><br>';
		echo esc_html__( 'Send a One-time Login Link to ALL active users.', 'zodan-one-time-login-link' ) . '</p>';
		echo '<p><a href="' . esc_url( $url ) . '" class="button button-primary zodanloginonce-send-all">';
		echo esc_html__( 'Send to ALL active users', 'zodan-one-time-login-link' );
		echo '</a></p>';
		echo '</div>';
	}  



    public function register_bulk_action( $actions ) {
		$actions['zodanloginonce_send'] = __( 'Send One-time Login Link', 'zodan-one-time-login-link' );
		return $actions;
	}  



    /**
	 * Enqueue default scripts
	 * 
	 */ 
    public function zodan_onetime_login_link_add_admin_scripts( $hook ) {

        if ( is_admin() ) {

            $plugin_url = plugins_url( '/', __FILE__ );
            $admin_css = $plugin_url . 'assets/admin-styles.css';
            wp_enqueue_style( 'zodan-onetime-login-link-admin-styles', esc_url($admin_css), array(), ZODAN_ONETIME_LOGIN_LINK_VERSION );

            $admin_js = $plugin_url . 'assets/admin-scripts.js';
            wp_register_script( 'zodan-onetime-login-link-admin-scripts', esc_url( $admin_js ) , array( 'jquery' ), ZODAN_ONETIME_LOGIN_LINK_VERSION, array( 'in_footer' => true ) );
            wp_localize_script('zodan-onetime-login-link-admin-scripts', 'zodan_onetime_login_link_admin', array(
				'copiedText' => esc_html__('code copied', 'zodan-one-time-login-link'),
			) );
            wp_enqueue_script( 'zodan-onetime-login-link-admin-scripts' );
        }
    }  



    /**
	 * Enqueue bulk action scripts
	 * 
	 */ 
    public function enqueue_bulk_confirm_scripts ( $hook ) {

		if ( $hook !== 'users.php' ) {
			return;
		}
        if ( is_admin() ) {

			$plugin_url = plugins_url( '/', __FILE__ );
			$admin_user_actions_js = $plugin_url . 'assets/admin-user-actions-scripts.js';
			wp_register_script( 'zodan-onetime-login-link-admin-users-actions-scripts', esc_url( $admin_user_actions_js ) , array( 'jquery' ), ZODAN_ONETIME_LOGIN_LINK_VERSION, array( 'in_footer' => true ) );

			wp_localize_script('zodan-onetime-login-link-admin-users-actions-scripts', 'zOnetimeLoginLinkUserActionsBulkVars', array(
				'triggerAllUsersMessage' => esc_html__( 'This will email ALL active users. Continuing in', 'zodan-one-time-login-link' ),
				'confirmAllUsersMessage' => esc_html__( 'Are you absolutely sure? This action cannot be undone.', 'zodan-one-time-login-link' ),
				'allUsersButtonText' => esc_html__( 'Send to ALL active users', 'zodan-one-time-login-link' ),
				'confirmSelectedUsersMessage' => esc_html__( 'Are you sure you want to send a One-time Login Link to the selected users?', 'zodan-one-time-login-link' )
			) );
			wp_enqueue_script( 'zodan-onetime-login-link-admin-users-actions-scripts' );
		}

	} 



 















    public function handle_send_all_active_users() {

		if ( empty( $_GET['action'] ) || $_GET['action'] !== 'zodanloginonce_send_all_active' ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'zodanloginonce_send_all_active' );

		$users = get_users( [
			'fields' => [ 'ID' ],
			'number' => -1,
		] );

		$queue = [];

		foreach ( $users as $user ) {

			if ( self::user_has_excluded_roles( $user->ID ) ) {
				continue;
			}

			// Actief = nooit ingelogd OF binnen 1 jaar
			$last_login = (int) get_user_meta( $user->ID, 'last_login', true );

			if ( $last_login === 0 || $last_login > strtotime( '-1 year' ) ) {
				$queue[] = (int) $user->ID;
			}
		}

		if ( empty( $queue ) ) {
			wp_safe_redirect( add_query_arg( 'zodanloginonce_none', '1', admin_url( 'users.php' ) ) );
			exit;
		}

		set_transient( 'zodanloginonce_bulk_queue', $queue, HOUR_IN_SECONDS );

		$this->log_event( 'queued_all', count( $queue ) );

		if ( ! wp_next_scheduled( 'zodanloginonce_process_batch' ) ) {
			wp_schedule_single_event( time() + 10, 'zodanloginonce_process_batch' );
		}

		wp_safe_redirect(
			add_query_arg(
				'zodanloginonce_queued',
				count( $queue ),
				admin_url( 'users.php' )
			)
		);
		exit;
	}



    public function handle_bulk_action( $redirect_to, $action, $user_ids ) {

		if ( $action !== 'zodanloginonce_send' ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $redirect_to;
		}

		// Filter actieve gebruikers
		$active_user_ids = [];

		foreach ( $user_ids as $user_id ) {

			if ( self::user_has_excluded_roles( $user_id ) ) {
				continue;
			}

			// Actief = recent ingelogd of account niet geblokkeerd
			$last_login = (int) get_user_meta( $user_id, 'last_login', true );

			if ( $last_login === 0 || $last_login > strtotime( '-1 year' ) ) {
				$active_user_ids[] = (int) $user_id;
			}
		}

		if ( empty( $active_user_ids ) ) {
			return add_query_arg( 'zodanloginonce_none', '1', $redirect_to );
		}

		// Opslaan voor async verwerking
		set_transient(
			'zodanloginonce_bulk_queue',
			array_values( $active_user_ids ),
			HOUR_IN_SECONDS
		);

		// Start cron
		if ( ! wp_next_scheduled( 'zodanloginonce_process_batch' ) ) {
			wp_schedule_single_event( time() + 10, 'zodanloginonce_process_batch' );
		}

		return add_query_arg(
			'zodanloginonce_queued',
			count( $active_user_ids ),
			$redirect_to
		);
	}



    public function process_login_link_batch() {

		$queue = get_transient( 'zodanloginonce_bulk_queue' );

		if ( empty( $queue ) || ! is_array( $queue ) ) {
			delete_transient( 'zodanloginonce_bulk_queue' );
			return;
		}

		$batch_size = 25;
		$batch      = array_splice( $queue, 0, $batch_size );

		$this->log_event( 'batch_sent', count( $batch ) );

		foreach ( $batch as $user_id ) {
			$this->send_login_link_to_user( $user_id );
		}

		// Queue bijwerken
		if ( empty( $queue ) ) {
			delete_transient( 'zodanloginonce_bulk_queue' );
			$this->log_event( 'completed', 0 );

		} else {
			set_transient( 'zodanloginonce_bulk_queue', $queue, HOUR_IN_SECONDS );
			wp_schedule_single_event( time() + 10, 'zodanloginonce_process_batch' );
		}
	}



	public function send_login_link_to_user( $user_id = 0, $usefingerprinting = false ) {

		$options = get_option( 'zodan_onetime_login_link_plugin_options' );

		$user = get_user_by( 'id', intval($user_id) );
		// Bail out if the user does not exist
		if ( ! $user ) {
			return;
		}
		// Bail out if the user has a role that prohibits fast login
		if ( self::user_has_excluded_roles( $user->ID ) ) {
			return;
		}

		// Delete old tokens, if any
		delete_user_meta( $user->ID, 'z_login_once_token' );
		delete_user_meta( $user->ID, 'z_login_once_expires' );
		delete_user_meta( $user->ID, 'z_login_once_fingerprint' );

		// Prepare fingerprinting
		if ( $usefingerprinting === 'true' && ! empty($options['use_fingerprinting']) ) {
			if( ! empty( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
				// use fingerprinting
				update_user_meta(
					$user->ID,
					'z_login_once_fingerprint',
					hash( 'sha256', sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) . sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) )
				);
			}
		}

		// Create login token for the user
		$token = bin2hex( random_bytes( 32 ) ); // 64 chars
		$hash  = hash( 'sha256', $token );

		update_user_meta( $user->ID, 'z_login_once_token', $hash );
		update_user_meta( $user->ID, 'z_login_once_expires', time() + $this->expire_time );

		// Create login link
		$link_url = add_query_arg(
			[ 'zodanloginonce' => $token ],
			is_multisite() ? network_home_url() : home_url()
		);

		// Prepare content
		$subject = $options['mail_subject'];
		$linktext = $options['mail_linktext'] ?: $link_url;
		$link_html = '<a href="'.esc_url($link_url).'">'.esc_html($linktext).'</a>';
		$content = str_replace(
			['{{displayname}}','{{firstname}}','{{zloginlink}}'],
			[$user->display_name, $user->first_name, $link_html],
			wpautop($options['mail_content'])
		);

		// Sendmail
		wp_mail(
			$user->user_email,
			$subject,
			$content,
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);
	}



    protected function log_event( $type, $count = 0 ) {

        $options = get_option( 'zodan_onetime_login_link_plugin_options' );
		if( empty($options['use_bulk_mail_log']) ) {
			return;
		}
		$log = get_option( 'zodanloginonce_log', [] );
		if(empty($log) || ! is_array($log) ) {
			$log = array();
		}

		$log[] = [
			'time'  => current_time( 'mysql' ),
			'user'  => get_current_user_id(),
			'type'  => $type,
			'count' => (int) $count,
		];

		// Max 100 entries bewaren
		if ( count( $log ) > 100 ) {
			$log = array_slice( $log, -100 );
		}

		update_option( 'zodanloginonce_log', $log, false );
	}



    public function handle_clear_log_request() {

        if ( empty($_GET['action']) || $_GET['action'] !== 'zodan_onetimelogin_clear_log') {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( empty($_GET['zodan_onetimelogin_clear_log_action']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['zodan_onetimelogin_clear_log_action'] ) ), 'zodan_onetimelogin_clear_log_action' ) ) {
            return;
        }

        // All fine, let's continue
        update_option( 'zodanloginonce_log', array(), false );

        wp_safe_redirect(
            admin_url( 'options-general.php?page=' . $this->plugin_name . '&zodanloginonce_log_cleared=1' )
        );
        exit;
    }



}