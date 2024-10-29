<div class="field_wrapper select_wrapper <?php if (!empty($field['error'])):?>has_validation_error<?php endif;?>">
	<label for="form_<?php echo htmlentities($form_id); ?>_<?php echo htmlentities($field['name']); ?>"><?php echo htmlentities( $field['title'], ENT_QUOTES, 'UTF-8' ); ?> <?php echo ( !empty($field['required']) ? '*' : '' ); ?></label>
	<select 
		name="form_<?php echo htmlentities($form_id); ?>[<?php echo htmlentities($field['name']); ?>]"
		id="form_<?php echo htmlentities($form_id); ?>_<?php echo htmlentities($field['name']); ?>"
		data-required="<?php echo intval($field['required']); ?>"
	>
		<?php if ( !empty($field['options']) ): ?>
		<?php foreach($field['options'] as $option): ?>
			<?php 
				// skip blank entries
				if ( empty($option['label']) ) {
					continue;
				}
				
				// determine if option should be selected by default
				$selected = '';
				if ( !empty($_REQUEST['form_' . $form_id][$field['name']]) && ($_REQUEST['form_' . $form_id][$field['name']] == $option['value'])
					|| empty($_REQUEST['form_' . $form_id][$field['name']]) && ($field['default'] == $option['value']) ) {
					$selected = 'selected="selected"';
				}										
			?>
			<option value="<?php echo htmlentities($option['value'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>><?php echo htmlentities($option['label'], ENT_QUOTES, 'UTF-8'); ?></option>
		<?php endforeach; ?>
		<?php endif; ?>								
	</select>

	<?php if (!empty($field['description'])):?>
	<p class="gp_cf_description"><?php echo htmlentities($field['description'], ENT_QUOTES, 'UTF-8') ; ?></p>
	<?php endif;?>

	<?php if (!empty($field['error'])):?>
	<p class="error_message"><?php echo htmlentities($field['error']); ?></p>
	<?php endif;?>
</div>