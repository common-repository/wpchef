<?php $this->wpchef_me_warning(); ?>
<div class="wrap">
	<h1>
		Add Recipe
		<a href="<?=$this->url('create', '', true)?>" class="page-title-action add-new-h2"><?php _E('Create Recipe', 'wpchef')?></a>
		<?php $this->wpchef_me_badge() ?>
	</h1>

	<div class="upload-plugin" style="display:block;">
		<p class="install-help">If you have a recipe in a .recipe format, you may install it by uploading it here.</p>
		<form method="post" enctype="multipart/form-data" class="wp-upload-form" action="<?=$this->url_add?>&amp;upload&amp;noheader">
			<?php wp_nonce_field( 'recipe_upload', 'recipe_upload_nonce' ) ?>
			<label class="screen-reader-text" for="recipe">.recipe file</label>
			<input id="recipe" name="recipe" type="file">
			<input class="button" value="Install Now" type="submit">
		</form>
	</div>
</div>