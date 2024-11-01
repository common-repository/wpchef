
<div class="postbox recipe-edit-general">
	<div class="handlediv button-link" title="<?php esc_attr_e('Click to toggle') ?>">
		<span class="screen-reader-text"><?php esc_html_e('Toggle Recipe Info','wpchef') ?></span>
		<span class="toggle-indicator" aria-hidden="true"></span>
	</div>
	<h3 class="hndle"><?php esc_html_e('Recipe\'s General Info','wpchef') ?></h3>
	<div class="inside">
		<table class="form-table">
			<tr>
				<th><label for="recipe_name"><?php esc_html_e('Name')?></label>
				<td><input name="recipe_name" id="recipe_name" type="text" size="40" value="<?=esc_attr( $recipe['name'] )?>"/>
			<tr>
				<th nowrap>
					<label for="recipe_description"><?php esc_html_e('Description')?></label>
					<span class="fa fa-question-circle ingredient-hint" title="<?php echo esc_html_x('Will appear in the list of recipes.','Hint for recipe description field in edit form', 'wpchef') ?>"></span>
					<p class="description">Optional</p>
				<td><textarea name="recipe_description" id="recipe_description" type="text" rows="3" cols="40"><?=esc_textarea( $recipe['description'] )?></textarea>
			<tr>
				<th><label for="recipe_version"><?php esc_html_e('Version')?></label>
				<td><input name="recipe_version" id="recipe_version" type="text" size="10" value="<?=esc_attr( $recipe['version'] )?>"/>
			<tr>
				<th>
					<label for="recipe_uri"><?php esc_html_e('Recipe Site URL','wpchef')?></label>
					<p class="description">Optional</p>
				<td><input name="recipe_uri" id="recipe_uri" type="text" size="40" value="<?=esc_attr( $recipe['uri'] )?>"/>
			<?php /*
			<tr>
				<th nowrap>
					<label for="recipe_author">
						<?php esc_html_e('Author') ?>
						<?php if ( $recipe['wpchef_id'] && $recipe['is_my_own'] ): ?>
						<i class="fa fa-question-circle wpchef-hint" title="<?php echo esc_html_x('WPChef Recipe owner','Hint for recipe author field in edit form', 'wpchef') ?>"></i>
						<?php endif ?>
					</label>
					<?php if ( !$recipe['wpchef_id'] || !$recipe['is_my_own'] ): ?>
					<p class="description">Optional</p>
					<?php endif ?>
				<td>
					<?php if ( $recipe['wpchef_id'] && $recipe['is_my_own'] ): ?>
						<?php if ($recipe['author_profile_url'] ): ?>
						<a href="<?=esc_attr($recipe['author_profile_url'])?>" target="_blank"><?=esc_html($recipe['author'])?></a> (<strong><?php esc_html_e('you') ?></strong>)
						<?php else: ?>
						<?=esc_html($recipe['author'])?>
						<?php endif ?>
					<?php else: ?>
					<input name="recipe_author" id="recipe_author" type="text" size="40" value="<?=esc_attr( $recipe['author'] )?>"/>
					<?php endif ?>
			<tr>
				<th>
					<label for="recipe_author_uri"><?php esc_html_e('Author URL','wpchef') ?></label>
					<?php if ( !$recipe['wpchef_id'] || !$recipe['is_my_own'] ): ?>
					<p class="description">Optional</p>
					<?php endif ?>
				<td>
					<?php if ( $recipe['wpchef_id'] && $recipe['is_my_own'] ): ?>
					<a hrfe="<?=esc_attr($recipe['author_uri'])?>" trget="_blank"><?=esc_html($recipe['author_uri'])?></a>
					<?php else: ?>
					<input name="recipe_author_uri" id="recipe_author_uri" type="text" size="40" value="<?=esc_attr( $recipe['author_uri'] )?>"/>
					<?php endif ?>
			*/ ?>
			<tr>
				<th>
					<label for="recipe_phpversion"><?php esc_html_e('Required PHP Version','wpchef')?></label>
					<span class="fa fa-question-circle ingredient-hint" title="<?php esc_attr_e('If the server is running an older PHP version than required the installation will be aborted.','wpchef')?>"></span>
					<?php if ( !$recipe['wpchef_id'] || !$recipe['is_my_own'] ): ?>
					<p class="description">Optional</p>
					<?php endif ?>
				<td><input name="recipe_phpversion" id="recipe_phpversion" type="text" size="10" value="<?=esc_attr( $recipe['phpversion'] )?>"/>
		</table>
	</div>
</div>
<script>
jQuery( function($){
	$('.recipe-edit-general > .handlediv').click( function(){
		$(this).closest('.recipe-edit-general').toggleClass('closed');
	} );
} );
</script>
