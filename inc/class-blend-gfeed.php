<?php

/**
 * Blend GFeed class
 */

use function PHPSTORM_META\map;

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
	public function process_feed( $feed, $entry, $form ) {
		$feed_name = $feed['meta']['feedName'];
		// $mytextbox = $feed['meta']['mytextbox'];
		// $checkbox  = $feed['meta']['mycheckbox'];

		// Retrieve the name => value pairs for all fields mapped in the 'mappedFields' field map.
		$field_map = $this->get_field_map_fields($feed, 'mappedFields');

		$blend_target_instance = '';

		// Loop through the fields from the field map setting building an array of values to be passed to the third-party service.
		$merge_vars = array();
		foreach ($field_map as $name => $field_id) {

			if ( empty( $this->get_field_value( $form, $entry, $field_id ) ) ) continue;

			if ( $field_id == 'blend-target-instance' ) {
				$blend_target_instance = $this->get_field_value( $form, $entry, $field_id );
				continue;
			}

			// Get the nested array keys for this value
			$keys = explode( '.', $name );

			// Initialize a reference variable to point to the current level of the output array
			$ref = &$merge_vars;

			// Loop through the key levels
			foreach ( $keys as $i => $subkey ) {
				// If this level of nesting doesn't exist, create it as an empty array
				if ( ! isset( $ref[ $subkey ] ) ) {
					$ref[ $subkey ] = [];
				}

				// Set the reference pointer to the current level of nesting
				$ref = &$ref[ $subkey ];
			}

			// Set the value of the deepest level
			$ref = $this->get_field_value( $form, $entry, $field_id );
		}

		// If an assignee has been added, remove it from the merge vars, it's a separate API call
		if ( array_key_exists( 'assignee', $merge_vars ) ) {
			$assignee = $merge_vars['assignee'];
			unset( $merge_vars['assignee'] );
		} else {
			$assignee = false;
		}

		// Send the values to the third-party service.
		$api = new Blend_API();
		$response = $api->post( 'home-lending/applications', [], json_encode( $merge_vars ), $blend_target_instance );
		if ( is_wp_error( $response ) ) {
			$this->add_feed_error( 'Error posting to Blend', $feed, $entry, $form );
			return false;
		} 
		
		// Get data from the response
		$data = json_decode( $response );
		$application_id = $data->id;
		
		// Note on the entry
		$this->add_note( $entry['id'], "Application sent to Blend successfully as $application_id", 'success' );
		
		// Patch the assignee to the application
		if ( $application_id && $assignee ) {
			// Create the JSON to send
			$assignees = sprintf( '{"assignees":[{"userId":"%s"}]}', $assignee );
			// Send the API request
			$response = $api->patch( "home-lending/applications/$application_id/assignees", [], $assignees, $blend_target_instance );
			// Handle error
			if ( is_wp_error( $response ) ) {
				$this->add_feed_error( 'Error assigning loan officer in Blend', $feed, $entry, $form );
				return false;
			}

			// Add note to entry on success
			$this->add_note( $entry['id'], "Loan officer $assignee assigned in Blend successfully.", 'success' );
		}

		// Not sure this works. We might need to parse the JSON to find the party that has "type" set to "BORROWER"
		// $party_id = $data->parties[0]->id;

		// post to the /parties/{id} endpoint
		// $response = $api->post( "parties/$party_id", [], json_encode( $parties_data ) );
		
		// Sample custom Meta:
		// customMetadata.estCreditScore.fieldValue = 740

		// post to the /realtors/{id} endpoint
		// $response = $api->post( "realtors/$application_id", [], json_encode( $realtors_data ) );
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
			$field_value = sprintf('%s%s%s', $matches[1], $matches[2], $matches[3]);
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
		echo '<h2>Blend API Debug</h2>';
		echo '<p>Below is a response from the <code>home-lending/applications</code> endpoint with your current configuration.</p>';

		$api = new Blend_API();
		$response = $api->get( 'home-lending/applications' );

		if ( ! is_wp_error( $response ) ) {
			$json = json_encode( json_decode( $response ), JSON_PRETTY_PRINT );
			echo '<pre><code>';
			echo $json;
			echo '</code></pre>';
		} else {
			var_dump($response);
		}

		// $data = [
		// 	'party' => [
		// 		'name' => [
		// 			'firstName' => 'Test',
		// 			'lastName' => 'Userface',
		// 		],
		// 		'email' => 'test@test.local'
		// 	]
		// ];

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
						'tooltip' => esc_html__('Enter the Tenant Name you use to connect to Blend. This can be overridden for individual feeds.', 'blend-gfeed'),
						'label'   => esc_html__('API Username', 'blend-gfeed'),
						'type'    => 'text',
						'class'   => 'small',
					),
					array(
						'name'    => 'instance_id',
						'tooltip' => esc_html__('Enter the Instance ID you use to connect to Blend. This can be overridden for individual feeds.', 'blend-gfeed'),
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
						'name'    => 'environment',
						'tooltip' => esc_html__('Select        the environment to run requests against', 'blend-gfeed'),
						'label'   => esc_html__('Environment', 'blend-gfeed'),
						'type'    => 'select',
						'choices' => array(
							array(
								'label' => esc_html__('Beta/Test  API Environment', 'blend-gfeed'),
								'name'  => 'beta',
								'value' => 'https://api.beta.blend.com/',
							 ),
							 array(
								'label' => esc_html__('Production API Environment', 'blend-gfeed'),
								'name'  => 'prod',
								'value' => 'https://api.blend.com/',
							),
						),
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
		$td = 'blend-gfeed';
		$settings_fields = array(
			array(
				'title'  => esc_html__('Blend Feed Settings', $td ),
				'fields' => array(
					array(
						'label'   => esc_html__('Feed name', $td ),
						'type'    => 'text',
						'name'    => 'feedName',
						'tooltip' => esc_html__('This is the tooltip', $td ),
						'class'   => 'small',
					),
					// array(
					// 	'label' => esc_html__( 'Blend Action', $td  ),
					// 	'type' => 'select',
					// 	'name' => 'blendAction', 
					// 	'tooltip' => esc_html__( 'Select the Blend action this feed should trigger' ),
					// 	'class' => 'small',
					// 	'choices' => array(
					// 		array(
					// 			'label' => esc_html__( 'Create a Home Lending application' ),
					// 			'value' => '/home-lending/applications'
					// 		)
					// 	),
					// ),
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__('Map Fields', $td ),
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name' => 'blend-target-instance',
								'label' => esc_html__( 'Blend Target Instance', $td ),
								'tooltip' => esc_html__( 'The Blend target Tenant / Instance for this feed. Tenant and Instance should be separated by a "~"' ),
								'required' => false,
							),
							array(
								'name' => 'solutionSubType',
								'label' => esc_html__( 'Solution Subtype', $td ),
								'tooltip' => esc_html__( 'Subtype of home loan application being created. Must be one of: MORTGAGE, HELOC, HELOAN', $td  ),
								'required' => false,
							),
							array( 
								'name' => 'applicationExperienceType',
								'label' => esc_html__( 'Application Experience Type', $td ),
								'tooltip' => esc_html__( 'The type of borrower experience for this application. Must be one of: FULL_APPLICATION, LENDER_ENTERED, POST_SUBMISSION', 'blend-geed' ),
								'required' => false,
							),
							array(
								'name' => 'loanPurposeType',
								'label' => esc_html__( 'Loan Purpose Type', $td ),
								'tooltip' => esc_html__( 'Reason for this home loan application. Must be one of: CONSTRUCTION, PURCHASE, REFINANCE', $td ),
								'required' => false,
							),
							
							// PROPERTY OBJECT
							array(
								'name'     => 'property.address.streetAddressLine1',
								'label'    => esc_html__('Property Address Line 1', $td ),
								'required' => false, //true,
							),
							array(
								'name'     => 'property.address.streetAddressLine2',
								'label'    => esc_html__('Property Address Line 2', $td ),
								'required' => false,
							),
							array(
								'name'     => 'property.address.city',
								'label'    => esc_html__('Property Address City', $td ),
								'required' => false, //true,
							),	
							array(
								'name'     => 'property.address.state',
								'label'    => esc_html__('Property Address State', $td ),
								'required' => false, //true,
							),
							array(
								'name'     => 'property.address.zipCode',
								'label'    => esc_html__('Property Address ZIP code', $td ),
								'required' => false, //true,
							),
							array(
								'name'     => 'property.address.zipCodePlusFour',
								'label'    => esc_html__('Property Address ZIP+4', $td ),
								'required' => false,
							),
							array(
								'name'     => 'property.address.countyName',
								'label'    => esc_html__('Property Address County Name', $td ),
								'required' => false,
							),	
							array(
								'name'     => 'property.type',
								'tooltip'  => esc_html__( 'Describes the type of property to which the application pertains. Must be one of: SINGLE_FAMILY, CONDOMINIUM, TOWNHOUSE, TWO_TO_FOUR_UNIT_PROPERTY, COOPERATIVE, MANUFACTURED_OR_MOBILE_HOME', $td ),
								'label'    => esc_html__('Property Type', $td ),
								'required' => false,
							),
							array(
								'name'     => 'property.searchType',
								'tooltip'  => esc_html__( 'Describes the applicant search stage. Must be one of: NOT_STARTED, STILL_LOOKING, FOUND, NOT_IN_CONTRACT', $td ),
								'label'    => esc_html__('Property Search Type', $td ),
								'required' => false,
							),
							array(
								'name'     => 'property.searchTimeline',
								'tooltip'  => esc_html__( 'Describes the applicant timeline for searching for a property. Must be one of: 0_TO_3_MONTHS, 3_TO_6_MONTHS, 6_TO_12_MONTHS, 12_MONTHS_OR_MORE, UNKNOWN', $td ),
								'label'    => esc_html__('Property Search Timeline', $td ),
								'required' => false,
							),

							array(
								'name' => 'loanAmount',
								'label' => esc_html__( 'Loan Amount', $td ),
								'tooltip' => esc_html__( 'Amount of money (dollars and cents) for which the applicant is applying.', $td ),
								'required' => false,
							),
							array(
								'name' => 'purchasePrice',
								'label' => esc_html__( 'Purchase Price', $td ),
								'tooltip' => esc_html__( 'Purchase price for the subject property of the loan. Only supported for new URLA mortgage applications.', $td ),
								'required' => false,					
							),
							array(
								'name' => 'communityId', 
								'label' => esc_html__( 'Community ID', $td ),
								'required' => false,
							),
							
							// PARTY OBJECT
							// Name
							array(
								'name'     => 'party.name.firstName',
								'label'    => esc_html__('First Name', $td ),
								'required' => true,
							),
							array(
								'name'     => 'party.name.middleName',
								'label'    => esc_html__('Middle Name', $td ),
								'required' => false,
							),
							array(
								'name'     => 'party.name.lastName',
								'label'    => esc_html__('Last Name', $td ),
								'required' => true,
							),
							array(
								'name'     => 'party.name.suffixName',
								'label'    => esc_html__('Suffix Name', $td ),
								'required' => false,
							),
							array(
								'name'       => 'party.email',
								'label'      => esc_html__('Email', $td ),
								'required'   => false,
								'field_type' => array('email', 'hidden'),
								'tooltip' => esc_html__('This is the tooltip', $td ),
							),

							// skipping SSN/EINs for now

							array(
								'name' => 'party.dateOfBirth', 
								'label' => esc_html__( 'Date of Birth', $td ),
								'required' => false,
							),
							array(
								'name'       => 'party.homePhone',
								'label'      => esc_html__('Home Phone', $td ),
								'required'   => false,
								'field_type' => 'phone',
							),

							// CURRENT ADDRESS
							array(
								'name'       => 'party.currentAddress.streetAddressLine1',
								'label'      => esc_html__('Current Address Line 1', $td ),
								'required'   => false,
							),
							array(
								'name'       => 'party.currentAddress.streetAddressLine2',
								'label'      => esc_html__('Current Address Line 2', $td ),
								'required'   => false,
							),
							array(
								'name'       => 'party.currentAddress.city',
								'label'      => esc_html__('Current Address City', $td ),
								'required'   => false,
							),
							array(
								'name'       => 'party.currentAddress.state',
								'label'      => esc_html__('Current Address State', $td ),
								'required'   => false,
							),
							array(
								'name'       => 'party.currentAddress.zipCode',
								'label'      => esc_html__('Current Address ZIP Code', $td ),
								'required'   => false,
							),
							array(
								'name'       => 'party.currentAddress.zipCodePlusFour',
								'label'      => esc_html__('Current Address ZIP+4', $td ),
								'required'   => false,
							),
							array(
								'name' => 'countyName',
								'label' => esc_html__( 'Current Address County Name', $td ),
								'required' => false,
							),
							array(
								'name' => 'moveInDate',
								'label' => esc_html__( 'Move In Date', $td ),
								'tooltip' => esc_html__( 'UTC Timestamp of the move in for current address, eg: 2018-10-03T20:07:27+00:00', $td ),
								'required' => false,
							),

							// skipping mailing address right now

							// CRM / Leads
							array(
								'name' => 'crmId',
								'label' => esc_html__( 'CRM ID', $td ),
								'tooltip' => esc_html__( 'Unique identifier in the lender CRM system.', $td ),
								'required' => false,
							),
							array(
								'name' => 'leadId',
								'label' => esc_html__( 'Lead ID', $td ),
								'tooltip' => esc_html__( 'Unique identifier in the system that referred the lead to Blend. Usually this should be set to the Gravity Forms entry ID.', $td ),
								'required' => false,
							),

							// APPLICATION SOURCE
							array(
								'name' => 'applicationSource.type',
								'label' => esc_html__( 'Application Source Type', $td ),
								'tooltip' => esc_html__( 'Type of system the application came from. If set to Other, it is recommended to also set a name. Must be one of: LOS, CRM, Other', $td ),
								'required' => false,
							),
							array(
								'name' => 'applicationSource.name',
								'label' => esc_html__( 'Application Source Name', $td ),
								'tooltip' => esc_html__( 'Name of the system creating the application. Eg: WordPress, Gravity Forms, Website, etc.', $td ),
								'required' => false,
							),

							// LOAN INFO
							array(
								'name' => 'interestRate',
								'label' => esc_html__( 'Interest Rate', $td ),
								'tooltip' => esc_html__( 'Unique identifier in the lender CRM system.', $td ),
								'required' => false,
							),
							array(
								'name' => 'mortgageType',
								'label' => esc_html__( 'Mortgage Type', $td ),
								'tooltip' => esc_html__( 'Describes the type of mortgage. Must be one of: CONVENTIONAL, FHA, LOCAL_AGENCY, OTHER, PUBLIC_AND_INDIAN_HOUSING, STATE_AGENCY, USDARURAL_DEVELOPMENT, VA', $td ),
								'required' => false,
							),

							// Skipping customMetadata for now

							array(
								'name' => 'branchIdOverride',
								'label' => esc_html__( 'Branch ID Override', $td ),
								'tooltip' => esc_html__( 'A specific branch ID, used for origination attribution, that when set takes precedence over the originating user\'s.', $td ),
								'required' => false,
							),

							// Loan Officer
							array(
								'name' => 'assignee',
								'label' => esc_html__( 'Assignee', $td ),
								'tooltip' => esc_html__( 'The Blend ID of the Loan Officer to assign to the application.' ),
								'required' => false,
							),
						),
					),
				),
			),
		);
		
		$settings_fields[0][] = array(
			'name'           => 'condition',
			'label'          => esc_html__('Condition', $td ),
			'type'           => 'feed_condition',
			'checkbox_label' => esc_html__('Enable Condition', $td ),
			'instructions'   => esc_html__('Process this Blend feed if', $td ),
		);

		return $settings_fields;
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

	/**
	 * Use custom avatar for Blend Feed notes added to entries
	 *
	 * @return string URL for an image.
	 */
	public function note_avatar() {
		return $this->get_base_url() . '/../blend-logo.png';
	}
}
