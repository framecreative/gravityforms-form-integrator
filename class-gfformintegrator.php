<?php

GFForms::include_feed_addon_framework();

class GFFormIntegrator extends GFFeedAddOn {

	protected $_version = GF_FORM_INTEGRATOR_VERSION;
	protected $_min_gravityforms_version = '1.9.16';
	protected $_slug = 'simplefeedaddon';
	protected $_path = 'simplefeedaddon/gravityforms-form-integrator.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Form Integrator';
	protected $_short_title = 'Form Integrator';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFFormIntegrator
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFFormIntegrator();
		}

		return self::$_instance;
	}

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init() {

		parent::init();

	}

	public function supported_notification_events( $form ) {
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		return array(
			'intergation_success'  => esc_html__( 'Integration Success (200 http response)', 'simplefeedaddon' ),
			'intergation_failure' => esc_html__( 'Integration Failure (non 200 response)', 'simplefeedaddon' ),
		);
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
		$feedName  = $feed['meta']['feedName'];
		$submitUrl = $feed['meta']['submitUrl'];

		// Retrieve the name => value pairs for all fields mapped in the 'mappedFields' field map.
		$formMap = $this->get_dynamic_field_map_fields( $feed, 'formData' );

		$extraMap = $this->get_generic_map_fields( $feed, 'extraData' );

		// Loop through the fields from the field map setting building an array of values to be passed to the third-party service.
		$postDataValues = array();
		foreach ( $formMap as $name => $field_id ) {

			// Get the field value for the specified field id
			$postDataValues[ $name ] = $this->get_field_value( $form, $entry, $field_id );

		}

		foreach ( $extraMap as $name => $field_value ) {

			// In the case of a generic map field we can straight up use the value
			$postDataValues[ $name ] = $field_value;
		}

		// GFCommon::log_debug( print_r( $postDataValues ) );

		// Send the values to the third-party service.

        $request_args = array(
            'body' => $postDataValues,
            'timeout' => 30,
        );

        $response = wp_remote_post( $submitUrl, $request_args );


        if ( is_wp_error( $response ) ) {
	        $this->add_feed_error( '<b>Integration HTTP Error</b>\n WP Error:' . $response->get_error_message() , $feed, $entry, $form );

	        GFAPI::send_notifications( $form, $entry, 'integration_failure' );

	        return;
        }

        $status = '';

		if ( is_array( $response ) ){

			$status = $response['response']['code'];

		}

        if ( $status == 200 ) {

	        $this->add_note( $entry['id'], $feedName . ': Data Submission returned 200 ok', 'success' );
	        GFAPI::send_notifications( $form, $entry, 'integration_success' );

	        return;

        }

		$this->add_note( $entry['id'], $feedName . ': Data Submission returned ' . $status , 'warning' );
		GFAPI::send_notifications( $form, $entry, 'integration_failure' );

		// Allow other code to hook in here, possibly to set a queue / retry system
		do_action( 'form_integration_non_200_response', $form, $entry, $feed, $this );

		return;



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
	public function get_phone_field_value( $entry, $field_id, $field ) {

		// Get the field value from the Entry Object.
		$field_value = rgar( $entry, $field_id );

		// If there is a value and the field phoneFormat setting is set to standard reformat the value.
		if ( ! empty( $field_value ) && $field->phoneFormat == 'standard' && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches ) ) {
			$field_value = sprintf( '%s-%s-%s', $matches[1], $matches[2], $matches[3] );
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
				'deps'    => array( 'jquery' ),
				'strings' => array(
					'first'  => esc_html__( 'First Choice', 'simplefeedaddon' ),
					'second' => esc_html__( 'Second Choice', 'simplefeedaddon' ),
					'third'  => esc_html__( 'Third Choice', 'simplefeedaddon' ),
				),
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_settings' ),
						'tab'        => 'integrationsettings',
					),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
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
					array( 'field_types' => array( 'poll' ) ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Integration Settings', 'simplefeedaddon' ),
				'fields' => array(
					array(
						'name'    => 'textbox',
						'tooltip' => esc_html__( 'This is the tooltip', 'simplefeedaddon' ),
						'label'   => esc_html__( 'This is the label', 'simplefeedaddon' ),
						'type'    => 'text',
						'class'   => 'small',
					),
				),
			),
		);
	}

	/**
	 * Configures the settings which should be rendered on the feed edit page in the Form Settings > Simple Feed Add-On area.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Integration Instance Settings', 'simplefeedaddon' ),
				'fields' => array(
					array(
						'label'   => esc_html__( 'Integration name', 'simplefeedaddon' ),
						'type'    => 'text',
						'name'    => 'feedName',
						'tooltip' => esc_html__( 'This is the tooltip', 'simplefeedaddon' ),
						'class'   => 'large',
					),
					array(
						'label'   => esc_html__( 'Submit URL', 'simplefeedaddon' ),
						'type'    => 'text',
						'name'    => 'submitUrl',
						'tooltip' => esc_html__( 'This is the tooltip', 'simplefeedaddon' ),
						'class'   => 'large',
					),
				),
			),
            array(
                'title'  => esc_html__( 'Integration form field mapping', 'simplefeedaddon' ),
                'description' => 'Enter your field keys (input name attribute) provided by the 3rd party service and choose the gravity forms field to map it to</br>',
                'fields' => array(
                    array(
                        'name'                => 'formData',
                        'label'               => esc_html__( 'Form Data', 'sometextdomain' ),
                        'type'                => 'dynamic_field_map',
                        'limit'               => 20,
                        'exclude_field_types' => 'creditcard',
                        'tooltip'             => '<h6>' . esc_html__( 'Metadata', 'sometextdomain' ) . '</h6>' . esc_html__( 'You may send custom meta information to [...]. A maximum of 20 custom keys may be sent. The key name must be 40 characters or less, and the mapped data will be truncated to 500 characters per requirements by [...]. ', 'sometextdomain' ),
                        'validation_callback' => array( $this, 'validate_custom_meta' ),
                    ),
                ),
            ),
            array(
                'title'  => esc_html__( 'Integration hidden field mapping', 'simplefeedaddon' ),
                'description' => 'Additional key/value pairs can be provided to forward on to the 3rd party - this section is designed primarily to pass on hidden field values</br>',
                'fields' => array(
                    array(
                        'name'                => 'extraData',
                        'label'               => esc_html__( 'Extra Data', 'sometextdomain' ),
                        'type'                => 'generic_map',
                        'limit'               => 20,
                        'exclude_field_types' => 'creditcard',
                        'tooltip'             => '<h6>' . esc_html__( 'Metadata', 'sometextdomain' ) . '</h6>' . esc_html__( 'You may send custom meta information to [...]. A maximum of 20 custom keys may be sent. The key name must be 40 characters or less, and the mapped data will be truncated to 500 characters per requirements by [...]. ', 'sometextdomain' ),
                        'validation_callback' => array( $this, 'validate_custom_meta' ),
                    ),
                ),
            )
		);
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'  => esc_html__( 'Integration Name', 'simplefeedaddon' ),
			'submitUrl' => esc_html__( 'Submit URL', 'simplefeedaddon' ),
		);
	}

	/**
	 * Format the value to be displayed in the mytextbox column.
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_submitUrl( $feed ) {
		return '<p>' . rgars( $feed, 'meta/submitUrl' ) . '</p>';
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
		$key = rgar( $settings, 'apiKey' );

		return true;
	}

}
