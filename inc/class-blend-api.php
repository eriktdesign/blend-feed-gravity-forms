<?php

class Blend_API {

	protected $tenant_name;
	protected $instance_id;
	protected $api_username;
	protected $api_password;
	protected $environment;

	public $client;
	private $client_args;

	public function __construct() {
		// Get the plugin settings.
		$settings = blend_gfeed()->get_plugin_settings();

		// Access a specific setting e.g. an api key
		$this->tenant_name = rgar($settings, 'tenant_name');
		$this->instance_id = rgar($settings, 'instance_id');
		$this->api_username = rgar($settings, 'api_username');
		$this->api_password = rgar($settings, 'api_password');
		$this->environment = rgar($settings, 'environment', 'https://api.beta.blend.com/');


		$this->client = new \GuzzleHttp\Client();
	}

	public function set_client_args() {
		$this->client_args = [
			'headers' => [
				'Content-Type' => 'application/json',
				'accept' => 'application/json; charset=utf-8',
				'blend-api-version' => '5.3.0',
				'blend-target-instance' => "$this->tenant_name~$this->instance_id", //'allied~default',
				'cache-control' => 'no-cache',
			],
			'auth' => [ 
				$this->api_username, // username
				$this->api_password // Password
			],
		];
	}

	public function request( $method, $route, $args = [], $body = '' ) {
		$this->set_client_args();
		$args = wp_parse_args( $args, $this->client_args );
		$args['body'] = $body;
		// Run request
		try {
			$response = $this->client->request( $method, esc_url( $this->environment . $route ), $args );
		} catch ( \GuzzleHttp\Exception\RequestException $e ) {
			return new \WP_Error( 'guzzle_http_error', $e->getResponse()->getBody()->getContents() );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'general_error', 'Exception: ' . $e->getMessage() );
		}

		// Output response
		return $response->getBody();
	}

	/**
	 * Fires a GET request to the given route
	 *
	 * @param string $route The route to request
	 * @param array $args Arguments to merge with GuzzleHttp\Client->request()
	 * @param string $body Body of request
	 * @return string Body of HTTP response
	 */
	public function get( $route, $args = [], $body = '', $blend_target_instance = '' ) {
		if ( ! empty( $blend_target_instance ) ) $this->set_target_instance( $blend_target_instance );
		return $this->request( 'GET', $route, $args, $body );
	}
	
	/**
	 * Fires a POST request to the given route
	 *
	 * @param string $route The route to request
	 * @param array $args Arguments to merge with GuzzleHttp\Client->request()
	 * @param string $body Body of request
	 * @return string Body of HTTP response
	 */
	public function post( $route, $args = [], $body = '', $blend_target_instance = '' ) {
		if ( ! empty( $blend_target_instance ) ) $this->set_target_instance( $blend_target_instance );
		return $this->request( 'POST', $route, $args, $body );
	}

	/**
	 * Fires a DELETE request to the given route
	 *
	 * @param string $route The route to request
	 * @param array $args Arguments to merge with GuzzleHttp\Client->request()
	 * @param string $body Body of request
	 * @return string Body of HTTP response
	 */
	public function delete( $route, $args = [], $body = '', $blend_target_instance = '' ) {
		if ( ! empty( $blend_target_instance ) ) $this->set_target_instance( $blend_target_instance );
		return $this->request( 'DELETE', $route, $args, $body );
	}

	/**
	 * Fires a PATCH request to the given route
	 *
	 * @param string $route The route to request
	 * @param array $args Arguments to merge with GuzzleHttp\Client->request()
	 * @param string $body Body of request
	 * @return string Body of HTTP response
	 */
	public function patch( $route, $args = [], $body = '', $blend_target_instance = '' ) {
		if ( ! empty( $blend_target_instance ) ) $this->set_target_instance( $blend_target_instance );
		return $this->request( 'PATCH', $route, $args, $body );
	}

	/**
	 * Fires a PUT request to the given route
	 *
	 * @param string $route The route to request
	 * @param array $args Arguments to merge with GuzzleHttp\Client->request()
	 * @param string $body Body of request
	 * @return string Body of HTTP response
	 */
	public function put( $route, $args = [], $body = '', $blend_target_instance = '' ) {
		if ( ! empty( $blend_target_instance ) ) $this->set_target_instance( $blend_target_instance );
		return $this->request( 'PUT', $route, $args, $body );
	}

	/**
	 * Sets the target tenant and/or instance
	 *
	 * @param string $blend_target_instance tenant or tenant~instance string
	 * @return void
	 */
	public function set_target_instance( $blend_target_instance ) {
		if ( strpos( $blend_target_instance, '~' ) === false ) {
			// No separator, this is just the tenant name
			$this->tenant_name = $blend_target_instance;
		} else {
			// Separator is there, explode and set both values
			$blend_target_instance = explode( '~', $blend_target_instance );
			$this->tenant_name = $blend_target_instance[0];
			$this->instance_id = $blend_target_instance[1];
		}
	}
}