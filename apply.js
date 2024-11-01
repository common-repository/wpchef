'use strict';
jQuery( function($){
	var log = $('<div id="recipe_log" class="notice notice-error inline">');
	var form = $('#recipe_install');
	var list = $('.ingredients-list', form);

	var allboxes = $('.recipe-actionbox', form);
	var credentials = null;
	
	allboxes.not(':disabled')
	.data('enabled', true )
	.click( function()
	{
		var name = this.name;
		if ( !name )
			return true;
		
		var siblings = allboxes
		.filter( function (){ return this.name == name } )
		.not(this);
		
		if ( siblings.size() == 0 )
			return true;
		
		if ( $(this).data('uninstall') == '' )
		{
			if ( this.checked )
				siblings.removeAttr('checked');
		}
		else
		{
			if ( this.checked )
				siblings.attr( 'checked', 'checked' );
			else
				siblings.removeAttr('checked');
		}
		
		return true;
	})
	.each( function(){
		$(this).data('default-checked', this.checked );
	} );
	//filter(':checked').data('default-checked', true);
	
	var btn_apply = $('#recipe_apply');
	
	function apply()
	{
		if ( btn_apply.attr('disabled') )
			return false;
		
		btn_apply
		.attr( 'disabled', 'disabled' )
		.siblings('.loadinfo').fadeIn();
		
		allboxes.filter('.action-recipe, .action-recipe_uninstall').attr( 'disabled', 'disabled' );
		
		next_step();
		return false;
	};
	
	function credentials_dialog( callback ){
		var $modal = $( '#request-filesystem-credentials-dialog' );
		
		$( 'body' ).addClass( 'modal-open' );
		$modal.show();
		
		$modal.find( 'input:enabled:first' ).focus();
		
		$modal.find('.cancel-button').click( function(){
			$modal.hide();
			$( 'body' ).removeClass( 'modal-open' );
		} );
		
		$modal.find('form').submit( function(e){
			e.preventDefault();
			var data = 'action=wpchef_fs_credentials&' + $(this).serialize();
			
			$('#upgrade', this).addClass('disabled');
			
			$.post( ajaxurl, data )
			.done( function( data ) {
				if ( data && data.success )
				{
					credentials = data.data;
					$modal.hide();
					$( 'body' ).removeClass( 'modal-open' );
					
					callback();
				}
				else
					window.alert( 'Credentials are incorrect. Try again, please.');
			} )
			.error( function(){ window.alert( 'Connection error'); } )
			.always( function(){
				$('#upgrade').removeClass('disabled');
			} )
		} );
	}
	
	
	btn_apply.click( function(){
		var $modal = $( '#request-filesystem-credentials-dialog' );
		
		var should_credentials = $modal.size() > 0 && allboxes.filter('.should_credentials:checked').size() > 0;
		
		if ( should_credentials )
			credentials_dialog( apply );
		else
			apply();
		
	} );
	
	var errors = false;
	var success = false;
	var upgrade = wpchef.apply.upgrade;
	var checked_actions = [];
	var token = null;
	var success_url = null;
	
	var fail_items = [];
	var alerts = [];
	
	function next_step()
	{
		var actions = [];
		var items = [];
		var batch = false;
		var should_credentials = false;
		var first = null;
		
		while ( true )
		{
			var check = allboxes.filter(':checked:not(:disabled)').first();
			if ( check.size() == 0 )
				break;
			
			if ( check.data('batch') )
				batch = true;
			else if ( batch )
				break;
			
			var name = check.attr('name');
			if ( name )
				allboxes.filter( function (){ return this.name == name } ).attr( 'disabled', 'disabled' );
			
			//check.attr( 'disabled', 'disabled' );
			check.hide().siblings('label').hide();
			check.after('<i class="fa fa-refresh fa-spin">');
			
			var action = {
				step: name,
				uninstall: check.data('uninstall'),
				child: check.data('recipe'),
				params: {}
			};
			
			var helper = check.closest('.ingredient').find('.ingredient-title').text();
			if ( helper.length == 0 )
				helper = check.closest('.ingredient').find('.ingredient-description').text();
			
			$('.apply-state', form).text( helper + '...' );
			
			var item = check.closest('.ingredient');
			if ( action.step == 'complete' )
			{
				action.params.autoupdate = $('[name=recipe_autoupdate]:checked', form).val();
				action.params.checked_actions = checked_actions.join();
			}
			else
			{
				$('.action-param', item).each( function()
				{
					var name = $(this).data('name');
					if ( typeof name != typeof '' || !name.length )
						return;
					
					var type = 'type' in this ? this.type : '';
					if ( type == 'checkbox' || type == 'radio' )
						if ( !this.checked )
							return;
					
					action.params[name] = $(this).val();
				} )
			}
			
			if ( check.hasClass('should_credentials') )
				should_credentials = true;
			
			actions.push( action );
			checked_actions.push( action.step );
			items.push( item );
			
			if ( !batch )
				break;
		}
		
		if ( actions.length == 0 )
			return finish();

		if ( items[0].size() > 0 )
		{
			var scroll = list.scrollTop() + items[0].position().top - 15;
			if ( scroll < 0 )
				scroll = 0;
			list.stop().animate({scrollTop:scroll});
		}

		var post_data = {
			action: 'recipe_steps',
			mode: wpchef.apply.uninstall ? 'uninstall' : 'install',
			recipe: wpchef.apply.recipe,
			sec: wpchef.apply.apply_nonce,
			steps: actions
		};
		
		if ( token )
			post_data.token = token;
		
		if ( should_credentials )
			post_data.credentials = credentials;
		
		$.post( ajaxurl, post_data )
		.done(function(data){
			if ( !data || typeof data == 'string' )
			{
				steps_fail( data );
				return;
			}
			
			for ( var i in items )
			{
				var itemdata = data[i] ? data[i] : [];
				
				if ( 'alerts' in itemdata )
					for ( var ii in itemdata.alerts )
						alerts.push( itemdata.alerts[ ii ] );
				/*
				if ( 'recipe_finished' in itemdata && itemdata.recipe_finished )
				{
					is_finish = true;
					$('input[name=complete]', form).attr('checked', 'checked').removeAttr('disabled');
				}*/
				
				if ( itemdata.success )
				{
					//log.append( '<p><strong>Success</strong></p>' );
					$('.check-column .fa', items[i]).attr('class', 'fa fa-check text-success');
					success = true;
					if ( 'token' in itemdata )
						token = itemdata.token;
					if ( 'success_url' in itemdata )
						success_url = itemdata.success_url;
					
					continue;
				}
				
				mark_items_fail( [ items[i] ] );
				errors = true;

				var logrow = $('<p>');

				var itemtitle = item.find('.ingredient-title').text();
				logrow.append( $('<strong>').text( itemtitle + ': ' ) );

				var error_msg = itemdata.error ? itemdata.error : 'Invalid action';
				logrow.append( error_msg );

				if ( itemdata.data )
					logrow.append( itemdata.data );

				log.append( logrow );
			}
		})
		.fail( steps_fail )
		.always( next_step );
		
		function steps_fail( error_msg )
		{
			errors = true;
			mark_items_fail( items );

			var listitems = [];
			for ( var i in items )
				listitems.push( item.find('.ingredient-title').text() );

			listitems = $('<strong>').text( listitems.join(', ') + ':' );

			if ( !error_msg )
				error_msg = 'Connection error';

			log.append(
				$('<p>')
				.append( listitems )
				.append( error_msg )
			);
		}

		function mark_items_fail( items )
		{
			for ( var i in items ) {
				fail_items.push( items[i] );

				$('.check-column .fa', items[i]).attr('class', 'fa fa-close text-error');
				//$('.progress', items[i]).text('failed.').addClass('error');
			}
		}
	}
	
	var is_finish = false;
	
	function finish()
	{
		if ( !is_finish && (success || !errors || upgrade) )
		{
			is_finish = true;
			$('input[name=complete]', form).attr('checked', 'checked').removeAttr('disabled');
			next_step();
			$('.apply-state', form).text('Finishing...');
			return false;
		}
		
		$('.apply-state', form).text('');
		
		var div = $('<div class="recipe-activation-results">');

		var log_button = null;

		if ( is_finish )
		{
			var btn_continue = '<p><a class="button button-wpchef-lg" href="javascript:window.location.assign(wpchef.apply.success_url);">Continue</a></p>'
			if ( wpchef.apply.uninstall )
				div.append('<p>Recipe deactivated successfully.</p>' + btn_continue);
			else
				div.append('<h2 class="text-success">Congratulation!</h2><p>Recipe activated successfully.</p>' + btn_continue);
			
			if ( errors )
			{
				log_button = $('<a href="#">Some ingredients were omitted during activation.</a>');

				div.append( $('<div class="partial-details">').append( log_button ) )
			}
		}
		else
		{
			div
				.addClass('activation-failed')
				.append('<h2 class="text-error">' + (wpchef.apply.uninstall ? 'Deactivation' : 'Activation') + ' failed.</h2>' );

				log_button =
					$('<a href="#" class="recipe-log-button">Show error log</a>')
						.appendTo( div )
		}

		if ( log_button )
		{
			log_button
				.click( function(){
					log
						.hide()
						.appendTo( div );

					log_button.fadeOut( function(){
						log.show();
					} );

					return false;
				} );

		}
		
		if ( !success_url )
			success_url = wpchef.apply.url_list;
		wpchef.apply.success_url = success_url;
		
		$('.recipe-special').fadeOut( function() {
			//$('.recipe-special').html('').append( $('<a style="float:right" class="button" href="javascript:window.location.assign(wpchef.apply.success_url);">').text( 'Continue' ) ).fadeIn();
		});
		
		for ( var i in alerts )
			div.append( $('<div class="notice notice-'+alerts[i][0] + '"><p></p></div>').children().html( alerts[i][1] ).end() );
		
		//btn_apply.closest('.recipe-apply').fadeOut();
		list.fadeOut( function(){ list.html('').append( div ).fadeIn() } );
		
		if ( wpchef.activate_callback )
		{
			wpchef.activate_callback( success );
			wpchef.activate_callback = null;
		}
		//btn_apply.siblings('.loadinfo').fadeOut();
	}
	
	$('.ingredient-spoiler-trigger').click(function(){
		$(this)
		.blur()
		.find('.fa')
		.toggleClass('fa-chevron-down fa-chevron-up')
		.closest('.ingredient')
		.find('.ingredient-spoiler')
		.stop()
		.slideToggle();
		
		return false;
	});
	
	allboxes.filter('.action-recipe').change( function(){
		var row = $( this ).closest('.ingredient');
		var level = row.data('level');
		
		while ( row.next().size() > 0 )
		{
			row = row.next();
			
			if ( row.hasClass('description') )
				continue;
			
			if ( row.data('level') <= level )
				break;
			
			var box = row.find( allboxes );
			
			if ( !this.checked )
				box.removeAttr( 'checked' );
			
			else if ( box.data('default-checked') )
				box.attr('checked', 'checked');
		}
		
		return true;
	});
	
	function find_parent( item )
	{
		var level = item.data('level')*1;
		if ( level < 1 )
			return null;
		
		var parent = item.prev();
		while ( parent.data('level')*1 >= level )
			parent = parent.prev();
		
		return parent;
	}
	
	function check_all_parents( item )
	{
		while ( item = find_parent( item ) )
			item.find('.recipe-actionbox').attr( 'checked', 'checked' );
	}
	
	function check_parent_free( item )
	{
		item = find_parent( item );
		if ( !item )
			return false;
		
		var level = item.data('level')*1;
		
		var child = item.next();
		while( child.data('level')*1 > level )
		{
			var checkbox = child.find('.recipe-actionbox');
			if ( !checkbox.is(':disabled') && checkbox.is(':checked' ) )
			{
				check_all_parents( child );
				return;
			}
			child = child.next();
		}
		
		item.find('.recipe-actionbox').removeAttr( 'checked' );
		check_parent_free( item );
	}
	
	allboxes.not(':disabled').click( function(){
		var item = $(this).closest('.ingredient');
		
		if ( this.checked )
			check_all_parents( item );
		
		else
			check_parent_free( item );
	} );
	
	$('.inline-buy-child').click( function( e ){
		var btn = $(this)
		
		while ( btn.next().size() > 0 )
			btn.next().remove();
		
		btn.css( { opacity: '0.5' } );
		btn.after( ' <i class="fa fa-refresh fa-spin">' );
		
		var customs = {};
		allboxes.each( function() {
			var box = $(this);
			if ( !box.data('enabled') )
				return;
			
			if ( box.data('default-checked') != this.checked )
				customs[ this.name ] = Boolean(this.checked);
		} );
		
		$.post( ajaxurl, {
			action: 'wpchef_inline_buy_child',
			child: btn.data('wpchef_id'),
			recipe: wpchef.apply.recipe,
			customs: JSON.stringify( customs ),
			url: window.location.toString()
		} )
		.done( function( data ) {
			$('<div style="display:none">').html( data ).appendTo( 'body' );
		} )
		.fail( function() {
			window.location.assign( btn.attr('href') );
		} );
		
		return false;
	} );
	
	if ( wpchef.apply.customs ) {
		allboxes.not(':disabled').each( function()
		{
			if ( typeof wpchef.apply.customs[ this.name ] == 'boolean' )
				this.checked = wpchef.apply.customs[ this.name ];
		} );
	}
	
	wpchef.tooltip( $('.wpchef-hint', form) );
	
	var check_xhrs = {
		login: null,
		email: null
	};
	var check_user_cache = {
		login: {},
		email: {}
	};
	$('#recipe_install .ingredient').find('.action-param[data-name=login],.action-param[data-name=email]').bind('change propertychange paste keyup', function(){
		var input = $(this);
		var type = input.data('name');
		var value = input.val();
		
		function check_user_done( exists )
		{
			var notice = input.next('.description');
			if ( exists )
			{
				if ( notice.size() == 0 )
					notice = $('<p class="description"><span class="text-error">Already exists. User will be not created.</span></p>').insertAfter( input );
				
				notice.stop().fadeIn();
			}
			else
				notice.stop().fadeOut();
		}
		
		if ( value.length < 1 )
		{
			check_user_done( false );
			return true;
		}
		
		if ( value in check_user_cache[type] )
		{
			check_user_done( check_user_cache[type][value] );
			return true;
		}
		
		if ( check_xhrs[type] !== null )
		{
			if ( check_xhrs[type].check_user_value == value )
				return true;
			
			check_xhrs[type].abort();
		}
		
		check_xhrs[type] = $.post( ajaxurl, {
			action: 'wpchef_check_user',
			check_by: type,
			sec: wpchef.apply.check_user_nonce,
			value: value,
		} )
		.done( function(data){
			if ( !('success' in data) || !data.success )
				return;
			
			check_user_cache[type][value] = data.data;
			check_user_done( data.data );
		} )
		.always( function() {
			check_xhrs[type] = null;
		} );
		
		check_xhrs[type].check_user_value = value;
		
		return true;
	}).change();
} );