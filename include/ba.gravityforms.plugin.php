<?php
class BA_GravityForms_Plugin
{
	var $root;
	var $entries = array();
	
	public function __construct(&$root)
	{
		$this->root = $root;
		add_action( "gform_after_submission", array( $this, "capture_form_submissions"), 10, 2 );
		add_filter( "b_a_get_posted_data_gravity_form", array( $this, "get_posted_data") );
	}
	
	function get_posted_data()
	{
		$goal_id = !empty($_REQUEST['_before_after_goal_id'])
				   ? intval($_REQUEST['_before_after_goal_id'])
				   : 0;

		if ( empty($goal_id) || empty($this->entries[$goal_id]) ) {
			return array();
		}
		
		$entry = $this->entries[$goal_id];
		$entry = apply_filters('b_a_gravity_forms_posted_data', $entry);
		return $entry;
	}

	// Looks for Goals that are hooked to this form. If any are found, marks them as complete
	function capture_form_submissions ($entry, $form)
	{
		$form_id = $form['id'];
		
		/* 
		 * If a single goal is indicated (by its ID being passed in the request, complete it
		 * Else, complete all goals associated with this form id 
		 */
		if( isset($_REQUEST['_before_after_goal_id']) ) {
			// goal ID found, so complete only the single goal
			$goal_id = intval($_REQUEST['_before_after_goal_id']);
			if ($goal_id > 0) {
				$this->entries[$goal_id] = $this->prepare_entry($entry, $form_id);				
				$completed = $this->root->Goal->completeGoalById($goal_id);
			}
		}
		else {
			// no goal ID found, so complete all goals associated with this form
			$goals = $this->find_all_goals_by_form_id($form_id);
			if ( !empty($goals) ) {
				foreach ($goals as $goal) {
					$completed = $this->root->Goal->completeGoalById($goal->ID);
				}
			}
		}
	}
	
	function prepare_entry($entry, $form_id)
	{
		$new_entry = array();
		
		$form = GFAPI::get_form($form_id);
		
		if ( !empty($form) ) {
			foreach($form['fields'] as $field) {
				if ( is_array($field) ) {
					$field = (object) $field;
				}
				$key_from_label = $this->make_field_name($field->label);
				$extract_value = $this->extract_field_value($field, $entry);
				if ( is_array($extract_value) ) {
					$new_entry = array_merge($new_entry, $extract_value);
				} else {
					$new_entry[$key_from_label] = $extract_value;
				}
			}
		}		
		return $new_entry;
	}
	
	function make_field_name($str)
	{
		return str_replace( '-', '_', sanitize_title($str) );
	}
	
	/*
	 * Given a Gravity Forms field object, returns the corresponding value from
	 * a Gravity Forms entry object. This includes recombining special fields 
	 * such as Name and Address which are split into multiple input fields.
	 *
	 * @param $field Gravity Forms field object (https://www.gravityhelp.com/documentation/article/field-object/)
	 * @param $field Gravity Forms entry array (e.g., from here https://www.gravityhelp.com/documentation/article/gform_after_submission/)
	 *
	 * @return string Extracted field value, or empty string if field cannot be found.
	 */
	function extract_field_value($field, $entry)
	{
		$value = !empty($entry[$field->id])
				 ? $entry[$field->id]
				 : '';
				 
		// if a value was found, great! return it and be done
		if ( !empty($value) ) {
			return $value;
		}

		// if we don't have a simple value nor inputs to rebuild from, we must
		// return an empty string
		if ( empty($value) && empty($field['inputs']) ) {
			return '';
		}
		
		// if its a name field, handle that specially
		if ($field->type == 'name') {
			return $this->extract_name_values($field, $entry);
		}
		
		// if its an address field, handle that specially
		if ($field->type == 'address') {
			return $this->rebuild_address_value($field, $entry);
		}

		// we didn't find a simple value, and we have inputs, so re-build value 
		// from inputs and return it
		$rebuilt_value = '';
		foreach ( $field['inputs'] as $input ) {
			$input_value = !empty( $entry[ $input['id'] ] )
						   ? $entry[ $input['id'] ]
						   : '';
			if ( !empty($input_value) ) {
				$rebuilt_value .= $input_value . ' ';
			}
		}
		return trim($rebuilt_value);
	}

	/*
	 * Given a Gravity Forms field object of name type, returns an array of
	 * values corresponding to the name parts, as well as the full name. 
	 *
	 * Fields returned: first_name,  last_name, full_name
	 *
	 *
	 *
	 * @param $field Gravity Forms field object. Must be address type. (https://www.gravityhelp.com/documentation/article/field-object/)
	 * @param $field Gravity Forms entry array (e.g., from here https://www.gravityhelp.com/documentation/article/gform_after_submission/)
	 *
	 * @return array Extracted field values: address, address_line_2, city, 
					 state, zipcode, country, and full_address
	 */
	function extract_name_values($field, $entry)
	{
		// if we don't have a simple value nor inputs to rebuild from, we must
		// return an empty string
		if ( empty($field['inputs']) ) {
			return '';
		}
		
		$map[$field->id . '.2'] = 'prefix';
		$map[$field->id . '.3'] = 'first_name';
		$map[$field->id . '.4'] = 'middle_name';
		$map[$field->id . '.6'] = 'last_name';
		$map[$field->id . '.8'] = 'suffix';
		
		foreach ( $map as $key => $value ) {
			$r[$value] = '';
		}
				
		foreach ($field['inputs'] as $input) {
			$key = $map[ $input['id'] ];
			$input_value = !empty( $entry[ $input['id'] ] )
						   ? $entry[ $input['id'] ]
						   : '';
			$r[$key] = $input_value;
		}
		
		$r['full_name']= $this->build_name_from_parts($r);
		return $r;
	}
	
	function build_name_from_parts($parts)
	{
		$name = !empty( $parts['middle_name'] )
				? sprintf( '%s %s %s', $parts['first_name'], $parts['middle_name'], $parts['last_name'] )
				: sprintf( '%s %s', $parts['first_name'], $parts['last_name'] );
		
		$suffix = !empty( $parts['suffix'] )
				  ? ', ' . $parts['suffix']
				  : '';
				  
		$full_name = sprintf('%s %s %s', $parts['prefix'], $name, $suffix);
		$full_name = trim($full_name);		
		return apply_filters('b_a_gravity_forms_full_name', $full_name, $parts);
	}
	
	/*
	 * Given a Gravity Forms field object of address type, returns an array of
	 * values corresponding to the address parts, as well as the full address. 
	 *
	 * Fields returned: address, address_line_2, city, state, zipcode, country
	 *					full_address
	 *
	 *
	 *
	 * @param $field Gravity Forms field object. Must be address type. (https://www.gravityhelp.com/documentation/article/field-object/)
	 * @param $field Gravity Forms entry array (e.g., from here https://www.gravityhelp.com/documentation/article/gform_after_submission/)
	 *
	 * @return array Extracted field values: address, address_line_2, city, 
					 state, zipcode, country, and full_address
	 */
	function rebuild_address_value($field, $entry)
	{
		// if we don't have a simple value nor inputs to rebuild from, we must
		// return an empty string
		if ( empty($field['inputs']) ) {
			return '';
		}
		
		$map[$field->id . '.1'] = 'address';
		$map[$field->id . '.2'] = 'address_line_2';
		$map[$field->id . '.3'] = 'city';
		$map[$field->id . '.4'] = 'state';
		$map[$field->id . '.5'] = 'zipcode';
		$map[$field->id . '.6'] = 'country';
		
		foreach ( $map as $key => $value ) {
			$r[$value] = '';
		}
				
		foreach ($field['inputs'] as $input) {
			$key = $map[ $input['id'] ];
			$input_value = !empty( $entry[ $input['id'] ] )
						   ? $entry[ $input['id'] ]
						   : '';
			$r[$key] = $input_value;
		}
		
		$addr = $this->build_address_from_parts($r);
		$r['full_address'] = trim($addr);
		return $r;
	}
	
	function build_address_from_parts($parts)
	{
		$addr = !empty($parts['address_line_2'])
				? $parts['address'] . "\n" . $parts['address_line_2']
				: $parts['address'];
		
		
		$addr = sprintf("%s\n%s, %s %s\n%s", $addr, $parts['city'], $parts['state'], $parts['zipcode'], $parts['country']);
		return apply_filters('b_a_gravity_forms_address', $addr, $parts);
	}
	
	function map_entry_labels_to_ids($entry, $form)
	{
		$r = array();
		foreach($entry as $key => $val) {
			if ( !is_numeric($key) ) {
				continue;
			}
			$r[$key];
		}
	}
	
	function find_all_goals_by_form_id($form_id)
	{
		$goal_selector = 'gform_' . intval($form_id);
		$conditions = array('post_type' => 'b_a_goal', 
							'meta_key' => '_goal_selector',
							'meta_value' => $goal_selector,
							);
		$posts = get_posts($conditions);
		if ($posts) {
			return $posts;
		} else {
			return false;
		}
	}
	
	function find_goal_by_form_id($form_id)
	{
		$goal_selector = 'gform_' . intval($form_id);
		$conditions = array('post_type' => 'b_a_goal', 
							'meta_key' => '_goal_selector',
							'meta_value' => $goal_selector,
							);
		$posts = get_posts($conditions);
		if ($posts) {
			return $posts[0];
		} else {
			return FALSE;
		}
	}

	
}