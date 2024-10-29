<?php
// Before &amp; After Welcome Page template

ob_start();
$learn_more_url = 'https://goldplugins.com/special-offers/upgrade-to-before-after-pro/?utm_source=before_and_after_free&utm_campaign=welcome_screen_upgrade&utm_content=col_1_learn_more';
$settings_url = menu_page_url('before-and-after-settings', false);
$pro_registration_url = $settings_url . '#tab-pro_registration';
$utm_str = '?utm_source=before_and_after_free&utm_campaign=welcome_screen_help_links';
$new_post_link = admin_url('post-new.php?post_type=b_a_goal&guided_tour=1');
?>


<p class="aloha_intro"><strong>Thank you for installing Before &amp; After!</strong> This page is here to help you get up and running. If you're already familiar with Before &amp; After, you can skip it and <a href="<?php echo $settings_url; ?>">continue to the Basic Settings page</a>. </p>
<p class="aloha_tip"><strong>Tip:</strong> You can always access this page via the <strong>Before &amp; After &raquo; About Plugin</strong> menu.</p>

<h3>Introduction To Before & After: Goals</h3>
<p>Before &amp; After can help you create all kinds of lead capture forms, age gates, Terms of Service pages, and other kinds of gated content. What all of these have in common is that they feature showing one thing to the user <strong>Before</strong> they complete a desired action (e.g., filling out your form), and then showing different content to them (e.g., a whitepaper, discount, or other reward) <strong>After</strong> they've completed that action.</p>
<p>With Before &amp; After, all of this is done using <strong>Goals</strong>.  You'll create Goals which have both a Before state (e.g, your newsletter sign-up form) and an After state (e.g., the link to a PDF). Goals will automatically track who has completed the goal, and show the right content to the right people.</p>
<h4>Create Your First Goal Now</h4>
<p>Click the button below to create your first Goal. It will only take a moment, and its easy to understand.
<br>
<br>
<a href="<?php echo $new_post_link; ?>" class="button">Create A New Goal &raquo;</a></p>
<br>
<hr>
<br>
<div class="three_col">
	<div class="col">
		<?php if ($is_pro): ?>
			<h3>Before &amp; After Pro: Active</h3>
			<p class="plugin_activated">Before &amp; After Pro is licensed and active.</p>
			<a href="<?php echo $pro_registration_url; ?>">Registration Settings</a>
		<?php else: ?>
			<h3>Upgrade To Pro</h3>
			<p>Before &amp; After Pro is the Professional, fully-functional version of Before &amp; After, which features technical support and access to all Pro&nbsp;features.</p>
			<a class="button" href="<?php echo $learn_more_url; ?>">Click Here To Learn More</a>
		<?php endif; ?>
	</div>
	<div class="col">
		<h3>Getting Started</h3>
		<ul>
			<li><a href="<?php echo $new_post_link; ?>">Click Here To Add Your First Goal</a></li>
			<li><a href="https://goldplugins.com/documentation/before-after-documentation/before-after-pro-configuration-and-usage-instructions/<?php echo $utm_str; ?>">Getting Started With Before &amp; After</a></li>
			<li><a href="https://goldplugins.com/documentation/before-after-documentation/how-to-create-a-lead-capture-form-with-before-after-pro/<?php echo $utm_str; ?>">How To Create A Lead Capture Form</a></li>
			<li><a href="https://goldplugins.com/documentation/before-after-documentation/before-after-faqs/<?php echo $utm_str; ?>">Frequently Asked Questions (FAQs)</a></li>
			<li><a href="https://goldplugins.com/contact/<?php echo $utm_str; ?>">Contact Technical Support</a></li>
		</ul>
	</div>
	<div class="col">
		<h3>Further Reading</h3>
		<ul>
			<li><a href="https://goldplugins.com/documentation/before-after-documentation/<?php echo $utm_str; ?>">Before &amp; After Documentation</a></li>
			<li><a href="https://wordpress.org/support/plugin/before-and-after/<?php echo $utm_str; ?>">WordPress Support Forum</a></li>
			<li><a href="https://goldplugins.com/documentation/before-after-documentation/before-after-changelog/<?php echo $utm_str; ?>">Recent Changes</a></li>
			<li><a href="https://goldplugins.com/<?php echo $utm_str; ?>">Gold Plugins Website</a></li>
		</ul>
	</div>
</div>

<div class="continue_to_settings">
	<p><a href="<?php echo $settings_url; ?>">Continue to Basic Settings &raquo;</a></p>
</div>

<?php 
$content =  ob_get_contents();
ob_end_clean();
return $content;