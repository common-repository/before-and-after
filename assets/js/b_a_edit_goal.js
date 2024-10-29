jQuery(function () {

	/* Shows the secondary fields corresponding to the value of 'id'.
	 * Hides any other secondary fields.
	 *
	 * Note: the <div> containing the secondary fields needs to have an id 
	 * of the form: "{before_or_after}_options_{id}"
	 *
	 * @param string id The fragment containing the ID of the fields to show.
	 *				    Should be the value of the selected option box 
	 *					(e.g., 'free_text')
	 *
	 * @param string before_or_after Specifies which fields are being targeted.
	 *								 Must be either 'before' or 'after'
	 */
	var show_secondary_fields = function (id, before_or_after) {
		var p = jQuery('#goal_' + before_or_after);
		p.find('.secondary_fields').hide();
		p.find('#' + before_or_after + '_options_' + id).show();
	};
	
	/* Shows the Instructions text corresponding to the value of 'id'.
	 * Hides any other Instructions text.
	 *
	 * Note: the <div> containing the instructions needs to have an id 
	 * of the form: "instructions_{id}"
	 *
	 * @param string id The fragment containing the ID of the selected field
	 *					(e.g., 'free_text')
	 */
	var show_instructions = function (id) {
		id = id || 'no_selection';
		jQuery('.before_after_goal_instructions').hide();
		jQuery('#instructions_' + id).show();
	};
	
	/* When an option is selected in the Before/After meta boxes, show the
	 * corresponding secondary fields. (e.g,, if "Contact Form 7" is chosen
	 * in the Before meta box, we need to show the options to choose which
	 * Contact Form 7 form to use.
	 */
	var setup_secondary_fields = function () {
		jQuery('#before_action_select').on('change', function () {
			var id = jQuery(this).val();
			show_instructions(id);
			show_secondary_fields( id, 'before' );
		});
		
		jQuery('#after_action_select').on('change', function () {
			var id = jQuery(this).val();
			show_secondary_fields( id, 'after' );
		});
		
		// set initial state of Before meta box and instructions
		var before_id = jQuery('#before_action_select').val();
		show_instructions( before_id );
		show_secondary_fields( before_id, 'before' );
		
		// set initial state of After meta box
		var after_id = jQuery('#after_action_select').val();
		show_secondary_fields( after_id, 'after' );
	};
	
	// initialize the dropdown menus and fields
	setup_secondary_fields();
	
	/* 
	 * When the Reset Download Key button is clicked send off an AJAX request
	 * to reset the key. If successful, show the success message.
	 */
	var setup_reset_download_key_buttons = function () {
		jQuery('#goal_after').on('click', '#before_after_reset_download_key', function () {
			var goal_id = jQuery(this).data('goal-id');
			var nonce = jQuery(this).data('nonce');
			
			jQuery.ajax({
				url: ajaxurl,
				data:({
					action: 'before_and_after_reset_download_key',
					goal_id: goal_id,
					nonce: nonce,
					r: Math.random()
				}),
				type: 'POST',
				success: function (resp) {
					// replace container HTML with response
					if ( 'OK' == resp ) {
						jQuery('#before_after_reset_download_key_success_message').fadeIn();
						setTimeout(function () {
							jQuery('#before_after_reset_download_key_success_message').hide();
						}, 5000);
					}
				},
			});			
			
			
		});
		
	};
	
	// init Reset Download Key buttons
	setup_reset_download_key_buttons();
});