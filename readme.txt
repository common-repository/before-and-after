=== Before And After: Lead Capture Forms For Wordpress ===
Plugin Name: Before & After
Contributors: ghuger, richardgabriel
Tags: lead capture, lead capture form, lead capture plugin, protect content, protected content, gated content, hidden content, protected downloads, click wrap, click wrapper, tos wrap, tos wrapper, copyright notice, copyright wrapper
Requires at least: 4.5.0
Tested up to: 5.8
Stable tag: 3.9
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create Lead Capture Forms and other kinds of goals to protect your content and files. Generate leads from your downloads.

== Description ==

Before & After is a lead capture forms plugin for WordPress. It allows a webmaster to require visitors to complete a goal, such as filling out a contact form, before viewing the contents of a page or downloading a file. 

The plugin automatically tracks which visitors have completed your goals, and shows the appropriate content to each visitor. For example, it can show a form to visitors until they have completed the form, at which point they will be given a link to download a special file or whitepaper.

Before & After can be used with any plugin that uses shortcodes, but works especially well with Contact Form 7 and Gravity Forms.


= Protect Pages with Goals =

With Before & After, you can require your visitors to complete one of your Goals before viewing a given page. This functionality is especially useful when you want to ensure visitors read a Terms Of Service, Copyright Notice, or other important message before viewing a given page or bit of content.

Once you've installed Before & After, simply edit the Page or Post in WordPress, and then use the Before & After meta box to configure Page Protection.

[More Information on Page Protection](https://goldplugins.com/documentation/before-after-documentation/how-to-setup-page-protection/?utm_source=wp_readme&utm_campaign=b_a_page_protection)


= Create Lead Capture Forms with Contact Form 7, Gravity Forms, or Your Favorite Forms Plugin! =

Before & After Pro integrates directly with [Gravity Forms](https://goldplugins.com/documentation/before-after-documentation/how-to-create-a-lead-capture-form-using-gravity-forms-and-before-after-pro/?utm_source=wp_readme&utm_campaign=b_a_lead_capture_gforms) and [Contact Form 7](https://goldplugins.com/documentation/before-after-documentation/how-to-create-a-lead-capture-form-using-contact-form-7-and-before-after-pro/?utm_source=wp_readme&utm_campaign=b_a_lead_capture_cf7). Any forms you create in Contact Form 7 (CF7) or Gravity Forms will be available in Before & After, to create lead capture forms!

Not using Contact Form 7 or Gravity Forms? That's OK - Before & After can [create Lead Capture forms with any forms plugin](https://goldplugins.com/documentation/before-after-documentation/how-to-configure-before-after-to-work-with-any-forms-plugin/?utm_source=wp_readme&utm_campaign=b_a_lead_capture_any).

Check out our [documentation](https://goldplugins.com/documentation/before-after-documentation/?utm_source=wp_readme&utm_campaign=b_a_docs) instructions for more information.


= One Plugin, Tons of Uses = 

Using this simple plugin, any number of scenarios are possible:

 - **Lead Capture Forms:** Ask a visitor to signup for your newsletter in return for a free download, special report, or whitepaper.
 
 - **Protected Downloads:** Ask visitors to complete a form or other Goal before downloading your file. If they share the link, the recipient will have to complete the form as well.
 
 - **Protected Content:** Use Before & After to hide content from users until they have completed your form, or another goal. Hidden content will only be visible to those who have already completed your Goal.
 
 - **Terms Of Service Pages:** Make sure a visitor reads the terms of service first. Once they have read the TOS once, they may view any other page.
 
 - **Age Gates:** Make the visitor confirm their age before browsing a given page. 
 
 - **Copyright Notices:** Inform visitors of the copyright of a particular piece of content before allowing them to view it.
 
 - **Instructions In Series:** Make sure that a visitor reads a series of instructions in sequence. If they land on a later page, ask them to start over.
 
 - **Guided Product Tours:** Show your users the screens of your product in a sequenced progression.
 
 - **HubSpot Tracking:** Capture lead data and send it to HubSpot (requires Pro).

There are many other possibilities. By offering Wordpress webmasters a simple way to gate content we hope to provide a useful tool for many scenarios.

= Upgrade to Pro for For Advanced Features and Support =

The GoldPlugins team does not provide direct support for the Before & After plugin on the WordPress.org forums. One on one email support is available to people who have purchased Before & After Pro only. Before & After Pro also includes conversion tracking, email notifications, submitting data to HubSpot, and other advanced features. You should [upgrade today!](http://goldplugins.com/our-plugins/before-and-after/upgrade-to-before-and-after-pro/?utm_source=wp&utm_campaign=desc_upgrade1 "Upgrade to Before & After Pro")

[Upgrade To Before & After Pro](http://goldplugins.com/our-plugins/before-and-after/upgrade-to-before-and-after-pro/?utm_source=wp&utm_campaign=desc_upgrade2)


== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the contents of `/before-and-after/` to the `/wp-content/plugins/` directory
2. Activate the Before And After Lead Capture Plugin through the 'Plugins' menu in WordPress
3. Read the Instructions.

= Introduction: How Does This Plugin Work? =

Before & After is a lead capture plugin that lets you offer your users something in exchange for their information. It can also be when you need to make sure your users read your Terms of Service, verify their age before entering your website, or otherwise need to see one thing, and then another.

To achieve this, Before & After uses what we call goals. A goal is simply an action, or a "gate", that your users need to pass before they will be allowed to see your protected content.

A user completes a Goal by simply encountering the [complete_goal] shortcode. You will have placed it on your Thank You page, on the terms page, or whatever other page you need the user to view to signify that they have completed the goal.

= How To Setup A New Goal =

To create a new Goal, simply follow these steps:
1. Under the Before & After menu, select Goals. This will bring up a page which lists all of your goals.
2. Click the "Add A New Goal" button
3. Give your Goal a title, and then fill out the Before & After sections. When you are done, click the Publish button.
4. Your goal has been created! Copy the [goal] shortcode from the Edit Goal screen you are currently viewing, and go paste it onto the on which page you'd like the goal to appear.

= How To Have A Visitor Complete A Goal =
		
Simply add a shortcode like this to the final step of your goal funnel. For example, you could place it on the "Thank You" page from a contact form.

	[complete_goal id="82"]

_(Replace the number 82 with the id of your goal. Tip: you can find the shortcode for each goal on the Goals page.)_

= Shortcode Reference =

**Goal Shortcode**

Add this shortcode to any page or post to display your goal there.
	
	[goal id="82"]

**Complete Goal Shortcode**

Add this shortcode to the page which signifies that a visitor has completed the goal. For example, you could put this on a "Thank You For Contacting Us" page.

	[complete_goal id="82"]

= Integrating with Contact Form 7 =

If you have the Contact Form 7 plugin installed, you'll be able to select any Contact Form 7 form as the Before option for your Goals. Simply Add a new Goal or edit an existing one, and you'll see your Contact Form 7 forms listed.
Important: be sure to redirect your Contact Form 7 form to a thank you page, and to add the complete goal shortcode to the Thank You page. <a href="http://contactform7.com/redirecting-to-another-url-after-submissions/" target="_blank">Refer to these instructions if you are unsure how to do this.</a>

= Integrating with Gravity Forms =

If you have the Gravity Forms plugin installed, you'll be able to select any Gravity Form you have created as the Before option for your Goals. Simply Add a new Goal or edit an existing one, and you'll see your Gravity Forms forms listed.
Important: be sure to redirect your Gravity Form to a thank you page, and to add the complete goal shortcode to the Thank You page. <a href="http://www.gravityhelp.com/documentation/page/Form_Settings" target="_blank">Refer to these instructions if you are unsure how to do this.</a>

= How to Submit My Data to HubSpot =

If you have Before & After Pro installed, and you have an active HubSpot account, you can have submission data from your Goals submitted through to HubSpot.  To do so, first login to your HubSpot account and create a new Form to receive our submissions.  Next, simply visit our Settings page and look for HubSpot settings.  Once here, you'll want to add your HUB ID, the GUID of the Form (on HubSpot) that you want to send data to, and (optionally) input the Titles of any forms that you don't want to send to HubSpot.  This feature will only work on Contact Form 7 or Gravity Forms that are associated with a Goal.

Before &amp; After will attempt to map your fields to HubSpot -- to adjust the field mapping array use the ```ba_hubspot_field_mappings``` filter.

Before &amp; After will block some default Gravity Forms fields from being sent to HubSpot -- to adjust the field mapping array used for blocking, use the ```ba_gform_default_fields``` filter.

== Screenshots ==

1. This is the list of Goals.
2. This is the Add New Goal page.
3. This is the Conversion Tracking Log.
4. This is the Settings screen.
5. This is the Help screen.

== Frequently Asked Questions ==

= How Do I Get Started? =

After installing the plugin, visit the Goals -> Add New menu to create your first Goal.

= How can I see the Before stage of my Goal again, now that I've completed it? =

Visit the Before & After -> Settings menu, and then choose the Reset Goals tab. Then click the Reset Goals button. You will now see the Before stage again for all of your goals.

= Where Can I Find The Settings Page? =

Look for the Before & After menu in WordPress, and then choose the Settings submenu.

= My goals work when I am logged in, but not when I am logged out. What could be happening? =

You are most likely experiencing a caching issue. Try excluding the URL with your Goal from your caching plugin's settings, or temporarily disable your caching plugin, and see if this resolves the issue.

WP Engine Users: You'll want to contact support, and ask them to exclude your pages from the cache. 

= Is there a Pro version available? =

Yes! Before and After is an entirely lead capture free plugin, but has a Pro plugin which upgrades it with advanced features such as conversion tracking and notifications. It also includes email-based technical support.

[Click Here To Learn More About Before and After Pro](https://goldplugins.com/downloads/before-after-pro/?utm_source=before_and_after_readme&utm_campaign=learn_more_about_pro" "Before and After Pro").

[View More FAQs](https://goldplugins.com/documentation/before-after-documentation/before-after-faqs/?utm_source=before_and_after_readme&utm_campaign=faqs" "View More FAQs")

== Changelog ==

= 3.9 =
* Page Protection can now show Goals inline on the page, instead of having to redirect to a new page.
* Fixes bug with broken link
* Compatibility with WordPress 5.8

= 3.8.1 =
* Fixes bug with duplicated forms having same download key.
* Adds 'Reset Download Keys' button to Goals. Compatibility with WordPress 5.7.2.
* Compatibility with WordPress 5.7.2

= 3.7 =
* Fixes bug in Page Protection meta box.
* Compatibility with WordPress 5.7.

= 3.6.2 =
* Minor UI updates.

= 3.6.1 =
* Bug fix, issues when adding new fields to a form built from a template.

= 3.6 =
* Adds form & goal templates - now Before & After make Lead Gen forms right out of the box.
* Adds example Goals & Forms
* Adds new field types to form builder (Rating, Heading, HTML)
* And allows creating forms from a template, and marking forms as templates
* Fixes field ordering in Available Fields box
* Adds Duplicate action for Goals & Forms
* Adds Admin Bar menu to allow admins to reset goals from Front End, as well as quick access to Before & After menus

= 3.5.4 =
* Compatibility with WP 5.3
* Minor updates.

= 3.5.3 =
* Compatibility with WP 5.2.1
* Minor updates.

= 3.5.2 =
* Compatibility with WP 5.1.1
* Minor fixes.

= 3.5.1 =
* Minor fixes.

= 3.5 =
* Adds Form Builder! Create your own forms inside Before & After.

= 3.4.1 =
* Minor fixes

= 3.4 =
* Simplified interface for the Add and Edit Goal Screen with improved instructions.
* Minor fixes

= 3.3.1 =
* Minor update

= 3.3 =
* Adds Gutenberg blocks for Goal and Complete Goal.
* Compatibility update for WP 5.0

= 3.2.1 =
* Prevent Page Protection from redirecting users on anything other than single Pages or Posts

= 3.2 =
* Compatibility with Contact Form 7 version 5.0 (fixes issue with pages not reloading automatically on form submit)
* Compatibility with WordPress version 4.9.4

= 3.1 =
* Improves persistence for goal completion across browser sessions.

= 3.0.1 =
* Automatic file type detection for protected downloads.

= 3.0 =
* MAJOR UPGRADE! Please backup site before proceeding.
* Admin UI update.
* Under the hood improvements.

= 2.6.8 =
* Update Protected Download file delivery to allow redirects.

[View Changelog](https://goldplugins.com/documentation/before-after-documentation/before-after-changelog/ "View Changelog")

== Upgrade Notice ==

**3.9** Page Protection can now show Goals inline on the page, instead of having to redirect to a new page. Compatibile with WordPress 5.8.