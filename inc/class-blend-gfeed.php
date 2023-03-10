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
					// array(
					// 	'label'   => esc_html__('Textbox', 'blend-gfeed'),
					// 	'type'    => 'text',
					// 	'name'    => 'mytextbox',
					// 	'tooltip' => esc_html__('This is the tooltip', 'blend-gfeed'),
					// 	'class'   => 'small',
					// ),
					// array(
					// 	'label'   => esc_html__('My checkbox', 'blend-gfeed'),
					// 	'type'    => 'checkbox',
					// 	'name'    => 'mycheckbox',
					// 	'tooltip' => esc_html__('This is the tooltip', 'blend-gfeed'),
					// 	'choices' => array(
					// 		array(
					// 			'label' => esc_html__('Enabled', 'blend-gfeed'),
					// 			'name'  => 'mycheckbox',
					// 		),
					// 	),
					// ),
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__('Map Fields', 'blend-gfeed'),
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'       => 'party.email',
								'label'      => esc_html__('Email', 'blend-gfeed'),
								'required'   => 1,
								'field_type' => array('email', 'hidden'),
								'tooltip' => esc_html__('This is the tooltip', 'blend-gfeed'),
							),
							array(
								'name'     => 'party.firstName',
								'label'    => esc_html__('First Name', 'blend-gfeed'),
								'required' => 1,
							),
							array(
								'name'     => 'party.lastName',
								'label'    => esc_html__('Last Name', 'blend-gfeed'),
								'required' => 1,
							),
							array(
								'name'       => 'party.homePhone',
								'label'      => esc_html__('Home Phone', 'blend-gfeed'),
								'required'   => 0,
								'field_type' => 'phone',
							),
							array(
								'name'     => 'loanPurposeType',
								'tooltip'  => esc_html__( 'Should be one of the following: CONSTRUCTION, PURCHASE, REFINANCE' ),
								'label'    => esc_html__('Loan Purpose', 'blend-gfeed'),
								'required' => 0,
							),		
							array(
								'name'     => 'property.address.state',
								'label'    => esc_html__('Property Address State', 'blend-gfeed'),
								'required' => 0,
							),
							array(
								'name'     => 'property.address.zipCode',
								'label'    => esc_html__('Property Address ZIP code', 'blend-gfeed'),
								'required' => 0,
							),							
							array(
								'name'     => 'property.type',
								'tooltip'  => esc_html__( 'Should be one of the following: SINGLE_FAMILY, CONDOMINIUM, TOWNHOUSE, TWO_TO_FOUR_UNIT_PROPERTY, COOPERATIVE, MANUFACTURED_OR_MOBILE_HOME' ),
								'label'    => esc_html__('Property Type', 'blend-gfeed'),
								'required' => 0,
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

	/**
	 * Outputs the menu icon
	 *
	 * @return string SVG tag
	 */
	public function get_menu_icon() {
		return '<svg width="22" height="20" version="1.0" xmlns="http://www.w3.org/2000/svg"
		width="1687.000000pt" height="2790.000000pt" viewBox="0 0 1687.000000 2790.000000"
		preserveAspectRatio="xMidYMid meet">
	   
	   <g transform="translate(0.000000,2790.000000) scale(0.100000,-0.100000)"
	   fill="#000000" stroke="none">
	   <path d="M0 26200 l0 -1700 5073 -3 c4569 -3 5084 -5 5197 -19 775 -99 1381
	   -360 1915 -824 1055 -917 1520 -2470 1135 -3788 -221 -756 -700 -1426 -1445
	   -2023 -55 -44 -311 -239 -570 -433 -258 -194 -487 -366 -508 -383 l-38 -29
	   103 -76 c141 -104 590 -431 1523 -1111 435 -316 835 -608 890 -648 229 -167
	   297 -214 304 -211 19 8 447 324 589 435 238 188 405 336 643 573 542 540 944
	   1088 1285 1755 414 808 643 1610 731 2555 24 257 24 870 0 1130 -76 822 -252
	   1529 -568 2275 -76 178 -276 583 -370 745 -607 1058 -1423 1892 -2457 2512
	   -221 132 -700 368 -937 461 -585 230 -1128 371 -1730 447 -458 58 -29 54
	   -5632 57 l-5133 3 0 -1700z"/>
	   <path d="M1470 21166 c-773 -108 -1370 -722 -1460 -1501 -6 -61 -10 -1936 -10
	   -5725 0 -4803 2 -5651 15 -5741 89 -669 527 -1203 1154 -1409 410 -135 856
	   -108 1246 74 126 59 196 102 335 206 107 80 406 298 1325 965 198 144 594 432
	   880 640 286 208 608 442 715 520 107 78 404 294 660 480 256 186 515 374 575
	   418 61 44 157 115 215 157 58 42 222 162 365 265 373 270 425 309 425 315 0 3
	   -31 26 -68 52 -37 26 -125 90 -197 142 -71 52 -170 125 -220 160 -206 150
	   -266 193 -600 437 -586 428 -1272 927 -1423 1037 -79 56 -195 141 -258 188
	   l-114 84 -587 -426 c-324 -235 -689 -501 -813 -591 l-225 -164 -3 1101 c-1
	   606 -1 1594 0 2195 l3 1094 530 -386 c292 -212 753 -548 1025 -746 491 -358
	   633 -461 1178 -857 607 -440 930 -675 1252 -910 184 -134 382 -278 440 -320
	   58 -42 323 -235 590 -430 628 -457 774 -563 868 -629 42 -30 248 -178 457
	   -331 209 -152 602 -438 874 -636 271 -197 555 -404 630 -459 75 -55 290 -212
	   478 -348 451 -329 596 -452 811 -687 439 -481 730 -1063 842 -1685 43 -243 53
	   -362 54 -645 0 -294 -12 -438 -59 -696 -190 -1029 -794 -1929 -1650 -2462
	   -340 -211 -798 -383 -1210 -452 -343 -58 75 -53 -5447 -57 l-5068 -3 0 -1700
	   0 -1700 5123 3 c3864 2 5151 6 5242 15 621 62 1009 131 1462 263 1118 325
	   2100 901 2913 1708 292 290 517 552 742 866 485 675 841 1413 1063 2202 255
	   904 338 1705 269 2571 -150 1889 -1068 3606 -2583 4831 -140 113 -1611 1189
	   -2073 1516 -84 60 -224 161 -1023 744 -401 292 -791 576 -865 630 -74 54 -317
	   230 -540 392 -223 162 -497 362 -610 444 -113 82 -783 569 -1490 1084 -707
	   514 -1629 1185 -2050 1491 -421 306 -1147 834 -1615 1175 -887 646 -1064 774
	   -1255 912 -206 148 -478 266 -715 309 -120 22 -401 27 -525 10z"/>
	   </g>
	   </svg>';
	}
}
