<?php
	$checked = '';
	if ( !empty($_REQUEST['form_' . $form_id][$field['name']]) && ($_REQUEST['form_' . $form_id][$field['name']] == $option['value'])
		|| empty($_REQUEST['form_' . $form_id][$field['name']]) && ($field['default'] == $option['value']) ) {
		$checked = 'checked="checked"';
	}	
?>
<div class="field_wrapper checkbox_wrapper <?php if (!empty($field['error'])):?>has_validation_error<?php endif;?>">
	<input 
		type="hidden"
		value="0"
		name="form_<?php echo htmlentities($form_id); ?>[<?php echo htmlentities($field['name']); ?>]"
	/>
	<label for="form_<?php echo htmlentities($form_id); ?>_<?php echo htmlentities($field['name']); ?>">
		<input 
			type="checkbox"
			value="1"
			<?php echo $checked; ?>
			name="form_<?php echo htmlentities($form_id); ?>[<?php echo htmlentities($field['name']); ?>]"
			id="form_<?php echo htmlentities($form_id); ?>_<?php echo htmlentities($field['name']); ?>"
			data-required="<?php echo intval($field['required']); ?>"
		/>
		<?php echo htmlentities( $field['title'], ENT_QUOTES, 'UTF-8' ); ?> <?php echo ( !empty($field['required']) ? '*' : '' ); ?>
	</label>

	<?php if (!empty($field['description'])):?>
	<p class="gp_cf_description"><?php echo htmlentities($field['description'], ENT_QUOTES, 'UTF-8') ; ?></p>
	<?php endif;?>

	<?php if (!empty($field['error'])):?>
	<p class="error_message"><?php echo htmlentities($field['error']); ?></p>
	<?php endif;?>
</div>												
