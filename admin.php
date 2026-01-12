<?php

/**
 * Settings page for Zodan One-time Login Link
 *
 * Author: Zodan
 * Author URI: https://zodan.nl
 * License: GPL2+
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}


if ( !function_exists( 'zodan_onetime_login_link_register_settings' ) ) {

    add_action( 'admin_init', 'zodan_onetime_login_link_register_settings' );

    function zodan_onetime_login_link_register_settings() {
		
		$settings_args = array(
			'type' => 'array',
			'description' => '',
			'sanitize_callback' => 'zodan_onetime_login_link_plugin_options_validate',
			'show_in_rest' => false
		);
        register_setting( 'zodan_onetime_login_link_plugin_options', 'zodan_onetime_login_link_plugin_options', $settings_args);

		// Add the Roles section
		add_settings_section(
			'zodan_onetime_login_link_roles_section',
			'',
			'zodan_onetime_login_link_roles_section_text',
			'zodan_onetime_login_link_plugin',
            array(
                'before_section' => '<details class="zodan-settings-detail-el" open>',
                'after_section' => '</div></details>',
            )
		);

        // Field: Role selection
		add_settings_field(
			'zodan_onetime_login_link_select_roles',
			esc_html__('Excluded roles', 'zodan-one-time-login-link') . '<span class="description">' .
                esc_html__( 'Some user roles should not have a fast login', 'zodan-one-time-login-link' ) . '</span>', 
			'zodan_onetime_login_link_render_roles_checkboxes',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_roles_section'
		);

		// Add the Mail section
		add_settings_section(
			'zodan_onetime_login_link_mail_section',
			'',
			'zodan_onetime_login_link_mail_section_text',
			'zodan_onetime_login_link_plugin',
            array(
                'before_section' => '<details class="zodan-settings-detail-el">',
                'after_section' => '</div></details>',
            )
		);

        // Field: Mail link text section
		add_settings_field(
			'zodan_onetime_login_link_mail_linktext',
			esc_html__('Login link text', 'zodan-one-time-login-link'), 
			'zodan_onetime_login_link_render_mail_linktext',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_mail_section'
		);

        // Field: Mail subject section
		add_settings_field(
			'zodan_onetime_login_link_mail_subject',
			esc_html__('Mail subject', 'zodan-one-time-login-link'), 
			'zodan_onetime_login_link_render_mail_subject',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_mail_section'
		);

        // Field: Mail template section
		add_settings_field(
			'zodan_onetime_login_link_mail_content',
			esc_html__('Mail content', 'zodan-one-time-login-link'), 
			'zodan_onetime_login_link_render_mail_content',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_mail_section'
		);

		// Add the other options section
		add_settings_section(
			'zodan_onetime_login_link_other_options_section',
			'',
			'zodan_onetime_login_link_other_options_section_text',
			'zodan_onetime_login_link_plugin',
            array(
                'before_section' => '<details class="zodan-settings-detail-el">',
                'after_section' => '</div></details>',
            )
		);

        // Field: Expiration time
		add_settings_field(
			'zodan_onetime_login_link_user_token_expire_time',
			esc_html__('Link expiration time', 'zodan-one-time-login-link'), 
			'zodan_onetime_login_link_render_token_expire_time',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_other_options_section'
		);

        // Field: Allow user request
		add_settings_field(
			'zodan_onetime_login_link_user_request',
			esc_html__('User request', 'zodan-one-time-login-link'), 
			'zodan_onetime_login_link_render_user_request_checkbox',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_other_options_section'
		);

        // Field: Use rate limiting
		add_settings_field(
			'zodan_onetime_login_link_user_request_rate_limit',
			esc_html__('Use rate limiting', 'zodan-one-time-login-link'), 
			'zodan_onetime_login_link_render_rate_limit_checkbox',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_other_options_section'
		);

        // Field: Use rate limiting
		add_settings_field(
			'zodan_onetime_login_link_user_request_rate_limit_value',
			esc_html__('Rate limit', 'zodan-one-time-login-link'), 
			'zodan_onetime_login_link_render_rate_limit_value',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_other_options_section'
		);

        // Field: Use fingerprinting for extra security
		add_settings_field(
			'zodan_onetime_login_link_bulk_use_fingerprinting',
			esc_html__('Use fingerprinting', 'zodan-one-time-login-link'), 
			'zodan_onetime_login_link_render_fingerprinting_checkbox',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_other_options_section'
		);

        // Field: Use log for bulk mail
		add_settings_field(
			'zodan_onetime_login_link_bulk_mail_logging',
			esc_html__('Log bulk mailings', 'zodan-one-time-login-link'), 
			'zodan_onetime_login_link_render_bulk_mail_logging',
			'zodan_onetime_login_link_plugin',
			'zodan_onetime_login_link_other_options_section'
		);
    } 



    function zodan_onetime_login_link_roles_section_text() { 

        echo '<summary><h2>';
        esc_html_e( 'Roles', 'zodan-one-time-login-link' );
        echo '</h2></summary><div class="details-content">';

        echo '<p><strong class="z-warning zotll-warning">' . esc_html__('Please note', 'zodan-one-time-login-link') . '</strong> ';
        echo esc_html__("While we've prioritized security in developing this plugin, we still recommend against using direct login for certain roles.", 'zodan-one-time-login-link');
        echo '<br>';
        echo esc_html__('Be wise and exclude roles like Administrators, Editors, Shop managers.', 'zodan-one-time-login-link');
        echo '</p>';     
    }   



    function zodan_onetime_login_link_render_roles_checkboxes() {
        global $wp_roles;
        $roles = $wp_roles->roles;
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );
        $enabled_roles = isset( $options['roles'] ) ? $options['roles'] : array('administrator');

        foreach ( $roles as $role_slug => $role_details ) {
            printf(
                '<label><input type="checkbox" name="zodan_onetime_login_link_plugin_options[roles][]" value="%s" %s> %s</label><br>',
                esc_attr( $role_slug ),
                checked( in_array( $role_slug, $enabled_roles ), true, false ),
                esc_html( $role_details['name'] )
            );
        }
    }   



    function zodan_onetime_login_link_mail_section_text() {
        echo '<summary><h2>';
        esc_html_e('Mail settings', 'zodan-one-time-login-link');
        echo '</h2></summary><div class="details-content">';
    }   



    function zodan_onetime_login_link_render_mail_linktext() {
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );

        $mail_linktext = isset( $options['mail_linktext'] ) ? $options['mail_linktext'] : __('Click here to login without a password', 'zodan-one-time-login-link');

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="text" id="zmail-linktext" name="zodan_onetime_login_link_plugin_options[mail_linktext]" value="%s"> <span id="zlinktext-example-frame"> %s: <a href="#" id="zlinktext-example"></a></span></label><br>%s.',
            esc_html(__('Text', 'zodan-one-time-login-link')),
            esc_html( $mail_linktext),
            esc_html(__('resulting in something like', 'zodan-one-time-login-link')),
            esc_html(__('If you leave this blank, the link text will display the URL', 'zodan-one-time-login-link')),
        );
    }   



    function zodan_onetime_login_link_render_mail_subject() {
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );

        $mail_subject = isset( $options['mail_subject'] ) ? $options['mail_subject'] : __('Your One-time Login Link', 'zodan-one-time-login-link');

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="text" name="zodan_onetime_login_link_plugin_options[mail_subject]" value="%s"></label>',
            esc_html(__('Subject', 'zodan-one-time-login-link')),
            esc_html( $mail_subject)
        );
    }   



    function zodan_onetime_login_link_render_mail_content() {
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );

		$default_mail_content = '<p>'.__('Hello {{firstname}}', 'zodan-one-time-login-link').'</p>';
        $default_mail_content .= '<p>'.__('With the following link you can directly log in without having to enter your password.', 'zodan-one-time-login-link').'</p>';
        $default_mail_content .= '<p>{{zloginlink}}</p><p>'.__('This link can be used only once.', 'zodan-one-time-login-link').'</p>';

        $mail_content =  ! empty( $options['mail_content'] ) ? $options['mail_content'] : $default_mail_content;

        $id = 'zodan_onetime_login_link_plugin_mail_content';

        $settings = array(
            'media_buttons' => false,
            'textarea_name' => 'zodan_onetime_login_link_plugin_options[mail_content]',
            'textarea_rows' => 10,
            'editor_class' => 'zodan_onetime_login_link_editor'
        );
        echo '<p>';
        // echo esc_html(__("Enter the mail content that will be sent to the user.", 'zodan-one-time-login-link')).'<br>';
        echo esc_html(__("The text must include the {{zloginlink}} code where the actual login link should be included.", 'zodan-one-time-login-link')).'<br>';
        echo esc_html(__("You can also include the {{displayname}} and {{firstname}} codes for the user's display name and first name respectively.", 'zodan-one-time-login-link')).'</p>';
        wp_editor( $mail_content, $id, $settings );

    }   



    function zodan_onetime_login_link_other_options_section_text() {
        echo '<summary><h2>';
        esc_html_e('Other options', 'zodan-one-time-login-link');
        echo '</h2></summary><div class="details-content">';
    }   



    function zodan_onetime_login_link_render_user_request_checkbox() {
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );
        $allow_user_request = isset( $options['allow_user_request'] ) ? $options['allow_user_request'] : false;
        printf(
            '<label><input type="checkbox" name="zodan_onetime_login_link_plugin_options[allow_user_request]" value="1" %s> %s</label><br>',
            checked( $allow_user_request, true, false ),
            esc_html( __('Allow users to request a new One-time Login Link on the login form, similar to the reset password method.', 'zodan-one-time-login-link') )
        );
    }   



    function zodan_onetime_login_link_render_token_expire_time() {
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );

        $expire_time = isset( $options['expire_time'] ) ? $options['expire_time'] : intval(HOUR_IN_SECONDS);

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="number" name="zodan_onetime_login_link_plugin_options[expire_time]" value="%s" min="300"> %s</label>',
            esc_html(__('Subject', 'zodan-one-time-login-link')),
            esc_html( $expire_time),
            esc_html(__('seconds', 'zodan-one-time-login-link')),
        );
    }   



    function zodan_onetime_login_link_render_rate_limit_checkbox() {
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );
        $use_rate_limit = isset( $options['use_rate_limit'] ) ? $options['use_rate_limit'] : false;
        printf(
            '<label><input type="checkbox" id="z_use_rate_limit" name="zodan_onetime_login_link_plugin_options[use_rate_limit]" value="1" %s> %s</label><br>',
            checked( $use_rate_limit, true, false ),
            esc_html( __('Use rate limiting to the user requests (e.g. max. once every 10 minutes)', 'zodan-one-time-login-link') )
        );
    }   



    function zodan_onetime_login_link_render_rate_limit_value() {
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );

        $rate_limit_value = isset( $options['rate_limit_value'] ) ? $options['rate_limit_value'] : intval(MINUTE_IN_SECONDS * 10);

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="number" id="z_rate_limit_value" name="zodan_onetime_login_link_plugin_options[rate_limit_value]" value="%s" min="60"> %s</label>',
            esc_html(__('Subject', 'zodan-one-time-login-link')),
            esc_html( $rate_limit_value),
            esc_html(__('seconds between requests', 'zodan-one-time-login-link')),
        );
    }   



    function zodan_onetime_login_link_render_bulk_mail_logging() {
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );

        $use_bulk_mail_log = isset( $options['use_bulk_mail_log'] ) ? $options['use_bulk_mail_log'] : 0;

        printf(
            '<label><input type="checkbox" id="z_use_bulk_mail_log" name="zodan_onetime_login_link_plugin_options[use_bulk_mail_log]" value="1" %s> %s</label><br>',
            checked( $use_bulk_mail_log, true, false ),
            esc_html( __('Log bulk mail activities (number of users processed, scheduled cron jobs etc.)', 'zodan-one-time-login-link') )
        );

    }   



    function zodan_onetime_login_link_render_fingerprinting_checkbox() {
        $options = get_option( 'zodan_onetime_login_link_plugin_options' );

        $use_fingerprinting = isset( $options['use_fingerprinting'] ) ? $options['use_fingerprinting'] : 0;

        printf(
            '<label><input type="checkbox" id="z_use_fingerprinting" name="zodan_onetime_login_link_plugin_options[use_fingerprinting]" value="1" %s> %s</label><br>',
            checked( $use_fingerprinting, true, false ),
            esc_html( __('Use browser fingerprinting for extra security.<br>This requires the user to use the same browser (on the same device, from the same IP address) for both requesting the Login Link and using it.', 'zodan-one-time-login-link') )
        );

    }



    function zodan_onetime_login_link_plugin_options_validate( $input ) {
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

        if ( isset( $input['use_bulk_mail_log'] ) ) {
            $output['use_bulk_mail_log'] = true;
        }

        if ( isset( $input['allow_user_request'] ) ) {
            $output['allow_user_request'] = true;
        }

        if ( isset( $input['use_rate_limit'] ) ) {
            $output['use_rate_limit'] = true;
        }

        if ( isset( $input['use_fingerprinting'] ) ) {
            $output['use_fingerprinting'] = true;
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



    function zodan_onetime_login_link_add_admin_menu() {
        add_options_page(
            __('Login once', 'zodan-one-time-login-link'),
            'Login Once',
            'manage_options',
            'zodan_onetime_login_link',
            'zodan_onetime_login_link_options_page'
        );
    }
    add_action( 'admin_menu', 'zodan_onetime_login_link_add_admin_menu' );   



    function zodan_onetime_login_link_options_page() {
        add_filter('admin_footer_text', 'zodan_onetime_login_link_admin_footer_print_thankyou', 900);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Zodan One-time Login Link settings', 'zodan-one-time-login-link'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'zodan_onetime_login_link_plugin_options' );

                echo '<h2>' . esc_html__('Introduction', 'zodan-one-time-login-link') . '</h2>';
                echo '<p>' . esc_html__('With the One-time Login Link you can give users the option to log into Wordpress without a password.', 'zodan-one-time-login-link') . '</p>';
                echo '<p>' . esc_html__('Current version', 'zodan-one-time-login-link') . ': '.esc_html( ZODAN_ONETIME_LOGIN_LINK_VERSION ).'</p>';
                
                $users_url = admin_url( 'users.php');
                echo '<p><span class="dashicons dashicons-email-alt"></span> '.esc_html__( 'You can send a One-time Login Link to ALL active users from the', 'zodan-one-time-login-link' ). ' ';
                printf('<a href="%s">%s</a>.',
                    esc_url( $users_url ),
                    esc_html__( 'users admin page', 'zodan-one-time-login-link' )
                );
                echo '</p>';
                echo '<p>&nbsp;</p>';

                echo '<div class="zodan-settings-details-wrapper">';
                do_settings_sections( 'zodan_onetime_login_link_plugin' );
                echo '</div>';
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

 



    function zodan_onetime_login_link_admin_footer_print_thankyou( $data ) {
        $data = '<p class="zThanks"><a href="https://zodan.nl" target="_blank" rel="noreferrer">' .
                esc_html__('Made with', 'zodan-one-time-login-link') . 
                '<svg id="heart" data-name="heart" xmlns="http://www.w3.org/2000/svg" width="745.2" height="657.6" version="1.1" viewBox="0 0 745.2 657.6"><path class="heart" d="M372,655.6c-2.8,0-5.5-1.3-7.2-3.6-.7-.9-71.9-95.4-159.9-157.6-11.7-8.3-23.8-16.3-36.5-24.8-60.7-40.5-123.6-82.3-152-151.2C0,278.9-1.4,217.6,12.6,158.6,28,93.5,59,44.6,97.8,24.5,125.3,10.2,158.1,2.4,190.2,2.4s.3,0,.4,0c34.7,0,66.5,9,92.2,25.8,22.4,14.6,70.3,78,89.2,103.7,18.9-25.7,66.8-89,89.2-103.7,25.7-16.8,57.6-25.7,92.2-25.8,32.3-.1,65.2,7.8,92.8,22.1h0c38.7,20.1,69.8,69,85.2,134.1,14,59.1,12.5,120.3-3.8,159.8-28.5,69-91.3,110.8-152,151.2-12.8,8.5-24.8,16.5-36.5,24.8-88.1,62.1-159.2,156.6-159.9,157.6-1.7,2.3-4.4,3.6-7.2,3.6Z"></path></svg>' .
                esc_html__('by Zodan', 'zodan-one-time-login-link') .
                '</a></p>';

        return $data;
    }

}