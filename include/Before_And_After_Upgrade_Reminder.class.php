<?php

	class Before_And_After_Upgrade_Reminder
	{
		
		function __construct()
		{
			add_action( 'admin_init', array($this, 'maybe_display_upgrade_message'), 1);				
			add_action( 'wp_ajax_before_and_after_dismiss_admin_message', array($this, 'dismiss_admin_message') );
	}
		
		function get_oldest_goal_date()
		{
			$oldest_post = get_posts( array(
				'post_type' => 'b_a_goal',
				'order' => 'ASC',
				'orderby' => 'date',
				'posts_per_page' => 1,
			) );
			
			return isset($oldest_post[0])
				   ? $oldest_post[0]->post_date
				   : '';
		}
		
		function maybe_display_upgrade_message( $hook )
		{
			// if viewing one of our settings pages,
			// and is an administrator,
			// and the user has been using the plugin for at least a month, 
			// ask for a review in the WP directory on our settings page
			$oldest_post_date = strtotime($this->get_oldest_goal_date());
			if ( current_user_can('administrator')
				 && !empty($oldest_post_date)
				 && ( $oldest_post_date < strtotime('1 month ago') ) 
				 && !$this->upgrade_message_dismissed()
			) {			 
				 add_action( 'admin_notices', array($this, 'display_upgrade_message') );
			}	
		}
		
		/*
		 * AJAX function to permanently dismiss an alert by ID
		 */
		function dismiss_admin_message()
		{
			$user_id = get_current_user_id();
			$alert_id = isset($_POST['alert_id'])
						? sanitize_text_field($_POST['alert_id'])
						: '';
						
			if ( !empty($user_id) && !empty($alert_id) ) {
				
				switch ( $alert_id ) {
					case 'notice-upgrade-message':
						update_user_meta( $user_id, 'b_a_dismiss_notice_upgrade_message', date('U') );
					break;
					
					default: 
					break;
				}
			}
			wp_die("OK");
		}	
		
		function upgrade_message_dismissed()
		{
			$user_id = get_current_user_id();
			if ( empty($user_id) ) {
				return false;
			}
			$upgrade_message_dismissed = get_user_meta($user_id, 'b_a_dismiss_notice_upgrade_message', true);
			return ( !empty($upgrade_message_dismissed) );
		}
		
		function display_upgrade_message()
		{
			
			$message = '<style>
				#before_and_after_upgrade_message_box h3::before { content: "\01F389"; margin-right: 10px }
				#before_and_after_upgrade_message_box h3 { color: #3b8dd4; }
				#before_and_after_upgrade_message_box p { margin-bottom: 12px; max-width: 1020px; }
			</style>';
			$message .= '<script type="text/javascript">
			var ezt_setup_upgrade_message = function () {
				var box = jQuery(\'#before_and_after_upgrade_message_alert\');
				if ( box.length == 0 ) {
					return;
				}
				box.on(\'click\', \'.notice-dismiss\', function () {
					jQuery.ajax({
						type:"POST",
						url: ajaxurl,
						data: { 
							action: "before_and_after_dismiss_admin_message",
							alert_id: "notice-upgrade-message",
						},
						success: function (data) {}
					});
				});
			};	
			jQuery(function () {
				ezt_setup_upgrade_message();
			});
			</script>';
			$message .= '<div id="before_and_after_upgrade_message_box">';
			$message .= sprintf( '<h3 style="">%s</h3>', __('Thanks for using Before&nbsp;&amp;&nbsp;After!') );
			$message .= sprintf( '<p style="font-size: 16px; color:green;">%s </p>', __('Now that you\'ve been using Before&nbsp;&amp;&nbsp;After for some time, we want to thank you by offering you a 25% discount on Before&nbsp;&amp;&nbsp;After&nbsp;Pro!') );
			$message .= sprintf( '<p>%s <strong>%s</strong> %s</p>', __('When you upgrade to '), 'Before&nbsp;&amp;&nbsp;After&nbsp;Pro', __(' you will instantly gain access to new features such as conversion tracking, geolocation, HubSpot integration, and more. Visit our website using the link below to learn more and to claim your discount.') );
			$upgrade_url = 'https://goldplugins.com/special-offers/upgrade-to-before-and-after-pro/?discount=onemonthbday&utm_campaign=plugin_one_month&utm_source=' . urlencode( site_url() );
			$message .= sprintf( '<p><a target=_"blank" href="%s" class="button button-primary">%s</a></p>', $upgrade_url, __('Save 25% on Your Upgrade Now!') );
			$message .= sprintf( '<p><em>%s</em> </p>', __('This is a one-time offer. If you don\'t wish to see it again simply click the close button.') );
			$message .= '</div>';
			
			?>
			<div class="notice notice-success is-dismissible" data-dismissible="notice-upgrade-message" id="before_and_after_upgrade_message_alert">
				<?php echo $message; ?>
			</div>
			<?php
		}
	}	
?>