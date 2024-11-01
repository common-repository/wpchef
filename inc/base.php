<?php

if ( !class_exists('wpchef_base') ):

abstract class wpchef_base
{
	public $debug = false;
	protected $cache = true;
	private static $runtime_cache = array();

	public static function instance()
	{
		if( !isset( static::$instance ) ){
			static::$instance = new static();
		}
		
		return static::$instance;
	}
	
	protected function __construct()
	{
		static::$instance = $this;
	}

	function response_success( $data = null )
	{
		return array(
			'success' => true,
			'data' => $data,
		);
	}
	
	function response_error( $error = '', $data = null )
	{
		return array(
			'success' => false,
			'error' => $error,
			'data' => $data,
		);
	}
	
	function json_success( $data = null )
	{
		$this->json_response( $this->response_success($data) );
	}
	
	function json_error( $error = '', $data = null )
	{
		$this->json_response( $this->response_error( $error, $data ) );
	}
	
	function json_response( $res )
	{
		header( 'Content-Type: application/json; charset=utf-8' );

		$options = 0;
		if ( defined('JSON_PRETTY_PRINT') )
			$options = $options | JSON_PRETTY_PRINT;
		if ( defined('JSON_UNESCAPED_SLASHES') )
			$options = $options | JSON_UNESCAPED_SLASHES;

		$resp = json_encode( $res, $options );
		header( 'Content-Length: '.strlen( $resp ) );
		echo $resp;
		$this->log( 'Sent.', $resp );
		exit;
	}
	
	function remote_json( $url )
	{
		$request = wp_remote_get( $url );
		$this->log( $url, $request );
		
		if ( is_wp_error($request) )
			return false;
		
		$info = json_decode( $request['body'], true );
		
		return $info;
	}

	function cache_get( $var )
	{
		if ( !$this->cache )
			return false;

		if ( array_key_exists( $var,  self::$runtime_cache ) )
			return self::$runtime_cache[ $var ];

		if ( $this->cache === 'runtime' )
			return false;

		$cache = (array)$this->get_option('cache');
		//$this->log( $var, $cache );
		if ( !isset( $cache[ $var ] ) )
			return false;

		if ( $cache[ $var ]['timeout'] < time() )
		{
			$this->clean_cache();
			return false;
		}

		return $cache[ $var ]['value'];
	}

	function clean_cache()
	{
		$cache = (array)$this->get_option('cache');

		foreach ( $cache as $var => $info )
			if ( $info['timeout'] < time() )
				unset( $cache[ $var ] );

		$this->set_option('cache', $cache);
	}

	function cache_set( $var, $val, $timeout = null )
	{
		if ( !$this->cache )
			return;

		self::$runtime_cache[ $var ] = $val;

		if ( $timeout === 0 || $this->cache === 'runtime' )
			return;

		$cache = (array)$this->get_option('cache');

		if ( !$timeout || (int)$timeout <= 0 )
			$timeout = $this->update_timeout;

		$cache[ $var ] = array(
			'timeout' => time() + (int)$timeout,
			'value' => $val,
		);
		$this->set_option('cache',$cache);
	}

	function cache_delete( $var )
	{
		if ( !$this->cache )
			return;

		unset( self::$runtime_cache[ $var ] );

		if ( $this->cache == 'runtime' )
			return;

		$cache = (array)$this->get_option('cache');

		if ( isset( $cache[ $var ] ) )
		{
			unset( $cache[ $var ] );
			$this->set_option('cache',$cache);
		}
	}

	function get_option( $option, $default = null )
	{
		return get_option( 'wpchef_'.$option, $default );
	}

	function set_option( $option, $value, $autoload = false )
	{
		$option = 'wpchef_'.$option;
		add_option( $option, $value, '', $autoload ? 'yes' : 'no' );
		update_option( $option, $value );
	}
	
	function log()
	{
		if ( !$this->debug )
			return;
		
		$log = "\n\n".date('r').":\n";
		
		$inf = debug_backtrace();
		if ( !empty($inf[1]) )
		{
			$log .= sprintf( '%s:%s (%s)', $inf[1]['function'], $inf[1]['line'], basename($inf[1]['file']) )."\n";
		}
		
		$log .= var_export( func_get_args(), true );
		
		file_put_contents( __DIR__.'/log.txt', $log, FILE_APPEND );
	}
}

endif;

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