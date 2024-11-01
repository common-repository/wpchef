<?php $this->wpchef_me_warning(); ?>
<h1>Add Recipe</h1>

<div class="upload-plugin">
	<p class="install-help">The recipe already exists. Upgrade existing recipe?</p>
	<div class="wp-upload-form" style="text-align:center">
		<form method="post" style="display:inline-block">
			<?php wp_nonce_field( 'recipe_upload', 'recipe_upload_nonce' ) ?>
			<input class="button" name="confirm" value="Upgrade" type="submit">
		</form>
		&nbsp;
		&nbsp;
		<a class="button" href="<?=$this->url_add?>&amp;upload&amp;noheader">Cancel</a>
	</div>
</div>