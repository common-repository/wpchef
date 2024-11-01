<?php $this->wpchef_me_warning() ?>
<div class="wrap">

	<?php if ( $mode == 'install' ): ?>
	<h1>Installing Recipe: <?=esc_html($recipe['name'])?></h1>
	<p>Successfully installed the recipe <strong><?=esc_html($recipe['name'])?></strong>.</p>
	<p>
		<a href="<?=esc_attr($this->url( 'activate', $recipe['slug'] ))?>">Activate Recipe</a>
		|
		<a href="<?=esc_attr($this->url('install'))?>">Return to Recipe Installer</a>
	</p>
	
	<?php elseif ( $mode == 'confirm' ): ?>
	<p>Are you sure wan't to install the recipe <strong><?=esc_html($recipe['name'])?></strong>?</p>
	<p>
		<a href="<?=esc_attr($install_url)?>" class="<?=$recipe['allow_access']?'':'wpchef_auth_only'?>">Install the Recipe</a>
		|
		<a href="<?=esc_attr($recipe['link'])?>">Return to wpchef.org Recipe Page</a>
	</p>
	
	<?php elseif ( $mode == 'form' ): ?>
		<?php echo $form; ?>
	
	<?php else: ?>
	<div class="notice notice-error"><p><?php
		esc_html_e('Can\'t fetch recipe from wpchef server.')
	?></p></div>
	<p>
		<a href="<?=esc_attr($this->url( 'install', $_GET['recipe'] ))?>"><?php
			esc_html_e('Try Again')
		?></a>
		|
		<a href="<?=esc_attr($this->url('install'))?>"><?php
			esc_html_e('Return to Recipe Installer')
		?></a>
	</p>
	<?php endif ?>
</div>