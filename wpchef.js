'use strict'

jQuery( function($){
	wpchef.connect_callback = null;
	
	wpchef.connect = function( callback, cancel )
	{
		var url =  wpchef.admin_url + 'admin.php?page=recipe-settings&noheader&popup';
		var left = screen.availWidth/2-250;
	    var top = screen.availHeight/2-250;
		
		var win = window.open( url, 'wpchef_connect', 'menubar=no,location=no,resizable=yes,scrollbars=yes,width=500,height=500,left='+left+',top='+top );
		
		if ( !win )
		{
			alert( 'Can\'t open popup' );
			return;
		}
		
		if ( typeof callback == 'function' )
			wpchef.connect_callback = callback;
		else
			setTimeout( function(){
				location.reload( true );
			}, 200 );
			
		if ( typeof cancel == 'function' )
		{
			var timeout = null;
			var checkCancel = function(){
				if ( wpchef.connect_callback === null )
					return;
				
				if ( win && !win.closed )
				{
					timeout = setTimeout( checkCancel, 200 );
					return;
				}
				
				wpchef.connect_callback = null;
				cancel();
			}
			
			var timeout = setTimeout( checkCancel, 1000 );
		}
		
		return win;
	}
	
	wpchef.auth_only_click = function(e){
		var button = $(this);
		
		if ( wpchef.me && !button.hasClass('wpchef_reauth') )
		{
			button.off('click', wpchef.auth_only_click );
			return true;
		}
		
		wpchef.loadinfo( button );
		
		if ( e.stopPropagation )
			e.stopPropagation();
		
		if ( e.stopImmediatePropagation )
			e.stopImmediatePropagation();
		
		wpchef.connect( function()
		{
			button.off('click', wpchef.auth_only_click );
			$('.wpchef-me-warning').fadeOut();
			wpchef.loadinfo_stop( button );
			
			if ( button[0].click )
				button[0].click();
			else
				button.click();
		}, function(){
			wpchef.loadinfo_stop( button );
		} );
		
		return false;
	}
	
	$('.wpchef_auth_only').on('click', wpchef.auth_only_click );
	
	wpchef.tooltip = function( set ) {
		set.tooltip({
			content: function(){
				var title = $( this ).attr( "title" ) || "";
				title = $( "<a>" ).text( title ).html();
				return title.split("\n").join('<br>');
			}
		});
	}
	
	wpchef.tooltip( $('.wpchef-hint') );
	
	wpchef.activate_modal = function( args )
	{
		var settings = {
			name: null,
			slug: null,
			callback: null,
			upload: null,
			deactivate: false,
			customs: null
		};
		$.extend( settings, args );
		
		if ( !settings.name || !settings.slug )
			return;
		
		var url = ajaxurl + '?action=wpchef_activate&recipe=' + encodeURIComponent( settings.slug );
		
		if ( settings.deactivate )
			url += '&deactivate';
		
		if ( settings.upload )
			url += '&upload_sec=' + encodeURIComponent( settings.upload );
		
		if ( settings.customs )
			url += '&customs=' + encodeURIComponent( settings.customs );
		
		var txt = settings.deactivate ? 'Deactivate Recipe' : 'Activate Recipe';
		
		var name = $('<span>').text( settings.name ).html();
		name = '<span style="font-size:1.2em;font-weight:normal">' + txt + ': <strong>' + name + '</strong></span>';
		tb_show( name, url );
		wpchef.activate_callback = settings.callback;
	}
	
	wpchef.loadinfo = function( button )
	{
		button.addClass('wpchef-loadinfo').wrapInner('<div class="loadinfo-content">');
		button.append('<div class="loadinfo-spinner"><i class="fa fa-refresh fa-spin"></i></div>');

		$(window).on('message', function(e) {
			console.log( e );
			if ( 'data' in e.originalEvent && e.originalEvent.data == 'loaded' )
				wpchef.loadinfo_stop( button );
		});
	}
	
	wpchef.loadinfo_stop = function( button )
	{
		if ( !button.hasClass('wpchef-loadinfo') )
			return;
		
		button.removeClass('wpchef-loadinfo').children('.loadinfo-content').contents().unwrap();
		button.children('.loadinfo-content, .loadinfo-spinner').remove();
	}

	wpchef.scroll_to = function( item, offset, duration )
	{
		if ( typeof offset != typeof 50 )
			offset = 50;

		if ( typeof duration != typeof 1000 )
			duration = 1000;

		$('html, body').animate({
			scrollTop: item.offset().top - offset
		}, duration );
	}
} );