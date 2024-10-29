<?php
	$radio_name = 'form_' . $form_id . '[' . htmlentities($field['name']) . ']';
?>
<p class="field_label"><label><?php echo htmlentities( $field['title'], ENT_QUOTES, 'UTF-8' ); ?> <?php echo ( !empty($field['required']) ? '*' : '' ); ?></label></p>
<div class="field_wrapper radio_wrapper <?php if (!empty($field['error'])):?>has_validation_error<?php endif;?>" data-required="<?php echo intval($field['required']); ?>">
	<?php if ( !empty($field['options']) ): ?>
	<?php foreach($field['options'] as $option): ?>
		<?php 
			// skip blank entries
			if ( empty($option['label']) ) {
				continue;
			}
			
			// determine if option should be selected by default
			$checked = '';
			if ( !empty($_REQUEST['form_' . $form_id][$field['name']]) && ($_REQUEST['form_' . $form_id][$field['name']] == $option['value'])
				|| empty($_REQUEST['form_' . $form_id][$field['name']]) && ($field['default'] == $option['value']) ) {
				$checked = 'checked="checked"';
			}										
		?>
			<label>
			<input 
				type="radio"
				name="<?php echo $radio_name; ?>" 
				value="<?php echo htmlentities($option['value'], ENT_QUOTES, 'UTF-8'); ?>"
				<?php echo $checked; ?>
			/>
			<?php echo htmlentities( $option['label'], ENT_QUOTES, 'UTF-8' ); ?>
		</label>
	<?php endforeach; ?>
	<?php endif; ?>

	<?php if (!empty($field['description'])):?>
	<p class="gp_cf_description"><?php echo htmlentities($field['description'], ENT_QUOTES, 'UTF-8') ; ?></p>
	<?php endif;?>

	<?php if (!empty($field['error'])):?>
	<p class="error_message"><?php echo htmlentities($field['error']); ?></p>
	<?php endif;?>
</div>