<?php
	class Before_And_After_Field_Matcher
	{
		function match_fields($data)
		{
			$matched = array();
			$data = $this->normalize_data($data);
			$map = $this->get_match_map();
			foreach ($data as $key => $value)
			{
				if ( isset($map[$key]) ) {
					$match_key = $map[$key];
					$matched[$match_key] = $value;
				}
			}
			return $matched;
		}
		
		function normalize_data($data)
		{
			$keep = array();
			foreach ($data as $key => $value)
			{
				// remove fields beginning with _
				if (strpos($key, '_') === 0) {
					continue;
				} 

				// replace spaces and dashes with underscores
				$space_chars = array(' ', '-');
				$fixed_key = trim($key);
				$fixed_key = str_replace($space_chars, '_', $fixed_key);			
				
				// add to $keep array with normalized key
				$keep[$fixed_key] = $value;		
			}
			
			return $keep;
		}
		
		function get_match_map()
		{
			$candidates = $this->get_match_candidates();
			$map = array();
			foreach($candidates as $map_key => $list) {
				foreach ($list as $list_index => $list_value) {				
					$map[$list_value] = $map_key;
				}
			}
			return $map;
		}

		function get_match_candidates()
		{
			$candidates = array(
				'full_name' => array(
					'name',
					'full_name',
					'your_name',
				),
				'first_name' => array(
					'first_name',
				),
				'last_name' => array(
					'last_name',
				),
				'title' => array(
					'title',
					'your_title',
				),
				'email' => array(
					'email',
					'your_email',
				),
				'fax' => array(
					'fax',
					'your_fax',
					'fax_number',
					'your_fax_number',
				),
				'phone' => array(
					'phone',
					'your_phone',
					'phone_number',
					'your_number',
				),
				'address' => array(
					'address',
					'your_address',
					'mailing_address',
				),
				'city' => array(
					'city',
					'your_city',
					'town',
				),
				'state' => array(
					'state',
					'your_state',
					'province',
					'your_province',
				),
				'zipcode' => array(
					'zip',
					'your_zip',
					'zipcode',
					'your_zipcode',
					'zip_code',
					'your_zip_code',
					'post_code',
					'your_post_code',
					'postal_code',
					'your_postal_code',
				),
				'country' => array(
					'country',
					'your_country',
				),
				'message' => array(
					'message',
					'your_message',
				),
				'subject' => array(
					'subject',
					'your_subject',
				),
			);
			return apply_filters('b_a_field_match_candidates', $candidates);			
		}
	} // end class