<?php

if ( !class_exists('GP_Custom_Forms') ):

require_once('include/GP_Custom_Forms_Field_Helper.php');

class GP_Custom_Forms
{
	var $form_id = '';
	var $Field_Helper = '';
	var $valid_form_submitted = false;
	var $submitted_form_data = array();
	
	function __construct($form_id = 0, $capture_submissions = true, $enqueue_scripts = true)
	{
		global $post;
		if ( !empty($form_id) ) {
			$this->form_id = $form_id;
		}
		$this->Field_Helper = new GP_Custom_Forms_Field_Helper($this->form_id);
		$this->add_hooks($capture_submissions, $enqueue_scripts);
	}
	
	function get_form_id()
	{
		return $this->form_id;
	}
	
	function set_form_id($form_id)
	{
		$this->form_id = $form_id;
	}
	
	function was_valid_form_submitted($form_id = false)
	{
		if ( empty($form_id) ) {
			$form_id = $this->form_id;
		}
		
		return ( $this->valid_form_submitted 
			     && defined('GP_CUSTOM_FORM_SUBMITTED_' . $form_id) );
	}

	function add_hooks($capture_submissions = true, $enqueue_scripts = true)
	{
		// enqueue CSS
		if ( $enqueue_scripts ) {
			add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_css') );
			add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_js') );
			add_action( 'wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts') );
		}
		
		// add AJAX hooks for saving data
		if ( $capture_submissions ) {
			add_action( 'wp_ajax_gp_custom_forms_save_fields', array($this, 'ajax_save_fields') );
			add_action( 'init', array($this, 'check_for_form_submit') );
		}
	}
	
	function enqueue_admin_css($hook = '')
	{
		$css_url = plugins_url( 'assets/css/gp_custom_forms_admin.css', __FILE__ );
		wp_register_style(
			'GP_Custom_Forms_Editor',
			$css_url
		);
		wp_enqueue_style( 'GP_Custom_Forms_Editor' );
	}	

	function enqueue_admin_js($hook = '')
	{	
		$js_url = plugins_url( 'assets/js/gp_custom_forms_admin.js', __FILE__ );
		wp_register_script(
			'GP_Custom_Forms_Editor',	// handle
			$js_url,					// source URL
			array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable', 'jquery-ui-tabs', 'jquery-ui-sortable' ),
			false,
			true
		);
		wp_enqueue_script( 'GP_Custom_Forms_Editor' );

		$fe_js_url = plugins_url( 'assets/js/gp_custom_forms.js', __FILE__ );
		wp_register_script(
			'GP_Custom_Forms',
			$fe_js_url,
			array('jquery'),
			false,
			true
		);
		wp_enqueue_script( 'GP_Custom_Forms' );

		// Pass localized strings to JS
		$translation_array = array(
			'plugin_slug' => $this->form_id,
			'success_message' => __( 'Success! Form settings saved.' ),
		);
		wp_localize_script( 'GP_Custom_Forms_Editor', 'gp_custom_forms_strings', $translation_array );		
	}	
	
	function enqueue_frontend_scripts($hook = '')
	{	
		$css_url = plugins_url( 'assets/css/gp_custom_forms.css', __FILE__ );
		wp_register_style(
			'GP_Custom_Forms',
			$css_url
		);
		wp_enqueue_style( 'GP_Custom_Forms' );

		$rateit_css_url = plugins_url( 'assets/rateit/rateit.css', __FILE__ );
		wp_register_style(
			'GP_Custom_Forms_RateIt',
			$rateit_css_url
		);
		wp_enqueue_style( 'GP_Custom_Forms_RateIt' );

		$forms_js_url = plugins_url( 'assets/js/gp_custom_forms.js', __FILE__ );
		wp_register_script(
			'GP_Custom_Forms',
			$forms_js_url,
			array('jquery'),
			false,
			true
		);
		wp_enqueue_script( 'GP_Custom_Forms' );
	}	
	
	// creates a new form in the database
	function create($post_title, $form_data)
	{
		// 1) Create a new post in the database
		$new_post_params = array(
			'post_title'            => $post_title,
			'post_status'           => 'publish',
			'post_type'             => 'b_a_form',
		);
		$form_id = wp_insert_post($new_post_params);
		
		if ( empty($form_id) ) {
			return false;
		}
		
		// 2) Save the form data and next field index as meta fields
		$next_field_index = count($form_data) + 1;
		$this->save_next_field_index($next_field_index, $form_id);
		$this->save_form_data($form_data, $form_id);
		
		return $form_id;
	}

	function get_default_fields()
	{
		$fields = array();
		return apply_filters('gp_custom_form_default_fields', $fields, $this->form_id);
	}
	
	function render_field_template($field, $i = '')
	{
			$field['title'] = htmlentities($field['title']);
			$field['description'] = htmlentities($field['description']);
			$field['name'] = htmlentities($field['name']);
			$field['type_uc'] = $this->get_field_type_friendly_name($field['type']);
			$display_title = !empty($field['title']) ? $field['title'] : $field['type_uc'];
			$field_name_root = $this->get_field_name($field['title'], 'i');
			
			$out = '';
			$out = apply_filters('gp_custom_forms_render_field', $out, $field);
			$out = apply_filters('gp_custom_forms_render_field_' . $field['type'], $out, $field['type'], $field);
			
			if ( empty($out) )
			{
				switch($field['type'])
				{
					case 'select':
						$out = $this->render_select_field($field, $i);
						break;
					
					case 'radio':
						$out = $this->render_radio_field($field, $i);
						break;
					
					case 'checkbox':
						$out = $this->render_checkbox_field($field, $i);
						break;
					
					case 'rating':
						$out = $this->render_rating_field($field, $i);
						break;
	
					case 'heading':
						$out = $this->render_heading_field($field, $i);
						break;
	
					case 'html':
						$out = $this->render_html_field($field, $i);
						break;
	
					default:
						$out = $this->render_simple_field($field, $i);
						break;
				}
			}
			return $out;
	}
	
	function render_field_editor_top($field, $i)
	{
		$display_title = !empty($field['title']) ? $field['title'] : $field['type_uc'];
		$field_name_root = $this->get_field_name($field['title'], 'i');
		$extra_fields_top = apply_filters('gp_custom_forms_extra_fields_top', '', $i, $this->form_id, $field);
		$out = <<<EDITFIELDTOP
			<li class="gp_custom_forms_edit_field_wrapper gp_custom_forms_collapsed" data-field-index="{$i}">
			<div class="gp_custom_field_toolbox_handle">%s</div> 
			<div class="gp_custom_forms_collapse_toggle"><span class="dashicons dashicons-plus"></span></div>
			<div class="gp_custom_forms_drag_handle"><span class="dashicons dashicons-sort"></span></div>
			<h3 class="field_title"><span class="display_title">{$display_title}</span> <span class="field_type">{$field['type_uc']} Field</span></h3>
			
			<div class="field_details_wrapper" style="display:none">
				{$extra_fields_top}
				<div class="field_details">
					<label for="custom_forms_name_{$i}">Name:</label>
					<input type="text" name="custom_forms[$i][name]" id="custom_forms_name_{$i}" value="{$field['name']}" readonly="true" data-field-name-root="{$field_name_root}" />
				</div>
				<div class="field_details">
					<label for="custom_forms_title_{$i}">Label:</label>
					<input type="text" name="custom_forms[$i][title]" id="custom_forms_title_{$i}" value="{$field['title']}" class="update_field_title" />
				</div>
				<div class="field_details">
					<label for="custom_forms_description_{$i}">Description:</label>
					<input type="text" name="custom_forms[$i][description]" id="custom_forms_description_{$i}" value="{$field['description']}" />
				</div>
EDITFIELDTOP;
		return $out;
	}

	function render_field_editor_top_no_inputs($field, $i)
	{
		$display_title = !empty($field['title']) ? $field['title'] : $field['type_uc'];
		$field_name_root = $this->get_field_name($field['title'], 'i');
		$extra_fields_top = apply_filters('gp_custom_forms_extra_fields_top', '', $i, $this->form_id, $field);
		$out = <<<EDITFIELDTOP
			<li class="gp_custom_forms_edit_field_wrapper gp_custom_forms_collapsed" data-field-index="{$i}">
			<div class="gp_custom_field_toolbox_handle">%s</div> 
			<div class="gp_custom_forms_collapse_toggle"><span class="dashicons dashicons-plus"></span></div>
			<div class="gp_custom_forms_drag_handle"><span class="dashicons dashicons-sort"></span></div>
			<h3 class="field_title"><span class="display_title">{$display_title}</span> <span class="field_type">{$field['type_uc']} Field</span></h3>
			
			<div class="field_details_wrapper" style="display:none">
				{$extra_fields_top}
				<div class="field_details">
					<label for="custom_forms_name_{$i}">Name:</label>
					<input type="text" name="custom_forms[$i][name]" id="custom_forms_name_{$i}" value="{$field['name']}" readonly="true" data-field-name-root="{$field_name_root}" />
				</div>
EDITFIELDTOP;
		return $out;
	}

	function render_field_editor_bottom($field, $i)
	{
		$out = '';
		// default to on
		$required_checked = (isset($field['required']) && $field['required'] == '1') 
							  ? 'checked="checked"'
							  : '';
		$extra_fields_bottom = apply_filters('gp_custom_forms_extra_fields_bottom', '', $i, $this->form_id, $field);		
		$delete_button = $this->get_delete_button();

		$required_input = <<<REQUIREDINPUT
		<div class="field_details">
			<br>
			<label for="custom_forms_required_{$i}">
				<input type="hidden" name="custom_forms[$i][required]" value="0" />
				<input type="checkbox" name="custom_forms[$i][required]" id="custom_forms_required_{$i}" value="1" {$required_checked} />
				Required
			</label>
		</div>
		<br>
REQUIREDINPUT;

		// remove delete button if field is not deleteable
		if ( isset($field['deleteable']) && empty($field['deleteable']) ) {
			$delete_button = '<input type="hidden" name="custom_forms[' . $i . '][deleteable]" value="0" />';
			$required_input = '<input type="hidden" name="custom_forms[' . $i . '][required]" value="1" />';
		}
		
		$out .= <<<EDITFIELDBOTTOM
			{$required_input}
			{$extra_fields_bottom}
			{$delete_button}
			<input name="custom_forms[$i][type]" type="hidden" value="{$field['type']}" />
		</div><!--.field_details_wrapper-->
	</li>
EDITFIELDBOTTOM;
		return $out;
	}
	
	function render_field_editor_bottom_no_inputs($field, $i)
	{
		// add a delete button if the field is deleteable, or a hidden input with deleteable=0 if not
		$delete_button =  !isset($field['deleteable']) || empty($field['deleteable'])
						  ? $delete_button = $this->get_delete_button()
						  : '<input type="hidden" name="custom_forms[' . $i . '][deleteable]" value="0" />';
		
		$out = <<<EDITFIELDBOTTOM
			<input type="hidden" name="custom_forms[' . $i . '][required]" value="0" />
			{$delete_button}
			<input name="custom_forms[$i][type]" type="hidden" value="{$field['type']}" />
		</div><!--.field_details_wrapper-->
	</li>
EDITFIELDBOTTOM;
		return $out;
	}
	
	function get_delete_button()
	{
		$delete_button = <<<DELETEBUTTON
		<div class="delete_field_wrapper">
				<a href="#" class="delete_field_link">Delete Field</a>
		</div>
DELETEBUTTON;
		return $delete_button;
	}
	
	function render_options_table($field, $i)
	{
		$option_rows_html = '';
		/* 
		 * Templates
		 */
		$last_column_html = '<a href="#" class="button gp_custom_forms_button delete_options_row"><span class="dashicons dashicons-minus"></span></a>';
		$option_row_tmpl = <<<OPTIONSTMPL
			<tr>
				<td>
					<input class="option_default" type="radio" value="%s" %s />
				</td>
				<td>
					<input class="option_value" type="text" value="%s" />
				</td>
				<td>
					<input class="option_label" type="text" value="%s" />
				</td>
				<td>
					{$last_column_html}
				</td>
			</tr>		
OPTIONSTMPL;
		$row_tmpl = htmlentities( sprintf($option_row_tmpl, '[i]', '', '', '') );

		/*
		 * Add a row of inputs for each of the existing options
		 */
		 $default = isset($field['default'])
						   ? intval($field['default'])
						   : 0;
		// if default is out of bounds set it to 0
	    if ( $default >= (count($field['options']) - 1) ) {
			$default = 0;
		}
						   
		foreach($field['options'] as $option_index => $option) {
			$current_value = !empty($option['value'])
							 ? $option['value']
							 : '';
			$current_label = !empty($option['label'])
							 ? $option['label']
							 : '';
							 
			$checked = ( $option_index == $default )
					   ? 'checked="checked"'
					   : '';

			// skip empty rows
			$current_label = trim($current_label);
			$current_value = trim($current_value);
			if ( empty( $current_label ) && empty( $current_value ) ) {
				continue;
			}
							 
			$option_rows_html .= sprintf($option_row_tmpl, $option_index, $checked, htmlentities($current_value), htmlentities($current_label), $last_column_html);
		}
		
		/*
		 * Add an extra row of empty inputs to the end
		 */
		$option_rows_html .= sprintf($option_row_tmpl, count($field['options']), '', '', '', $last_column_html);		

		/*
		 * Wrap all of the rows in a table and return
		 */
		$out = <<<EDITOPTIONS
			<div class="gp_custom_forms_options_panel">
				<label>Options:</label>
				<table class="gp_custom_forms_options_table">
					<thead>
						<tr>
							<th width="20px">&nbsp;</th>
							<th width="200px">Value</th>
							<th width="1000px">Label</th>
							<th width="20px">&nbsp;</th>
						</tr>
					</thead>
					<tbody>
						{$option_rows_html}
					</tbody>
				</table>
				<div style="display:none" class="gp_custom_forms_options_row_tmpl" data-tmpl="{$row_tmpl}"></div>
				<button class="button add_options_row gp_custom_forms_button" type="button"><span class="dashicons dashicons-plus"></span> <span>Add Option</span></button>
			</div>
EDITOPTIONS;
		return $out;		
	}
	
	/*
	 * Renders the field editor for a checkbox field.
	 */	 
	function render_checkbox_field($field, $i = '')
	{
		// init options if needed
		if ( !isset($field['options']) ) {
			$field['options'] = array();
		}
		$default = isset($field['default']) && in_array( $field['default'], array('yes', 'no') )
				   ? $field['default']
				   : 'no';

		$display_title = !empty($field['title']) ? $field['title'] : $field['type_uc'];
		$field_name_root = $this->get_field_name($field['title'], 'i');
		$out = $this->render_field_editor_top($field, $i);
		
		/*
 		 * Render the "choose default" field
		 */
		$on_label = __('On', 'company-directory');
		$on_checked = ($default == 'yes')
					  ? 'checked="checked"'
					  : '';
		$off_label = __('Off', 'company-directory');
		$off_checked = ($default == 'no')
					   ? 'checked="checked"'
					   : '';
		
		$on_off_radio = <<<ONOFFRADIO
			<label>Default:</label>
			<div class="gp_custom_forms_on_off_radio">
				<label><input type="radio" class="option_on_off" value="yes" {$on_checked} /> {$on_label}</label>
				<label><input type="radio" class="option_on_off" value="no" {$off_checked} /> {$off_label}</label>
			</div>
ONOFFRADIO;
		$out .= sprintf('<div class="field_details field_details_checkbox_default">%s</div>', $on_off_radio);
		
		// finish rendering the field box
		$out .= $this->render_field_editor_bottom($field, $i);

		return $out;
	}

	/*
	 * Renders the field editor for a rating field.
	 */
	function render_rating_field($field, $i = '')
	{
			// init options if needed
			if ( !isset($field['min']) ) {
				$field['min'] = 1;
			}
			if ( !isset($field['max']) ) {
				$field['max'] = 5;
			}
			if ( !isset($field['placeholder']) ) {
				$field['placeholder'] = '';
			}
			$display_title = !empty($field['title'])
							 ? $field['title']
							 : $field['type_uc'];
			$field_name_root = $this->get_field_name($field['title'], 'i');
			$field_editor_top = $this->render_field_editor_top($field, $i);
			$field_editor_bottom = $this->render_field_editor_bottom($field, $i);
			
			$placeholder = '';
			if ( !isset($field['show_placeholder']) || $field['show_placeholder'] ) {
				
				$placeholder = <<<PLACEHOLDER
			<div class="field_details">
				<label for="custom_forms_default_{$i}">Placeholder:</label>
				<input type="text" name="custom_forms[$i][placeholder]" id="custom_forms_placeholder_{$i}" value="{$field['placeholder']}" />
			</div>		
PLACEHOLDER;
			}
			
			$out = <<<EDITFIELD
			{$field_editor_top}
			<div class="field_details">
				<label for="custom_forms_default_{$i}">Default:</label>
				<input type="text" name="custom_forms[$i][default]" id="custom_forms_default_{$i}" value="{$field['default']}" />
			</div>
			<!--
			<div class="field_details">
				<label for="custom_forms_default_{$i}">Minimum:</label>
				<input type="number" min="0" max="10" name="custom_forms[$i][min]" id="custom_forms_placeholder_{$i}" value="{$field['min']}" />
			</div>		
			-->
			<div class="field_details">
				<label for="custom_forms_default_{$i}">Maximum:</label>
				<input type="number" min="1" max="10" name="custom_forms[$i][max]" id="custom_forms_placeholder_{$i}" value="{$field['max']}" />
			</div>		
			{$placeholder}
			{$field_editor_bottom}
EDITFIELD;
				return $out;
	}

	/*
	 * Renders the field editor for a heading field.
	 */
	function render_heading_field($field, $i = '')
	{
			// init options if needed
			$display_title = !empty($field['title'])
							 ? $field['title']
							 : $field['type_uc'];
			$field_name_root = $this->get_field_name($field['title'], 'i');
			$field_editor_top = $this->render_field_editor_top_no_inputs($field, $i);
			$field_editor_bottom = $this->render_field_editor_bottom_no_inputs($field, $i);			
			
			if ( empty($field['heading_type']) ) {
				$field['heading_type'] = 'h1';
			}
			
			if ( empty($field['heading_text']) ) {
				$field['heading_text'] = '';
			}
			
			if ( empty($field['subheading_text']) ) {
				$field['subheading_text'] = '';
			}
			
			$heading_options = '';
			foreach( range(1, 6) as $level ) {
				$selected = ("h{$level}" == $field['heading_type'])
							? ' selected="selected"'
							: '';
				$heading_options .= sprintf('<option value="h%d"%s>Heading %d</option>', $level, $selected, $level);
			}			
			
			$out = <<<EDITFIELD
			{$field_editor_top}
			<div class="field_details">
				<label for="custom_forms_title_{$i}">Heading Text:</label>
				<input type="text" name="custom_forms[$i][heading_text]" id="custom_forms_heading_text_{$i}" value="{$field['heading_text']}" class="update_field_title" />
			</div>
			<div class="field_details">
				<label for="custom_forms_description_{$i}">Subheading Text:</label>
				<input type="text" name="custom_forms[$i][subheading_text]" id="custom_forms_subheading_text_{$i}" value="{$field['subheading_text']}" />
			</div>
			<div class="field_details">
				<label for="custom_forms_default_{$i}">Heading Level:</label>
				<select name="custom_forms[$i][heading_type]" id="custom_forms_heading_type_{$i}">
					{$heading_options}				
				</select>
			</div>
			{$field_editor_bottom}
EDITFIELD;
				return $out;
	}

	/*
	 * Renders the field editor for a free form HTML field
	 */
	function render_html_field($field, $i = '')
	{
			// init options if needed
			$display_title = !empty($field['title'])
							 ? $field['title']
							 : $field['type_uc'];
			$field_name_root = $this->get_field_name($field['title'], 'i');
			$field_editor_top = $this->render_field_editor_top_no_inputs($field, $i);
			$field_editor_bottom = $this->render_field_editor_bottom_no_inputs($field, $i);			
						
			if ( empty($field['content']) ) {
				$field['content'] = '';
			}
			
			$content_safe = htmlentities($field['content']);
			$description = __('Enter any text that you like. HTML is allowed.');
			$add_paragraphs_checked = !empty($field['add_paragraphs'])
									  ? 'checked="checked"'
									  : '';
			$out = <<<EDITFIELD
			{$field_editor_top}
			<div class="field_details">
				<label for="custom_forms_title_{$i}">Content:</label>
				<textarea rows="10" name="custom_forms[$i][content]" id="custom_forms_content_{$i}" class="html_input widefat">{$content_safe}</textarea>
				<p class="description">{$description}</p>
			</div>
			<div class="field_details">
				<label for="custom_forms_add_paragraphs_{$i}">
					<input type="hidden" name="custom_forms[$i][add_paragraphs]" value="0" />
					<input type="checkbox" name="custom_forms[$i][add_paragraphs]" id="custom_forms_add_paragraphs_{$i}" value="1" {$add_paragraphs_checked} />
					Automatically Add Paragraphs (&lt;p&gt; tags)
				</label>
			</div>
			{$field_editor_bottom}
EDITFIELD;
			return $out;
	}

	
	/*
	 * Renders the field editor for a multiple choice (radio) field.
	 */	 
	function render_radio_field($field, $i = '')
	{
		// init options if needed
		if ( !isset($field['options']) ) {
			$field['options'] = array();
		}
		$display_title = !empty($field['title']) ? $field['title'] : $field['type_uc'];
		$field_name_root = $this->get_field_name($field['title'], 'i');
		$out = $this->render_field_editor_top($field, $i);
		
		/*
 		 * Render the list of options
		 */
		
		// render a set of inputs for the values stored in the option, plus one extra row
		$options_table_html = $this->render_options_table($field, $i);
		$out .= sprintf('<div class="field_details field_details_radio">%s</div>', $options_table_html);
		
		// finish rendering the field box
		$out .= $this->render_field_editor_bottom($field, $i);

		return $out;
	}
		
	/*
	 * Renders the field editor for a Select field
	 */	 
	function render_select_field($field, $i = '')
	{
		// init options if needed
		if ( !isset($field['options']) ) {
			$field['options'] = array();
		}
		$display_title = !empty($field['title']) ? $field['title'] : $field['type_uc'];
		$field_name_root = $this->get_field_name($field['title'], 'i');
		$out = $this->render_field_editor_top($field, $i);
		
		/*
 		 * Render the list of options
		 */
		
		// render a set of inputs for the values stored in the option, plus one extra row
		$options_table_html = $this->render_options_table($field, $i);
		$out .= sprintf('<div class="field_details field_details_select">%s</div>', $options_table_html);

		// finish rendering the field box
		$out .= $this->render_field_editor_bottom($field, $i);

		return $out;
	}
	
	/*
	 * Renders the field editor for a simple custom field editor,
	 * simple means it has no extra parameters and can be rendered 
	 * in a standard way. (e.g., Text, Textarea, Email, Phone, etc.)
	 */	 
	function render_simple_field($field, $i = '')
	{
		$display_title = !empty($field['title']) ? $field['title'] : $field['type_uc'];
		$field_name_root = $this->get_field_name($field['title'], 'i');
		$field_editor_top = $this->render_field_editor_top($field, $i);
		$field_editor_bottom = $this->render_field_editor_bottom($field, $i);
		
		$placeholder = '';
		if ( !isset($field['show_placeholder']) || $field['show_placeholder'] ) {
			
			$placeholder = <<<PLACEHOLDER
		<div class="field_details">
			<label for="custom_forms_default_{$i}">Placeholder:</label>
			<input type="text" name="custom_forms[$i][placeholder]" id="custom_forms_placeholder_{$i}" value="{$field['placeholder']}" />
		</div>		
PLACEHOLDER;
		}
		
		$out = <<<EDITFIELD
		{$field_editor_top}
		<div class="field_details">
			<label for="custom_forms_default_{$i}">Default:</label>
			<input type="text" name="custom_forms[$i][default]" id="custom_forms_default_{$i}" value="{$field['default']}" />
		</div>
		{$placeholder}
		{$field_editor_bottom}
EDITFIELD;
			return $out;
	}
	
	function get_field_type_friendly_name($type)
	{
		
		switch($type) {
			case 'text':
				return 'Single Line Text';
				break;

			case 'textarea':
				return 'Multi-Line Text';
				break;

			case 'radio':
				return 'Multiple Choice';
				break;

			case 'select':
				return 'Dropdown Menu';
				break;

			case 'checkbox':
				return 'Checkbox';
				break;
				
			case 'html':
				return 'HTML';
				break;
				
			default:
				return ucwords($type);
				break;
		}
	}
		
	function form_editor( $show_toolbox = true )
	{
		$fields = $this->get_form_data();
		$next_field_index = $this->get_next_field_index();
		
		// if brand new form with no fields, use the default fields
		if ( empty($fields) ) {
			$fields = $this->get_default_fields();
			$next_field_index = count($fields) + 1;
		}

		// render form
		print('<div class="gp_custom_forms_editor">');
		print('<form class="gp_custom_forms_form">');
		
		printf( '<input type="hidden" name="next_field_index" value="%s" />', intval($next_field_index) );
		

		// pass count to JS
		//wp_localize_script( 'GP_Custom_Forms_Editor', 'gp_custom_forms_state', array('next_field_index' => $next_field_index ) );

		print('<ul class="gp_custom_forms_all_fields_wrapper">');
		
		// foreach fields
		foreach($fields as $i => $field) {
			// renders an <li> containing all the HTML to edit this field
			print( $this->render_field_template($field, $i) );
		}
		
		
		print('</ul>'); //.gp_custom_forms_all_fields_wrapper
		
			// render an edit box for that field
		
		
		print('</form>');
		print('</div><!--.gp_custom_forms_editor-->');
		
		if ( $show_toolbox ) {
			$this->toolbox();
		}
	}
	
	function toolbox( $show_save_button = true, $show_title = true )
	{
		print( '<div class="gp_custom_forms_editor_toolbox">' );
		if ( $show_save_button ) {
			printf( '<button id="gp_custom_forms_save_button" class="button button-primary">%s</button>', __('Save Fields', 'company-directory') );		
			print('<hr>');
		}
		if ( $show_title ) {
			printf( '<h3>%s</h3>', __('Available Fields', 'company-directory') );
		}
		print('<ul class="available_fields">');
		$available_fields = $this->get_available_fields();
		foreach($available_fields as $j => $af) {
			$af['name'] = $this->get_field_name($af['title'], 'i');
			$field_template = $this->render_field_template($af, 'i');
			$icon_html = sprintf('<span class="dashicons %s"></span>', $af['icon']);
			printf( '<li data-field-type="%s" class="custom_field %s_field">%s <div class="gp_custom_field_toolbox_handle">%s</div> <div class="gp_custom_field_template"><ul>%s</ul></div></li>', $af['type'], $af['type'], $icon_html, $af['title'], $field_template );
		}
		print('</ul>');


		print( '</div>' );
	}
	
	function get_available_fields()
	{
		$available_fields = array();
		$available_fields[] = array(
				'name' => '',
				'title' => 'Single Line Text',
				'default' => '',
				'placeholder' => '',
				'description' => '',
				'type' => 'text',		
				'icon' => 'dashicons-minus',
		);
		$available_fields[] = array(
				'name' => '',
				'title' => 'Multi-Line Text',
				'default' => '',
				'placeholder' => '',
				'description' => '',
				'type' => 'textarea',
				'icon' => 'dashicons-menu',
		);
		$available_fields[] = array(
				'name' => '',
				'title' => 'Checkbox',
				'default' => '',
				'placeholder' => '',
				'description' => '',
				'type' => 'checkbox',	
				'icon' => 'dashicons-yes',
		);
		$available_fields[] = array(
				'name' => '',
				'title' => 'Dropdown Menu',
				'default' => '',
				'placeholder' => '',
				'description' => '',
				'type' => 'select',
				'icon' => 'dashicons-list-view',				
		);
		$available_fields[] = array(
				'name' => '',
				'title' => 'Multiple Choice',
				'default' => '',
				'placeholder' => '',
				'description' => '',
				'type' => 'radio',	
				'icon' => 'dashicons-editor-ul',				
		);
		$available_fields[] = array(
				'name' => '',
				'title' => 'Email',
				'default' => '',
				'placeholder' => 'name@example.com',
				'description' => '',
				'type' => 'email',		
				'icon' => 'dashicons-email-alt',				
		);
		$available_fields[] = array(
				'name' => '',
				'title' => 'Phone',
				'default' => '',
				'placeholder' => '(123) 456-7890',
				'description' => '',
				'type' => 'phone',		
				'icon' => 'dashicons-phone',				
		);
		$available_fields[] = array(
				'name' => '',
				'title' => 'Date',
				'default' => '',
				'placeholder' => '',
				'description' => '',
				'type' => 'date',		
				'icon' => 'dashicons-calendar-alt',				
		);
		$available_fields[] = array(
			'name' => '',
			'title' => 'Rating',
			'default' => '',
			'placeholder' => '',
			'description' => '',
			'type' => 'rating',
			'icon' => 'dashicons-star-half',
			'show_placeholder' => false,
		);
		$available_fields[] = array(
				'name' => '',
				'title' => 'Heading',
				'default' => '',
				'placeholder' => '',
				'description' => '',
				'type' => 'heading',		
				'icon' => 'dashicons-editor-textcolor',
		);
		$available_fields[] = array(
				'name' => '',
				'title' => 'HTML',
				'default' => '',
				'placeholder' => '',
				'description' => '',
				'type' => 'html',		
				'icon' => 'dashicons-text-page',
		);
		return apply_filters('gp_custom_forms_available_fields', $available_fields);
	}
	
	function get_field_name($title, $index)
	{
		$title = str_replace( array('-', ' '), '_', $title);
		
		// alphanumeric only
		$title = preg_replace("/[^a-zA-Z0-9_]+/", "", $title);

		$title .= '_' . $index;
		
		$title = strtolower($title);
		return $title;
	}
	
	function get_form_data($form_id = 0)
	{
		if ( empty($form_id) ) {
			$form_id = $this->form_id;
		}
		return get_post_meta($form_id, 'form_fields', true);
	}
	
	function save_form_data($new_form_data, $form_id = 0)
	{
		$form_id = !empty($form_id)
				   ? $form_id
				   : $this->form_id;
		return update_post_meta($form_id, 'form_fields', $new_form_data);
	}
	
	function get_next_field_index()
	{
		$next_field_index = intval( get_post_meta($this->form_id, 'next_field_index', true) );
		return !empty($next_field_index)
			   ? $next_field_index
			   : 1;
	}
	
	function save_next_field_index($new_next_field_index, $form_id = 0)
	{
		$form_id = !empty($form_id)
				   ? $form_id
				   : $this->form_id;
				   
		return update_post_meta($form_id, 'next_field_index', $new_next_field_index);
	}
	
	/* Takes a list of field names (strings) and returns an array containing
	 * the full metadata for each field. If a field name doesn't match a custom
	 * field in our database (i.e., is invalid) then it will not be included in 
	 * the return array. This could happen if a custom field is added to a form,
	 * and then later the field is deleted in the Custom Fields admin area.
	 *
	 * @param array $fields List of strings representing the field names.
	 *
	 * @return array Array of arrays, each containing the metadata for each 
	 * 				 matched field.
	 */
	function load_full_field_data($fields = array())
	{
		$completed = array();
		$custom_forms = $this->get_form_data();
		if ( empty($fields ) ) {
			// no valid fields specified so return empty array
			return $completed;
		}
		
		foreach($fields as $field) {
			foreach ($custom_forms as $cf) {
				if ( strcmp($field, $cf['name']) === 0 ) {
					$completed[] = $cf;
					break;
				}
			}
		}

		// return the list of fields that were matched (could be empty)
		return $completed;
	}
	
	function ajax_save_fields()
	{
		// exit with error if user is not authorized
		if ( !current_user_can('manage_options') ) {
			wp_die('0');
		}
		
		// TODO:
		// 1) grab posted data, verify it is valid
		
		$form_data = !empty($_POST['form_data'])
					 ? $_POST['form_data']
					 : array();

		if ( empty($form_data) ) {
			wp_die('0');
		}
		
		parse_str($form_data, $posted_data);
		
		if ( empty($posted_data['form_data']) ) {
			wp_die('0');
		}
		
		/*
		 * Capture the value of next_field_index
		 */
		$this->save_next_field_index_from_post();

		// TODO: add better verification
		
		// 2) save it as a wp option. use array_values to reindex the array 
		//    from 0 (because the order may have changed due to drag and drop)
		$new_form_data = array_values($posted_data['form_data']);
		$new_form_data = stripslashes_deep($new_form_data);
		
		//$new_form_data = array_map( stripslashes, $new_form_data );

		// if the value has not changed just skip this step and return success
		$old_value = $this->get_form_data();
		if ( $old_value == $new_form_data ) {
			wp_die('1');
		}
		
		// we have a new value to save, so try doing so now
		$update_success = $this->save_form_data($new_form_data);
		
		// return 1 if valid data && save worked. 0 if not
		$output .= $update_success 
			 ? '1'
			 : '0';
		
		// terminate safely (required for WP AJAX)
		wp_die();
	}
	
	function save_next_field_index_from_post()
	{		
		$next_field_index = !empty($_POST['next_field_index'])
						    ? intval($_POST['next_field_index'])
						    : 0;

		if ( $next_field_index > 0 ) {
			$this->save_next_field_index($next_field_index);
		}	
	}
	
	// rekey form data to use field names for the array keys instead of numbers
	function rekey_field_data($fields)
	{
		$rekeyed = array();
		foreach($fields as $index => $field) {
			$rekeyed[ $field['name'] ] = $field;
		}
		return $rekeyed;
	}
	
	function match_email($input)
	{
		return (strpos($input, '@') !== false);
	}
	
	function validate_posted_data($form_data, $fields)
	{
		$fields = $this->rekey_field_data($fields);
		$errors = array();
		
		foreach($form_data as $key => $val) {
			
			// skip fields that are not part of the form 
			if ( !isset($fields[$key]) ) {
				continue;
			}
			
			$field = $fields[$key];
			
			if ( !empty($field['required']) && empty($val) ) {
				$errors[$key] = __('This field is required.', 'before-and-after');
			}
			else if ( $field['type'] == 'email' && !$this->match_email($val) ) {
				$errors[$key] = __('A valid email is required.', 'before-and-after');
			}
		}
		
		return apply_filters('gp_custom_forms_validate_submission', $errors, $form_data, $fields);
	}
	
	function check_for_form_submit()
	{
		global $wp;
		
		// check for honeypot value. if not empty just ignore this form
		if ( !empty($_POST['gp_custom_form_extra_comments_hp']) ) {
			// not blank! probably a bot. ignore this submission
			return;
		}
		
		if ( !empty($_POST['gp_custom_form_submitted']) 
 			 && !empty( $_POST['gp_custom_form_id'] ) 
			 && !$this->valid_form_submitted
		) {

			// a form was submitted, so validate it before continuing
			$form_id = intval($_POST['gp_custom_form_id']);
			$this->form_id = $form_id;
			$fields = $this->get_form_data( $form_id );
			$form_key = sprintf('form_%d', $form_id);
			$form_data = $_POST[$form_key];
			$errors = $this->validate_posted_data($form_data, $fields);			
			$this->submitted_form_data = $form_data;
			$this->validation_errors = $errors;

			// if no errors, form is valid so continue
			if ( empty($errors) ) {
				$this->valid_form_submitted = true;
				if ( !defined('GP_CUSTOM_FORM_SUBMITTED_' . $form_id) ) {
					define('GP_CUSTOM_FORM_SUBMITTED_' . $form_id, true);
				}
				do_action('gp_custom_form_submitted', $form_id, $form_data);
			}
		}
	}
	
	function render_form( $options = array() )
	{
		global $wp;
		$fields = $this->get_form_data();
		$url = remove_query_arg('gp_custom_form');
		$output = '';
		
		if ( $this->valid_form_submitted 
			 || defined('GP_CUSTOM_FORM_SUBMITTED_' . $this->form_id)
		) {
			$success_message = get_post_meta($this->form_id, 'after_submit_message', true);
			if ( empty($success_message) ) {
				$success_message = __('Thank you for your message!', 'before-and-after');
			}
			$output .= $success_message;
		}
		else {
			// render the form, possibly with errors
			$output .= sprintf( '<form id="gp_custom_form_%s" class="gp_custom_form" action="%s" method="POST" enctype="multipart/form-data">',
								$this->form_id,
								$url );

			$this->Field_Helper->set_form_id($this->form_id);

			if ( !empty($fields) ) {
				foreach($fields as $field)
				{
					$field['error'] = !empty($this->validation_errors) && !empty($this->validation_errors[ $field['name'] ])
									  ? $this->validation_errors[ $field['name'] ]
									  : '';

				    $field_helper = new GP_Custom_Forms_Field_Helper($this->form_id);
		
									  
					switch($field['type']) {
							
							default:
								$output .= $this->Field_Helper->render_field($field['type'], $field);
							break;

							case 'text':
								$output .= $this->Field_Helper->text_field($field);
							break;
							
							case 'textarea':
								$output .= $this->Field_Helper->textarea_field($field);
							break;
							
							case 'date':
								$output .= $this->Field_Helper->date_field($field);
							break;
							
							case 'email':
								$output .= $this->Field_Helper->email_field($field);
							break;
							
							case 'phone':
								$output .= $this->Field_Helper->phone_field($field);
							break;
							
							case 'select':
								$output .= $this->Field_Helper->select_field($field);
							break;
							
							case 'checkbox':
								$output .= $this->Field_Helper->checkbox_field($field);
							break;
							
							case 'radio':
								$output .= $this->Field_Helper->radio_field($field);
							break;

							case 'rating':
								$output .= $this->Field_Helper->rating_field($field);
							break;

							case 'category':
								$output .= $this->Field_Helper->category_field($field);
							break;
						} // end switch
				}
			}
			$submit_button_label = !empty($options['submit_button_label'])
								   ? $options['submit_button_label']
								   : __('Send', 'before-and-after');
								   
			// honeypot
			$captcha_html = '';
			$captcha_html .= '<div style="display:none;">';
			$captcha_html .= '<label for="gp_custom_form_extra_comments_hp">Leave this field blank</label>';
			$captcha_html .= '<input type="text" name="gp_custom_form_extra_comments_hp" value="" placeholder="Leave this field blank" />';
			$captcha_html .= '</div>';
			$captcha_error = !empty($this->validation_errors['captcha'])
							 ? $this->validation_errors['captcha']
							 : '';
			$output .= apply_filters('gp_custom_form_captcha_html', $captcha_html, $this->form_id, $captcha_error);

			// metadata
			$output .= '<input type="hidden" name="gp_custom_form_submitted" value="1" />';
			$output .= sprintf('<input type="hidden" name="gp_custom_form_id" value="%s" />', $this->form_id);

			$submit_html = sprintf( '<button type="submit">%s</button>', $submit_button_label );
			$output .= $submit_html;
			
			
			if ( !empty($options['metadata']) ) {
				$field_tmpl = '<input type="hidden" name="%s" value="%s" />';
				foreach($options['metadata'] as $key => $val) {
					$output .= sprintf($field_tmpl, htmlentities($key), htmlentities($val) );
				}
			}
			
			$output .= '</form>';
		}
		
		return $output;
	}
	
	/*
	 * Returns an array matching the given form's field names to their labels.
	 *
	 * @param string $form_id Optional. The form to use. Will use the value of
	 *									$this->form_id if not specified.
	 *
	 * @return array Array of fields, where the keys are the field names and
	 *				 the values are the corresponding labels.
	 */
	function get_field_labels($form_id = 0)
	{
		if ( empty($form_id) ) {
			$form_id = $this->form_id;
		}

		$fields = $this->get_form_data($form_id);
		$labels = array();
		
		foreach($fields as $field) {
			$labels[ $field['name'] ] = $field['title'];
		}
		
		return $labels;
	}
	
	/*
	 * Returns an array matching the given form's field names to their types.
	 *
	 * @param string $form_id Optional. The form to use. Will use the value of
	 *									$this->form_id if not specified.
	 *
	 * @return array Array of fields, where the keys are the field names and
	 *				 the values are the corresponding types.
	 */
	function get_field_types($form_id = 0)
	{
		if ( empty($form_id) ) {
			$form_id = $this->form_id;
		}

		$fields = $this->get_form_data($form_id);
		$types = array();
		
		foreach($fields as $field) {
			$types[ $field['name'] ] = $field['type'];
		}
		
		return $types;
	}
	
	/*
	 * Determines whether a field exists in the given form.
	 *
	 * @param string $field_name The field to look for
	 * @param string $form_id Optional. The form to look inside. Will use the 
	 *						value of $this->form_id if not specified.
	 *
	 * @return boolean True if the field exists, false if not.
	 */
	function is_valid_field($field_name, $form_id = 0)
	{
		if ( empty($form_id) ) {
			$form_id = $this->form_id;
		}

		$fields = $this->get_form_data($form_id);
		
		if ( empty($fields) ) {
			return false;			
		}
		
		foreach($fields as $field) {
			if ( 0 == strcmp($field['name'], $field_name) ) {
				return true;
			}
		}
		return false;
	}
}
endif;
