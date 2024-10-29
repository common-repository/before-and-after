<div class="field_wrapper textarea_wrapper <?php if (!empty($field['error'])):?>has_validation_error<?php endif;?>">
	<label for="form_<?php echo htmlentities($form_id); ?>_<?php echo htmlentities($field['name']); ?>"><?php echo htmlentities( $field['title'], ENT_QUOTES, 'UTF-8' ); ?> <?php echo ( !empty($field['required']) ? '*' : '' ); ?></label>
	<textarea 
		name="form_<?php echo htmlentities($form_id); ?>[<?php echo htmlentities($field['name']); ?>]"
		id="form_<?php echo htmlentities($form_id); ?>_<?php echo htmlentities($field['name']); ?>"
		data-required="<?php echo intval($field['required']); ?>"
	><?php echo !empty($_REQUEST['form_' . $form_id][$field['name']]) ? htmlentities( stripslashes($_REQUEST['form_' . $form_id][$field['name']]), ENT_QUOTES, 'UTF-8' ) : '' ?></textarea>

	<?php if (!empty($field['description'])):?>
	<p class="gp_cf_description"><?php echo htmlentities($field['description'], ENT_QUOTES, 'UTF-8') ; ?></p>
	<?php endif;?>

	<?php if (!empty($field['error'])):?>
	<p class="error_message"><?php echo htmlentities($field['error']); ?></p>
	<?php endif;?>
</div>