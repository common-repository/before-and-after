<?php
	class Before_And_After_String_Formatter
	{
		function urlify($url, $label = '', $target = '')
		{
			if ( empty($label) ) {
				$label = $url;
			}
			$target_attr = !empty($target)
						   ? sprintf('target="%s"', $target)
						   : '';
			return sprintf('<a href="%s" %s>%s</a>', $url, $target_attr, $label);
		}
		
		function friendly_time($timestamp)
		{
			if ( empty($timestamp) ) {
				return '';
			}

			// example: Jul 7, 2016 8:01 pm
			return date('M j, Y g:i:s a', $timestamp);
		}
		
		function link_goal($goal_id)
		{
			$goal_title = get_the_title($goal_id);
			$edit_url = get_edit_post_link($goal_id);
			return $this->urlify($edit_url, $goal_title);
		}
		
		function link_post($post_id)
		{
			$post_title = get_the_title($post_id);
			$edit_url = get_edit_post_link($post_id);
			return $this->urlify($edit_url, $post_title);
		}
		
		function time_diff($num_seconds)
		{
			$time = round($num_seconds);

			$seconds = $time % 60;
			$time = ($time - $seconds) / 60;
			
			$minutes = $time % 60;
			$time = ($time - $minutes) / 60;

			$hours = $time % 24;
			$time = ($time - $hours) / 24;

			$days = $time;
			
			$out = '';
			
			if ($days >= 1) {
				$out .= sprintf( '%d day%s, ', $days, $this->maybe_s($days) );
			}
			
			if ($hours >= 1) {
				$out .= sprintf( '%d hour%s, ', $hours, $this->maybe_s($hours) );
			}
			
			if ($minutes >= 1) {
				$out .= sprintf( '%d minute%s, ', $minutes, $this->maybe_s($minutes) );
			}
			
			if ($seconds >= 1) {
				$out .= sprintf( '%d second%s', $seconds, $this->maybe_s($seconds) );
			}
			$out = rtrim($out, ', ');
			
			return $out;
			
			return sprintf('%d seconds', $seconds);
		}
		
		function build_address($addr, $all_meta)
		{
			$parts = maybe_unserialize($all_meta['parts']['matched_fields'][0]);
			$addr = !empty($parts['address_line_2'])
					? $parts['address'] . "\n" . $parts['address_line_2']
					: $parts['address'];
			$addr = sprintf(
				"%s\n%s, %s %s\n%s", 
				$addr,
				$parts['city'],
				$parts['state'],
				$parts['zipcode'],
				$parts['country']
			);
			return nl2br($addr);
		}

		function maybe_s($num) {
			return ($num > 1)
				   ? 's'
				   : '';
		}		
		
		function browser_details($browser_details)
		{
			$browser_details = maybe_unserialize($browser_details);
			$browser = !empty($browser_details) && !empty($browser_details['name'])
						? $browser_details['name']
						: '';
			$platform = !empty($browser_details) && !empty($browser_details['platform'])
						? $browser_details['platform']
						: '';
			$version = !empty($browser_details) && !empty($browser_details['version'])
						? $browser_details['version']
						: '';
						
			$browser_string = !empty($version)
							  ? sprintf('%s (%s)', $browser, $version)
							  : $browser;

			$platform_string = !empty($platform)
							  ? ' / ' . ucwords($platform)
							  : '';
			
			return $browser_string . $platform_string;
		}
		
		function prepend_flag_icon($text, $country_code = '' )
		{
			if ( is_array($country_code) && isset($country_code['country_code']) ) {
				$country_code = $country_code['country_code'];
			}

			if ( !empty($country_code) ) {
				$flag_html = $this->get_flag_html($country_code);
				return $flag_html . ' &nbsp;'. $text;
			}

			return $text;
		}
		
		function get_flag_html($country_code = '')
		{
			return sprintf( '<div class="flag flag-%s"></div>', strtolower($country_code) );
		}
		
	} // end class