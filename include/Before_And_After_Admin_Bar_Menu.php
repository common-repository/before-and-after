<?php
class Before_And_After_Admin_Bar_Menu
{
   const post_type = 'b_a_form';
	
	/**
     * Start up
     */
    public function __construct()
    {
		add_action( 'admin_bar_menu', array($this, 'add_admin_bar_menus'), 500 );		
	}

	function add_admin_bar_menus( WP_Admin_Bar $admin_bar )
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$admin_bar->add_menu( array(
			'id'    => 'before-and-after-admin-bar-menu',
			'parent' => null,
			'group'  => null,
			'title' => '<span class="ab-icon"></span><span class="ab-label">Before & After</span>', //you can use img tag with image link. it will show the image icon Instead of the title.
			'href'  => admin_url('admin.php?page=before-and-after-settings'),
			'meta' => [
				'title' => 'Before & After', //This title will show on hover
			]
		) );

		$admin_bar->add_menu( array(
			'id'    => 'before-and-after-reset-goals',
			'parent' => 'before-and-after-admin-bar-menu',
			'group'  => null,
			'title' => __('Reset All', 'before-and-after') . ' Goals', //you can use img tag with image link. it will show the image icon Instead of the title.
			'href'  => '#',
			'meta' => [
				'title' => __('Reset All', 'before-and-after') . ' Goals - ' . __('After clicking this link, all', 'before-and-after') . ' Goals ' . __('will show their After state agian. This only affects you - not your visitors.', 'before-and-after'), //This title will show on hover
			]
		) );

		$admin_bar->add_menu( array(
			'id'    => 'before-and-after-new-goal',
			'parent' => 'before-and-after-admin-bar-menu',
			'group'  => null,
			'title' => __('New', 'before-and-after') . ' Goal', //you can use img tag with image link. it will show the image icon Instead of the title.
			'href'  => admin_url('post-new.php?post_type=b_a_goal'),
			'meta' => [
				'title' => __('New', 'before-and-after') . ' Goals', //This title will show on hover
			]
		) );
		
		$admin_bar->add_menu( array(
			'id'    => 'before-and-after-view-all-goals',
			'parent' => 'before-and-after-admin-bar-menu',
			'group'  => null,
			'title' => __('View All', 'before-and-after') . ' Goals', //you can use img tag with image link. it will show the image icon Instead of the title.
			'href'  => admin_url('edit.php?post_type=b_a_goal'),
			'meta' => [
				'title' => __('View All', 'before-and-after') . ' Goals', //This title will show on hover
			]
		) );		

		$admin_bar->add_menu( array(
			'id'    => 'before-and-after-new-form',
			'parent' => 'before-and-after-admin-bar-menu',
			'group'  => null,
			'title' => __('New', 'before-and-after') . ' Form', //you can use img tag with image link. it will show the image icon Instead of the title.
			'href'  => admin_url('post-new.php?post_type=b_a_form'),
			'meta' => [
				'title' => __('New', 'before-and-after') . ' Form', //This title will show on hover
			]
		) );

		$admin_bar->add_menu( array(
			'id'    => 'before-and-after-view-all-forms',
			'parent' => 'before-and-after-admin-bar-menu',
			'group'  => null,
			'title' => __('View All', 'before-and-after') . ' Forms', //you can use img tag with image link. it will show the image icon Instead of the title.
			'href'  => admin_url('edit.php?post_type=b_a_form'),
			'meta' => [
				'title' => __('View All', 'before-and-after') . ' Forms', //This title will show on hover
			]
		) );

		$admin_bar->add_menu( array(
			'id'    => 'before-and-after-settings',
			'parent' => 'before-and-after-admin-bar-menu',
			'group'  => null,
			'title' => __('Settings', 'before-and-after'), //you can use img tag with image link. it will show the image icon Instead of the title.
			'href'  => admin_url('admin.php?page=before-and-after-settings'),
			'meta' => [
				'title' => __('Settings', 'before-and-after'), //This title will show on hover
			]
		) );
		
		$admin_bar->add_menu( array(
			'id'    => 'before-and-after-help',
			'parent' => 'before-and-after-admin-bar-menu',
			'group'  => null,
			'title' => __('Help', 'before-and-after'), //you can use img tag with image link. it will show the image icon Instead of the title.
			'href'  => admin_url('admin.php?page=b_a_help_and_troubleshooting'),
			'meta' => [
				'title' => __('Help', 'before-and-after'), //This title will show on hover
			]
		) );
		
		$admin_bar->add_menu( array(
			'id'    => 'before-and-after-upgrade',
			'parent' => 'before-and-after-admin-bar-menu',
			'group'  => null,
			'title' => __('Upgrade To', 'before-and-after') . ' Pro', //you can use img tag with image link. it will show the image icon Instead of the title.
			'href'  => admin_url('admin.php?page=before-and-after-upgrade-to-pro'),
			'meta' => [
				'title' => __('Upgrade To', 'before-and-after') . ' Pro', //This title will show on hover
			]
		) );
	}
}
