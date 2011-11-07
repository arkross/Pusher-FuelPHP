<?php
/**
 * Pusher FuelPHP Class
 * 
 * A FuelPHP Class to work with Pusher API.
 * This class is based on the Squeeks' Pusher-PHP project on Github.
 * Every lines of code inside the functions are almost the same. There are just
 * some changes to adapt to singleton pattern.
 * 
 * Call this before any other functions:
 * Pusher::init();
 * 
 * All credit goes to Squeeks on Github:
 * https://github.com/squeeks/Pusher-PHP
 *
 * The repository of this class:
 * https://github.com/arkross/Pusher-FuelPHP
 */
class Pusher
{

	private static $settings = array ();

	/**
	 * Initializes a new Pusher instance with key, secret , app ID and channel. 
	 * You can optionally turn on debugging for all requests by setting debug to true.
	 * If this function is called without arguments, the defaults at the config file will be used.
	 * 
	 * @param string $auth_key if it's an array, it will be loaded as config. If it's a string, it will be considered as auth_key.
	 * @param string $secret
	 * @param int $app_id
	 * @param bool $debug [optional]
	 * @param string $host [optional]
	 * @param int $port [optional]
	 * @param int $timeout [optional]
	 */
	public static function init( $auth_key = null, $secret = '', $app_id = '', $debug = false, $host = 'http://api.pusherapp.com', $port = '80', $timeout = 30 )
	{

		// Check compatibility, disable for speed improvement
		static::check_compatibility();
		
		Config::load('pusher', 'pusher');
		$data = array(
			'auth_key' => '',
			'secret' => '',
			'app_id' => '',
			'debug' => false,
			'host' => '',
			'port' => 80,
			'timeout' => 30
		);
		foreach ($data as $k => $v)
		{
			static::$settings[$k] = Config::get('pusher.'.$k);
		}
		
		if (is_array($auth_key))
		{
			foreach ($auth_key as $k => $d)
			{
				static::$settings[$k] = $d;
			}
		}
		elseif (is_string($auth_key))
		{
			// Setup defaults
			static::$settings['server']	= $host;
			static::$settings['port']		= $port;
			static::$settings['auth_key']	= $auth_key;
			static::$settings['secret']	= $secret;
			static::$settings['app_id']	= $app_id;
			static::$settings['url']		= '/apps/' . static::$settings['app_id'];
			static::$settings['debug']	= $debug;
			static::$settings['timeout']	= $timeout;
		}
	}

	/**
	* Check if the current PHP setup is sufficient to run this class
	*/
	private static function check_compatibility()
	{

		// Check for dependent PHP extensions (JSON, cURL)
		if ( ! extension_loaded( 'curl' ) || ! extension_loaded( 'json' ) )
		{
			die( 'There is missing dependant extensions - please ensure both cURL and JSON modules are installed' );
		}

		# Supports SHA256?
		if ( ! in_array( 'sha256', hash_algos() ) )
		{
			die( 'SHA256 appears to be unsupported - make sure you have support for it, or upgrade your version of PHP.' );
		}

	}

	/**
	* Trigger an event by providing event name and payload. 
	* Optionally provide a socket ID to exclude a client (most likely the sender).
	* 
	* @param string $event
	* @param mixed $payload
	* @param int $socket_id [optional]
	* @param string $channel [optional]
	* @param bool $debug [optional]
	* @return bool|string
	*/
	public static function trigger( $channel, $event, $payload, $socket_id = null, $debug = false, $already_encoded = false )
	{

		# Check if we can initialize a cURL connection
		$ch = curl_init();
		if ( $ch === false )
		{
			die( 'Could not initialise cURL!' );
		}

		# Add channel to URL..
		$s_url = static::$settings['url'] . '/channels/' . $channel . '/events';

		# Build the request
		$signature = "POST\n" . $s_url . "\n";
		$payload_encoded = $already_encoded ? $payload : json_encode( $payload );
		$query = "auth_key=" . static::$settings['auth_key'] . "&auth_timestamp=" . time() . "&auth_version=1.0&body_md5=" . md5( $payload_encoded ) . "&name=" . $event;

		# Socket ID set?
		if ( $socket_id !== null )
		{
			$query .= "&socket_id=" . $socket_id;
		}

		# Create the signed signature...
		$auth_signature = hash_hmac( 'sha256', $signature . $query, static::$settings['secret'], false );
		$signed_query = $query . "&auth_signature=" . $auth_signature;
		$full_url = static::$settings['server'] . ':' . static::$settings['port'] . $s_url . '?' . $signed_query;

		# Set cURL opts and execute request
		curl_setopt( $ch, CURLOPT_URL, $full_url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array ( "Content-Type: application/json" ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload_encoded );
		curl_setopt( $ch, CURLOPT_TIMEOUT, static::$settings['timeout'] );

		$response = curl_exec( $ch );

		curl_close( $ch );

		if ( $response == "202 ACCEPTED\n" && $debug == false )
		{
			return true;
		}
		elseif ( $debug == true || static::$settings['debug'] == true )
		{
			return $response;
		}
		else
		{
			return false;
		}

	}

	/**
	* Creates a socket signature
	* 
	* @param int $socket_id
	* @param string $custom_data
	* @return string
	*/
	public static function socket_auth( $channel, $socket_id, $custom_data = false )
	{

		if($custom_data == true)
		{
			$signature = hash_hmac( 'sha256', $socket_id . ':' . $channel . ':' . $custom_data, static::$settings['secret'], false );
		}
		else
		{
			$signature = hash_hmac( 'sha256', $socket_id . ':' . $channel, static::$settings['secret'], false );
		}

		$signature = array ( 'auth' => static::$settings['auth_key'] . ':' . $signature );
		// add the custom data if it has been supplied
		if($custom_data){
		  $signature['channel_data'] = $custom_data;
		}
		return json_encode( $signature );

	}

	/**
	* Creates a presence signature (an extension of socket signing)
	*
	* @param int $socket_id
	* @param string $user_id
	* @param mixed $user_info
	* @return string
	*/
	public static function presence_auth( $channel, $socket_id, $user_id, $user_info = false )
	{

		$user_data = array( 'user_id' => $user_id );
		if($user_info == true)
		{
			$user_data['user_info'] = $user_info;
		}

		return static::socket_auth($channel, $socket_id, json_encode($user_data) );
	}
}

?>
