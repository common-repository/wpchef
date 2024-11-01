<?php $this->wpchef_me_warning(); ?>
<?php
	function wpchef_normalize_action( &$action )
	{
		$action += array(
			'action' => 'invalid',
			'title' => 'Invalid action',
			'enabled' => false,
			'checked' => false,
			'uname' => '',
			'spoiler' => '',
			'description' => '',
			'actions' => array(),
			'uninstall' => false,
			'merge' => false,
			'batch' => false,
			'notices' => array(),
		);
		
		$action['should_credentials'] = in_array( $action['action'], array('plugin_install', 'theme_install', 'uninstall_plugin') ) && $action['enabled'];
		var_dump( $action ); exit;
	}
?>
<div class="wrap recipe-activate-warp" id="poststuff">
	<?php if ( isset($_GET['saved']) ): ?>
	<div class="notice notice-success is-dismissible">
		<p><?php _e('Recipe saved successfully.', 'wpchef')?></p>
	</div>
	<div class="notice notice-info">
		<p><a href="<?=$this->url('edit', $slug, true)?>"><?php _e('Continue editing the recipe', 'wpchef')?></a></p>
	</div>
	<?php endif ?>
	<?php if ( isset( $_GET['child_success'] ) ): ?>
	<div class="notice notice-success"><p>
		Recipe has been paid succesfully
	</p></div>
	<?php endif ?>
	<h1 class="hndle">
		The following recipe will be <?=$title_action?>:
		<span class="recipe-name"><?=esc_html($recipe['name'])?></span>
	</h1>
	
	<div id="recipe_install">
		<style>
		#recipe_install .ingredient {
			display: block;
			border: 1px solid #d3d3d3;
			background-color: #f7f7f7;
			border-radius: 4px;
			position: relative;
			padding: 5px 5px 5px 80px;
			margin: 20px 0;
			min-height: 80px;
		}
		#recipe_install .ingredient-description {
			font-size: 26px;
			color: black;
			line-height: 1.2em;
			margin: 0;
		}
		#recipe_install .action-invalid .ingredient-description {
			color: #666;
		}
		
		#recipe_install .check-column {
			position: absolute;
			left:20px;
			top: 50%;
			margin-top: -16px;
			font-size: 32px;
			width: 40px;
			text-align: center;
			color: #46b450;
		}
		#recipe_install .ingredient-title {
			font-size: 15px;
			/*font-style: italic;*/
			margin: 5px 0;
			color: #666;
		}
		.ingredient a {
			font-weight: normal;
			text-decoration: none;
		}
		</style>
		
		<div class="postbox">
			<div class="inside" >
				<ul>
					<?php recipe_install_list( $recipe['actions'] ); ?>
				</ul>
				
		<?php function recipe_install_list( $actions, $level=0, &$number=0 ) { ?>
			<?php foreach ( $actions as $action ): wpchef_normalize_action($action); $number++ ?>
			<li class="ingredient action-<?=$action['action']?><?=$action['description']?' with-description':''?>" data-level="<?=$level?>">
				<div class="check-column">
					
					<?php if ( $action['enabled'] ): ?>
					<input type="checkbox"<?php if($action['uname']):?> name="<?=esc_attr($action['uname'])?>"<?php endif ?><?= $action['checked'] ? ' checked' : '' ?>  data-uninstall="<?=$action['uninstall']?'1':''?>" data-recipe="<?=esc_attr($action['recipe'])?>" class="recipe-actionbox action-<?=$action['action']?> wpchef-checkbox<?=$action['should_credentials'] ? ' should_credentials' : ''?>" data-batch="<?=$action['batch']?'1':''?>" id="recipe_actionbox_<?=$number?>"/>
					<label for="recipe_actionbox_<?=$number?>" class="checkbox-checked"><i class="fa fa-toggle-on "></i></label>
					<label for="recipe_actionbox_<?=$number?>"><i class="fa fa-toggle-off"></i></label>
					
					<?php elseif ( $action['checked'] ): ?>
					<input type="checkbox" checked class="recipe-actionbox action-<?=$action['action']?> wpchef-checkbox" disabled/>
					<?php endif ?>
					
					<label class="checkbox-disabled"><i class="fa fa-toggle-on"></i></label>
					<p style="display:none" class="action-title"><?=esc_wpchef($action['title'])?></p>
				</div>
				
				<?php if ( $action['description'] ): ?>
				<div class="ingredient-description">
					<?=esc_wpchef($action['description'])?>
				</div>
				<?php endif ?>
				
				<p class="ingredient-<?=$action['description']?'title':'description'?>">
					<?=esc_wpchef($action['title'])?>
					<?php if ( $action['spoiler'] ): ?>
					&nbsp; <a class="ingredient-spoiler-trigger" href="#" title="See more..."><span class="fa fa-chevron-down"></span></a>
					<?php endif ?>
				</p>
				<?php if ( $action['spoiler'] ): ?>
				<pre class="ingredient-spoiler"><?=esc_wpchef($action['spoiler'])?></pre>
				<?php endif ?>
				
				<?php foreach( $action['notices'] as $notice ): $type = array_shift( $notice ) ?>
				<div class="notice notice-<?=$type?> inline"><p><?=esc_wpchef( $notice )?></p></div>
				<?php endforeach ?>
				
				<?php if ( $action['actions'] )
					recipe_install_list( $action['actions'], $level+1, $number ); ?>
			
			<?php endforeach ?>
		<?php } ?>
		
				<?php if (!$uninstall): 
						if ( $recipe['wpchef_id'] && !$recipe['installed'] )
							$autoupdate = 'minor';
						else
							$autoupdate = $recipe['autoupdate'];
				?>
				<div class="recipe-special">
					Auto-update: 
					<label>
						<input type="radio" name="recipe_autoupdate" value="major" <?=$recipe['wpchef_id']?'':'disabled '?><?=$autoupdate=='major'?'checked ':''?>/>
						Major
						<span class="wpchef-hint fa fa-question-circle" title="Major versions of the recipe will be applied automatically. A major version is a version that can make a big impact to the site like changing its functionality and appearance. Also minor versions will be applied automatically within this option."></span>
					</label>
					<label>
						<input type="radio" name="recipe_autoupdate" value="minor" <?=$recipe['wpchef_id']?'':'disabled '?><?=$autoupdate=='minor'?'checked ':''?>/>
						Minor (recommended)
						<span class="wpchef-hint fa fa-question-circle" title="Only minor versions of the recipe will be applied automatically. A minor version is a version that makes small effect to the site without changing its functionality and appearance. Recommended."></span>
					</label>
					<label>
						<input type="radio" name="recipe_autoupdate" value="" <?=$recipe['wpchef_id']?'':'disabled '?><?=$autoupdate?'':'checked '?>/>
						Off
					</label>
				</div>
				<?php endif ?>
				<div class="recipe-apply">
					<label style="display:none">
						<input name="complete" type="checkbox" checked disabled class="recipe-actionbox"/>Finishing...
					</label>
					<button class="button <?=$uninstall?'danger':'apply'?>" id="recipe_apply"><?=$uninstall ? 'Deactivate' : 'Apply to this site'?></button>
					<span class="loadinfo"><i class="fa fa-refresh fa-spin"></i></span>
					<span class="apply-state"></span>
				</div>
			</div>
		</div>
	</div>
	
	<div style="display:none">
		<h3 class="hndle">Log</h3>
		<div class="inside" id="recipe_log">
		</div>
	</div>
</div>

<?php wp_print_request_filesystem_credentials_modal() ?>

<script>
	wpchef.apply = {
		upgrade: <?=$recipe['installed']?'true':'false'?>,
		recipe: <?=json_encode($slug)?>,
		apply_nonce: <?=json_encode( wp_create_nonce('recipe_steps_'.$slug) )?>,
		uninstall: <?=$uninstall?'true':'false'?>,
		url_list: <?=json_encode($this->url_list)?>
	}
	<?php if ( !empty($_REQUEST['customs']) && $customs = json_decode( stripslashes($_REQUEST['customs']), true ) ): ?>
	wpchef.apply.customs = <?=json_encode($customs)?>;
	<?php endif ?>
</script>
<script src="<?=plugins_url('wpchef/apply.js')?>"></script>