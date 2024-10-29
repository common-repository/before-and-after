<?php
	class Before_And_After_Example_Content
	{
		function __construct()
		{
			$this->form_editor = new GP_Custom_Forms(0, false, false);
		}
		
		/*
		 * Creates example Before & After Goals
		 */
		function create_example_content($ignore_existing_content = false)
		{
			if ( $ignore_existing_content || !$this->example_content_present() ) {

				$this->find_and_create_all_templates();
			}
		}

		/*
		 * Looks for all form templates (JSON files) in the data directory.
		 * Returns an array of all templates found.
		 *
		 * TODO: Allow the user to place template files in a folder inside their theme directory		 
		 */
		function get_available_form_templates()
		{
			$dir = plugin_dir_path( __FILE__ );
			$files = glob("{$dir}/data/*.json");
			$found = array();
			foreach($files as $index => $filename) {
				$tmpl = $this->load_form_template($filename);
				if ( $this->is_valid_template($tmpl) ) {
					$info = pathinfo($filename);
					$form_slug = sanitize_title( basename($filename, '.'.$info['extension']) );
					$found[$form_slug] = $tmpl['form']['settings']['title'];
				}
			}
			return $found;
		}
		
		/*
		 * Loads a B & A example form template from disk by slug name.
		 *
		 * @param string The name of the template. Should match a filename,
		 * 				 when '.json' is appended.
		 *
		 * @return mixed The form data as an array on success, or false 
		 *               if the template is not found.
		 */
		function load_template_by_slug(string $template_name)
		{
			$dir = plugin_dir_path( __FILE__ );
			$filename = sprintf( "{$dir}/data/%s.json", basename($template_name) );
			$tmpl = $this->load_form_template($filename);
			if ( $this->is_valid_template($tmpl) ) {
				return $tmpl;
			}
			return false;
		}

		/*
		 * Looks for all form templates (JSON files) in the data directory.
		 * Creates the corresponding Forms (and Goals) for every valid template
		 * file found.
		 *
		 * TODO: Allow the user to place template files in a folder inside their theme directory		 
		 */
		function find_and_create_all_templates()
		{
			$dir = plugin_dir_path( __FILE__ );
			$files = glob("{$dir}/data/*.json");			
			foreach($files as $index => $filename) {
				$tmpl = $this->load_form_template($filename);
				if ( $this->is_valid_template($tmpl) ) {
					$new_form_id = $this->create_form_from_template($tmpl);				
				} else if ( WP_DEBUG ) {
					error_log( sprintf( 'Template (%s) could not be loaded. Invalid JSON?', basename($filename) ) );
				}
			}
		}
		
		/*
		 * Checks whether a given template contains all required data to create a form
		 *
		 * @param array $tmpl The template to examine
		 */
		function is_valid_template($tmpl = array())
		{
			if ( empty($tmpl) ) {
				return false;
			}
			if ( empty($tmpl['form']) ) {
				return false;
			}
			if ( empty($tmpl['form']['settings']) ) {
				return false;
			}
			if ( empty($tmpl['form']['settings']['title']) ) {
				return false;
			}
			if ( empty($tmpl['form']['fields']) ) {
				return false;
			}
			
			// passed all checks
			return true;
		}
		
		/*
		 * Detects whether the example content has already been created.
		 *
		 * @ return bool True if any example content exists, false if not.
		 */
		function example_content_present()
		{
			// look for a b_a_form or a goal that has the example_content meta field
			// if any are found return true, else return false 
			
			$query = new WP_Query( array( 
				'post_type' => 'b_a_form',
				'meta_key' => '_example_content',
				'meta_value' => '1' 
			) );

			if ( !empty($query->found_posts > 0) ) {
				return true;
			}
			
			$query = new WP_Query( array( 
				'post_type' => 'b_a_goal',
				'meta_key' => '_example_content',
				'meta_value' => '1' 
			) );

			if ( !empty($query->found_posts > 0) ) {
				return true;
			}

			return false;
		}

		/*
		 * Creates an example Feedback Form
		 *
		 * @param string The path to the JSON file containing the template
		 *
		 * @return array An array representing the form which can be passed
		 *				 to the GP_Form_Editor's create method.
		 */
		function load_form_template($template_filename)
		{
			if ( !file_exists($template_filename) ) {
				throw new Exception( sprintf('Filename does not exist (or has wrong permissions). (%s)', $template_filename) );
			}
			
			$json = file_get_contents($template_filename);

			if ( empty($json) ) {
				return false;
			}
			
			$fields = json_decode($json, true);
			if ( !empty($fields) ) {
				// reformat array if needed and return
				return $fields;
			}
			
			// invalid form data ($fields was empty)
			return false;
		}
		
		
		/*
		 * Creates an Before & After form from a template
		 *
		 * @param array The form template, which was loaded from JSON. 
		 * 				Must 'settings' and 'fields' arrays.
		 *
		 * @return mixed The ID (int) of the new form, or false on failure.
		 */
		function create_form_from_template(array $form_template)
		{
			$form_title = $form_template['form']['settings']['title'];
			$form_fields = $form_template['form']['fields'];
			$goal = !empty($form_template['goal'])
					? $form_template['goal']
					: '';
			$new_form_id = $this->form_editor->create( $form_title, $form_fields);
			
			// save form settings (can be overriden in templates)
			if ( !empty($new_form_id) ) {

				// email to receive submissions
				$email = !empty($form_template['form']['settings']['email_for_submissions'])
					     ? $form_template['form']['settings']['email_for_submissions']
					     : $email = get_option('admin_email', '');
				update_post_meta($new_form_id, 'email_for_submissions', $email);

				// submit button label
				$submit_button_label = !empty($form_template['form']['settings']['submit_button_label'])
									   ? $form_template['form']['settings']['submit_button_label']
									   : __('Send', 'before-and-after');
				update_post_meta($new_form_id, 'submit_button_label', $submit_button_label);

				// notification email subject
				$email_subject = !empty($form_template['form']['settings']['email_subject'])
								 ? $form_template['form']['settings']['email_subject']
								 : __('New user feedback from:', 'before-and-after') . ' {{name}} {{email}}';
				update_post_meta($new_form_id, 'email_subject', $email_subject);

				// success message
				$success_message = !empty($form_template['form']['settings']['email_subject'])
								   ? $form_template['form']['settings']['email_subject']
								   : __('Thank you for your feedback!', 'before-and-after');;
				update_post_meta($new_form_id, 'after_submit_message', $success_message);

				// mark this form as example content
				update_post_meta($new_form_id, '_example_content', '1');
				
				// create the Goal too (if Goal settings are present)
				if ( !empty($goal) ) {
					$this->create_example_goal($goal, $new_form_id);
				}
				
				return $new_form_id;			
			}
			
			return false; // post creation failed			
		}
		
		/*
		 * Creates an example Before & After Goal, where a B&A Form is the Before state
		 *   and a Thank You message is shown for the after state.
		 *
		 * TODO: allow other Goal types (e.g., show a download link)
		 *
		 * @param string $goal Information about the goal. 'title', 
		 *                     'after_action', and 'after_values' are required.
		 * @param int $form_id The post ID of the form to attach to this goal
		 * @param array $goal_options Options for the goal (e.g., the thank you message)
		 *
		 * @return mixed New form ID (post ID) on success, false on failure.
		 */
		function create_example_goal($goal, $form_id)
		{
			// create a new goal in the database. 
			$goal_id = wp_insert_post( array(
				'post_type' => 'b_a_goal',
				'post_title' => $goal['title'],
				'post_status' => 'publish',
			) );

			// abort if creating goal fails
			if ( empty($goal_id) ) {
				return false;
			}

			// store the Before state data
			update_post_meta($goal_id, '_goal_before_action', 'b_a_form');
			update_post_meta($goal_id, '_goal_before_values', array(
				'b_a_form' => $form_id
			));
			
			// store the After state data
			// (first fix file urls if needed)
			$plugin_dir = plugin_dir_url( dirname(__FILE__, 2) );
			if ( !empty($goal['after_values']['file_url']) && false !== strpos($goal['after_values']['file_url'], '{plugin_dir}') ) {
				$goal['after_values']['file_url'] = str_replace('{plugin_dir}', $plugin_dir, $goal['after_values']['file_url']);
			}
			update_post_meta($goal_id, '_goal_after_action', $goal['after_action']);
			update_post_meta($goal_id, '_goal_after_values', $goal['after_values']);
			
			// flag the new goal as example content
			update_post_meta($goal_id, '_example_content', '1');
			return $goal_id;
		}
		
		
	}
