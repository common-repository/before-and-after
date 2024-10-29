<div class="field_wrapper text_wrapper heading_wrapper">
	<?php 
		if ( empty($field['heading_type']) ) {
			$field['heading_type'] = 'h1';
		}
		printf('<%s>', $field['heading_type']);
		echo htmlentities( $field['heading_text'], ENT_QUOTES, 'UTF-8' );
		printf('</%s>', $field['heading_type']);
	?>	
	
	<?php if (!empty($field['subheading_text'])):?>
	<p class="gp_cf_description"><?php echo htmlentities($field['subheading_text'], ENT_QUOTES, 'UTF-8') ; ?></p>
	<?php endif;?>	
</div>