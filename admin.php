<?php

/**
 * Settings page for Z User Onetime Login
 *
 * Author: Zodan
 * Author URI: https://zodan.nl
 * License: GPL2+
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}


if ( ! defined( 'Z_USER_ONETIME_LOGIN_VERSION' ) ) {
	define( 'Z_USER_ONETIME_LOGIN_VERSION', gmdate('Ymd') );
}



if ( !function_exists( 'z_user_onetime_login_register_settings' ) ) {

    function z_user_onetime_login_register_settings() {
		
		$settings_args = array(
			'type' => 'array',
			'description' => '',
			'sanitize_callback' => 'z_user_onetime_login_plugin_options_validate',
			'show_in_rest' => false
		);
        register_setting( 'z_user_onetime_login_plugin_options', 'z_user_onetime_login_plugin_options', $settings_args);

		// Voeg settings section toe
		add_settings_section(
			'z_user_onetime_login_main_section',
			 esc_html__('Global settings', 'z-user-onetime-login'),
			'z_user_onetime_login_main_section_text',
			'z_user_onetime_login_plugin'
		);


        // Field: Role selection
		add_settings_field(
			'z_user_onetime_login_select_roles',
			 esc_html__('Excluded roles', 'z-user-onetime-login') . '<span class="description">' .
                esc_html__( 'Some user roles should not have a fast login', 'z-user-onetime-login' ) . '</span>', 
			'z_user_onetime_login_render_roles_checkboxes',
			'z_user_onetime_login_plugin',
			'z_user_onetime_login_main_section'
		);

        // Field: Mail link text section
		add_settings_field(
			'z_user_onetime_login_mail_linktext',
			esc_html__('Login link text', 'z-user-onetime-login'), 
			'z_user_onetime_login_render_mail_linktext',
			'z_user_onetime_login_plugin',
			'z_user_onetime_login_main_section'
		);

        // Field: Mail subject section
		add_settings_field(
			'z_user_onetime_login_mail_subject',
			esc_html__('Mail subject', 'z-user-onetime-login'), 
			'z_user_onetime_login_render_mail_subject',
			'z_user_onetime_login_plugin',
			'z_user_onetime_login_main_section'
		);

        // Field: Mail template section
		add_settings_field(
			'z_user_onetime_login_mail_content',
			esc_html__('Mail content', 'z-user-onetime-login'), 
			'z_user_onetime_login_render_mail_content',
			'z_user_onetime_login_plugin',
			'z_user_onetime_login_main_section'
		);
    }

    add_action( 'admin_init', 'z_user_onetime_login_register_settings' );



    function z_user_onetime_login_main_section_text() { 
        echo '<p>' . esc_html__('Here you can set all the options for using the WordPress User One Time Login.', 'z-user-onetime-login') . '</p>';
        echo '<ol>';
        echo '<li>' . esc_html__('Select the user roles that CAN NOT have a fast login - e.g. Administrators', 'z-user-onetime-login') . '</li>';
        echo '<li>' . esc_html__('Customize the link text', 'z-user-onetime-login') . '</li>';
        echo '<li>' . esc_html__('Set the message users will see in the e-mail', 'z-user-onetime-login') . '</li>';
        echo '</ol>';
        echo '<p>&nbsp;</p>';
    }



    function z_user_onetime_login_render_roles_checkboxes() {
        global $wp_roles;
        $roles = $wp_roles->roles;
        $options = get_option( 'z_user_onetime_login_plugin_options' );
        $enabled_roles = isset( $options['roles'] ) ? $options['roles'] : array();

        foreach ( $roles as $role_slug => $role_details ) {
            printf(
                '<label><input type="checkbox" name="z_user_onetime_login_plugin_options[roles][]" value="%s" %s> %s</label><br>',
                esc_attr( $role_slug ),
                checked( in_array( $role_slug, $enabled_roles ), true, false ),
                esc_html( $role_details['name'] )
            );
        }
    }



    function z_user_onetime_login_render_mail_linktext() {
        $options = get_option( 'z_user_onetime_login_plugin_options' );

        $mail_linktext = isset( $options['mail_linktext'] ) ? $options['mail_linktext'] : __('Click here to login without a password', 'z-user-onetime-login');

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="text" id="zmail-linktext" name="z_user_onetime_login_plugin_options[mail_linktext]" value="%s"> <span id="zlinktext-example-frame"> %s: <a href="#" id="zlinktext-example"></a></span></label><br>%s.',
            esc_html(__('Text', 'z-user-onetime-login')),
            esc_html( $mail_linktext),
            esc_html(__('resulting in something like', 'z-user-onetime-login')),
            esc_html(__('If you leave this blank, the link text will display the URL', 'z-user-onetime-login')),
        );
    }



    function z_user_onetime_login_render_mail_subject() {
        $options = get_option( 'z_user_onetime_login_plugin_options' );

        $mail_subject = isset( $options['mail_subject'] ) ? $options['mail_subject'] : __('Your One Time Login link', 'z-user-onetime-login');

        printf('<label><strong class="screen-reader-text">%s:</strong> <input type="text" name="z_user_onetime_login_plugin_options[mail_subject]" value="%s"></label>',
            esc_html(__('Subject', 'z-user-onetime-login')),
            esc_html( $mail_subject)
        );
    }



    function z_user_onetime_login_render_mail_content() {
        $options = get_option( 'z_user_onetime_login_plugin_options' );

		$default_mail_content = '<p>'.__('Hello {{firstname}}', 'z-user-onetime-login').'</p>';
        $default_mail_content .= '<p>'.__('With the following link you can directly log in without having to enter your password.', 'z-user-onetime-login').'</p>';
        $default_mail_content .= '<p>{{zloginlink}}</p><p>'.__('This link can be used one time only.', 'z-user-onetime-login').'</p>';


        $mail_content =  ! empty( $options['mail_content'] ) ? $options['mail_content'] : $default_mail_content;

        $id = 'z_user_onetime_login_plugin_mail_content';

        $settings = array(
            'media_buttons' => false,
            'textarea_name' => 'z_user_onetime_login_plugin_options[mail_content]',
            'textarea_rows' => 10,
            'editor_class' => 'z_user_onetime_login_editor'
        );
        echo '<p>';
        // echo esc_html(__("Enter the mail content that will be sent to the user.", 'z-user-onetime-login')).'<br>';
        echo esc_html(__("The text must include the {{zloginlink}} code where the actual login link should be included.", 'z-user-onetime-login')).'<br>';
        echo esc_html(__("You can also include the {{displayname}} and {{firstname}} codes for the user's display name and first name respectively.", 'z-user-onetime-login')).'</p>';
        wp_editor( $mail_content, $id, $settings );

    }



    function z_user_onetime_login_plugin_options_validate( $input ) {
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

        if ( isset( $input['roles'] ) && is_array( $input['roles'] ) ) {
            global $wp_roles;
            $all_roles = array_keys( $wp_roles->roles );
            $valid_roles = array_intersect( $input['roles'], $all_roles );
            $output['roles'] = array_map( 'sanitize_text_field', $valid_roles );
        }

        return $output;
    }



    function z_user_onetime_login_add_admin_menu() {
        add_options_page(
            __('Login once', 'z-user-onetime-login'),
            'Login Once',
            'manage_options',
            'z_user_onetime_login',
            'z_user_onetime_login_options_page'
        );
    }
    add_action( 'admin_menu', 'z_user_onetime_login_add_admin_menu' );



    function z_user_onetime_login_options_page() {
        add_filter('admin_footer_text', 'z_user_onetime_login_admin_footer_print_thankyou', 900);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Z User One Time Login settings', 'z-user-onetime-login'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'z_user_onetime_login_plugin_options' );
                do_settings_sections( 'z_user_onetime_login_plugin' );
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
    add_action( 'admin_enqueue_scripts', 'z_user_onetime_login_add_admin_scripts' );
    function z_user_onetime_login_add_admin_scripts( $hook ) {

        if ( is_admin() ) {

            $plugin_url = plugins_url( '/', __FILE__ );
            $admin_css = $plugin_url . 'assets/admin-styles.css';
            wp_enqueue_style( 'z-user-onetime-login-admin-styles', esc_url($admin_css), array(), Z_USER_ONETIME_LOGIN_VERSION );

            $admin_js = $plugin_url . 'assets/admin-scripts.js';
            wp_register_script( 'z-user-onetime-login-admin-scripts', esc_url( $admin_js ) , array( 'jquery' ), Z_USER_ONETIME_LOGIN_VERSION, array( 'in_footer' => true ) );
            wp_localize_script('z-user-onetime-login-admin-scripts', 'z_user_onetime_login_admin', array(
                    'copiedText' => esc_html__('PHP code copied!', 'z-user-onetime-login'),
                )
            );
            wp_enqueue_script( 'z-user-onetime-login-admin-scripts' );
        }
    }



    function z_user_onetime_login_admin_footer_print_thankyou( $data ) {
        $data = '<p class="zThanks"><a href="https://zodan.nl" target="_blank" rel="noreferrer">' .
                esc_html__('Made with', 'z-user-onetime-login') . 
                '<svg id="heart" data-name="heart" xmlns="http://www.w3.org/2000/svg" width="745.2" height="657.6" version="1.1" viewBox="0 0 745.2 657.6"><path class="heart" d="M372,655.6c-2.8,0-5.5-1.3-7.2-3.6-.7-.9-71.9-95.4-159.9-157.6-11.7-8.3-23.8-16.3-36.5-24.8-60.7-40.5-123.6-82.3-152-151.2C0,278.9-1.4,217.6,12.6,158.6,28,93.5,59,44.6,97.8,24.5,125.3,10.2,158.1,2.4,190.2,2.4s.3,0,.4,0c34.7,0,66.5,9,92.2,25.8,22.4,14.6,70.3,78,89.2,103.7,18.9-25.7,66.8-89,89.2-103.7,25.7-16.8,57.6-25.7,92.2-25.8,32.3-.1,65.2,7.8,92.8,22.1h0c38.7,20.1,69.8,69,85.2,134.1,14,59.1,12.5,120.3-3.8,159.8-28.5,69-91.3,110.8-152,151.2-12.8,8.5-24.8,16.5-36.5,24.8-88.1,62.1-159.2,156.6-159.9,157.6-1.7,2.3-4.4,3.6-7.2,3.6Z"></path></svg>' .
                esc_html__('by Zodan', 'z-user-onetime-login') .
                '</a></p>';

        return $data;
    }

}