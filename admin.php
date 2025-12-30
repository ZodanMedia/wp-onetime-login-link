<?php

/**
 * Settings page for Z Onetime Login Link
 *
 * Author: Zodan
 * Author URI: https://zodan.nl
 * License: GPL2+
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}


if ( ! defined( 'Z_ONETIME_LOGIN_LINK_VERSION' ) ) {
	define( 'Z_ONETIME_LOGIN_LINK_VERSION', gmdate('Ymd') );
}



if ( !function_exists( 'z_onetime_login_link_register_settings' ) ) {

    function z_onetime_login_link_register_settings() {
		
		$settings_args = array(
			'type' => 'array',
			'description' => '',
			'sanitize_callback' => 'z_onetime_login_link_plugin_options_validate',
			'show_in_rest' => false
		);
        register_setting( 'z_onetime_login_link_plugin_options', 'z_onetime_login_link_plugin_options', $settings_args);

		// Add the introduction section
		add_settings_section(
			'z_onetime_login_link_introduction_section',
			esc_html__('Introduction', 'z-onetime-login-link'),
			'z_onetime_login_link_introduction_section_text',
			'z_onetime_login_link_plugin'
		);

		// Add the Roles section
		add_settings_section(
			'z_onetime_login_link_roles_section',
			esc_html__('Roles', 'z-onetime-login-link'),
			'z_onetime_login_link_roles_section_text',
			'z_onetime_login_link_plugin'
		);

        // Field: Role selection
		add_settings_field(
			'z_onetime_login_link_select_roles',
			esc_html__('Excluded roles', 'z-onetime-login-link') . '<span class="description">' .
                esc_html__( 'Some user roles should not have a fast login', 'z-onetime-login-link' ) . '</span>', 
			'z_onetime_login_link_render_roles_checkboxes',
			'z_onetime_login_link_plugin',
			'z_onetime_login_link_roles_section'
		);

		// Add the Mail section
		add_settings_section(
			'z_onetime_login_link_mail_section',
			esc_html__('Mail settings', 'z-onetime-login-link'),
			'z_onetime_login_link_mail_section_text',
			'z_onetime_login_link_plugin'
		);

        // Field: Mail link text section
		add_settings_field(
			'z_onetime_login_link_mail_linktext',
			esc_html__('Login link text', 'z-onetime-login-link'), 
			'z_onetime_login_link_render_mail_linktext',
			'z_onetime_login_link_plugin',
			'z_onetime_login_link_mail_section'
		);

        // Field: Mail subject section
		add_settings_field(
			'z_onetime_login_link_mail_subject',
			esc_html__('Mail subject', 'z-onetime-login-link'), 
			'z_onetime_login_link_render_mail_subject',
			'z_onetime_login_link_plugin',
			'z_onetime_login_link_mail_section'
		);

        // Field: Mail template section
		add_settings_field(
			'z_onetime_login_link_mail_content',
			esc_html__('Mail content', 'z-onetime-login-link'), 
			'z_onetime_login_link_render_mail_content',
			'z_onetime_login_link_plugin',
			'z_onetime_login_link_mail_section'
		);

		// Add the other options section
		add_settings_section(
			'z_onetime_login_link_other_options_section',
			esc_html__('Other options', 'z-onetime-login-link'),
			'z_onetime_login_link_other_options_section_text',
			'z_onetime_login_link_plugin'
		);

        // Field: Expiration time
		add_settings_field(
			'z_onetime_login_link_user_token_expire_time',
			esc_html__('Link expiration time', 'z-onetime-login-link'), 
			'z_onetime_login_link_render_token_expire_time',
			'z_onetime_login_link_plugin',
			'z_onetime_login_link_other_options_section'
		);

        // Field: Allow user request
		add_settings_field(
			'z_onetime_login_link_user_request',
			esc_html__('User request', 'z-onetime-login-link'), 
			'z_onetime_login_link_render_user_request_checkbox',
			'z_onetime_login_link_plugin',
			'z_onetime_login_link_other_options_section'
		);

        // Field: Use rate limiting
		add_settings_field(
			'z_onetime_login_link_user_request_rate_limit',
			esc_html__('Use rate limiting', 'z-onetime-login-link'), 
			'z_onetime_login_link_render_rate_limit_checkbox',
			'z_onetime_login_link_plugin',
			'z_onetime_login_link_other_options_section'
		);

        // Field: Use rate limiting
		add_settings_field(
			'z_onetime_login_link_user_request_rate_limit_value',
			esc_html__('Rate limit', 'z-onetime-login-link'), 
			'z_onetime_login_link_render_rate_limit_value',
			'z_onetime_login_link_plugin',
			'z_onetime_login_link_other_options_section'
		);

        // Field: Use log for bulk mail
		add_settings_field(
			'z_onetime_login_link_bulk_mail_logging',
			esc_html__('Log bulk mailings', 'z-onetime-login-link'), 
			'z_onetime_login_link_render_bulk_mail_logging',
			'z_onetime_login_link_plugin',
			'z_onetime_login_link_other_options_section'
		);
    }

    add_action( 'admin_init', 'z_onetime_login_link_register_settings' );   



    function z_onetime_login_link_introduction_section_text() { 
        echo '<p>' . esc_html__('With the One-time Login Link you can give users the option to log into Wordpress without a password.', 'z-onetime-login-link') . '</p>';
       
        echo '<p>&nbsp;</p>';
    }   



    function z_onetime_login_link_roles_section_text() { 
        echo '<p><strong class="z-warning zotll-warning">' . esc_html__('Please note', 'z-onetime-login-link') . '</strong> ';
        echo esc_html__("While we've prioritized security in developing this plugin, we still recommend against using direct login for certain roles.", 'z-onetime-login-link');
        echo '<br>';
        echo esc_html__('Be wise and exclude roles like Administrators, Editors, Shop managers.', 'z-onetime-login-link');
        echo '</p>';

        $users_url = admin_url( 'users.php');
        echo '<p><span class="dashicons dashicons-email-alt"></span> '.esc_html__( 'You can send a one-time login link to ALL active users from the', 'z-onetime-login-link' ). ' ';
		printf('<a href="%s">%s</a>.',
            esc_url( $users_url ),
            esc_html__( 'users admin page', 'z-onetime-login-link' )
        );
        echo '</p>';        
    }   



    function z_onetime_login_link_render_roles_checkboxes() {
        global $wp_roles;
        $roles = $wp_roles->roles;
        $options = get_option( 'z_onetime_login_link_plugin_options' );
        $enabled_roles = isset( $options['roles'] ) ? $options['roles'] : array();

        foreach ( $roles as $role_slug => $role_details ) {
            printf(
                '<label><input type="checkbox" name="z_onetime_login_link_plugin_options[roles][]" value="%s" %s> %s</label><br>',
                esc_attr( $role_slug ),
                checked( in_array( $role_slug, $enabled_roles ), true, false ),
                esc_html( $role_details['name'] )
            );
        }
    }   



    function z_onetime_login_link_mail_section_text() {}   



    function z_onetime_login_link_render_mail_linktext() {
        $options = get_option( 'z_onetime_login_link_plugin_options' );

        $mail_linktext = isset( $options['mail_linktext'] ) ? $options['mail_linktext'] : __('Click here to login without a password', 'z-onetime-login-link');

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="text" id="zmail-linktext" name="z_onetime_login_link_plugin_options[mail_linktext]" value="%s"> <span id="zlinktext-example-frame"> %s: <a href="#" id="zlinktext-example"></a></span></label><br>%s.',
            esc_html(__('Text', 'z-onetime-login-link')),
            esc_html( $mail_linktext),
            esc_html(__('resulting in something like', 'z-onetime-login-link')),
            esc_html(__('If you leave this blank, the link text will display the URL', 'z-onetime-login-link')),
        );
    }   



    function z_onetime_login_link_render_mail_subject() {
        $options = get_option( 'z_onetime_login_link_plugin_options' );

        $mail_subject = isset( $options['mail_subject'] ) ? $options['mail_subject'] : __('Your One-time Login link', 'z-onetime-login-link');

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="text" name="z_onetime_login_link_plugin_options[mail_subject]" value="%s"></label>',
            esc_html(__('Subject', 'z-onetime-login-link')),
            esc_html( $mail_subject)
        );
    }   



    function z_onetime_login_link_render_mail_content() {
        $options = get_option( 'z_onetime_login_link_plugin_options' );

		$default_mail_content = '<p>'.__('Hello {{firstname}}', 'z-onetime-login-link').'</p>';
        $default_mail_content .= '<p>'.__('With the following link you can directly log in without having to enter your password.', 'z-onetime-login-link').'</p>';
        $default_mail_content .= '<p>{{zloginlink}}</p><p>'.__('This link can be used only once.', 'z-onetime-login-link').'</p>';

        $mail_content =  ! empty( $options['mail_content'] ) ? $options['mail_content'] : $default_mail_content;

        $id = 'z_onetime_login_link_plugin_mail_content';

        $settings = array(
            'media_buttons' => false,
            'textarea_name' => 'z_onetime_login_link_plugin_options[mail_content]',
            'textarea_rows' => 10,
            'editor_class' => 'z_onetime_login_link_editor'
        );
        echo '<p>';
        // echo esc_html(__("Enter the mail content that will be sent to the user.", 'z-onetime-login-link')).'<br>';
        echo esc_html(__("The text must include the {{zloginlink}} code where the actual login link should be included.", 'z-onetime-login-link')).'<br>';
        echo esc_html(__("You can also include the {{displayname}} and {{firstname}} codes for the user's display name and first name respectively.", 'z-onetime-login-link')).'</p>';
        wp_editor( $mail_content, $id, $settings );

    }   



    function z_onetime_login_link_other_options_section_text() {}   



    function z_onetime_login_link_render_user_request_checkbox() {
        $options = get_option( 'z_onetime_login_link_plugin_options' );
        $allow_user_request = isset( $options['allow_user_request'] ) ? $options['allow_user_request'] : false;
        printf(
            '<label><input type="checkbox" name="z_onetime_login_link_plugin_options[allow_user_request]" value="1" %s> %s</label><br>',
            checked( $allow_user_request, true, false ),
            esc_html( __('Allow users to request a new One-time Login Link on the login form, similar to the reset password method.', 'z-onetime-login-link') )
        );
    }   



    function z_onetime_login_link_render_token_expire_time() {
        $options = get_option( 'z_onetime_login_link_plugin_options' );

        $expire_time = isset( $options['expire_time'] ) ? $options['expire_time'] : intval(HOUR_IN_SECONDS);

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="number" name="z_onetime_login_link_plugin_options[expire_time]" value="%s" min="300"> %s</label>',
            esc_html(__('Subject', 'z-onetime-login-link')),
            esc_html( $expire_time),
            esc_html(__('seconds', 'z-onetime-login-link')),
        );
    }   



    function z_onetime_login_link_render_rate_limit_checkbox() {
        $options = get_option( 'z_onetime_login_link_plugin_options' );
        $use_rate_limit = isset( $options['use_rate_limit'] ) ? $options['use_rate_limit'] : false;
        printf(
            '<label><input type="checkbox" id="z_use_rate_limit" name="z_onetime_login_link_plugin_options[use_rate_limit]" value="1" %s> %s</label><br>',
            checked( $use_rate_limit, true, false ),
            esc_html( __('Use rate limiting to the user requests (e.g. max. once every 10 minutes)', 'z-onetime-login-link') )
        );
    }   



    function z_onetime_login_link_render_rate_limit_value() {
        $options = get_option( 'z_onetime_login_link_plugin_options' );

        $rate_limit_value = isset( $options['rate_limit_value'] ) ? $options['rate_limit_value'] : intval(MINUTE_IN_SECONDS * 10);

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="number" id="z_rate_limit_value" name="z_onetime_login_link_plugin_options[rate_limit_value]" value="%s" min="60"> %s</label>',
            esc_html(__('Subject', 'z-onetime-login-link')),
            esc_html( $rate_limit_value),
            esc_html(__('seconds between requests', 'z-onetime-login-link')),
        );
    }   



    function z_onetime_login_link_render_bulk_mail_logging() {
        $options = get_option( 'z_onetime_login_link_plugin_options' );

        $use_bulk_mail_log = isset( $options['use_bulk_mail_log'] ) ? $options['use_bulk_mail_log'] : 0;

        printf(
            '<label><input type="checkbox" id="z_use_bulk_mail_log" name="z_onetime_login_link_plugin_options[use_bulk_mail_log]" value="1" %s> %s</label><br>',
            checked( $use_bulk_mail_log, true, false ),
            esc_html( __('Log bulk mail activities (number of users processed, scheduled cron jobs etc.)', 'z-onetime-login-link') )
        );

    }   



    function z_onetime_login_link_plugin_options_validate( $input ) {
        $output = array();

        if ( isset( $input['mail_subject'] ) ) {
            $output['mail_subject'] = sanitize_text_field( $input['mail_subject'] );
        }

        if ( isset( $input['mail_linktext'] ) ) {
            $output['mail_linktext'] = sanitize_text_field( $input['mail_linktext'] );
        }

        if ( isset( $input['mail_content'] ) ) {
            $output['mail_content'] = wp_kses_post( $input['mail_content'] );
        }

        if ( isset( $input['allow_user_request'] ) ) {
            $output['allow_user_request'] = true;
        }

        if ( isset( $input['use_rate_limit'] ) ) {
            $output['use_rate_limit'] = true;
        }

        if ( isset( $input['expire_time'] ) ) {
            $expire_time = intval($input['expire_time']);
            if( empty($expire_time) ) {
                $expire_time = HOUR_IN_SECONDS;
            } elseif ( $expire_time < 300 ) {// set minimum to 5 minutes
                $expire_time = 300;
            }
            $output['expire_time'] = $expire_time;
        }

        if ( isset( $input['rate_limit_value'] ) ) {
            $expire_time = intval($input['rate_limit_value']);
            if( empty($rate_limit_value) ) {
                $rate_limit_value = MINUTE_IN_SECONDS * 10;
            } elseif ( $rate_limit_value < 60 ) { // set minimum to 1 minute
                $rate_limit_value = 300;
            }
            $output['rate_limit_value'] = $rate_limit_value;
        }

        if ( isset( $input['roles'] ) && is_array( $input['roles'] ) ) {
            global $wp_roles;
            $all_roles = array_keys( $wp_roles->roles );
            $valid_roles = array_intersect( $input['roles'], $all_roles );
            $output['roles'] = array_map( 'sanitize_text_field', $valid_roles );
        }

        return $output;
    }   



    function z_onetime_login_link_add_admin_menu() {
        add_options_page(
            __('Login once', 'z-onetime-login-link'),
            'Login Once',
            'manage_options',
            'z_onetime_login_link',
            'z_onetime_login_link_options_page'
        );
    }
    add_action( 'admin_menu', 'z_onetime_login_link_add_admin_menu' );   



    function z_onetime_login_link_options_page() {
        add_filter('admin_footer_text', 'z_onetime_login_link_admin_footer_print_thankyou', 900);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Z One-time Login Link settings', 'z-onetime-login-link'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'z_onetime_login_link_plugin_options' );
                do_settings_sections( 'z_onetime_login_link_plugin' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }


    /*
    * Enqueue scripts and styles
    *
    *
    */
    add_action( 'admin_enqueue_scripts', 'z_onetime_login_link_add_admin_scripts' );
    function z_onetime_login_link_add_admin_scripts( $hook ) {

        if ( is_admin() ) {

            $plugin_url = plugins_url( '/', __FILE__ );
            $admin_css = $plugin_url . 'assets/admin-styles.css';
            wp_enqueue_style( 'z-onetime-login-link-admin-styles', esc_url($admin_css), array(), Z_ONETIME_LOGIN_LINK_VERSION );

            $admin_js = $plugin_url . 'assets/admin-scripts.js';
            wp_register_script( 'z-onetime-login-link-admin-scripts', esc_url( $admin_js ) , array( 'jquery' ), Z_ONETIME_LOGIN_LINK_VERSION, array( 'in_footer' => true ) );
            wp_localize_script('z-onetime-login-link-admin-scripts', 'z_onetime_login_link_admin', array(
                    'copiedText' => esc_html__('PHP code copied!', 'z-onetime-login-link'),
                )
            );
            wp_enqueue_script( 'z-onetime-login-link-admin-scripts' );
        }
    }   



    function z_onetime_login_link_admin_footer_print_thankyou( $data ) {
        $data = '<p class="zThanks"><a href="https://zodan.nl" target="_blank" rel="noreferrer">' .
                esc_html__('Made with', 'z-onetime-login-link') . 
                '<svg id="heart" data-name="heart" xmlns="http://www.w3.org/2000/svg" width="745.2" height="657.6" version="1.1" viewBox="0 0 745.2 657.6"><path class="heart" d="M372,655.6c-2.8,0-5.5-1.3-7.2-3.6-.7-.9-71.9-95.4-159.9-157.6-11.7-8.3-23.8-16.3-36.5-24.8-60.7-40.5-123.6-82.3-152-151.2C0,278.9-1.4,217.6,12.6,158.6,28,93.5,59,44.6,97.8,24.5,125.3,10.2,158.1,2.4,190.2,2.4s.3,0,.4,0c34.7,0,66.5,9,92.2,25.8,22.4,14.6,70.3,78,89.2,103.7,18.9-25.7,66.8-89,89.2-103.7,25.7-16.8,57.6-25.7,92.2-25.8,32.3-.1,65.2,7.8,92.8,22.1h0c38.7,20.1,69.8,69,85.2,134.1,14,59.1,12.5,120.3-3.8,159.8-28.5,69-91.3,110.8-152,151.2-12.8,8.5-24.8,16.5-36.5,24.8-88.1,62.1-159.2,156.6-159.9,157.6-1.7,2.3-4.4,3.6-7.2,3.6Z"></path></svg>' .
                esc_html__('by Zodan', 'z-onetime-login-link') .
                '</a></p>';

        return $data;
    }

}