jQuery(function () {

	var inject_tabs = function (container) {
		var tabs = jQuery('<ul id="b_a_form_settings_tabs">' +
					'<li class="b_a_form_settings_tab"><a href="#form_editor">Form Editor</a></li>' +
					'<li class="b_a_form_settings_tab"><a href="#form_settings">Settings</a></li>' +
				'</ul>');
		container.prepend(tabs);		
		tabs.parent().tabs();
	};
		
	var setup_tabs = function () {
		var container = jQuery('#normal-sortables');
		inject_tabs(container);
	};

	setup_tabs();
});