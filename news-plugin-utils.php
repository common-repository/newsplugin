<?php

/**
 * Utils
 *
 * @package    WordPress
 * @subpackage News Plugin
 * @since 1.0.0
 */

// Prevent ourselves from being run directly.
defined('ABSPATH') or die("No script kiddies please!");

if (!function_exists('json_last_error_msg')) {

	/**
	 * Get error message
	 *
	 * @return string
	 */
	function json_last_error_msg()
	{
		static $ERRORS = [
			JSON_ERROR_NONE => 'No error',
			JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
			JSON_ERROR_STATE_MISMATCH => 'State mismatch (invalid or malformed JSON)',
			JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
			JSON_ERROR_SYNTAX => 'Syntax error',
			JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
		];

		$error = json_last_error();
		return isset($ERRORS[$error]) ? $ERRORS[$error] : 'Unknown error';
	}
}

/**
 * Utils
 *
 * @package News Plugin
 */
class News_Plugin_Utils
{

	/**
	 * Plugin version
	 *
	 * @var mixed
	 */
	public static $np_version = null;

	/**
	 * Get plugin version
	 *
	 * @return mixed
	 */
	public static function np_version()
	{
		if (self::$np_version) {
			return (self::$np_version);
		}
		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found
		if (self::$np_version = get_option('news_plugin_version')) {
			return (self::$np_version);
		}
		return (self::np_version_hard());
	}

	/**
	 * Get plugin version from plugin file
	 *
	 * @return mixed
	 */
	public static function np_version_hard()
	{
		if (!function_exists('get_plugin_data')) {
			include(ABSPATH . 'wp-admin/includes/plugin.php');
		}
		$plugin_data = get_plugin_data(plugin_dir_path(__FILE__) . 'news-plugin.php');
		self::$np_version = $plugin_data['Version'];
		update_option('news_plugin_version', $plugin_data['Version']);
		update_option('news_plugin_version_taken', filemtime(plugin_dir_path(__FILE__) . 'news-plugin.php'));
		return (self::$np_version);
	}

	/**
	 * Create a plugin specific user agent
	 *
	 * @param string $type Type of the user agent.
	 * @return string
	 */
	public static function user_agent($type)
	{
		global $wp_version;

		return ('WordPress/' . $wp_version . '; ' . 'Newsplugin/' . self::np_version() . ' ' . $type . '; ' . home_url());
	}

	/**
	 * CURL request
	 *
	 * @param string $url Request URL.
	 * @return string[]|(string|bool)[]
	 */
	public static function http_remote_get_curl($url)
	{
		if (!function_exists('curl_version')) {
			return (['', 'Error: CURL disabled or not installed']);
		}
		if (!function_exists('curl_init') || !function_exists('curl_exec')) {
			return (['', 'Error: CURL disabled by security settings']);
		}

		$response = wp_remote_get($url, [
			'timeout' => 120
		]);

		if (\is_wp_error($response)) {
			return [$response, $response->get_error_message()];
		}

		$code = \wp_remote_retrieve_response_code($response);

		if ( $code !== 200 ) {
			$error = \wp_remote_retrieve_response_message($response);
			return [$response, $error];
		}

		$body = \wp_remote_retrieve_body($response);
		return [$body, false];

	}

	/**
	 * Get socket request
	 *
	 * @param string $url Request URL.
	 * @return string[]
	 */
	public static function http_remote_get_socket($url)
	{
		if (!function_exists('stream_socket_client')) {
			return (['', 'Error: Socket disabled']);
		}
		$aURL = parse_url($url);
		$addr = $aURL['host'];
		$secure_transport = ($aURL['scheme'] === 'ssl' || $aURL['scheme'] === 'https');
		if (!isset($aURL['port'])) {
			if ($secure_transport) {
				$aURL['port'] = 443;
			} else {
				$aURL['port'] = 80;
			}
		}
		if ($secure_transport) {
			$proto = 'ssl://';
		} else {
			$proto = 'tcp://';
		}
		$socket = stream_socket_client($proto . $addr . ':' . $aURL['port'], $errno, $errorMessage, 10, STREAM_CLIENT_CONNECT);
		if ($socket === false) {
			return (['', 'Socket error: ' . $errorMessage]);
		}
		$url = $aURL['path'];
		if (isset($aURL['query']) && $aURL['query']) {
			$url .= "?" . $aURL['query'];
		}
		fwrite($socket, "GET " . $url . " HTTP/1.0\r\nHost: " . $addr . "\r\nAccept: */*\r\nUser-Agent: " . self::user_agent('socket') . "\r\n\r\n");
		$output = '';
		while (!feof($socket)) {
			$output .= fread($socket, 1024);
		}
		fclose($socket);
		if (preg_match('/^(.*?)\r?\n\r?\n(.*)$/s', $output, $m)) {
			return ([$m[2], '']);
		}
		return ([$output, '']);
	}

	/**
	 * Test content checker
	 *
	 * @param mixed $ret Retrieved content.
	 * @return mixed
	 */
	public static function http_test_evaluate($ret)
	{

		$response = isset($ret[0]) ? json_decode($ret[0]) : null;
		$error = isset($ret[1]) ? $ret[1] : null;

		if ($error || !isset($response->client) && $response->server !== 'online' ) {
			$ret[1] = 'Error: unexpected content';
			$ret[1] .= '; Starts with ' . htmlspecialchars(substr($ret[0], 0, 30));
		}

		return ($ret);
	}

	/**
	 * Test URL
	 *
	 * @var string
	 */
	public static $test_url = 'http://api.newsplugin.com/ping';

	/**
	 * Test Curl funcion
	 *
	 * @return mixed
	 */
	public static function http_remote_get_curl_test()
	{
		$ret = self::http_remote_get_curl(self::$test_url);
		return (self::http_test_evaluate($ret));
	}

	/**
	 * Test Get Socket
	 *
	 * @return mixed
	 */
	public static function http_remote_get_socket_test()
	{
		$ret = self::http_remote_get_socket(self::$test_url);
		return (self::http_test_evaluate($ret));
	}

	/**
	 * Test URL for SSL
	 *
	 * @var string
	 */
	public static $test_url_ssl = 'https://api.newsplugin.com/ping';

	/**
	 * Test Curl with SSL
	 *
	 * @return mixed
	 */
	public static function http_remote_get_curl_test_ssl()
	{
		$ret = self::http_remote_get_curl(self::$test_url_ssl);
		return (self::http_test_evaluate($ret));
	}

	/**
	 * Test Get Socket with SSL
	 *
	 * @return mixed
	 */
	public static function http_remote_get_socket_test_ssl()
	{
		$ret = self::http_remote_get_socket(self::$test_url_ssl);
		return (self::http_test_evaluate($ret));
	}

	/**
	 * API root
	 *
	 * @var string
	 */
	public static $api_root = 'https://api.newsplugin.com/';

	/**
	 * API ping PATH
	 *
	 * @var string
	 */
	public static $api_ping_path = 'ping';

	/**
	 * Evaluate HTTP ping
	 *
	 * @param mixed $var Variable.
	 * @return mixed
	 */
	public static function http_ping_evaluate($var)
	{
		$output = $var[0];
		if ($var[0]) {
			$var[0] = json_decode($var[0]);
		}
		// TODO
		if ((!$var[1]) && (!is_object($var[0]))) {
			$var[1] = 'Error: not json';
			if (json_last_error()) {
				$var[1] .= ':' . json_last_error_msg();
			}
			$var[1] .= '; Starts with ' . htmlspecialchars(substr($output, 0, 30));
		}
		return ($var);
	}

	/**
	 * CURL ping
	 *
	 * @return mixed
	 */
	public static function http_remote_get_curl_ping()
	{
		$var = self::http_remote_get_curl(self::$api_root . self::$api_ping_path);
		return (self::http_ping_evaluate($var));
	}

	/**
	 * Get Socket ping
	 *
	 * @return mixed
	 */
	public static function http_remote_get_socket_ping()
	{
		$var = self::http_remote_get_socket(self::$api_root . self::$api_ping_path);
		return (self::http_ping_evaluate($var));
	}

	/**
	 * Remote GET request
	 *
	 * @param string $url Request URL.
	 * @param string $method Method type.
	 * @return mixed
	 */
	public static function generic_remote_get($url, $method)
	{
		switch ($method) {
			case 'wp':
				$ret = wp_remote_get($url, ['timeout' => 10, 'user-agent' => self::user_agent('wp')]);
				if (is_array($ret)) {
					return ([$ret['body'], '']);
				}
				$ret = self::generic_remote_get($url, 'curl');
				return ($ret);
			case 'curl':
				$ret = self::http_remote_get_curl($url);
				if (!$ret[1]) {
					return ($ret);
				}
				$ret = self::generic_remote_get($url, 'socket');
				return ($ret);
			case 'socket':
				$ret = self::http_remote_get_socket($url);
				return ($ret);
		}
	}

	/**
	 * Call API
	 *
	 * @param string     $path Path withing the URL.
	 * @param mixed|null $args Arguments.
	 * @return mixed
	 */
	public static function generic_api_call($path, $args = null)
	{
		$key = get_option('news_plugin_api_key');
		$args = $args ? $args : [];
		$args['k'] = $key;
		$url = self::$api_root . $path;
		$url = add_query_arg(urlencode_deep($args), $url);
		$method = get_option('news_plugin_url_method');
		$ret = self::generic_remote_get($url, $method ? $method : 'wp');
		if ($ret[1]) {
			$currentTime = gmdate('y-m-d h:i:s', time());
			// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.Changed, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$backtrace = debug_backtrace();
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log("$currentTime  -->   " . "Error accessing API point " . self::$api_root . "$path: " . $ret[1] . "  Log Generation Details :      Filename: " . $backtrace[0]['file'] . "   at Line number : " . $backtrace[0]['line'] . "\n\n", 3, __DIR__ . "/logs/plugin-logs.txt");
			return (null);
		}
		return (json_decode($ret[0]));
	}

	/**
	 * Get user info
	 *
	 * @return mixed
	 */
	public static function get_user_info()
	{
		return (self::generic_api_call('user_info'));
	}

	/**
	 * Get system info version
	 * TODO: What a magic constant it is?
	 */
	public static function get_system_info_version()
	{
		return (1.0002);
	}

	/**
	 * Get System DB info
	 *
	 * @return array
	 */
	public static function get_system_db_info()
	{
		global $wpdb;

		if (!empty($wpdb->use_mysqli)) {
			/*
			 See also http://fw2s.com/how-to-get-complete-mysql-version-in-wordpress/
			   Note: use_mysqli is private and dbh is protected, BUT wpdb class is allowing
			   to access then through getters and setters anyway. Backward compatibility.
			 */
			return ([
				'mysql_method'          => 'mysqli',
				'mysql_server_info'     => mysqli_get_server_info($wpdb->dbh),
				'mysql_client_info'     => mysqli_get_client_info($wpdb->dbh),
				'mysql_proto_info'      => mysqli_get_proto_info($wpdb->dbh),
			]);
		} else {
			return ([
				'mysql_method'          => 'mysql',
				// phpcs:ignore PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved
				'mysql_server_info'     => mysql_get_server_info(),
				// phpcs:ignore PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved
				'mysql_client_info'     => mysql_get_client_info(),
				// phpcs:ignore PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved
				'mysql_proto_info'      => mysql_get_proto_info(),
			]);
		}
	}

	/**
	 * Get system info
	 *
	 * @return array
	 */
	public static function get_system_info()
	{
		$my_theme = wp_get_theme();
		$curl_test = self::http_remote_get_curl_test();
		$socket_test = self::http_remote_get_socket_test();
		$curl_test_ssl = self::http_remote_get_curl_test_ssl();
		$socket_test_ssl = self::http_remote_get_socket_test_ssl();
		$curl_ping = self::http_remote_get_curl_ping();
		$socket_ping = self::http_remote_get_socket_ping();
		$user_info = self::get_user_info();

		if (function_exists('curl_version')) {
			$curl_ver = curl_version();
			$curl_status = 'Enabled';
			if (!function_exists('curl_init') || !function_exists('curl_exec')) {
				$curl_status = 'Disabled by security settings';
			}
		} else {
			$curl_status = 'Disabled';
		}

		$db_info = self::get_system_db_info();

		$system_info = [
			'info_version' => self::get_system_info_version(),
			'api_key'      => get_option('news_plugin_api_key'), /* We need to refresh on api key change ... */
			'wordpress_env' => [
				'siteurl'               => get_bloginfo('url'),
				'version'               => get_bloginfo('version'),
				'html_type'             => get_bloginfo('html_type'),
				'language'              => get_bloginfo('language'),
				'theme_Name'            => $my_theme->get('Name'),
				'theme_version'         => $my_theme->get('Version'),
				'theme_AuthorURI'       => $my_theme->get('AuthorURI'),
			],
			'system_env' => [
				'php_version'           => phpversion(),
				'SERVER_SOFTWARE'       => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : null,
				'SERVER_OS'             => PHP_OS,
				'SERVER_IP_ADDRESS'     => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : null,
				'HTTP_HOST'             => isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : null,
				'SERVER_NAME'           => isset($_SERVER['SERVER_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])) : null,
				'HTTP_USER_AGENT'       => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null,
				'HTTP_ACCEPT'           => isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : null,
				'memory_limit'          => ini_get('memory_limit'),
				'execution_time'        => ini_get('max_execution_time'),
				'mysql_method'          => $db_info['mysql_method'],
				'mysql_server_info'     => $db_info['mysql_server_info'],
				'mysql_client_info'     => $db_info['mysql_client_info'],
				'mysql_proto_info'      => $db_info['mysql_proto_info'],
				'is_Curl'               => $curl_status,
				'curl_version'          => $curl_ver['version'],
				'curl_ssl'              => $curl_ver['ssl_version'],
				'curl_status'           => $curl_test[1] ? $curl_test[1] : 'OK',
				'curl_status_ssl'       => $curl_test_ssl[1] ? $curl_test_ssl[1] : 'OK',
				'is_Socket'             => function_exists('stream_socket_client') ? 'Enabled' : 'Disabled',
				'socket_status'         => $socket_test[1] ? $socket_test[1] : 'OK',
				'socket_status_ssl'     => $socket_test_ssl[1] ? $socket_test_ssl[1] : 'OK',
			],
			'newsplugin_env' => [
				'REGISTERED EMAIL'      => $user_info ? $user_info->email : 'error or unregistered',
				'USER STATUS'           => $user_info ? $user_info->status : 'error or unregistered',
				'plugin version'        => self::np_version(),
				'curl_ping'             => $curl_ping[1] ? $curl_ping[1] : ('OK from ' . $curl_ping[0]->client),
				'socket_ping'           => $socket_ping[1] ? $socket_ping[1] : ('OK from ' . $socket_ping[0]->client),
			]
		];
		if ($curl_test[1] === $curl_test_ssl[1]) {
			unset($system_info['system_env']['curl_status_ssl']);
		}
		if ($socket_test[1] === $socket_test_ssl[1]) {
			unset($system_info['system_env']['socket_status_ssl']);
		}
		return ($system_info);
	}
}
