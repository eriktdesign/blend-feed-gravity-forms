<?php

/**
 * Blend GFeed class
 */

GFForms::include_feed_addon_framework();

class BlendGFeed extends GFFeedAddOn {

	protected $_version = BLEND_GFEED_VERSION;
	protected $_min_gravityforms_version = '1.9.16';
	protected $_slug = 'blend-gfeed';
	protected $_path = 'blend-feed-gravity-forms/blend-feed-gravity-forms.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Blend Feed Add-On';
	protected $_short_title = 'Blend Feed';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return BlendGFeed
	 */
	public static function get_instance() {
		if (self::$_instance == null) {
			self::$_instance = new BlendGFeed();
		}

		return self::$_instance;
	}

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init() {

		parent::init();

	}


	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process the feed e.g. subscribe the user to a list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return bool|void
	 */
	public function process_feed($feed, $entry, $form) {
		$feedName  = $feed['meta']['feedName'];
		$mytextbox = $feed['meta']['mytextbox'];
		$checkbox  = $feed['meta']['mycheckbox'];

		// Retrieve the name => value pairs for all fields mapped in the 'mappedFields' field map.
		$field_map = $this->get_field_map_fields($feed, 'mappedFields');

		// Loop through the fields from the field map setting building an array of values to be passed to the third-party service.
		$merge_vars = array();
		foreach ($field_map as $name => $field_id) {

			// Get the field value for the specified field id
			$merge_vars[$name] = $this->get_field_value($form, $entry, $field_id);
		}

		// Send the values to the third-party service.
	}

	/**
	 * Custom format the phone type field values before they are returned by $this->get_field_value().
	 *
	 * @param array $entry The Entry currently being processed.
	 * @param string $field_id The ID of the Field currently being processed.
	 * @param GF_Field_Phone $field The Field currently being processed.
	 *
	 * @return string
	 */
	public function get_phone_field_value($entry, $field_id, $field) {

		// Get the field value from the Entry Object.
		$field_value = rgar($entry, $field_id);

		// If there is a value and the field phoneFormat setting is set to standard reformat the value.
		if (!empty($field_value) && $field->phoneFormat == 'standard' && preg_match('/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches)) {
			$field_value = sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]);
		}

		return $field_value;
	}

	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'my_script_js',
				'src'     => $this->get_base_url() . '/js/my_script.js',
				'version' => $this->_version,
				'deps'    => array('jquery'),
				'strings' => array(
					'first'  => esc_html__('First Choice', 'blend-gfeed'),
					'second' => esc_html__('Second Choice', 'blend-gfeed'),
					'third'  => esc_html__('Third Choice', 'blend-gfeed'),
				),
				'enqueue' => array(
					array(
						'admin_page' => array('form_settings'),
						'tab'        => 'blend-gfeed',
					),
				),
			),
		);

		return array_merge(parent::scripts(), $scripts);
	}

	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {

		$styles = array(
			array(
				'handle'  => 'my_styles_css',
				'src'     => $this->get_base_url() . '/css/my_styles.css',
				'version' => $this->_version,
				'enqueue' => array(
					array('field_types' => array('poll')),
				),
			),
		);

		return array_merge(parent::styles(), $styles);
	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Creates a custom page for this add-on.
	 */
	public function plugin_page() {
		echo '<h2>Blend API Settings</h2>';

		$api = new Blend_API();
		echo $api->get( 'home-lending/applications' );

		$data = [
			'party' => [
				'name' => [
					'firstName' => 'Test',
					'lastName' => 'Userface',
				],
				'email' => 'test@test.local'
			]
		];

		// echo $api->post( 'home-lending/applications', [], json_encode( $data ) );

	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__('Blend Feed Settings', 'blend-gfeed'),
				'fields' => array(
					array(
						'name'    => 'tenant_name',
						'tooltip' => esc_html__('Enter the Tenant Name you use to connect to Blend', 'blend-gfeed'),
						'label'   => esc_html__('API Username', 'blend-gfeed'),
						'type'    => 'text',
						'class'   => 'small',
					),
					array(
						'name'    => 'instance_id',
						'tooltip' => esc_html__('Enter the Instance ID you use to connect to Blend', 'blend-gfeed'),
						'label'   => esc_html__('Instance ID', 'blend-gfeed'),
						'type'    => 'text',
						'class'   => 'small',
						'default_value' => 'default',
					),
					array(
						'name'    => 'api_username',
						'tooltip' => esc_html__('Enter the API Username you use to connect to Blend', 'blend-gfeed'),
						'label'   => esc_html__('API Username', 'blend-gfeed'),
						'type'    => 'text',
						'class'   => 'small',
					),
					array(
						'name'    => 'api_password',
						'tooltip' => esc_html__('Enter the API Password you use to connect to Blend', 'blend-gfeed'),
						'label'   => esc_html__('API Password', 'blend-gfeed'),
						'type'    => 'text',
						'class'   => 'small',
					),
					array(
						'name'    => 'api_connection_status',
						// 'tooltip' => esc_html__('The status of your API connection', 'blend-gfeed'),
						'label'   => esc_html__('API Connection Status', 'blend-gfeed'),
						'type'    => 'api_connection_status',
						// 'class'   => 'small',
					),
				),
			),
		);
	}

	public function settings_api_connection_status() {
		$api = new Blend_API();
		$response = $api->get( 'authentication-status' ); 
		if ( is_wp_error( $response ) ) echo '&#9888; API Connection error. Please check your connection details.';
		else if ( json_decode( $response )->isAuthenticated == 'true' ) echo '&check; API Connected Successfully';
	}

	/**
	 * Configures the settings which should be rendered on the feed edit page in the Form Settings > Simple Feed Add-On area.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__('Blend Feed Settings', 'blend-gfeed'),
				'fields' => array(
					array(
						'label'   => esc_html__('Feed name', 'blend-gfeed'),
						'type'    => 'text',
						'name'    => 'feedName',
						'tooltip' => esc_html__('This is the tooltip', 'blend-gfeed'),
						'class'   => 'small',
					),
					array(
						'label'   => esc_html__('Textbox', 'blend-gfeed'),
						'type'    => 'text',
						'name'    => 'mytextbox',
						'tooltip' => esc_html__('This is the tooltip', 'blend-gfeed'),
						'class'   => 'small',
					),
					array(
						'label'   => esc_html__('My checkbox', 'blend-gfeed'),
						'type'    => 'checkbox',
						'name'    => 'mycheckbox',
						'tooltip' => esc_html__('This is the tooltip', 'blend-gfeed'),
						'choices' => array(
							array(
								'label' => esc_html__('Enabled', 'blend-gfeed'),
								'name'  => 'mycheckbox',
							),
						),
					),
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__('Map Fields', 'blend-gfeed'),
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'       => 'email',
								'label'      => esc_html__('Email', 'blend-gfeed'),
								'required'   => 0,
								'field_type' => array('email', 'hidden'),
								'tooltip' => esc_html__('This is the tooltip', 'blend-gfeed'),
							),
							array(
								'name'     => 'name',
								'label'    => esc_html__('Name', 'blend-gfeed'),
								'required' => 0,
							),
							array(
								'name'       => 'phone',
								'label'      => esc_html__('Phone', 'blend-gfeed'),
								'required'   => 0,
								'field_type' => 'phone',
							),
						),
					),
					array(
						'name'           => 'condition',
						'label'          => esc_html__('Condition', 'blend-gfeed'),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__('Enable Condition', 'blend-gfeed'),
						'instructions'   => esc_html__('Process this simple feed if', 'blend-gfeed'),
					),
				),
			),
		);
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'  => esc_html__('Name', 'blend-gfeed'),
			'mytextbox' => esc_html__('My Textbox', 'blend-gfeed'),
		);
	}

	/**
	 * Format the value to be displayed in the mytextbox column.
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_mytextbox($feed) {
		return '<b>' . rgars($feed, 'meta/mytextbox') . '</b>';
	}

	/**
	 * Prevent feeds being listed or created if an api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		// Get the plugin settings.
		$settings = $this->get_plugin_settings();

		// Access a specific setting e.g. an api key
		$key = rgar($settings, 'apiKey');

		return true;
	}
}
