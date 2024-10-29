function before_and_after_reload_goal (goal_id, goal_container) {
	// load goal content via AJAX	
	if ( typeof(before_and_after_vars) != 'undefined' )
	{
		jQuery.ajax({
			url: before_and_after_vars.ajax_url,
			data:({
				action: 'before_and_after_get_goal_content',
				goal_id: goal_id,
				r: Math.random()
			}),
			type: 'POST',
			dataType: 'json',
			success: function (resp) {
				// replace container HTML with response
				if ( resp.success && 'OK' == resp.success ) {
					goal_container.replaceWith(resp.html);
				}
			},
		});
	}	
	
};
