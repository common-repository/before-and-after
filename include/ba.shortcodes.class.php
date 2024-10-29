<?php
class BA_Shortcodes
{
	var $root;
	var $form_editor;
	
	public function __construct($root)
	{
		$this->root = $root;
		$this->registerShortcodes();
		$this->form_editor = new GP_Custom_Forms(0, false);
	}
	
	// looks up the current value of the setting specified. returns $default_value if its not set
	// Note: $setting_location should be set to either "before-values" or "after-values"
	function get_goal_setting_value($goal_id, $setting_location, $setting_key, $default_value = '', $allow_empty = false)
	{
		return $this->root->Goal->get_goal_setting_value($goal_id, $setting_location, $setting_key, $default_value, $allow_empty);
	}	

	/* Creates the [goal], [before], [after], and [complete_goal] shortcodes */
	function registerShortcodes()
	{
		add_shortcode('goal', array($this, 'goal_shortcode'));
		add_shortcode('before', array($this, 'before_shortcode'));
		add_shortcode('after', array($this, 'after_shortcode'));
		add_shortcode('complete_goal', array($this, 'complete_goal_shortcode'));
		add_shortcode('goal_stats', array($this, 'goal_stats_shortcode'));
		
		/* Gutenberg custom blocks */
		if ( function_exists('register_block_type') ) {

			register_block_type( 'before-and-after/goal', array(
				'editor_script' 	=> 'goal-block-editor',
				'editor_style'  	=> 'goal-block-editor',
				'style'         	=> 'goal-block',
				'render_callback' 	=> array($this, 'goal_shortcode')
			) );

			register_block_type( 'before-and-after/complete-goal', array(
				'editor_script' => 'complete-goal-block-editor',
				'editor_style'  => 'complete-goal-block-editor',
				'style'         => 'complete-goal-block',
				'render_callback' 	=> array($this, 'complete_goal_shortcode')
			) );
		}
	}
	
	
	/* Holds the content which should be shown to a visitor BEFORE they complete a specified goal.
	 *
	 * This shortcode expects to be nested inside a [goal] shortcode, and possibly accompanied by an [after] shortcode.
	 */
	function before_shortcode($atts, $content)
	{
		return do_shortcode($content);
	}
	
	/* Holds the content which should be shown to a visitor AFTER they complete a specified goal.
	 *
	 * This shortcode expects to be nested inside a [goal] shortcode, and possibly accompanied by a [before] shortcode.
	 */
	function after_shortcode($atts, $content)
	{
		return do_shortcode($content);
	}

	/* [complete_goal] shortcode
	 *
	 * This shortcode should be included on a page, e.e., a Thank You page after a newsletter signup form, 
	 * and marks the current visitor as having completed the goal (via a $_SESSION variable).
	 */
	function complete_goal_shortcode($atts, $content)
	{
		$atts = shortcode_atts( array(
				'id' => 0,
				'name' => '',
				'silent' => false
		), $atts );
		$goalId = intval($atts['id']);

		if ( !empty($atts['name']) ) {
			$goalName = $atts['name'];
			return $this->root->Goal->completeGoal($goalName); // ad hoc (name based) goal. just return and be done.
		}
		else if ($goalId > 0) {
			// A goal was specified by ID. 
			$completed = $this->root->Goal->completeGoalById($goalId);
			// Render the after content now, unless silent flag is on
			if ( $atts['silent'] == false ) {
				$goal_code = '[goal id="' . $goalId . '" /]';
				return do_shortcode($goal_code);
			}
		}
		return '';		
	}
	
	// output the [goal_stats] shortcode; a list of recent completions and the recorded stats
	function goal_stats_shortcode($atts, $content = '')
	{
		
		$atts = shortcode_atts( array(
				'id' => 0,
		), $atts );	
		$goalId = intval($atts['id']);
		$goalData = get_post($goalId);
		$goalCompletions = get_post_meta($goalId, '_goal_completions', false);
		$html = '';
		$html .= '<h2>Goal Name: ' . apply_filters('the_title', $goalData->post_title) . '</h2>';
		foreach($goalCompletions as $index => $visitor)
		{
			if (!isset($visitor['IP']) || $visitor['IP'] == '') {
				continue; // skip blanks
			}
			
			
			$friendlyTime = date('M d Y h:i a', floatval($visitor['timestamp']));
			$html .= "Time: " . $friendlyTime . '<br />';
			$html .= "IP: " . $visitor['IP'] . '<br />' . '<br />';
		}
		
		//$html .= print_r ($goalCompletions, true);
		return $html;
		
	}
	
	/* Renders the [goal] shortcode
	 *
	 * First checks if the specified goal has been completed.
	 * - If it has, the nested [before] shortcode is rendered and returned. 
	 * - If it has not, the nested [after] shortcode is rendered and returned. 
	 * 
	 * If neither a before nor an after shortcode is contained within the [goal] shortcode,
	 * the shortcode implicitly assumes its contents should be shown *before* the 
	 * goal is completed, and nothing is shown after
	 */	
	function goal_shortcode($atts, $content)
	{
		// merge the passed in arguments with our defaults
		$atts = shortcode_atts( array(
				'id' => 0,
				'goal_id' => 0,
				'name' => '',
				'page_protection' => 0,
		), $atts );
		
		if ( intval($atts['id']) == 0  && intval($atts['goal_id'] > 0) ) {
			$atts['id'] = intval($atts['goal_id']);
		}		
		
		// sanitize $goalId to an int
		$goalId = intval($atts['id']);
		$goalName = $this->get_goal_name($atts);

		// make sure a valid goal name or ID was set. if not, exit and return an empty string
		if ($goalName == '') {
			// a name or id is required. since neither was found, return empty string.
			return '';
		}

		//TBD: mark this as an uncompleted coversion until / unless they convert?
		
		global $post;
		$_SESSION['goal_'.$goalId.'_encounter_url'] = isset($post->ID) ? get_permalink($post->ID) : '';
		if ( !isset($_SESSION['goal_'.$goalId.'_start_time']) ) {
			$_SESSION['goal_'.$goalId.'_start_time'] = current_time('timestamp', 0);
			$_SESSION['goal_'.$goalId.'_start_time_utc'] = current_time('timestamp', 1);
		}

		// start making a list of classes to wrap our goal with
		$goal_wrapper_classes = array(
			'before-and-after-goal', 
			sprintf('before-and-after-goal-%d', $goalId)
		);
		
		// Check if the Goal has been completed. If it has, we'll show the Before content. If not, we'll show the After content
		if ( !$this->root->Goal->wasGoalCompleted($goalName) )
		{
			// see if a [before] was specified to override the goal's default
			if (has_shortcode($content, 'before')) { 
				$value = $this->process_before_or_after_shortcode('before', $content);
			}
			else { // no override was specified, so continue with the default
				$is_pp = ( '1' == $atts['page_protection'] );
				$value = $this->process_action($goalId, 'before', $is_pp);
			}
			
			$goal_content = apply_filters('b_a_before_content', $value, $goalId);
		}
		else
		{
			// see if a [after] was specified to override the goal's default
			if (has_shortcode($content, 'after')) { 
				$value = $this->process_before_or_after_shortcode('after', $content);
			}
			else { // no override was specified, so continue with the default
				$value = $this->process_action($goalId, 'after');
			}
			
			$goal_content = apply_filters('b_a_after_content', $value, $goalId);
			$goal_wrapper_classes[] = 'before-and-after-goal-complete';
		}

		// apply a filter and then combine the resulting list of classes
		$goal_wrapper_classes = apply_filters('b_a_after_goal_wrapper_classes', $goal_wrapper_classes, $goalId);		
		$wrapper_class_list = implode(' ', $goal_wrapper_classes);

		// wrap the goal content in its wrapper, apply a filter, and return it
		$wrapper_html = '<div class="%s">%s</div>';
		$goal_html = sprintf($wrapper_html, $wrapper_class_list, $goal_content);		
		return apply_filters('b_a_goal_html', $goal_html, $goalId);		
	}
	
	private function get_goal_name($atts)
	{
		$goalName = '';
		$goalId = intval($atts['id']);
		if ($atts['name'] !== '') {
			$goalName = $atts['name'];
		}
		else if ($goalId > 0) {
			// Base the goal name on the ID
			$goalName = 'Goal_ID_' . $goalId;
		}
		return $goalName;
	}
	
	private function process_before_or_after_shortcode($beforeOrAfter, $content, $apply_filters = true)
	{
		$extractedContent = $this->extract_shortcode($beforeOrAfter, $content);
		$trimmedContent = $this->trim_brs($extractedContent);
		if ($apply_filters) {
			$old_val = $this->root->in_widget;
			$this->root->in_widget = true;
			$trimmedContent = apply_filters('the_content', $trimmedContent);
			$this->root->in_widget = $old_val;
		}
		return $trimmedContent;
	}
	
	public function process_action($goalId, $beforeOrAfter, $page_protection = false)
	{
		if ($beforeOrAfter == 'before') {
			$context = 'before-values';
			$action = get_post_meta($goalId, '_goal_before_action', true);
		} else if ($beforeOrAfter == 'after') {
			$context = 'after-values';
			$action = get_post_meta($goalId, '_goal_after_action', true);
		}
		else {
			return ''; // TBD: throw error/exception?
		}
		
		$this->goalId = $goalId;

		switch($action)
		{
			case 'redirect_page':
				$value = $this->get_goal_setting_value($goalId, $context, $action);
				$targetPageId = intval($value);
				$targetURL = get_permalink($targetPageId);				
				//todo: make sure we're not already on this page, to prevent a loop
				if (!$this->is_current_url($targetURL)) {
					return $this->get_redirect_javascript($targetURL);
				} else {
					return '';
				}
			break;
			case 'redirect_url':
				$targetURL = $this->get_goal_setting_value($goalId, $context, $action);
				//todo: make sure we're not already on this page, to prevent a loop
				if (!$this->is_current_url($targetURL)) {
					return $this->get_redirect_javascript($targetURL);
				} else {
					return '';
				}
			break;

			case 'b_a_form':
				$value = $this->get_goal_setting_value($goalId, 'before-values', $action);
				$b_a_form_id = intval($value);
				$this->b_a_form_id = $b_a_form_id;
				$form = get_post($b_a_form_id);
				$submit_button_label = get_post_meta($b_a_form_id, 'submit_button_label', true);
				$metadata = array(
					'_before_after_goal_id' => $goalId,
				);
				//add_action('wp_footer', array( $this, "output_b_a_page_refresh_code_in_footer") );
				
				$output = $this->form_editor->set_form_id($b_a_form_id);
				$output = $this->form_editor->render_form( compact('metadata', 'submit_button_label' ) );
				
				
				
				return $output;
			break;

			case 'page_content':
				$value = $this->get_goal_setting_value($goalId, $context, $action);
				$targetPageId = intval($value);
				$content_post = get_post($targetPageId);
				$page_content = apply_filters('the_content', $content_post->post_content);
				return $page_content;
			break;

			case 'contact_form_7':
				$value = $this->get_goal_setting_value($goalId, 'before-values', $action);
				$contactFormId = intval($value);
				$this->contactFormId = $contactFormId;
				$form = get_post($contactFormId);
				add_action('wp_footer', array( $this, "output_page_refresh_code_in_footer") );
				if ( $page_protection ) {
					$old_val = $this->disable_ajax_refresh;
					$this->disable_ajax_refresh = true;
				}
				add_filter('wpcf7_form_elements', array( $this, "add_ba_goal_id_to_cf7_shortcode") ); // inside form
				$shortcode = '[contact-form-7 id="' . $contactFormId . '" title="' . $form->post_title . '"]';
				$output = do_shortcode($shortcode);						
				$this->contactFormId = 0;				
				remove_filter('wpcf7_form_elements', array( $this, "add_ba_goal_id_to_cf7_shortcode") );
				if ( $page_protection ) {
					$this->disable_ajax_refresh = $old_val;
				}
				return $output;
			break;

			case 'gravity_form':
				$value = $this->get_goal_setting_value($goalId, 'before-values', $action);
				$contactFormId = intval($value);
				//$form = get_post($contactFormId); // we dont support titles for GForms yet. TBD.
				add_filter( 'gform_submit_button', array( $this, 'add_ba_goal_id_to_gravity_forms_shortcode' ), 10, 2 );
				$shortcode = '[gravityform id="' . $contactFormId . '"]';
				$output = do_shortcode($shortcode);						
				remove_filter( 'gform_submit_button', array( $this, 'add_ba_goal_id_to_gravity_forms_shortcode' ), 10, 2 );
				return $output;
			break;
			case 'file_url':
				$targetURL = $this->get_goal_setting_value($goalId, $context, $action);
				$download_url = $this->generate_download_url($goalId, $targetURL);
				$link_text = $this->get_goal_setting_value($goalId, 'after-values', 'download_link_text', 'Please click here to complete your download.', false);
				$output = sprintf('<a class="download_link" href="%s" target="_blank">%s</a>', $download_url, $link_text);
				$old_val = $this->root->in_widget;
				$this->root->in_widget = true;
				$rendered = apply_filters('the_content', $output);
				$this->root->in_widget = $old_val;
				return $rendered;
			break;
			
			case 'default':
			case 'free_text':
				$freeText = $this->get_goal_setting_value($goalId, $context, 'free_text');
				$old_val = $this->root->in_widget;
				$this->root->in_widget = true;
				$rendered = apply_filters('the_content', $freeText);
				$this->root->in_widget = $old_val;
				return $rendered;
			break;
		}	
	}
	
	function add_ba_goal_id_to_cf7_shortcode($form)
	{
		if (!empty($this->goalId)) {
			$form_id = !empty($this->contactFormId)
					   ? $this->contactFormId
					   : 0;
			$form .= sprintf('<input type="hidden" id="_before_after_cf7_form_id" value="%d" />', $form_id);
			$form .= sprintf('<input type="hidden" id="_before_after_goal_id" name="_before_after_goal_id" value="%d" />', $this->goalId);
			if ( '1' == get_post_meta($this->goalId, 'contact_form_7_ajax_refresh', true) && empty($this->disable_ajax_refresh) ) {
				$form .= sprintf('<input type="hidden" id="_contact_form_7_ajax_refresh_%d" value="1" />', $this->goalId);
			}
		}
		return $form;
	}
	
	function output_page_refresh_code_in_footer()
	{
		$options = get_option( 'b_a_options' );
		$add_reload_code = isset( $options['reload_page_on_submit'] ) ? intval($options['reload_page_on_submit']) : 1;
		if ( !$add_reload_code ) {
			return;
		}
		$submit_js = <<<SUBMIT_JS
	<script type="text/javascript">
	document.addEventListener( 'wpcf7mailsent', function( event ) {
		form_id_input = jQuery('#_before_after_cf7_form_id');		
		goal_id_input = jQuery('#_before_after_goal_id');
	
		if ( form_id_input.length > 0 && form_id_input.val() == event.detail.contactFormId ) {
			var goal_id = goal_id_input.val();
			var goal_container = goal_id_input.parents('.before-and-after-goal:first');
			var ajax_refresh_input = jQuery('#_contact_form_7_ajax_refresh_' + goal_id);
			var do_ajax_refresh = ( ajax_refresh_input.length > 0 && '1' == ajax_refresh_input.val() );
			if ( do_ajax_refresh && 'function' == typeof(before_and_after_reload_goal) ) {
				before_and_after_reload_goal(goal_id, goal_container);
			}
			else {
				location.reload();
			}
		}
	}, false );
	</script>
SUBMIT_JS;
		echo $submit_js;
	}
	
	function add_ba_goal_id_to_gravity_forms_shortcode( $button, $form ) {
		if (!empty($this->goalId)) {
			$button .= sprintf('<input type="hidden" name="_before_after_goal_id" value="%d" />', $this->goalId);
		}
		return $button;
	}

	/* Checks whether the provided URL matches the current page's URL. Case-insensitive.
	 *
	 * @returns		bool	True if $targetURL matches the current page's URL, false if not
	 */
	function is_current_url($targetURL)
	{
		$currentURL = $_SERVER["REQUEST_URI"];
		
		// reconstruct URLs into only their Path + Query String, to allow for more reliable comparisons
		$parts_target = parse_url($targetURL);
		$parts_current = parse_url($currentURL);		
		$cmp_target  = ( !empty($parts_target['path']) ? $parts_target['path'] : '' ) . '?' . ( !empty($parts_target['query']) ? $parts_target['query'] : '' );
		$cmp_current = ( !empty($parts_current['path']) ? $parts_current['path'] : '' ) . '?' . ( !empty($parts_current['query']) ? $parts_current['query'] : '' );

		// compare the reconstructed URLs, and return whether they match
		return (strcasecmp($cmp_target, $cmp_current) === 0);
	}
	
	// returns the HTML/Javascript code needed to perform an immediate redirect to the specified URL
	function get_redirect_javascript($targetPageURL)
	{
		$script = '<script type="text/javascript">window.location.href = "' . esc_url_raw($targetPageURL) . '";</script>';
		return $script;	
	}
		
	/* Finds a the given $shortcode inside $content and return it, along with its contemts.
	 * Returns an empty string if $shortcode is not found within $content
	 */
	private function extract_shortcode($shortcode, $content)
	{
		// first verify that $shortcode is inside $content, before we start in with the regular expressions to extract it
		if (!has_shortcode($content, $shortcode)) {
			return '';
		}
		// extraction time!
		$pattern = get_shortcode_regex(); // this is a wp function, it returns a regex to match all shortcodes which are currently registered

		if (   preg_match_all( '/'. $pattern .'/s', $content, $matches )
			&& array_key_exists( 2, $matches )
			&& in_array( $shortcode, $matches[2] ) )
		{
			// shortcode is being used somewhere in this arrey (but the array could also contain other shortcodes, because of the regex we used). 
			// so look through the array and find it!
			
			// first step is to find the key's index, from $matches[2]
			$foundIndex = -1;
			foreach ($matches[2] as $index => $match) {			
				if ($match == $shortcode) {
					$foundIndex = $index;
					break;
				}
			}
			
			// if we found the key's index, look for the corresponding entry in $matches[0]
			// that will contain the entire shortcode, which is what we want to extract!
			if ($foundIndex >= 0 && isset($matches[0][$foundIndex])) {
				return $matches[0][$foundIndex];
			} else {
				return '';
			}
			
		} else {
			return '';
		}
	
	}	
	
	function generate_download_url($goalId, $file_url)
	{
		$goalName = 'Goal_ID_' . $goalId;
		$sessionKey_cid = 'goal_' . md5($goalName) . '_cid';
		$cid = isset($_SESSION[$sessionKey_cid]) ? intval($_SESSION[$sessionKey_cid]) : 0;
		$options = get_option( 'b_a_options' );

		// if file masking is disabled, just return the plain URL
		if ( isset($options['mask_real_file_url']) && 0 == $options['mask_real_file_url'] ) {
			return $file_url;
		}
		
		if ($cid > 0) {
			$metaKey = '_b_a_download_key';
			$download_key = get_post_meta($cid, $metaKey, true);
			if ($download_key && strlen($download_key) > 1) {
				$url = site_url( '?file_download=' . urlencode($download_key) );
				return $url;
			}
		}
		else {		
			// no conversion id was set (so not Pro)
			// so try to encode it by the goal's download key
			$goal_key = get_post_meta($goalId, '_b_a_download_key', true);

			if ( !empty($goal_key) ) {
				$url = site_url( '?file_download=' . urlencode($goal_key) );
				return $url;
			}

			// we weren't able to encode with the download key ofeither the conversion or Goal,			
			// so just return the plain URL. This could happen with an old goal, created in the 
			// free version, that hasn't been re-saved.
			return $file_url;
		}
	}
	
	/* Sort of a "mega trim" function that removes <br /> tags from the start and end of a string, along with a normal trim () for whitespace.
	 * Used to extract nested shortcodes without leaving a bunch of extra whitespace
	*/
	private function trim_brs($str)
	{
		$str = preg_replace('/(<br \/>)+$/', '', $str);
		$str = preg_replace('/^(<br \/>)*/', '', $str);
		$str = rtrim(trim($str), '<br />');  	
		return trim($str);
	}

	
	
	
}

?>