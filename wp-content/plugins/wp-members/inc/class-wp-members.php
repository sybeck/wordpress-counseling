<?php
/**
 * The WP_Members Class.
 *
 * This is the main WP_Members object class. This class contains functions
 * for loading settings, shortcodes, hooks to WP, plugin dropins, constants,
 * and registration fields. It also manages whether content should be blocked.
 *
 * @package WP-Members
 * @subpackage WP_Members Object Class
 * @since 3.0.0
 */

class WP_Members {
	
	public $version;
	public $block;
	public $show_excerpt;
	public $show_login;
	public $show_reg;
	public $autoex;
	public $notify;
	public $mod_reg;
	public $captcha;
	public $use_exp;
	public $use_trial;
	public $warnings;
	public $user_pages;
	public $post_types;

	/**
	 * Plugin initialization function.
	 *
	 * @since 3.0.0
	 */
	function __construct() {
	
		/**
		 * Filter the options before they are loaded into constants.
		 *
		 * @since 2.9.0
		 * @since 3.0.0 Moved to the WP_Members class.
		 *
		 * @param array $this->settings An array of the WP-Members settings.
		 */
		$settings = apply_filters( 'wpmem_settings', get_option( 'wpmembers_settings' ) );

		// Validate that v3 settings are loaded.
		if ( ! isset( $settings['version'] ) || $settings['version'] != WPMEM_VERSION ) {
			/**
			 * Load installation routine.
			 */
			require_once( WPMEM_PATH . 'wp-members-install.php' );
			// Update settings.
			$settings = apply_filters( 'wpmem_settings', wpmem_upgrade_settings() );
		}
		
		// Assemble settings.
		foreach ( $settings as $key => $val ) {
			$this->$key = $val;
		}
		
		$this->load_user_pages();

		// Set the stylesheet.
		$this->cssurl = ( isset( $this->style ) && $this->style == 'use_custom' ) ? $this->cssurl : $this->style;
		
		// Load forms.
		require_once( WPMEM_PATH . 'inc/class-wp-members-forms.php' );
		$this->forms = new WP_Members_Forms;
		
		// Load api.
		require_once( WPMEM_PATH . 'inc/class-wp-members-api.php' );
		$this->api = new WP_Members_API;
		
	}

	/**
	 * Plugin initialization function to load shortcodes.
	 *
	 * @since 3.0.0
	 */
	function load_shortcodes() {

		/**
		 * Load the shortcode functions.
		 */
		require_once( WPMEM_PATH . 'inc/shortcodes.php' );
		
		add_shortcode( 'wp-members',       'wpmem_shortcode'       );
		add_shortcode( 'wpmem_field',      'wpmem_shortcode'       );
		add_shortcode( 'wpmem_logged_in',  'wpmem_sc_logged_in'    );
		add_shortcode( 'wpmem_logged_out', 'wpmem_sc_logged_out'   );
		add_shortcode( 'wpmem_logout',     'wpmem_shortcode'       );
		add_shortcode( 'wpmem_form',       'wpmem_sc_forms'        );
		add_shortcode( 'wpmem_show_count', 'wpmem_sc_user_count'   );
		add_shortcode( 'wpmem_profile',    'wpmem_sc_user_profile' );
		add_shortcode( 'wpmem_loginout',   'wpmem_sc_loginout'     );
		
		/**
		 * Fires after shortcodes load (for adding additional custom shortcodes).
		 *
		 * @since 3.0.0
		 */
		do_action( 'wpmem_load_shortcodes' );
	}
	
	/**
	 * Plugin initialization function to load hooks.
	 *
	 * @since 3.0.0
	 */
	function load_hooks() {

		// Add actions.
		add_action( 'template_redirect',     array( $this, 'get_action' ) );
		add_action( 'widgets_init',          'widget_wpmemwidget_init' );  // initializes the widget
		add_action( 'admin_init',            'wpmem_chk_admin' );          // check user role to load correct dashboard
		add_action( 'admin_menu',            'wpmem_admin_options' );      // adds admin menu
		add_action( 'user_register',         'wpmem_wp_reg_finalize' );    // handles wp native registration
		add_action( 'login_enqueue_scripts', 'wpmem_wplogin_stylesheet' ); // styles the native registration
		add_action( 'wp_print_styles',       'wpmem_enqueue_style' );      // load the stylesheet if using the new forms

		// Add filters.
		add_filter( 'the_content',           array( $this, 'do_securify' ), 99 );
		add_filter( 'allow_password_reset',  'wpmem_no_reset' );                 // no password reset for non-activated users
		add_filter( 'register_form',         'wpmem_wp_register_form' );         // adds fields to the default wp registration
		add_filter( 'registration_errors',   'wpmem_wp_reg_validate', 10, 3 );   // native registration validation
		add_filter( 'comments_open',         'wpmem_securify_comments', 99 );    // securifies the comments
		
		// If registration is moderated, check for activation (blocks backend login by non-activated users).
		if ( $this->mod_reg == 1 ) { 
			add_filter( 'authenticate', 'wpmem_check_activated', 99, 3 ); 
		}

		/**
		 * Fires after action and filter hooks load (for adding/removing hooks).
		 *
		 * @since 3.0.0
		 */
		do_action( 'wpmem_load_hooks' );
	}
	
	/**
	 * Load drop-ins.
	 *
	 * @since 3.0.0
	 *
	 * @todo This is experimental. The function and its operation is subject to change.
	 */
	function load_dropins() {

		/**
		 * Filters the dropin file folder.
		 *
		 * @since 3.0.0
		 *
		 * @param string $folder The dropin file folder.
		 */
		$folder = apply_filters( 'wpmem_dropin_folder', WP_PLUGIN_DIR . '/wp-members-dropins/' );
		
		// Load any drop-ins.
		foreach ( glob( $folder . '*.php' ) as $filename ) {
			include_once( $filename );
		}

		/**
		 * Fires after dropins load (for adding additional dropings).
		 *
		 * @since 3.0.0
		 */
		do_action( 'wpmem_load_dropins' );
	}
	
	/**
	 * Loads pre-3.0 constants (included primarily for add-on compatibility).
	 *
	 * @since 3.0.0
	 */
	function load_constants() {
		( ! defined( 'WPMEM_BLOCK_POSTS'  ) ) ? define( 'WPMEM_BLOCK_POSTS',  $this->block['post']  ) : '';
		( ! defined( 'WPMEM_BLOCK_PAGES'  ) ) ? define( 'WPMEM_BLOCK_PAGES',  $this->block['page']  ) : '';
		( ! defined( 'WPMEM_SHOW_EXCERPT' ) ) ? define( 'WPMEM_SHOW_EXCERPT', $this->show_excerpt['post'] ) : '';
		( ! defined( 'WPMEM_NOTIFY_ADMIN' ) ) ? define( 'WPMEM_NOTIFY_ADMIN', $this->notify    ) : '';
		( ! defined( 'WPMEM_MOD_REG'      ) ) ? define( 'WPMEM_MOD_REG',      $this->mod_reg   ) : '';
		( ! defined( 'WPMEM_CAPTCHA'      ) ) ? define( 'WPMEM_CAPTCHA',      $this->captcha   ) : '';
		( ! defined( 'WPMEM_NO_REG'       ) ) ? define( 'WPMEM_NO_REG',       ( -1 * $this->show_reg['post'] ) ) : '';
		( ! defined( 'WPMEM_USE_EXP'      ) ) ? define( 'WPMEM_USE_EXP',      $this->use_exp   ) : '';
		( ! defined( 'WPMEM_USE_TRL'      ) ) ? define( 'WPMEM_USE_TRL',      $this->use_trial ) : '';
		( ! defined( 'WPMEM_IGNORE_WARN'  ) ) ? define( 'WPMEM_IGNORE_WARN',  $this->warnings  ) : '';

		( ! defined( 'WPMEM_MSURL'  ) ) ? define( 'WPMEM_MSURL',  $this->user_pages['profile']  ) : '';
		( ! defined( 'WPMEM_REGURL' ) ) ? define( 'WPMEM_REGURL', $this->user_pages['register'] ) : '';
		( ! defined( 'WPMEM_LOGURL' ) ) ? define( 'WPMEM_LOGURL', $this->user_pages['login']    ) : '';
		
		define( 'WPMEM_CSSURL', $this->cssurl );
	}
	
	/**
	 * Gets the requested action.
	 *
	 * @since 3.0.0
	 *
	 * @global string $wpmem_a The WP-Members action variable.
	 */
	function get_action() {

		// Get the action being done (if any).
		$this->action = ( isset( $_REQUEST['a'] ) ) ? trim( $_REQUEST['a'] ) : '';

		// For backward compatibility with processes that check $wpmem_a.
		global $wpmem_a;
		$wpmem_a = $this->action;

		// Get the regchk value (if any).
		$this->regchk = $this->get_regchk( $this->action );
	}
	
	/**
	 * Gets the regchk value.
	 *
	 * @since 3.0.0
	 *
	 * @global string $wpmem_a The WP-Members action variable.
	 *
	 * @param  string $action The action being done.
	 * @return string         The regchk value.
	 *
	 * @todo Describe regchk.
	 */
	function get_regchk( $action ) {

		switch ( $action ) {

			case 'login':
				$regchk = wpmem_login();
				break;

			case 'logout':
				$regchk = wpmem_logout();
				break;
			
			case 'pwdchange':
				$regchk = wpmem_change_password();
 				break;
			
			case 'pwdreset':
				$regchk = wpmem_reset_password();
				break;
			
			case 'getusername':
				$regchk = wpmem_retrieve_username();
				break;
			
			case 'register':
			case 'update':
				require_once( WPMEM_PATH . 'inc/register.php' );
				$regchk = wpmem_registration( $action  );
				break;

			default:
				$regchk = ( isset( $regchk ) ) ? $regchk : '';
				break;
		}
		
		/**
		 * Filter wpmem_regchk.
		 *
		 * The value of regchk is determined by functions that may be run in the get_regchk function.
		 * This value determines what happens in the wpmem_securify() function.
		 *
		 * @since 2.9.0
		 *
		 * @param  string $this->regchk The value of wpmem_regchk.
		 * @param  string $this->action The $wpmem_a action.
		 */
		$regchk = apply_filters( 'wpmem_regchk', $regchk, $action );
		
		// @todo Remove legacy global variable.
		global $wpmem_regchk;
		$wpmem_regchk = $regchk;
		
		return $regchk;
	}
	
	/**
	 * Determines if content should be blocked.
	 *
	 * This function was originally stand alone in the core file and
	 * was moved to the WP_Members class in 3.0.
	 *
	 * @since 3.0.0
	 *
	 * @global object $post  The WordPress Post object.
	 * @return bool   $block true|false
	 */
	function is_blocked() {
	
		global $post;
		
		if ( $post ) {

			// Backward compatibility for old block/unblock meta.
			$meta = get_post_meta( $post->ID, '_wpmem_block', true );
			if ( ! $meta ) {
				// Check for old meta.
				$old_block   = get_post_meta( $post->ID, 'block',   true );
				$old_unblock = get_post_meta( $post->ID, 'unblock', true );
				$meta = ( $old_block ) ? 1 : ( ( $old_unblock ) ? 0 : $meta );
			}
	
			// Setup defaults.
			$defaults = array(
				'post_id'    => $post->ID,
				'post_type'  => $post->post_type,
				'block'      => ( isset( $this->block[ $post->post_type ] ) && $this->block[ $post->post_type ] == 1 ) ? true : false,
				'block_meta' => $meta, // @todo get_post_meta( $post->ID, '_wpmem_block', true ),
				'block_type' => ( isset( $this->block[ $post->post_type ] ) ) ? $this->block[ $post->post_type ] : 0,
			);
	
			/**
			 * Filter the block arguments.
			 *
			 * @since 2.9.8
			 *
			 * @param array $args     Null.
			 * @param array $defaults Although you are not filtering the defaults, knowing what they are can assist developing more powerful functions.
			 */
			$args = apply_filters( 'wpmem_block_args', '', $defaults );
	
			// Merge $args with defaults.
			$args = ( wp_parse_args( $args, $defaults ) );
	
			if ( is_single() || is_page() ) {
				switch( $args['block_type'] ) {
					case 1: // If content is blocked by default.
						$args['block'] = ( $args['block_meta'] == '0' ) ? false : $args['block'];
						break;
					case 0 : // If content is unblocked by default.
						$args['block'] = ( $args['block_meta'] == '1' ) ? true : $args['block'];
						break;
				}

			} else {
				$args['block'] = false;
			}

		} else {
			$args = array( 'block' => false );
		}
	
		// Don't block user pages.
		$args['block'] = ( in_array( get_permalink(), $this->user_pages ) ) ? false : $args['block'];

		/**
		 * Filter the block boolean.
		 *
		 * @since 2.7.5
		 *
		 * @param bool  $args['block']
		 * @param array $args
		 */
		return apply_filters( 'wpmem_block', $args['block'], $args );
	}
	
	/**
	 * The Securify Content Filter.
	 *
	 * This is the primary function that picks up where get_action() leaves off.
	 * Determines whether content is shown or hidden for both post and pages. This
	 * is a filter function for the_content.
	 *
	 * @link https://developer.wordpress.org/reference/functions/the_content/
	 * @link https://developer.wordpress.org/reference/hooks/the_content/
	 *
	 * @since 3.0.0
	 *
	 * @global string $wpmem_themsg Contains messages to be output.
	 * @global object $post         The WordPress Post object.
	 * @param  string $content
	 * @return string $content
	 */
	function do_securify( $content = null ) {

		global $wpmem_themsg, $post;

		$content = ( is_single() || is_page() ) ? $content : wpmem_do_excerpt( $content );

		if ( ( ! wpmem_test_shortcode( $content, 'wp-members' ) ) ) {

			if ( $this->regchk == "captcha" ) {
				global $wpmem_captcha_err;
				$wpmem_themsg = __( 'There was an error with the CAPTCHA form.' ) . '<br /><br />' . $wpmem_captcha_err;
			}

			// Block/unblock Posts.
			if ( ! is_user_logged_in() && $this->is_blocked() == true ) {

				include_once( WPMEM_PATH . 'inc/dialogs.php' );
				
				//Show the login and registration forms.
				if ( $this->regchk ) {
					
					// Empty content in any of these scenarios.
					$content = '';

					switch ( $this->regchk ) {

					case "loginfailed":
						$content = wpmem_inc_loginfailed();
						break;

					case "success":
						$content = wpmem_inc_regmessage( $this->regchk, $wpmem_themsg );
						$content = $content . wpmem_inc_login();
						break;

					default:
						$content = wpmem_inc_regmessage( $this->regchk, $wpmem_themsg );
						$content = $content . wpmem_inc_registration();
						break;
					}

				} else {

					// Toggle shows excerpt above login/reg on posts/pages.
					global $wp_query;
					if ( isset( $wp_query->query_vars['page'] ) && $wp_query->query_vars['page'] > 1 ) {

							// Shuts down excerpts on multipage posts if not on first page.
							$content = '';

					} elseif ( isset( $this->show_excerpt[ $post->post_type ] ) && $this->show_excerpt[ $post->post_type ] == 1 ) {

						if ( ! stristr( $content, '<span id="more' ) ) {
							$content = wpmem_do_excerpt( $content );
						} else {
							$len = strpos( $content, '<span id="more' );
							$content = substr( $content, 0, $len );
						}

					} else {

						// Empty all content.
						$content = '';

					}

					$content = ( isset( $this->show_login[ $post->post_type ] ) && $this->show_login[ $post->post_type ] == 1 ) ? $content . wpmem_inc_login() : $content . wpmem_inc_login( 'page', '', 'hide' );

					$content = ( isset( $this->show_reg[ $post->post_type ] ) && $this->show_reg[ $post->post_type ] == 1 ) ? $content . wpmem_inc_registration() : $content;
				}

			// Protects comments if expiration module is used and user is expired.
			} elseif ( is_user_logged_in() && $this->is_blocked() == true ){

				$content = ( $this->use_exp == 1 && function_exists( 'wpmem_do_expmessage' ) ) ? wpmem_do_expmessage( $content ) : $content;

			}
		}

		/**
		 * Filter the value of $content after wpmem_securify has run.
		 *
		 * @since 2.7.7
		 *
		 * @param string $content The content after securify has run.
		 */
		$content = apply_filters( 'wpmem_securify', $content );

		if ( strstr( $content, '[wpmem_txt]' ) ) {
			// Fix the wptexturize.
			remove_filter( 'the_content', 'wpautop' );
			remove_filter( 'the_content', 'wptexturize' );
			add_filter( 'the_content', 'wpmem_texturize', 999 );
		}

		return $content;
		
	}

	/**
	 * Sets the registration fields.
	 *
	 * @since 3.0.0
	 */
	function load_fields() {
		$this->fields = get_option( 'wpmembers_fields' );
		
		// Add new field array keys
		// @todo multi-form project for 3.1.2
		/*for( $row = 0; $row < count( $this->fields ); $row++ ) {
			$this->fields[ $row ]['id']            = $this->fields[ $row ][0];
			$this->fields[ $row ]['label']         = $this->fields[ $row ][1];
			$this->fields[ $row ]['meta_key']      = $this->fields[ $row ][2];
			$this->fields[ $row ]['type']          = $this->fields[ $row ][3];
			$this->fields[ $row ]['display']       = ( 'y' == $this->fields[ $row ][4] ) ? true : false;
			$this->fields[ $row ]['required']      = ( 'y' == $this->fields[ $row ][5] ) ? true : false;
			$this->fields[ $row ]['profile_only']  = '';
			$this->fields[ $row ]['native']        = ( 'y' == $this->fields[ $row ][6] ) ? true : false;
		}*/
	}
	
	/**
	 * Get excluded meta fields.
	 *
	 * @since Unknown
	 *
	 * @param  string $tag A tag so we know where the function is being used.
	 * @return array       The excluded fields.
	 */
	function excluded_fields( $tag ) {

		// Default excluded fields.
		$excluded_fields = array( 'password', 'confirm_password', 'confirm_email', 'password_confirm', 'email_confirm' );

		/**
		 * Filter the fields to be excluded when user is created/updated.
		 *
		 * @since 2.9.3
		 * @since Unknown Moved to new method in WP_Members Class.
		 *
		 * @param array       An array of the field meta names to exclude.
		 * @param string $tag A tag so we know where the function is being used.
		 */
		$excluded_fields = apply_filters( 'wpmem_exclude_fields', $excluded_fields, $tag );

		// Return excluded fields.
		return $excluded_fields;
	}
	
	/**
	 * Set page locations.
	 *
	 * Handles numeric page IDs while maintaining
	 * compatibility with old full url settings.
	 *
	 * @since 3.0.8
	 */
	function load_user_pages() {
		foreach ( $this->user_pages as $key => $val ) {
			if ( is_numeric( $val ) ) {
				$this->user_pages[ $key ] = get_page_link( $val );
			}
		}
	}
	

	/**
	 * Returns a requested text string.
	 *
	 * This function manages all of the front-end facing text.
	 * All defaults can be filtered using wpmem_default_text_strings.
	 *
	 * @since 3.1.0
	 *
	 * @param  string $str
	 * @return string $text
	 */	
	function get_text( $str ) {

		// Default Form Fields.
		$default_form_fields = array(
			'first_name'       => __( 'First Name', 'wp-members' ),
			'last_name'        => __( 'Last Name', 'wp-members' ),
			'addr1'            => __( 'Address 1', 'wp-members' ),
			'addr2'            => __( 'Address 2', 'wp-members' ),
			'city'             => __( 'City', 'wp-members' ),
			'thestate'         => __( 'State', 'wp-members' ),
			'zip'              => __( 'Zip', 'wp-members' ),
			'country'          => __( 'Country', 'wp-members' ),
			'phone1'           => __( 'Day Phone', 'wp-members' ),
			'user_email'       => __( 'Email', 'wp-members' ),
			'confirm_email'    => __( 'Confirm Email', 'wp-members' ),
			'user_url'         => __( 'Website', 'wp-members' ),
			'description'      => __( 'Biographical Info', 'wp-members' ),
			'password'         => __( 'Password', 'wp-members' ),
			'confirm_password' => __( 'Confirm Password', 'wp-members' ),
			'tos'              => __( 'TOS', 'wp-members' ),
		);
	
		$defaults = array(
			
			// Login form.
			'login_heading'        => __( 'Existing Users Log In', 'wp-members' ),
			'login_username'       => __( 'Username' ),
			'login_password'       => __( 'Password' ),
			'login_button'         => __( 'Log In' ),
			'remember_me'          => __( 'Remember Me' ),
			'forgot_link_before'   => __( 'Forgot password?', 'wp-members' ) . '&nbsp;',
			'forgot_link'          => __( 'Click here to reset', 'wp-members' ),
			'register_link_before' => __( 'New User?', 'wp-members' ) . '&nbsp;',
			'register_link'        => __( 'Click here to register', 'wp-members' ),
			'username_link_before' => __( 'Forgot username?', 'wp-members' ) . '&nbsp;',
			'username_link'        => __( 'Click here', 'wp-members' ),
			
			// Password change form.
			'pwdchg_heading'       => __( 'Change Password', 'wp-members' ),
			'pwdchg_password1'     => __( 'New password' ),
			'pwdchg_password2'     => __( 'Confirm new password' ),
			'pwdchg_button'        => __( 'Update Password', 'wp-members' ),
			
			// Password reset form.
			'pwdreset_heading'     => __( 'Reset Forgotten Password', 'wp-members' ),
			'pwdreset_username'    => __( 'Username' ),
			'pwdreset_email'       => __( 'Email' ),
			'pwdreset_button'      => __( 'Reset Password' ),
			
			// Retrieve username form.
			'username_heading'     => __( 'Retrieve username', 'wp-members' ),
			'username_email'       => __( 'Email Address', 'wp-members' ),
			'username_button'      => __( 'Retrieve username', 'wp-members' ),
			
			// Register form.
			'register_heading'     => __( 'New User Registration', 'wp-members' ),
			'register_username'    => __( 'Choose a Username', 'wp-members' ),
			'register_rscaptcha'   => __( 'Input the code:', 'wp-members' ),
			'register_tos'         => __( 'Please indicate that you agree to the %s TOS %s', 'wp-members' ),
			'register_clear'       => __( 'Reset Form', 'wp-members' ),
			'register_submit'      => __( 'Register' ),
			'register_req_mark'    => '<span class="req">*</span>',
			'register_required'    => '<span class="req">*</span>' . __( 'Required field', 'wp-members' ),
			
			// User profile update form.
			'profile_heading'      => __( 'Edit Your Information', 'wp-members' ),
			'profile_username'     => __( 'Username' ),
			'profile_submit'       => __( 'Update Profile', 'wp-members' ),
			'profile_upload'       => __( 'Update this file', 'wp-members' ),
			
			// Error messages and dialogs.
			'login_failed_heading' => __( 'Login Failed!', 'wp-members' ),
			'login_failed'         => __( 'You entered an invalid username or password.', 'wp-members' ),
			'login_failed_link'    => __( 'Click here to continue.', 'wp-members' ),
			'pwdchangempty'        => __( 'Password fields cannot be empty', 'wp-members' ),
			'usernamefailed'       => __( 'Sorry, that email address was not found.', 'wp-members' ),
			'usernamesuccess'      => __( 'An email was sent to %s with your username.', 'wp-members' ),
			'reg_empty_field'      => __( 'Sorry, %s is a required field.', 'wp-members' ),
			'reg_valid_email'      => __( 'You must enter a valid email address.', 'wp-members' ),
			'reg_non_alphanumeric' => __( 'The username cannot include non-alphanumeric characters.', 'wp-members' ),
			'reg_empty_username'   => __( 'Sorry, username is a required field', 'wp-members' ),
			'reg_password_match'   => __( 'Passwords did not match.', 'wp-members' ),
			'reg_email_match'      => __( 'Emails did not match.', 'wp-members' ),
			'reg_empty_captcha'    => __( 'You must complete the CAPTCHA form.', 'wp-members' ),
			'reg_invalid_captcha'  => __( 'CAPTCHA was not valid.', 'wp-members' ),
			
			// Links.
			'profile_edit'         => __( 'Edit My Information', 'wp-members' ),
			'profile_password'     => __( 'Change Password', 'wp-members' ),
			'register_status'      => __( 'You are logged in as %s', 'wp-members' ),
			'register_logout'      => __( 'Click to log out.', 'wp-members' ),
			'register_continue'    => __( 'Begin using the site.', 'wp-members' ),
			'login_welcome'        => __( 'You are logged in as %s', 'wp-members' ),
			'login_logout'         => __( 'Click to log out', 'wp-members' ),
			'status_welcome'       => __( 'You are logged in as %s', 'wp-members' ),
			'status_logout'        => __( 'click to log out', 'wp-members' ),
			
			// Widget.
			'sb_status'            => __( 'You are logged in as %s', 'wp-members' ),
			'sb_logout'            => __( 'click here to log out', 'wp-members' ),
			'sb_login_failed'      => __( 'Login Failed!<br />You entered an invalid username or password.', 'wp-members' ),
			'sb_not_logged_in'     => __( 'You are not logged in.', 'wp-members' ),
			'sb_login_username'    => __( 'Username' ),
			'sb_login_password'    => __( 'Password' ),
			'sb_login_button'      => __( 'log in', 'wp-members' ),
			'sb_login_forgot'      => __( 'Forgot?', 'wp-members' ),
			'sb_login_register'    => __( 'Register' ),
			
			// Default Dialogs.
			'restricted_msg'       => __( "This content is restricted to site members.  If you are an existing user, please log in.  New users may register below.", 'wp-members' ),
			'user'                 => __( "Sorry, that username is taken, please try another.", 'wp-members' ),
			'email'                => __( "Sorry, that email address already has an account.<br />Please try another.", 'wp-members' ),
			'success'              => __( "Congratulations! Your registration was successful.<br /><br />You may now log in using the password that was emailed to you.", 'wp-members' ),
			'editsuccess'          => __( "Your information was updated!", 'wp-members' ),
			'pwdchangerr'          => __( "Passwords did not match.<br /><br />Please try again.", 'wp-members' ),
			'pwdchangesuccess'     => __( "Password successfully changed!", 'wp-members' ),
			'pwdreseterr'          => __( "Either the username or email address do not exist in our records.", 'wp-members' ),
			'pwdresetsuccess'      => __( "Password successfully reset!<br /><br />An email containing a new password has been sent to the email address on file for your account.", 'wp-members' ),
		
		); // End of $defaults array.
		
		/**
		 * Filter default terms.
		 *
		 * @since 3.1.0
		 */
		$text = apply_filters( 'wpmem_default_text_strings', '' );
		
		// Merge filtered $terms with $defaults.
		$text = wp_parse_args( $text, $defaults );
		
		// Return the requested text string.
		return $text[ $str ];
	
	} // End of get_text().
	
	
	/**
	 * Load the admin api.
	 *
	 * @since 3.1.0
	 */
	function load_admin_api() {
		if ( is_admin() ) {
			$this->admin = new WP_Members_Admin_API;
		}
	}

} // End of WP_Members class.