<?php $this->wpchef_me_warning(); ?>
<div class="wrap">
	<h1>
		Recipes
		<a href="<?=$this->url_add?>" class="page-title-action add-new-h2">Add New</a>
		<?php /*<a href="<?=$this->url('create', '', true)?>" class="page-title-action"><?php _E('Create Recipe', 'wpchef')?></a> */ ?>
		<?php $this->wpchef_me_badge() ?>
	</h1>
	<!--
	<ul class="subsubsub">
		<li class="all"><a href="plugins.php?plugin_status=all" class="current">All <span class="count">(10)</span></a> |</li>
		<li class="active"><a href="plugins.php?plugin_status=active">Active <span class="count">(6)</span></a> |</li>
	</ul>
	-->
	<?php if ( $recipes ): ?>
	<table class="wp-list-table widefat plugins recipes">
		<thead>
			<tr>
				<td id="cb" class="manage-column column-cb check-column" width="1">
					<!-- <label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"> -->
				<th>Recipe</th>
				<th>Description</th>
				<?php if ( !$this->offline ): ?>
				<th class="column-status"><?php esc_html_e('Status', 'wpchef')?></th>
				<th class="column-autoupdate">Auto-update <i class="fa fa-question-circle wpchef-hint" title="Sets automatic updates option. If enabled auto-update will run twice a day."></i></th>
				<?php endif ?>
		<tbody id="the-list">
			<?php foreach( $recipes as $slug => $recipe ): ?>
			<?php
				$classes = array();
				$classes[] = $recipe['installed'] ? 'active' : 'inactive';
				
				if ( $recipe['upgrade'] )
					$classes[] = 'update';
				
				if ( $recipe['autoupdate_fail'] )
					$classes[] = 'error';
				
				$classes[] = $recipe['is_my_own'] ? 'is-my-own' : 'not-my-own';
			?>
			
			<tr id="recipe-<?php echo $slug?>" class="recipe-list-item <?php echo implode( ' ', $classes ) ?>" data-recipe="<?php echo $slug?>" data-wpchef_id="<?php echo $recipe['wpchef_id']?>">
				<th scope="row" class="check-column">
					<!-- <label class="screen-reader-text" for="checkbox_a2d1869ef6f4a1cb80f9abb01226c12e">Select 2by2 MainWP customization</label><input name="checked[]" value="mainwp-customize/mainwp-customize.php" id="checkbox_a2d1869ef6f4a1cb80f9abb01226c12e" type="checkbox"></th>
					-->
				<td class="plugin-title column-primary">
					<strong class="recipe-name"><?=esc_html($recipe['name'])?></strong>
					<div class="row-actions visible">
						<?php if ( !$recipe['installed'] ): ?>
						<a href="#" class="activate-now">Activate</a>
						<?php else: ?>
						<a href="#" class="deactivate-now">Deactivate</a>
						<?php endif ?>
						<?php if ( $recipe['admin_access'] || !$recipe['wpchef_id'] ): ?>
						| <a href="<?=$this->url('edit', $slug, true)?>" class="edit-recipe">Edit</a>
						<?php elseif ( $recipe['installed'] ): ?>
						| <a href="#" class="activate-now">Re-activate</a>
						<?php endif ?>
						<?php if ( !$recipe['installed'] ): ?>
						| <a href="<?=wp_nonce_url( $this->url('', $slug, true), "remove-$slug" )?>&amp;action=delete" class="delete">Delete</a>
						<?php endif ?>
					</div>
				<td class="column-description desc">
					<div class="plugin-description"><p><?=esc_html($recipe['description'])?></p></div>
					<div class="plugin-version-author_uri">
						Version <?=esc_html( is_bool($recipe['installed']) ? $recipe['version'] : $recipe['installed'])?>
						<?php if ( $recipe['author'] ): ?>
						| By <?php if ( $recipe['author_uri'] ): ?><a href="<?=esc_url($recipe['author_uri'])?>"><?=esc_html($recipe['author'])?></a><?php else: ?><?=esc_html($recipe['author'])?><?php endif ?>
						<?php endif ?>
						<?php if ( $recipe['uri'] ): ?>
						| <a href="<?=esc_url($recipe['uri'])?>" target="_blank">Visit recipe site</a>
						<?php endif ?>
						| <?php printf( esc_html__('Ingredients: %d', 'wpchef'), count( $recipe['ingredients'] ) ) ?>
					</div>
				<?php if ( !$this->offline ): ?>
				<td class="column-status">
					<i class="wpchef-hint fa <?=$recipe['status_icon']?>" title="<?= esc_attr($recipe['status_hint'])?>"></i>
				<?php $autoupdate = $recipe['autoupdate'] ? $recipe['autoupdate'] : 'off' ?>
				<td class="column-autoupdate">
					<?php if ( !$recipe['installed'] ): ?>
					<?php elseif ( !$recipe['wpchef_id'] ): ?>
						<span class="wpchef-hint" title="<?php esc_attr_e('This option is inactive because the recipe is local.', 'wpchef') ?>"><a class="button disabled" disabled >Off</a></span>
					<?php else:  ?>
						<a class="button autoupdate-3switcher autoupdate-<?=$autoupdate?>">
							<span class="switcher-state"><?=ucfirst($autoupdate)?></span> <i class="fa fa-caret-down"></i>
							<ul class="switcher-items">
								<li data-value="off">Off</li>
								<li data-value="minor">Minor</li>
								<li data-value="major">Major</li>
							</ul>
						</a>
					<?php endif ?>
				<?php endif ?>
			<?php 	if ( $recipe['upgrade'] ): ?>
			<tr id="recipe-<?=$slug?>-upgrade" class="<?= implode( ' ', $classes ) ?> plugin-update-tr">
				<td class="plugin-update colspanchange" colspan="<?=$this->offline ? 3 : 5?>">
					<?php if ( $recipe['autoupdate_fail'] ): ?>
					<p class="autoupdate-fail-message" style="margin:0 10px 8px 31px">
						<i class="fa fa-exclamation-triangle text-danger"></i>
						Autoupdate failed. <a class="upgrade-activate-now update-link" href="#" data-upload_sec="<?=wp_create_nonce('wpchef_upload_'.$recipe['slug'])?>">Update recipe manually</a>.
					</p>
						<?php else: ?>
					<div class="update-message notice notice-warning notice-alt inline"><p>
						There is a new <strong><?=esc_html($recipe['upgrade']['version'])?></strong> version of <strong><?=esc_html($recipe['name'])?></strong> available.
						<a href="<?=$this->server?>recipe/<?=$recipe['upgrade']['slug']?>/#changelog" class="update-link" target="_blank">View changelog</a>
						or
						<a class="upgrade-activate-now" href="#" data-upload_sec="<?=wp_create_nonce('wpchef_upload_'.$recipe['slug'])?>">Update Now</a>
					</p></div>
					<?php endif ?>
			<?php 	endif ?>
			<?php endforeach ?>
		<tfoot>
			<tr>
				<td class="manage-column column-cb check-column">
				<!--
					<label class="screen-reader-text" for="cb-select-all-2">Select All</label>
					<input id="cb-select-all-2" type="checkbox">
				-->
				<th>Recipe</th>
				<th>Description</th>
				<?php if ( !$this->offline ): ?>
				<th class="column-status" nowrap><?php esc_html_e('Status', 'wpchef')?></th>
				<th class="column-autoupdate" nowrap>Auto-update <i class="fa fa-question-circle wpchef-hint" title="Sets automatic updates option. If enabled auto-update will run twice a day."></i></th>
				<?php endif ?>
	</table>
	<?php /*else: ?>
	<br />
	<h4><a href="<?=$this->url_add?>" class="button">Add New Recipe</a></h4>
	<?php*/ endif ?>
</div>
<?php add_thickbox(); ?>
<script>
'Strict mode'
jQuery( function($){

	$('.autoupdate-3switcher')
		.click( function() {
			$(this)
				.css('z-index', '1')
				.find('.switcher-items')
					.fadeIn();
			
		} )
		.mouseleave( function() {
			$(this)
				.css('z-index', '0')
				.find('.switcher-items')
					.fadeOut();
		} )
	
	$('.autoupdate-3switcher .switcher-items > *').click( function(){
		var $this = $(this);
		var switcher = $this.closest('.autoupdate-3switcher');
		
		switcher.css('opacity','.5');
		$('.switcher-items', switcher).hide();
		
		var mode = $this.data('value');
		var recipe = switcher.closest('.recipe-list-item').data('recipe');
		
		$.post( ajaxurl, {
			action: 'wpchef_autoupdate',
			mode: mode,
			recipe: recipe,
			sec: <?=json_encode( wp_create_nonce('wpchef_autoupdate') )?>
		})
		.done( function(data){
			if ( data && data.success )
			{
				switcher
					.css('opacity', '1')
					.attr('class', 'button autoupdate-3switcher autoupdate-'+mode )
					.find('.switcher-state').text( $this.text() );
				return;
			}
			fail();
		} )
		.fail( fail );
		
		function fail(){
			switcher.css('opacity', '1')
			window.alert('Connection error');
		}
		
		return false;
		
	} );

	
	$('.autoupdate-switcher').click(function(e){
		var $this = $('input[type=checkbox]', this);
		
		if ( $this.size() == 0 || $this.hasClass('ready') )
			return false;
		
		$this.addClass('ready');
		var is_on = !$this[0].checked;
		
		$.post( ajaxurl, {
			action: 'wpchef_autoupdate',
			mode: is_on ? 'on' : 'off',
			recipe: $this.closest('tr').data('recipe'),
			sec: <?=json_encode( wp_create_nonce('wpchef_autoupdate') )?>
		})
		.done( function(data){
			if ( data && data.success )
			{
				$this[0].checked = is_on;
				$this.removeClass('ready');
				return;
			}
			fail();
		} )
		.fail( fail );
		
		function fail(){
			$this[0].checked = !is_on;
			$this.removeClass('ready');
			window.alert('Connection error');
		}
		
		return false;
	});
	
	$('.row-actions .delete').click( function(){
		if ( !window.confirm('Are you sure?') )
			return false;
		
		error = 'Connection error';
		
		var box = $(this).closest('.recipe-list-item');
		
		$.post( ajaxurl, {
			action: 'wpchef_recipe_delete',
			sec: <?=json_encode(wp_create_nonce('wpchef_recipe_delete'))?>,
			slug: box.data('recipe')
		} )
		.done( function( data ) {
			if ( data && data.success )
			{
				box.fadeOut( function(){ box.remove() } );
				return;
			}
			
			if ( data && data.error )
				error = data.error;
			
			fail();
		} )
		.fail( fail );
		
		function fail() {
			window.alert( error );
		}
		
		return false;
	} );
	
	$('.activate-now, .deactivate-now, .upgrade-activate-now').click( function(){
		var btn = $(this);
		var item;
		
		if ( btn.hasClass('upgrade-activate-now') )
			item = btn.closest('.plugin-update-tr').fadeOut().prev();
		else
			item = btn.closest('.recipe-list-item');
			
		wpchef.activate_modal( {
			slug: item.data('recipe'),
			name: item.find('.recipe-name').text(),
			callback: function( installed ){
				if ( installed )
					$('body').on( 'thickbox:removed', function(){window.location.reload()} );
			},
			upload: btn.data('upload_sec'),
			deactivate: btn.hasClass( 'deactivate-now')
		} );
		
		return false;
	} );
	
	$('.recipe-list-item.not-my-own .edit-recipe').click( function()
	{
		return;
		var win = null;
		var link = $(this);
		link.after( $('<sapn class="loadinfo">&nbsp;<i class="fa fa-refresh fa-spin" title="Opening a popup window..." style="color:#666"></i></span>') );
		wpchef.tooltip( link.siblings('.loadinfo').find('.fa') );
		var timer = setInterval( function(){
			if (win && win.closed !== false)
			{
				clearInterval(timer);
				link.siblings('.loadinfo').remove();
				link.blur();
			}
		}, 100 );
		
		var url = <?php echo json_encode($this->server.'oauth/?temp_token') ?>;
		url += '&return=' + encodeURIComponent( this.href + '&in_popup' );
		url += '&recipe_id=' + link.closest('.recipe-list-item').data('wpchef_id');
		
		<?php if ( $me = $this->wpchef_me() ): ?>
		url += '&hash=<?=urlencode(md5(get_option('wpchef_access_token',null)))?>&user_id=<?=$me['ID']?>';
		<?php endif ?>
		
		var w = Math.min( 500, window.screen.availWidth );
		var h = Math.min( 500, window.screen.availHeight );
		var left = ( window.screen.availWidth - w )/2;
		var top = (window.screen.availHeight - h )/2;
		
		win = window.open( url, 'wpchef_connect', 'menubar=no,location=no,resizable=yes,scrollbars=yes,width='+w+',height='+h+',left='+left+',top='+top );
		/*win.onload = function(){
			link.siblings('.loadinfo').remove();
		}*/
		window.addEventListener("message", function( e ){
			if( e.data == 'loaded' )
			{
				clearInterval(timer);
				link.siblings('.loadinfo').remove();
				link.blur();
			}
		}, false );
		
		if ( win )
			return false;
		
		return true;
	})
	
	wpchef.tooltip( $('.recipe-list-item.not-my-own .edit-recipe').attr('title', '') );
		
});
</script>
<?php wp_print_request_filesystem_credentials_modal() ?>