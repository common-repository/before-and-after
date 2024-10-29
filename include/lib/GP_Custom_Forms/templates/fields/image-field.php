<?php
	$field_name = sprintf( 'form_%s_%s', htmlentities($form_id), htmlentities($field['name']) );
	$current_value = !empty($_REQUEST['form_' . $form_id][$field['name']])
					 ? htmlentities( $_REQUEST['form_' . $form_id][$field['name']], ENT_QUOTES, 'UTF-8' )
					 : '';
?>

<div class="gp_cf_field_wrap field_wrapper image_field_wrapper <?php if (!empty($field['error'])):?>has_validation_error<?php endif;?>">
	<label for="<?php echo $field_name; ?>"><?php echo htmlentities( $field['title'], ENT_QUOTES, 'UTF-8' ); ?></label>
	
	<input type="file" id="<?php echo $field_name; ?>" class="the-image <?php echo $field_name; ?>" value="" tabindex="6" size="20" name="form_<?php echo htmlentities($form_id); ?>[<?php echo htmlentities($field['name']); ?>]" 
		data-required="<?php echo intval($field['required']); ?>"
		data-image-field="1"
	/>
	
	<?php if (!empty($field['description'])):?>
	<p class="gp_cf_description"><?php echo htmlentities($field['description'], ENT_QUOTES, 'UTF-8') ; ?></p>
	<?php endif;?>

	<?php if (!empty($field['error'])):?>
	<p class="error_message"><?php echo htmlentities($field['error']); ?></p>
	<?php endif;?>		
</div>