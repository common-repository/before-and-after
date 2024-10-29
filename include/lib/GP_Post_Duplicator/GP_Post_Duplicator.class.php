<?php
if  ( !class_exists('GP_Post_Duplicator') ) :

	class GP_Post_Duplicator
	{
		function __construct($add_hooks = true)
		{
			if ( $add_hooks ) {
				add_action( 'admin_action_gp_duplicate_post_as_draft', array($this, 'duplicate_post_as_draft') );
			}			
		}
		
		/* 
		 * Copies a post along with all attributes, taxonomies, and meta values to a new post
		 * a redirects to the Edit Post page for the new post.
		 *
		 * Expects a get parameter of 'post' with the original post ID to be set
		 */
		function duplicate_post_as_draft()
		{
			global $wpdb;
			if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'b_a_duplicate_post_as_draft' == $_REQUEST['action'] ) ) ) {
				wp_die('No post to duplicate has been supplied!');
			}
		 
			/*
			 * get the original post id
			 */
			$post_id = ( isset($_GET['post']) ? intval($_GET['post']) : intval($_POST['post']) );
		 
			/*
			 * if post data exists, create the post duplicate
			 */
			if ( !empty( $post_id ) ) {
		 
				$new_post_id = $this->copy_post($post_id); 
				
				// Redirect to the edit post screen for the new draft
				if ( !empty($new_post_id) ) {
					do_action('gp_post_duplicate', $post_id);
					wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
					exit;
				}
				else {
					wp_die('Post creation failed, could create new post. Original post ID: ' . $post_id);
				}
			}
			else {
				wp_die('Post creation failed, could not find original post: ' . $post_id);
			}
		}
		
		/*
		 * Copies the specified post and all its attributes, meta values, and taxonomies
		 * to a new post.
		 *
		 * @param int $post_id The ID of the post to copy from.
		 *
		 * @param mixed On success, an integer representing the new post ID. 
		 *				On failure, returns false.
		 */
		function copy_post($post_id)
		{
			global $wpdb;
			
			/*
			 * if you don't want current user to be the new post author,
			 * then change next couple of lines to this: $new_post_author = $post->post_author;
			 */
			$current_user = wp_get_current_user();
			$new_post_author = $current_user->ID;

			/*
			 * load all original post data
			 */
			$post = get_post( $post_id );
		 
			/*
			 * if post data exists, create the post duplicate
			 */
			if (isset( $post ) && $post != null) {
		 
				/*
				 * new post data array
				 */
				$args = array(
					'comment_status' => $post->comment_status,
					'ping_status'    => $post->ping_status,
					'post_author'    => $new_post_author,
					'post_content'   => $post->post_content,
					'post_excerpt'   => $post->post_excerpt,
					'post_name'      => $post->post_name,
					'post_parent'    => $post->post_parent,
					'post_password'  => $post->post_password,
					'post_status'    => 'draft',
					'post_title'     => $post->post_title,
					'post_type'      => $post->post_type,
					'to_ping'        => $post->to_ping,
					'menu_order'     => $post->menu_order
				);
		 
				/*
				 * insert the post by wp_insert_post() function
				 */
				$new_post_id = wp_insert_post( $args );
		 
				/*
				 * get all current post terms ad set them to the new post draft
				 */
				$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
				foreach ($taxonomies as $taxonomy) {
					$post_terms = wp_get_object_terms($post_id, $taxonomy);
					for ($i=0; $i<count($post_terms); $i++) {
						wp_set_object_terms($new_post_id, $post_terms[$i]->slug, $taxonomy, true);
					}
				}
		 
				/*
				 * duplicate all post meta
				 */
				$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
				if (count($post_meta_infos)!=0) {
					$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
					foreach ($post_meta_infos as $meta_info) {
						$meta_key = $meta_info->meta_key;
						$meta_value = addslashes($meta_info->meta_value);
						$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
					}
					$sql_query.= implode(" UNION ALL ", $sql_query_sel);
					$wpdb->query($sql_query);
				}
				
				return $new_post_id;
			}
			
			// failed to create new post
			return false;
		}
		
	} // end class
	
endif; // class_exists
