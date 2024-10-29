// init on document.ready
jQuery( function () {

	// track whether there are unsaved changes to be saved
	var is_dirty = false;
	var next_field_index = jQuery('input[name="next_field_index"]').val();
	
	var save_next_field_index = function(new_value)
	{
		next_field_index = new_value; // set "global"
		var nfi = jQuery('input[name="next_field_index"]');
		if ( nfi.length == 0) {
			var new_input = '<input type="hidden" name="next_field_index" />';
			nfi = jQuery(new_input);
			$('#post').append(nfi);
		}
		nfi.val(next_field_index);
	};
	
	var setup_template_selector = function()
	{
		var start_page = jQuery('#form_editor_start_page');
		if ( start_page.length == 0 ) {
			return;
		}		
		var select_template = jQuery('#form_editor_template_select', start_page);
		var select_template_button = jQuery('#btn_form_editor_use_selected_template', start_page);
		var create_from_empty_button = jQuery('#btn_form_editor_start_with_blank', start_page);
		
		select_template_button.on('click', function () {		
			var chosen_template = select_template.val();
			if ( chosen_template ) {
				create_from_template(chosen_template, this);
			}
		});
		
		create_from_empty_button.on('click', function () {		
			show_form_editor();
		});		
		
		
	};
	
	var create_from_template = function(template_name, btn) {
		btn = jQuery(btn);
		btn.html('Loading...');
		
		// ajax to the server
		var params = {
			'action': 'b_a_load_form_from_template',
			'template': template_name
		};
		
		jQuery.post(ajaxurl, params, function (resp) {
			// load fields from json
			jQuery('.gp_custom_forms_all_fields_wrapper').html(resp.output);
			save_next_field_index( resp.next_field_index );
			show_form_editor();
		}, 'json');		
	};
	
	
	var show_form_editor = function() {
		jQuery('#form_editor_select_starting_option').hide();
		jQuery('#form_editor_wrapper').show();
	};
	
	var setup_toolbox = function()
	{
		// make the toolbox a jqui sortable
		jQuery( ".gp_custom_forms_editor_toolbox .available_fields" ).sortable({
			start: function (event, ui) {
				// store the original position in the toolbox so we can restore to this spot
				jQuery(ui.item).data("start_index", ui.item.index());				
			},
			connectWith: ".gp_custom_forms_all_fields_wrapper",
			helper: function (item) {
				return jQuery(item.target).parents('.custom_field:first')
										  .clone()
										  .addClass('gp_custom_forms_dragging')
										  .width( jQuery('.gp_custom_forms_all_fields_wrapper').width() );
			},
			revert: true,
			remove: function (event, ui) {

				var clone = ui.item.clone();

				clone.find('.field_details > input, input[type="hidden"]').each(function () {
					
					// replace [i] in field names			
					var field_name = jQuery(this).attr('name');
					var new_name = field_name.replace('[' + next_field_index + ']', '[i]');
					jQuery(this).attr('name', new_name);

					// replace _i in field ids
					var field_id = jQuery(this).attr('id');
					if (field_id) {
						var new_id = field_id.replace('_' + next_field_index, '_i');
						jQuery(this).attr('id', new_id);
					}

					// replace _i in the value of the [name] field
					if ( jQuery(this).attr('name').indexOf('[name]') >= 0 ) {
						var field_value = jQuery(this).attr('value');
						var new_value = field_value.replace('_' + next_field_index, '_i');
						jQuery(this).attr('value', new_value);
					}
				});
				
				//clone.prependTo('.gp_custom_forms_editor_toolbox .available_fields');
				var start_index = jQuery(ui.item).data("start_index");
				if ( start_index > 0 ) {
					clone.insertAfter('.gp_custom_forms_editor_toolbox .available_fields > li:nth-child(' + start_index + ')');
				} else {
					jQuery('.gp_custom_forms_editor_toolbox .available_fields').prepend(clone);
				}
				
				
				var li_html = ui.item.find('.gp_custom_field_template > ul > li:first')[0].outerHTML;
				ui.item = jQuery(li_html);
				jQuery('.gp_custom_forms_all_fields_wrapper .highlight_field').removeClass('highlight_field');
				ui.item.addClass('highlight_field');
			}
		});
		jQuery( ".gp_custom_forms_editor_toolbox" ).find( "ul, li" ).disableSelection();

		/* 
		 * Makes the list of fields a jqui sortable
		 */
		jQuery( ".gp_custom_forms_all_fields_wrapper" ).sortable({
			helper: function (item) {
				var p = jQuery(item.target).parents('.gp_custom_forms_edit_field_wrapper:first');
				p.find('input[type="radio"]').each(function (i) {
					jQuery(this).attr('data-was-checked', jQuery(this).attr('checked') );
				});
				return  p.clone();
			},
			xhelper: "clone",
			update: function(event, ui) {
				reindex_all_options();				
				set_dirty_flag();
			},
			revert: "invalid",
			receive: function (event, ui) {
				// replace [i] in field labels' for attributes
				ui.item.find('.field_details label').each(function () {					
					var old_attr = jQuery(this).attr('for');
					if ( old_attr) {
						var new_attr = old_attr.replace('[i]', '[' + next_field_index + ']');
						jQuery(this).attr('for', new_attr);
					}
				});

				ui.item.find('.field_details input, input[type="hidden"]').each(function () {
					
					// replace [i] in field names			
					var field_name = jQuery(this).attr('name');
					if (field_name) {					
						var new_name = field_name.replace('[i]', '[' + next_field_index + ']');
						jQuery(this).attr('name', new_name);
					}
					
					// replace _i in field ids
					var field_id = jQuery(this).attr('id');
					if (field_id) {
						var new_id = field_id.replace('_i', '_' + next_field_index);
						jQuery(this).attr('id', new_id);
					}
					
					if ( jQuery(this).attr('name') && jQuery(this).attr('name').indexOf('[name]') >= 0 ) {
						var field_value = jQuery(this).attr('value');
						if (field_value) {
							var new_value = field_value.replace('_i', '_' + next_field_index);
							jQuery(this).val(new_value);
						}
					}
				});
				
				ui.item.find('.gp_custom_forms_edit_field_wrapper')
					   .data('field-index', next_field_index)
					   .attr('data-field-index', next_field_index);
				ui.item.find('input[type="radio"]').each(function (i) {
					jQuery(this).attr('was-checked', jQuery(this).attr('checked') );
				});
				
				toggle_field_collapsed(ui.item);
				
				reindex_all_options();				
				next_field_index++;
				set_dirty_flag();
				
				save_next_field_index(next_field_index);
				
			},
			handle: ".gp_custom_forms_drag_handle",
		});
		
		// set the dirty flag when the user types anything into any input
		jQuery( ".gp_custom_forms_all_fields_wrapper").on('keydown', 'input', function() {
			set_dirty_flag();
		});

		// enable collapse/expand fields when plus/minus icon is clicked
		jQuery( ".gp_custom_forms_all_fields_wrapper").on('click', '.gp_custom_forms_collapse_toggle', function() {
			toggle_field_collapsed( jQuery(this).parents('.gp_custom_forms_edit_field_wrapper:first') );
		});

		/* Make double clicking on the field title expand/collapse the field
		 *
		 * IMPORTANT: we are using the mousedown event and checking the value of
		 * event.detail to detect double clicks, instead of using the dblclick 
		 * event. This is so that we can prevent text selection on double-click.
		 */
		jQuery( ".gp_custom_forms_all_fields_wrapper").on('mousedown', '.field_title', function(e) {
			if ( e.detail > 1 ) {
				if (e.detail % 2 == 0) {
					toggle_field_collapsed( jQuery(this).parents('.gp_custom_forms_edit_field_wrapper:first') );
				}
				
				// prevent normal double click behavior
				e.preventDefault();
				return false;
			}
		});
		
		// enable "Delete Field" link
		jQuery( ".gp_custom_forms_all_fields_wrapper").on('click', '.delete_field_link', function(e) {
			var confirm_delete = confirm('Are you sure you wish to delete this field?');
			if (confirm_delete) {
				var field_wrapper = jQuery(this).parents('.gp_custom_forms_edit_field_wrapper:first');
				var field_wrapper = jQuery(this).parents('.custom_field:first');
				field_wrapper.slideUp(function () {
					jQuery(this).remove();
				});
				set_dirty_flag();
			}
			
			// prevent normal link behavior
			e.preventDefault();
			return false;
		});
	};
	
	/* Shows or hides the details for a custom field 
	 *
	 * @param trg The target <LI> representing the field to be toggled.
	 */	
	var toggle_field_collapsed = function(trg) {		
		var icon = jQuery(trg).find('.gp_custom_forms_collapse_toggle .dashicons');
		var field_details_wrapper = jQuery(trg).find('.field_details_wrapper');
		
		if ( icon.hasClass('dashicons-minus') ) {
			icon.removeClass('dashicons-minus')
				.addClass('dashicons-plus');
			jQuery(trg).removeClass('gp_custom_forms_expanded')
					   .addClass('gp_custom_forms_collapsed');
			field_details_wrapper.slideUp();
		} else {
			icon.removeClass('dashicons-plus')
				.addClass('dashicons-minus');
			jQuery(trg).removeClass('gp_custom_forms_collapsed')
					   .addClass('gp_custom_forms_expanded');
			field_details_wrapper.slideDown();
		}	
	};
	
	// collect field data, serialize, and return it
	// returns field data (string) if a form was found, empty string if not
	var get_field_data = function() {
		var fields_form = jQuery('.gp_custom_forms_form');
		
		return fields_form.length > 0
			   ? fields_form.serialize()
			   : '';
	};

	// sends field_data to server via AJAX
	// returns true/false on success/fail
	var post_field_data = function(field_data, callback) {				
		var data = {
			'action': 'wp_ajax_gp_custom_forms_save_fields',
			'form_data': field_data,
			'next_field_index': next_field_index,
		};
		// We can also pass the url value separately from ajaxurl for front end AJAX implementations
		jQuery.post(ajaxurl, data, function(response) {
			var result = (response == '1');
			if (typeof(callback) == 'function') {
				callback(result);
			}
		});
		
		
	};

	// shows a checkmark, message that the fields were saved successfully
	var show_success_message = function() {
		// look for existing message boxes first, and remove them
		jQuery('#gp_custom_forms_save_message').remove();
		
		// build new message box and insert it after the save button
		var message_box = jQuery('<div id="gp_custom_forms_save_message">&#10003; ' + gp_custom_forms_strings.success_message + '</div>');
		jQuery('#gp_custom_forms_save_button').after(message_box);
		
		// fade out the messge after 10 seconds
		setTimeout(function () {
			message_box.fadeOut();
		}, 10000);
		
	};
	
	/* The save button should send the form data to the server, and show a 
	 * success message if all was successful. Will also clear the dirty flag
	 * on a successful save.
	 */
	var setup_save_button = function()
	{
		var save_button = jQuery('#gp_custom_forms_save_button');
		jQuery('#gp_custom_forms_save_button').on('click', function () {
			// disable save button			
			save_button.attr('disabled', 'disabled');

			// collect data
			var field_data = get_field_data();
			
			// save data to server
			post_field_data(field_data, function (result) {
				if (result) {
					show_success_message();
					clear_dirty_flag();
				}

				setTimeout(function () {
					// re-enable save button
					save_button.removeAttr('disabled');
				}, 200);
			});
		});
	};
	
	/* Init live updates of field titles when the corresponding input changes 
	 */	
	var update_field_title = function(input) {
		var new_val = jQuery(input).attr('value');
		if ( !new_val ) {
			new_val = gp_custom_forms_strings.unnamed_field;
		}
		jQuery(input).parents('li:first').find('.field_title .display_title').html(new_val);
	};
	
	/* Init live updates of field titles when the corresponding input changes 
	 */	
	var setup_title_updates = function() {
		// run when fields change
		jQuery('.gp_custom_forms_editor').on('keyup', '.update_field_title', function() {
			update_field_title(this);
		});
		
		// once run on load for each field
		jQuery('.gp_custom_forms_editor').find('.update_field_title').each(function() {
			update_field_title(this);
		});
	};
	
	/* Sets the global is_dirty flag to true so that users will be asked if they
	 * really want to leave the page with unsaved data.
	 *
	 * Can be cleared by calling clear_dirty_flag()
	 */	
	var set_dirty_flag = function() {
		is_dirty = true;
	};

	/* Clears the dirty flag so that users will *not* be asked if they really
	 * want to leave the page with unsaved data.
	 */	
	var clear_dirty_flag = function() {
		is_dirty = false;		
	};
	
	/* If the is_dirty flag is set when the user navigates away from the page,
	 * ask the user if they really want to leave first.
	 */	
/*
	jQuery(window).bind('beforeunload', function() {
		if ( is_dirty ) {
			// NOTE: this message will be ignored in new browsers, and replaced
			// with a default message.
			return 'Are you sure you want to leave this page? You have unsaved changes that will be lost.';			
		} else {
			// return undefined instead of false to prevent weird behaviors in 
			// old browsers (i.e., showing a dialog box with the word "false")
			return undefined;
		}
	});
*/	
	var add_row_to_options_table = function(btn) {
		var panel = jQuery(btn).parents('.gp_custom_forms_options_panel:first');
		var options_table = panel.find('.gp_custom_forms_options_table');
		var row_count = options_table.find('tbody > tr').length;
		var row_tmpl = panel.find('.gp_custom_forms_options_row_tmpl')
							.data('tmpl')
							.replace('[i]', row_count);
		options_table.find('tr:last')
					 .after(row_tmpl);
		reindex_all_options();
	};
	
	var delete_row_from_options_table = function(btn) {
		var row = jQuery(btn).parents('tr:first');
		var options_table = jQuery(btn).parents('.gp_custom_forms_options_panel:first')
									   .find('.gp_custom_forms_options_table');
		row.remove();
		reindex_all_options();
	};
	
	var reindex_all_options = function () {
		// reindex on_off inputs (checkboxes)
		jQuery('.gp_custom_forms_all_fields_wrapper .gp_custom_forms_on_off_radio').each(function (index) {
			reindex_checkbox_inputs( this );
		});
		// reindex options table inputs (radios and selects)
		jQuery('.gp_custom_forms_all_fields_wrapper .gp_custom_forms_options_table').each(function (index) {
			reindex_options_table_inputs( this );
		});
		fix_radio_states();
	};
	
	/*
	 * Renames the inputs in checkbox inputs with incremental array keys.
	 * Note: this changes the name attributes of each checkbox input
	 */
	var fix_radio_states = function () {
		jQuery('.gp_custom_forms_edit_field_wrapper input[type="radio"]').each(function (i) {
			if ( jQuery(this).data('was-checked') == 'checked' ) {
				jQuery(this).removeAttr('data-was-checked');
				jQuery(this).data('was-checked', undefined);
				jQuery(this).attr('checked', 'checked');
			}
		});		
	};
	
	/*
	 * Renames the inputs in checkbox inputs with incremental array keys.
	 * Note: this changes the name attributes of each checkbox input
	 */
	var reindex_checkbox_inputs = function (on_off_wrapper) {
		var checkboxes = jQuery(on_off_wrapper).find('input[type="radio"]');
		var field_index = jQuery(on_off_wrapper).parents('.gp_custom_forms_edit_field_wrapper:first')
									  .data('field-index');
		var save_checked = [];
		
		checkboxes.each(function(i) {
			if ( jQuery(this).attr('checked') == 'checked' ) {
				jQuery(this).attr('data-was-checked', 'checked');
			}
			jQuery(this).attr('name', 'custom_forms[' + field_index + '][default]');
		});
	};
		
	/*
	 * Renames the inputs in an options table to have incremental array keys.
	 * Note: this changes the name attributes of each value + label input
	 */
	var reindex_options_table_inputs = function (options_table) {
		jQuery(options_table).find('tr').each(function (index) {
			var radio_input = jQuery(this).find('.option_default');
			var value_input = jQuery(this).find('.option_value');
			var label_input = jQuery(this).find('.option_label');
			var field_index = jQuery(this).parents('.gp_custom_forms_edit_field_wrapper:first')
										  .data('field-index');
			radio_input.attr('name', 'custom_forms[' + field_index + '][default]');
			radio_input.val(index - 1);
			value_input.attr('name', 'custom_forms[' + field_index + '][options][' + (index - 1) + '][value]');
			label_input.attr('name', 'custom_forms[' + field_index + '][options][' + (index - 1) + '][label]');			
		});
	};
	
	/*
	 * Initialize the Edit Options Tables
	 *   - Enable the Add Row Button
	 *   - Enable the Delete Row Buttons
	 * 	 - Set the name attributes for the value and label inputs
	 */
	var setup_edit_options_tables = function() {
		jQuery('.gp_custom_forms_all_fields_wrapper').on('click', '.gp_custom_forms_options_panel .delete_options_row', function (e) {
			delete_row_from_options_table(this);
			e.preventDefault();
			return false;
		});
		jQuery('.gp_custom_forms_all_fields_wrapper').on('click', '.gp_custom_forms_options_panel .add_options_row', function (e) {
			add_row_to_options_table(this);
			e.preventDefault();
			return false;
		});
		reindex_all_options();
	};
	
	setup_template_selector();
	setup_toolbox();
	setup_save_button();
	setup_title_updates();
	setup_edit_options_tables();
});