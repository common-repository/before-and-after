<?php
class BA_Goal_Model
{
    /**
     * Holds the values to be used in the fields callbacks
     */
	const post_type = 'b_a_goal';
	private $root;
	private $plugin_title;

    /**
     * Start up
     */
    public function __construct($root)
    {
		$this->root = $root;
		$this->plugin_title = $root->plugin_title;
		$this->setup_custom_post_type();
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'wp_ajax_before_and_after_complete_goal', array( $this, 'ajax_complete_goal_by_id' ) );
		add_action( 'wp_ajax_nopriv_before_and_after_complete_goal', array( $this, 'ajax_complete_goal_by_id' ) );
		add_action( 'wp_ajax_before_and_after_reset_download_key', array( $this, 'ajax_reset_download_key' ) );
		add_action( 'wp_enqueue_scripts', array($this, 'register_cookie_script') );
		add_filter( 'the_content', array($this, 'single_goal_content_filter') );
		add_action( 'added_user_meta', array($this, 'unhide_goals_in_menu_editor'), 10, 4 );
		add_filter( 'manage_b_a_goal_posts_columns', array($this, 'output_custom_columns_head'), 10 );
		add_action( 'manage_b_a_goal_posts_custom_column', array($this, 'output_custom_columns_content'), 10, 2 );
		add_action( 'template_redirect', array($this, 'catch_goal_protected_pages') );
		add_filter( 'post_updated_messages', array($this, 'goal_updated_messages') );
	}

	function admin_init()
	{
		add_filter('manage_edit-goal_columns', array( $this, 'add_new_columns' ));
		add_filter('manage_edit-goals_columns', array( $this, 'add_new_columns' ));
		add_action('manage_goal_posts_custom_column', array( $this, 'manage_goal_columns' ), 10, 2);
	}

	function manage_goal_columns($column_name, $id) {
		global $wpdb;
		switch ($column_name) {
		case 'id':
			echo $id;
				break;

		case 'goal_shortcode':
/* 			$goal_id = intval(get_post_meta($id, 'goal_id', true));
			if ($goal_id > 0) {
				$my_goal = get_post($goal_id);
				$goal_title = apply_filters('the_title', $my_goal->post_title);
				$my_admin_url = admin_url( 'post.php?post=' . $goal_id . '&action=edit');
				$my_link = '<a class="row-title" href="' . $my_admin_url .'">' . htmlentities($goal_title) . '</a>';
				echo $my_link;
			} else {
				echo "";
			}
 */
			echo '[goal id="' . $id . '"]';
			break;

			case 'complete_goal_shortcode':
			echo '[complete_goal id="' . $id . '"]';
			break;

		default:
			break;
		} // end switch
	}

	function add_new_columns($gallery_columns) {
		$gc = $this->array_put_to_position($gallery_columns, __('Complete Goal Shortcode'), 2, 'complete_goal_shortcode');
		$gc = $this->array_put_to_position($gallery_columns, __('Goal Shortcode'), 2, 'goal_shortcode');
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
		// create the Goal custom post type
		$postType = array('name' => 'Goal', 'plural' => 'Goals', 'slug' => 'b_a_goals');
		$fields = array();
		$args = array(
			'supports' => array( 'title' ) // notably *not* thumbnail, comments, author, page attributes, etc
		);
		$args = apply_filters('b_a_goal_cpt_args', $args);
/* 		$fields[] = array('name' => 'before_goal_content', 'title' => 'Before', 'description' => 'Show this text to the user BEFORE they have completed this goal.', 'type' => 'textarea');
		$fields[] = array('name' => 'after_goal_content', 'title' => 'After', 'description' => 'Show this text to the user AFTER they have completed this goal.', 'type' => 'textarea');	 */
		$this->root->custom_post_types[] = new B_A_CustomPostType($postType, $fields, true, $args);

		// setup the meta boxes on the Add/Edit Goal screen
		add_action('init', array($this, 'remove_unneeded_metaboxes')); // remove some default meta boxes
		add_action( 'admin_menu', array( $this, 'add_meta_boxes' ) ); // add our custom meta boxes

		// add a hook to save the new values of our Goal settings whenever the Goal is saved
		add_action( 'save_post', array( $this, 'save_goal_settings' ), 1, 2 );
		add_action( 'save_post', array( $this, 'save_page_protection_settings' ), 1, 2 );

		// add a special link to the Row Actions menu of each Goal, which displays the visitors who have completed the goal
		add_filter('page_row_actions', array( $this, 'add_page_row_actions' ), 10, 2);
		add_filter('post_row_actions', array( $this, 'add_page_row_actions' ), 10, 2);

	}

	// adds a special link to the Row Actions menu, to display the visitors who have completed each goal
	function add_page_row_actions($actions, $page_object)
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
												 __('Duplicate This', 'before-and-after') . ' Goal',
												 __('Duplicate', 'before-and-after') );
			}
			$actions = apply_filters('before_and_after_admin_goals_row_actions', $actions, $page_object);
		}
		
		

		return $actions;
	}

	// saves the per-Goal settings. called whenever the Goal is saved
	function save_goal_settings()
	{
		global $post;

		// make sure  that the nonce matches and the user has permission to edit this goal
		if (!isset($_POST[ 'b_a_goal_settings_nonce' ]) || !wp_verify_nonce( $_POST[ 'b_a_goal_settings_nonce' ], 'b_a_goal_settings' ) ||
			!current_user_can( 'edit_post', $post->ID ) ||
			$post->post_type != self::post_type)
		{
			return;
		}

		$this->update_goal_setting_from_post($post->ID, 'before-action', '_goal_before_action');
		$this->update_goal_setting_from_post($post->ID, 'after-action', '_goal_after_action');
		$this->update_goal_setting_from_post($post->ID, 'before-values', '_goal_before_values');
		$this->update_goal_setting_from_post($post->ID, 'after-values', '_goal_after_values');

		// special: if the before-action is a contact form 7 or a gravity form, we need to save an extra meta value now so that we can query it later
		if ( $_POST[ 'before-action' ] == 'contact_form_7' && isset($_POST['before-values']['contact_form_7']) ) {
			$form_id = intval($_POST['before-values']['contact_form_7']);
			$goal_selector = 'cf7_' . $form_id;
			$ajax_refresh = !empty ( $_POST['before-values']['contact_form_7_ajax_refresh'] ) ? 1 : 0;
			update_post_meta($post->ID, 'contact_form_7_ajax_refresh', $ajax_refresh);

		} else if ( $_POST[ 'before-action' ] == 'gravity_form' && isset($_POST['before-values']['gravity_form']) ) {
			$form_id = intval($_POST['before-values']['gravity_form']);
			$goal_selector = 'gform_' . $form_id;
		} else {
			$goal_selector = 'none';
		}
		update_post_meta($post->ID, '_goal_selector', $goal_selector);

		// add download keys for protected downloads
		if ( $_POST[ 'after-action' ] == 'file_url' ) {
			$this->add_download_keys($post->ID);
		}
	}

	function add_download_keys($goal_id)
	{
		// if this Goal already has a download key do not overwrite it
		$cur_key = get_post_meta($goal_id, '_b_a_download_key', true);
		if ( !empty($cur_key) ) {
			return;
		}

		// this Goal does not have a download key, so generate and save a random download key now
		$new_download_key = $this->generate_download_key($goal_id);
		add_post_meta($goal_id, '_b_a_download_key', $new_download_key, true);
	}

	function reset_download_keys($goal_id)
	{
		// generate and save a random download key now
		$new_download_key = $this->generate_download_key($goal_id);
		update_post_meta($goal_id, '_b_a_download_key', $new_download_key);
	}
	
	function generate_download_key($goal_id)
	{
		$new_download_key = 'd_' . substr(md5(rand()), 0, 10) . substr(md5($goal_id), 0, 10);
		return apply_filters('before_and_after_generate_download_key', $new_download_key, $goal_id);
	}

	function save_page_protection_settings()
	{
		global $post;

		// return if b_a_protection fields are not present
		if ( empty($_POST['b_a_protection']) ) {
			return;
		}

		// make sure that the nonce matches and the user has permission to edit this page/post
		if ( empty($post)
			 || !in_array($post->post_type, array('post', 'page') )
			 || !current_user_can( 'edit_post', $post->ID )
			) {
			return;
		}

		// abort if there's nothing to save
		if ( empty($_POST['b_a_protection']) ) {
			return;
		}
		$enabled = ! empty($_POST['b_a_protection']['enabled']) 
				   ? 1
				   : 0;

		$goal_id = ! empty($_POST['b_a_protection']['goal_id'])
				   ? intval($_POST['b_a_protection']['goal_id'])
				   : 0;

		$bounce_action = ! empty($_POST['b_a_protection']['bounce_action'])
					     ? sanitize_text_field($_POST['b_a_protection']['bounce_action'])
					     : '';

		$bounce_url = ! empty($_POST['b_a_protection']['bounce_url'])
					  ? sanitize_text_field($_POST['b_a_protection']['bounce_url'])
					  : '';

		$new_value = compact('enabled', 'goal_id', 'bounce_action', 'bounce_url');
		update_post_meta( $post->ID, '_b_a_required_goal', $new_value );

		if ($enabled) {
			update_post_meta( $post->ID, '_b_a_protected', '1' );
		} else {
			delete_post_meta($post->ID, '_b_a_protected');
		}
	}



	// remove unneeded meta boxes from the Goal custom post type
	function remove_unneeded_metaboxes()
	{
		remove_post_type_support( self::post_type, 'editor' ); // note: may remove this later and replace with a custom field
		remove_post_type_support( self::post_type, 'excerpt' );
		remove_post_type_support( self::post_type, 'comments' );
		remove_post_type_support( self::post_type, 'author' );
		remove_post_type_support( self::post_type, 'page-attributes' );
		remove_post_type_support( self::post_type, 'thumbnail' );
	}

	// add our custom meta boxes to capture per-Goal settings
	function add_meta_boxes()
	{
		$before_meta_label = 'Before State - ' . __('What Your Visitors See BEFORE Completing The Goal');
		$after_meta_label = 'After State - ' . __('What Your Visitors See AFTER Completing The Goal');

		add_meta_box( 'goal_before', $before_meta_label, array( $this, 'display_before_meta_box' ), self::post_type, 'normal', 'high' );
		add_meta_box( 'goal_after', $after_meta_label, array( $this, 'display_after_meta_box' ), self::post_type, 'normal', 'high' );
		add_meta_box( 'goal_summary', 'Further Instructions', array( $this, 'display_summary_meta_box' ), self::post_type, 'normal', 'high' );
		add_meta_box( 'goal_conversions', 'Conversions', array( $this, 'display_conversions_meta_box' ), self::post_type, 'side', 'default' );
		add_meta_box( 'goal_shortcodes', 'Shortcodes', array( $this, 'display_shortcodes_meta_box' ), self::post_type, 'side', 'default' );
		add_meta_box( 'before_and_after_protection', 'Before &amp; After - Page Protection', array( $this, 'display_protection_meta_box' ), array('post', 'page'), 'normal', 'default' );
	}

	// creates the "Before" meta box
	function display_before_meta_box()
	{
		global $post;
		?>
		<div class="form-wrap">
			<?php wp_nonce_field( 'b_a_goal_settings', 'b_a_goal_settings_nonce', false, true ); ?>
			<p class="goal_state_explanation"><strong class="highlightx"><?php echo __('Before', 'before-and-after'); ?></strong> <?php echo __('the user has completed this goal, what content should they be shown?', 'before-and-after'); ?></p>

			<select name="before-action" id="before_action_select">
				<option value=""><?php echo __('Please select an option...', 'before-and-after'); ?></option>
				<option value="b_a_form" <?php echo $this->is_option_selected($post->ID, 'before-action', 'b_a_form'); ?>>Show a Form</option>
				<?php
					if(defined('WPCF7_VERSION')):
						$cf7_forms = $this->get_all_cf7_forms();
						if (is_array($cf7_forms) && count($cf7_forms) > 0):
				?>
				<option value="contact_form_7" <?php echo $this->is_option_selected($post->ID, 'before-action', 'contact_form_7'); ?>>Show a Contact Form 7 form</option>
				<?php endif; ?>
				<?php endif; ?>
				<?php
					if(class_exists('RGFormsModel')):
						$gravity_forms = RGFormsModel::get_forms( null, 'title' );
						if (is_array($gravity_forms) && count($gravity_forms) > 0):
				?>
				<option value="gravity_form" <?php echo $this->is_option_selected($post->ID, 'before-action', 'gravity_form'); ?>>Show a Gravity Form</option>
					<?php endif; // end "if has any gravity forms" ?>
				<?php endif; // end "if RGFormsModel exists" ?>
				<option value="free_text" <?php echo $this->is_option_selected($post->ID, 'before-action', 'free_text'); ?>>Show a message (HTML)</option>
				<option value="page_content" <?php echo $this->is_option_selected($post->ID, 'before-action', 'page_content'); ?>>Show the content from another page</option>
			</select>

			<div class="b_a_options">
				<?php
					$forms = get_posts(array(
						'post_type' => 'b_a_form',
						'post_status' => 'publish',
						'posts_per_page' => -1,
					));
					$selected_value = !empty($_REQUEST['form_id'])
									  ? intval($_REQUEST['form_id'])
									  : '';
				?>
				<div class="secondary_fields" id="before_options_b_a_form">
					<h4><?php echo __('Form Options', 'before-and-after'); ?></h4>
					<label for="before-b-a-form"><?php echo __('Select A Form:', 'before-and-after'); ?></label>
					<div class="secondary-option form-field">
						<select name="before-values[b_a_form]">
							<?php foreach($forms as $form): ?>
							<?php
								$form_title = !empty($form->post_title)
											  ? $form->post_title
											  : 'Form ' . $form->ID . ' (no title)';
							?>
							<?php echo $this->display_option($post->ID, 'before-values', 'b_a_form', $form_title, $form->ID, $selected_value); ?>
							<?php endforeach; ?>
						</select>
						&nbsp;
						<a style="display: none" href="#" data-href-template="<?php echo admin_url('post.php?post=_POST_ID_&action=edit&_return_to_goal_id=' . $post->ID); ?>" class="button edit_form_link"><?php echo __('Edit This', 'before-and-after'); ?> Form</a>
						&nbsp;
						<a href="<?php echo admin_url('post-new.php?post_type=b_a_form&_return_to_goal_id=' . $post->ID); ?>" class="button"><?php echo __('Create a New', 'before-and-after'); ?> Form</a>
					</div>
				</div>

				<?php
					if(defined('WPCF7_VERSION')):
						$cf7_forms = $this->get_all_cf7_forms();
						if (is_array($cf7_forms) && count($cf7_forms) > 0):
				?>
				<div class="secondary_fields" id="before_options_contact_form_7">
					<h4><?php echo __('Contact Form 7 Options', 'before-and-after'); ?></h4>
					<label for="before-cf7-form"><?php echo __('Select A Form:', 'before-and-after'); ?></label>
					<div class="secondary-option form-field">
						<select name="before-values[contact_form_7]">
							<?php foreach($cf7_forms as $cf7_form): ?>
							<?php echo $this->display_option($post->ID, 'before-values', 'contact_form_7', $cf7_form->post_title, $cf7_form->ID); ?>
							<?php endforeach; ?>
						</select>
					</div>

					<?php
						$checked = ( '1' == get_post_meta($post->ID, 'contact_form_7_ajax_refresh', true) )
								   ? 'checked="checked" '
								   : '';
					?>
					<div class="secondary-option form-field">
						<input type="hidden" name="before-values[contact_form_7_ajax_refresh]" value="0" />
						<label for="contact_form_7_ajax_refresh">
							<input type="checkbox" name="before-values[contact_form_7_ajax_refresh]" id="contact_form_7_ajax_refresh" value="1" <?php echo esc_attr($checked); ?> />
							<?php echo __('Refresh with AJAX', 'before-and-after'); ?>
							<p class="description"><?php echo __('Tip: Disable if experiencing issues.', 'before-and-after'); ?></p>
						</label>
					</div>

				</div>
					<?php endif; // end "if has any cf7 forms" ?>
				<?php endif; // end "is_defined(WPCF7_VERSION)" ?>
				<?php
					if(class_exists('RGFormsModel')):
						$gravity_forms = RGFormsModel::get_forms( null, 'title' );
						if (is_array($gravity_forms) && count($gravity_forms) > 0):
				?>
				<div class="secondary_fields" id="before_options_gravity_form">
					<h4><?php echo __('Gravity Form Options', 'before-and-after'); ?></h4>
					<input type="radio" name="before-action" id="before-gravity-form" value="gravity_form" <?php echo $this->is_radio_checked($post->ID, 'before-action', 'gravity_form')?> />
					<label for="before-gravity-form"><?php echo __('Show a Gravity Form:', 'before-and-after'); ?></label>
					<div class="secondary-option form-field">
						<select name="before-values[gravity_form]">
							<?php foreach($gravity_forms as $gravity_form): ?>
							<?php echo $this->display_option($post->ID, 'before-values', 'gravity_form', $gravity_form->title, $gravity_form->id); ?>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
					<?php endif; // end "if has any gravity forms" ?>
				<?php endif; // end "if RGFormsModel exists" ?>
				<div class="secondary_fields" id="before_options_page_content">
					<label for="before-page-content"><?php echo __('Select A Page:', 'before-and-after'); ?></label>
					<div class="secondary-option form-field">
						<?php
							$currentPageId = $this->get_goal_setting_value($post->ID, 'before-values', 'page_content');
							$args = array(  'name' => 'before-values[page_content]',
											'selected' => $currentPageId,
									);
							wp_dropdown_pages($args);
						?>
					</div>
				</div>
				<div class="secondary_fields" id="before_options_free_text">
					<h4><?php echo __('Message Options', 'before-and-after'); ?></h4>
					<label for="before-text"><?php echo __('Please enter the text to display:', 'before-and-after'); ?></label>
					<div class="secondary-option form-field">
						<textarea name="before-values[free_text]" rows="5"><?php echo htmlentities( $this->get_goal_setting_value($post->ID, 'before-values', 'free_text') ); ?></textarea>
						<p class="description"><?php echo __('Tip: HTML and shortcodes are allowed.', 'before-and-after'); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	// creates the "After" meta box
	function display_after_meta_box()
	{
		global $post;
		?>
		<div class="form-wrap">
			<p class="goal_state_explanation"><strong class="highlightx"><?php echo __('After', 'before-and-after'); ?></strong> <?php echo __('the user has completed this goal, what content should they be shown?', 'before-and-after'); ?></p>
			<select name="after-action" id="after_action_select">
				<option value=""><?php echo __('Please select an option...', 'before-and-after'); ?></option>
				<option value="redirect_page" <?php echo $this->is_option_selected($post->ID, 'after-action', 'redirect_page'); ?>>Redirect to another Page on your website</option>
				<option value="redirect_url" <?php echo $this->is_option_selected($post->ID, 'after-action', 'redirect_url'); ?>>Redirect to an URL</option>
				<option value="file_url" <?php echo $this->is_option_selected($post->ID, 'after-action', 'file_url'); ?>>Download a file</option>
				<option value="page_content" <?php echo $this->is_option_selected($post->ID, 'after-action', 'page_content'); ?>>Show the content from another page</option>
				<option value="free_text" <?php echo $this->is_option_selected($post->ID, 'after-action', 'free_text'); ?>>Show a message (HTML)</option>
			</select>
			<div class="b_a_options">
				<div class="secondary_fields" id="after_options_redirect_page">
					<h4><?php echo __('Redirect Options', 'before-and-after'); ?></h4>
					<label for="after-redirect-page"><?php echo __('Target Page:', 'before-and-after'); ?></label>
					<div class="secondary-option form-field">
						<?php
							$currentPageId = $this->get_goal_setting_value($post->ID, 'after-values', 'redirect_page');
							$args = array(  'name' => 'after-values[redirect_page]',
											'selected' => $currentPageId,
									);
							wp_dropdown_pages($args);
						?>
					</div>
				</div>
				<div class="secondary_fields" id="after_options_redirect_url">
					<h4><?php echo __('Redirect Options', 'before-and-after'); ?></h4>
					<label for="after-redirect-url"><?php echo __('Target URL:', 'before-and-after'); ?></label>
					<div class="secondary-option form-field">
						<input type="text" name="after-values[redirect_url]" value="<?php echo $this->get_goal_setting_value($post->ID, 'after-values', 'redirect_url')?>"/>
					</div>
				</div>
				<div class="secondary_fields" id="after_options_file_url">
					<h4><?php echo __('Download Options', 'before-and-after'); ?></h4>
					<label for="after-file-url"><?php echo __('Please enter the details for your file:', 'before-and-after'); ?></label>
					<div class="dependent-option form-field">
						<label for="after-file-url"><?php echo __('File URL:', 'before-and-after'); ?></label>
						<input type="text" name="after-values[file_url]" value="<?php echo $this->get_goal_setting_value($post->ID, 'after-values', 'file_url')?>" />
						<p class="description"><?php _e('A public URL where the file can be found.');?></p>
					</div>
					<div class="dependent-option form-field">
						<label for="after-file-url"><?php echo __('File Name:', 'before-and-after'); ?></label>
						<input type="text" name="after-values[file_name]" value="<?php echo $this->get_goal_setting_value($post->ID, 'after-values', 'file_name')?>" placeholder="download.pdf" />
						<p class="description"><?php _e('Leave blank to use the file name from the URL.'); ?></p>
					</div>
					<div class="dependent-option form-field">
						<label for="after-file-url"><?php echo __('Link Text', 'before-and-after'); ?></label>
						<input type="text" name="after-values[download_link_text]" value="<?php echo $this->get_goal_setting_value($post->ID, 'after-values', 'download_link_text', 'Please click here to complete your download.')?>" />
						<p class="description"><?php _e('The text to use for the download link.'); ?></p>
					</div>
					<div class="dependent-option form-field">
						<label for="after-file-url-no-key"><?php echo __('Send users who try to download without completing the Goal to this URL:', 'before-and-after'); ?></label>
						<input type="text" name="after-values[file_url_no_key]" value="<?php echo $this->get_goal_setting_value($post->ID, 'after-values', 'file_url_no_key')?>" />
						<p class="description"><?php _e('Usually an URL where this Goal displayed.'); ?></p>
					</div>
					<h4><?php echo __('Tools', 'before-and-after'); ?></h4>
					<div class="dependent-option form-field">
						<p>
							<button id="before_after_reset_download_key" type="button" data-goal-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce('b_a_reset_download_key_' . $post->ID) ); ?>"><?php _e('Reset Download Keys', 'before-and-after'); ?></button>
							<span id="before_after_reset_download_key_success_message" style="display: none"><?php _e('Download key reset.', 'before-and-after'); ?></span>
						</p>
						<?php if ( ! $this->root->is_pro() ): ?>
						<p class="description"><?php _e('Reset this Goal\'s download key. '); ?></p>
						<p class="description"><strong><?php _e('Warning: ', 'before-and-after'); ?></strong><?php _e('This will invalidate existing download links.', 'before-and-after'); ?> <a href="<?php echo esc_url( $url = $this->get_upgrade_url('personalized_links', 'edit_goal') ); ?>"><?php _e('Upgrade to', 'before-and-after'); ?> Before &amp; After Pro <?php _e('to prevent this in the future by using personalized download links.', 'before-and-after'); ?></a></p>
						<?php else: ?>
						<p class="description"><?php _e('Reset this Goal\'s download key. This will not affect existing download links.'); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<div class="secondary_fields" id="after_options_page_content">
					<label for="after-page-content"><?php echo __('Select A Page:', 'before-and-after'); ?></label>
					<div class="secondary-option form-field">
						<?php
							$currentPageId = $this->get_goal_setting_value($post->ID, 'after-values', 'page_content');
							$args = array(  'name' => 'after-values[page_content]',
											'selected' => $currentPageId,
									);
							wp_dropdown_pages($args);
						?>
					</div>
				</div>
				<div class="secondary_fields" id="after_options_free_text">
					<h4><?php echo __('Message Options', 'before-and-after'); ?></h4>
					<label for="after-text"><?php echo __('Please enter the text to display:', 'before-and-after'); ?></label>
					<div class="secondary-option form-field">
						<textarea name="after-values[free_text]" rows="5"><?php echo htmlentities( $this->get_goal_setting_value($post->ID, 'after-values', 'free_text') );?></textarea>
						<p class="description"><?php echo __('Tip: HTML and Shortcodes are allowed.', 'before-and-after'); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php

	}

	// creates the "Summary" meta box
	function display_summary_meta_box()
	{
		global $post;
		$before_action = $this->get_state_value($post->ID, 'before-action');
		$after_action = $this->get_state_value($post->ID, 'after-action');
		?>
		<div class="before_after_goal_instructions" id="instructions_no_selection">
			<p><?php echo __('Check back here once you\'ve setup your Goal.', 'before-and-after'); ?></p>
		</div>
		<div class="before_after_goal_instructions" id="instructions_b_a_form">
			<p><strong class="highlight"><?php echo __('Great news!', 'before-and-after'); ?></strong> <?php echo __('Your Goal will be completed automatically when the form is submitted, so no further action is needed.', 'before-and-after'); ?></p>
			<p><?php echo __('Once they have completed the form, your visitors will no longer see the Before state, and will instead see the After state.', 'before-and-after'); ?></p>
		</div>
		<div class="before_after_goal_instructions" id="instructions_contact_form_7">
			<p><strong class="highlight"><?php echo __('Great news!', 'before-and-after'); ?></strong> <strong>Before &amp; After</strong> <?php echo __('works automatically with', 'before-and-after'); ?> Contact Form 7, <?php echo __('so no further action is needed. This Goal will be completed when the form is submitted.', 'before-and-after'); ?></p>
			<p><?php echo __('Once they have completed the form, your visitors will no longer see the Before state, and will instead see the After state.', 'before-and-after'); ?></p>
		</div>
		<div class="before_after_goal_instructions" id="instructions_gravity_forms">
			<p><strong class="highlight"><?php echo __('Great news!', 'before-and-after'); ?></strong> <strong>Before &amp; After</strong> <?php echo __('works automatically with', 'before-and-after'); ?> Gravity Forms, <?php echo __('so no further action is needed. This Goal will be completed when the form is submitted.', 'before-and-after'); ?></p>
			<p><?php echo __('Once they have completed the form, your visitors will no longer see the Before state, and will instead see the After state.', 'before-and-after'); ?></p>
		</div>
		<div class="before_after_goal_instructions" id="instructions_page_content">
			<div>
				<p><strong class="highlight"><?php echo __('Important:', 'before-and-after'); ?></strong></p>
				<?php
					$cg_shortcode = sprintf('&#91;complete_goal id=&quot;%s&quot;&#93;', $post->ID);
					$cg_shortcode_no_enc = sprintf('[complete_goal id="%s"]', $post->ID);
					$cg_shortcode_min_width = (strlen($cg_shortcode_no_enc) + 2) . 'ch';
				?>
				<p><?php echo __('To allow your users to complete this Goal, insert a ', 'before-and-after'); ?><strong>Complete Goal Custom Block</strong> <?php echo __('or add the shortcode', 'before-and-after'); ?> <input type="text" class="code" value="<?php echo $cg_shortcode;?>" style="min-width: <?php echo $cg_shortcode_min_width; ?>; text-align: center;" /> <?php echo __('to any page or post on your website.', 'before-and-after'); ?></p>
				<p><?php echo __('When one of your visitors encounters that Custom Block or Shortcode, the Goal will be automatically marked as completed for them. They will no longer see the Before state, and will now see the After state.', 'before-and-after'); ?></p>
				<p><strong><?php echo __('Tip:', 'before-and-after'); ?></strong> <?php echo __('We suggest placing the Custom Block or Shortcode on the final page of a form or wizard, or on another page such as your Terms of Service page which you are requiring visitors to read.', 'before-and-after'); ?></p>
			</div>
		</div>
		<div class="before_after_goal_instructions" id="instructions_free_text">
			<div>
				<p><strong class="highlight"><?php echo __('Important:', 'before-and-after'); ?></strong></p>
				<?php
					$cg_shortcode = sprintf('&#91;complete_goal id=&quot;%s&quot;&#93;', $post->ID);
					$cg_shortcode_no_enc = sprintf('[complete_goal id="%s"]', $post->ID);
					$cg_shortcode_min_width = (strlen($cg_shortcode_no_enc) + 2) . 'ch';
				?>
				<p><?php echo __('To allow your users to complete this Goal, insert a ', 'before-and-after'); ?><strong>Complete Goal Custom Block</strong> <?php echo __('or add the shortcode', 'before-and-after'); ?> <input type="text" class="code" value="<?php echo $cg_shortcode;?>" style="min-width: <?php echo $cg_shortcode_min_width; ?>; text-align: center;" /> <?php echo __('to any page or post on your website.', 'before-and-after'); ?></p>
				<p><?php echo __('When one of your visitors encounters that Custom Block or Shortcode, the Goal will be automatically marked as completed for them. They will no longer see the Before state, and will now see the After state.', 'before-and-after'); ?></p>
				<p><strong><?php echo __('Tip:', 'before-and-after'); ?></strong> <?php echo __('We suggest placing the Custom Block or Shortcode on the final page of a form or wizard, or on another page such as your Terms of Service page which you are requiring visitors to read.', 'before-and-after'); ?></p>
			</div>
		</div>
		<?php
	}

	function get_goal_shortcode_textbox($goal_id)
	{
		$textarea_tmpl = '<div class="gp_code_example_wrapper"><textarea rows="1" class="gp_code_example">%s</textarea></div>';
		$goal_shortcode = sprintf('[goal id="%d"]', $goal_id);
		return sprintf($textarea_tmpl, $goal_shortcode);
	}

	function get_complete_goal_shortcode_textbox($goal_id)
	{
		$textarea_tmpl = '<div class="gp_code_example_wrapper"><textarea rows="1" class="gp_code_example">%s</textarea></div>';
		$complete_goal_shortcode = sprintf('[complete_goal id="%d"]', $goal_id);
		return sprintf($textarea_tmpl, $complete_goal_shortcode);
	}

	/* Displays a meta box with the shortcodes to display and complete the current goal */
	function display_shortcodes_meta_box()
	{
		global $post;
		echo "<h4>" . __('Displaying This Goal', 'before-and-after') . "</h4>";
		echo "<p>" . __('Add this shortcode to the page where you would like to display this goal:', 'before-and-after') . "</p>";
		echo $this->get_goal_shortcode_textbox($post->ID);
		echo "<p>" . __('You can also add this Goal to any Page or Post by adding a Goal block in the Gutenberg editor.', 'before-and-after') . "</p>";

		echo "<h4>" . __('Completing This Goal', 'before-and-after') . "</h4>";
		echo "<p>" . __('Add this shortcode to any page to manually mark the goal as complete (e.g., your Thank You page):', 'before-and-after') . "</p>";
		echo $this->get_complete_goal_shortcode_textbox($post->ID);
		echo "<p>" . __('You can also complete this Goal by adding a Complete Goal block to any Page or Post using the Gutenberg editor.', 'before-and-after') . "</p>";
		echo "<p>" . __('Tip: If you\'re using a ', 'before-and-after') . 'Before &amp; After Form, a Gravity Form, or a CF7 Form, ' . __('this Goal will be automatically completed when the form is submitted.', 'before-and-after') . "</p>";
	}

	/* Displays a meta box with the shortcodes to display and complete the current goal */
	function display_conversions_meta_box()
	{
		global $post;
		$meta_box_content = apply_filters('before_and_after_conversions_meta_box_content', '', $post->ID);
		if ( empty($meta_box_content) && !$this->root->is_pro() ) {
			$url = $this->get_upgrade_url('conversions', 'edit_goal');
			printf( '<p style="color: #a00">%s</p>', __('Conversions are not tracked in the free plugin.', 'before-and-after') );
			print '<div style="text-align: center; margin-top: 20px">';
			printf( '<p><a class="button button-primary" href="%s" target="_blank">%s</a></p>',
					$url,
					__('Upgrade to', 'before-and-after') . ' Before &amp; After Pro ' );
			printf( '<p style="font-style:italic;">%s</p>',  __('and start tracking your conversions!', 'before-and-after') );
			print '</div>';
		} else {
			echo $meta_box_content;
		}
	}

	/* Displays a meta box allowing the user to protect the current page with a Goal*/
	function display_protection_meta_box()
	{
		// init
		global $post;
		$has_b_a_hook = get_post_meta($post->ID, '_b_a_required_goal', true);
		$enabled = !empty($has_b_a_hook) && !empty($has_b_a_hook['enabled'])
				   ? 1
				   : 0;

		$current_goal_id = !empty($has_b_a_hook) && !empty($has_b_a_hook['goal_id'])
						   ? intval($has_b_a_hook['goal_id'])
						   : 0;

		$bounce_url = !empty($has_b_a_hook) && !empty($has_b_a_hook['bounce_url'])
					  ? sanitize_text_field($has_b_a_hook['bounce_url'])
					  : '';

		$bounce_action = !empty($has_b_a_hook) && !empty($has_b_a_hook['bounce_action'])
					  ? $has_b_a_hook['bounce_action']
					  : 'redirect';
		
		// output form inputs
		echo '<p><em>Use Page Protection to require your visitors to complete one of your Goals before they can access this page.</em></p>';

		echo '<p>';
		$checked = $enabled ? 'checked="checked"' : '';
		echo '<input type="hidden" name="b_a_protection[enabled]" value="0" />';
		printf( '<label for="b_a_protection_enabled"><input type="checkbox" id="b_a_protection_enabled" name="b_a_protection[enabled]" value="1" %s />Enable Page Protection</label>', esc_attr($checked) );
		echo '</p>';

		echo '<div id="b_a_page_protection_advanced_options" style="">';

		echo '<p>';
		printf( '<input type="hidden" name="b_a_protection[goal_id]" value="%d" />', $current_goal_id );
		echo '<label for="b_a_protection_goal_id">Require Visitors To Complete This Goal Before Viewing This Page:</label><br />';
		$this->generate_post_select( 'b_a_protection[goal_id]', 'b_a_protection_goal_id', self::post_type, $current_goal_id);
		echo '</p>';

		printf( '<h4>%s:</h4>', esc_html( __('For Visitors Who Have Not Completed The Goal', 'before-and-after') ) );
		
		$checked = ( 'show_goal' == $bounce_action )
				   ? 'checked="checked"'
				   : '';
		printf( '<p><label><input type="radio" name="b_a_protection[bounce_action]" value="show_goal" %s />%s</label></p>',
				$checked,
				esc_html( __('Show the Goal on this page instead of the content', 'before-and-after') ) );

		$checked = ( empty($bounce_action) || 'redirect' == $bounce_action )
				   ? 'checked="checked"'
				   : '';
		printf( '<p><label><input type="radio" name="b_a_protection[bounce_action]" value="redirect" %s />%s</label></p>',
				$checked,
				esc_html( __('Redirect the visitor to a different URL', 'before-and-after') ) );

		echo '<div id="b_a_protection_bounce_url_wrapper">';
		echo '<label for="b_a_protection_bounce_url">Send users who have not completed the goal to this URL:</label><br />';
		printf( '<input type="hidden" name="b_a_protection[bounce_url]" value="%s" />', esc_url($bounce_url) );
		printf( '<input class="widefat" type="text" id="b_a_protection_bounce_url" name="b_a_protection[bounce_url]" value="%s" />', esc_url($bounce_url) );
		echo '</div>';

		echo '</div>';

		echo '<script type="text/javascript">';
		echo 'jQuery(function () {
				var update_opts = function () {
					var enabled = jQuery("#b_a_protection_enabled").prop("checked");
					var opts = jQuery("#b_a_page_protection_advanced_options").find("select, input[type=\"text\"], input[type=\"radio\"]");
					var labels = jQuery("#b_a_page_protection_advanced_options").find("label, h4");
					if ( enabled ) {
						labels.css("color", "black");
						opts.removeAttr("disabled");
					}
					else {
						labels.css("color", "gray");
						opts.prop("disabled", "disabled");
					}
				};
				jQuery("#b_a_protection_enabled").on("click change", update_opts);
				update_opts();

				var update_bounce_action = function () {
					var enabled = jQuery("#b_a_protection_enabled").prop("checked");
					if ( ! enabled ) {
						return;
					}
					// toggle the bounce_url field based on radio selection					
					var bounce_action = jQuery(\'#b_a_page_protection_advanced_options input[name="b_a_protection[bounce_action]"]:checked\');
					if ( "redirect" == bounce_action.val() ) {
						jQuery("#b_a_protection_bounce_url_wrapper").show();
					}
					else {
						jQuery("#b_a_protection_bounce_url_wrapper").hide();
					}
					//console.log(bounce_action.val());
				};
				jQuery("#b_a_page_protection_advanced_options").on("click change", "input", update_bounce_action);
				update_bounce_action();
		});';
		echo '</script>';
	}

	
	function get_upgrade_url($campaign = 'freeplugin', $medium = 'general')
	{
		global $wp_version;
		$base_url = 'https://goldplugins.com/special-offers/upgrade-to-before-and-after-pro/?';
		
		$params = array(
			'utm_source' => 'WordPress',
			'utm_campaign' => $campaign,
			'utm_medium' => $medium,
			'wp_version' => $wp_version,
			'plugin_version' => $this->get_plugin_version(),
		);
		// TODO: add days since install (buckets?)
		
		return $base_url . http_build_query($params);		
	}	
	
	function get_plugin_version()
	{
		$cached_val = wp_cache_get( 'before_and_after_free_version' );
		if ( !empty($cached_val) ) {
			return $cached_val;
		}
		
		$all = get_plugins();
		if ( empty($all['before-and-after/before-and-after.php']) ) {
			return '';
		}

		if ( empty($all['before-and-after/before-and-after.php']['Version']) ) {
			return '';
		}
		
		$version = $all['before-and-after/before-and-after.php']['Version'];
		wp_cache_set( 'before_and_after_free_version', $version );
		return $version;
	}

	function generate_post_select($select_id, $select_name, $post_type, $selected = 0, $include_select_all_option = false)
	{
        $post_type_object = get_post_type_object($post_type);
        $label = $post_type_object->label;
		$args = array(
			'post_type'=> $post_type,
			'post_status'=> 'publish',
			'suppress_filters' => false,
			'posts_per_page'=> -1
		);
        $posts = get_posts($args);

        echo '<select name="'. $select_id .'" id="'.$select_id.'">';
		if ($include_select_all_option) {
			echo '<option value = "" >' . __('All') . $label .' </option>';
		}
        foreach ($posts as $post) {
            echo '<option value="', $post->ID, '"', $selected == $post->ID ? ' selected="selected"' : '', '>', $post->post_title, '</option>';
        }
        echo '</select>';
    }
	
	function get_goal_content($goal_id)
	{
		global $post;

		// sanitize $goal_id to an int
		$goal_name = 'Goal_ID_' . $goal_id;

		// start making a list of classes to wrap our goal with
		$goal_wrapper_classes = array(
			'before-and-after-goal', 
			sprintf('before-and-after-goal-%d', $goal_id)
		);
		
		// Check if the Goal has been completed. If it has, we'll show the Before content. If not, we'll show the After content
		if ( !$this->wasGoalCompleted($goal_name) ) {
			$value = $this->root->Shortcodes->process_action($goal_id, 'before');
			$goal_content = apply_filters('b_a_before_content', $value, $goal_id);
		}
		else {
			$value = $this->root->Shortcodes->process_action($goal_id, 'after');			
			$goal_content = apply_filters('b_a_after_content', $value, $goal_id);
			$goal_wrapper_classes[] = 'before-and-after-goal-complete';
		}

		// apply a filter and then combine the resulting list of classes
		$goal_wrapper_classes = apply_filters('b_a_after_goal_wrapper_classes', $goal_wrapper_classes, $goal_id);		
		$wrapper_class_list = implode(' ', $goal_wrapper_classes);

		// wrap the goal content in its wrapper, apply a filter, and return it
		$wrapper_html = '<div class="%s">%s</div>';
		$goal_html = sprintf($wrapper_html, $wrapper_class_list, $goal_content);		
		return apply_filters('b_a_goal_html', $goal_html, $goal_id);			
	}
	
	/*
	 * Returns true/false, indicating whether the specified goal has been completed
	 * based on the database / cookies / session.
	 *
	 * NOTE: Whichever method (database, cookies, session) is found will update the others.
	 *
	 * @param	string	$goalName	Required. The name of the goal to check.
	 * @param	int		$goalId		Optional. The ID of the goal to check. Needed to check
	 *								cookies as well as session. Cookie check will be ignored
	 *								if omitted, but session check will remain in place.
	 */
	public function wasGoalCompleted($goalName, $goalId = 0)
	{
		$goal_completed = false;

		if ($goalId == 0) {
			$goalId = filter_var($goalName, FILTER_SANITIZE_NUMBER_INT);
		}

		// check database (post meta) first
		if ( $goalId > 0 && $this->check_goal_complete_in_post_meta($goalId) ) {

			// update cookies if needed
			if ( !$this->check_cookie_for_goal($goalId) ) {
				$this->store_goal_completion_cookies($goalName, $goalId);
			}

			// update session if needed
			if ( !$this->check_goal_complete_in_session($goalName) ) {
				$this->mark_goal_complete_in_session($goalName);
			}
			$goal_completed = true;
		}
		// if not found in goal's post meta, check in cookies
		else if ( $goalId > 0 && $this->check_cookie_for_goal($goalId) ) {

			// mark complete in post meta
			$this->mark_goal_complete_in_post_meta($goalId);

			// update session if needed
			if ( !$this->check_goal_complete_in_session($goalName) ) {
				$this->mark_goal_complete_in_session($goalName);
			}

			$goal_completed = true;
		}
		// if not found in the cookies, check $_SESSION
		else if ( $this->check_goal_complete_in_session($goalName) ) {

			// mark complete in post meta and cookies
			$this->mark_goal_complete_in_post_meta($goalId);
			$this->store_goal_completion_cookies($goalName, $goalId);
			$goal_completed = true;
		}

		// allow the user to modify the result with a filter, then return it
		return apply_filters('b_a_was_goal_complete', $goal_completed, $goalName, $goalId);
	}

	/* Place a session variable that marks the current visitor as having completed the specified goal
	 * Note: this function does not support Conversion logging or server side goal completion.
	 * Use completeGoalById for that (names were only used in v1, before these features were added)
	*/
	public function completeGoal($goalName)
	{
		$alreadyCompleted = $this->wasGoalCompleted($goalName);
		if (!$alreadyCompleted) {
			$params = array(
				'goal_name' => $goalName,
				'goal_id' => false,
			);
			do_action('b_a_goal_complete', $params);
		}
		$this->store_goal_completion_cookies($goalName);
		return '';
	}

	/* Place a session variable that marks the current visitor as having completed the specified goal
	 * Note: Will also log a conversion (Pro only)
	*/
	function completeGoalById($goalId, $goal_complete_url = '')
	{
		$goalName = 'Goal_ID_' . $goalId;
		$alreadyCompleted = $this->wasGoalCompleted($goalName, $goalId);
		$goal_after_action = get_post_meta($goalId, '_goal_after_action', true);

		if (!$alreadyCompleted) {
			$params = array(
				'goal_name' => $goalName,
				'goal_id' => $goalId,
				'goal_complete_url' => $goal_complete_url,
				'goal_after_action' => $goal_after_action
			);
			do_action('b_a_goal_complete', $params);
			do_action('b_a_goal_complete_' . $goalId, $params);
		}

		// mark this goal as completed (in the session and in post meta)
		$this->store_goal_completion_cookies($goalName, $goalId);

		$this->mark_goal_complete_in_post_meta($goalId);
		return '';
	}

	// saves the value of a POST variable to the database
	// Important: WILL save blank values
	private function update_goal_setting_from_post($post_id, $request_key, $meta_key)
	{
		$post_val = isset($_POST[$request_key])
					? $_POST[$request_key]
					: '';

		return update_post_meta($post_id, $meta_key, $post_val);
	}

	// returns the "checked" attribute for a radio button, depending on whether the setting specified matches the test value specified
	// returns either the string 'checked="checked"', or an empty string ''. These are intended to be used inside an <input type="radio" /> HTML tag
	private function is_radio_checked($goal_id, $setting_name, $setting_value)
	{
		$val = '';
		if ($setting_name == 'before-action') {
			$val = get_post_meta($goal_id, '_goal_before_action', true);
		}
		else if ($setting_name == 'after-action') {
			$val = get_post_meta($goal_id, '_goal_after_action', true);
		}

		if ( !empty($val) && ($val == $setting_value) ) {
			return 'checked="checked"';
		} else {
			return '';
		}
	}

	// returns the "selected" attribute for an <option>, depending on whether the setting specified matches the test value specified
	// returns either the string 'selected="selected"', or an empty string ''. This is intended to be used inside an <option> HTML tag
	private function is_option_selected($goal_id, $setting_name, $setting_value)
	{
		//echo "Goal: $goal_id <br>Setting: $setting_name <br>Value: $setting_value <br>";
		$val = '';
		if ($setting_name == 'before-action') {
			// special case: if a form_id GET param exists, 
			//  			 override the before action to always be B_A_Form
			$val = !empty($_REQUEST['form_id'])
				   ? 'b_a_form'
				   : get_post_meta($goal_id, '_goal_before_action', true);			
		}
		else if ($setting_name == 'after-action') {
			$val = get_post_meta($goal_id, '_goal_after_action', true);
		}

		if ( !empty($val) && ($val == $setting_value) ) {
			return 'selected="selected"';
		} else {
			return '';
		}
	}

	private function get_state_value($goal_id, $setting_name, $default_value = '')
	{
		$val = '';
		if ($setting_name == 'before-action' || $setting_name == 'before') {
			$val = get_post_meta($goal_id, '_goal_before_action', true);
		}
		else if ($setting_name == 'after-action' || $setting_name == 'after') {
			$val = get_post_meta($goal_id, '_goal_after_action', true);
		}
		return (false != $val)
			   ? $val
			   : $default_value;
	}

	// returns a formatted HTML <option> tag with the specified settings
	private function display_option($goal_id, $setting_location, $setting_key, $option_text, $option_value = '', $selected_value = '')
	{
		$val = !empty($selected_value)
			   ? $selected_value
			   : $this->get_goal_setting_value($goal_id, $setting_location, $setting_key);

		if($option_value == '') {
			$option_value = $option_text;
		}
		$selected = false;
		if($option_value == $val) {
			$selected = true;
		}
		$html = '<option value="' . htmlspecialchars($option_value) . '"';
		if ($selected) {
			$html .= ' selected="selected"';
		}
		$html .= '>' . htmlspecialchars($option_text) . '</option>';
		return $html;
	}

	public function get_goal_setting_value($goal_id, $setting_location, $setting_key, $default_value = '', $allow_empty = true)
	{
		if ($setting_location == 'before-values') {
			$vals = get_post_meta($goal_id, '_goal_before_values', true);
		}
		else if ($setting_location == 'after-values') {
			$vals = get_post_meta($goal_id, '_goal_after_values', true);
		}

		if( $vals && is_array($vals) && isset($vals[$setting_key]) && ( $allow_empty || !empty($vals[$setting_key]) ) ) {
			return $vals[$setting_key];
		} else {
			return $default_value;
		}
	}

	private function get_all_cf7_forms()
	{
		return get_posts(
			array(
				'post_type' => 'wpcf7_contact_form',
				'posts_per_page' => -1,
				'nopaging' => true,
				'orderby' => 'title',
				'order' => 'ASC'
			)
		);
	}

	/*
	 * Sets a cookie and a session variable marking the specified goal as complete.
	 *
	 * @param	string	$goalName	Required. The name of the goal to check.
	 * @param	int		$goalId		Optional. The ID of the goal to check. Needed to check
	 *								cookies as well as session. Cookie check will be ignored
	 *								if omitted, but session check will remain in place.
	 */
	function store_goal_completion_cookies($goalName, $goalId = 0)
	{
		// mark this goal as completed in the session
		$this->mark_goal_complete_in_session($goalName);

		// back in the day, we didn't have goal IDs. So if one wasn't passed in,
		// try to figure it out by stripping out the non-integer chars
		if ($goalId == 0) {
			$goalId = filter_var($goalName, FILTER_SANITIZE_NUMBER_INT);
		}

		// if we found a valid goal ID, store a cookie marking it complete
		if ($goalId > 0)
		{
			if ( !headers_sent() ) {
				// we haven't set the headers yet, so we can simply
				// set the cookie in the normal fashion
				$this->save_cookie_for_goal($goalId);
			}
			else {
				// it looks like the headers have already been sent, so
				// we'll have to make an AJAX call after the page loads
				// in order to set the cookies safely
				$this->set_cookie_for_goal_on_page_load($goalId);
			}
		}
	}

	/*
	 * Stores a $_SESSION key marking the specified goal as complete
	 *
	 * Used in conjunction with check_goal_complete_in_session()
	 *
	 * @param	string	$goalName	Required. The name of the goal to set.
	 *								Usually, they are: 'Goal_ID_' . $goalId
	 */
	function mark_goal_complete_in_session($goalName)
	{
		$sessionKey = 'goal_' . md5($goalName);
		$sessionValue = 'goal_completed_' . md5($goalName);
		$_SESSION[$sessionKey] = $sessionValue;
	}

	/*
	 * Checks the $_SESSION to see if the specified goal is marked complete.
	 * NOTE: even if not marked as complete in the session, a goal can be marked
	 * as completed by a cookie.
	 *
	 * @param	string	$goalName	Required. The name of the goal to set.
	 *								Usually, they are: 'Goal_ID_' . $goalId
	 */
	function check_goal_complete_in_session($goalName)
	{
		$sessionKey = 'goal_' . md5($goalName);
		$sessionValue = 'goal_completed_' . md5($goalName);
		return (isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] == $sessionValue);
	}

	/*
	 * Calculates an unique hash (md5) for this user based on their IP
	 * and user agent string.
	 *
	 * @return string md5 hash specific to the current user
	 */
	function get_browser_hash()
	{
		return md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
	}

	/*
	 * Checks the goal's post meta to see if the specified goal is marked complete.
	 *
	 * @param int $goalId The ID of the goal to set (aka post ID).
	 * @return bool true if goal was completed, false if not.
	 */
	function check_goal_complete_in_post_meta($goalId)
	{
		$browser_hash = $this->get_browser_hash();
		$meta_key = 'b_a_goal_complete_' . $browser_hash;
		$found_meta = get_post_meta($goalId, $meta_key, false);
		return !empty($found_meta);
	}


	/*
	 * Saves a custom field to the goal, marking it as complete.
	 * Also stores the timestamp and user agent. Goals can be completed
	 * more than once.
	 *
	 * @return string md5 hash specific to the current user
	 */
	function mark_goal_complete_in_post_meta($goalId)
	{
		$browser_hash = $this->get_browser_hash();
		$meta_key = 'b_a_goal_complete_' . $browser_hash;
		$meta_value = array(
			'timestamp' => date('U'),
			'ip' => $_SERVER['REMOTE_ADDR'],
			'ua' => $_SERVER['HTTP_USER_AGENT'],
		);
		add_post_meta($goalId, $meta_key, $meta_value, false); // non-unique
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log("ba_server_side_cookie saved: {$_SERVER['REMOTE_ADDR']} {$_SERVER['HTTP_USER_AGENT']} :  $browser_hash");
		}
	}

	/*
	 * Enqueues Javascript which will set a cookie to mark the goal as complete
	 * upon the next page load (via AJAX).
	 *
	 * @param	int		$goalId		Required. The ID of the goal to complete.
	 */
	function set_cookie_for_goal_on_page_load($goalId)
	{
		$localize_vars = array(
			'before_and_after_user_vars' => array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'goal_to_complete' => $goalId,
				'complete_nonce' => wp_create_nonce('b_a_complete_goal_' . $goalId)
			)
		);
		$this->enqueue_cookie_script( $localize_vars );
	}

	/*
	 * Resets the download key for a specified Goal
	 *
	 * All params passed via $_REQUEST.
	 *
	 * @param	integer	$_REQUEST['goal_id']	Required. The goal ID to mark as complete.
	 * @param	string	$_REQUEST['nonce']	Required. Wordpress nonce, created via
	 *										wp_create_nonce('b_a_complete_goal_' . $goal_id)
	 *
	 */
	function ajax_reset_download_key()
	{
		// check for required params
		if (empty($_REQUEST['goal_id']) || intval($_REQUEST['goal_id']) == 0 || empty($_REQUEST['nonce']) ) {
			echo "FAIL";
			wp_die();
		} else {
			$goal_id = intval($_REQUEST['goal_id']);
		}

		// verify_nonce
		if ( !wp_verify_nonce( $_REQUEST['nonce'], 'b_a_reset_download_key_' . $goal_id ) ) {
			echo "FAIL";
			wp_die();
		}
		
		// reset the download key
		// @TODO: reset download key
		$this->reset_download_keys($goal_id);
		
		// return safely
		echo 'OK';
		wp_die();
	}

	/*
	 * Sets a cookie marking the specified goal as complete.
	 *
	 * Intended to be used via AJAX. Its easier to set cookies	via AJAX, as
	 * there are no worries about echoing headers after output has started.
	 *
	 * All params passed via $_REQUEST.
	 *
	 * @param	integer	$_REQUEST['goal_id']	Required. The goal ID to mark as complete.
	 * @param	string	$_REQUEST['nonce']	Required. Wordpress nonce, created via
	 *										wp_create_nonce('b_a_complete_goal_' . $goal_id)
	 *
	 */
	function ajax_complete_goal_by_id()
	{
		// check for required params
		if (empty($_REQUEST['goal_id']) || intval($_REQUEST['goal_id']) == 0 || empty($_REQUEST['nonce']) ) {
			echo "FAIL";
			wp_die();
		} else {
			$goal_id = intval($_REQUEST['goal_id']);
		}

		// verify_nonce
		if ( !wp_verify_nonce( $_REQUEST['nonce'], 'b_a_complete_goal_' . $goal_id ) ) {
			echo "FAIL";
			wp_die();
		}

		// set cookie marking this goal as complete
		$this->save_cookie_for_goal($goal_id);

		// return safely
		echo 'OK';
		wp_die();
	}

	function save_cookie_for_goal($goal_id)
	{
		$cookieName = $this->get_cookie_name_for_goal($goal_id);
		$cookieValue = $this->get_cookie_value_for_goal($goal_id);
		$expiration_time = time() + (60 * 60 * 24 * 30); // 30 days
		setcookie($cookieName, $cookieValue, $expiration_time, '/');
	}

	function check_cookie_for_goal($goal_id)
	{
		$cookieName = $this->get_cookie_name_for_goal($goal_id);
		$cookieValue = $this->get_cookie_value_for_goal($goal_id);
		return ( !empty($_COOKIE[$cookieName]) && strcmp($_COOKIE[$cookieName], $cookieValue) === 0 );
	}

	function delete_cookie_for_goal($goal_id)
	{
		$cookieName = $this->get_cookie_name_for_goal($goal_id);
		$expiration_time = time() - 3600; // set expiration in past so cookie expires on next page load
		unset($_COOKIE[$cookieName]);
		setcookie($cookieName, '', $expiration_time, '/');
	}

	function get_cookie_name_for_goal($goal_id)
	{
		$goalName = sprintf('Goal_ID_%d', $goal_id);
		return sprintf('b_a_a_g_%s_c', substr(md5($goalName), 0, 10));
	}

	function get_cookie_value_for_goal($goal_id)
	{
		return substr( md5( 'goal_completed_' . md5($goal_id) . get_site_url() ), 10, 12);
	}

	function register_cookie_script()
	{
		wp_register_script(
			'before_and_after-cookies',
			plugins_url('../assets/js/ba-cookies.js', __FILE__),
			array( 'jquery' ),
			false,
			true
		);
	}

	function enqueue_cookie_script( $localize_vars = array() )
	{
		wp_enqueue_script( 'before_and_after-cookies' );
		if ( !empty($localize_vars) ) {
			foreach ($localize_vars as $key => $value) {
				wp_localize_script('before_and_after-cookies', $key, $value);
			}
		}
	}

	function single_goal_content_filter($content)
	{
		if ( empty($this->in_widget) && empty($this->root->in_widget) && get_post_type() == 'b_a_goal' ) {
			global $post;
			$sc = sprintf('[goal id="%d"]', $post->ID);
			return do_shortcode($sc);
		}
		return $content;
	}

	/*
	 * Shows Before & After Goals shown by default on the menu editor screen.
	 *
	 * Background:
	 *
	 * Starting with WordPress 3.0, Custom Post Types are hidden from the menu
	 * editor, but only when the user has never visited the menu editor screen
	 * before. This is caused by the odd way WordPress initializes the list of
	 * metaboxes to show on this screen (see wp_initial_nav_menu_meta_boxes).
	 *
	 * Because there is not an appropriate filter to do so, we'll wait until
	 * this value is first saved, and remove Before & After goals from the list
	 * of hidden metaboxes. This way only the initial setting is affected - if
	 * a user explicitly hides our Goals they will remain hidden.
	 */
	function unhide_goals_in_menu_editor($mid, $object_id, $meta_key, $meta_value = '')
	{
		if ($meta_key == 'metaboxhidden_nav-menus') {
			$old_value = $meta_value;
			if(($key = array_search('add-post-type-b_a_goal', $meta_value)) !== false) {
				unset($meta_value[$key]);
				update_metadata('user', $object_id, $meta_key, $meta_value, $old_value);
			}
		}
	}

	function output_custom_columns_head($defaults)
	{
		// create array of custom columns to  insert into Goals post list
		$cols_to_add = array();
		$cols_to_add["b_a_goal_shortcode"] = __('Shortcode', 'before-and-after');
		$cols_to_add = apply_filters('before_and_after_admin_goals_list_columns', $cols_to_add, $defaults);

		// insert our custom columns at the 3rd position
		$defaults =
			array_slice($defaults, 0, 2, true) +
			$cols_to_add +
			array_slice($defaults, 2, count($defaults)-2, true);
		return apply_filters('b_a_admin_columns_head', $defaults);
	}

	function output_custom_columns_content($column_name, $post_ID)
	{
		if ($column_name == 'b_a_goal_shortcode') {
			$my_shortcode = sprintf('[goal id="%d"]', $post_ID);
			printf('<textarea rows="1" onclick="this.select();" class="gp_code" style="height: 2em; margin-top: 10px; max-width:100%%">%s</textarea>', $my_shortcode);
		}
		do_action('b_a_admin_columns_content', $column_name, $post_ID);
	}

	function goal_id_completed($goal_id)
	{
		$goal_name = 'Goal_ID_' . $goal_id;
		return $this->wasGoalCompleted($goal_name, $goal_id);
	}

	/* Enforces B&A's page protection */
	function catch_goal_protected_pages()
	{
		global $post;

		if ( empty($post) || !(is_page() || is_single()) ) {
			return;
		}

		$is_protected = get_post_meta($post->ID, '_b_a_protected', true);
		if ( !empty($is_protected) ) {
			$has_b_a_hook = get_post_meta($post->ID, '_b_a_required_goal', true);
			if ( !empty($has_b_a_hook) ) {
				$complete = $this->goal_id_completed( $has_b_a_hook['goal_id'] );
				//if ( !$complete && !empty($has_b_a_hook['enabled']) && !empty($has_b_a_hook['bounce_url']) ) {
				if ( !$complete && !empty($has_b_a_hook['enabled']) ) {
					
					$bounce_action = !empty($has_b_a_hook) && !empty($has_b_a_hook['bounce_action'])
									 ? $has_b_a_hook['bounce_action']
									 : 'redirect';
									 
					// redirect visitor to specified bounce URL
					if ( 'redirect' == $bounce_action && !empty($has_b_a_hook['bounce_url']) ) {
						if ( wp_redirect($has_b_a_hook['bounce_url']) ) {
							exit;
						}
					}
					
					// show the visitor the goal instead of the page content
					if ( 'show_goal' == $bounce_action ) {
						add_filter('the_content', array($this, 'replace_content_with_goal'), 20);
					}
				}
			}
		}
	}
	
	function replace_content_with_goal($content)
	{
		global $post;
		$has_b_a_hook = get_post_meta($post->ID, '_b_a_required_goal', true);
		if ( !empty($has_b_a_hook) ) {
			$goal_id = $has_b_a_hook['goal_id'];
		}
		
		// render the goal shortcode instead of the page's content
		if ( ! empty($goal_id) ) {
			$sc = sprintf( '[goal id="%d" page_protection="1"]', intval($goal_id) );
			return do_shortcode($sc);
		}
		
		// no goal specified, so just return the content
		return $content;
	}
	
	/* 
	 * Customize the post saved/updated/etc messages for this custom post type.
	 * hooked to filter: post_updated_messages
	 */
	function goal_updated_messages($messages)
	{
		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );

		// add our messages to the list and return
		$messages['b_a_goal'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Goal updated.', 'before-and-after' ),
			2  => __( 'Custom field updated.', 'before-and-after' ),
			3  => __( 'Custom field deleted.', 'before-and-after' ),
			4  => __( 'Goal updated.', 'before-and-after' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Goal restored to revision from %s', 'before-and-after' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Goal published.', 'before-and-after' ),
			7  => __( 'Goal saved.', 'before-and-after' ),
			8  => __( 'Goal submitted.', 'before-and-after' ),
			9  => sprintf(
				__( 'Goal scheduled for: <strong>%1$s</strong>.', 'before-and-after' ),
				// translators: Publish box date format, see http://php.net/date
				date_i18n( __( 'M j, Y @ G:i', 'before-and-after' ), strtotime( $post->post_date ) )
			),
			10 => __( 'Goal draft updated.', 'before-and-after' )
		);

		if ( $post_type_object->publicly_queryable && 'b_a_goal' === $post_type ) {
			$permalink = get_permalink( $post->ID );

			$view_link = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), __( 'View goal', 'before-and-after' ) );
			$messages[ $post_type ][1] .= $view_link;
			$messages[ $post_type ][6] .= $view_link;
			$messages[ $post_type ][9] .= $view_link;

			$preview_permalink = add_query_arg( 'preview', 'true', $permalink );
			$preview_link = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), __( 'Preview goal', 'before-and-after' ) );
			$messages[ $post_type ][8]  .= $preview_link;
			$messages[ $post_type ][10] .= $preview_link;
		}

		return $messages;
	}

}
