<?php

if ( !class_exists( 'wpchef_ingredient' ) ):

class wpchef_ingredient extends wpchef_base
{
	public $debug = false;
	protected static $instance;
	
	public $actions = array();
	
	protected function __construct(){
		parent::__construct();
		
		$this->actions = array(
			'eval' =>              __('Snippet', 'wpchef'),
			'uninstall_plugin' =>  __('Uninstall Plugin','wpchef'),
			'deactivate_plugin' => __('Deactivate Plugin','wpchef'),
			'add_page' =>          __('Add Page','wpchef'),
			'add_menu_item' =>     __('Add Menu Item','wpchef'),
			'add_user' =>          __('Add User', 'wpchef'),
			'alert' =>             __('Show Message'),
		);
		
		$this->recipe = wpchef_recipe::instance();
		$this->server = get_option( 'wpchef_server', 'https://wpchef.org/' );
	}
	
	function normalize( $origin )
	{
		$ingredient = (array)$origin;
		
		$ingredient += array(
			'type' => '',
			'action' => '',
			'type_full' => '',

			'name' => '',
			'slug' => '',
			'wpchef_id' => '',
			
			'template' => '',
			'template_name' => '',
			
			'option' => '',
			'value' => '',
			'description' => '',
			
			'code' => array(),
			'runon' => '',
			
			'title' => '',
			'valid' => true,
			
			'content' => '',
			
			'placement' => '',
			'url' => '',
			
			'link' => '',
			'link_title' => '',
			
			'author' => '',
			'author_uri' => '',
			'override' => false,
			
			'email' => '',
			'login' => '',
			'role' => '',
			
			'alert_type' => 'info',
		);
		
		$ingredient['link'] = '';
		$Type = ucfirst( $ingredient['type'] );
		
		if ( !empty( $this->chef->offline ) && $ingredient['type'] == 'recipe' )
			$ingredient['type'] = '';
		
		
		switch ( $ingredient['type'] )
		{
			case 'plugin':
			case 'theme':
				$ingredient['valid'] = $ingredient['valid'] && (bool)$ingredient['slug'];

				if ( $ingredient['slug'] )
				{
					$ingredient['link'] = "https://wordpress.org/{$ingredient['type']}s/{$ingredient['slug']}/";

					if ( !$ingredient['name'] )
						$ingredient['name'] = $ingredient['slug'];
					
					$ingredient['title'] = sprintf(
						$ingredient['type'] == 'plugin' ? 
						  __("Install Plugin: <b>%s</b>",'wpchef'):
						  __("Install Theme: <b>%s</b>",'wpchef'),
						esc_html($ingredient['name'])
					);
					
					if ( $ingredient['template'] && $ingredient['type'] == 'theme' )
					{
						if ( !$ingredient['template_name'] )
							$ingredient['template_name'] = $ingredient['template'];
						
						$ingredient['title'] .= sprintf( __(' (child of <b>%s</b>)', 'wpchef'), esc_html( $ingredient['template_name'] ) );
					}
				}
				else
					$ingredient['title'] = ( $ingredient['type'] == 'plugin' ? 
						__("Install Plugin",'wpchef') :
						__("Install Theme",'wpchef') );

				$ingredient['short_title'] = $ingredient['type'] == 'plugin' ? __("Plugin") : __("Theme");
				$ingredient['short_title_plural'] = $ingredient['type'] == 'plugin' ? __("Plugins") : __("Themes");

				if ( $ingredient['link'] )
					$ingredient['link_title'] = $ingredient['type'] == 'plugin' ? 
						__("Plugin",'wpchef') :
						__("Theme",'wpchef');
				
				break;
			
			case 'recipe':
				$ingredient['wpchef_id'] = (int)$ingredient['wpchef_id'];
				
				$recipe = apply_filters( 'wpchef_the_recipe', null, $ingredient['wpchef_id'] );
				
				if ( $recipe )
				{
					$ingredient['name'] = $recipe['name'];
				
					$ingredient['title'] = sprintf(
						__("Include Recipe: <b>%s</b>",'wpchef'),
						esc_html( $ingredient['name'] )
					);
					
					$ingredient['link'] = $recipe['link'];
					$ingredient['link_title'] = __('Recipe', 'wpchef');
				}
				else
				{
					$ingredient['title'] = __('Include Recipe','wpchef');
					$ingredient['name'] = '#'.$ingredient['wpchef_id'];
					$ingredient['valid'] = false;
				}

				$ingredient['short_title'] = __("Recipe", 'wpchef');
				$ingredient['short_title_plural'] = __("Recipes", 'wpchef');

				break;
			
			case 'option':
				$ingredient['valid'] = $ingredient['valid'] && (bool)$ingredient['option'];
				
				if ( is_string( $ingredient['value'] ) )
					$val = '"'.$ingredient['value'].'"';
				else
				{
					$params = 0;
					if ( defined('JSON_UNESCAPED_SLASHES') )
						$params = $params | JSON_UNESCAPED_SLASHES;
					if ( defined('JSON_UNESCAPED_UNICODE') )
						$params = $params | JSON_UNESCAPED_UNICODE;
					$val = json_encode( $ingredient['value'], $params );
				}
				//$ingredient['value'] = $this->value_encode( $ingredient['value'] );
				//$val = is_string( $ingredient['value'] ) ? $ingredient['value'] : json_encode( $ingredient['value'] );

				if ( $ingredient['option'] )
					$ingredient['title'] = sprintf(
						__("Set Option <b>%s</b> to Value <b>%s</b>",'wpchef'),
						esc_html($ingredient['option']), esc_html($val)
					);
				else
					$ingredient['title'] = __("Set Option", 'wpchef');
				
				$ingredient['short_title'] = __("Option", 'wpchef');
				$ingredient['short_title_plural'] = __("Options", 'wpchef');

				break;
			
			case 'new':
				$ingredient['title'] = '';
				break;
			
			case 'action':
				if ( isset( $this->actions[ $ingredient['action'] ] ) )
					$ingredient['title'] = $this->actions[ $ingredient['action'] ];

				$ingredient['short_title'] = __("Action", 'wpchef');
				$ingredient['short_title_plural'] = __("Actions", 'wpchef');

				switch ($ingredient['action']):
				
				case 'uninstall_plugin':
				case 'deactivate_plugin':

					if ( $ingredient['slug'] )
						$ingredient['title'] .= sprintf(': <b>%s</b>', esc_html($ingredient['slug']));
					
					else
						$ingredient['valid'] = false;

					break;
				
				case 'eval':
				
					$ingredient['code'] = $this->nice_code($ingredient['code'] );
					$ingredient['valid'] = !empty($ingredient['code']);
					
					if ( $ingredient['runon'] !== 'uninstall' )
						$ingredient['runon'] = 'install';
					
					//$ingredient['short_title'] = __("Snippet", 'wpchef');
					//$ingredient['short_title_plural'] = __("Snippets", 'wpchef');

					break;
				
				case 'add_page':
				
					$ingredient['title'] .= sprintf(': <b>%s</b>', esc_html($ingredient['name']));
					$ingredient['valid'] = !empty( $ingredient['name'] );
					
					//$ingredient['short_title'] = __("Page", 'wpchef');
					//$ingredient['short_title_plural'] = __("Pages", 'wpchef');

					break;
				
				case 'add_menu_item':
				
					$ingredient['title'] .= sprintf(': <b>%s</b>', esc_html($ingredient['name']));
					$ingredient['valid'] = !empty( $ingredient['url'] ) && !empty( $ingredient['name'] ) && !empty( $ingredient['placement'] );
					
					$ingredient['link'] = str_replace('{:HOME_URL:}', untrailingslashit( home_url() ),  $ingredient['url'] );
					
					//$ingredient['short_title'] = __("Menu", 'wpchef');
					//$ingredient['short_title_plural'] = __("Menus", 'wpchef');

					break;
				
				case 'add_user':
				
					$ingredient['login'] = sanitize_user( $ingredient['login'], true );
					$ingredient['email'] = sanitize_email( $ingredient['email'] );
					
					$ingredient['title'] = sprintf(
						__("Add User <b>%s</b>",'wpchef'),
						esc_html($ingredient['login'])
					);
					
					$ingredient['valid'] = true; //!empty( $ingredient['login'] );
					
					//$ingredient['short_title'] = __("User", 'wpchef');
					//$ingredient['short_title_plural'] = __("Users", 'wpchef');

					break;
				
				case 'alert':
				
					if ( !in_array( $ingredient['alert_type'], array('error', 'warning', 'success') ) )
						$ingredient['alert_type'] = 'info';
					
					$ingredient['valid'] = !empty( $ingredient['content'] );
					
					//$ingredient['short_title'] = __("Message", 'wpchef');
					//$ingredient['short_title_plural'] = __("Messages", 'wpchef');

					break;
				
				default:
					$ingredient['title'] = _x('Unknown Action','ingredient title', 'wpchef').': '.esc_html( $ingredient['action'] );
					$ingredient['valid'] = false;
					
				endswitch;
				
				
				if ( !in_array( $ingredient['action'], array( 'eval', 'alert', 'uninstall_plugin', 'deactivate_plugin' ) ) || $ingredient['runon'] !== 'uninstall'  )
					$ingredient['runon'] = 'install';
				
				break;
			
			default:
				$ingredient['title'] = __('Invalid ingredient', 'wpchef');
				$ingredient['type'] = 'invalid';
				$ingredient['valid'] = false;
				
				break;
		}
		
		$ingredient['title'] = wp_kses( $ingredient['title'], array('b' => array() ) );

		$ingredient['type_full'] = $ingredient['type'];
		if ( $ingredient['type'] == 'action' )
			$ingredient['type_full'] .= '-'.$ingredient['action'];

		$ingredient = apply_filters( 'wpchef_normalize_ingredient', $ingredient, $origin );
		
		return $ingredient;
	}
	
	public function sanitize( $raw )
	{
		if ( !$raw || empty($raw['type']) )
			return false;
		
		foreach( $raw as $key => $val )
			if ( is_string($val) && !in_array( $key, array( 'value', 'code') ) )
				$raw[ $key ] = trim( $val );
		
		$clean = array(
			'type' => $raw['type'],
		);
		switch ( $clean['type'] )
		{
			case 'plugin':
			case 'theme':
				$clean = array(
					'type' => $clean['type'],
					'slug' => (string)@$raw['slug'],
					'name' => (string)(empty($raw['name']) ? @$raw['slug'] : $raw['name']),
				);
				
				if ( !empty($raw['package']) )
					if (
						preg_match('~^https?://(?:[a-z]+\.)?wordpress\.org/(plugin|theme)s/([a-z\d_-]+)/~i', $raw['package'], $m)
						||
						preg_match('~^https?://downloads\.wordpress\.org/(plugin|theme)/([a-z\d_-]+)\.(?:[\d\.]+\.)?zip$~i', $raw['package'], $m)
					)
					{
						$clean['slug'] = $m[2];
						$clean['type'] = $m[1];
					}
				
				if ( $clean['type'] == 'theme' && !empty( $raw['template'] ) )
				{
					$clean['template'] = $raw['template'];
					
					if ( !empty( $raw['template_name'] ) )
						$clean['template_name'] = @(string)$raw['template_name'];
				}
				
				if ( !empty( $raw['author'] ) )
				{
					$clean['author'] = $raw['author'];
					if ( !empty( $raw['author_uri'] ) )
						$clean['author_uri'] = $raw['author_uri'];
				}
				
				break;
				
			case 'recipe':
				$clean['wpchef_id'] = (int)@$raw['wpchef_id'];
				
				if ( !empty($raw['name']) && is_scalar($raw['name']) )
					$clean['name'] = (string)$raw['name'];
				
				if ( !empty( $raw['author'] ) )
				{
					$clean['author'] = $raw['author'];
					if ( !empty( $raw['author_uri'] ) )
						$clean['author_uri'] = $raw['author_uri'];
				}
				
				break;
			
			case 'option':
				$clean['option'] = (string)@$raw['option'];
				
				if ( array_key_exists( 'value', $raw ) )
					$clean['value'] = $raw['value'];
				
				if ( !empty( $raw['editable'] ) )
					$clean['editable'] = true;
				
				break;
			
			case 'action':
				$clean['action'] = (string)@$raw['action'];
				
				switch ( $clean['action'] ):
				
				case 'uninstall_plugin':
				case 'deactivate_plugin':
					
					$clean['slug'] = (string)@$raw['slug'];
					
					break;
				
				case 'eval':
				
					if ( $code = $this->nice_code( $raw['code'] ) )
						$clean['code'] = $code;
					
					break;
				
				case 'add_page':
				
					$clean['name'] = (string)@$raw['name'];
					
					if ( !empty( $raw['slug'] ) )
						$clean['slug'] = $raw['slug'];
					
					if ( !empty( $raw['content'] ) )
						$clean['content'] = (string)@$raw['content'];
					
					if ( !empty( $raw['placement'] ) )
						$clean['placement'] = $raw['placement'];
					
					if ( !empty( $raw['override'] ) )
						$clean['override'] = $raw['override'] === 'rename' ? 'rename' : true;
					
					break;
				
				case 'add_menu_item':
				
					$clean['url'] = (string)@$raw['url'];
					$clean['name'] = (string)@$raw['name'];
					$clean['placement'] = (string)@$raw['placement'];
					
					break;
				
				case 'add_user':
				
					$clean['login'] = sanitize_user( @$raw['login'] );
					$clean['email'] = sanitize_email( @$raw['email'] );
					if ( !empty( $raw['role'] ) )
						$clean['role'] = (string)$raw['role'];
					
					break;
				
				case 'alert':
					if ( !empty($raw['alert_type']) && in_array( $raw['alert_type'], array('error', 'warning', 'success') ) )
						$clean['alert_type'] = $raw['alert_type'];
					
					$clean['content'] = (string)@$raw['content'];
					
					break;
					
				endswitch;
				
				if ( !empty($raw['runon']) && $raw['runon'] == 'uninstall' )
					$clean['runon'] = 'uninstall';
				
				break;
			
			default:
				return false;
		}
		
		if ( !empty( $raw['description'] ) )
			$clean['description'] = (string)$raw['description'];
		
		$clean = apply_filters( 'wpchef_sanitize_ingredient', $clean, $raw );

		return $clean;
	}
	
	function nice_code( $code = array() )
	{
		if ( empty($code) )
			return array();
		
		/*if ( is_string( $code ) )
			$code = str_replace( "\t", '    ', $code );
		*/
		if ( !is_array( $code ) )
			$code = explode( "\n", (string)$code );
		
		return $code;
	}
	
	function sanitize_list( $ingredients = array() )
	{
		$clean = array();
		
		foreach ( (array)$ingredients as $ingredient )
			if ( $ingredient = $this->sanitize( $ingredient ) )
				$clean[] = $ingredient;
		
		return $clean;
	}

	function value_encode( $val )
	{
		if ( $val === '' )
			return '';
		
		if ( is_string($val) )
		{
			$val2 = json_decode( $val );
			if ( is_scalar($val2) )
				return $val;

			if ( function_exists('json_last_error') )
				$error =  json_last_error() != JSON_ERROR_NONE;
			else
				$error = is_null( $val2 ) && trim($val) != 'null';

			if ( $error )
				return $val;

			if ( is_scalar($val2) && !is_bool( $val2 ) )
				return $val;
		}
		
		return json_encode( $val );
	}
	
	function value_decode( $val )
	{
		if ( $val === '' )
			return '';
		
		$out = json_decode( $val, true );
		
		if ( function_exists('json_last_error') )
			$error =  json_last_error() != JSON_ERROR_NONE;
		else
			$error = is_null( $out ) && trim($val) != 'null';

		if ( !$error )
			return $out;
		
		return $val;
	}
	
	public function form_post()
	{
		if ( empty($_POST['ingredient_order']) || !is_array( $_POST['ingredient_order'] ) )
			return array();
		
		$error = false;
		foreach ( $_POST['ingredient_order'] as $id )
		{
			if ( empty($_POST['ingredient'][$id]) || !empty($_POST['ingredient'][$id]['remove']) )
				continue;
			
			$ingredient = stripslashes_deep( $_POST['ingredient'][$id] );
			if ( array_key_exists( 'value', $ingredient ) )
				$ingredient['value'] = $this->value_decode( $ingredient['value'] );
			
			if ( @$ingredient['type'] == 'recipe' && !empty($ingredient['slug']) && preg_match('~^\s*https?://(?:www\.)?wpchef\.(?:me|org)/recipe/([a-z\d_-]+)~i', $ingredient['slug'], $m) )
				$ingredient['slug'] = $m[1];
			
			$ingredients[] = $ingredient;
			
			$ingredient = $this->normalize( $ingredient );
			if ( !$ingredient['valid'] )
				$error = true;
		}
		
		if ( $error )
			add_filter( 'redirect_post_location', array( $this, 'invalid_post' ), 20 );
		
		$ingredients = $this->sanitize_list( $ingredients );
		return $ingredients;
	}
}

endif;
