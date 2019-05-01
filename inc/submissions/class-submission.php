<?php
namespace MACS_Forms\Submissions;

use MACS_Forms;
use MACS_Forms\Forms\Form as Form;

class Submission {

	/**
	 * Submission ID (WP_Post ID)
	 *
	 * @var int
	 */
	public $sub_id = 0;

	/**
	 * Related form ID (WP_Post ID)
	 *
	 * @var int
	 */
	public $form_id = 0;

	/**
	 * Fields allowed for the parent form
	 *
	 * @var array
	 */
	public $fields = [];

	/**
	 * Sumission saved data
	 *
	 * @var array
	 */
	public $data = [];

	/**
	 * Validation errors
	 *
	 * @var array
	 */
	private $validation_errors = [];

	/**
	 * Submission constructor
	 */
	public function __construct( $sub_id = 0 ) {

		$this->sub_id = absint( $sub_id );
		$this->init();
	}

	/**
	 * Initialize Submission instance
	 * Retrieves fields from parent form and tries to get saved data
	 */
	protected function init() {
		$this->get_related_form();
		$this->get_fields();
		$this->get_data();
	}

	/**
	 * Retrieve submission's related form
	 *
	 * @return mixed (int|bool) form ID on success or false
	 */
	public function get_related_form() {

		if ( ! empty( $this->form_id ) ) {
			return $this->form_id;
		}

		if ( empty( $this->sub_id ) ) {
			return false;
		}

		$this->form_id = get_post_meta( $this->sub_id, 'form_id', true );

		return $this->form_id;
	}

	/**
	 * Retrieve saved submission data from database
	 *
	 * @return array
	 */
	public function get_data() {
		if ( empty( $this->sub_id ) ) {
			return;
		}

		$this->get_fields();

		$data = [];

		foreach ( $this->fields as $field ) {
			if ( empty( $field->id ) || empty( $field->type ) ) {
				continue;
			}

			$data[ $field->id ] = get_post_meta( $this->sub_id, $field->id, true );
		}

		$this->data = $data;

		return $this->data;
	}

	/**
	 * Retrieve fields allowed for a submission from a relatetd form
	 *
	 * @return array
	 */
	public function get_fields() {

		if ( ! empty( $this->fields ) ) {
			return $this->fields;
		}

		$fields = [];

		if ( empty( $this->form_id ) ) {
			return $fields;
		}

		$form = MACS_Forms\Builder::get_instance()->make_form( $this->form_id );

		if ( ! empty( $form->fields ) ) {
			foreach ( $form->fields as $field ) {
				$field->saved_value   = get_post_meta( $this->sub_id, $field->id, true );
				$fields[ $field->id ] = $field;
			}
		}

		$this->fields = $fields;

		return $this->fields;
	}

	/**
	 * Insert new submission to DB
	 *
	 * @return int
	 */
	public function insert( $data ) {
		$data = $this->prepare_input_data( $data );

		if ( false === $data ) {
			return 0;
		}

		$submission_post = [
			'post_type'   => 'mf_sub',
			'post_status' => 'publish',
			'post_title'  => sprintf( 'mf_submission_%1$s_%2$d', $data['form_id'], time() ),
			'meta_input'  => $data,
		];

		$new_sub_id = wp_insert_post( $submission_post, true );

		return $new_sub_id;
	}

	/**
	 * Delete submission from DB
	 *
	 * @return mixed (\WP_Error|bool)
	 */
	public function delete() {

		if ( ! $this->sub_id ) {
			return new \WP_Error( 'no_sub_id', __( 'Set object\'s sub_id field before calling this method', 'macs_forms' ) );
		}

		if ( false === wp_delete_post( $this->sub_id, true ) ) {
			return new \WP_Error( 'deletion_failed', __( 'Deletion failed!', 'macs_forms' ) );
		}

		return true;
	}

	/**
	 * Format and validate user input.
	 * Only fields defined in parent form are allowed.
	 * Passed values get validated with method defined in fields classes.
	 *
	 * @NOTE: 'form_id' key must be present in data array
	 *        to maintain relationship between submission and related form.
	 *
	 * @param array $input_data user input array formatted as field_id => field_value
	 */
	public function prepare_input_data( $input_data ) {

		// reset errors
		$this->validation_errors = [];

		// [form_id] field is required
		if ( empty( $input_data['form_id'] ) || ! absint( $input_data['form_id'] ) ) {
			$this->validation_errors[] = new \WP_Error( 'missing_form_id', __( 'Submission data array must contain proper form_id', 'macs_forms' ) );
		}

		// Set related form ID
		if ( empty( $this->form_id ) ) {
			$this->form_id = absint( $input_data['form_id'] );
		}

		$allowed_fields = $this->get_fields();

		$clean_data = [
			'form_id' => absint( $input_data['form_id'] ),
		];

		// Check input data against form fields
		foreach ( (array) $input_data as $field_id => $value ) {

			$field_obj = ! empty( $allowed_fields[ $field_id ] ) ? $allowed_fields[ $field_id ] : '';

			// Field not present in form, ignore it
			if ( empty( $field_obj ) ) {
				continue;
			}

			// Validate input data with field's validation method
			$validated = $field_obj->validate_input( $value );

			if ( is_wp_error( $validated ) ) {
				$this->validation_errors[] = $validated;
			} else {
				$clean_data[ $field_id ] = $validated;
			}
		}

		if ( count( $this->validation_errors ) ) {
			return false;
		}

		return $clean_data;
	}

	/**
	 * Return errors that occured during Submission save attempts
	 *
	 * @return array array of \WP_Errors
	 */
	public function get_errors() {
		return $this->validation_errors;
	}
}
