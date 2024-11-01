<?php $this->wpchef_me_warning(); ?>
<div class="wrap recipe-edit" id="poststuff">
	<?php
		if ( $this->offline )
		{
			$recipe['is_my_own'] = true;
			$recipe['wpchef_id'] = 0;
		}
		elseif ( !$recipe['is_my_own'] )
		{
			if ( $me )
			{
				$recipe['author'] = $me['display_name'];
				$recipe['author_profile_url'] = $me['profile_url'];
				$recipe['author_uri'] = $me['url'];
			}
			else
			{
				$user = get_userdata( get_current_user_id() );
				
				$recipe['author'] = $user->display_name;
				$recipe['author_profile_url'] = '';
				$recipe['author_uri'] = $user->user_url;
			}
		}
	?>
	<?php if ( !empty($error) ): ?>
	<div class="notice notice-error recipe-save-error">
		<p><?=wp_kses_post($error)?></p>
	</div>
	<script>
	jQuery( function($) {
		$(window).on( 'message', function(e){
			console.log( e.originalEvent.data );
			if( e.originalEvent.data == 'add_slots_success' )
			{
				$('.recipe-save-error')
					.attr('class', 'notice notice-success')
					.html('')
					.append ( $('<p>').text( <?= json_encode(__('Slots added successfully.', 'wpchef')) ?> ) )
					.append( $('<p>').append( $('<a href="#" class="button warning save-to-wpchef">').text(<?= json_encode(__('Save recipe to WPChef', 'wpchef')) ?>) ) )
					.on('click', '.save-to-wpchef', function(){
						$('.recipe-actions .save-to-cloud button').click();
					} );
			}
		} );
	} );
	</script>
	<?php elseif ( $recipe['new'] ): ?>
	<div class="notice notice-info"><p><?php _e('On this page you can create a brand new recipe from scratch or take a snap-shot of your site as a recipe.', 'wpchef')?></p></div>
	<?php endif ?>
	
	<?php if ( isset($_GET['success']) ): ?>
	<div class="notice notice-success">
		<p>
		<?php
			$at = __('WPChef.org', 'wpchef');
			if ( $current && !$current['private'] )
				$at = sprintf('<a href="%s" target="_blank">%s</a>', esc_attr( $current['link'] ), $at );
		?>
		<?php if ($_GET['success'] == 'cloudnew' ): ?>
		<?php printf( __('Recipe saved successfully to your <a href="%s" target="_blank">private account</a> at %s.', 'wpchef'), $this->server.'wp-admin/edit.php?post_type=recipe&amp;wpchef_user_id='.$me['ID'], $at ) ?>
			<?php if ( isset( $_GET['slots'] ) ): ?>
			(Private slots usage: <b><?php echo esc_html($_GET['slots'] ) ?></b>)
			<?php endif ?>
		<?php elseif ( $_GET['success'] == 'cloud'): ?>
			<?php if ($current && $current['private']): ?>
				<?php printf( __('Recipe updated successfully in your <a href="%s" target="_blank">private account</a> at %s.', 'wpchef'), $this->server.'wp-admin/edit.php?post_type=recipe&amp;wpchef_user_id='.$me['ID'], $at ) ?>
			<?php else: ?>
				<?php printf( esc_html__('Recipe updated successfully at %s.', 'wpchef'), $at ) ?>
			<?php endif ?>
			<?php if ( isset( $_GET['slots'] ) ): ?>
			(Private slots usage: <b><?php echo esc_html($_GET['slots'] ) ?></b>)
			<?php endif ?>
		<?php else: ?>
		<?php esc_html_e('Recipe saved successfully.', 'wpchef') ?>
		<a href="<?php echo $this->url() ?>"><?php esc_html_e('View Recipes', 'wpchef') ?></a>
		<?php endif ?>
		</p>
	</div>
	<?php endif ?>
	<h1>
		<?php if($recipe['new']): ?>
		Create Recipe
		<?php else: ?>
		Edit Recipe: <?=esc_html($recipe['name'])?>
		<?php endif ?>
		<?php $this->wpchef_me_badge() ?>
	</h1>
	<form method="post" action="<?=$this->url('edit', $recipe['slug'], true)?>&amp;noheader">
		<?php wp_nonce_field( 'recipe_edit', 'recipe_edit_nonce' ) ?>
		<input type="hidden" name="recipe_new" value="<?=$recipe['new']?1:''?>">
		<input type="hidden" name="recipe_slug" value="<?=esc_attr( $recipe['slug'] )?>"/>
		<div id="templateside" class="recipe-edit-side">
			<div class="postbox recipe-actions">
				<h3 class="hndle">Actions</h3>
				<div class="inside">
					<ul>
						<li>
							<button type="submit" class="button apply" name="save" value="save">
								<?php /* <span class="recipe-action-number">1</span> */ ?>
								Save locally
							</button>
						<li>
							<button type="submit" class="button button-primary" name="save" value="apply">
								Apply to this site
							</button>
						<?php if ( $me && !$me['admin_access'] ): ?>
						<?php elseif ( !$this->offline ): ?>
						<li class="save-to-cloud">
							<button type="submit" class="button warning wpchef_auth_only" name="save" value="cloud">
								<?php esc_html_e('Save to WPChef', 'wpchef') ?>
								&nbsp;
								<i class="fa fa-question-circle wpchef-hint" title="<?php esc_html_e('The recipe will be saved to your private account at WPChef.org.', 'wpchef') ?>"></i>
							</button>
						<?php endif ?>
					</ul>
				<?php if ( $me && !$me['admin_access'] ): ?>
					<p>
						<i class="fa fa-info-circle text-like-primary"></i>
						<?php printf(
							esc_html__( 'This site is authorized under %s %s account in %s mode, therefore you can\'t save recipes to your private WPChef account. To gain full access you can either %s this site using another account or change current %s.', 'wpchef' ),
							sprintf('<b>%s</b>', esc_html( $me['display_name'] ) ),
							sprintf('<a href="%s" target="_blank">%s</a>', esc_attr( $this->server ), esc_html__('WPChef', 'wpchef') ),
							sprintf('<b class="text-warning-dark">%s</b>', esc_html__('read-only', 'wpchef') ),
							sprintf('<a href="%s">%s</a>', esc_attr( admin_url('admin.php?page=recipe-settings') ), __('authorize', 'wpchef') ),
							sprintf('<a href="%s">%s</a>', esc_attr( admin_url('admin.php?page=recipe-settings') ), __('access level', 'wpchef') )
						) ?>
					</p>
				<?php else: ?>
					<?php if ( !$this->offline ): ?>
					<p>
						<?php if ( !$recipe['wpchef_id'] ): ?>
						<i class="fa fa-circle text-muted" style="font-size: 1.1em;"></i> &nbsp;
						<?php esc_html_e('This recipe is not uploaded to WPChef.', 'wpchef' ) ?>
						<?php elseif ( !$recipe['is_my_own'] ): ?>
						<i class="fa fa-exclamation-triangle text-warning"></i>
						<?php esc_html_e('You are not the author of this recipe. If you save it, a copy will be created.', 'wpchef') ?>
						<?php else: ?>
						<i class="fa <?=$recipe['status_icon']?>"></i>
						<?php esc_html_e('You are the author of this recipe.', 'wpchef') ?>
						<?php endif ?>
					</p>
						<?php if ( $recipe['is_my_own'] && $recipe['wpchef_id'] ): ?>
					<p>
						<strong>Status:</strong>
						<a href="<?=esc_attr($this->server.'wp-admin/post.php?post='.$recipe['wpchef_id'].'&action=edit&wpchef_user_id='.$me['ID'])?>" target="_blank"><?=$recipe['private']?'Private':'Public'?></a>
						<i class="fa fa-question-circle wpchef-hint" title="<?php esc_attr_e('You can make your recipe public or private at WPChef.org.', 'wpchef') ?>"></i>
					</p>
						<?php endif ?>
						<?php if ( $recipe['fork_id'] && ($fork = $this->fetch_recipe( $recipe['fork_id'] )) ):
								$this->recipe_things( $fork, $fork['slug'] );
								
								if ( $fork['is_my_own'] || !$fork['private'] )
									$fork_link = sprintf(
										'<a href="%s" target="_blank">%s</a>',
										esc_attr( $fork['link'] ),
										esc_html( $fork['name'] )
									);
								
								else
									$fork_link = sprintf( '<b>%s</p> (%s)', esc_html($fork['name']), esc_html__('private recipe') );
						 ?>
					<p>
						<?php printf( esc_html__('This recipe is a fork of %s.', 'wpchef' ), $fork_link ) ?><br>
					</p>
					<p class="description" style="text-align:right">
						<a href="<?= $this->url('edit', $recipe['slug'], true ) ?>&amp;switchback=<?=urlencode(wp_create_nonce('recipe_switchback'))?>&amp;noheader" onclick="return window.confirm('Are you sure?');"><?php esc_html_e('Switch back to origin.') ?></a><br>
						<?php esc_html_e('All custom changes will be forgotten.') ?>
					</p>
						<?php endif ?>
						<?php if ( $current ): ?>
					<p>
						<?php printf(
							esc_html__('Version at %s', 'wpchef'),
							sprintf('<a href="%s" target="_blank">%s</a>', esc_attr($this->server), esc_html__('WPChef','wpchef'))
						) ?>: <b><?=esc_html($current['version'])?></b>
						<?php endif ?>
					<?php endif ?>
				<?php endif ?>
				</div>
			</div>
			<?php /*
			<div class="postbox">
				<h3 class="hndle">Import a Recipe</h3>
				<div class="inside">
					<?php wpchef_recipe::instance()->form_import() ?>
				</div>
			</div>
			*/ ?>
		</div>
		<div id="recipe_template" class="meta-box-sortables">
			<?php if ( $recipe['new'] )
				include dirname(__FILE__).'/recipe-edit-info.tpl.php' ?>
			
			<div class="postbox recipe-edit-ingredients">
				<h3 class="hndle">
					<a class="button button-small hide-if-no-js" id="recipe_snapshot">
						<i class="fa fa-camera"></i>&nbsp; 
						Take a snapshot of this site
					</a>
					Ingredients
				</h3>
				<div class="inside">
					<?php wpchef_editor::instance()->constructor( $recipe['ingredients'] ) ?>
				</div>
			</div>
			
			<?php if ( !$recipe['new'] )
				include dirname(__FILE__).'/recipe-edit-info.tpl.php' ?>
			
			<button type="submit" class="button" name="save" value="download"><span class="fa fa-download"></span> Download Recipe</button>
			
		</div>
	</form>
</div>
<script>
jQuery( function($){
	var form = $('#poststuff > form');
	$('.recipe-edit-ingredients .handlediv').click( function(){
		$(this).closest('.recipe-ingredient').toggleClass('closed');
	} );
	
	$('#recipe_snapshot').click( function(){
		var $this = $(this);
		if( $this.attr('disabled') )
			return false;
		
		if ( $('#recipe-ingredients').children('.recipe-ingredient').size() > 0 && !window.confirm('This will erase all ingredients added already. Would you like to continue?') )
			return false;
		
		$this.attr('disabled', 'disabled');
		$('.fa', $this).attr('class', 'fa fa-refresh fa-spin');
		$('#recipe-ingredients').fadeOut( 600 );
		
		$.post( ajaxurl, {
			action: 'wpchef_snapshot',
			sec: '<?=wp_create_nonce('wpchef_snapshot')?>'
		})
		.done( function( data ){
			$('.recipe-edit-ingredients > .inside')
				.html( data )
				.find('.handlediv').click( function(){
					$(this).closest('.recipe-ingredient').toggleClass('closed');
				} );
			form.data( 'changed', true );
		} )
		.fail( function(){
			window.alert( 'Connection error' );
		})
		.always( function(){
			$this.removeAttr( 'disabled' );
			$('.fa', $this).attr('class', 'fa fa-camera');
		})
		
		return false;
	} );
	
	var actions_box = $('#templateside > .postbox');
	actions_box.width( actions_box.width() );
	var actions_pos = actions_box.position().top;
	
	var $window = $( window );
	$window.scroll( function(){
		if ( $window.scrollTop() > actions_pos )
		{
			if ( actions_box.css('position') != 'fixed' )
				actions_box.css( {
					position: 'fixed',
					top: '36px'
				} );
		}
		else
		{
			if ( actions_box.css('position') == 'fixed' )
				actions_box.css( {
					position: 'static',
					top: 'auto'
				} );
		}
	} ).scroll();
	
	form.on('keyup keypress', function(e) {
		if ( e.target.nodeName.toLowerCase() == 'textarea' )
			return true;

		var keyCode = e.keyCode || e.which;
		if (keyCode === 13) { 
			e.preventDefault();
			return false;
		}
	})

	function formchange( e ) {
		form.off( 'keyup change', ':input', formchange );
		form.data('changed', true);
		return true;
	}
	form.on( 'keyup change', ':input', formchange );
	
	$(window).bind('beforeunload', function() {
		if( form.data('changed') ) {
			return "The changes you made will be lost if you navigate away from this page.";
		}
	});
	
	form.submit( function(){
		form.data( 'changed', false );
	});

	$('.recipe-edit-side button').click( function(e){
		<?php if ( $current && $current['installs'] ): ?>
		if ( $(this).parent().hasClass('save-to-cloud') )
		{
			if ( !window.confirm('This will affect about <?=$recipe['installs']?> installations of the recipe all over the world. Are you sure?') )
			{
				e.preventDefault();
				return false;
			}
		}
		<?php endif ?>
		wpchef.loadinfo( $(this) );
		return true;
	});
	/*
	$('button[name="save"][value="apply"]', form).click( function(){
		
	} )
	*/
} );
</script>
