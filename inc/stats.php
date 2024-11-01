<?php

class wpchef_stats extends wpchef_base
{
	protected static $instance;
	protected function __construct()
	{
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public function admin_init()
	{
		add_action('wpchef_settings_bottom', array( $this, 'settings_page' ) );
		add_action('wp_ajax_wpchef_get_stats', array( $this, 'get_stats' ) );
		add_action('wp_ajax_nopriv_wpchef_get_stats', array( $this, 'get_stats' ) );
	}

	public function settings_page()
	{
		$me = wpchef::instance()->wpchef_me();

		if ( !$me )
			return;

		$this->manage_site_on_connect();

		if ( !empty($_POST['_wpnonce']) && wp_verify_nonce( @$_POST['_wpnonce'], 'wpchef_settings' ) )
		{
			$users = $this->get_option('discard_manage');
			if ( !$this->me_in_list( $_POST['wpchef_statsusers'] ) )
			{
				if ( !in_array( (int)$me['ID'], $users, true ) )
					$users[] = (int)$me['ID'];
			}
			else
			{
				$i = array_search( (int)$me['ID'], $users, true );
				if ( $i !== false )
					unset( $users[ $i ] );
			}
			$users = array_filter( $users );
			sort( $users );
			$this->set_option( 'discard_manage', $users );
			$this->set_option( 'statsusers', $_POST['wpchef_statsusers'] );

			?>
			<div class="updated">
				<p><?php
					esc_html_e('Settings updated.');
					if ( !$this->send_stats() )
					{
						echo ' ';
						esc_html_e('But statistics was not sent to wpchef.org. Please, try again later by resave settings.');
					}
				?></p>
			</div>
			<?php
		}
		?>
		<div class="postbox">
			<h3 class="hndle">Site management</h3>
			<div class="inside">
				<form method="post">
					<?php wp_nonce_field( 'wpchef_settings' ); ?>
					<table class="form-table">
						<tr>
							<th>
								<?php _e('Allow site management by users', 'wpchef') ?>
								<p class="description"><?php _e('Optional') ?></p>
							<td>
								<input type="text" name="wpchef_statsusers" value="<?=esc_attr($this->get_option('statsusers'))?>" size="40">
								<p class="description"><?php _e('Enter comma separated WPChef.org user logins or IDs that will be able to manage this site from their WPChef accounts.')?></p>
						<tr>
							<th>
							<td>
								<button type="submit" class="button">Save</button>
					</table>
				</form>
			</div>
		</div>
		<?php
	}

	protected function send_stats()
	{
		$this->manage_site_on_connect();

		$token = wp_generate_password( 64 );
		add_option( 'wpchef_stats_token', $token, '', 'no' );
		update_option( 'wpchef_stats_token', $token );

		return wpchef::instance()->oauth_request( 'stats', array(
			'token' => $token,
			'users' => get_option('wpchef_statsusers'),
			'nomyself' => 1,
		) );
	}

	public function get_stats()
	{
		header( 'Access-Control-Allow-Origin: *' );

		$token = get_option( 'wpchef_stats_token' );
		if ( !$token || $token != @$_REQUEST['token'] )
			$this->json_error('Access denied');

		require_once ABSPATH . 'wp-admin/includes/update.php';

		$core_update = false;
		if ( $core_updates = get_core_updates() )
			foreach( $core_updates as $update )
				if ( !empty( $update->response ) && $update->response == 'upgrade' )
					$core_update = $update->version;

		$stats = array(
			'recipes' => array(),
			'plugins' => array(),
			'themes' => array(),
			'core' => array(
				'version' => $GLOBALS['wp_version'],
				'update' => $core_update,
			),
		);

		foreach ( wpchef::instance()->recipes_list() as $slug => $recipe )
		{
			$stats['recipes'][ $slug ] = array(
				'name' => $recipe['name'],
				'version' => is_bool($recipe['installed']) ? $recipe['version'] : $recipe['installed'],
				'update' => $recipe['upgrade'],
				'active' => $recipe['installed'],
				'child_update' => false,
			);
		}

		// slug => wpchef_id
		foreach ( wpchef::instance()->get_plugins() as $slug => $plugin )
		{
			if ( ($recipe = wpchef::instance()->package_recipe( 'plugin', $slug )) && isset($stats['recipes'][ $recipe ]) )
			{
				if ( $plugin['update'] )
					$stats['recipes'][ $recipe ]['child_update'] = true;
			}
			else
				$stats['plugins'][ $slug ] = array(
					'name' => $plugin['name'],
					'slug' => $plugin['slug'],
					'version' => $plugin['version'],
					'update' => $plugin['update'],
					'active' => $plugin['is_active'],
				);
		}

		foreach ( wpchef::instance()->get_themes() as $slug => $theme )
		{
			if ( ($recipe = wpchef::instance()->package_recipe( 'theme', $slug )) && isset($stats['recipes'][ $recipe ]) )
			{
				if ( $theme['update'] )
					$stats['recipes'][ $recipe ]['child_update'] = true;
			}
			else
				$stats['themes'][ $slug ] = array(
					'name' => $theme['name'],
					'version' => $theme['version'],
					'update' => $theme['update'],
					'active' => $theme['is_active'],
				);
		}

		$this->json_success( $stats );
	}

	protected function manage_site_on_connect()
	{
		$me = wpchef::instance()->wpchef_me();
		if ( !$me )
			return;

		$canceled_users = $this->get_option( 'discard_manage' );
		if ( !is_array( $canceled_users ) )
			$this->set_option( 'discard_manage', array() );

		elseif ( in_array( (int)$me['ID'], $canceled_users, true ) )
			return;

		$users = get_option('wpchef_statsusers', '');
		if ( !$this->me_in_list( $users, true ) )
			$this->set_option( 'statsusers', $users );
	}

	private function me_in_list( &$list, $add_if_not = false )
	{
		$me = wpchef::instance()->wpchef_me();
		if ( !$me || !$me['ID'] )
			return false;

		$users = explode(',', (string)$list);
		$users = array_map('trim', $users );
		if ( in_array( (string)$me['ID'], $users, true) || in_array( $me['login'], $users, true) )
			return true;

		if ( $add_if_not )
		{
			$users[] = $me['login'];
			$users = array_filter( $users );
			$list = implode( ',', $users );
		}
		return false;
	}
}
