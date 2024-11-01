<?php

require_once dirname( __FILE__ ).'/ingredient.php';
if ( !class_exists( 'wpchef_editor' ) ):

class wpchef_editor extends wpchef_ingredient
{
	public $debug = false;
	protected static $instance;
	public $chef = true;
	
	protected function __construct(){
		parent::__construct();
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}
	
	function admin_init()
	{
		$this->server = get_option( 'wpchef_server', 'https://wpchef.org/' );
		$this->chef = wpchef::instance();

		if ( !current_user_can('install_plugins') )
			return;
		
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		
		add_action( 'wp_ajax_wpchef_search_packages', array( $this, 'search_packages' ) );
		
		add_action( 'wp_ajax_wpchef_add_ingredient', array( $this, 'add_ingredient' ) );
		add_action( 'wp_ajax_wpchef_ingredient', array( $this, 'ajax_list_item' ) );

		add_filter( 'wpchef_new_ingredient_theme', array( $this, 'new_ingredient_theme' ) );
		add_filter( 'wpchef_new_ingredient_plugin', array( $this, 'new_ingredient_plugin' ) );
		add_filter( 'wpchef_new_ingredient_recipe', array( $this, 'new_ingredient_recipe' ) );
		add_filter( 'wpchef_new_ingredient_action', array( $this, 'new_ingredient_action' ) );
		add_filter( 'wpchef_new_ingredient_option', array( $this, 'new_ingredient_option' ) );

		add_action( 'wpchef_add_ingredient_tabs', array( $this, 'add_ingredient_tabs' ), 10, 2 );
		
		add_action( 'wp_ajax_wpchef_check_user', array( $this, 'ajax_check_user' ) );

		add_filter('wpchef_hidden_fields', array( $this, 'hidden_fields_filter' ), 10, 2 );
	}
	
	public function constructor( $ingredients = array() )
	{
		if ( !is_array($ingredients) )
			$ingredients = array();
		
		$cnt = 0;
		?>
		<div id="recipe-ingredients">
			<?php
			foreach ( $ingredients as $ingredient )
				$cnt = $this->list_item( $ingredient );
			?>
		</div>
		<p class="hide-if-no-js">
			<div class="recipe-recent-options">
				<i class="fa fa-refresh fa-spin fa-lg" style="display:none"></i>
				<a class="button"><?php esc_html_e('Monitor For Changed Options', 'wpchef') ?></a>
			</div>
			<div class="add_ingredient_wrap">
				<select id="add_ingredient_type">
					<option value="" selected class="placeholder">Add New Ingredient
					<option value="plugin">Plugin</option>
					<option value="theme">Theme</option>
					<option value="option">Option</option>
					<?php if ( !$this->chef->offline ): ?>
					<option value="recipe">Recipe</option>
					<?php endif ?>
					<option value="action">Action</option>
				</select>
			</div>
			<div class="add_ingredient_wrap hide-if-js">
				<select id="add_ingredient_action">
					<option value="" selected class="placeholder">Select Action...</option>
					<?php foreach( wpchef_ingredient::instance()->actions as $action => $title ): ?>
					<option value="<?php echo $action?>"><?php echo esc_html($title)?></option>
					<?php endforeach ?>
				</select>
			</div>
			&nbsp; <i class="fa fa-refresh fa-spin fa-lg" style="display:none"></i>
			<div id="recipe_recent_options">
				<table class="widefat list-table stripped">
					<tr class="recent-options-hide">
						<td colspan="3">
							<a href="#"><i class="fa fa-times text-danger"></i></a>
							<div class="notice notice-warning inline"><p>
								<i class="fa fa-refresh fa-spin"></i>
								&nbsp;
								<?php esc_html_e('Monitoring for changed options. Open your site in a new window and start making changes in the settings. They will appear here automatically.', 'wpchef') ?>
							</p></div>
				</table>
			</div>
			<div id="recipe_add_ingredient" class="recipe-ingredient"></div>
			
			<!--
			&nbsp;
			<a href="#" id="save-ingredients" class="button button-primary" disabled>Save Ingredients</a>
			-->
		</p>
		<script>
		'use strict';
		
		jQuery( function($){
			var ingredients = $('#recipe-ingredients');
			var addbox = $('#recipe_add_ingredient');
			
			function remove_button_click(){
				if ( window.confirm('Are you sure?') )
				{
					$(this)
						.closest('.recipe-ingredient').fadeOut( function(){ $(this).remove() } )
						.closest('form').data('changed', true);
					
				}
				
				return false;
			}
			
			ingredients.on('click', '.ingredient-remove-button', remove_button_click );
			$('.ingredient-remove').remove();
			
			$('.ingredient-hint').tooltip({
				content: function(){
					var title = $( this ).attr( "title" ) || "";
					title = $( "<a>" ).text( title ).html();
					return title.split("\n").join('<br>');
				}
			});
			
			function package_search(){
				var btn = $(this);
				var input = addbox.find('.ingredient-search');
				var row = btn.closest('table');
				
				var type = addbox.find('.ingredient-type' ).val();
				
				btn.attr('disabled', 'disabled');
				
				var oldxhr = addbox.data('xhr-search');
				if ( oldxhr )
				{
					addbox.data('xhr-search', false);
					oldxhr.customBreak = true;
					oldxhr.responseText = '';
					oldxhr.abort();
				}
				
				$('.loadinfo', row).removeClass('hidden');
				
				var xhr = $.post( ajaxurl, {
						action: 'wpchef_search_packages',
						q: input.val(),
						p: btn.data('page'),
						type: type
					} )
					.done( function( data ){
						var listing = row.next().stop().show()
							.html( data )
							.find( 'table' );
						
						packages_pagination( listing );
						
						listing.find('.select-founded-plugin').click( function()
							{
								var $this = $(this);
								$this.siblings('.fa-spin').stop().fadeIn();
								
								var data = $this.data();
								data.type = type;
								
								add_ingredient( data, function( ingredient ){
									ingredient.removeClass('closed');
									$('#add_ingredient_type').val('').change();
								}, function(){
									$this.siblings('.fa-spin').stop().fadeOut();
								} );
								
								return false;
								
								var tr = $(this).closest('tr');
								var data = {
									type: type,
									name: tr.find('.package-search-link').text(),
									slug: tr.find('.package-search-slug').text()
								}
								var desc = $('#ingredient_description');
								if ( desc.val().length == 0 )
									desc.val( $(this).data('description') );
								
								var result = $('<div">');
								result
									.append( $('<input type="hidden" data-name="name">').val( data.name ) )
									.append( $('<input type="hidden" data-name="slug">').val( data.slug ) )
								
								if ( $(this).data('template') )
									result.append( $('<input type="hidden" data-name="template">').val( $(this).data('template') ) );
								
								result
									.append(
										$('<a target="_blank">')
											.attr('href', 'https://wordpress.org/plugins/' + data.slug + '/')
											.text( data.name )
									)
									.append( ' ' + type.charAt(0).toUpperCase() + type.slice(1) );
								
								$(this).closest('.package-tabs').fadeOut( function() {
									$(this).remove();
									
									$('#recipe_add_ingredient')
										.find('.form-table')
										.prepend( '<tr><th><td class="selected-result">' )
											.find( '.selected-result' )
											.append( result )
								} );
									
								
								/*
								add_ingredient( data, function(){
									addbox.fadeOut( function(){ addbox.html('') } );
								} );
								*/
								
								return false;
							});
						
					} )
					.fail( function( jqXHR, status ){
						row.next().stop().show().children().text('Connection error');
					})
					.always( function(){
						$('.loadinfo', row).addClass('hidden');
						btn.removeAttr('disabled');
					});
					
				addbox.data( 'xhr-search', xhr );
				btn.data('page', 0);
				return false;
			};
			addbox.on('click', '.package-search', package_search );
			
			function packages_pagination( table ) {
				var results = table.data('results');
				var pages = Math.max(1, table.data('pages') );
				var page =  Math.max(0, table.data('page') );
				
				if ( pages < 2 || !results )
					return;
				
				var pagination = $('<div class="tablenav-pages">');
				
				if ( results )
					pagination.append( $('<span class="displaying-num">').text(results+' items') );
				
				if ( pages > 1 )
				{
					var links = $('<span class="pagination-links">');
					
					if ( page > 0 )
						links.append( '<a class="first-page" href="#" data-page="0">«</a> ' );
						
					if ( page > 1 )
						links.append( '<a class="prev-page" href="#" data-page="'+(page-1)+'">‹</a> ' );
					
					links.append('<span class="paging-input">'+page+' of <span class="total-pages">'+pages+'</span></span>');
					
					if ( pages - page > 2 )
						links.append( '<a class="next-page" href="#" data-page="'+(page+1)+'">›</a> ' );
					
					if ( pages - 1 > page )
						links.append( '<a class="next-page" href="#" data-page="'+(pages-1)+'">»</a> ' );
					
					pagination.append( links );
				}
				
				$('a', pagination).click( function(){
					$(this)
						.html('<i class="fa fa-refresh fa-spin"></i>')
						.closest('.recipe-ingredient')
						.find('.package-search')
						.data( 'page', $(this).data('page') )
						.click();
					
					return false;
				} );
				
				table.after( pagination );
				pagination.wrap('<div class="tablenav bottom">');
			}

			function recipe_select()
			{
				var val = $(this).val();
				if ( val.match(/^\d+$/) )
					$(this).closest('.recipe-ingredient').find('#ingredient_description').val( $(':selected', this).data('description') );

				$('#ingredient_name, #ingredient_slug', addbox).val('');
			}
			addbox.on( 'change', '.ingredient-recipe-select', recipe_select );

			function recipes_refresh(){
				var btn = $(this);
				btn.children('.fa').addClass('fa-spin');
				
				$.post( ajaxurl, {
					action: 'wpchef_refresh_recipes'
				})
				.done( function(data){
					if ( typeof data != typeof [] )
					{
						window.alert('Connection error');
						return;
					}
					
					recipes_fill( data );
				} )
				.fail( function(){
					window.alert( 'Connection error' );
				})
				.always( function(){
					btn.children('.fa').removeClass( 'fa-spin' );
				})
				
				return false;
			}
			function recipes_fill( recipes )
			{
				var type = addbox.find('.ingredient-type' ).val();
				
				var select = addbox.find('.ingredient-recipe-select');
				select.html('<option value="">');
				
				for ( var i in recipes )
				{
					select.append(
						$('<option>')
							.val( recipes[i].id )
							.text( recipes[i].name )
							.data('description', recipes[i].description )
					);
				}
			}
			addbox.on( 'click', '.ingredient-recipes-refresh', recipes_refresh );
			
			function add_ingredient_form( type, callback )
			{
				$('.recent-options-hide a', optionsbox).click();
				
				addbox.load( ajaxurl, {
					action: 'wpchef_add_ingredient',
					type: type
				}, function(data){
					$('.wpchef_auth_only', addbox).click( wpchef.auth_only_click );
					$('.package-tabs', addbox).tabs();
					
					$('.ingredient-hint', addbox).tooltip(); //click( show_hint );
					
					$('#add_ingredient_type').parent().siblings('.fa').stop().fadeOut();
					addbox.stop().fadeIn();
					
					$('#ingredient_add', addbox).click( function() {
						$(this).siblings('.ingredient-add-loadinfo').children().stop().fadeIn();
						var data = {};
						$('[data-name]', addbox).each( function(){
							var $this = $(this);
							if ( $this.attr('type') == 'checkbox' && !this.checked )
								return;
							
							data[ $this.data('name') ] = $this.val();
						} );
						add_ingredient( data, function() {
							addbox.fadeOut( function(){ addbox.html('') } );
							$('#add_ingredient_type').val('');
							$('#add_ingredient_action').val('').parent().fadeOut();
						} );
					} );

					if ( typeof callback == 'function' )
						callback( addbox );
				} );
			};
			
			var counter = <?=(int)$cnt?>;
			function add_ingredient( data, done, fail, always ){
				counter++;
				
				data.action = 'wpchef_ingredient';
				data.counter = counter;
				
				$.post( ajaxurl, data )
				.done( function(data){
					var ingredient = $( data );
					
					$('.handlediv', ingredient).click( function(){
						$(this).closest('.recipe-ingredient').toggleClass('closed');
					} );
					
					ingredient.hide();
					ingredients.append( ingredient );
					
					$('.ingredient-remove-button', ingredient).data( 'new', true );
					ingredient.closest('form').data('changed', true);
					
					$('.ingredient-hint', ingredient).tooltip(); //click( show_hint );
					$('.ingredient-remove', ingredient).remove();
					
					if ( !ingredient.hasClass('invalid' ) )
						ingredient.css({backgroundColor: '#d2f5b0', transition: 'background 5s'}).show().css( {backgroundColor:'#fff'}, 3000 );
					
					else
						ingredient.fadeIn();
					
					wpchef.scroll_to( ingredient );
					
					if ( typeof done == 'function' )
						done( ingredient );
				})
				.always( function(){
					$('#ingredient_add', addbox).siblings('.fa').stop().fadeOut();
					if ( typeof always == 'function' )
						always();
				})
				.fail( function(){
					alert( 'Connection error' );
					if ( typeof fail == 'function' )
						fail();
				} )
				
			};
			$('#add_ingredient_type').change( function(){
				addbox.stop().fadeOut( function(){ addbox.html('') } );
				var type = $(this).val();
				if ( type == 'action' )
				{
					$('#add_ingredient_action').val('').parent().stop().fadeIn().css("display","inline-block");
				}
				else if ( type.length != 0 )
				{
					$('#add_ingredient_action').parent().stop().fadeOut();
					$(this).parent().siblings( '.fa' ).stop().fadeIn();
					add_ingredient_form( type );
				}
				return true;
			} );
			$('#add_ingredient_action').change( function(){
				var type = $(this).val();
				if ( type.length != 0 )
				{
					$(this).parent().siblings( '.fa' ).stop().fadeIn();
					add_ingredient_form( 'action-'+type );
				}
				return true;
			} );
			
			var optionsbox = $('#recipe_recent_options');
			var doing_options_check = false;
			var options_check_timeout = null;
			function recipe_recent_options()
			{
				var btn = $(this);
				//btn.siblings( '.fa' ).stop().show();
				doing_options_check = true;
				
				$('#add_ingredient_type, #add_ingredient_action').val('').change();
				
				$.get( ajaxurl, { action: 'wpchef_recent_options' })
					.done( function(data) {
						if ( data )
						{
							optionsbox.find('tr').first().after( data );
						}
					} )
					.fail( function(){
						window.alert('Connection error');
					})
					.always(function(){
						optionsbox.fadeIn();
						//btn.siblings('.fa').stop().fadeOut();
						if ( doing_options_check )
							options_check_timeout = setTimeout( function(){ btn.click() }, 5000 );
					})
			}
			$('.recipe-recent-options .button').click( recipe_recent_options );
			
			$('.recent-options-hide a', optionsbox).click( function(){
				if ( options_check_timeout )
					clearTimeout( options_check_timeout );
					
				options_check_timeout = null;
				doing_options_check = false;
				
				var $this = $(this);
				optionsbox.stop().fadeOut( function(){
					$this.closest('tr').siblings().remove();
				} );
				return false;
			});
			
			optionsbox.on( 'click', '.use-recent-option', function(){
				var btn = $(this);
				btn.siblings( '.fa' ).stop().addClass('active');
				
				var data = {
					type: 'option',
					option: btn.data('option'),
					value: btn.attr('data-value')
				}
				
				add_ingredient( data, null, null, function() {
					var row = btn.closest('tr');
					if ( row.prev().children('th').size() > 1 && row.next().children('th').size() != 1 )
						row = row.add( row.prev() );
					
					row.fadeOut( function(){ row.remove() } );
				})
			} );
			
			addbox.on('click', '.ingredient-cancel', function(){
				addbox.fadeOut( function(){ addbox.html('') } );
				$('#add_ingredient_type').val('');
			} );
			
			if ( !wpchef.me )
			addbox.one('click', '.repository-tab-link', function(){
				addbox.find('.ingredient-packages-refresh').click();
			});
			
			ingredients.sortable({
				axis: 'y',
				handle: '.ingredient-move-button'
			});

			<?php do_action('wpchef_editor_js', $ingredients) ?>
		} );
		</script>
		<div style="display:none">
		<?php
		wp_editor( '', 'ingredient_content_init', array(
			'media_buttons' => false,
			'textarea_rows' => 10,
			'teeny' => true,
			'tinymce' => false,
			'quicktags' => true,
		) );
		?>
		</div>
		<?php
		add_thickbox();
	}
	
	function list_item( $ingredient, &$id = null )
	{
		static $cnt = 0;
		static $all_fields = array('type', 'action', 'wpchef_id', 'name', 'option', 'template', 'template_name', 'content', 'placement', 'url', 'author', 'author_uri', 'slug', 'override', 'login', 'email', 'role', 'alert_type', 'runon');

		$ingredient = $this->normalize( $ingredient );

		$id = $id ? $id : $cnt++;
		$type = $ingredient['type_full'];
		?>
			<div id="ingredient_<?=$id?>" class="recipe-ingredient postbox<?=empty($ingredient['fixed_open'])?' closed':''?><?=$ingredient['valid']?'':' invalid'?>" data-id="<?=$id?>" data-type="<?=$type?>">
				<div class="ingredient-remove-button hide-if-no-js" title="Remove ingredient"><span class="dashicons dashicons-no"></span></div>
				<div class="ingredient-move-button hide-if-no-js"><span class="fa fa-arrows"></span></div>
				<div class="handlediv button-link" title="Click to toggle">
					<span class="screen-reader-text">Toggle ingredient: <?=$ingredient['title']?></span>
					<span class="toggle-indicator" aria-hidden="true"></span>
				</div>
				<h2 class="ingredient-title"><span><?=$ingredient['title']?></span></h2>
				<div class="inside">
					<?php if ( !empty( $ingredient['alert'] ) ): ?>
					<div class="notice notice-warning"><p><?=esc_wpchef( $ingredient['alert'] )?></p></div>
					<?php endif ?>
					
				<?php if ( $ingredient['valid'] ): ?>
					<?php $hidden_fields = apply_filters( 'wpchef_hidden_fields', $all_fields, $ingredient ); ?>
					<?php foreach( $hidden_fields as $property ): ?>
					<input name="ingredient[<?=$id?>][<?php echo $property ?>]" type="hidden" value="<?=esc_attr($ingredient[ $property ])?>" class="ingredient-<?php echo $property?>"/>
					<?php endforeach ?>
					
				<?php endif ?>
					
					<table class="form-table">

						<?php if ( $this->option_for_type( 'link', $type ) ): ?>
						<tr>
							<th><label><?php if ( $ingredient['link_title'] ) echo esc_html($ingredient['link_title']) ?></label>
							
							<td>
								<?php if ( $ingredient['name'] ): ?>
								
									<?php if ( $ingredient['link'] && $ingredient['valid'] ): ?>
									<a class="ingredient-link" href="<?=esc_attr($ingredient['link'])?>" target="_blank"><b><?=esc_html($ingredient['name'])?></b></a>
									
									<?php else: ?>
									<b><?=esc_html($ingredient['name'])?></b>
									
									<?php endif ?>
									
									<?php if ( $ingredient['author'] ): ?>
									<?php printf(
										esc_html_x('by %s', 'Plugin/Theme/Recipe author', 'wpchef'),
										esc_url($ingredient['author_uri'])
										? sprintf( 
											'<a href="%s" target="_blank">%s</a>',
											esc_url($ingredient['author_uri']),
											esc_html($ingredient['author'])
										)
										: esc_html($ingredient['author'])
									); ?>
									<?php endif; ?>
								
								<?php elseif ( $type == 'recipe' ): ?>
								<?php _e( 'Recipe not found.', 'wpchef' ) ?>
								
								<?php else: ?>
								<?php _e( 'Unknown package.', 'wpchef' ) ?>
								
								<?php endif ?>

						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'name', $type, 'show' ) ): ?>
						<tr>
							<th><label>Title</label>
							<td>
								<?php echo esc_html($ingredient['name'])?>
						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'login', $type, 'show' ) ): ?>
						<tr>
							<th><label>Username</label>
							<td>
								<?php echo esc_html($ingredient['login'])?>
								<?php if ( $ingredient['role'] ): ?>
								<p class="description"><?= esc_html($ingredient['role']) ?></p>
								<?php endif ?>
						<?php endif ?>
						
						<?php if ( $ingredient['email'] && $this->option_for_type( 'email', $type, 'show' ) ): ?>
						<tr>
							<th><label>User Email</label>
							<td>
								<?php echo esc_html($ingredient['email'])?>
						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'slug', $type, 'show' ) ): ?>
						<tr>
							<th><label>Slug</label>
							<td>
								<?php echo esc_html($ingredient['slug'])?>
						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'slug', $type, 'edit' ) ): ?>
						<tr>
							<th><label for="ingredient_slug_<?=$id?>">Slug</label>
							<td>
								<input name="ingredient[<?=$id?>][slug]" id="ingredient_slug_<?=$id?>" type="text" value="<?=esc_attr($ingredient['slug'])?>" class="ingredient-slug input-short"/>
						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'override', $type ) && $ingredient['override'] ): ?>
						<tr>
							<th>
							<td>
								<?= $ingredient['override'] == 'rename' ? 'Rename' : 'Override' ?> if exists.
						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'value', $type ) ): ?>
						<tr>
							<th>
								<label for="ingredient_value_<?=$id?>">
									Option Value
								</label>
								<span class="fa fa-question-circle ingredient-hint" title="Enter NULL if you want to delete the option completely from the website where the recipe will be installed."></span>
							<td>
								<input name="ingredient[<?=$id?>][value]" id="ingredient_value_<?=$id?>" type="text" value="<?=esc_attr($this->value_encode($ingredient['value']))?>" class="ingredient-value input-short"/>
						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'code', $type ) ): ?>
						<tr>
							<th>
								<label for="ingredient_code_<?=$id?>">
									PHP Code
								</label>
								<p class="description">All standard WordPress functions can be used here as well as any PHP code.</p>
							<td>
								<strong>&lt;?php<strong/><br />
								<textarea name="ingredient[<?=$id?>][code]" id="ingredient_code_<?=$id?>" rows="<?=min(max( 6, count($ingredient['code'])+2),25)?>" style="width:100%"><?=esc_textarea(implode("\n",$ingredient['code']))?></textarea><br />
								<strong>?&gt;</strong>
						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'content', $type, 'show-alert' ) ): ?>
						<tr>
							<th><label>Show Message</label>
							<td>
								<div class="notice notice-<?=$ingredient['alert_type']?> inline">
									<p><?=wp_kses_post($ingredient['content'])?></p>
								</div>
						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'runon', $ingredient['type'] ) ): ?>
						<tr>
							<th><label for="ingredient_runon_<?=$id?>">Run On</label>
							<td>
								<?php if ( $ingredient['runon'] == 'uninstall' ): ?>
								<?php esc_html_e('Deactivation', 'wpchef') ?>
								<?php else: ?>
								<?php esc_html_e('Activation', 'wpchef') ?>
								<?php endif ?>
						<?php endif ?>
						
						<?php if ( $this->option_for_type( 'description', $type ) ): ?>
						<tr>
							<th nowrap>
								<label for="ingredient_description_<?=$id?>">
								Description
								</label>
								<span class="fa fa-question-circle ingredient-hint" title="<?php esc_attr_e('This text will appear on the recipe activation/deactivation screen.', 'wpchef') ?>"></span>
								<p class="description">Optional</p>
							<td>
								<input name="ingredient[<?=$id?>][description]" id="ingredient_description_<?=$id?>" type="text" value="<?=esc_attr($ingredient['description'])?>" class="ingredient-description input-medium"/>
						<?php endif ?>
						
						<tr class="ingredient-remove hide-if-js">
							<th>
							<td>
								<label>
									<input name="ingredient[<?=$id?>][remove]" type="checkbox" value="1"/>
									Remove this ingredient
								</label>
					</table>
					
					<?php if ( $type == 'invalid' ): ?>
					<p>Ingredient will be removed</p>
					<?php else: ?>
					<input type="hidden" name="ingredient_order[]" value="<?=$id?>" />
					<?php endif ?>
				</div>
			</div>
		<?php
		return $cnt;
	}

	public function new_ingredient_theme( $ingredient )
	{
		$ingredient = $this->new_ingredient_package( $ingredient );

		if ( !empty( $ingredient['template'] ) && empty( $ingredient['template_name'] ) )
		{
			$template = wp_get_theme( $ingredient['template'] );
			if ( $template )
				$ingredient['template_name'] = $template->Name;

			if ( empty( $ingredient['template_name'] ) )
			{
				$url = 'https://api.wordpress.org/themes/info/1.1/?action=theme_information&request[slug]='.$ingredient['template'];
				$info = $this->remote_json( $url );

				if ( $info && !empty( $info['name'] ) )
					$ingredient['template_name'] = $info['name'];
			}
		}

		return $ingredient;
	}

	public function new_ingredient_plugin( $ingredient )
	{
		return $this->new_ingredient_package( $ingredient );
	}

	protected function new_ingredient_package( $ingredient )
	{
		if ( !empty($ingredient['slug']) && ( empty($ingredient['description']) || empty($ingredient['name']) ) )
		{
			$package = $this->resolve_wp_package( $ingredient['slug'], $ingredient['type'] );
			if ( !empty($package['success']) )
			{
				if ( !empty($ingredient['description']) )
					unset( $package['description'] );

				$ingredient = array_merge( $ingredient, $package );
			}
		}

		return $ingredient;
	}

	public function new_ingredient_action( $ingredient )
	{
		if ( !empty( $ingredient['action'] ) )
		{
			if ( in_array( $ingredient['action'], array('add_menu_item', 'add_page'), true ) && !empty( $ingredient['menu'] ) )
				$ingredient['placement'] = $ingredient['menu'];

		}

		if ( !empty( $ingredient['url'] ) )
			$ingredient['url'] = str_replace( untrailingslashit( home_url() ), '{:HOME_URL:}', $ingredient['url'] );

		return $ingredient;
	}

	public function new_ingredient_recipe( $ingredient )
	{
		$recipe = null;

		if ( !empty($ingredient['wpchef_id']) )
			$recipe = $this->chef->fetch_recipe( $ingredient['wpchef_id'] );

		elseif ( !empty( $ingredient['slug'] ) )
		{
			$recipe = $this->chef->oauth_request( 'recipe', array(
				'slug' => $ingredient['slug'],
			) );

			if ( $recipe )
			{
				$this->chef->cache_set( 'fetch_recipe_'.$recipe['wpchef_id'], $recipe );
				$ingredient['wpchef_id'] = $recipe['wpchef_id'];
			}
		}

		if ( $recipe )
		{
			$ingredient['name'] = $recipe['name'];
			$ingredient['slug'] = $recipe['slug'];
			if ( empty($ingredient['description']) )
				$ingredient['description'] = $recipe['description'];

			foreach ( array('author', 'author_uri') as $i => $option )
				if ( !empty( $recipe[ $option ] ) )
					$ingredient[ $option ] = $recipe[ $option ];
		}

		return $ingredient;
	}

	public function new_ingredient_option( $ingredient )
	{
		if ( array_key_exists( 'value', $ingredient ) )
			$ingredient['value'] = $this->value_decode( $ingredient['value'] );

		return $ingredient;
	}
	
	function ajax_list_item()
	{
		$id = (int)@$_POST['counter'];
		$ingredient = stripslashes_deep($_POST);
		
		if ( preg_match( '/^action-([a-z_]+)$/', $ingredient['type'], $m ) )
		{
			$ingredient['type'] = 'action';
			$ingredient['action'] = $m[1];
		}

		if ( !empty( $ingredient['type'] ) )
			$ingredient = apply_filters( 'wpchef_new_ingredient_'.$ingredient['type'], $ingredient );


		$ingredient = $this->normalize( $ingredient );
		$ingredient = apply_filters( 'wpchef_new_ingredient', $ingredient );

		$this->log( $ingredient );
		$this->list_item( $ingredient, $id );
		exit;
	}

	function add_ingredient_tabs( $type, $readonly )
	{
		if ( $type !== 'recipe' || $readonly || wpchef::instance()->offline )
			return;
		?>
		<li>
			<a href="#repository-tab" class="wpchef_auth_only repository-tab-link">
				My Recipes
			</a>
		</li>
		<?php
		add_action( 'wpchef_add_ingredient_tabs_content', array( $this, 'add_ingredient_tabs_content' ) );
	}

	function add_ingredient_tabs_content()
	{
		?>
		<div id="repository-tab">
			<table class="form-table">
				<tr>
					<td>
						<p style="margin-bottom:.5em">
							<?php printf(
								esc_html__('Here you can pick a recipe from your private account at %s', 'wpchef'),
								sprintf('<a href="%s" target="_blank">%s</a>', esc_attr($this->server), esc_html__('WPChef', 'wpchef'))
							) ?>
						</p>
						<select class="ingredient-recipe-select" data-name="wpchef_id">
							<option value="">
							<?php foreach( wpchef::instance()->my_recipes() as $recipe ): ?>
							<option value="<?= (int)$recipe['wpchef_id'] ?>" data-description="<?= esc_attr($recipe['description']) ?>"><?= esc_html($recipe['name']) ?></option>
							<?php endforeach ?>
						</select>
						<a href="#" class="ingredient-recipes-refresh button"><i class="fa fa-refresh"></i></a>
			</table>
		</div>
		<?php
		remove_action( 'wpchef_add_ingredient_tabs_content', array( $this, 'add_ingredient_tabs_content' ) );
	}
	
	function add_ingredient()
	{
		$this->new_item( $_POST['type'] );
		exit;
	}

	function new_item( $type )
	{
		$ingredient = array(
			'type' => $type,
		);
		$ingredient = $this->normalize( $ingredient );

		$me = wpchef::instance()->wpchef_me();
		$readonly = $me && empty( $me['admin_access'] );
		?>
		<?php /* <h2 class="ingredient-title"><?=esc_wpchef($ingredient['title'])?></h2> */ ?>
		<input type="hidden" data-name="type" value="<?=$type?>" class="ingredient-type">
		
		<?php /*if ( $this->option_for_type( 'template', $type ) ): ?>
		<input name="ingredient[<?=$id?>][template]" type="hidden" />
		<?php endif*/ ?>
		<?php if ( $type == 'recipe' ): ?>
		<p><b><?php esc_html_e('This will add another recipe with all its ingredients to this one.', 'wpchef') ?></b></p>
		<?php endif ?>

		<?php $default_tabs = in_array( $type, array('plugin','theme','recipe'), true ); ?>
		<?php if ( apply_filters( 'wpchef_add_ingredient_has_tabs', $default_tabs ) ): ?>
		<div class="package-tabs">
			<ul>
				<?php if ( $default_tabs ): ?>
				<li>
					<a href="#package-search-tab">
						<?php if ( $type == 'recipe' ): ?>
						Search on WPChef.org
						<?php else: ?>
						Search on WordPress.org
						<?php endif ?>
					</a>
				</li>
				<?php endif ?>
				<?php do_action('wpchef_add_ingredient_tabs', $type, $readonly ) ?>
			</ul>
			
			<?php if ( $default_tabs ): ?>
			<div id="package-search-tab" class="package-search-tab">
				<table class="form-table">
					<tr>
						<td>
					<input type="text" value="" class="ingredient-search input-short"/>
					&nbsp;
					<a href="#" class="button package-search">Find</a>
					&nbsp;
					<span class="loadinfo hidden"><span class="fa fa-spinner fa-spin"></span></span>
					<p class="error description hidden"></p>
					<?php if ( $this->option_for_type( 'wp', $type ) ): ?>
					<p class="description">Use as regular WordPress search or enter plugin address from WordPress.org.</p>
					<?php else: ?>
					<p class="description">Only public recipes will be found.</p>
					<?php endif ?>
				</table>
				<div style="display:none" class="search-results"></div>
			</div>
			<?php endif ?>
			<?php do_action('wpchef_add_ingredient_tabs_content' ) ?>

			<?php if ( !$this->chef->offline && !$readonly && $type == 'recipe' ): ?>

			<?php endif ?>
		</div>
		<?php endif ?>
		<table class="form-table">
		
			<?php if ( $this->option_for_type( 'name', $type, 'new' ) ): ?>
			<tr>
				<th><label for="ingredient_name">Title</label>
				<td>
					<input data-name="name" type="text" class="input-short" id="ingredient_name"/>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'slug', $type, 'new' ) ): ?>
			<tr>
				<th><label for="ingredient_slug">Slug</label>
				<td>
					<input data-name="slug" type="text" class="input-short" id="ingredient_slug"/>
			<?php endif ?>
	
			<?php if ( $this->option_for_type( 'url', $type ) ): ?>
			<tr>
				<th><label for="ingredient_url">URL</label>
				<td>
					<input data-name="url" type="text" class="input-short" id="ingredient_url"/>
					<p class="description"><?php printf(
						esc_html__('"%s" will be replaced with installation WP URL.', 'wpchef'),
						esc_html( untrailingslashit( home_url() ) )
					) ?></p>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'menu', $type ) ): require_once( ABSPATH . 'wp-admin/includes/nav-menu.php' ); ?>
			<tr>
				<th><label for="ingredient_menu"><?php esc_html_e('Select Menu','wpchef') ?></label>
				<td>
					<select data-name="menu" id="ingredient_menu">
						<option vlaue="">
						<?php foreach( get_registered_nav_menus() as $location => $name ): ?>
						<option value="<?php echo esc_attr($location)?>"><?php echo esc_html( $name ) ?></option>
					<?php endforeach ?>
					</select>
			<?php endif ?>
	
			<?php if ( $this->option_for_type( 'alert_type', $type ) ): ?>
			<tr>
				<th><label for="ingredient_alert_type"><?php esc_html_e('Alert Type','wpchef') ?></label>
				<td>
					<select data-name="alert_type" id="ingredient_alert_type" class="text-info" onchange="this.className = 'text-' + this.value; return true;">
						<option value="info" class="text-info">Info
						<option value="success" class="text-success">Success
						<option value="warning" class="text-warning">Warning
						<option value="error" class="text-error">Error
					</select>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'content', $type ) ): ?>
			<tr>
				<th><label for="ingredient_content"><?php echo $type == 'action-alert' ? __('Text to Display', 'wpchef') : __('Content', 'wpchef')?></label>
				<td>
					<?php /*<textarea class="wp-editor-area" rows="10" autocomplete="off" cols="40" name="ingredient_content" id="ingredient_content"></textarea>*/ ?>
					<?php wp_editor( '', 'ingredient_content', array(
						'media_buttons' => false,
						'textarea_rows' => 10,
						'teeny' => true,
						'tinymce' => false,
						'quicktags' => true,
					) );
					?>
					<script>
						var settings = tinyMCEPreInit.qtInit.ingredient_content_init;
						settings.id = 'ingredient_content';
						quicktags( settings );
						QTags._buttonsInit();
						
						jQuery(function($){
							$('#ingredient_content').attr('data-name','content');
						})
					</script>
					<!-- <input data-name="content" type="text" class="input-short" id="ingredient_content"/> -->
			<?php endif ?>
	
			
			<?php if ( $this->option_for_type( 'option', $type ) ): ?>
			<tr>
				<th>
					<label for="ingredient_option">
						Option Name
					</label>
				<td><input data-name="option" id="ingredient_option" type="text" class="input-short" />
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'value', $type ) ): ?>
			<tr>
				<th nowrap>
					<label for="ingredient_value">Option Value</label>
					<span class="fa fa-question-circle ingredient-hint" title="Enter NULL if you want to delete the option completely from the website where the recipe will be installed."></span>
				<td>
					<input data-name="value" id="ingredient_value" type="text" class="input-short"/>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'code', $type ) ): ?>
			<tr>
				<th>
					<label for="ingredient_code">
						PHP Code
					</label>
					<p class="description">All standard WordPress functions can be used here as well as any PHP code.</p>
				<td>
					<strong>&lt;?php<strong/><br />
					<textarea data-name="code" id="ingredient_code" rows="6" style="width:100%"></textarea><br />
					<strong>?&gt;</strong>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'override', $type ) ): ?>
			<tr>
				<th><label for="ingredient_override">If Exists</label>
				<td>
					<select data-name="override" id="ingredient_override">
						<option value="">
							Do nothing
						<option value="1">
							Override
						<option value="rename">
							Rename
					</select>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'login', $type ) ): ?>
			<tr>
				<th>
					<label for="ingredient_login">
					Username
					</label>
				<td>
					<input data-name="login" id="ingredient_login" type="text" class="input-short"/>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'email', $type ) ): ?>
			<tr>
				<th>
					<label for="ingredient_email">
					User Email
					</label>
				<td>
					<input data-name="email" id="ingredient_email" type="email" class="input-short"/>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'role', $type ) ): ?>
			<tr>
				<th>
					<label for="ingredient_email">
					User Role
					</label>
				<td>
					<select data-name="role" id="ingredient_role">
						<option value=""></option>
						<?php wp_dropdown_roles() ?>
					</select>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'runon', $type ) ): $can_uninstall = $this->option_for_type( 'runon_uninstall', $type ) ?>
			<tr>
				<th><label for="ingredient_runon">Run Once On</label>
				<td>
					<select data-name="runon" id="ingredient_runon" <?php if (!$can_uninstall): ?> disabled <?php endif ?>>
						<option value="">
							Activation
						<?php if ( $can_uninstall ): ?>
						<option value="uninstall">
							Deactivation
						<?php endif ?>
					</select>
			<?php endif ?>
			
			<?php if ( $this->option_for_type( 'description', $type ) ): ?>
			<tr>
				<th nowrap>
					<label for="ingredient_description">
					Description
					</label>
					<span class="fa fa-question-circle ingredient-hint" title="<?php esc_attr_e('This text will appear on the recipe\'s activation/deactivation screen.', 'wpchef') ?>"></span>
					<p class="description">Optional</p>
				<td>
					<input data-name="description" id="ingredient_description" type="text" class="input-medium"/>
			<?php endif ?>
			
			<tr class="submit">
				<th>
				<td>
					<a class="button" id="ingredient_add">Done</a>
					<span class="ingredient-add-loadinfo">
						<i class="fa fa-refresh fa-spin"></i>
					</span>
					<a class="button ingredient-cancel">Cancel</a>
		</table>
		<?php
	}
	
	function hidden_fields_filter( $fields, $ingredient )
	{
		foreach ( $fields as $i => $field )
			if ( !$this->option_for_type( $field, $ingredient['type_full'], 'hidden' ) )
				unset( $fields[ $i ] );

		return array_values( $fields );
	}
	
	/*
		mode: "new" for new item, "show" for view only, "edit" for editable, "hidden" for hidden (not editable)
	*/
	function option_for_type( $option, $type, $mode = 'new' )
	{
		$option_for = $this->option_for( $option, $mode );
		
		if ( $option_for == 'all' )
			return true;
		
		$option_for  = (array)$option_for;
		
		if ( in_array( $type, $option_for ) )
			return true;
		
		if ( substr( $type, 0, 7 ) == 'action-' )
			return in_array( 'action', $option_for );
		
		return false;
	}
	
	/*
	*/
	function option_for( $option, $mode )
	{
		if ( $option == 'description' || $option == 'type' )
			return 'all';
		
		if ( $option == 'wpchef_id' )
			return 'recipe';
		
		if ( in_array( $option , array( 'template', 'template_name' ) ) )
			return 'theme';
		
		if ( $option == 'name' )
		{
			if ( $mode == 'new' )
				return array('action-add_page', 'action-add_menu_item');
			
			elseif ( $mode == 'show' )
				return 'action-add_page';
			
			else
				return array( 'theme', 'plugin', 'recipe', 'action-add_page', 'action-add_menu_item' );
		}
			
		if ( $option == 'link' )
			return array( 'theme', 'plugin', 'recipe', 'action-add_menu_item' );
			
		if ( in_array( $option, array( 'wp' ) ) )
			return array( 'theme', 'plugin' );

		if ( $option == 'repository' )
			return 'recipe';
		
		if ( $option == 'slug' )
		{
			$types = array();
			
			if ( $mode == 'new' || $mode == 'hidden' || $mode == 'show' )
			{
				$types[] = 'action-add_page';
				$types[] = 'action-deactivate_plugin';
				$types[] = 'action-uninstall_plugin';
			}
			
			if ( $mode == 'hidden' )
			{
				$types[] = 'theme';
				$types[] = 'plugin';
			}
			return $types;
		}
		
		if ( $option == 'action' )
			return 'action';
		
		if ( $option == 'runon' )
			return 'action';
		
		if ( $option == 'runon_uninstall' )
			return array( 'action-eval', 'action-alert', 'action-uninstall_plugin', 'action-deactivate_plugin' );
		
		if ( in_array( $option, array( 'option', 'value' ) ) )
			return 'option';
		
		if ( $option == 'code' )
			return 'action-eval';
		
		if ( $option == 'content' )
		{
			if ( $mode = 'show-alert' )
				return 'action-alert';
			
			return array( 'action-add_page', 'action-alert' );
		}
		
		if ( $option == 'override' )
			return 'action-add_page';
		
		if ( $option == 'url' )
			return 'action-add_menu_item';
		
		if ( in_array( $option, array('menu', 'placement') ) )
			return array( 'action-add_page', 'action-add_menu_item' );
		
		if ( in_array( $option, array('author', 'author_uri') ) )
			return array( 'theme', 'plugin', 'recipe' );
		
		if ( in_array( $option, array('login', 'email', 'role') ) )
			return 'action-add_user';
		
		if ( $option == 'alert_type' )
			return 'action-alert';
		
		return 'invalid';
	}
	
	function invalid_post( $location )
	{
		remove_filter( 'redirect_post_location', array( $this, 'invalid_post' ), 20 );
		return add_query_arg( 'invalid_ingredient', 1, $location );
	}
	
	function admin_notices()
	{
		if ( !empty($_GET['invalid_ingredient']) ) : ?>
		<div class="error">
			<p><?=__('Please re-check your ingredients. Some of them are incomplete.', 'wpchef')?></p>
		</div>
		<?php
		endif;
	}
	
	function resolve_by_url( $url, $type )
	{
		$this->log( $url, $type );
		if ( 
			preg_match('~^https?://(?:[a-z]+\.)?wordpress\.org/(plugin|theme)s/([a-z\d_-]+)(?:/.*)?$~i', $url, $m)
			||
			preg_match('~^https?://downloads\.wordpress\.org/(plugin|theme)/([a-z\d_-]+)\.(?:[\d\.]+\.)?zip$~i', $url, $m)
		)
		{
			if ( $m[1] != $type )
				return false;
			
			$slug = $m[2];
			
			return $this->resolve_wp_package( $slug, $type );
		}
		
		return false;
	}

	function resolve_wp_package( $slug, $type )
	{
		$this->log( $slug, $type );
		
		if ( $type == 'theme' )
			$url = 'https://api.wordpress.org/themes/info/1.1/?action=theme_information&request[slug]='.$slug;
		
		elseif ( $type == 'plugin' )
			$url = "https://api.wordpress.org/plugins/info/1.0/$slug.json?fields=short_description";
		
		else return false;
		
		$info = $this->remote_json( $url );
		if ( empty($info['name']) )
			return array(
				'error' => 'Can\'t found package at WordPress.org',
			);
		//var_dump( $info );

		$rez = array(
			'slug' => $slug,
			'name' => $info['name'],
			'id' => '',
			'success' => true,
			'description' => $type == 'theme' ? $info['sections']['description']: $info['short_description'],
			'link' => "https://wordpress.org/plugins/$slug/",
		);
		
		if ( !empty( $info['author'] ) )
		{
			if ( preg_match( '~<a href="(.*)">(.*)</a>~i', $info['author'], $m ) )
			{
				$rez['author'] = $m[2];
				$rez['author_uri'] = $m[1];
			}
			else
				$rez['author'] = $info['author'];
		}
		
		if ( !empty( $info['template'] ) )
			$rez['template'] = $info['template'];
		
		//var_dump( $info, $rez ); exit;
		
		return $rez;
	}
	
	function search_packages()
	{
		if ( !in_array(@$_POST['type'], array('plugin','theme','recipe')) )
		{
			echo '<div class="notice notice-error"><p>Invalid Request</p></div>';
			exit;
		}
		if ( empty($_POST['q']) )
		{
			echo '<div class="notice notice-error"><p>Please specify a '.$_POST['type'].' name.</p></div>';
			exit;
		}
		
		if ( $_POST['type'] == 'plugin' )
			$this->search_packages_plugin();
		
		elseif ( $_POST['type'] == 'theme' )
			$this->search_packages_theme();
		
		elseif ( $_POST['type'] == 'recipe' )
			$this->search_packages_recipe();
		
		exit;
	}
		
	function search_packages_plugin()
	{
		$args = array(
			'page' => @$_POST['p'],
			'per_page' => 10,
			'fields' => array(
				'last_updated' => true,
				'icons' => true,
				'active_installs' => true
			),
			'locale' => get_locale(),
			'installed_plugins' => array(),
			'search' => wp_unslash( @$_POST['q'] ),
		);
		
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		do {
			$api = plugins_api( 'query_plugins', $args );

			if ( !$first && is_wp_error( $api ) ) {
				echo '<div class="notice notice-error"><p>Connection error.</p></div>';
				exit;
			}
			
			$args['page']--;
		} while ( empty($api->plugins) && $args['page'] > 0 );
		
		$items = (array)$api->plugins;
		
		if ( empty( $_POST['p'] ) && ( $first = $this->resolve_by_url( $_POST['q'], 'plugin' ) ) )
		{
			$first['short_description'] = $first['description'];
			array_unshift( $items, (object)$first );
		}
		
		if ( $items ): ?>
		<table class="wp-list-table widefat fixed striped ingredient-search-plugins" <?php if ( $api->info['pages'] > 1 ): ?> data-pages="<?=(int)$api->info['pages']?>" data-page="<?=(int)$api->info['page']?>" <?php endif ?> data-results="<?=(int)$api->info['results']?>" >
			<thead>
				<tr>
					<th>Name
					<th>Slug
					<th>Action
			<tbody>
				<?php foreach ( $items as $plugin ) : ?>
				<tr>
					<td>
						<a href="https://wordpress.org/plugins/<?=esc_attr($plugin->slug)?>/" target="blank" class="package-search-link"><?=esc_html($plugin->name)?></a>
					
					<td class="package-search-slug"><?=esc_html($plugin->slug)?></td>
					
					<td>
						<a class="select-founded-plugin button button-small" href="#" data-slug="<?=esc_attr($plugin->slug)?>">Select</a>
						<i class="fa fa-refresh fa-spin"></i>
				<?php endforeach ?>
		</table>
		<?php else: ?>
		<div class="notice notice-info"><p>No plugins found.</p></div>
		<?php endif;
		
		exit;
	}
	
	function search_packages_theme()
	{
		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		global $theme_field_defaults;
		
		$args = array(
			'page' => (int)@$_POST['p'],
			'per_page' => 10,
			'fields' => $theme_field_defaults,
			'search' => wp_unslash( $_POST['q'] ),
		);
		
		$api = themes_api( 'query_themes', $args );

		if ( is_wp_error( $api ) ) {
			echo '<div class="notice notice-error"><p>Connection error.</p></div>';
			exit;
		}
		$items = (array)$api->themes;
		
		if ( empty( $_POST['p'] ) && ( $first = $this->resolve_by_url( $_POST['q'], 'theme' ) ) )
			array_unshift( $items, (object)$first );

		if ( $items ): ?>
		<table class="wp-list-table widefat fixed striped ingredient-search-plugins" <?php if ( $api->info['pages'] > 1 ): ?> data-pages="<?=(int)$api->info['pages']?>" data-page="<?=(int)$api->info['page']?>" <?php endif ?> data-results="<?=(int)$api->info['results']?>">
			<thead>
				<tr>
					<th>Name
					<th>Slug
					<th>Action
			<tbody>
				<?php foreach ( $items as $theme ) : ?>
				<tr>
					<td>
						<a href="https://wordpress.org/themes/<?=esc_attr($theme->slug)?>/" target="blank" class="package-search-link"><?=esc_html($theme->name)?></a>
					
					<td class="package-search-slug"><?=esc_html($theme->slug)?></td>
					
					<td>
						<a class="select-founded-plugin button button-small" href="#" data-slug="<?=esc_attr($theme->slug)?>">Select</a>
				<?php endforeach ?>
		</table>
		<?php else: ?>
		<div class="notice notice-info"><p>No themes found.</p></div>
		<?php endif;
		
		exit;
	}
	
	function search_packages_recipe()
	{
		$api = apply_filters( 'wpchef_search_recipes', array(), array(
			's' => $_POST['q'],
			'p' => (int)@$_POST['p'],
			)
		);
		
		if ( empty( $_POST['p'] ) )
		{
			$info = $this->chef->resolve_recipe( $_POST['q'] );
			if ( $info && !empty( $info['success'] ) )
				array_unshift( $api['items'], $info );
		}
		
		if ( !$api ): ?>
		<div class="notice notice-error"><p>Connection error.</p></div>
		<?php elseif ( $api['items'] ): ?>
		<table class="wp-list-table widefat fixed striped ingredient-search-plugins">
			<thead>
				<tr>
					<th>Name
					<th>Slug
					<th>Action
			<tbody>
				<?php foreach ( $api['items'] as $recipe ): ?>
				<tr>
					<td>
						<a href="<?=esc_attr($recipe['link'])?>" target="blank" class="package-search-link"><?=esc_html($recipe['name'])?></a>
					
					<td class="package-search-slug"><?=esc_html($recipe['slug'])?></td>
					
					<td>
						<a class="select-founded-plugin button button-small" href="#" data-slug="<?=esc_attr($recipe['slug'])?>">Select</a>
				<?php endforeach ?>
		</table>
		<?php else: ?>
		<div class="notice notice-info"><p>No recipes found.</p></div>
		<?php endif;
		
		exit;
	}
	
	function ajax_check_user()
	{
		//check_ajax_referer( 'wpchef_check_user', 'sec' );
		if ( empty( $_POST['check_by'] ) || !in_array( $_POST['check_by'], array('login','email') ) )
			$this->json_error();
		
		if ( empty( $_POST['value'] ) )
			$this->json_error();
		
		$user = get_user_by( $_POST['check_by'], stripslashes( $_POST['value'] ) );
		
		$this->json_success( $user ? true : false );
	}
}

endif;
