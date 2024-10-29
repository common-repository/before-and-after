<?php
class BA_Menus
{
	var $root;
	
	public function __construct($root)
	{
		$this->root = $root;

		// setup hooks to create the admin menus
        add_action( 'admin_menu', array( $this, 'create_primary_admin_menu' ), 2 ); // note: set priority to <=9, else the "Goals" custom post type will override the first submenu
		add_action( 'admin_menu', array( $this, 'create_admin_submenus' ), 10 );		
		if ( !$this->root->is_pro() ) {		
			add_action( 'admin_menu', array( $this, 'add_upgrade_menu' ), 20 );	// late, to ensure last position
			add_action( 'admin_head', array( $this, 'admin_menu_css' ) );			
		}
	}
	
	// Creates the "Before & After" menu heading, and adds the Settings submenu to it
	function create_primary_admin_menu()
	{
		// Note: this will also create a child menu with the same name. 
		// We'll override that name in the next step, to make it read "Settings"
		add_menu_page( 
			$this->root->plugin_title . ' Settings',
			$this->root->plugin_title, 
			'manage_options',
			'before-and-after-settings',
			array( $this->root->Settings, 'output_settings_page' ),
			'dashicons-star-filled'
		);
	}
	
	// Add the submenus to our Before & After primary menu
	function create_admin_submenus()
	{		
		global $submenu;
		
		// allow addons to add menus now
		do_action('before_and_after_add_menus', 'before-and-after-settings');

		if ( !$this->root->is_pro() ) {		
			// Add the Conversions menu
			add_submenu_page(
				'before-and-after-settings',
				'Conversions', 
				'Conversions',
				'manage_options', 
				'b_a_conversions',
				array($this->root->Settings, 'render_conversions_page')
				
			);
		}
		
		// Add the Help & Troubleshooting menu
		add_submenu_page(
			'before-and-after-settings',
			'Help & Troubleshooting', 
			'Help & Troubleshooting',
			'manage_options', 
			'b_a_help_and_troubleshooting',
			array($this->root->Settings, 'render_help_page')
			
		);

		// We want the main menu's label to be "Before & After", but the first submenu's label to be "Settings",
		// so we must override the submenu's label (by default, both would be labeled "Before & After")		
		// IMPORTANT: this code needs to run *after* the other submenus have already been added, else it won't work
		$submenu['before-and-after-settings'][0][0] = __('Settings', 'before-and-after');
		
		/*
		 * Rename goals to "All Goals" and insert an "Add New Goal" submenu after it
		 */
		
		// find the 'Goals' menu, rename it 'All Goals', and keep track of the index
		$found_index = -1;
		foreach( $submenu['before-and-after-settings'] as $index => $sm ) {
			if ( 'Goals' == $sm[0] ) {
				$found_index = $index;
				$submenu['before-and-after-settings'][$index][0] = __('All', 'before-and-after') . ' Goals';
				$submenu['before-and-after-settings'][$index][3] = __('All', 'before-and-after') . ' Goals';
				break;
			}
		}
		
		// inject new 'Add A New Goal' menu after the 'All Goals' menu we just located
		$new_item = array(
			__('Add New', 'before-and-after') . ' Goal',
			'edit_posts',
			'post-new.php?post_type=b_a_goal',
			__('Add New', 'before-and-after') . ' Goal',
		);
		$submenu['before-and-after-settings'] = $this->array_put_to_position_numeric($submenu['before-and-after-settings'], $new_item, $found_index + 1);
		
		// move Settings menu from first to 3rd to last
		$first_menu_item = array_shift($submenu['before-and-after-settings']);		
		$res = array_merge( 
			   array_slice($submenu['before-and-after-settings'], 0, 4, true),
			   array($first_menu_item),
			   array_slice($submenu['before-and-after-settings'], 4, count($submenu['before-and-after-settings'])-3, true) 
			);		
		$submenu['before-and-after-settings'] = $res;
		
	}

	// Add the upgrade menu for free users
	function add_upgrade_menu()
	{
		add_submenu_page(
			'before-and-after-settings',
			__('Upgrade To Pro'),
			__('Upgrade To Pro'),
			'administrator',
			'before-and-after-upgrade-to-pro',
			array($this->root->Settings, 'render_upgrade_page')
		);
	}
	
	function admin_menu_css()
	{
		echo '<style>
		#adminmenu .before_and_after_admin_menu ul.wp-submenu > li:last-child > a {
			color: #3ce038;
			font-weight: bold;
		}
		</style>';
	}

	function show_upgrade_page()
	{
		echo "<h3>Upgrade To Before & After Pro</h3>";
		echo "You should upgrade to PRO! Then you'd be tracking Goal Conversions.";
	}
	function show_help_page()
	{
		include('pages/help.html');
	}
	
	function array_put_to_position(&$array, $object, $position, $name = null)
	{
			$count = 0;
			$return = array();
			foreach ($array as $k => $v)
			{  
					// insert new object
					if ($count == $position)
					{  
							if (!$name) $name = $count;
							$return[$name] = $object;
							$inserted = true;
					}  
					// insert old object
					$return[$k] = $v;
					$count++;
			}  
			if (!$name) $name = $count;
			if (!$inserted) $return[$name];
			$array = $return;
			return $array;
	}		
	
	function array_put_to_position_numeric(&$array, $object, $position)
	{
			$count = 0;
			$return = array();
			foreach ($array as $k => $v)
			{  
					// insert new object
					if ($count == $position)
					{  
						$return[] = $object;
						$inserted = true;
						$count++;
					}  
					// insert old object
					$return[] = $v;
					$count++;
			}  
			$array = $return;
			return $array;
	}		
	
	
}