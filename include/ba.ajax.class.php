<?php

class Before_And_After_AJAX
{
	function __construct( $root, $goal_module )
	{
		$this->root = $root;
		$this->Goal = $goal_module;
		$this->add_hooks();
	}
	
	function add_hooks()
	{
		add_action( 'wp_ajax_before_and_after_get_goal_content', array($this, 'ajax_get_goal_content') );
		add_action( 'wp_ajax_nopriv_before_and_after_get_goal_content', array($this, 'ajax_get_goal_content') );
	}
	
	function ajax_get_goal_content()
	{
		$goal_id = isset($_POST['goal_id'])
				   ? intval($_POST['goal_id'])
				   : 0;
		
		// if invalid input die with no response
		if ( empty($goal_id) ) {
			wp_die('');
		}
		
		$goal_content = $this->Goal->get_goal_content($goal_id);
		
		$ret = [
			'success' => 'OK',
			'html' => $goal_content,
		];
		
		wp_die( json_encode($ret) );
		
	}
}