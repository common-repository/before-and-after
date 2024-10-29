<div class="field_wrapper text_wrapper html_wrapper">
<?php 
	if ( !empty($field['content']) ) {
		if ( !empty($field['add_paragraphs']) ) {
			$field['content'] = wpautop($field['content']);
		}
		echo $field['content']; 
	}
?>
</div>