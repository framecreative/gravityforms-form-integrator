<?php

GFForms::include_feed_addon_framework();

class GFFormIntegrator extends GFFeedAddOn {

	protected $_version = GF_FORM_INTEGRATOR_VERSION;
	protected $_min_gravityforms_version = '1.9.16';
	protected $_slug = 'simplefeedaddon';
	protected $_path = 'gravityforms-form-integrator/gravityforms-form-integrator.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Form Integrator';
	protected $_short_title = 'Form Integrator';

	public $_postDataValues;

	public $_async_feed_processing = false;

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

		$this->_async_feed_processing = ( defined('WP_ENV') && ( WP_ENV === 'live' || WP_ENV === 'production' ) );

		parent::init();

	}

	public function supported_notification_events( $form ) {
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		return array(
			'intergation_success'  => esc_html__( 'Integration Success (200 http response)', 'gravityforms-form-integrator' ),
			'intergation_failure' => esc_html__( 'Integration Failure (non 200 response)', 'gravityforms-form-integrator' ),
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

		// Reset this at the start of each feed
		$this->_postDataValues = [];

		// Retrieve the name => value pairs for all fields mapped in the 'mappedFields' field map.
		$formMap = $this->get_dynamic_field_map_fields( $feed, 'formData' );

		$extraMap = $this->get_generic_map_fields( $feed, 'extraData' );

		// Loop through the fields from the field map setting building an array of values to be passed to the third-party service.
		foreach ( $formMap as $name => $field_id ) {

			$field = GFFormsModel::get_field( $form, $field_id );

			/*
			 * Gives us a chance to write hooks to alter values, these hooks should return false if they modify the postDataValues array
			 */
			$value = apply_filters( 'gf_form_integrator_modify_dynamic_field_value', $this->get_field_value( $form, $entry, $field_id ), $name, $field, $this, $feed, $entry, $form );

			if ( ! $value ) continue;

			$this->_postDataValues[ $name ] = $value;

		}

		foreach ( $extraMap as $name => $field_value ) {

			// In the case of a generic map field we can straight up use the value
			$this->_postDataValues[ $name ] = $field_value;
		}

		if ( defined('WP_ENV') && ! ( WP_ENV === 'live' || WP_ENV === 'production' ) ) {
			GFCommon::log_debug( print_r( $this->_postDataValues ) );
		}

		$finalValues = apply_filters( 'gf_form_integrator_modify_values_pre_submit', $this->_postDataValues, $feed, $entry, $form );

		// Provide one last opportunity for people to bail on this feed by returning false
		if ( false === $finalValues ) return;

		// Send the values to the third-party service.
        $request_args = array(
            'body' => $finalValues,
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
	 * Configures the settings which should be rendered on the feed edit page in the Form Settings > Simple Feed Add-On area.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title' => esc_html__('Integration Conditional Activate','gravityforms-form-integrator'),
				'fields' => array(
					array(
						'type'           => 'feed_condition',
						'name'           => 'optin',
						'label'          => __( 'Opt-In Condition', 'gravityforms-form-integrator' ),
						'checkbox_label' => __( 'Enable Condition', 'gravityforms-form-integrator' ),
						'instructions'   => __( 'Process this example feed if', 'gravityforms-form-integrator' )
					)
				),
			),
			array(
				'title'  => esc_html__( 'Integration Instance Settings', 'gravityforms-form-integrator' ),
				'fields' => array(
					array(
						'label'   => esc_html__( 'Integration name', 'gravityforms-form-integrator' ),
						'type'    => 'text',
						'name'    => 'feedName',
						'tooltip' => esc_html__( 'Feed Name (mainly for admin reference)', 'gravityforms-form-integrator' ),
						'class'   => 'large',
					),
					array(
						'label'   => esc_html__( 'Submit URL', 'gravityforms-form-integrator' ),
						'type'    => 'text',
						'name'    => 'submitUrl',
						'tooltip' => esc_html__( 'The Url that this feed should submit to via HTTP POST', 'gravityforms-form-integrator' ),
						'class'   => 'large',
					),
				),
			),
            array(
                'title'  => esc_html__( 'Integration form field mapping', 'gravityforms-form-integrator' ),
                'description' => 'Enter your field keys (input name attribute) provided by the 3rd party service and choose the gravity forms field to map it to</br>',
                'fields' => array(
                    array(
                        'name'                => 'formData',
                        'label'               => esc_html__( 'Form Data', 'sometextdomain' ),
                        'type'                => 'dynamic_field_map',
                        'limit'               => 20,
                        'exclude_field_types' => 'creditcard',
                        'tooltip'             => '<h6>' . esc_html__( 'Metadata', 'sometextdomain' ) . '</h6>' . esc_html__( 'You may add as many name / value pairs as needed. Within the textbox you put the name of the input the external service is expecting, then choose a gravity form field to provide the value on submission', 'gravityforms-form-integrator' ),
                        'validation_callback' => array( $this, 'validate_custom_meta' ),
                    ),
                ),
            ),
            array(
                'title'  => esc_html__( 'Integration hidden field mapping', 'gravityforms-form-integrator' ),
                'description' => 'Additional key/value pairs can be provided to forward on to the 3rd party - this section is designed primarily to pass on hidden field values</br>',
                'fields' => array(
                    array(
                        'name'                => 'extraData',
                        'label'               => esc_html__( 'Extra Data', 'sometextdomain' ),
                        'type'                => 'generic_map',
                        'limit'               => 20,
                        'exclude_field_types' => 'creditcard',
                        'tooltip'             => '<h6>' . esc_html__( 'Metadata', 'sometextdomain' ) . '</h6>' . esc_html__( 'Define as many key/value pairs of static values as needed. These are sent with every request, and are mainly used for mocking hidden fields that the external service is expecting, but that do not need to be in your gravity form', 'gravityforms-form-integrator' ),
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
			'feedName'  => esc_html__( 'Integration Name', 'gravityforms-form-integrator' ),
			'submitUrl' => esc_html__( 'Submit URL', 'gravityforms-form-integrator' ),
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

		return true;
	}

}
