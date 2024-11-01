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
			'installed' => false,
		);
		
		$action['should_credentials'] = in_array( $action['action'], array('plugin_install', 'theme_install', 'uninstall_plugin') ) && $action['enabled'];
	}
?>
<div class="recipe-activate-warp activate-inline">
	
	<div id="recipe_install" data-wpchef_id="<?=$recipe['wpchef_id']?>">
	
		<div class="ingredients-list">
		<table class="wp-list-table widefat plugins" >
			<!--
			<thead>
				<tr>
					<td class="check-column">
					<th width="45%">Ingredient
					<th>Description
			-->
			<tbody id="the-list">
			<?php recipe_install_list( $recipe['actions'] ); ?>
			<!--
			<tfoot>
				<tr>
					<td class="check-column">
					<th>Ingredient
					<th>Description
			-->
		</table>
		</div>
		
		<?php function recipe_install_list( $actions, $level=0, &$number=0 )
		{
			$padding = '';
			for ( $i=0; $i<$level; $i++)
				$padding .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
		?>
			<?php foreach ( $actions as $action ): wpchef_normalize_action($action) ?>
				<tr class="ingredient action-<?=$action['action']?><?=$action['notices']?' update':''?> <?php echo $action['installed']?'active':'inactive'?> <?php if (!$action['enabled'] && !$action['checked'] && !$action['uninstall']) echo 'impossible'?>" data-level="<?php echo $level?>">
				
					<th class="check-column" nowrap style="white-space:nowrap;">

					<?php if (!$action['enabled'] && $action['checked'] ): ?>
						<div class="wpchef-hint disabled-wrapper" title="<?php esc_attr_e('Already present.','wpchef')?>">
							<?php echo $padding?><input type="checkbox" checked class="recipe-actionbox action-<?=$action['action']?>" disabled/>
							<div class="wpchef-hint-bg"></div>
						</div>
					
					<?php elseif (!$action['enabled'] && !$action['checked'] ): ?>
						<div class="wpchef-hint disabled-wrapper" title="<?php $action['uninstall'] ? esc_attr_e('Not present.','wpchef') : esc_attr_e('Can\'t be applied.','wpchef')?>">
							<?php echo $padding?><input type="checkbox" class="recipe-actionbox action-<?=$action['action']?>" disabled/>
							<div class="wpchef-hint-bg"></div>
						</div>
						
					<?php else: ?>
					
						<?php echo $padding?><input type="checkbox"<?php if($action['uname']):?> name="<?=esc_attr($action['uname'])?>"<?php endif ?><?= $action['checked'] ? ' checked' : '' ?>  data-uninstall="<?=$action['uninstall']?'1':''?>" data-recipe="<?=esc_attr($action['recipe'])?>" class="recipe-actionbox action-<?=$action['action']?><?=$action['should_credentials'] ? ' should_credentials' : ''?>" data-batch="<?=$action['batch']?'1':''?>" id="recipe_actionbox_<?=$number?>"/>
					
					<?php endif ?>
					
					<td class="ingredient-title"><?php echo $padding?><?=esc_wpchef($action['title'])?></td>
					
					<td class="ingredient-description">
						<?php if ( $action['action'] == 'add_user' && $action['enabled'] ):?>
						<table class="form-table">
							<tr>
								<th>Login:
								<td><input type="text" data-name="login" value="<?=esc_attr($action['login'])?>" class="action-param"/>
							<tr>
								<th>Email:
								<td><input type="email" data-name="email" value="<?=esc_attr($action['email'])?>" class="action-param"/>
							<tr>
								<th>Password:
								<td>
									<input type="password" data-name="password" value="" class="action-param"/>
									<p class="description">Will be generated if empty.</p>
							<tr>
								<th>Role:
								<td>
									<?php $roles = get_editable_roles(); ?>
									<?php if ( $action['role'] && empty($roles[ $action['role'] ]) ): ?>
									<select data-name="role" class="action-param" onchange="var $this = jQuery(this); if ( $this.children(':selected').hasClass('default-not-exists') ) $this.siblings('.notice').fadeIn() else $this.siblings('.notice').fadeOut(); return true;">
										<option value="<?=esc_attr($action['role'])?>" selected class="default-not-exists"><?=esc_html($action['role'])?></option>
										<?php wp_dropdown_roles( $action['role'] ) ?>
									</select>
									<div class="notice notice-warning">The default <b><?=esc_html($action['role'])?></b> role isn't exists now. If role will not be created during recipe install the <b>Subscriber</b> role will be assigned.</div>
									<?php else: ?>
									<select data-name="role" class="action-param">
										<?php wp_dropdown_roles( $action['role'] ? $action['role'] : 'subscriber' ) ?>
									</select>
									<?php endif ?>
						</table>
						<?php endif ?>
						
						<?=esc_wpchef($action['description'])?>
						
						<?php if ( $action['spoiler'] ): ?>
						<a class="ingredient-spoiler-trigger" href="#" title="See more..."><span class="fa fa-chevron-down"></span></a>
						<pre class="ingredient-spoiler"><?=esc_wpchef($action['spoiler'])?></pre>
						<?php endif ?>
					</td>
				</tr>
				
				<?php if ( $action['notices'] ): ?>
				<tr class="plugin-update-tr <?=$action['installed']?'active':'inactive'?> <?php if (!$action['enabled'] && !$action['checked'] && !$action['uninstall']) echo 'impossible'?>" data-level="<?=$level?>">
					<td class="plugin-update colspanchange" colspan="3">
						<?php foreach( $action['notices'] as $notice ): $type = array_shift( $notice ) ?>
						<div class="notice notice-<?=$type?> inline"><p><?=esc_wpchef( $notice )?></p></div>
						<?php endforeach ?>
				</tr>
				<?php endif ?>
				
				<?php if ( $action['actions'] )
					recipe_install_list( $action['actions'], $level+1, $number ); ?>
			
			<?php endforeach ?>
		<?php } ?>
		
		<div class="recipe-special">
			<?php if (!$uninstall): 
					if ( $recipe['wpchef_id'] && !$recipe['installed'] )
						$autoupdate = 'minor';
					else
						$autoupdate = $recipe['autoupdate'];
			?>
				<?php if ( !$recipe['wpchef_id'] ): ?>
			<span class="wpchef-hint fa fa-exclamation-triangle text-warning" title="<?php esc_attr_e('This option is inactive because the recipe was not uploaded to your WPChef account yet.', 'wpchef' )?>"></span> &nbsp;
				<?php endif ?>
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
<script>
	window.wpchef.apply = {
		upgrade: <?=$recipe['installed']?'true':'false'?>,
		recipe: <?=json_encode($slug)?>,
		name: <?=json_encode( $recipe['name'] )?>,
		apply_nonce: <?=json_encode( wp_create_nonce('recipe_steps_'.$slug) )?>,
		uninstall: <?=$uninstall?'true':'false'?>,
		url_list: <?=json_encode($this->url_list)?>,
		check_user_nonce: <?=json_encode( wp_create_nonce('wpchef_check_user'.$slug) )?>
	}
	<?php if ( !empty($_REQUEST['customs']) && $customs = json_decode( stripslashes($_REQUEST['customs']), true ) ): ?>
	wpchef.apply.customs = <?=json_encode($customs)?>;
	<?php endif ?>
</script>
<script src="<?=plugins_url('wpchef/apply.js')?>?<?=filemtime(dirname(dirname(__FILE__)).'/apply.js')?>"></script>
<style>
.ui-tooltip.ui-widget {
	position: relative;
	z-index: 100100;
}
#TB_window {
	background: #f1f1f1;
}
#TB_title {
	height: 60px;
    background: #f1f1f1;
}
#TB_ajaxWindowTitle {
	line-height: 60px;
	padding-left: 25px;
}
#recipe_install .ingredient-description {
	max-width: 300px;
}
div.ui-tooltip {
    min-width: 100px;
    width:auto;
    display: inline-block;
}
.check-column .fa {
	margin: 5px 0 0 9px;
}
#recipe_install .recipe-log-button {
	position: absolute;
    right: 1em;
    bottom: 1em;
}
#recipe_log {
	text-align: left;
}
</style>