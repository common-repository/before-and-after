jQuery(function ()
{
	jQuery('#b_a_help .gp_code_example, #goal_shortcodes .gp_code_example').bind('click', function () {
		jQuery(this).trigger('focus').select();
	});

	var update_edit_goal_button = function (select) {
		select = jQuery(select);
		var form_id = select.val();
		var edit_form_link = select.parent().find('.edit_form_link');
		if ( form_id ) {
			var new_url = edit_form_link.data('href-template')
										.replace('_POST_ID_', form_id);
			edit_form_link.attr('href', new_url);
			edit_form_link.show();
		}
	};

	var b_a_form_select = jQuery('#goal_before select[name="before-values[b_a_form]"');
	if ( b_a_form_select.length > 0 )  {
		b_a_form_select.on('change', function (ev) {
			update_edit_goal_button(this);
		});
		
		// also run it once when we first reveal the form
		update_edit_goal_button(b_a_form_select);
	}
	
	jQuery('#b_a_help .gp_code_example, #goal_shortcodes .gp_code_example').bind('click', function () {
		jQuery(this).trigger('focus').select();
	});

});

var before_and_after_clear_conversion_cookies = function (btn, callback)
{
	if ( typeof(btn) == 'function' ) {
		callback = btn;
		btn = jQuery('<button>');
	}	
	
	btn = jQuery(btn);
	btn.attr('disabled', 'disabled');

	var icon = btn.find('.fa');
	icon.addClass('fa-spin');

	var data = { 
		'action': 'b_a_clear_cookies',
		'b_a_clear_cookies' : 'go',
		'is_ajax': 1
	};

	var handle_response = function (data, testData, jqXHR) {
		var throttle = 500; // small throttle, in ms
		setTimeout(
			function () 
			{
				if (data.msg) {
					// display the message
					msg_box = jQuery('#conversion_cookies_message');
					msg_box.html(data.msg).fadeOut(0).fadeIn();
					
					// clear any previous fade effects before applying a new one
					if ( typeof(msg_box.data('fade_timer') !== 'undefined') ) {
						clearTimeout( msg_box.data('fade_timer') );
					}
					
					// fade the message out after 15 seconds
					msg_box.data( 'fade_timer', setTimeout(function () {
						msg_box.fadeOut();
					}, 15000) );
				}
				
				// remove the spinning effect and re-enable the button
				icon.removeClass('fa-spin');
				btn.removeAttr('disabled');
			},
			throttle
		); 
	};
	
	jQuery.post(
		before_and_after_ajax.ajax_url,
		data,
		function (data, testData, jqXHR) {
			handle_response(data, testData, jqXHR);
			if ( typeof(callback) == 'function' ) {
				callback();
			}
		},
		'json'
	);
};

var b_a_export_conversions = function()
{
	var loc = location.href;        
    loc += loc.indexOf("?") === -1 ? "?" : "&";
    location.href = loc + "b_a_export_conversions=1";	
	
}

jQuery(function () {
	jQuery('#wpadminbar').on('click', '#wp-admin-bar-before-and-after-reset-goals a.ab-item', function (e) {
		var do_reset = confirm(before_and_after_ajax.confirm_reset_message);
		if ( do_reset ) {
			before_and_after_clear_conversion_cookies(function () {
				// remove hash if present or reload will not have any effect
				var no_hash = window.location.href.split('#')[0];
				window.location = no_hash;
			});
			e.preventDefault();			
		}
	});
});	
