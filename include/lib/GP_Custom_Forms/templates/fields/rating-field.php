<?php
	$field_name = sprintf( 'form_%s_%s', htmlentities($form_id), htmlentities($field['name']) );
	$current_value = !empty($_REQUEST['form_' . $form_id][$field['name']])
					 ? htmlentities( $_REQUEST['form_' . $form_id][$field['name']], ENT_QUOTES, 'UTF-8' )
					 : '';
	$min_rating = !empty($field['min'])
	       ? intval($field['min'])
		   : 0;
	$min_rating = max(0, $min_rating);

	$max_rating = !empty($field['max'])
	       ? intval($field['max'])
		   : 0;
	$max_rating = min(10, $max_rating);
?>

<div class="gp_cf_field_wrap field_wrapper rating_wrapper <?php if (!empty($field['error'])):?>has_validation_error<?php endif;?>">
	<label for="<?php echo $field_name; ?>"><?php echo htmlentities( $field['title'], ENT_QUOTES, 'UTF-8' ); ?></label>
	<select 
		id="<?php echo $field_name; ?>" 
		class="gp_cf_select gp_cf_rating <?php echo $field_name; ?>"
		tabindex="4"
		size="1"
		name="form_<?php echo htmlentities($form_id); ?>[<?php echo htmlentities($field['name']); ?>]" 
		data-required="<?php echo intval($field['required']); ?>"
		data-rating-field="1"
	>
		<?php 
		foreach(range($min_rating, $max_rating) as $rating) {
			$sel_attr = ( $current_value == $rating ) 
						? 'selected="selected"'
						: '';
			printf('<option value="%d" %s>%d</option>', $rating, $sel_attr, $rating);
		}
		?>
	</select>
	<div 
		class="rateit"
		data-rateit-resetable="true"
		data-rateit-backingfld=".<?php echo $field_name; ?>"
		xdata-rateit-min="<?php echo $min_rating; ?>"
		data-rateit-max="<?php echo $max_rating; ?>"
		data-rateit-step="1.0"
	></div>

	<?php if (!empty($field['description'])):?>
	<p class="gp_cf_description"><?php echo htmlentities($field['description'], ENT_QUOTES, 'UTF-8') ; ?></p>
	<?php endif;?>

	<?php if (!empty($field['error'])):?>
	<p class="error_message"><?php echo htmlentities($field['error']); ?></p>
	<?php endif;?>	
</div>
