var gp_before_and_after = (function () {
	var my_name = "Before and After";
	
	var say_hello = function () {
		alert("Hello! My name is " + my_name + ", nice to meet you.");
	};
	
	var find_goals_to_complete = function () {
		if ( typeof(before_and_after_user_vars) != 'undefined' )
		{
			if ( jQuery && before_and_after_user_vars.goal_to_complete && before_and_after_user_vars.complete_nonce )
			{
				jQuery.ajax({
					url: before_and_after_user_vars.ajaxurl,
					data:({
						action: 'before_and_after_complete_goal',
						goal_id: before_and_after_user_vars.goal_to_complete,
						nonce: before_and_after_user_vars.complete_nonce,
						r: Math.random()
					}),
					type: 'POST'
				});
			}
		}
	};

	var set_goal_cookies = function () {
		
	};
	
	find_goals_to_complete();
	
	// expose these functions publicly
	return {
		say_hello,
		set_goal_cookies
	}	
})();