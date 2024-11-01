<?php

if ( !class_exists('wpchef_recipe') ):

class wpchef_recipe extends wpchef_base
{
	protected static $instance;
	
	protected function __construct()
	{
		parent::__construct();
		
		$this->ingredient = wpchef_ingredient::instance();
	}
	
	function normalize( $recipe, $strict = false )
	{
		if ( $strict && ( !is_array($recipe) || empty($recipe['name']) ) )
			return false;
		
		if ( !$recipe || !is_array($recipe) )
			$recipe = array(
				'name' => _x('(invalid recipe)','recipe title fallback','wpchef'),
				'invalid' => true,
			);
		
		$default = array(
			'name' => _x('(invalid recipe)','recipe title fallback','wpchef'),
			'version' => '',
			'description' => '',
			'uri' => '',
			'author' => '',
			'author_uri' => '',
			'author_profile_url' => '',
			'wpchef_id' => 0,
			'fork_id' => 0,
			'ingredients' => array(),
			'invalid' => false,
			'post_author' => 0,
			'phpversion' => '',
		);
		
		$recipe += $default;
		
		if ( !is_array($recipe['ingredients']) )
			$recipe['ingredients'] = array();
		
		return $recipe;
	}
	
	function sanitize( $recipe, $strict = false )
	{
		$recipe = $this->normalize( $recipe, $strict );
		
		foreach( $recipe as $key => $val )
			if ( is_string($val) )
				$recipe[ $key ] = trim( $val );
		
		
		if ( $strict && !$recipe )
			return false;
		
		$clean = array();
		
		$clean = array(
			'name' => (string)$recipe['name'],
			'version' => (string)$recipe['version'],
			'description' => (string)$recipe['description'],
		);
		
		$optional = array(
			'uri' => (string)$recipe['uri'],
			'author' => (string)$recipe['author'],
			'author_uri' => (string)$recipe['author_uri'],
			'author_profile_url' => (string)$recipe['author_profile_url'],
			'wpchef_id' => (int)$recipe['wpchef_id'],
			'fork_id' => (int)$recipe['fork_id'],
			'phpversion' => (string)$recipe['phpversion'],
		);
		
		foreach ( $optional as $key => $val )
			if ( $val )
				$clean[$key] = $val;
		
		$clean['ingredients'] = $this->ingredient->sanitize_list( $recipe['ingredients'] );
		
		return $clean;
	}
	
	function sanitize_slug( $slug, $fallback = 'recipe' )
	{
		$slug = preg_replace('/[^a-z0-9_-]+/iu', '-', $slug);
		$slug = preg_replace('/--+/', '-', $slug);
		$slug = preg_replace('/^-+/', '', $slug);
		$slug = preg_replace('/-+$/', '', $slug);
		$slug = strtolower( $slug );
		
		if ( !$slug )
			$slug = $fallback;
		
		elseif ( preg_match( '/^[\d-]+$/', $slug ) )
			$slug = $fallback.'-'.$slug;
		
		return $slug;
	}
	
	function form_info( $recipe, $extend = false )
	{
		$recipe = $this->normalize( $recipe );
		?>
		<table class="form-table">
			<?php if ( $extend ): ?>
			<tr>
				<th><label for="recipe_name"><?php esc_html_e('Name')?></label>
				<td><input name="recipe_name" id="recipe_name" type="text" size="40" value="<?=esc_attr( $recipe['name'] )?>"/>
			<?php endif ?>
			<tr>
				<?php if ( $recipe['wpchef_id'] && $recipe['is_my_own'] ): ?>
				<th><label><?php esc_html_e('Slug')?></label>
				<td><?=esc_html($recipe['slug'])?>
				<?php else: ?>
				<th>
					<label><?php esc_html_e('Local Slug','wpchef')?></label>
					<span class="fa fa-question-circle ingredient-hint" title="<?php esc_attr_e('The slug will likely change after you upload the recipe to WPChef.','wpchef')?>"></span>
					<p class="description">Optional <i class="fa fa-question-circle wpchef-hint" title="If empty will be generated automatically."></i></p>
				<td><input name="recipe_slug" id="recipe_slug" type="text" size="40" value="<?=esc_attr( $recipe['slug'] )?>"/>
				<?php endif ?>
			<tr>
				<th><label for="recipe_version"><?php esc_html_e('Version')?></label>
				<td><input name="recipe_version" id="recipe_version" type="text" size="10" value="<?=esc_attr( $recipe['version'] )?>"/>
			<tr>
				<th nowrap>
					<label for="recipe_description"><?php esc_html_e('Description')?></label>
					<span class="fa fa-question-circle ingredient-hint" title="<?php echo esc_html_x('Will appear in the list of recipes.','Hint for recipe description field in edit form', 'wpchef') ?>"></span>
					<p class="description">Optional</p>
				<td><textarea name="recipe_description" id="recipe_description" type="text" rows="3" cols="40"><?=esc_textarea( $recipe['description'] )?></textarea>
			<tr>
				<th>
					<label for="recipe_uri"><?php esc_html_e('Recipe site URL','wpchef')?></label>
					<p class="description">Optional</p>
				<td><input name="recipe_uri" id="recipe_uri" type="text" size="40" value="<?=esc_attr( $recipe['uri'] )?>"/>
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
			<tr>
				<th>
					<label for="recipe_phpversion"><?php esc_html_e('Required PHP Version','wpchef')?></label>
					<span class="fa fa-question-circle ingredient-hint" title="<?php esc_attr_e('If the server is running an older PHP version than the required one by the recipe its installation will be aborted.','wpchef')?>"></span>
					<?php if ( !$recipe['wpchef_id'] || !$recipe['is_my_own'] ): ?>
					<p class="description">Optional</p>
					<?php endif ?>
				<td><input name="recipe_phpversion" id="recipe_phpversion" type="text" size="10" value="<?=esc_attr( $recipe['phpversion'] )?>"/>
		</table>
		<?php
	}
	
	function form_import()
	{
		return;
		?>
			<p>
				<input name="recipe_import" id="recipe_import" type="file" />
			</p>
			<p style="text-align:right">
				<input type="submit" class="button" name="recipe_import_confirm" id="recipe_import_confirm" value="<?= _e('Import' ) ?>" />
			</p>
			<p class="description"><?php esc_html_e('Will overwrite all data of the current recipe.','wpchef')?></p>
		<script>
		jQuery(function($){
			$('#recipe_import_confirm').click( function(){
				if ( window.confirm(<?php echo json_encode(__('Are you sure?')) ?>) )
				{
					$(this).closest('form').attr('enctype', 'multipart/form-data');
					
					return true;
				}
				else
					return false;
			} );
		});
		</script>
		<?php
	}
	
	function upload( $file, &$error, $raw = false, &$slug = null )
	{
		$error = __('Invalid recipe', 'wpchef');
		
		if ( !is_uploaded_file( $file['tmp_name'] ) )
			 $error = _x('Please, select a file.', 'Upload recipe', 'wpchef');
		
		elseif ( !preg_match('/^(.*)\.recipe$/i', $file['name'], $m) )
			$error = __('Only .recipe files can be uploaded.', 'wpchef');
		
		else
		{
			$slug = $m[1];
			$json = file_get_contents( $file['tmp_name'] );
			$recipe = $this->sanitize( json_decode($json, true), true );
			if ( $recipe )
				$recipe = $this->normalize( $recipe );
		}
		
		if ( empty( $recipe ) )
			return false;
		
		return $raw ? $json : $recipe;
	}
	
	function form_post()
	{
		$recipe = array();
		foreach ( $_POST as $key => $val )
		{
			if ( preg_match( '/^recipe_(.+)$/', $key, $m ) )
				$recipe[ $m[1] ] = stripslashes($val);
		}
		
		$recipe = $this->normalize( $recipe );
		$recipe['ingredients'] = $this->ingredient->form_post();
		
		return $recipe;
	}
	
	function download( $recipe, $slug )
	{
		$recipe = $this->sanitize( $recipe );
		
		header( 'Content-type: application/json; charset=utf-8' );
		header( 'Content-disposition: attachment; filename='.urlencode($slug).'.recipe' );
		echo json_encode( $recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}
}

endif;