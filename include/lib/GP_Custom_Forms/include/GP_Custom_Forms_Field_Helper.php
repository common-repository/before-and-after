<?php

if ( !class_exists('GP_Custom_Forms_Field_Helper') ):

	class GP_Custom_Forms_Field_Helper
	{
		var $form_id = '';
		
		function __construct($form_id = '')
		{
			if ( !empty($form_id) ) {
				$this->form_id = $form_id;
			}
		}
		
		function get_form_id()
		{
			return $this->form_id;
		}
		
		function set_form_id($form_id)
		{
			$this->form_id = $form_id;
		}
		
		function render_field($field_type, $field)
		{
			// can't render fields without a form ID, so fail quietly
			if ( empty($this->form_id) ) {
				//return '';				
			}
			
			$template_path = dirname(plugin_dir_path( __FILE__ )) . "/templates/fields/{$field_type}-field.php";
			$view_vars = array(
				'field' => $field,
				'form_id' => $this->form_id,
			);
			$output = $this->render_template($template_path, $view_vars);
			return $output;
		}
		
		function render_template($templatePath, $vars = false)
		{
			$templateFile = basename($templatePath);

			// checks if the file exists in the theme first,
			// otherwise serve the file from the plugin
			if ( $theme_file = locate_template( array ( $templateFile ) ) ) {
				$real_template_path = $theme_file;
			} else {
				$real_template_path = $templatePath;
			}

			if (is_array($vars)) {
				extract($vars);
			}

			$html = '' . $real_template_path;
			if (file_exists($real_template_path)) {
				ob_start(); 
				require ($real_template_path);
				$html = ob_get_clean();
			}
			return $html;		
		}
		
		function checkbox_field($field)
		{
			return $this->render_field('checkbox', $field);
		}
		
		function date_field($field)
		{
			return $this->render_field('date', $field);
		}
		
		function email_field($field)
		{
			return $this->render_field('email', $field);
		}
		
		function phone_field($field)
		{
			return $this->render_field('phone', $field);
		}
		
		function radio_field($field)
		{
			return $this->render_field('radio', $field);
		}
		
		function select_field($field)
		{
			return $this->render_field('select', $field);
		}		

		function text_field($field)
		{
			return $this->render_field('text', $field);
		}

		function textarea_field($field)
		{
			return $this->render_field('textarea', $field);
		}
		
		function rating_field($field)
		{
			return $this->render_field('rating', $field);
		}	
	}
	
endif; // class_exists