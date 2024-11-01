<?php

/*
  Plugin Name: WPChef
  Description: Quickly set up a preconfigured WordPress site or expand an existing one using a recipe which is a set of plugins, options, themes and content pieces.
  Author: WPChef
  Author URI: https://wpchef.org
  Version: 2.1.2
  Text Domain: wpchef
 */


require_once 'inc/base.php';

require_once 'inc/recipe.php';
require_once 'inc/editor.php';
require_once 'inc/stats.php';

class wpchef extends wpchef_base
{
	//public $debug = true;
	//public $cache = false;
	public $offline = false;

	protected $features = array();
	public $credentials = null;
	protected static $instance;

	protected function __construct()
	{
		parent::__construct();

		$this->update_timeout = 6*HOUR_IN_SECONDS;
		$this->autoupdate_check_interval = 12*HOUR_IN_SECONDS;
		$this->autoupdate_step_interval = 5*MINUTE_IN_SECONDS;

		//ini_set( 'display_errors', true );
		//error_reporting( E_ALL );
		$this->autoupdate = false;
		//update_option( 'wpchef_server', 'http://localhost/wordpress/' );
		//delete_option( 'wpchef_server' );
		$this->server = get_option( 'wpchef_server', 'https://wpchef.org/' );
		$this->servername = get_option( 'wpchef_server_name', __('WPChef.org', 'wpchef') );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );

		$this->recipe = wpchef_recipe::instance();
		$this->ingredient = wpchef_editor::instance();

		$this->ingredient->chef = $this;
		$this->credentials = false;

		wpchef_stats::instance();
	}

	function url( $action = null, $slug = '', $html_escape = false )
	{
		$url = admin_url('admin.php');

		if ( $action == 'edit' )
			$action = 'editor';

		$data = array(
			'page' => $action ? 'recipe-'.$action : 'recipes',
		);

		if ( $slug )
			$data['recipe'] = urlencode( $slug );

		$url .= '?'.http_build_query( $data, $html_escape ? '&amp;' : '&' );

		return $url;
	}

	public function activate()
	{
		add_option( 'wpchef_actions_made', array(), '', 'no' );
		add_option( 'wpchef_installed_recipes', array(), '', 'no' );
		add_option( 'wpchef_recipes', array(), '', 'no' );
		if ( !get_option('wpchef_builtin_installed') )
			$this->install_builtin_recipes();
	}

	public function deactivate()
	{
		$pointers = (string)get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true );
		$pointers = explode( ',', $pointers );
		if ( in_array( 'wpchef_welcome_alert', $pointers ) )
		{
			unset( $pointers[ array_search( 'wpchef_welcome_alert', $pointers, true ) ] );
			update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', implode(',', $pointers ) );
		}

		delete_option( 'wpchef_builtin_installed');
	}

	function init()
	{
		//global $wpdb;
		//$wpdb->query(' update ignore `wp_options` set option_name = REPLACE( option_name, "wp_chef", "wpchef" ) where option_name LIKE "wp_chef%" ');

		$this->url_list = admin_url('admin.php?page=recipes');
		$this->url_add = admin_url('admin.php?page=recipe-install');

		require_once 'inc/recipe.php';
		require_once 'inc/ingredient.php';

		if ( !$this->offline )
		{
			add_filter( 'cron_schedules', array( $this, 'cron_shedules' ) );
			add_action( 'wpchef_autoupdate_step', array( $this, 'autoupdate_step') );
			add_action( 'wpchef_updates_cron', array( $this, 'updates_cron') );

			$check = wp_next_scheduled('wpchef_updates_cron');
			if ( !$check || $check-time() > $this->autoupdate_check_interval*1.2 )
			{
				wp_clear_scheduled_hook( 'wpchef_updates_cron' );
				wp_schedule_event( time()+$this->autoupdate_check_interval, 'wpchef_updates_check', 'wpchef_updates_cron' );
			}

			add_action( 'wp_ajax_wpchef_check_client', array( $this, 'wpchef_check_client' ) );
			add_action( 'wp_ajax_nopriv_wpchef_check_client', array( $this, 'wpchef_check_client' ) );
		}

		add_filter('wpchef_the_recipe', array( $this, 'the_recipe_filter' ), 10, 2 );

		add_action( 'wp_ajax_nopriv_recipe_steps', array($this, 'recipe_steps') );

		if ( !current_user_can('install_plugins') )
			return;

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function admin_init()
	{
		add_action( 'wp_ajax_recipe_steps', array($this, 'recipe_steps') );
		add_action( 'admin_enqueue_scripts', array($this, 'eneque_scripts') );
		add_action( 'admin_print_scripts', array( $this, 'admin_print_scripts') );
		add_action( 'wp_ajax_wpchef_snapshot', array( $this, 'make_snapshot' ) );
		add_action( 'wp_ajax_wpchef_recent_options', array( $this, 'recent_options' ) );
		add_action( 'wp_ajax_wpchef_fs_credentials', array( $this, 'ajax_fs_credentials' ) );
		add_action( 'wp_ajax_wpchef_recipe_delete', array( $this, 'ajax_remove' ) );
		add_action( 'wp_ajax_wpchef_activate', array( $this, 'page_apply' ) );

		if ( $this->offline )
			return;

		add_action( 'wp_ajax_wpchef_clean_token', array($this, 'clean_token') );
		add_action( 'wp_ajax_wpchef_autoupdate', array( $this, 'autoupdate_ajax' ) );
		add_action( 'wp_ajax_wpchef_refresh_recipes', array( $this, 'ajax_refresh_recipes' ) );
		add_filter( 'wpchef_search_recipes', array( $this, 'search_recipes' ), 10, 2 );
		add_action( 'wp_ajax_wpchef_recipe_install', array( $this, 'ajax_recipe_install' ) );
		add_action( 'wp_ajax_wpchef_inline_buy_child', array( $this, 'ajax_buy_child' ) );

		if ( get_user_meta( get_current_user_id(), 'wpchef_auth_now' ) )
			add_action( 'admin_notices', array( $this, 'auth_now_notice' ) );
		//wpchef_inline_buy_child
	}

	function admin_menu()
	{
		$recipes = $this->recipes_list();
		$cnt = 0; $cnt_fail = 0;
		foreach ( $recipes as $recipe )
		{
			if ( $recipe['upgrade'] )
			{
				if ( $recipe['autoupdate_fail'] )
					$cnt_fail++;

				else
					$cnt++;
			}
		}

		$menu_title = __('Recipes', 'wpchef');
		if ( $cnt )
			$menu_title .= sprintf(' <span class="update-plugins count-%1$d"><span class="plugin-count">%1$d</span></span>', $cnt );
		if ( $cnt_fail )
			$menu_title .= sprintf(' <span class="update-plugins errors count-%1$d"><span class="plugin-count">%1$d</span></span>', $cnt_fail );
			//$menu_title .= sprintf('<span class="fa-stack"><i class="fa fa-circle fa-stack-2x text-danger"></i><b class="fa-stack-1x">%d</b></span>', $cnt_fail );

		add_menu_page( __('WPChef Recipes', 'wpchef'), $menu_title, 'install_plugins', 'recipes', array($this, 'page_list'),  plugin_dir_url( __FILE__ ).'/wpchef.png', '64.wpchef' );

		$page = add_submenu_page( 'recipes', __('WPChef Recipes', 'wpchef'), __('Installed Recipes', 'wpchef'), 'install_plugins', 'recipes', array($this, 'page_list') );
		add_action('admin_print_styles-' . $page, array( $this, 'page_list_styles' ) );

		add_submenu_page( 'recipes', 'Add Recipes', 'Add New', 'install_plugins', 'recipe-install', array( $this, 'page_add') );

		add_submenu_page( 'recipes', __('Edit Recipe', 'wpchef'),  null, 'install_plugins', 'recipe-editor', array( $this, 'page_edit') );
		add_submenu_page( 'recipes', __('Create Recipe', 'wpchef'),  null, 'install_plugins', 'recipe-create', array( $this, 'page_edit') );

		if ( $this->offline )
			return;

		add_submenu_page( 'recipes', 'WP Chef Settings', 'Settings', 'install_plugins', 'recipe-settings', array( $this, 'page_settings') );
		// add_submenu_page( 'recipes',  __('Activate Recipe', 'wpchef'),  null, 'install_plugins', 'recipe-activate', array( $this, 'page_apply') );
		// add_submenu_page( 'recipes',  __('Deactivate Recipe', 'wpchef'),  null, 'install_plugins', 'recipe-deactivate', array( $this, 'page_apply') );
	}

	function admin_print_scripts()
	{
		$data = array(
			'admin_url' => admin_url(),
			'me' => ( $me = $this->wpchef_me() ) ? $me : false,
		);
		?><script>window.wpchef = <?=json_encode($data)?>;</script><?php
	}

	function eneque_scripts()
	{
		//wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-core', false, array('jquery') );
		wp_enqueue_script('jquery-ui-tooltip', false, array('jquery-ui-core') );
		wp_enqueue_script('jquery-ui-sortable', false, array('jquery-ui-core') );
		wp_enqueue_script('jquery-ui-tabs', false, array('jquery-ui-core') );

		wp_enqueue_style( 'jquery-ui-style', plugin_dir_url( __FILE__ ).'assets/jquery-ui-smoothness/jquery-ui.min.css', array(), '1.11.4' );
		wp_enqueue_style( 'font-awesome', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' );

		//wp_enqueue_style('jquery-ui-dialog', site_url('/wp-includes/css/jquery-ui-dialog.min.css') );
		$dir = dirname( __FILE__ );
		wp_enqueue_style( 'wpchef', plugin_dir_url( __FILE__ ) . 'wpchef.css', array(), filemtime("$dir/wpchef.css") );
		wp_enqueue_script( 'wpchef', plugin_dir_url( __FILE__ ) . 'wpchef.js', array(), filemtime("$dir/wpchef.js") );

		// Requires WP 3.3
		if ( version_compare( get_bloginfo( 'version' ), '3.3', '>=') )
		{
			$pointers = (string)get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true );
			$pointers = explode( ',', $pointers );
			if ( !in_array( 'wpchef_welcome_alert', $pointers ) )
			{
				wp_enqueue_style( 'wp-pointer' );
				wp_enqueue_script( 'wp-pointer' );

				add_action( 'admin_print_footer_scripts', array( $this, 'wpchef_welcome_alert' ) );
			}
		}
	}

	function wpchef_welcome_alert()
	{
		?>
		<script>
		jQuery( function($) {
			$('#toplevel_page_recipes').pointer({
				content: '\
					<h3>Welcome to WPChef!</h3>\
					<p>\
						- Instantly supercharge your site<br>\
						- Build, Deploy, and Manage your WordPress sites<br>\
						- Develop and work on multiple sites easily\
					</p>\
					',
				position: {
					edge: 'left',
					align: 'center'
				},
				pointerWidth: 350,
				close: function() {
					$.post( ajaxurl, {
						pointer: 'wpchef_welcome_alert', // pointer ID
						action: 'dismiss-wp-pointer'
					} );
				}
			}).pointer('open');
		});
		</script>
		<?php
	}

	function fetch_recipe( $id, $cache = true )
	{
		if ( !$id || $this->offline )
			return false;

		$cache_id = 'fetch_recipe_'.$id;

		if ( !$cache )
			$this->cache_delete( $cache_id );

		elseif ( ( $recipe = $this->cache_get($cache_id) ) !== false )
		{
			$recipe['_is_from_cache'] = true;
			return $recipe;
		}
		else
			$this->log( 'not in cache', $id );

		$recipe = $this->oauth_request( 'recipe', array(
			'wpchef_id' => $id,
		), true );

		$this->cache_set( $cache_id, $recipe );

		return $recipe;
	}

	function the_recipe_filter( $recipe, $id )
	{
		if ( $recipe || (int)$id <= 0 )
			return $recipe;

		return $this->fetch_recipe( $id );
	}

	function get_recipe_by_id( $id, $fallback_slug = '', $strict = false )
	{
		if ( $id )
		{
			$recipes = get_option( 'wpchef_recipes', array() );
			$slug = array_search( $id, $recipes, true );
		}

		if ( empty($slug) )
			$slug = $fallback_slug;

		if ( !$slug )
			return false;

		return $this->get_recipe( $slug, $strict );
	}

	function get_recipe( $slug, $strict = false )
	{
		$recipes = get_option( 'wpchef_recipes' );

		if ( isset($recipes[$slug]) )
			$recipe = get_option( "wpchef_recipe_$slug" );

		elseif ( $strict )
			return false;

		else
			$recipe = null;

		if ( is_string($recipe) )
			$recipe = json_decode( $recipe, true );

		$this->recipe_things( $recipe, $slug );
		return $recipe;
	}

	function recipe_things( &$recipe, $slug )
	{
		$recipe = $this->recipe->normalize( $recipe );

		$recipes = get_option( 'wpchef_recipes' );
		if ( isset($recipes[$slug]) )
		{
			$installed = get_option( 'wpchef_installed_recipes' );

			if ( !empty($installed[$slug]) )
			{
				$recipe['installed'] = !empty( $installed[$slug]['version'] ) ? $installed[$slug]['version'] : true;

				$recipe['actions_made'] = (array)$installed[$slug];
				$recipe['actions_canceled'] = (array)@$recipe['actions_made']['canceled'];
				unset( $recipe['actions_made']['version'] );
				unset( $recipe['actions_made']['canceled'] );
			}
			$recipe['uploaded'] = true;
		}

		$things = array(
			'installed' => false,
			'actions_made' => array(),
			'actions_canceled' => array(),
			'upgrade' => false,
			'private' => false,
			'post_author' => 0,
			'is_free' => false,
			'uploaded' => false,
		);
		$recipe += $things;
		$recipe['autoupdate_fail'] = get_option('recipe_autoupdate_alert_'.$slug );

		$recipe['slug'] = $slug;

		$me = $this->wpchef_me();

		if  ( !$recipe['wpchef_id'] || $this->offline )
			$recipe['is_my_own'] = true;
		elseif ( $me )
			$recipe['is_my_own'] = (string)$me['ID'] === (string)$recipe['post_author'];
		else
			$recipe['is_my_own'] = false;

		$recipe['autoupdate'] = false;
		if ( $recipe['wpchef_id'] && $recipe['installed'] )
		{
			$autoupdate = get_option( 'wpchef_autoupdate' );
			if ( !empty($autoupdate[ $slug ]) )
				$recipe['autoupdate'] = ($autoupdate[ $slug ]=='major' ? 'major' : 'minor');
		}

		$recipe['admin_access'] = $recipe['wpchef_id'] && $recipe['is_my_own'] && $me && $me['admin_access'];
		//status hint
		if ( !$recipe['wpchef_id'] ):
			$recipe['status_hint'] = __('Local Recipe', 'wpchef');
			$recipe['status_icon'] = 'fa-circle';

		elseif ( $recipe['admin_access'] && $recipe['private'] ):
			$recipe['status_hint'] = __('Your own private recipe', 'wpchef');
			$recipe['status_icon'] = 'fa-lock text-info';

		elseif ( $recipe['admin_access'] ):
			$recipe['status_hint'] = __('Your own public recipe', 'wpchef');
			$recipe['status_icon'] = 'fa-globe text-info';

		elseif ( $recipe['private'] ):
			$recipe['status_hint'] = __('Private Recipe', 'wpchef');
			$recipe['status_icon'] = 'fa-lock text-success';

		else:
			$recipe['status_hint'] = __('Public Recipe', 'wpchef');
			$recipe['status_icon'] = 'fa-globe text-success';

		endif;

		return $recipe;
	}

	function recipe_save( $recipe, $slug, $raw = false )
	{
		if ( !$raw )
		{
			$clean = $this->recipe->sanitize( $recipe );

			$things = array( 'private', 'is_free', 'post_author' );
			foreach( $things as $thing )
				if ( isset($recipe[ $thing ]) )
					$clean[ $thing ] = $recipe[ $thing ];
		}
		else
			$clean = $recipe;

		update_option( "wpchef_recipe_$slug", $clean );
		$this->log( 'recipe_save', $recipe, $clean, $slug );

		$recipes = get_option( 'wpchef_recipes' );
		$recipes[ $slug ] = $recipe['wpchef_id'] ? $recipe['wpchef_id'] : true;
		update_option( 'wpchef_recipes', $recipes );

		if ( $recipe['wpchef_id'] )
		{
			unset( $recipes[ $slug ] );
			while ( $oldslug = array_search( $recipe['wpchef_id'], $recipes, true ) )
			{
				$this->log( "slug changed from $oldslug to $slug", $recipe['wpchef_id'], $recipes );
				unset( $recipes[ $oldslug ] );
				$this->recipe_merge( $oldslug, $slug );
				$this->recipe_remove( $oldslug, true );
			}
		}
	}

	function recipe_merge( $from, $to )
	{
		$recipes = (array)get_option( 'wpchef_installed_recipes', array() );

		if ( !empty($recipes[ $from ]) && is_array($recipes[ $from ]) )
		{
			$this->log( "merge $from to $to" );

			if ( !empty($recipes[ $to ]) )
				$recipes[ $to ] = array_merge( $recipes[ $from ], (array)$recipes[ $to ] );
			else
				$recipes[ $to ] = $recipes[ $from ];

			if ( !empty($recipes[ $from ]['canceled']) && $recipes[ $from ]['canceled'] != @$recipes[ $to ]['canceled'] )
				$recipes[ $to ]['canceled'] = array_unique( array_merge( $recipes[ $from ]['canceled'], $recipes[ $to ]['canceled'] ) );

			$all_actions = (array)get_option( 'wpchef_actions_made', array() );
			foreach ( $all_actions as $action => $slug )
				if ( $slug == $from )
					$all_actions[ $action ] = $to;

			update_option( 'wpchef_installed_recipes', $recipes );
			update_option( 'wpchef_actions_made', $all_actions );
		}

		return true;
	}

	function recipe_remove( $slug, $force = false )
	{
		$recipe = $this->get_recipe( $slug );
		if ( $recipe['installed'] && !$force )
			return false;

		$this->log( "remove $slug" );

		$this->recipe_free( $recipe );

		$recipes = (array)get_option( 'wpchef_recipes', array() );
		unset( $recipes[$slug] );
		update_option( 'wpchef_recipes', $recipes );

		delete_option( "wpchef_recipe_$slug" );

		return true;
	}

	function recipes_list( $cache = true )
	{
		static $recipes;

		if ( $cache && !empty( $recipes ) )
			return $recipes;

		$recipes = array();

		$list = (array)get_option('wpchef_recipes', array() );

 		foreach ( $list as $slug => $i )
			$recipes[ $slug ] = $this->get_recipe( $slug );

		uasort( $recipes, array( $this, 'recipes_sort') );

		$this->check_for_update( $recipes );

		if ( get_option('wpchef_updates_alert_sent') )
		{
			$updates = false;
			foreach( $recipes as $recipe )
				if ( $recipe['upgrade'] )
				{
					$updates = true;
					break;
				}

			if ( !$updates )
				delete_option( 'wpchef_updates_alert_sent' );
		}

		return $recipes;
	}

	function recipes_sort( $recipe, $recipe2 )
	{
		if ( $recipe['name'] == $recipe2['name'] )
			return 0;
		elseif ( $recipe['name'] > $recipe2['name'] )
			return 1;
		else
		return -1;
	}

	function updates_cron()
	{
		wp_clear_scheduled_hook( 'wpchef_autoupdate_check' );
		wp_clear_scheduled_hook( 'wpchef_updates_cron' );

		if ( $this->offline )
			return;

		//reschedule to change interval if it was changed in settings
		wp_schedule_event( time()+$this->autoupdate_check_interval, 'wpchef_updates_check', 'wpchef_updates_cron' );

		$this->oauth_refresh_me = true;
		$this->fetch_recipes_updates();

		$this->autoupdate_check();
	}

	function check_for_update( &$recipes )
	{
		if ( $this->offline )
			return;

		$updates = get_option( 'wpchef_recipes_updates' );
		if ( !$updates )
			return;

		foreach ( $recipes as $slug => $recipe )
		{
			if ( !$recipe['wpchef_id'] || empty($updates[ $recipe['wpchef_id'] ]) )
				continue;

			$update = $updates[ $recipe['wpchef_id'] ];

			$version = $recipe['installed'] ? $recipe['installed'] : $recipe['version'];
			if ( version_compare( $version, $update['version'], '<' ) )
				$recipes[ $slug ]['upgrade'] = $update;
		}
	}

	function fetch_recipes_updates()
	{
		if ( $this->offline )
			return false;

		$list = (array)get_option('wpchef_recipes');

		$ids = $active = array();
		foreach ( $list as $slug => $i )
		{
			$recipe = $this->get_recipe( $slug, true );
			if ( !$recipe || !$recipe['wpchef_id'] )
				continue;

			$ids[] = $recipe['wpchef_id'];
			if ( $recipe['installed'] )
				$active[] = $recipe['wpchef_id'];
		}

		$data = $this->oauth_request( 'recipe_updates', array(
			'ids' => implode(',',$ids),
			'active' => implode(',',$active),
		), true );

		$this->log( 'fetch updates', $data );

		if ( !is_array($data) )
			return false;

		$updates = array();
		foreach( $data as $recipe )
		{
			$update = array(
				'time' => time(),
				'version' => $recipe['version'],
				'slug' => $recipe['slug'],
			);
			$updates[ $recipe['wpchef_id'] ] = $update;

			$local = $this->get_recipe_by_id( $recipe['wpchef_id'] );

			if ( $local && ( $local['private'] != $recipe['private'] || $local['is_free'] != $recipe['is_free'] || $local['post_author'] != $recipe['post_author'] ) )
			{
				$local['private'] = $recipe['private'];
				$local['is_free'] = $recipe['is_free'];
				$local['post_author'] = $recipe['post_author'];

				$this->recipe_save( $local, $local['slug'] );
			}
		}


		add_option( 'wpchef_recipes_updates', $updates, '', 'no' );
		update_option( 'wpchef_recipes_updates', $updates );
		return true;
	}

	function autoupdate_add( $recipe_slug, $type=null )
	{
		if ( $this->offline )
			return;

		$list = $this->get_option( 'autoupdate' );
		if ( !is_array($list) )
			$list = array();

		if ( !in_array( $type, array( 'major', 'minor'), true ) )
		{
			//keep current update type if it present and new is not specified
			if ( !empty($list[ $recipe_slug ]) )
				return;

			$type = 'minor';
		}

		$list[ $recipe_slug ] =  $type;
		$this->set_option( 'autoupdate', $list );
	}

	function autoupdate_remove( $recipe_slug )
	{
		$this->autoupdate_unschedule( $recipe_slug );
		delete_option( 'recipe_autoupdate_alert_'.$recipe_slug );
		delete_option( 'wpchef_autoupdate_actions_'.$recipe_slug );

		$list = get_option( 'wpchef_autoupdate' );
		unset($list[ $recipe_slug ]);
		update_option( 'wpchef_autoupdate', $list );
	}

	function cron_shedules( $schedules )
	{
		$schedules['wpchef_autoupdate_step'] = array(
			'interval' => $this->autoupdate_step_interval,
			'display' => "Every {$this->autoupdate_step_interval} seconds",
		);
		$schedules['wpchef_updates_check'] = array(
			'interval' => $this->autoupdate_check_interval,
			'display' => "Every {$this->autoupdate_check_interval} seconds",
		);

		return $schedules;
	}

	function autoupdate_check()
	{
		$recipes = $this->recipes_list( false );

		$autoupdate = get_option( 'wpchef_autoupdate' );

		if ( !get_option('wpchef_updates_alert_sent') )
			foreach ( $recipes as $slug => $recipe )
				if ( $recipe['installed'] && $recipe['upgrade'] && empty( $autoupdate[ $slug ] ) )
					$this->send_updates_alert();

		$this->log( 'Check updates', $autoupdate, $recipes );

		if ( !is_array( $autoupdate ) )
			return;

		foreach ( $autoupdate as $slug => $type )
			if ( empty( $recipes[ $slug ]['installed'] ) )
				$this->autoupdate_remove( $slug );

			//elseif ( $this->is_minor_u

			elseif ( $recipes[ $slug ]['upgrade'] )
				$this->autoupdate_schedule( $slug, $type );
	}

	function is_major_update( $v1, $v2 )
	{
		$v1 = explode('.',$v1);
		$v1 = $v1[0];

		$v2 = explode('.',$v2);
		$v2 = $v2[0];

		return version_compare( $v1, $v2, '<' );
	}

	function autoupdate_reset_step_schedule()
	{
		$nexttime = wp_next_scheduled( 'wpchef_autoupdate_step' );
		if ( !$nexttime || $this->autoupdate_step_interval*1.2 < $nexttime - time() )
		{
			wp_clear_scheduled_hook( 'wpchef_autoupdate_step' );
			wp_schedule_event( time() + $this->autoupdate_step_interval, 'wpchef_autoupdate_step', 'wpchef_autoupdate_step');
		}
	}

	function autoupdate_schedule( $slug, $type=null )
	{
		if ( $this->offline )
			return;

		$recipe = $this->get_recipe( $slug );

		if ( !$recipe['wpchef_id'] )
			return;

		if ( $type === null )
		{
			$autoupdate = get_option( 'wpchef_autoupdate' );
			$type = isset( $autoupdate[ $slug ] ) ? $autoupdate[ $slug ] : 'minor';
		}

		if ( $type != 'major' && $this->is_major_update( $recipe['installed'], $recipe['version'] ) )
		{
			$this->log( 'major update disallowed' );
			$this->send_updates_alert();
			return;
		}

		$queue = (array)get_option( 'wpchef_autoupdate_queue', array() );
		$this->log( 'autoupdate schedule', $slug, $type, $queue, $recipe );

		//update in progress
		if ( !empty($queue[ $slug ]) && $queue[ $slug ] == $recipe['version'] )
			return;

		$recipe = $this->fetch_recipe( $recipe['wpchef_id'], false );
		if ( !$recipe || !$recipe['allow_access'] )
			return;

		$slug = $recipe['slug'];
		$this->recipe_save( $recipe, $slug, true );
		$this->recipe_things( $recipe, $slug );

		if ( !$recipe['installed'] )
			return;

		$this->autoupdate_reset_step_schedule();

		$this->recipe_actions( $recipe );

		$this->log( $recipe );
		$children=array(); $auto_actions=array();
		foreach ( $recipe['default_actions'] as $uname => $action )
		{
			if ( isset($recipe['actions_canceled'][ $uname ]) )
				continue;

			if ( !empty( $action['recipe'] ) && isset($recipe['actions_canceled'][ 'recipe_'.$action['recipe'] ]) )
				continue;

			$auto_actions[ $uname ] = 0;

			if ( !empty( $action['recipe'] ) && $action['recipe'] != $slug )
				$children[ $action['recipe'] ] = true;
		}

		if ( $type != 'major' && $children )
		{
			$current_children = (array)get_option( 'wpchef_recipe_children_'.$recipe['wpchef_id'], array() );
			foreach( $children as $child_slug => $v )
			{
				$child = $recipe['children'][ $child_slug ];
				if ( !isset($current_children[ $child['wpchef_id'] ]) )
					continue;

				if ( $this->is_major_update( $current_children[ $child['wpchef_id'] ], $child['version'] ) )
				{
					$this->log( 'has major child', $child );
					$this->send_updates_alert();
					return;
				}
			}
		}

		$queue[ $slug ] = $recipe['version'];
		$this->set_option( 'autoupdate_queue', $queue );

		$this->log( 'queue', $queue, $auto_actions );
		$this->set_option( 'autoupdate_actions_'.$slug, $auto_actions );
	}

	function autoupdate_unschedule( $slug )
	{
		delete_option( 'wpchef_autoupdate_actions_'.$slug );

		$queue = get_option( 'wpchef_autoupdate_queue' );
		if ( isset($queue[ $slug ]) )
		{
			unset($queue[ $slug ]);
			update_option( 'wpchef_autoupdate_queue', $queue );
		}

		$this->log( 'autoupdate_unschedule', $slug );
	}

	function autoupdate_trouble( $recipe, $action )
	{
		$slug = $recipe['slug'];

		$this->autoupdate_unschedule( $slug );

		//send alert to admin
		if ( get_option( 'recipe_autoupdate_alert_'.$slug ) )
			return;

		$email = get_bloginfo( 'admin_email' );
		$from = get_bloginfo();

		$subject = sprintf( '%s recipe auto-update failed', sanitize_text_field( $recipe['name'] ) );

		$message = sprintf(
'Hello!

The %s recipe at your %s website can\'t be updated automatically because some of its ingredients can\'t be installed. Please go to your site and update the recipe manually.

WPChef Team. ', sanitize_text_field( $recipe['name'] ), $from );

		$headers = sprintf( 'From: "%s" <%s>' , $from, $email ) ."\r\n";

		wp_mail( $email, $subject, $message, $headers );
		add_option( 'recipe_autoupdate_alert_'.$slug, '1', '', 'no' );
		update_option( 'recipe_autoupdate_alert_'.$slug, '1' );
	}

	function autoupdate_step()
	{
		if ( $this->offline )
			return;

		$queue = (array)get_option('wpchef_autoupdate_queue');
		$this->log( 'autoupdate step', $queue );
		if ( !$queue )
		{
			wp_clear_scheduled_hook( 'wpchef_autoupdate_step' );
			return;
		}

		$this->autoupdate_reset_step_schedule();

		$this->autoupdate = true;
		foreach ( $queue as $slug => $ver )
		{
			$recipe = $this->get_recipe( $slug );
			$actions = get_option( 'wpchef_autoupdate_actions_'.$slug );
			$this->log( $recipe, $actions );

			if ( !$recipe['installed'] || version_compare($recipe['installed'], $ver, '>=') || !is_array($actions) )
			{
				$this->log( 'Not to update', $ver );
				$this->autoupdate_unschedule( $slug );
				continue;
			}

			if ( $recipe['upgrade'] && version_compare( $recipe['upgrade'], $ver, '>' ) )
			{
				$this->autoupdate_schedule( $slug );
				continue;
			}

			$this->log( 'recipe need continue', $slug, $actions );

			if ( !$actions )
			{
				$this->recipe_complete( $recipe );
				$this->log( 'updated successfull' );
				$this->autoupdate_unschedule( $slug );
				continue;
			}

			$this->recipe_actions( $recipe );

			$elephants = false;
			foreach ( $actions as $uname => $attempts )
			{
				if ( empty($recipe['default_actions'][ $uname ]) )
				{
					unset( $actions[ $uname ] );
					update_option( 'wpchef_autoupdate_actions_'.$slug, $actions );

					$this->log( 'should not be installed by default', $uname );

					continue;
				}
				$action = $recipe['default_actions'][ $uname ];

				if ( $attempts >= 3 )
				{
					$this->autoupdate_trouble( $recipe, $action );
					break;
				}

				if ( empty($action['batch']) )
				{
					if ( $elephants )
						break;

					$elephants = true;
				}

				$actions[ $uname ]++;
				update_option( 'wpchef_autoupdate_actions_'.$slug, $actions );

				$this->log( 'start autoupdate action', $slug, $action );

				//ob_start();
				$rez = $this->do_action( $action, $recipe, $log );
				$this->log( 'autoupdate action results', $rez, $log /*, ob_get_clean()*/ );

				if ( $rez === true )
				{
					$this->recipe_save_action( $recipe, $action );
					unset( $actions[ $uname ] );
					update_option( 'wpchef_autoupdate_actions_'.$slug, $actions );
				}
			}
		}
		$this->autoupdate = false;
	}

	function autoupdate_ajax()
	{
		check_ajax_referer( 'wpchef_autoupdate', 'sec' );

		if ( !isset( $_POST['recipe'], $_POST['mode'] ) )
			$this->json_error();

		$recipe = $this->get_recipe( $_POST['recipe'], true );
		if ( !$recipe )
			$this->json_error( 'Invalid recipe', $_POST );

		if ( in_array( $_POST['mode'], array( 'major', 'minor' ) ) )
			$this->autoupdate_add( $recipe['slug'], $_POST['mode'] );

		elseif ( $_POST['mode'] == 'off' )
			$this->autoupdate_remove( $recipe['slug'] );

		else
			$this->json_error();

		$this->json_success();
	}

	function send_updates_alert()
	{
		if ( get_option('wpchef_updates_alert_sent') )
			return;

		add_option('wpchef_updates_alert_sent', 1, '', 'no');
		update_option('wpchef_updates_alert_sent', 1);

		$email = get_bloginfo( 'admin_email' );
		//$from = get_bloginfo();

		$subject = sprintf( 'Recipe updates', sanitize_text_field( $recipe['name'] ) );

		$message = sprintf(
'Hello!

The are recipe updates on %s. Update their manually at this link: %s

WPChef Team. ', get_bloginfo(), $this->url() );

		$this->log( $email, $subject, $message );
		wp_mail( $email, $subject, $message );
	}

	function page_list_styles()
	{
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	function page_list()
	{
		if ( isset( $_GET['force-check'] ) )
		{
			do_action('wpchef_updates_cron');
			$this->recipes_list( false );
		}

		$recipes = $this->recipes_list();

		if ( @$_GET['action'] == 'delete' && wp_verify_nonce( @$_GET['_wpnonce'], "remove-{$_GET['recipe']}" ) )
		{
			if ( $this->recipe_remove( $_GET['recipe'] ) )
			{
				unset( $recipes[ $_GET['recipe'] ] );
				?><div class="updated"><p>Recipe removed succesfully.</p></div><?php
			} else {
				?><div class="error"><p>Can't remove installed recipe.</p></div><?php
			}
		}


		include 'inc/list.tpl.php';
		$this->inject_apply_popup();
	}

	function ajax_remove()
	{
		check_ajax_referer( 'wpchef_recipe_delete', 'sec' );

		if ( $this->recipe_remove( $_REQUEST['slug'] ) )
			$this->json_success();

		$this->json_error('Can\'t delete the recipe.');
	}

	function wpchef_check_client()
	{
		$info = parse_url( $this->server );
		header( 'HTTP/1.0 200 Ok' );
		header( 'Access-Control-Allow-Origin: '.$info['host'] );
		header( 'Connection: close' );
		header( 'Transfer-Encoding: identity');
		//var_dump( $_SERVER );exit;
		$success_url = $this->url( 'install', $_REQUEST['id'] );
		$this->log( $success_url );
		$this->json_success( $success_url );
	}

	function page_add()
	{
		if ( isset($_GET['upload']) || $this->offline )
			return $this->page_upload();

		if ( !empty($_GET['recipe']) )
			return $this->page_install();

		$this->oauth_refresh_me = true;

		$info = null;
		$tab = @$_GET['tab'];
		switch( $tab ):

		case 'my':
			$data = $this->my_recipes();
			break;

		case 'purchased':
		case 'private':

			$data = $this->oauth_request('purchased', array(), true);
			break;

		default:
			$tab = '';
			$page = max(1, (int)@$_GET['p']);
			$args = array( 'per_page' => 12, 'p' => $page );

			if ( isset($_GET['s']) )
				$args['s'] = stripslashes( $_GET['s'] );

			$inf = $this->oauth_request( 'search', $args, true );
			//var_dump( $inf, $args ); exit;

			$data = $inf['items'];
			$info = $inf['info'];

			break;

		endswitch;

		if ( !is_array( $data ) )
			$data = array();

		//$installed = $this->recipes_list();

		//$me = $this->wpchef_me();

		include 'inc/add.tpl.php';
		$this->inject_apply_popup();
	}

	function page_install()
	{
		$recipe = $this->fetch_recipe( $_GET['recipe'], false );
		$mode = null;

		while ( $recipe )
		{
			$install_url = $this->url( 'install', $recipe['wpchef_id'], true ) . '&amp;nonce='.wp_create_nonce( 'install_recipe_'.$recipe['wpchef_id'] );

			if ( !@wp_verify_nonce( $_GET['nonce'], 'install_recipe_'.$_GET['recipe']) )
			{
				$mode = 'confirm';
				break;
			}

			if ( $recipe['allow_access'] )
			{
				$this->recipe_save( $recipe, $recipe['slug'], true );
				$mode = 'install';
				break;
			}

			if ( !$recipe['can_paid'] )
				break;

			$form = $this->oauth_request( 'checkout', array(
				'wpchef_id' => $recipe['wpchef_id'],
				'success_url' => $install_url,
				'cancel_url' => $this->url('install'),
			) );

			if ( $form )
			{
				$mode = 'form';
				break;
			}

			break;
		}

		include 'inc/install.tpl.php';
	}

	function page_upload()
	{
		$error = false;
		$token = (string)@$_GET['token'];

		if ( isset($_POST['recipe_upload_nonce']) && wp_verify_nonce( $_POST['recipe_upload_nonce'], 'recipe_upload') )
		{
			if ( !empty($_FILES['recipe']) )
				$error = $this->upload_recipe( $_FILES['recipe'] );

			elseif ( !empty($_POST['confirm']) && $token )
				$error = $this->replace_recipe( $token );
		}

		if ( isset($_GET['noheader']) )
			require_once ABSPATH.'wp-admin/admin-header.php';

		if ( $error )
		{
			?><div class="error"><p><?=$error?></p></div><?php
		}

		if ( $token && !$error )
			include 'inc/confirm.tpl.php';

		else
			include 'inc/upload.tpl.php';
	}

	function ajax_recipe_install()
	{
		check_ajax_referer( 'wpchef_recipe_install', 'sec' );
		$recipe = $this->fetch_recipe( $_REQUEST['id'], false );

		if ( $recipe && $recipe['allow_access'] )
		{
			$this->recipe_save( $recipe, $recipe['slug'], true );
			$this->json_success( $this->url('activate', $recipe['slug']) );
		}

		$this->json_error( 'Can\'t fetch recipe from repository.' );
	}

	function ajax_buy_child()
	{
		if ( !empty( $_REQUEST['recipe'] ) && !empty( $_REQUEST['child'] ) )
		{
			if ( !empty($_REQUEST['url']) )
			{
				$return = stripcslashes( $_REQUEST['url'] );
				$info = stripslashes_deep( $_REQUEST );
				$token = wp_generate_password( 6, false );

				set_transient( 'wpchef_autoapply_info_'.get_current_user_id().'_'.$token, $info, 30*MINUTE_IN_SECONDS );

				$return = add_query_arg( 'wpchef_apply_popup', $token, $return );
			}
			else
				$return = $this->url( 'activate', $_REQUEST['recipe'] );

			$form = $this->oauth_request( 'checkout', array(
				'wpchef_id' => $_REQUEST['child'],
				'success_url' => $return.'&child_success='.$_REQUEST['child'],
				'cancel_url' => $return,
			) );

			if ( $form )
			{
				echo $form;
				exit;
			}
		}
		header( 'HTTP/1.1 503 Service Temporarily Unaviable' );
		exit;
	}

	function inject_apply_popup( $with_scripts = false )
	{
		if ( empty( $_REQUEST['wpchef_apply_popup'] ) )
			return;

		$transient = 'wpchef_autoapply_info_'.get_current_user_id().'_'.$_REQUEST['wpchef_apply_popup'];
		$info = get_transient( $transient );
		if ( !$info || empty( $info['recipe'] ) )
			return;

		$recipe = $this->get_recipe( $info['recipe'] );
		if ( !$recipe )
			return;

		if ( $with_scripts )
		{
			add_thickbox();
			wp_print_request_filesystem_credentials_modal();
		}
		?>
		<script>
		jQuery( function($) {
			setTimeout( function(){
				wpchef.activate_modal( {
					slug: <?=json_encode($recipe['slug'])?>,
					name: <?=json_encode($recipe['name'])?>,
					callback: function( installed ){
						if ( installed )
						{
							$('body').on( 'thickbox:removed', function(){
								<?php if ( !empty($info['url']) ): ?>
								var url = <?=json_encode($info['url'])?>;
								<?php else: ?>
								url = window.location.toString().replace(/\&?wpchef_apply_popup=[a-z\d]+/i , '');
								<?php endif ?>
								window.location.assign( url );
							} );
						}
					},
					customs: <?=json_encode(empty($info['customs'])?null:$info['customs'])?>
				} );
			}, 200 );
		} );
		</script>
		<?php
		delete_transient( $transient );
	}

	function upload_recipe( $file )
	{
		$recipe = $this->recipe->upload( $file, $error, false, $slug );

		if ( !$recipe )
			return $error;

		if ( $this->get_recipe( $slug, true ) )
			return $this->replace_confirm( $slug, $recipe );

		$this->recipe_save( $recipe, $slug );

		wp_redirect( $this->url( 'install', $slug ).'&uploaded' );
		exit;
	}

	function replace_confirm( $slug, $recipe )
	{
		$data = array(
			'slug' => $slug,
			'recipe' => $recipe,
		);

		$token = wp_generate_password( 8, false, false );
		set_transient( 'recipe-confirm-'.$token, $data, HOUR_IN_SECONDS/2 );

		wp_redirect( $this->url_add.'&upload&token='.$token.'&noheader' );
		exit;
	}

	function replace_recipe( $token )
	{
		$data = get_transient( 'recipe-confirm-'.$token );
		if ( !$data )
			return 'Session timeout, try again.';

		delete_transient( 'recipe-confirm-'.$token );

		update_option( 'wpchef_recipe_'.$data['slug'], $data['recipe'] );

		wp_redirect( $this->url( 'install', $data['slug'] ).'&uploaded' );
		exit;
	}

	function page_edit()
	{
		$fallback_me = $this->wpchef_me();
		$new = @$_GET['page'] == 'recipe-create';

		if ( $new )
		{
			$recipe = $this->make_recipe();
			$slug = $recipe['slug'];
		}
		else
		{
			$slug = (string)@stripslashes($_GET['recipe']);
			$recipe = $this->get_recipe( $slug, !@$_POST['recipe_new'] );

			if ( !$recipe || !$recipe['is_my_own'] )
			{
				if ( isset($_GET['noheader']) )
					require_once ABSPATH.'wp-admin/admin-header.php';

				?><div class="notice notice-error"><p>Recipe not exists.<p/></div><?php
				return;
			}

			if ( isset( $_GET['switchback'] ) && wp_verify_nonce( $_GET['switchback'], 'recipe_switchback' ) )
			{
				$fork = $this->fetch_recipe( $recipe['fork_id'] );
				if ( !$fork )
				{
					?><div class="notice notice-error"><p><?php esc_html_e('Unable to switch back. Origin is not accessible.', 'wpchef') ?></p></div><?php
				}
				else
				{
					$this->recipe_save( $fork, $fork['slug'], true );
					if ( $fork['slug'] != $recipe['slug'] )
					{
						$this->recipe_merge( $recipe['slug'], $fork['slug'] );
						$this->recipe_remove( $recipe['slug'], true );
					}
					wp_redirect( $this->url('edit', $fork['slug']) );
					exit;
				}
			}

			if ( isset($_POST['recipe_edit_nonce']) && wp_verify_nonce( $_POST['recipe_edit_nonce'], 'recipe_edit' ) )
				$this->edit_recipe( $recipe, $error );

			elseif ( !empty($_GET['sec']) && wp_verify_nonce($_GET['sec'], "savecloud_{$_GET['sec']}") )
				$this->save_cloud( $recipe, $error );

			$recipe['new'] = !empty($_POST['recipe_new']) && !empty($error);
		}

		if ( isset($_GET['noheader']) )
			require_once ABSPATH.'wp-admin/admin-header.php';

		//$is_my_own = $this->is_my_own( $recipe );
		$me = $this->wpchef_me();

		$current = !$this->offline && $recipe['is_my_own'] && $recipe['wpchef_id'] ? $this->fetch_recipe( $recipe['wpchef_id'], false ) : false;

		$this->options_snapshot();

		include 'inc/edit.tpl.php';
		$this->inject_apply_popup( true );
	}

	function excluded_options()
	{
		return "
			    not option_name like '%_transient%'
			and not option_name like 'mainwp_cron_last_%'
			and not option_name like 'wpchef_%'
			and option_name != 'cron'
			and option_name != 'active_plugins'
		";
	}

	function options_snapshot()
	{
		global $wpdb;
		$wpdb->query("drop table if exists `{$wpdb->prefix}chef_options_snapshot`");
		$wpdb->query("
			create table `{$wpdb->prefix}chef_options_snapshot`
			like `{$wpdb->prefix}options`
		");
		$wpdb->query("
			insert `{$wpdb->prefix}chef_options_snapshot`
			select * from `{$wpdb->prefix}options`
			where ".$this->excluded_options()
		);
	}

	function recent_options()
	{
		global $wpdb;
		$options = $wpdb->get_col("
			select
				o.option_name
			from
				`{$wpdb->prefix}options` o
				left join `{$wpdb->prefix}chef_options_snapshot` s using( option_name, option_value )
			where
				s.option_id is null
			having ".$this->excluded_options()."
			UNION DISTINCT
			select
				s.option_name
			from
				`{$wpdb->prefix}chef_options_snapshot` s
				left join `{$wpdb->prefix}options` o using( option_name )
			where
				o.option_id is null
			having
				".$this->excluded_options()
		);
		$changelist = array();
		foreach ( $options as $option )
		{
			$value = get_option( $option, null );
			$oldvalue = $wpdb->get_var( $wpdb->prepare("
				select option_value
				from `{$wpdb->prefix}chef_options_snapshot`
				where option_name=%s", $option ) );
			$oldvalue = maybe_unserialize( $oldvalue );

			if ( $value === $oldvalue )
				continue;

			$this->option_compare( $option, $oldvalue, $value, $changelist );
		}

		if ( !$changelist )
			$this->json_response( false );

		$this->options_snapshot();

		include 'inc/options_snapshot.tpl.php';
		exit;
	}

	function option_compare( $option, $oldvalue, $value, &$changelist )
	{
		$type = gettype( $value );

		if ( $type != 'array' && $type != 'object' )
		{
			$changelist[ $option ] = $value;
			return;
		}

		if ( gettype( $oldvalue ) != $type )
		{
			$changelist[ $option ] = $value;
			return;
		}

		if ( $type != 'array' )
		{
			$value = (array)$value;
			$oldvalue = (array)$oldvalue;
		}
		$allkeys = array_keys( $value + $oldvalue );

		foreach ( $allkeys as $key )
		{
			if ( !isset($value[ $key ]) )
				$value[ $key ] = null;

			if ( !isset($oldvalue[ $key ]) )
				$oldvalue[ $key ] = null;

			if ( $value[ $key ] !== $oldvalue[ $key ] )
			{
				$suboption = $option;
				if ( $type == 'array' )
					$suboption .= '['.$key.']';
				else
					$suboption .= '->'.$key;

				$this->option_compare( $suboption, $oldvalue[ $key ], $value[ $key ], $changelist );
			}
		}
	}

	function my_recipes( $cache = true )
	{
		if ( !$this->wpchef_me() )
			return array();

		if ( !$cache )
			$this->cache_delete( 'my_recipes' );

		elseif ( ($recipes = $this->cache_get('my_recipes')) !== false )
			return $recipes;

		$my_recipes = $this->oauth_request( 'my_recipes' );
		if ( !is_array($my_recipes) )
			return array();

		$required_keys = array(
			'wpchef_id', 'slug', 'name', 'description', 'author', 'author_uri'
		);
		foreach ( $my_recipes as $id => $recipe )
		{
			if ( !is_array( $recipe ) )
			{
				unset( $my_recipes[ $id ] );
				continue;
			}
			foreach ( $required_keys as $key )
			{
				if ( !isset( $recipe[ $key ] ) )
					$my_recipes[ $id ][ $key ] = '';
			}
		}

		$this->cache_set( 'my_recipes', $my_recipes, 30 * MINUTE_IN_SECONDS );

		return $my_recipes;
	}

	function ajax_refresh_recipes()
	{
		$this->json_response( array_values( $this->my_recipes( false ) ) );
	}

	function edit_recipe( &$recipe, &$error = null )
	{
		$slug = $recipe['slug'];

		$new = $this->recipe->form_post();
		//never fetch this fields from form
		if ( $recipe['is_my_own'] )
		{
			$new['wpchef_id'] = $recipe['wpchef_id'];
			$new['post_author'] = $recipe['post_author'];
			$new['fork_id'] = $recipe['fork_id'];
			$new['private'] = $recipe['private'];
			$new['is_free'] = $recipe['is_free'];
		}
		else
		{
			$new['wpchef_id'] = 0;
			$new['post_author'] = 0;

			$new['fork_id'] = $recipe['wpchef_id'];
			$new['private'] = false;
			$new['is_free'] = true;
		}


		$recipe = array_merge( $recipe, $new );

		if ( $me = $this->wpchef_me() )
		{
			$recipe['author'] = $me['display_name'];
			$recipe['author_uri'] = $me['url'];
		}

		if ( empty( $recipe['name'] ) )
		{
			$error = __('Name is required.', 'wpchef');
			return false;
		}

		if ( empty( $recipe['version'] ) )
		{
			$error = __('Version is required.', 'wpchef');
			return false;
		}

		$recipe['slug'] = $recipe['wpchef_id'] ? $slug : $recipe['slug'];
		if ( !$recipe['slug'] )
			$recipe['slug'] = $recipe['name'];

		$recipe['slug'] = $this->recipe->sanitize_slug( $recipe['slug'] );

		if ( $recipe['slug'] != $slug && $this->get_recipe( $recipe['slug'], true ) )
		{
			$i = 0;
			do
			{
				$i++;
				$newslug = $recipe['slug'].'-'.$i;
			}
			while ( $newslug != $slug && $this->get_recipe( $newslug, true ) );

			$recipe['slug'] = $newslug;
		}

		if ( @$_POST['save'] == 'download' )
			$this->recipe->download( $recipe, $slug );

		$this->recipe_save( $recipe, $recipe['slug'] );

		if ( $slug != $recipe['slug'] )
		{
			if ( $slug )
				$this->recipe_merge( $slug, $recipe['slug'] );
			$this->recipe_remove( $slug, true );

			$slug = $recipe['slug'];
		}

		if ( !$recipe['wpchef_id'] )
			$this->autoupdate_remove( $slug );

		if ( !$this->offline && @$_POST['save'] == 'cloud' )
		{
			if ( !( $me = $this->wpchef_me() ) )
			{
				wp_redirect( admin_url('admin.php?page=recipe-settings').'&for='.urlencode($slug) );
				exit;
			}

			if ( empty( $me['admin_access'] ) )
			{
				$error = sprintf(
					esc_html__('You\'re logged in WPChef as %s in read-only mode. Therefore you cant save your recipes at WPChef. To gain full access you can reconnect to WPChef under another account on the %s.', 'wpchef' ),
					sprintf(
						'<a href="%s" target="_blank">%s</a>',
						esc_attr($me['profile_url']), esc_html($me['display_name'])
					),
					sprintf(
						'<a href="%s">%s</a>',
						admin_url('admin.php?page=recipe-settings'),
						esc_html__('settings page', 'wpchef' )
					)
				);
				return false;
			}

			return $this->save_cloud( $recipe, $error );
		}

		$url = $this->url( 'edit', $slug );

		if ( @$_POST['save'] == 'apply' )
		{
			$info = array( 'recipe' => $slug );
			$token = wp_generate_password( 6, false );
			set_transient( 'wpchef_autoapply_info_'.get_current_user_id().'_'.$token, $info, 30*MINUTE_IN_SECONDS );

			$url .= '&wpchef_apply_popup='.$token;
		}
		else
			$url .= '&success';

		wp_redirect( $url );
		exit;
	}

	function make_recipe()
	{
		$recipe = null;
		$this->recipe_things( $recipe, '' );

		$recipe['name'] = get_bloginfo();
		$recipe['description'] = get_bloginfo( 'description' );
		$recipe['version'] = '1.0';
		$recipe['new'] = true;

		if ( $me = $this->wpchef_me() )
		{
			$recipe['author'] = $me['display_name'];
			$recipe['author_uri'] = $me['url'];
		}

		return $recipe;
	}

	function make_snapshot()
	{
		check_ajax_referer( 'wpchef_snapshot', 'sec' );

		$ingredients = array();

		$theme = wp_get_theme();
		$ingredient = array(
			'type' => 'theme',
			'slug' => $theme->get_stylesheet(),
			'name' => $theme->Name,
			'description' => $theme->Description,
			'author' => $theme->get('Author'),
			'author_uri' => $theme->get('AuthorURI'),
		);
		$this->check_theme_accesible( $ingredient );

		if ( $template = $theme->parent() )
		{
			$ingredient0 = array(
				'type' => 'theme',
				'slug' => $template->get_stylesheet(),
				'name' => $template->Name,
				'description' => $template->Description,
				'author' => $template->get('Author'),
				'author_uri' => $template->get('AuthorURI'),
			);
			$this->check_theme_accesible( $ingredient0 );

			$ingredients[] = $ingredient0;

			$ingredient['template'] = $template->get_stylesheet();
			$ingredient['template_name'] = $template->Name;
		}
		$ingredients[] = $ingredient;

		foreach ( $this->get_plugins() as $plugin_slug => $plugin )
		{
			if ( !$plugin['is_active'] || $plugin_slug == 'wpchef' )
				continue;

			if ( preg_match( '/^(.+)\.php$/', $plugin_slug, $m ) )
				$plugin_slug = $m[1];

			$ingredient = array(
				'type' => 'plugin',
				'slug' => $plugin_slug,
				'name' => $plugin['name'],
				'description' => $plugin['description'],
				'author' => $plugin['author'],
				'author_uri' => $plugin['authoruri'],
			);

			$this->check_plugin_accesible( $ingredient );

			$ingredients[] = $ingredient;
		}

		$ingredients = apply_filters( 'wpchef_make_snapshot', $ingredients );

		echo wpchef_editor::instance()->constructor( $ingredients );
		exit;
	}

	function check_plugin_accesible( &$ingredient )
	{
		$updates = get_site_transient( 'update_plugins' );
		if ( !empty( $updates->response[ $ingredient['slug'] ] ) || !empty( $updates->no_update[ $ingredient['slug'] ] ) )
			return true;

		$url = "https://api.wordpress.org/plugins/info/1.0/{$ingredient['slug']}.json";
		$info = $this->remote_json( $url );

		if ( empty( $info['name'] ) )
		{
			$ingredient['alert'] = array(
				'%s %s',
				esc_html__('This plugin was not found in the WordPress.org directory and thus can\'t be used here.', 'wpchef'),
				esc_html__('The ingredient will not be saved.', 'wpchef')
			);
			$ingredient['valid'] = false;

			return false;
		}

		return true;
	}

	function check_theme_accesible( &$ingredient )
	{
		$updates = get_site_transient( 'update_themes' );
		if ( !empty( $updates->response[ $ingredient['slug'] ] ) || !empty( $updates->no_update[ $ingredient['slug'] ] ) )
			return true;

		$url = 'https://api.wordpress.org/themes/info/1.1/?action=theme_information&request[slug]='.$ingredient['slug'];
		$info = $this->remote_json( $url );

		if ( empty( $info['name'] ) )
		{
			$ingredient['alert'] = array(
				'%s %s',
				esc_html__('This theme was not found in the WordPress.org directory and thus can\'t be used here.', 'wpchef'),
				esc_html__('The ingredient will not be saved.', 'wpchef')
			);
			$ingredient['valid'] = false;

			return false;
		}

		return true;
	}

	function save_cloud( &$recipe, &$error )
	{
		$json = json_encode( $this->recipe->sanitize( $recipe ) );

		$data = array(
			'recipe_json' => $json
		);

		$new = $this->oauth_request( 'save', $data );
		if ( !$new )
		{
			$error = $this->oauth_last_error ? $this->oauth_last_error :
				__('Can\'t connect to your WPChef account. Please reconnect to WPChef.org or try again later.', 'wpchef');

			return false;
		}

		$private_slots = isset( $new['private_slots'] ) ? $new['private_slots'] : null;
		unset( $new['private_slots'] );

		$this->log( 'save_cloud', $new );
		$this->recipe_save( $new, $new['slug'], true );

		if ( $new['slug'] != $recipe['slug'] /*&& $recipe['wpchef_id'] != $new['wpchef_id']*/ )
		{
			$this->recipe_merge( $recipe['slug'], $new['slug'] );
			$this->recipe_remove( $recipe['slug'], true );
		}

		$url =  $this->url( 'edit', $new['slug'] ).'&success=cloud'.($recipe['wpchef_id']==$new['wpchef_id']?'':'new');

		if ( isset($private_slots) && $recipe['wpchef_id'] != $new['wpchef_id'] )
			$url .= '&slots='.urlencode($private_slots);

		wp_redirect( $url );
		exit;
	}

	public function get_plugins( $cache = true )
	{
		static $plugins;
		if ( isset( $plugins ) && $cache )
			return $plugins;

		if ( !$cache )
			wp_cache_delete( 'plugins', 'plugins' );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins = array();
		foreach ( get_plugins() as $slug => $plugin )
		{
			$plugin = array_change_key_case( $plugin );
			$plugin['slug'] = $slug;
			$plugin['is_active'] = is_plugin_active( $slug );
			$plugin['update'] = false;

			if ( preg_match('~^(.+)/.+\.php$~', $slug, $m) )
				$slug = $m[1];

			$plugins[ $slug ] = $plugin;
		}

		require_once ABSPATH.'wp-includes/update.php';
		wp_update_plugins();

		$updates = get_site_transient( 'update_plugins' );
		foreach( $plugins as $slug => $plugin )
			if ( !empty( $updates->response[ $plugin['slug'] ] ) )
				$plugins[ $slug ]['update'] = $updates->response[ $plugin['slug'] ]->new_version;

		return $plugins;
	}

	public function get_themes( $cache = true )
	{
		static $themes;
		if ( isset( $themes ) && $cache )
			return $themes;

		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$themes = array();
		foreach ( wp_get_themes() as $slug => $item )
		{
			$theme = array (
				'name' => $item->Name,
				'version' => $item->Version,
				'slug' => $slug,
				'is_active' =>  $slug == get_option( 'stylesheet' ),
				'update' => false,
				'template' => $item->get_template(),
			);

			$themes[ $slug ] = $theme;
		}

		require_once ABSPATH.'wp-includes/update.php';
		wp_update_themes();

		$updates = get_site_transient( 'update_themes' );
		foreach( $themes as $slug => $theme )
			if ( !empty( $updates->response[ $slug ] ) )
				$themes[ $slug ]['update'] = $updates->response[ $slug ]['new_version'];

		return $themes;
	}

	function recipe_actions( &$recipe, $uninstall = false )
	{
		$recipe['actions'] = array();
		if ( empty($recipe['root']) )
			$recipe['root'] = $recipe['slug'];

		$recipe['children'][ $recipe['slug'] ] = &$recipe;

		foreach ( $recipe['ingredients'] as $i => $ingredient )
		{
			if ( $uninstall )
				$action = $this->ingredient_action_uninstall( $ingredient, $recipe );
			else
				$action = $this->ingredient_action( $ingredient, $recipe );

			if ( $action === false )
				continue;

			if ( empty($recipe['children'][ $recipe['root'] ]['is_my_own']) && $action['action'] == 'invalid' )
				continue;

			$action['recipe'] = $recipe['slug'];
			$action['notices'] = (array)@$action['notices'];

			if ( $action['uname'] && isset( $recipe['actions'][ $action['uname'] ]) )
			{
				continue;
				/*
				$action['ingredient_subname'] = 'Duplicate ingredient';
				$action['notices'][] = array( 'warning', 'Duplicate ingredient' );
				$action['checked'] = false;
				$action['enabled'] = false;
				$action['uname'] = '';
				*/
			}

			if ( $action['uname'] )
			{
				if ( !$uninstall && $action['enabled'] && isset($recipe['actions_enabled'][ $action['uname'] ]) )
				{
					$action['checked'] = false;
					$action['enabled'] = false;

					$action['notices'][] = array( 'info', 'Install child recipe directly to use this ingredient.' );
				}

				if ( $action['enabled'] )
				{
					$recipe['actions_enabled'][ $action['uname'] ] = true;

					if ( $action['checked'] && $action['type'] != 'recipe' )
						$recipe['default_actions'][ $action['uname'] ] = &$action;
				}

				$recipe['actions'][ $action['uname'] ] = &$action;
				$recipe['all_actions'][ $action['uname'] ] = &$recipe['actions'][ $action['uname'] ];

			}
			else
				$recipe['actions'][] = &$action;

			unset( $action );
		}

		//Child recipes
		foreach ( $recipe['actions'] as $i => $action )
		{
			if ( @$action['type'] != 'recipe' || $action['child']['installed'] )
				continue;

			if ( isset($recipe['children'][ $action['slug'] ]) )
			{
				$action['ingredient_subname'] = 'Already imported';
				$action['notices'][] = array( 'info', __('Already imported', 'wpchef') );
				$action['checked'] = false;
				$action['enabled'] = false;
			}

			else
			{
				$child = &$action['child'];
				unset( $action['child'] );

				$child['children'] = &$recipe['children'];
				$child['all_actions'] = &$recipe['all_actions'];
				$child['actions_enabled'] = &$recipe['actions_enabled'];
				$child['default_actions'] = &$recipe['default_actions'];
				$child['actions_made'] = &$recipe['actions_made'];
				$child['root'] = $recipe['root'];
				$child['installed'] = $recipe['installed'];

				$this->recipe_actions( $child, $uninstall );
				$action['actions'] = $child['actions'];
				if ( $action['enabled'] )
				{
					$has_ingredient = false; $has_installed = false;
					foreach ( $action['actions'] as $child_action )
					{
						if ( $child_action['enabled'] && $child_action['checked'] )
							$has_ingredient = true;

						if ( $child_action['installed'] && $child_action['checked'] )
							$has_installed = true;
					}
					if ( !$has_ingredient )
					{
						$action['enabled'] = false;
						$action['checked'] = $action['installed'] = $has_installed;
					}
				}

			}
			$recipe['actions'][ $i ] = $action;
		}

		if ( $recipe['slug'] == $recipe['root'] )
		{
			if ( $recipe['installed'] )
				$this->forgotten_uninstall_actions( $recipe );

			if ( $uninstall )
				$recipe['actions'] = array_reverse( $recipe['actions'] );
		}
	}

	function ingredient_action( $ingredient, &$recipe )
	{
		$ingredient = $this->ingredient->normalize( $ingredient );

		$plugins = $this->get_plugins();
		$themes = $this->get_themes();

		$fail_action = array(
			'uname' => '',
			'action' => 'invalid',
			'title' => '',
			'description' => 'Invalid ingredient',
			'enabled' => false,
			'checked' => false,
		);

		$type = $ingredient['type'];

		switch ( $type )
		{
			case 'plugin':
			case 'theme':
				$slug = $ingredient['slug'];

				if ( $type == 'plugin' && preg_match('~^(.+)/.+\.php$~', $slug, $m) )
					$slug = $m[1];

				if ( !$slug )
				{
					$action = $fail_action;
					break;
				}

				$name = $ingredient['name'];

				$Type = ucfirst( $type );
				$action = array(
					'action' => "{$type}_install",
					'slug' => $slug,
					'uname' => "{$type}_$slug",
					'title' => array(
						"%s <b>%s</b> $type",
						esc_html__('Install & Activate', 'wpchef'),
						sprintf(
							'<a href="%s" target="_blank">%s</a>',
							esc_url("https://wordpress.org/{$type}s/$slug/"),
							esc_html( $name )
						),
					),
					'description' => $ingredient['description'],
					'enabled' => true,
					'checked' => true,
					'type' => $type,
					'replace' => false,
					'installed' => false,
				);

				$current = false;

				$items = ($type == 'plugin') ? $this->get_plugins() : $this->get_themes();
				if ( !empty( $items[ $slug ] ) )
					$current = $items[ $slug ];

				if ( $current )
				{
					if ( $current['is_active'] )
					{
						if ( $type == 'theme' && $current['template'] && isset( $recipe['all_actions'][ 'theme_'.$current['template'] ] ) )
						{
							$recipe['all_actions'][ 'theme_'.$current['template'] ]['checked'] = false;
							$recipe['all_actions'][ 'theme_'.$current['template'] ]['notices'] = array( array( 'info', 'The child theme will be activated instead' ) );
						}
						$action['enabled'] = false;
						$action['checked'] = true;
						$action['installed'] = true;
					}
					else
					{
						$action['action'] = "{$type}_activate";
						$action['title'][1] = esc_html__('Activate', 'wpchef');
					}
				}

				break;

			case 'option':

				if ( !$ingredient['option'] )
				{
					$action = $fail_action;
					break;
				}

				$action = array(
					'action' => "option",
					'uname' => 'option_'.$ingredient['option'],
					'option' => $ingredient['option'],
					'value' => $ingredient['value'],
					'title' => array(
						'<i class="fa fa-question-circle wpchef-hint" title="%s"></i>
						Set option <strong>%s</strong> to <input type="text" data-name="value" class="action-param" value="%s">',
						__('Current value:', 'wpchef').' '.esc_html( json_encode( $this->option_get_value( $ingredient['option'] ) ) ),
						esc_html($ingredient['option']), esc_attr( wpchef_ingredient::instance()->value_encode( $ingredient['value'] ) ),
					),
					'enabled' => true,
					'checked' => true,
					'batch' => true,
					'type' => $type,
					'description' => $ingredient['description'],
				);

				if ( is_null($ingredient['value']) )
				{
					$action['title'] = array(
						'Remove option <strong>%s</strong>',
						esc_html($ingredient['option']),
					);
				}

				$equal = $this->option_is( $ingredient['option'], $ingredient['value'] );

				$actions = (array)get_option( 'wpchef_actions_made', array() );
				$actions = !empty($actions[ $action['uname'] ]) ? (array)$actions[ $action['uname'] ] : array();

				if ( !empty( $recipe['actions_made'][ $action['uname'] ] ) )
				{
					$prev = $recipe['actions_made'][ $action['uname'] ];

					$prev_value = array_key_exists( 'value', (array)@$prev['params'] ) ? $prev['params']['value'] : @$prev['value'];

					$keys = array_keys( $actions );
					$setted_up_by_me = end($keys) == $recipe['root'] && $this->option_is( $ingredient['option'], $prev_value );
					$customized = $setted_up_by_me && ( $prev_value != $prev['value'] );
					//var_dump( compact( 'actions', 'prev', 'prev_value', 'equal', 'customized', 'setted_up_by_me'), $recipe['slug'] );
				}
				else
				{
					$setted_up_by_me = false;
					$customized = false;
				}

				$action['notices'] = array();

				if ( $customized && !$equal )
				{
					$action['checked'] = false;
					$action['notices'][] = array( 'warning', __('The custom value was set at the previous installation', 'wpchef') );

					$action['installed'] = true;
				}

				if ( $setted_up_by_me && $equal )
				{
					$action['installed'] = true;
					$action['checked'] = true;
					$action['enabled'] = false;
				}

				if ( !$equal && $this->autoupdate )
					$action['enabled'] = false;

				elseif ( !$equal && !$setted_up_by_me )
				{
					$keys = array_keys( $actions );
					$conflict = end( $keys );

					if ( $conflict != $recipe['root'] && ($conflict = $this->get_recipe($conflict, true)) && $conflict['installed'] )
						$action['notices'][] = array( 'warning', __('Will replace recipe settings: ', 'wpchef').esc_html( $conflict['name'] ) );
				}

				break;

			case 'recipe':
				$child = $this->fetch_recipe( $ingredient['wpchef_id'] );

				if ( $child )
				{
					$this->recipe_things( $child, $child['slug'] );

					$action = array(
						'uname' => 'recipe_'.$ingredient['wpchef_id'],
						'slug' => $child['slug'],
						'action' => 'recipe',
						'title' => array( 'Recipe "%s"', esc_html($child['name']) ),
						'description' => $ingredient['description'],
						'enabled' => true,
						'checked' => true,
						'child' => $child,
						'type' => 'recipe',
					);

					if ( $child['installed'] )
					{
						$action['enabled'] = false;
						$action['installed'] = true;
						//$action['notices'][] = array( 'info', 'Already installed manually.' );
					}
					elseif ( !$child['allow_access'] )
					{
						if ( $child['_is_from_cache'] )
							$child = $this->fetch_recipe( $ingredient['wpchef_id'], false );

						if ( !$child['allow_access'] )
						{
							$action['type'] = 'recipe_disallow';
							$action['enabled'] = false;
							$action['checked'] = false;

							if ( $child['can_paid'] )
							{
								$action['notices'][] = array(
									'warning',
									'You must buy this recipe for your site. <a href="%s" class="inline-buy-child" data-wpchef_id="%d">Buy now ($%d)</a>',
									esc_attr( $child['link'] ), $child['wpchef_id'], esc_html($child['amount'])
								);
							}
							else
							{
								$action['notices'][] = array(
									'error',
									'Sorry, you don\'t have access to this recipe.'
								);
							}
						}
					}
				}

				else
					$action = $fail_action;

				break;



			case 'action':

				if ( $ingredient['runon'] != 'install' )
					return false;

				$action = $this->ingredient_action_action( $ingredient, $recipe );

				break;


			default:
				$action = $fail_action;
				break;
		}

		return apply_filters( 'wpchef_ingredient_action', $action, $ingredient, $recipe );
	}

	function ingredient_action_uninstall( $ingredient, &$recipe )
	{
		$ingredient = $this->ingredient->normalize( $ingredient );

		$fail_action = array(
			'uname' => '',
			'action' => 'invalid',
			'title' => 'Invalid ingredient',
			'enabled' => false,
			'checked' => false,
		);

		$type = $ingredient['type'];

		switch ( $type ):

		case 'plugin':
		case 'theme':
			$slug = $ingredient['slug'];

			if ( preg_match('~^(.+)/.+\.php$~', $slug, $m) )
				$slug = $m[1];

			if ( !$slug )
			{
				$action = $fail_action;
				break;
			}

			$current = false;
			$items = ($type == 'plugin') ? $this->get_plugins() : $this->get_themes();
			if ( !empty( $items[ $slug ] ) )
			{
				$current = $items[ $slug ];
				$ingredient['name'] = $current['name'];
			}

			$action = array(
				'action' => "{$type}_uninstall",
				'slug' => $slug,
				'uname' => "{$type}_$slug",
				'title' => array( "Deactivate <b>%s</b> $type", esc_html($ingredient['name']) ),
				'description' => $ingredient['description'],
				'enabled' => true,
				'checked' => true,
				'type' => $type,
				'uninstall' => 1,
				'installed' => true,
			);

			if ( !$current || !$current['is_active'] )
			{
				$action['enabled'] = false;
				$action['checked'] = false;
				$action['installed'] = false;
			}
			elseif ( !isset($recipe['actions_made'][ $action['uname'] ]) )
			{
				$action['checked'] = false;
				$action['notices'][] = array(
					'info',
					'%s <i class="fa fa-question-circle wpchef-hint" title="%s"></i>',
					esc_html__('Was installed prior to the recipe.','wpchef'),
					esc_attr__("This $type was already installed before you applied this recipe to the site. If you still want to remove the $type from your site please check its checkbox.", 'wpchef'),
				);
			}
			elseif ( $other = $this->package_still_need( $slug, $type, $recipe['root'] ) )
			{
				$action['checked'] = false;
				$action['notices'][] = array( 'warning',
					'Used by <b>%s</b> recipe', esc_html( $other['recipe']['name'] )
				);
				$action['other'] = $other;
			}

			break;

		case 'option':

			if ( !$ingredient['option'] )
			{
				$action = $fail_action;
				break;
			}

			$action = array(
				'action' => "option_uninstall",
				'uname' => "option_{$ingredient['option']}",
				'option' => $ingredient['option'],
				'title' => "Restore previous value of option \"{$ingredient['option']}\"",
				'description' => $ingredient['description'],
				'batch' => true,
				'enabled' => true,
				'checked' => true,
				'type' => 'option',
				'uninstall' => 1,
			);

			if ( empty($recipe['actions_made'][ $action['uname'] ]) )
			{
				$action['enabled'] = false;
				$action['checked'] = false;
			}
			else
			{
				$action['installed'] = true;

				$action['title'] = array(
					$action['title'].' &nbsp; <i class="fa fa-question-circle wpchef-hint" title="%s"></i>',
				);

				$current = json_encode( $this->option_get_value( $ingredient['option'] ) );

				$all_actions = (array)get_option( 'wpchef_actions_made', array() );
				if ( empty( $all_actions[ $action['uname'] ] ) || !is_array( $all_actions[ $action['uname'] ] ) )
					$new = $current;

				else
				{
					$last = end( $all_actions[ $action['uname'] ] );
					$last_slug = key( $all_actions[ $action['uname'] ] );

					if ( $last_slug != $recipe['root'] || !$this->option_is( $ingredient['option'], $last ) )
						$new = $current;

					elseif ( count( $all_actions[ $action['uname'] ] ) < 2 )
						$new = 'null';

					else
						$new = prev( $all_actions[ $action['uname'] ] );

				}

				$action['title'][] = esc_attr( sprintf(
					'Current value: %s
					Will set to: %s',
					esc_html( $current ),
					esc_html( $new )
				) );
			}

			break;

		case 'recipe':
			$child = $this->fetch_recipe( $ingredient['wpchef_id'] );
			if ( $child )
				$this->recipe_things( $child, $child['slug'] );

			if ( !$child )
			{
				$action = $fail_action;
				$action['notices'][] = array( 'error', "Can't fetch recipe \"%s\" from repository.", esc_html($ingredient['name']) );
				break;
			}

			$action = array(
				'uname' => 'recipe_'.$ingredient['wpchef_id'],
				'action' => 'recipe_uninstall',
				'slug' => $child['slug'],
				'title' => array( 'Recipe "%s"', esc_html($child['name']) ),
				'description' => $ingredient['description'],
				'type' => 'recipe',
				'child' => $child,
				'enabled' => true,
				'checked' => true,
				'uninstall' => true,
				'installed' => true,
			);

			if ( $child['installed'] )
			{
				$action['enabled'] = false;
				$action['checked'] = false;
				$action['installed'] = true;
				$action['notices'][] = array( 'info', 'Installed manually.' );
			}

			break;

		case 'action':

			if ( $ingredient['runon'] != 'uninstall' )
				return false;

			$action = $this->ingredient_action_action( $ingredient, $recipe );

			break;

		default:
			$action = $fail_action;
			break;

		endswitch;

		$action = apply_filters( 'wpchef_ingredient_action_uninstall', $action, $ingredient, $recipe );
		return $action;
	}

	function ingredient_action_action( $ingredient, &$recipe )
	{
		switch ( $ingredient['action'] ):

		case 'deactivate_plugin':
		case 'uninstall_plugin':

			$action = array(
				'uname' => sprintf('action_%s_%s', $ingredient['action'], $ingredient['slug']),
				'action' => $ingredient['action'],
				'title' => array(
					'%s <b>%s</b> Plugin',
					$ingredient['action'] == 'deactivate_plugin' ? 'Deactivate' : 'Uninstall',
					$ingredient['slug'],
				),
				'slug' => $ingredient['slug'],
				'description' => $ingredient['description'],
				'enabled' => true,
				'checked' => true,
				'installed' => false,
				'type' => $type,
			);

			$current = false;
			$items = $this->get_plugins();
			if ( !empty( $items[ $ingredient['slug'] ] ) )
			{
				$current = $items[ $ingredient['slug'] ];
				$action['title'][2] = $current['name'];
			}

			$installed = $current && ( $current['is_active'] || $ingredient['action'] == 'uninstall_plugin' );

			if ( !$installed )
			{
				$action['enabled'] = false;
				$action['checked'] = false;
				$action['installed'] = true;
			}

		break;

		case 'add_page':

			$action = array(
				'uname' => 'action_add_page_'.md5( $ingredient['name'] ),
				'action' => 'add_page',
				'title' => array(
					'Add <b>%s</b> Page',
					esc_html( $ingredient['name'] ),
				),
				'slug' => $ingredient['slug'],
				'enabled' => true,
				'checked' => true,
				'installed' => false,
				'name' => $ingredient['name'],
				'content' => $ingredient['content'],
				'placement' => $ingredient['placement'],
				'override' => $ingredient['override'],
				'type' => $type,
				'description' => $ingredient['description'],
			);
			if ( !empty( $recipe['actions_made'][ $action['uname'] ] ) )
			{
				$action['enabled'] = false;
				$action['installed'] = true;
				break;
			}

			$post = $this->find_page_by_slug_or_title( $action['slug'], $action['name'] );
			if ( $post )
			{
				if ( !$action['override'] )
				{
					$action['enabled'] = false;
					$action['installed'] = true;
				}
				elseif ( $action['override'] !== 'rename' )
				{
					$action['notices'][] = array(
						'warning',
						'The <a href="%s" target="_blank">Page</a> will be replaced.',
						esc_attr( get_permalink( $post->ID ) ),
					);
				}
			}

		break;

		case 'add_menu_item':

			$action = array(
				'uname' => 'action_add_menu_item_'.md5( $ingredient['link'] ),
				'action' => 'add_menu_item',
				'title' => array(
					'Add <b>%s</b> Menu Item',
					esc_html( $ingredient['name'] ),
				),
				'enabled' => true,
				'checked' => true,
				'installed' => false,
				'name' => $ingredient['name'],
				'url' => $ingredient['url'],
				'link' => $ingredient['link'],
				'placement' => $ingredient['placement'],
				'type' => $type,
			);

			if ( !empty( $recipe['actions_made'][ $action['uname'] ] ) )
			{
				$action['enabled'] = false;
				$action['installed'] = true;
				break;
			}

			$locations = get_nav_menu_locations();
			if ( empty( $locations[ $action['placement'] ] ) )
			{
				$action['enabled'] = false;
				$action['checked'] = false;
				$action['notices'][] = array(
					'error',
					__('Menu not exists'),
				);
			}
			elseif ( $this->has_menu_item( $action['link'], $action['placement'] ) )
			{
				$action['instaled'] = true;
				$action['enabled'] = false;
			}

		break;

		case 'add_user':

			$action = array(
				'uname' => 'user_'.$ingredient['login'],
				'action' => 'add_user',
				'title' => array($ingredient['title']),
				'description' => $ingredient['description'],
				'enabled' => true,
				'checked' => true,
				'instaled' => false,
				'type' => $type,
				'login' => $ingredient['login'],
				'email' => $ingredient['email'],
				'role' => $ingredient['role'],
			);

			if ( !empty( $recipe['actions_made'][ $action['uname'] ] ) )
			{
				$action['enabled'] = false;
				$action['installed'] = true;
				break;
			}

		break;

		case 'eval':

			$code = trim( implode( "\n", $ingredient['code'] ) );

			$action = array(
				'uname' => sprintf( 'eval_%d_%s', strlen($code), md5($code) ),
				'action' => 'eval',
				'title' => 'Run Snippet',
				'description' => $ingredient['description'],
				'code' => $code,
				'enabled' => !empty($code),
				'checked' => !empty($code),
				'spoiler' => $code,
				'type' => 'eval',
			);

			if ( !$code )
			{
				$action['notices'][] = array( 'warning', __('Empty code', 'wpchef') );
			}

			break;

		case 'alert':

			if ( empty( $ingredient['content'] ) )
				return false;

			$action = array(
				'uname' => 'action_alert_'.md5($ingredient['content']),
				'action' => 'alert',
				'title' => 'Show Alert',
				'enabled' => true,
				'checked' => true,
				'batch' => true,
				'description' => '',
				'type' => 'alert',

				'alert_type' => $ingredient['alert_type'],
				'content' => $ingredient['content'],
			);

			break;

		default:

			$action = array(
				'uname' => 'action_'.$ingredient['action'],
				'action' => 'action',
				'title' => 'Unknown Action',
				'description' => $ingredient['description'],
				'enabled' => false,
				'checked' => false,
				'notices' => array(
					array( 'warning', 'This action is not compatible with the instaled WPChef version. Please update the WPChef plugin to fix it.' ),
				),
				'type' => $type,
			);

			break;
		endswitch;

		return apply_filters( 'wpchef_ingredient_action', $action, $ingredient, $recipe );
	}

	function forgotten_uninstall_actions( &$recipe )
	{
		$recipes_actions = array();

		foreach( $recipe['actions_made'] as $uname => $v )
		{
			if ( !preg_match('/^([a-z]+)_(.+)$/', $uname, $m) || isset($recipe['all_actions'][ $uname ]) )
				continue;

			$ingredient = array(
				'type' => $m[1],
			);

			switch( $ingredient['type'] )
			{
				case 'plugin':
				case 'theme':
					$ingredient['slug'] = $m[2];
					break;

				case 'option':
					$ingredient['option'] = $m[2];
					break;

				case 'recipe':
					$ingredient['recipe'] = $m[2];
					break;

				default:
					continue;
			}

			$action = $this->ingredient_action_uninstall( $ingredient, $recipe );

			if ( !$action['uname'] || !$action['enabled'] )
				continue;

			$recipe['actions_enabled'][ $action['uname'] ] = true;
			$recipe['actions'][ $action['uname'] ] = $action;
			$recipe['all_actions'][ $action['uname'] ] = &$recipe['actions'][ $action['uname'] ];
		}

		return true;
	}

	function package_still_need( $slug, $type, $exclude_recipe = '' )
	{
		$recipes = (array)get_option( 'wpchef_installed_recipes', array() );

		foreach ( $recipes as $recipe_slug => $tmp )
		{
			if ( $recipe_slug == $exclude_recipe )
				continue;

			$recipe = $this->get_recipe( $recipe_slug, true );
			if ( !$recipe || isset( $recipe['actions_canceled'][ "{$type}_$slug" ] ) )
				continue;

			foreach ( $recipe['ingredients'] as $ingredient )
			{
				$ingredient = wpchef_ingredient::instance()->normalize( $ingredient );

				if ( $ingredient['type'] == $type && $ingredient['slug'] == $slug )
					return array( 'recipe' => $recipe, 'ingredient' => $ingredient );
			}
		}

		return false;
	}

	function page_apply()
	{
		if ( !empty( $_REQUEST['child_success'] ) )
			delete_transient( 'wpchef_fetch_recipe_'.$_REQUEST['child_success'] );

		$ajax = defined('DOING_AJAX') && DOING_AJAX;

		$uninstall =
			$ajax ?
			isset( $_REQUEST['deactivate'] ) :
			@$_GET['page'] == 'recipe-deactivate';

		$slug = (string)@$_REQUEST['recipe'];
		$recipe = $this->get_recipe( $slug, true );

		if ( !$recipe )
		{
			?><div class="error"><p>Invalid recipe</p></div><?php
			if ( $ajax ) exit;
			return;
		}

		if ( $recipe['wpchef_id'] && !empty( $_REQUEST['upload_sec']) && check_ajax_referer( 'wpchef_upload_'.$recipe['slug'], 'upload_sec' ) )
		{
			$recipe = $this->fetch_recipe( $recipe['wpchef_id'], false );
			$slug = $recipe['slug'];
			if ( !$recipe || !$recipe['allow_access'] )
			{
				?><div class="notice notice-error inline"><p>Can't fetch recipe from repository</p></div><?php
				if ( $ajax ) exit;
				return;
			}
			$this->recipe_save( $recipe, $slug, true );
			$this->recipe_things( $recipe, $slug );
		}

		if ( isset($_GET['uploaded']) )
		{
			?><div class="updated"><p>Recipe uploaded successfully.</p></div><?php
		}

		if ( !$uninstall && $recipe['phpversion'] && version_compare( $recipe['phpversion'], phpversion(), '>') )
		{
			?><div class="error">
				<p>This recipe requires at least PHP <?=esc_html($recipe['phpversion'])?> version. Your current PHP version is <?=phpversion()?>. Please contact your hosting provider to address this.</p>
				<p><a href="<?=$this->url_list?>">Return to Recipes page</a></p>
			</div><?php
			if ( $ajax ) exit;
			return;
		}

		if ( $this->wpchef_me() )
			$this->recipe->access_token = get_option( 'wpchef_access_token' );

		$this->recipe_actions( $recipe, $uninstall );

		if ( $uninstall )
		{
			$title_action = 'deactivated';
		}
		elseif ( $recipe['installed'] )
		{
			$title_action = 'applied';
		}
		else
		{
			$title_action = 'activated';
		}

		$this->debug = true;
		$this->log( $recipe );

		if ( $ajax )
		{
			include 'inc/apply-inline.tpl.php';
			exit;
		}

		include 'inc/apply.tpl.php';
	}

	function ajax_fs_credentials()
	{
		$credentials = stripslashes_deep( $_POST );
		unset( $credentials['action'] );

		if ( WP_Filesystem( $credentials, WP_CONTENT_DIR ) )
			$this->json_success( $credentials );

		else
			$this->json_error();
	}

	function recipe_step_check_token( $token, $sec, $recipe )
	{
		if ( empty( $token ) || empty( $sec ) )
			return false;

		$session = get_transient('wpchef_apply_token_'.$token);
		if ( empty($session) || empty($session['user_id']) || empty($session['sec']) || $session['sec'] != $sec || $session['recipe'] != $recipe )
			return false;

		wp_set_current_user( $session['user_id'] );
		return true;
	}

	function recipe_step_set_token( $token, $sec, $recipe )
	{
		if ( $this->recipe_step_check_token( $token, $sec, $recipe ) )
			return $token;

		$token = wp_generate_password( 8, false );
		$session = array(
			'user_id' => get_current_user_id(),
			'sec' => $sec,
			'recipe' => $recipe,
		);
		set_transient( 'wpchef_apply_token_'.$token, $session, HOUR_IN_SECONDS );
		return $token;
	}

	function recipe_steps()
	{
		$slug = $_POST['recipe'];

		if ( !wp_verify_nonce(@$_POST['sec'], 'recipe_steps_'.$slug) && !$this->recipe_step_check_token( @$_POST['token'], @$_POST['sec'], $slug ) )
			$this->json_error( 'Session timeout.' );

		if ( !current_user_can('install_plugins') )
			$this->json_error( 'Access denied.' );

		$uninstall = ($_POST['mode'] == 'uninstall');
		$recipe = $this->get_recipe( $slug, true );

		if ( $recipe )
			$this->recipe_actions( $recipe, $uninstall );

		if ( !$recipe )
			$this->json_error( 'Invalid recipe.' );

		$this->credentials = stripslashes_deep( @$_POST['credentials'] );

		$results = array();
		foreach ( $_POST['steps'] as $step )
		{
			if ( $step['step'] == 'complete' )
			{
				if ( $uninstall )
					$this->recipe_free( $recipe );
				else
				{
					$this->recipe_complete( $recipe, explode(',', (string)@$step['params']['checked_actions'] ) );

					if ( !empty($step['params']['autoupdate']) )
						$this->autoupdate_add( $slug, $step['params']['autoupdate'] );
					else
						$this->autoupdate_remove( $slug );
				}

				if ( !empty($_POST['token']) )
					delete_transient( 'wpchef_apply_token_'.$_POST['token'] );

				 $response = $this->response_success();
				 $response['success_url'] = admin_url('admin.php?page=recipes');
				 $results[] = $response;
				continue;
			}


			if ( empty($recipe['children'][ $step['child'] ]['actions'][ $step['step'] ]) )
			{
				$this->log( $step, array_keys($recipe['children']), $recipe['children'][ $step['child'] ] );
				$results[] = $this->response_error( 'Invalid ingredient.' );
				continue;
			}

			$action = $recipe['children'][ $step['child'] ]['actions'][ $step['step'] ];
			set_time_limit( 200 );

			if ( isset($step['params']) )
				$action['params'] = $step['params'];

			if ( !empty($step['uninstall'] ) )
			{
				$results[] = $this->recipe_step_back( $recipe, $action );
				continue;
			}

			ob_start();
			$rez = $this->do_action( $action, $recipe, $log );

			$log = implode( "\n<br />", $log );
			$log .= ob_get_clean();

			if ( $rez === true )
			{
				$this->recipe_save_action( $recipe, $action );
				$response = $this->response_success( $log );
				$response['token'] = $this->recipe_step_set_token( @$_POST['token'], @$_POST['sec'], $slug );
			}

			else
				$response = $this->response_error( $rez, $log );

			if ( !empty($action['alerts']) )
				$response['alerts'] = $action['alerts'];

			$results[] = $response;
		}

		$this->json_response( $results );
	}

	function recipe_step_back( $recipe, $action )
	{
		set_time_limit( 200 );
		ob_start();
		$rez = $this->do_action( $action, $recipe, $log );

		$log = implode( "\n<br />", $log );
		$log .= ob_get_clean();

		if ( $rez === true )
		{
			$this->recipe_delete_action( $recipe, $action );
			$response = $this->response_success( $log );
			$response['token'] = $this->recipe_step_set_token( @$_POST['token'], @$_POST['sec'], $recipe['slug'] );

			return $response;
		}

		else
			return $this->response_error( $rez, $log );
	}

	//Save done action to db
	function recipe_save_action( $recipe, $action )
	{
		$slug = $recipe['slug'];

		if ( $recipe['wpchef_id'] && $action['recipe'] != $slug )
		{
			$children = (array)get_option( 'wpchef_recipe_children_'.$recipe['wpchef_id'], array() );
			$child = $recipe['children'][ $action['recipe'] ];

			if ( @$children[ $child['wpchef_id'] ] != $child['version'] )
			{
				$children[ $child['wpchef_id'] ] = $child['version'];
				update_option( 'wpchef_recipe_children_'.$recipe['wpchef_id'], $children );
			}
		}

		if ( $action['action'] == 'eval' || $action['action'] == 'alert')
			return;

		$recipes = (array)get_option( 'wpchef_installed_recipes', array() );
		$recipes[ $slug ][ $action['uname'] ] = $action;
		unset( $recipes[ $slug ]['canceled'][ $action['uname'] ] );

		update_option( 'wpchef_installed_recipes', $recipes );

		if ( $action['action'] == 'option' )
			return;

		$all_actions = (array)get_option( 'wpchef_actions_made', array() );
		$all_actions[ $action['uname'] ] = $slug;

		update_option( 'wpchef_actions_made', $all_actions);

		do_action( 'wpchef_save_action', $action, $recipe );
	}

	//Final conditioning
	function recipe_complete( $recipe, $checked_actions = null )
	{
		//TODO save children/disabled children

		//change package owner if not deactivated
		foreach ( $recipe['actions'] as $action )
			if ( !empty( $action['other'] ) )
			{
				$this->log( 'Other', $action );
				$action = $this->ingredient_action( $action['other']['ingredient'], $action['other']['recipe'] );
				$this->recipe_save_action( $action['other']['recipe'], $action );
			}

		$recipes = (array)get_option( 'wpchef_installed_recipes', array() );
		$all_actions = (array)get_option( 'wpchef_actions_made', array() );

		$slug = $recipe['slug'];

		$recipes[ $slug ]['version'] = $recipe['version'];

		//var_dump( $recipe, $recipes ); exit;
		//remove all old records
		foreach ( $recipe['actions_made'] as $action => $v )
			if ( empty($recipe['all_actions'][ $action ]) || !empty($recipe['all_actions'][ $action ]['uninstall']) )
				unset( $recipes[ $slug ][ $action ] );

		//Save canceled action
		if ( is_array( $checked_actions ) )
		{
			$recipes[ $slug ]['canceled'] = array();

			//collect full-canceled children
			$canceled_children = array();
			if ( !empty( $recipe['default_actions'] ) )
			foreach ( $recipe['default_actions'] as $action )
			{
				if ( $action['recipe'] == $slug )
					continue;

				//child canceled by default
				if ( !isset( $canceled_children[ $action['recipe'] ] ) )
					$canceled_children[ $action['recipe'] ] = true;

				if ( in_array( $action['uname'], $checked_actions ) )
					$canceled_children[ $action['recipe'] ] = false;
			}
			foreach ( $canceled_children as $uname => $canceled )
				if ( $canceled )
					$recipes[ $slug ]['canceled'][ 'recipe_'.$uname ] = true;

			//collect sef-canceled action
			if ( !empty( $recipe['default_actions'] ) )
			foreach ( $recipe['default_actions'] as $action )
			{
				if ( !empty( $canceled_children[ $action['recipe'] ] ) )
					continue;

				if ( !in_array( $action['uname'], $checked_actions ) )
					$recipes[ $slug ]['canceled'][ $action['uname'] ] = true;
			}
		}

		update_option( 'wpchef_installed_recipes', $recipes );
		delete_option( 'recipe_autoupdate_alert_'.$slug );
	}

	//Reomove action from db for recipe.
	function recipe_delete_action( $recipe, $action )
	{
		$all_actions = (array)get_option( 'wpchef_actions_made', array() );
		$recipes = (array)get_option( 'wpchef_installed_recipes', array() );

		$slug = $recipe['slug'];
		$uname = $action['uname'];

		unset( $all_actions[ $uname ] );
		unset( $recipes[ $slug ][ $uname ] );

		update_option( 'wpchef_actions_made', $all_actions);
		update_option( 'wpchef_installed_recipes', $recipes );

		do_action( 'wpchef_delete_action', $action, $recipe );
	}

	//Remove all actions for recipe
	function recipe_free( $recipe )
	{
		//change package owner if not deactivated
		if ( !empty( $recipe['actions'] ) )
		foreach ( $recipe['actions'] as $action )
			if ( !empty( $action['other'] ) )
			{
				$action_new = $this->ingredient_action( $action['other']['ingredient'], $action['other']['recipe'] );
				$this->recipe_save_action( $action['other']['recipe'], $action_new );
			}
		$this->log( 'Check other end' );

		$slug = $recipe['slug'];

		$recipes = (array)get_option( 'wpchef_installed_recipes', array() );

		unset( $recipes[ $recipe['slug'] ] );
		update_option( 'wpchef_installed_recipes', $recipes );

		$all_actions = (array)get_option( 'wpchef_actions_made', array() );
		foreach( $all_actions as $uname => $slug )
			if ( $slug == $recipe['slug'] )
				unset( $all_actions[ $uname ] );
		update_option( 'wpchef_actions_made', $all_actions );

		if ( $recipe['wpchef_id'] )
			delete_option( 'wpchef_recipe_children_'.$recipe['wpchef_id'] );

		$this->autoupdate_remove( $recipe['slug'] );
	}

	function do_action( &$action, $recipe, &$log = null )
	{
		require_once ABSPATH.'/wp-admin/includes/admin.php';
		$log = array();

		$res = null;
		do_action_ref_array( 'wpchef_do_action', array( &$res, &$action, &$recipe, &$log ) );
		$this->log( $res, $log );

		if ( $res === null )
		switch( $action['action'] )
		{
			case 'plugin_install':
				$res = $this->plugin_install( $action['slug'], $log );

				break;

			case 'plugin_activate':
				$res = $this->plugin_activate( $action['slug'] );

				break;

			case 'deactivate_plugin':
			case 'plugin_uninstall':
				$res = $this->plugin_uninstall( $action['slug'], $log );

				break;

			case 'uninstall_plugin':
				$res = $this->plugin_remove( $action['slug'], $log );

				break;

			case 'theme_install':
				$res = $this->theme_install( $action['slug'], $log );

				break;

			case 'theme_activate':
				$res = $this->theme_activate( $action['slug'] );

				break;

			case 'theme_uninstall':
				$res = $this->theme_uninstall( $action['slug'] );

				break;

			case 'option':
				if ( !empty($action['params']) && is_array( $action['params'] ) && array_key_exists('value', $action['params']) )
					$value = wpchef_ingredient::instance()->value_decode( $action['params']['value'] );

				else
					$value = $action['value'];

				$res = $this->option_install( $action['option'], $value, $recipe['slug'] );

				break;

			case 'option_uninstall':
				$res = $this->option_uninstall( $action['option'], $recipe['slug'], $log );

				break;

			case 'eval':
				$res = $this->action_eval( $action['code'], $log );

				break;

			case 'add_page':
				$res = $this->action_add_page( $action, $log );

				break;

			case 'add_menu_item':
				$res = $this->action_menu_item( $action, $log );

				break;

			case 'add_user':
				$res = $this->action_add_user( $action, $log );

				break;

			case 'alert':
				$res = $this->action_alert( $action );

				break;

			/*
			case 'trash_page':
				$res = $this->action_trash_page( $action, $log );

				break;

			case 'trash_menu_item':
				$res = $this->action_trash_menu_item( $action, $log );

				break;
			*/
			default:
				return 'Invalid action';
		}

		$this->log( $action, $res );

		return $res;
	}

	public function find_package( $dir, $type )
	{
		if ( !is_dir($dir) )
		{
			$this->log( "$dir not found" );
			return false;
		}

		$Type = ucfirst( $type );
		$default_headers = array(
			'Name' => "$Type Name",
			'Version' => 'Version',
			'Description' => 'Description',
			$Type.'URI' => $Type.' URI',
			'Author' => 'Author',
			'AuthorURI' => 'Author URI',
		);

		if ( $type == 'plugin' )
			foreach ( scandir( $dir ) as $file )
			{
				if ( substr($file, -4) != '.php' )
					continue;

				$info = get_file_data( "$dir/$file", $default_headers );
				if ( $info && $info['Name'] )
				{
					$info['pluginfile'] = $file;
					break;
				}
			}
		else
			$info = get_file_data( "$dir/style.css", $default_headers );

		if ( !empty($info) && $info['Name'] )
			return $info;

		$this->log( 'Info is empty', $info );
		return false;
	}

	function plugin_install( $slug, &$log = array() )
	{
		$plugins = $this->get_plugins();
		set_time_limit( 300 );

		do_action( 'wpchef_install', 'plugin', $slug );

		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

		if ( !WP_Filesystem( $this->credentials, WP_PLUGIN_DIR ) )
			return 'Please specify your FTP login information to proceed.';

		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$upgrader->init();
		$upgrader->fs_connect( array(WP_PLUGIN_DIR) );

		$url = "https://downloads.wordpress.org/plugin/$slug.zip";
		$download = $upgrader->download_package( $url );
		if ( is_wp_error( $download ) )
			return 'Can\'t download plugin';

		$dir = $upgrader->unpack_package( $download, false );
		if ( is_wp_error($dir) )
		{
			$this->log( $download, $dir );
			return 'Invalid Package';
		}

		$info = $this->find_package( "$dir/$slug", 'plugin' );
		if ( !$info )
			return 'Plugin not found';

		$current = !empty($plugins[ $slug ]) ? $plugins[ $slug ] : false;

		if ( $current )
			$this->plugin_uninstall( $slug, $log );

		$rez = $upgrader->install_package( array(
			'source' => $dir,
			'destination' => WP_PLUGIN_DIR,
			'clear_destination' => true,
			'abort_if_destination_exists' => false,
			'clear_working' => true,
			'hook_extra' => array(
				'type' => 'plugin',
				'action' => 'install',
			)
		) );

		//$rez = $upgrader->install( $download );

		$log = array_merge( $log, $skin->get_upgrade_messages() );
		@unlink( $download );

		if ( !$rez || is_wp_error($rez) )
		{
			$this->log( 'plugin_install_error', $rez);
			if ( is_wp_error($rez) )
				$log[] = $rez->get_error_message();

			return 'Can\'t install plugin';
		}

		return $this->plugin_activate( $slug );
	}

	function plugin_activate( $slug )
	{
		$plugins = $this->get_plugins( false );
		$this->log( $slug, $plugins[ $slug ] );

		if ( empty( $plugins[ $slug ] ) )
			return 'Can\'t activate plugin. It\'s not installed.';

		$plugin = $plugins[$slug]['slug'];

		if ( is_plugin_active( $plugin ) )
			return true;

		$res = activate_plugins( $plugin );
		if ( !$res || is_wp_error( $res ) )
			return 'Can\'t activate plugin.';

		return true;
	}

	public function plugin_uninstall( $slug, &$log = array() )
	{
		$plugins = $this->get_plugins();

		if ( empty($plugins[ $slug ]) )
		{
			$log[] = 'Plugin not installed';
			return true;
		}

		set_time_limit( 200 );

		$plugin = $plugins[ $slug ][ 'slug' ];

		if ( is_plugin_active( $plugin ) )
			deactivate_plugins( $plugin );

		return true;
	}

	function plugin_remove( $slug, &$log )
	{
		$plugins = $this->get_plugins( false );

		if ( empty($plugins[ $slug ]) )
		{
			$log[] = 'Plugin not installed';
			return true;
		}

		if ( $plugins[ $slug ]['is_active'] )
			$this->plugin_uninstall( $slug, $log );

		do_action( 'wpchef_install', 'plugin', $slug );

		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		if ( $this->credentials )
		{
			if ( !WP_Filesystem( $this->credentials, WP_PLUGIN_DIR ) )
				return 'Please specify your FTP login information to proceed.';

			add_filter( 'request_filesystem_credentials', array( $this, 'request_filesystem_credentials' ) );
		}

		$plugin = $plugins[ $slug ][ 'slug' ];

		try {
			$log[] = delete_plugins( array($plugin) );
			$log[] = $plugin;
		}
		catch ( Exception $e ) {
			$log[] = $e->getMessage();
			return 'Can\'t remove plugin';
		}
		//var_dump( $this->credentials ); exit;

		return true;
	}

	function request_filesystem_credentials( $credentials )
	{
		return $this->credentials;
	}

	function theme_install( $slug, &$log = array() )
	{
		$themes = $this->get_themes();
		set_time_limit( 200 );

		do_action( 'wpchef_install', 'theme', $slug );

		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

		if ( !WP_Filesystem( $this->credentials, WP_CONTENT_DIR ) )
			return 'Please specify your FTP login information to proceed.';

		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$upgrader->init();

		$url = "https://downloads.wordpress.org/theme/$slug.zip";
		$download = $upgrader->download_package( $url );
		if ( is_wp_error( $download ) )
			return 'Can\'t download theme';

		$dir = $upgrader->unpack_package( $download, false );
		if ( is_wp_error($dir) )
			return 'Invalid Package';

		$info = $this->find_package( "$dir/$slug", 'theme' );
		if ( !$info )
			return 'Theme not found';

		$rez = $upgrader->install_package( array(
			'source' => $dir,
			'destination' => get_theme_root(),
			'clear_destination' => true,
			'abort_if_destination_exists' => false,
			'clear_working' => true,
			'hook_extra' => array(
				'theme' => $theme,
				'type' => 'theme',
				'action' => 'update',
			),
		) );

		$log = array_merge( $log, $skin->get_upgrade_messages() );
		@unlink( $download );

		if ( !$rez || is_wp_error($rez) )
			return 'Can\'t update theme';

		wp_clean_themes_cache();
		return $this->theme_activate( $slug );
	}

	function theme_activate( $slug, $save_history=true )
	{
		$current = wp_get_theme()->get_stylesheet();
		if ( $current == $slug )
			return true;

		try {
			switch_theme( $slug );

			if ( !validate_current_theme() )
			{
				switch_theme( $current );
				return 'Invalid Theme';
			}
		}
		catch ( Exception $e )
		{
			$this->log( $e->getMessage() );
			return 'Can\'t activate theme.';
		}

		if ( $save_history )
			$this->theme_history_push( $current );

		return true;
	}

	public function theme_uninstall( $slug, &$log = array() )
	{
		$theme = wp_get_theme( $slug );

		if ( !$this->theme_history_back( $theme ) )
			return 'Can\'t remove active theme';

		if ( !$theme->exists() )
		{
			$log[] = 'Theme not installed';
			return true;
		}

		//set_time_limit( 200 );
		//do_action( 'wpchef_install', 'theme', $slug );

		return true;
	}

	function theme_history()
	{
		$history = get_option('wpchef_themes_history');

		if ( !is_array( $history ) )
		{
			$history = array();
			add_option( 'wpchef_themes_history', $history, '', 'no' );
		}

		return $history;
	}

	function theme_history_push( $slug )
	{
		$history = $this->theme_history();

		$k = array_search( $slug, $history, true );
		if ( $k !== false )
		{
			unset( $history[$k] );
			$history = array_values( $history );
		}

		$history[] = $slug;
		update_option( 'wpchef_themes_history', $history );
	}

	function theme_history_back( $old )
	{
		$history = $this->theme_history();

		//Clan depended themes
		foreach ( $history as $id => $slug )
		{
			$theme = $slug ? wp_get_theme( $slug ) : false;

			if ( !$theme || !$theme->exists() || $this->theme_require( $theme, $old )  )
				unset( $history[ $id ] );
		}
		update_option( 'wpchef_themes_history', array_values($history) );

		if ( !$old->exists() )
			return true;

		array_unshift( $history, WP_DEFAULT_THEME );
		array_unshift( $history, WP_Theme::get_core_default_theme() );
		$history[] = get_option('stylesheet');

		while ( $history )
		{
			$last = array_pop( $history );
			$this->log( 'Try restore', $last );

			$theme = wp_get_theme( $last );
			if ( !$theme->exists() || $this->theme_require( $theme, $old )  )
				continue;

			if ( $this->theme_activate( $last, false ) === true )
			{
				$this->log( 'Theme restored.' );
				return true;
			}
		}

		return false;
	}

	function theme_require( $new, $old )
	{
		$require =
			   $new->get_stylesheet() == $old->get_stylesheet()
			|| $new->get_template() == $old->get_stylesheet();

		return $require;
	}

	function option_get_value( $option, &$error = false )
	{
		if ( preg_match( '/^(.+?)((->|\[).+)$/', $option, $m ) )
		{
			$option = $m[1];
			$path = $m[2];
		}
		else
			$path = '';

		$value = get_option( $option, null );

		if ( !$this->nested_option( $value, $path, null, $current_value ) )
			$error = true;

		return $current_value;
	}

	function option_set( $option, $new_value )
	{
		//check for nested items
		if ( preg_match( '/^(.+?)((->|\[).+)$/', $option, $m ) )
		{
			$option = $m[1];
			$path = $m[2];
		}
		else
			$path = '';

		$value = get_option( $option, null );

		if ( !$this->nested_option( $value, $path, $new_value ) )
			return false;

		if ( $value === null )
			delete_option( $option );

		else
			update_option( $option, $value );

		return true;
	}

	//reqursion for nested arrays and objects in options
	function nested_option( &$value, $path, $new_value, &$parent_value = null )
	{
		if ( !$path )
		{
			$parent_value = $value;
			$value = $new_value;
			return true;
		}
		elseif ( preg_match( '/^->(.+?)((->|\[).+)?$/', $path, $m ) )
			$type = 'object';

		elseif ( preg_match( '/^\[(.+?)\]((->|\[).+)?$/', $path, $m ) )
			$type = 'array';

		else
			return false;

		if ( !isset( $value ) )
		{
			if ( $new_value === null )
				return true;

			$value = $type == 'object' ? (object)array() : array();
		}

		if ( gettype($value) != $type )
			return false;

		$item = $m[1];
		$sub_path = isset($m[2]) ? $m[2] : '';

		if ( $new_value === null && !$sub_path )
		{
			if ( $type == 'object' )
			{
				if ( isset($value->{$item}) )
					$parent_value = $value->{$item};

				unset( $value->{$item} );
			}
			else
			{
				if ( isset( $value[ $item ] ) )
					$parent_value = $value[ $item ];

				unset( $value[ $item ] );
			}

			return true;
		}

		if ( $type == 'object' )
			return $this->nested_option( $value->{$item}, $sub_path, $new_value, $parent_value );
		else
			return $this->nested_option( $value[ $item ], $sub_path, $new_value, $parent_value );
	}

	function option_is( $option, $value, &$error = false )
	{
		$current = $this->option_get_value( $option, $error );

		if ( $value === null )
			return $current === null;

		return $current == $value;
	}

	function option_install( $option, $value, $recipe_slug )
	{
		$all_actions = (array)get_option( 'wpchef_actions_made', array() );
		$action = 'option_'.$option;

		if ( empty( $all_actions[ $action ] ) || !$this->option_is( $option, end( $all_actions[ $action ] ) ) )
		{
			$all_actions[ $action ] = array();

			if ( !$this->option_is( $option, null ) )
				$all_actions[ $action ][] = $this->option_get_value( $option );
		}

		unset( $all_actions[ $action ][ $recipe_slug ] );
		$all_actions[ $action ][ $recipe_slug ] = $value;

		if ( !$this->option_set( $option, $value ) )
			return __('Invalid option type','wpchef');

		update_option( 'wpchef_actions_made', $all_actions );
		return true;
	}

	function option_uninstall( $option, $recipe_slug, &$log )
	{
		$all_actions = (array)get_option( 'wpchef_actions_made', array() );
		$action = 'option_'.$option;

		if ( empty( $all_actions[ $action ] ) )
		{
			$log[] = 'Option changed manually - reset history.';
			return true;
		}

		settype( $all_actions[ $action ], 'array' );

		$last = end( $all_actions[ $action ] );
		$last_slug = key( $all_actions[ $action ] );

		if ( $last_slug == $recipe_slug )
		{
			if ( !$this->option_is( $option, $last ) )
			{
				unset( $all_actions[ $action ] );
				$log[] = 'Option changed manually - reset history.';
			}

			elseif ( count( $all_actions[ $action ] ) < 2 )
			{
				unset( $all_actions[ $action ] );
				$this->option_set( $option, null );
				$log[] = 'Previous value not exists - delete option.';
			}

			//restore previous value
			else
			{
				unset( $all_actions[ $action ][ $recipe_slug ] );
				$last = end( $all_actions[ $action ] );
				$this->option_set( $option, $last );

				if ( !is_null($last) )
					$log[] = 'Restored previous value '.json_encode( $last );
				else
					$log[] = 'Delete option (previous state).';
			}
		}
		else
		{
			unset( $all_actions[ $action ][ $recipe_slug ] );
			$log[] = "Option changed by \"$last_slug\" recipe.";
		}

		update_option( 'wpchef_actions_made', $all_actions);

		return true;
	}

	function action_eval( $code, &$log )
	{
		$rez = eval( $code );

		if ( $rez === false )
			return __('Invalid snippet code','wpchef');

		return true;
	}

	function action_add_page( &$action, &$log )
	{
		$data = array(
			'post_title' => $action['name'],
			'post_content' => $action['content'],
			'post_status' => 'publish',
			'post_type' => 'page',
		);

		$post = $this->find_page_by_slug_or_title( $action['slug'], $action['name'] );
		if ( $post )
		{
			if ( !$action['override'] )
				return 'Already exists';

			if ( $action['override'] !== 'rename' )
				$data['ID'] = $post->ID;
		}

		if ( !empty( $data['ID'] ) )
			$log[] = 'Page exists. Will be updated';

		$res = wp_insert_post( $data );

		if ( is_wp_error( $res ) || !$res )
		{
			if ( $res )
				$log[] = $res->get_error_message();

			return 'Can\'t create page';
		}

		if ( $action['slug'] )
		{
			$slug = wp_unique_post_slug( $action['slug'], $res, 'publish', 'page', 0 );
			wp_update_post(array(
				'ID' => $res,
				'post_name' => $slug,
			));
		}

		if ( !$action['placement'] )
			return true;

		require_once( ABSPATH . 'wp-admin/includes/nav-menu.php' );
		$locations = get_nav_menu_locations();

		if ( empty( $locations[ $action['placement'] ] ) )
		{
			$log[] = 'Menu not found';
			return true;
		}

		$menu_id = $locations[ $action['placement'] ];
		$menu_item_db_id = wp_update_nav_menu_item($menu_id, 0, array(
			'menu-item-title' => $title,
			'menu-item-object' => 'page',
			'menu-item-object-id' => $res,
			'menu-item-type' => 'post_type',
			'menu-item-status' => 'publish',
			'menu-item-url' => get_permalink( $res ),
		));

		return true;
	}

	function action_trash_page( &$action, &$log )
	{
	/*
		if ( $action['current'] )
		{
			require_once( ABSPATH . 'wp-includes/nav-menu.php' );
			_wp_delete_post_menu_item( $action['current']['ID'] );

			wp_trash_post( $action['current']['ID'] );
		}
		else
			$log[] = 'Page not exists.';

		return true;
	*/
	}

	function action_menu_item( &$action )
	{
		require_once( ABSPATH . 'wp-admin/includes/nav-menu.php' );
		$locations = get_nav_menu_locations();

		if ( empty( $locations[ $action['placement'] ] ) )
			return 'Menu not found';

		$menu_id = $locations[ $action['placement'] ];
		$menu_item_db_id = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title' => $action['name'],
			'menu-item-url' => $action['link'],
			'menu-item-status' => 'publish',
		) );

		$action['menu_id'] = $menu_id;
		if ( is_wp_error($menu_item_db_id) )
			return __('Can\'t create menu item');

		$action['menu_item_db_id'] = $menu_item_db_id;

		return true;
	}

	function action_trash_menu_item( &$action, &$log )
	{
		/*
		wp_delete_post( $action['menu_item_db_id'], true );

		return true;
		*/
	}

	function find_page_by_slug_or_title( $slug, $title )
	{
		$args = array(
			'post_type' => 'page',
			'post_status' => 'publish,private',
			'posts_per_page' => 1,
			'no_found_rows' => true,
		);

		if ( $slug )
			$args['name'] = $slug;
		else
			$args['title'] = $title;

		$pages = new WP_Query( $args );
		if ( $pages->posts )
			return $pages->posts[0];

		return false;
	}

	function active_menu_placements()
	{
		require_once( ABSPATH . 'wp-admin/includes/nav-menu.php' );

		$locations = get_nav_menu_locations();
		$menus = get_registered_nav_menus();

		foreach ( $menus as $placement => $title )
			if ( empty( $locations[ $placement ] ) )
				unset( $menus[ $placement ] );

		return $menus;
	}

	function clean_menu_url( $url )
	{
		if ( strpos( $url, home_url() ) === 0 )
			$url = substr( $url, strlen( $home_url ) );

		if ( $url[0] == '/' && @$url[1] != '/' )
			$url = substr( $url, 1 );

		$url = untrailingslashit( $url );

		return $url;
	}

	function has_menu_item( $url, $placement )
	{
		$locations = get_nav_menu_locations();
		if ( empty( $locations[ $placement ] ) )
			return false;

		foreach ( wp_get_nav_menu_items( $locations[ $placement ] ) as $menu_item )
			if ( $this->clean_menu_url($menu_item->url) == $this->clean_menu_url( $url ) )
				return true;

		return false;
	}

	function action_add_user( &$action, &$log )
	{
		$args = array(
			'user_login' => @$action['params']['login'],
			'user_email' => @$action['params']['email'],
			'user_pass' => @$action['params']['password'],
			'role' => @$action['params']['role'],
		);

		$alert = null;
		if ( empty( $args['user_pass'] ) )
			$args['user_pass'] = wp_generate_password();

		if ( !get_role( $args['role'] ) )
			$args['role'] = 'subscriber';

		$user_id = wp_insert_user( $args );

		if ( is_wp_error( $user_id ) )
			return $user_id->get_error_message();

		if ( !$user_id )
			return 'Can\'t create user';

		if ( empty( $action['params']['password'] ) )
			$action['alerts'][] = array( 'info', sprintf('Password for user <b>%s</b>: <i>%s</i>', $args['user_login'], $args['user_pass'] ) );

		$action['user_id'] = $user_id;

		return true;
	}

	function action_alert( &$action )
	{
		$content = wp_kses_post( $action['content'] );
		if ( empty( $content ) )
			return 'Content is empty';

		$action['alerts'][] = array( $action['alert_type'], $content );
		return true;
	}

	function package_recipe( $type, $slug )
	{
		$all_actions = $this->get_option('actions_made');
		$uname = sprintf('%s_%s', $type, $slug );

		$this->log( $uname, $all_actions );

		if ( empty($all_actions[ $uname ]) )
			return false;

		return $all_actions[ $uname ];
	}

	function page_settings()
	{
		do_action('wpchef_pre_page_settings');

		if ( isset($_GET['popup']) )
			$this->oauth_popup( $_GET['popup'] );

		$me = $this->wpchef_me();

		include 'inc/settings.tpl.php';
	}

	function install_builtin_recipes()
	{
		$recipes = $this->oauth_request( 'builtin_recipes', array(), true );
		if ( !$recipes )
			return;

		foreach ( $recipes as $recipe )
			$this->recipe_save( $recipe, $recipe['slug'], true );

		add_option('wpchef_builtin_installed', 1, '', 'no');
		update_option('wpchef_builtin_installed', 1);
	}

	function oauth_popup( $sec )
	{
		if ( $sec && wp_verify_nonce($sec, 'wpchef_oauth_access_token') )
		{
			$token = (string)@$_REQUEST['access_token'];

			add_option( 'wpchef_access_token', $token, '', 'no');
			update_option( 'wpchef_access_token', $token );
			update_user_meta( get_current_user_id(), 'wpchef_auth_now', 1 );

			$this->cache_delete( 'my_recipes' );

			do_action('wpchef_updates_cron');
			?>
			<script>
				if ( window.opener )
				{
					window.opener.wpchef.me = <?=json_encode( $this->wpchef_me() )?>;

					if ( typeof window.opener.wpchef.connect_callback == 'function' )
						window.opener.wpchef.connect_callback();
					else
						window.opener.location.reload( true );

					window.opener.wpchef.connect_callback = null;
				}

				window.close();
			</script>
			<?php
			exit;
		}

		$sec = wp_create_nonce('wpchef_oauth_access_token');
		$return = admin_url('admin.php?page=recipe-settings&noheader&popup=').urlencode($sec);

		$user = wp_get_current_user();

		$url = sprintf(
			'%soauth?return=%s&email[]=%s&email[]=%s',
			$this->server, urlencode( $return ), urlencode( get_bloginfo('admin_email') ), urlencode( $user->email )
		);

		wp_redirect( $url );
		return;
	}

	function wpchef_me_warning()
	{
		if ( $this->offline || $this->wpchef_me() )
			return;
		?>
		<div class="notice notice-warning inline wpchef-me-warning">
			<p>
				<?php printf(__('%s to WPChef.org to take advantage of all features.', 'wpchef'), '<a href="javascript:window.location.reload(true)" class="wpchef_auth_only">'._x('Connect', '[Connect] to WPChef.org to take advantage of all features.', 'wpchef').'</a>' ) ?>
			</p>
		</div>
		<?php
	}

	function wpchef_me_badge()
	{
		if ( $this->offline )
			return;

		$me = $this->wpchef_me();
		if ( !$me )
			$icon = array( 'text-muted', __('This site is not connected to WPChef.org. Click to fix it.', 'wpchef') );
		elseif ( $me['admin_access'] )
			$icon = array( 'text-error', __('This site is connected to WPChef.org in full access mode', 'wpchef') );
		else
			$icon = array( 'text-success', __('This site is connected to WPChef.org in read-only mode', 'wpchef') );

		$username = $me ? $me['display_name'] : __('Not connected', 'wpchef');
		?>
		<a class="wpchef-me-badge wpchef-hint wpchef_auth_only" href="<?php echo $me ? admin_url('admin.php?page=recipe-settings') : 'javascript:window.location.reload(true)' ?>" title="<?php echo esc_attr($icon[1]) ?>"><i class="fa fa-user-circle <?php echo $icon[0] ?>"></i><?php echo esc_html( $username ) ?></a>
		<?php
	}

	function auth_now_notice()
	{
		if ( !get_user_meta( get_current_user_id(), 'wpchef_auth_now' ) )
			return;
		?>
		<div class="notice notice-success"><p>
			<?php esc_html_e('Successfully authorized at WPChef.org', 'wpchef') ?>
		</p></div>
		<?php
		delete_user_meta( get_current_user_id(), 'wpchef_auth_now' );
	}

	function clean_token()
	{
		if ( empty($_REQUEST['confirm']) || !wp_verify_nonce($_REQUEST['confirm'], 'oauth_access_token') )
			$this->json_error();

		delete_option( 'wpchef_access_token' );
		delete_option( 'wpchef_me' );
		delete_option( 'wpchef_cache' );

		do_action('wpchef_updates_cron');

		$this->json_success();
	}

	function search_recipes( $recipes, $params = array() )
	{
		return $this->oauth_request( 'search', $params, true );
	}

	function wpchef_me( $cache = true )
	{
		if ( $this->offline )
			return false;

		if ( $cache )
		{
			$me = get_option( 'wpchef_me', null );
			if ( $me !== null )
				return $me;
		}

		$me = $this->oauth_request( 'me' );

		add_option( 'wpchef_me', $me, '', 'no' );
		update_option( 'wpchef_me', $me );

		return $me;
	}

	function resolve_recipe( $url )
	{
		if ( preg_match('/^(\d+)$/', $url) )
			$recipe = $this->fetch_recipe( $url, false );

		else
		{
			$server = parse_url( $this->server );
			$server = $server['host'].$server['path'];
			$server = untrailingslashit( $server );
			$server = preg_quote( $server, '~' );

			if ( preg_match("~^https?://$server/recipe/([a-z\d_-]+)(?:/.*)?$~i", $url, $m) )
				$recipe = $this->oauth_request( 'recipe', array(
					'slug' => $m[1]
				) );

			else
				$recipe = null;
		}

		if ( $recipe )
			$info = array(
				'slug' => $recipe['slug'],
				'name' => $recipe['name'],
				'description' => $recipe['description'],
				'id' => $recipe['wpchef_id'],
				'success' => true,
				'link' => $recipe['link'],
				'author' => $recipe['author'],
				'author_uri' => $recipe['author_uri'],
			);

		else
			$info = array(
				'error' => 'Recipe not found',
			);

		return $info;
	}

	function oauth_request( $method, $data=array(), $ignore_token = false )
	{
		if ( $this->offline )
			return false;

		$this->oauth_last_error = '';
		$this->oauth_error_data = null;

		$url = "{$this->server}oauth/$method";

		$token = get_option( 'wpchef_access_token', null );
		if ( !$token && !$ignore_token )
		{
			$this->log( 'Token not exists' );
			return false;
		}

		$data = array_merge( array(
			'access_token' => $token,
			'domain' => $_SERVER['HTTP_HOST'],
			'site' => home_url(),
			'admin_url' => admin_url(),
		), $data );

		if ( !empty( $this->oauth_refresh_me ) )
			$data['refresh_me'] = true;

		$curl_options = array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
		);

		$curl = curl_init();
		curl_setopt_array( $curl, $curl_options );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );

		$result = curl_exec( $curl );
		$json = @json_decode( $result, true );

		$this->log( $url, $data, $json );

		if ( $json && !empty($json['success']) )
		{
			if ( !empty($json['me']) && is_array($json['me']) )
			{
				$this->set_option( 'me', $json['me'] );
				$this->oauth_refresh_me = false;
			}

			if ( !isset( $json['data'] ) )
				return true;

			return $json['data'];
		}

		$this->log( $result, curl_getinfo( $curl ) );

		if ( $json )
		{
			if ( !empty($json['reason']) )
				$this->oauth_last_error = @(string)$json['reason'];

			$this->oauth_error_data = @$json['data'];
		}


		return false;
	}

	function supports_features( $what=array() )
	{
		foreach ( (array)$what as $feature )
			if ( !in_array( $feature, $this->features, true ) )
				return false;

		return true;
	}
}

if ( !function_exists('esc_wpchef') )
{
	function esc_wpchef( $title )
	{
		if ( !is_array( $title ) )
			return esc_html( $title );

		$txt = array_shift( $title );

		return vsprintf( $txt, $title );
	}
}

wpchef::instance();