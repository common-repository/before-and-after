<?php
/*
Plugin Name: Before & After
Plugin URI: http://goldplugins.com/our-plugins/before-and-after/
Description: Before And After is a lead capture plugin for Wordpress. It allows a webmaster to require visitors to complete a goal, such as filling out a contact form, before viewing the content inside the shortcode. This functionality is also useful when webmaster's want to ensure visitors read a Terms Of Service or Copyright Notice before viewing a given page.
Author: Gold Plugins
Version: 3.9
Author URI: http://goldplugins.com

This plugin is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin .  If not, see <http://www.gnu.org/licenses/>.
*/
	
include('include/b_a-custom-post-type.php');
include('include/backfill_functions.php');
include('include/ba.settings.page.class.php');
include('include/ba.goal_model.class.php');
include('include/ba.forms.class.php');
include('include/ba.menus.class.php');
include('include/ba.shortcodes.class.php');
include('include/ba.ajax.class.php');
include('include/ba.cf7.plugin.php');
include('include/ba.gravityforms.plugin.php');
include('include/ba_kg.php');
include('include/widgets/ba.goal_widget.class.php');
require_once('include/Before_And_After_Admin_Bar_Menu.php');
require_once('include/classes/Before_And_After_File_Tools.php');
require_once('include/classes/field_matcher.class.php');
require_once('include/classes/string_formatter.class.php');
require_once('include/example_content/Before_And_After_Example_Content.php');
require_once('include/lib/GP_Media_Button/gold-plugins-media-button.class.php');
require_once('include/lib/GP_Sajak/gp_sajak.class.php');
require_once('include/lib/GP_Vandelay/gp_vandelay.class.php');
require_once('include/lib/GP_Aloha/gp_aloha.class.php');
require_once('include/lib/GP_Custom_Forms/GP_Custom_Forms.php');
require_once('include/lib/GP_Post_Duplicator/GP_Post_Duplicator.class.php');
require_once('include/tgmpa/init.php');
require_once('include/Before_And_After_Update_Notices.php');
require_once('include/Before_And_After_Upgrade_Reminder.class.php');

/* Gutenberg custom blocks */
if ( function_exists('register_block_type') ) {
	require_once('blocks/goal.php');
	require_once('blocks/complete-goal.php');
}

class BeforeAndAfterPlugin
{
	var $plugin_title = 'Before & After';
	var $proUser = false;
	var $in_widget = false;
	
	/* Plugin init. Registers shortcodes and starts the session if needed */
	function __construct()
	{
		// first, ensure the session has been started so that we'll be able to mark goals as completed
		if(session_id() == '') {
			session_start();
		}
		
		// check the reg key
		$this->verify_registration_key();

		// instantiate our subclasses
		$this->Goal = new BA_Goal_Model( $this );
		$this->Forms = new Before_And_After_Forms( $this );
		$this->Menus = new BA_Menus( $this );
		$this->Shortcodes = new BA_Shortcodes( $this );
		$this->AJAX = new Before_And_After_AJAX( $this, $this->Goal );
		$this->CF7_Plugin = new BA_CF7_Plugin( $this );
		$this->GForms_Plugin = new BA_GravityForms_Plugin( $this );
		$this->Admin_Bar_Menus = new Before_And_After_Admin_Bar_Menu();
		$this->Update_Notices = new Before_And_After_Update_Notices();
		
		$extra_buttons = array();
		$tabs = new GP_Sajak( array(
			'header_label' => 'Before &amp; After Settings',
			'settings_field_key' => 'b_a_option_group',
			'show_save_button' => true
		) );
		
		// show it on any add post-ish page *but* goals or conversions
		$is_ba_post_type = !empty($_REQUEST['post_type']) && in_array( $_REQUEST['post_type'], array('b_a_goal', 'b_a_conversion') );
		$mb = false;
		if( is_admin() && !$is_ba_post_type ) {
			$this->media_button = new Gold_Plugins_Media_Button('Before &amp; After', 'star-filled', 'before_after_media_buttons');
			$this->media_button->add_button('Add Goal', 'goal', 'before_after_goal_widget', 'plus');
			$mb = $this->media_button;
		}

		$this->Settings = new BA_Settings_Page( $this, $tabs, $mb);
		
		if ( !$this->is_pro() ) {
			$this->Upgrade_Reminder = new Before_And_After_Upgrade_Reminder();
		}
		
		add_action( 'init', array($this, 'catch_download_links') );
		add_action( 'wp_enqueue_scripts', array($this, 'enqueue_scripts') );
		add_action( 'wp_enqueue_scripts', array($this, 'admin_css') );
		add_action( 'admin_enqueue_scripts', array($this, 'admin_css') );
		add_action( 'admin_enqueue_scripts', array($this, 'admin_scripts'), 10, 1 );
		
		//add our custom links for Settings and Support to various places on the Plugins page
		$plugin = plugin_basename(__FILE__);
		add_filter( "plugin_action_links_{$plugin}", array($this, 'add_settings_link_to_plugin_action_links') );
		add_filter( 'plugin_row_meta', array($this, 'add_custom_links_to_plugin_description'), 10, 2 );	
		
		// register all of our widgets now
		add_action( 'widgets_init', array($this, 'register_all_widgets') );

		register_activation_hook( __FILE__, array($this, 'activation_hook') );

		add_action( 'activate_before-and-after-pro/before-and-after-pro.php', array($this, 'pro_activation_hook') );
		
		/* Add Gutenberg block category */
		add_filter( 'block_categories', array($this, 'add_gutenberg_block_category'), 10, 2 );
		
		if ( is_admin() ) {
			// load Aloha
			$config = array(
				'menu_label' => __('About Plugin'),
				'page_title' => __('Welcome To Before &amp; After'),
				'tagline' => __('Before &amp; After is the easiest way to add Lead Capture forms to your website.'),
				'top_level_menu' => 'before-and-after-settings',
			);
			$this->Aloha = new GP_Aloha($config);
			add_filter( 'gp_aloha_welcome_page_content_before-and-after-settings', array($this, 'get_welcome_template') );			
		}
		
		add_action( 'admin_init', array($this, 'add_extra_classes_to_admin_menu') );
		
		// setup up post duplicator (used by both Goals and Forms, but could be used by any post type)
		$this->duplicator = new GP_Post_Duplicator();
		add_action( 'gp_post_duplicate', array($this, 'reset_meta_on_post_duplicate'), 10, 1 );
	}
	
	function get_welcome_template()
	{
		$base_path = plugin_dir_path( __FILE__ );
		$template_path = $base_path . '/include/content/welcome.php';
		$is_pro = $this->is_pro();
		$content = file_exists($template_path)
				   ? include($template_path)
				   : '';
		return $content;
	}
	
	//only do this once
	function activation_hook() {		
		
		// make sure the welcome screen gets seen again
		$this->Aloha->reset_welcome_screen();		
		$this->create_example_content();
	}	
		
	/**
	  * Delete registered name field (no longer needed with Pro plugin installed)
	 */	 
	function pro_activation_hook()
	{
		$options = get_option( 'b_a_options' );	
		if ( isset($options['registration_email']) ) {
			unset( $options['registration_email'] );
			update_option('b_a_options', $options);
			$options = get_option( 'b_a_options' );	
		}
	}
	
	function create_example_content()
	{		
		$example_content = new Before_And_After_Example_Content();
		$example_content->create_example_content();
	}

	function admin_css()
	{
		if( is_user_logged_in() ) {
			$admin_css_url = plugins_url( 'assets/css/admin_style.css' , __FILE__ );
			wp_register_style('before-and-after-admin', $admin_css_url);
			wp_enqueue_style('before-and-after-admin');
			
			$flags_css_url = plugins_url( 'assets/css/flags.css' , __FILE__ );
			wp_register_style('before-and-after-flags', $flags_css_url);
			wp_enqueue_style('before-and-after-flags');

			wp_register_script(
				'ba-admin_v1',
				plugins_url('assets/js/ba-admin_v1.js', __FILE__),
				array( 'jquery' ),
				false,
				true
			);
			
			$translation_array = array(
				'ajax_url' =>  admin_url( 'admin-ajax.php' ),
				'confirm_reset_message' => __("Are you sure you want to Reset All Goals?\n\nThis will return all Goals you may have completed to their Before state. It will not affect your visitors. \n\nThis will also reload this page, losing any information you may have entered.", 'before-and-after')
			);
			wp_enqueue_script('ba-admin_v1');
			wp_localize_script('ba-admin_v1', 'before_and_after_ajax', $translation_array );
			

		}	
	}
	
	function enqueue_scripts()
	{
		wp_register_script(
			'before_and_after',
			plugins_url('assets/js/before_and_after_fe.js', __FILE__),
			array( 'jquery' ),
			false,
			true
		);
		wp_enqueue_script('before_and_after');
		
		$translation_array = array(
			'ajax_url' =>  admin_url( 'admin-ajax.php' ),
		);
		wp_localize_script('before_and_after', 'before_and_after_vars', $translation_array );
				
	}

	function admin_scripts( $hook )
	{
		global $post;

		if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
			if ( 'b_a_goal' === $post->post_type ) {
				wp_register_script(
					'b_a_edit_goal',
					plugins_url('assets/js/b_a_edit_goal.js', __FILE__),
					array( 'jquery' ),
					false,
					true
				);
				wp_enqueue_script('b_a_edit_goal');
			}
		}

		if ( !empty($post) && ('b_a_form' === $post->post_type) ) {
			wp_register_script(
				'b_a_edit_form',
				plugins_url('assets/js/b_a_edit_form.js', __FILE__),
				array( 'jquery', 'jquery-ui-tabs' ),
				false,
				true
			);
			wp_enqueue_script('b_a_edit_form');
		}
	}
	
	
	function show_completed_goals()
	{
?>
		<h1>Hello Then!</h1>
		<p>Fancy a cup of tea?</p>
<?php		
	}
	
	function catch_download_links()
	{
		if (isset($_GET['file_download']))
		{
			$download_key = sanitize_text_field($_GET['file_download']);
			$file_url = '';
			
			// its a goal key if the download key begins with 'd_'
			$is_goal_key = (strpos($download_key, 'd_') === 0);
			$goal = 0;
			$conversion = 0;
			$goal_id = 0;
			$goal_completed = false;
			
			if ($is_goal_key) { // find goal by goal key

				// find the goal which matches this key
				$args = array(	'post_type' => 'b_a_goal',
								'posts_per_page' => 1,
								'meta_key' => '_b_a_download_key', 
								'meta_value' => $download_key );
				
				$goal_list = get_posts($args);
				$goal = count($goal_list) > 0 
						? array_shift($goal_list)
						: false;

				if ($goal) {
					// find the matching goal
					$goal_id = $goal->ID;
				}
				$goal_completed = $this->Goal->goal_id_completed($goal_id);
			}
			else { // find goal from the conversion
				
				// find the conversion which matches this key
				$args = array(	'post_type' => 'b_a_conversion',
								'posts_per_page' => 1,
								'meta_key' => '_b_a_download_key', 
								'meta_value' => $download_key );
				
				$conversion_list = get_posts($args);
				$conversion = count($conversion_list) > 0 ? array_shift($conversion_list) : false;
					// find the matching goal
					$goal_id = intval(get_post_meta($conversion->ID, 'goal_id', true));
					$goal = $goal_id > 0
							? get_post($goal_id)
							: false;					

				// see if this visitor has the matching conversion key in their session
				$goalName = 'Goal_ID_' . $goal_id;
				$sessionKey_cid = 'goal_' . md5($goalName) . '_cid';
				$cid = isset($_SESSION[$sessionKey_cid])
					   ? intval($_SESSION[$sessionKey_cid])
					   : 0;

				$goal_completed = !empty($cid);
			}
			
			// at this point, $goal_id and $goal must be non-empty, if the goal was found.
			// additionally, $goal_completed will be set (bool)
			// if they are empty, we can quit now
			if ( empty($goal) || empty($goal_id) ) {
				return;
			}
			
			if ( !$goal_completed ) {
				// does not have the key!!!
				// find the URL to send them to
				$fallback_url = $this->get_goal_fallback_url_for_downloads($goal_id);
				wp_redirect( $fallback_url, 302 );
			}
				
			// try to deliver the file. if we cant (i.e., there is no valid URL) then 
			// do nothing and let WP take over			
			if( $goal ) {
			
				// try to find a file URL to redirect to
				$after_action = get_post_meta($goal->ID, '_goal_after_action', true);
				if ($after_action && $after_action == 'file_url') {
					$file_url = $this->Goal->get_goal_setting_value($goal->ID, 'after-values', 'file_url', '');
				}

				// if a valid URL was found, deliver the file as a forced
				// download without exposing the URL
				if ($file_url != '') {
				
					// update conversions if needed
					if ( !empty($conversion) ) {
						do_action('before_and_after_file_download', $goal, $conversion, $file_url);
					}

					// force the file as a download
					$file_name = $this->Goal->get_goal_setting_value($goal->ID, 'after-values', 'file_name', '');
					$this->deliver_file($file_url, $file_name);
					
					// return cleanly
					exit; 				
				}
			}
		}		
	}
	
	function get_goal_fallback_url_for_downloads($goal_id)
	{
		$fallback_url = $this->Goal->get_goal_setting_value($goal_id, 'after-values', 'file_url_no_key', '');		
					
		// if no fallback URL was specified, try linking directly to the goal
		if ( empty($fallback_url) ) {
			$fallback_url = get_the_permalink($goal_id);
		}

		// if linking directly to the goal wont work, link to the home page
		if ( empty($fallback_url) ) {
			$fallback_url = home_url();
		}

		$fallback_url = apply_filters('b_a_download_fallback_url', $fallback_url, $goal_id);
		$fallback_url = apply_filters('b_a_download_fallback_url_goal_' . $goal_id, $fallback_url);
		return $fallback_url;
	}
	
	function deliver_file($file_url, $file_name = '')
	{
		$file_name = !empty($file_name)
					? basename($file_name)
					: basename($file_url);		
		$file_tools = new Before_And_After_File_Tools();
		$file_type = $file_tools->guess_file_type($file_name);
		
		// TODO: maybe check if remote file exists? right now its a garbage in garbage out situation
		ob_start();
		header( sprintf('Content-Type: %s', $file_type ) );
		header( sprintf('Content-Disposition: attachment; filename="%s"', $file_name ) );
		$this->readfile_chunked($file_url);
		$out = ob_get_contents();
		ob_end_clean();
		echo trim($out);
	}

	// Read a file and display its content chunk by chunk
	function readfile_chunked($file_name, $retbytes = TRUE) {
		$chunk_size = 1024*1024; // Size (in bytes) of tiles chunk
		$buffer = '';
		$cnt    = 0;
		
		$opts = array(
			'http' => array(
				'method' => 'GET',
				'max_redirects' => '20'
			)
        );

		$context = stream_context_create($opts);
		$handle = fopen($file_name, 'rb', false, $context);
		
		if ($handle === false) {
			return false;
		}

		while (!feof($handle)) {
			$buffer = fread($handle, $chunk_size);
			echo $buffer;
			ob_flush();
			flush();

			if ($retbytes) {
				$cnt += strlen($buffer);
			}
		}

		$status = fclose($handle);

		if ($retbytes && $status) {
			return $cnt; // return num. bytes delivered like readfile() does.
		}

		return $status;
	}
	
	// check the reg key, and set $this->isPro to true/false reflecting whether the Pro version has been registered
	function verify_registration_key()
	{
		$options = get_option( 'b_a_options' );	
		if (isset($options['api_key']) && 
			isset($options['registration_email']) /* && 
			isset($options['registration_url']) */ ) {
		
				// check the key
				$keychecker = new B_A_KeyChecker();
				$correct_key = $keychecker->computeKeyEJ($options['registration_email']);
				if (strcmp($options['api_key'], $correct_key) == 0) {
					$this->proUser = true;
				} else if(isset($options['registration_url']) && isset($options['registration_email'])) {//only check if its an old key if the relevant fields are set
					//maybe its an old style of key
					$correct_key = $keychecker->computeKey($options['registration_url'], $options['registration_email']);
					if (strcmp($options['api_key'], $correct_key) == 0) {
						$this->proUser = true;
					} else {
						$this->proUser = false;
					}
				}
		
		} else {
			// keys not set, so can't be valid.
			$this->proUser = false;
			
		}
		
		// look for the Pro plugin - this is also a way to be validated
		$plugin = "before-and-after-pro/before-and-after-pro.php";
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );			
		if( is_plugin_active($plugin) ) {
			$this->proUser = true;
		}		
	}

	function is_pro()
	{
		return $this->proUser;
	}

	//add an inline link to the settings page, before the "deactivate" link
	function add_settings_link_to_plugin_action_links($links) 
	{
		$settings_link = sprintf( '<a href="%s">%s</a>', 'admin.php?page=before-and-after-settings', __('Settings') );
		array_unshift($links, $settings_link); 

		$docs_link = sprintf( '<a href="%s">%s</a>', 'https://goldplugins.com/documentation/before-after-documentation/?utm_source=before_after&utm_campaign=before_after_docs', __('Documentation') );
		array_unshift($links, $docs_link); 
		
		if(!$this->is_pro()){
			$upgrade_url = 'http://goldplugins.com/special-offers/upgrade-to-before-and-after-pro/?utm_source=before_after_free_plugin&utm_campaign=upgrade_to_pro';
			$upgrade_link = sprintf( '<a href="%s" target="_blank" class="b_a_pro_link">%s</a>', $upgrade_url, __('Upgrade to Pro') );			
			array_unshift($links, $upgrade_link); 
		}

		if ( isset($links['edit']) ) {
			unset($links['edit']);
		}

		return $links; 
	}

	// add inline links to our plugin's description area on the Plugins page
	function add_custom_links_to_plugin_description($links, $file) 
	{

		/** Get the plugin file name for reference */
		$plugin_file = plugin_basename( __FILE__ );
	 
		/** Check if $plugin_file matches the passed $file name */
		if ( $file == $plugin_file )
		{
			$support_url = 'https://goldplugins.com/contact/?utm_source=before_after_plugin_menu&utm_campaign=before_after_support&utm_banner=plugin_description';			
			$support_link = sprintf( '<a href="%s" target="_blank">%s</a>', $support_url, __('Get Support') );
			$settings_link = sprintf( '<a href="%s">%s</a>', admin_url('admin.php?page=before-and-after-settings'), __('Settings') );

			$new_links['settings_link'] = $settings_link;
			$new_links['support_link'] = $support_link;
				
			if(!$this->is_pro()){
				$upgrade_url = 'https://goldplugins.com/special-offers/upgrade-to-before-and-after-pro/?utm_source=before_after_plugin_menu&utm_campaign=before_after_support&utm_banner=plugin_description';
				$upgrade_link = sprintf( '<a href="%s" target="_blank">%s</a>', $upgrade_url, __('Upgrade to Pro') );			
				$new_links['upgrade_to_pro'] = $upgrade_link;
			}
			
			$links = array_merge( $links, $new_links);
		}
		return $links; 
	}
	
	function reset_meta_on_post_duplicate( $post_id )
	{
		// reset the download key
		if ( metadata_exists('post', $post_id, '_b_a_download_key') ) {
			delete_post_meta($post_id, '_b_a_download_key');		
		}
	}

	function add_gutenberg_block_category ( $categories, $post ) 
	{
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'before-and-after',
					'title' => 'Before & After',
				),
			)
		);
	}

	function add_extra_classes_to_admin_menu() 
	{
		global $menu;
		if ( empty($menu) || !is_array($menu) ) {
			return;
		}
		
		foreach( $menu as $key => $value ) {
			$extra_classes = 'company_directory_admin_menu';
			$extra_classes .= $this->is_pro()
							  ? ' before_and_after_pro_admin_menu'
							  : ' before_and_after_free_admin_menu';
			if( 'Before & After' == $value[0] ) {
				$menu[$key][4] .= ' ' . $extra_classes;
			}
		}
	}
	
	
	function register_all_widgets()
	{
		register_widget( 'Before_After_Goal_Widget' );
	}
}

// Instantiate one copy of the plugin class, to kick things off
$beforeAndAfter = new BeforeAndAfterPlugin();

// Initialize any addons now
do_action('before_and_after_bootstrap');
