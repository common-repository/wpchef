<div class="wrap" id="poststuff">
	<div class="postbox">
		<h3 class="hndle"><?php printf( __('Connection to your <a href="%s" target="_blank" style="font-size: inherit;font-weight: bold;text-decoration: none;">WPChef.org</a> account', 'wpchef'), $this->server ) ?></h3>
		<div class="inside">
		<?php if ( !empty($_GET['confirm']) && wp_verify_nonce( $_GET['confirm'], 'oauth_access_token' ) ): ?>
			<p class="save_token_progress">Saving access token...<span></span></p>
			<script>
				jQuery( function($){
					//return true;
					var baseurl = <?= json_encode( empty($_GET['for']) ? admin_url('admin.php?page=recipe-settings') : $this->url('edit', $_GET['for'] ).'&noheader&sec='.wp_create_nonce('savecloud_'.$_GET['for']) )?>
					
					function token_fail()
					{
						$('.save_token_progress span').text( 'fail' );
						window.location.assign( baseurl )
					}
					
					var hash = window.location.hash.split('=', 2 );
					if ( hash.length < 2 )
						token_fail();
					
					token = decodeURIComponent(hash[1]);
					$.post( ajaxurl, {
						action: 'wpchef_save_token',
						token: token,
						confirm: <?=json_encode($_GET['confirm'])?>
					} )
					.done( function(data){
						if ( data && data.success )
						{
							$('.save_token_progress span').text( 'success' );
							window.location.assign( baseurl );
						}
						else
							token_fail();
					} )
					.fail( token_fail );
				} );
			</script>
		<?php elseif ( $me ): ?>
			<p>
				<i class="fa fa-check-circle-o fa-lg text-success"></i>
				<?php printf(
					esc_html__('This site is connected to WPChef.org as user %s in %s mode.', 'wpchef'),
					sprintf('<a href="%s" target="_blank">%s</a>', esc_attr($me['profile_url']), esc_html($me['display_name'])),
					sprintf('<a href="%s" class="wpchef_auth_only wpchef_reauth">%s</a>', esc_attr(admin_url('admin.php?page=recipe-settings')), empty($me['admin_access']) ? esc_html__('read-only', 'wpchef') : esc_html__('full access', 'wpchef') )
				); ?>
			</p>
			<p>
				<a class="button wpchef-disconnect" href="#">Disconnect</a>
			</p>
			<script>
				jQuery( function($){
					$('.wpchef-disconnect').click( function(){
						$(this)
							.attr('disabled', 'disabled')
							.text( 'Disconnecting...');
						
						var baseurl = <?=json_encode( admin_url('admin.php?page=recipe-settings') )?>
						
						$.post( ajaxurl, {
							action: 'wpchef_clean_token',
							confirm: <?=json_encode(wp_create_nonce('oauth_access_token'))?>
						} )
						.always( function(){
							window.location.assign( baseurl );
						} );
						
						return false;
					});
				} );
			</script>
		<?php else: ?>
			<p><?php printf(
				__('Connect to %s to take advantage of all features.', 'wpchef'),
				sprintf('<a href="%s" target="_blank">%s</a>', esc_attr($this->server), __('WPChef','wpchef') )
			) ?></p>
			<a class="button wpchef_auth_only" href="<?=esc_attr(admin_url('admin.php?page=recipe-settings'))?>">Connect Now</a>
		<?php endif ?>
		</div>
	</div>
	<?php do_action( 'wpchef_settings_bottom' ) ?>
</div>