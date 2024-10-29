<?php
class Before_And_After_Forms
{
    /**
     * Holds the values to be used in the fields callbacks
     */
	const post_type = 'b_a_form';
	private $root;
	private $plugin_title;
	private $form_editor;

    /**
     * Start up
     */
    public function __construct($root)
    {
		$this->root = $root;
		$this->plugin_title = $root->plugin_title;
		$this->form_editor = new GP_Custom_Forms();
		$this->setup_custom_post_type();
		
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'gp_custom_form_submitted', array( $this,  'complete_attached_goals' ), 10, 2 );
		add_action( 'gp_custom_form_submitted', array( $this,  'send_notification_email' ), 10, 2 );
		add_filter( 'b_a_get_posted_data_b_a_form', array( $this, 'get_posted_data') );

		// Customize the links in the Row Actions menu of each Form
		add_filter('page_row_actions', array( $this, 'customize_page_row_actions' ), 10, 2);
		add_filter('post_row_actions', array( $this, 'customize_page_row_actions' ), 10, 2);
		add_filter( 'post_updated_messages', array($this, 'form_updated_messages') );
		add_action( 'wp_ajax_b_a_load_form_from_template', array($this, 'ajax_load_form_from_template') );
	}

	// adds a special link to the Row Actions menu, to display the visitors who have completed each goal
	function customize_page_row_actions($actions, $page_object)
	{		
		if ($page_object->post_type == self::post_type)
		{
			unset($actions['view']); // remove View link
			unset($actions['inline hide-if-no-js']); // remove Quick Edit link
			
			if (current_user_can('edit_posts')) {
				// add duplicate link
				$duplicate_url = admin_url('admin.php?action=gp_duplicate_post_as_draft&post=' . $page_object->ID);
				$actions['duplicate'] = sprintf( '<a href="%s" title="%s" rel="permalink">%s</a>',
												 $duplicate_url,
												 __('Duplicate This', 'before-and-after') . '  Form',
												 __('Duplicate', 'before-and-after') );
				
				// add "Use In New Goal" link
				$new_goal_url = admin_url('post-new.php?post_type=b_a_goal&form_id=' . $page_object->ID);		
				$actions['use-in-goal'] = '<a href="' . $new_goal_url . '" class="use_in_goal_link">' . __('Use In New Goal') . '</a>';
			}
			$actions = apply_filters('before_and_after_admin_forms_row_actions', $actions, $page_object);
		}
		
		return $actions;
	}
	
	
	function get_posted_data()
	{
		$form_id = intval($_POST['gp_custom_form_id']);
		$posted_data = $_POST['form_' . $form_id];
		$posted_data = apply_filters('b_a_b_a_form_posted_data', $posted_data);	
		return $posted_data;
	}
	
	/*
	 * Returns the list of available templates that can be offered 
	 * to the user to start a new form.
	 */
	function get_form_templates()
	{		
		// load any templates the user has marked available
		$templates = $this->get_user_templates();
		
		// for now we'll just add the ones from example content
		$example_content = new Before_And_After_Example_Content();
		$example_templates = $example_content->get_available_form_templates();
		$templates = array_merge( $templates, $example_templates );
		return apply_filters('gp_custom_forms_get_form_templates', $templates);
	}
	

	/*
	 * Loads a list of user forms that have been marked available as 
	 * templates
	 * 
	 * @returns array If any templates are found, returns an array where
	 * 				  the keys are _form_{$post->ID}, and the values are 
	 * 				  the titles. If no templates are found, returns an
	 * 				  empty array.
	 */
	function get_user_templates()
	{
		$templates = array();
		$args = array(
			'meta_key' => 'is_template',
			'meta_value' => '1',
			'post_type' => 'b_a_form',
			'post_status' => 'any',
			'posts_per_page' => -1
		);
		$posts = get_posts($args);
		if ( !empty($posts) ) {
			foreach($posts as $post) {
				$key = sprintf('_form_%d', $post->ID);
				$templates[$key] = $post->post_title;
			}
		}
		return $templates;
	}
	
	/* Source: https://stackoverflow.com/a/8891890 */
	function url_origin( $s, $use_forwarded_host = false )
	{
		$ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
		$sp       = strtolower( $s['SERVER_PROTOCOL'] );
		$protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
		$port     = $s['SERVER_PORT'];
		$port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
		$host     = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
		$host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
		return $protocol . '://' . $host;
	}

	/* Source: https://stackoverflow.com/a/8891890 */
	function get_full_url( $use_forwarded_host = false )
	{
		return $this->url_origin( $_SERVER, $use_forwarded_host ) . $_SERVER['REQUEST_URI'];
	}
	
	/*
	 * Replace each field in $text with the corresponding value from the 
	 * submitted data
	 *
	 * @param string $text The text to modify
	 * @param integer $form_id The id of the B_A_Form to use. Will only replace fields 
	 *						   which appear in this form.
	 * @param array $form_data The submitted form data. The values in this array will 
	 * 						   be inserted into $text
	 *
	 * @return string The modified text, with all fields replaced
	 */
	function replace_fields_in_text($text, $form_id, $form_data)
	{
		foreach($form_data as $key => $val) {
			// make sure this is a real field before we continue
			if ( !$this->form_editor->is_valid_field($key) ) {
				continue;
			}
			
			// look for {{field_name}} and replace it with the submitted value
			$search = sprintf('{{%s}}', $key);
			$replace = filter_var($val, FILTER_FLAG_ENCODE_HIGH);
			$text = str_replace($search, $replace, $text);
		}
		
		return $text;
	}

	// Looks for a Goal that is hooked to this form. If one is found, marks it complete.
	function send_notification_email($form_id, $form_data)
	{
		// get per-Form email address setting. if blank, use the site admin's email
		$to_email = get_post_meta($form_id, 'email_for_submissions', true);
		if ( empty($to_email) ) {
			$to_email = get_option('admin_email', '');
		}
		
		$subject = get_post_meta($form_id, 'email_subject', true);
		$subject = !empty($subject)
				   ? $subject
				   : 'Form Completed: ' . get_the_title($form_id);
				   
		$subject = $this->replace_fields_in_text($subject, $form_id, $form_data);

	    $body = '<p style="font-family: arial; verdana; sans-serif;"><b>' . __('Congratulations!', 'before-and-after') . '</b></p>';
	    $body .= __('A visitor completed your form: ', 'before-and-after') . htmlentities(get_the_title($form_id)) . '</p>';
		$body .= "<br>\n<br>\n";
		$body .= $this->generate_submitted_data_table($form_id, $form_data);
		$body .= "<br>\n<br>\n";
		$body .= '<p font-size: 8px; font-family: verdana; arial; helvetica; sans-serif;">Sent by Before &amp; After on ' . site_url() . '</p>';
		
		$to_email = apply_filters('b_a_form_notification_email', $to_email, $form_id, $form_data);
		$subject = apply_filters('b_a_form_notification_subject', $subject, $form_id, $form_data);
		$body = apply_filters('b_a_form_notification_body', $body, $form_id, $form_data);
		
		// abort if no email is specified
		if ( empty($to_email) ) {
			return;
		}
		
		wp_mail($to_email, $subject, $body);
	}

	// Looks for a Goal that is hooked to this form. If one is found, marks it complete.
	function complete_attached_goals($form_id, $form_data)
	{
		$goal_id = !empty( $_REQUEST['_before_after_goal_id'] )
				   ? intval($_REQUEST['_before_after_goal_id'])
				   : 0;		
		$goal_complete_url = $this->get_full_url(); // use current URL

		if( !empty($goal_id) ) {
			$completed = $this->root->Goal->completeGoalById($goal_id, $goal_complete_url);
		}
	}

	function admin_init()
	{
		/*
		add_filter('manage_edit-form_columns', array( $this, 'add_new_columns' ));
		add_filter('manage_edit-forms_columns', array( $this, 'add_new_columns' ));
		add_action('manage_form_posts_custom_column', array( $this, 'manage_form_columns' ), 10, 2);
		*/
	}
	
	function manage_form_columns($column_name, $id) {
		global $wpdb;
		switch ($column_name) {
		case 'id':
			echo $id;
				break;
			
		case 'form_shortcode':
			echo '[form id="' . $id . '"]';
			break;

			case 'complete_form_shortcode':
			echo '[complete_form id="' . $id . '"]';
			break;
			
		default:
			break;
		} // end switch
	}
	
	function add_new_columns($gallery_columns) {
		$gc = $this->array_put_to_position($gallery_columns, __('Complete Form Shortcode'), 2, 'complete_form_shortcode');
		$gc = $this->array_put_to_position($gallery_columns, __('Form Shortcode'), 2, 'form_shortcode');
		return $gc;
	}
	
	function array_put_to_position(&$array, $object, $position, $name = null)
	{
			$count = 0;
			$return = array();
			foreach ($array as $k => $v)
			{  
					// insert new object
					if ($count == $position)
					{  
							if (!$name) $name = $count;
							$return[$name] = $object;
							$inserted = true;
					}  
					// insert old object
					$return[$k] = $v;
					$count++;
			}  
			if (!$name) $name = $count;
			if (!$inserted) $return[$name];
			$array = $return;
			return $array;
	}		
	
	private function setup_custom_post_type()
	{
		// create the Form custom post type
		$postType = array('name' => 'form', 'plural' => 'forms', 'slug' => 'b_a_forms');
		$fields = array();
		$args = array(
			'supports' => array( 'title' ), // notably *not* thumbnail, comments, author, page attributes, etc
			'public' => false,  // it's not public, it shouldn't have it's own permalink, and so on
			'publicly_queryable' => false,  // you should not be able to query it from the front end (e.g., http://website.com/?b_a_form=12345)
			'show_ui' => true,  // you should be able to edit it in wp-admin
			'exclude_from_search' => true,  // you should exclude it from search results
			'show_in_nav_menus' => false,  // you shouldn't be able to add it to menus
			'has_archive' => false,  // it shouldn't have archive page
			'rewrite' => false,  // it shouldn't have rewrite rules			
		);
		$args = apply_filters('b_a_form_cpt_args', $args);
		$this->root->custom_post_types[] = new B_A_CustomPostType($postType, $fields, true, $args);

		// setup the meta boxes on the Add/Edit Form screen
		//add_action('init', array($this, 'remove_unneeded_metaboxes')); // remove some default meta boxes
		add_action( 'admin_menu', array( $this, 'add_meta_boxes' ) ); // add our custom meta boxes

		// add a hook to save the new values of our Form settings whenever the Form is saved
		add_action( 'save_post', array( $this, 'save_form_settings' ), 1, 2 );

		// add a special link to the Row Actions menu of each Form, which displays the visitors who have completed the form
		//add_filter('page_row_actions', array( $this, 'add_page_row_actions' ), 10, 2);
		//add_filter('post_row_actions', array( $this, 'add_page_row_actions' ), 10, 2);

	}
	
	function is_new_post_page()
	{
		global $pagenow;
        return is_admin() && ('post-new.php' == $pagenow);
	}
	
	function display_form_editor_meta_box()
	{
		global $post;
		$form_templates = $this->get_form_templates();		
		if ( $this->is_new_post_page() ):
?>
		<div id="form_editor_start_page">		
			<div id="form_editor_select_starting_option">
				<div id="form_editor_select_template_wrapper">
					<h3><?php _e('Start from a template', 'before-and-after');?>:</h3>
					<select id="form_editor_template_select">
						<?php foreach($form_templates as $template_slug => $template_title):?>
						<option value="<?php echo htmlentities($template_slug); ?>"><?php echo htmlentities($template_title); ?></option>
						<?php endforeach; ?>
					</select>
					<button id="btn_form_editor_use_selected_template" class="button" type="button"><?php _e('Select Template', 'before-and-after'); ?></button>
				</div>
				<br>
				<div id="form_editor_blank_form_wrapper">
					<h3><em>or</em> <?php _e('Start with an empty form', 'before-and-after');?>:</h3>
					<button id="btn_form_editor_start_with_blank" class="button" type="button"><?php _e('Empty Form', 'before-and-after');?></button>
				</div>
			</div>		
		<?php endif; ?>
			<div id="form_editor_wrapper">
			<?php		
				$this->form_editor->set_form_id($post->ID);
				$this->form_editor->form_editor( false );
				wp_nonce_field( 'b_a_form_editor', 'b_a_form_editor_nonce', false, true );
			?>
			</div><!--.form_editor_wrapper-->
		<?php if ( $this->is_new_post_page() ): ?>
		</div><!--.form_editor_start_page-->
		<?php endif; 

		if ( !empty( $_REQUEST['_return_to_goal_id'] ) ) {
			printf( '<input type="hidden" name="_return_to_goal_id" value="%d">', intval($_REQUEST['_return_to_goal_id']) );
		}
		
		
	}

	function display_form_settings_meta_box()
	{
		global $post;
		$email_for_submissions = get_post_meta($post->ID, 'email_for_submissions', true);
		if ( empty($email_for_submissions) ) {
			$email_for_submissions = get_option('admin_email', '');	
		}
		
		$email_subject = get_post_meta($post->ID, 'email_subject', true);
		if ( empty($email_subject) ) {
			$email_subject = sprintf( '%s: %s', __('New submission for form'), get_the_title($post) );
		}	

		$submit_button_label = get_post_meta($post->ID, 'submit_button_label', true);
		if ( empty($submit_button_label) ) {
			$submit_button_label = __('Send', 'before-and-after');
		}	
		?>
		<div>
			<label><strong><?php echo __('Email Address To Receive Form Submissions:', 'before-and-after'); ?></strong></label><br>
			<input class="widefat" type="text" name="form_settings[email_for_submissions]" value="<?php echo htmlentities($email_for_submissions); ?>" />
			<p class="description"><?php echo __('Separate multiple emails with commas.', 'before-and-after'); ?></p>
		</div>

		<div>
			<label><strong><?php echo __('Email Subject:', 'before-and-after'); ?></strong></label><br>
			<input class="widefat" type="text" name="form_settings[email_subject]" value="<?php echo htmlentities($email_subject); ?>" />
			<p class="description"><?php echo __('Tip: add {{field_name}} to your text to replace it with the submitted value.', 'before-and-after'); ?></p>
		</div>

		<div>
			<label><strong><?php echo __('Submit Button Label:', 'before-and-after'); ?></strong></label><br>
			<input class="widefat" type="text" name="form_settings[submit_button_label]" value="<?php echo htmlentities($submit_button_label); ?>" />
			<p class="description"><?php echo __('The text for the button that submits this form.', 'before-and-after'); ?></p>
		</div>
		<?php
	}
	
	function display_template_settings_meta_box()
	{
		global $post;
		$checked = get_post_meta($post->ID, 'is_template', true);
		$checked_attr = !empty($checked)
						? 'checked="checked"'
						: 'xx';
?>
		<div>
			<label>
				<input type="hidden" name="is_template" value="0" />
				<input type="checkbox" name="is_template" value="1" <?php echo $checked_attr; ?> />
				<strong><?php echo __('Make this form available as a template for new Forms', 'before-and-after'); ?></strong>
			</label>
			<br>
		</div>
<?php
	}
	
	function display_available_fields_meta_box()
	{
		global $post;
		$this->form_editor->set_form_id($post->ID);
		$this->form_editor->toolbox( false, false );
	}

	// adds a special link to the Row Actions menu, to display the visitors who have completed each form
	function add_page_row_actions($actions, $page_object)
	{
		if ($page_object->post_type == self::post_type)
		{
			unset($actions['view']); // remove View link
			unset($actions['inline hide-if-no-js']); // remove Quick Edit link
			$actions = apply_filters('before_and_after_admin_forms_row_actions', $actions, $page_object);
		}
		
		return $actions;
	}
	
	// remove unneeded meta boxes from the Form custom post type
	function remove_unneeded_metaboxes()
	{
		remove_post_type_support( self::post_type, 'editor' ); // note: may remove this later and replace with a custom field
		remove_post_type_support( self::post_type, 'excerpt' );
		remove_post_type_support( self::post_type, 'comments' );
		remove_post_type_support( self::post_type, 'author' );		
		remove_post_type_support( self::post_type, 'page-attributes' );		
		remove_post_type_support( self::post_type, 'thumbnail' );		
	}

	// add our custom meta boxes to capture per-Form settings
	function add_meta_boxes()
	{
		add_meta_box( 'form_editor', 'Form', array( $this, 'display_form_editor_meta_box' ), self::post_type, 'normal', 'high' );
		add_meta_box( 'form_settings', 'Form Settings', array( $this, 'display_form_settings_meta_box' ), self::post_type, 'normal', 'high' );
		add_meta_box( 'available_fields', 'Available Fields', array( $this, 'display_available_fields_meta_box' ), self::post_type, 'side', 'default' );
		add_meta_box( 'template_settings', 'Template Settings', array( $this, 'display_template_settings_meta_box' ), self::post_type, 'side', 'default' );
	}
	
	function output_custom_columns_head($defaults)
	{
		// create array of custom columns to  insert into Forms post list
		$cols_to_add = array();
		$cols_to_add["b_a_form_shortcode"] = __('Shortcode', 'before-and-after');
		$cols_to_add = apply_filters('before_and_after_admin_forms_list_columns', $cols_to_add, $defaults);
		
		// insert our custom columns at the 3rd position
		$defaults = 
			array_slice($defaults, 0, 2, true) +
			$cols_to_add + 
			array_slice($defaults, 2, count($defaults)-2, true);			
		return apply_filters('b_a_admin_columns_head', $defaults);
	}
	
	function output_custom_columns_content($column_name, $post_ID)
	{
		if ($column_name == 'b_a_form_shortcode') {
			$my_shortcode = sprintf('[form id="%d"]', $post_ID);
			printf('<textarea rows="1" onclick="this.select();" class="gp_code" style="height: 2em; margin-top: 10px; max-width:100%%">%s</textarea>', $my_shortcode);
		}		
		do_action('b_a_admin_columns_content', $column_name, $post_ID);
	}
	
	// saves the per-Form settings. called whenever the Form is saved
	function save_form_settings()
	{
		global $post;
		// make sure  that the nonce matches and the user has permission to edit this goal
		 if (!isset($_POST[ 'b_a_form_editor_nonce' ]) || !wp_verify_nonce( $_POST[ 'b_a_form_editor_nonce' ], 'b_a_form_editor' ) ||
			 !current_user_can( 'edit_post', $post->ID ) || 
			 $post->post_type != self::post_type)
		{
			return;
		}
		
		// remove custom form data with 'i' keys - these are the 
		// "Available Fields" placeholders
		$form_data = $_POST['custom_forms'];
		$clean_data = array();
		foreach($form_data as $key => $val) {
			if ( is_numeric($key) ) {
				$clean_data[$key] = $val;
			}
		}
		
		$this->form_editor->set_form_id($post->ID);
		$this->form_editor->save_form_data($clean_data);

		
		if ( !empty( $_POST['next_field_index'] ) ) {
			$this->form_editor->save_next_field_index( intval($_POST['next_field_index']) );
		}
		
		// save form settings
		if  ( isset($_POST['form_settings']['email_for_submissions']) ) {
			update_post_meta($post->ID, 'email_for_submissions', $_POST['form_settings']['email_for_submissions']);
		}

		if  ( isset($_POST['form_settings']['email_subject']) ) {
			update_post_meta($post->ID, 'email_subject', $_POST['form_settings']['email_subject']);
		}

		if  ( isset($_POST['form_settings']['submit_button_label']) ) {
			update_post_meta($post->ID, 'submit_button_label', $_POST['form_settings']['submit_button_label']);
		}
		
		// handle "return to goal"
		if  ( isset($_POST['_return_to_goal_id']) ) {
			$_SESSION['_return_to_goal_id'] = intval($_POST['_return_to_goal_id']);
		}

		// save template settings
		if  ( isset($_POST['is_template']) ) {
			
			if ( '1' == $_POST['is_template'] ) {
				// make available
				update_post_meta($post->ID, 'is_template', 1);
			}
			else if ( '0' == $_POST['is_template'] ) {
				// make unavailable
				delete_post_meta($post->ID, 'is_template');
			}
		}
	}
	
	function generate_submitted_data_table($form_id, $form_data, $cleanup_data = true)
	{
		$all_meta = get_metadata('post', $form_id);
		$form_field_labels = $this->form_editor->get_field_labels($form_id);
		$form_field_types = $this->form_editor->get_field_types($form_id);
		ob_start();
		?>
		
		<div id="submitted_data_meta_box">
			<?php if ( !empty($form_data) ): ?>
			<table class="submitted_data table" cellpadding="2" cellspacing="0" style="width: 100%; border: 1px solid #7f7f7f; border-top: 5px solid #004c8b;">
				<thead>
					<tr>
						<th style="color: white; padding: 4px; text-align: left; max-width: 200px; border-bottom: 1px solid #ccc; border-right: 1px solid #288bc5; background-color:#0277bc; font-weight:bold; vertical-align: top; border-top: 1px solid #288bc5;">Field</th>
						<th style="color: white; padding: 4px; text-align: left; border-bottom: 1px solid #ccc; background-color:#0277bc; font-weight:bold; vertical-align: top; border-top: 1px solid #288bc5;">Value</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($form_data as $key => $val): ?>
					<?php
						if ( $cleanup_data && !empty($form_field_types[$key]) && $form_field_types[$key] == 'checkbox' ) {
							$val = __('Yes', 'before-and-after');
						}					
						if ( $cleanup_data && !empty($form_field_labels[$key] ) ) {
							$key = $form_field_labels[$key];
						}
					?>
					<tr class="submitted_field">
						<td class="submitted_label" style="padding: 4px; border-bottom: 1px solid #7f7f7f; border-right: 1px solid #7f7f7f; background-color:#e1e2e1; font-weight:bold; vertical-align: top;"><?php echo htmlentities($key); ?></td>
						<td class="submitted_value" style="padding: 4px; border-bottom: 1px solid #7f7f7f; vertical-align: top; background-color:#fcfcfc; "><?php echo nl2br(htmlentities($val)); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
	
	function ajax_load_form_from_template()
	{
		$template_name = $_POST['template'];
		$output = '';
		$field_count = 0;
		
		if ( '_form_' == substr($template_name, 0, 6) ) {
			$form_id = substr($template_name, 6);			
			$form_fields = get_post_meta($form_id, 'form_fields', true);
			$field_count = count($form_fields);
			foreach($form_fields as $i => $field) {
				$output .= $this->form_editor->render_field_template($field, $i);
			}
		}
		else {
			$example_content = new Before_And_After_Example_Content();
			$form_template = $example_content->load_template_by_slug($template_name);
			if ( !empty($form_template) ) {
				$field_count = count($form_template['form']['fields']);
				foreach($form_template['form']['fields'] as $i => $field) {
					$output .= $this->form_editor->render_field_template($field, $i);
				}
			}
		}

		wp_die( json_encode( array(
			'template' => $template_name,
			'output' => $output,
			'next_field_index' => ($field_count + 1),
		) ) );
	}
	
	/* 
	 * Customize the post saved/updated/etc messages for this custom post type.
	 * hooked to filter: post_updated_messages
	 */
	function form_updated_messages($messages)
	{
		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );

		// If we came to this form via a "Create a New Form" link on the 
		//   Add/Edit Goal screen, then offer the user a one-time chance to
		//   return to the Goal they were editing. 
		// Else offer them a link to use the Form in a new Goal
		if ( !empty($_SESSION['_return_to_goal_id']) ) {
			$new_goal_url  =  admin_url('post.php?post=' . intval($_SESSION['_return_to_goal_id']) . '&action=edit&form_id=' . $post->ID);
			$new_goal_link =  ' ' . sprintf( '<a href="%s">%s</a>', $new_goal_url, __('Click here to return to your', 'before-and-after') . ' Goal.');
			unset($_SESSION['_return_to_goal_id']);
		}
		else {
			$new_goal_url  =  admin_url('post-new.php?post_type=b_a_goal&form_id=' . $post->ID);
			$new_goal_link =  ' ' . sprintf( '<a href="%s">%s</a>', $new_goal_url, __('Click here to use it in a new', 'before-and-after') . ' Goal.' );								 
		}

		// add our messages to the list and return
		$messages['b_a_form'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Form updated.', 'before-and-after' ) . $new_goal_link,
			2  => __( 'Custom field updated.', 'before-and-after' ),
			3  => __( 'Custom field deleted.', 'before-and-after' ),
			4  => __( 'Form updated. ', 'before-and-after' ) . $new_goal_link,
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Form restored to revision from %s', 'before-and-after' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Form published.', 'before-and-after' ) . $new_goal_link,
			7  => __( 'Form saved.', 'before-and-after' ) . $new_goal_link,
			8  => __( 'Form submitted.', 'before-and-after' ) . $new_goal_link,
			9  => sprintf(
				__( 'Form scheduled for: <strong>%1$s</strong>.', 'before-and-after' ),
				// translators: Publish box date format, see http://php.net/date
				date_i18n( __( 'M j, Y @ G:i', 'before-and-after' ), strtotime( $post->post_date ) )
			),
			10 => __( 'Form draft updated.', 'before-and-after' ) . $new_goal_link
		);

		if ( $post_type_object->publicly_queryable && 'b_a_form' === $post_type ) {
			$permalink = get_permalink( $post->ID );

			$view_link = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), __( 'View form', 'before-and-after' ) );
			$messages[ $post_type ][1] .= $view_link;
			$messages[ $post_type ][6] .= $view_link;
			$messages[ $post_type ][9] .= $view_link;

			$preview_permalink = add_query_arg( 'preview', 'true', $permalink );
			$preview_link = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), __( 'Preview form', 'before-and-after' ) );
			$messages[ $post_type ][8]  .= $preview_link;
			$messages[ $post_type ][10] .= $preview_link;
		}

		return $messages;
	}	
}
