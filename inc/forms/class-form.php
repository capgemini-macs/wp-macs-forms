<?php
/**
 * Single Form Class
 */

namespace MACS_Forms\Forms;

use MACS_Forms;
use MACS_Forms\Fields\Field as Field;
use MACS_Forms\Fields\Field_Types as Field_Types;
use MACS_Forms\Submissions\Submission as Submission;

class Form {

	/**
	 * Form ID (WP post ID)
	 *
	 * @var int
	 */
	public $form_id = 0;

	/**
	 * Form name
	 *
	 * @var string
	 */
	public $name = 0;

	/**
	 * Fields objects
	 *
	 * @var array
	 */
	public $fields = [];

	/**
	 * Form settings
	 *
	 * @var
	 */
	public $settings = [];

	/**
	 * Form constructor
	 */
	public function __construct( $form_post_id = 0 ) {
		$this->form_id = absint( $form_post_id );
		$this->name    = sanitize_title_with_dashes( get_the_title( $form_post_id ) );
		$this->init();
	}

	/**
	 * Initialize Form instance
	 */
	protected function init() {

		// populate with fields
		$this->get_fields();
	}

	/**
	 * Retrieve fields data from database
	 *
	 * @return array
	 */
	public function get_fields() {

		if ( ! $this->form_id ) {
			return [];
		}

		$fields_data = get_post_meta( $this->form_id, 'mf_fields', true );

		// format and validate fields data
		$fields_settings = $this->format_fields_data( $fields_data );

		// Create field objects
		foreach ( $fields_settings as $field_data ) {

			$field = MACS_Forms\Builder::get_instance()->make_field( $field_data['type'], $field_data );

			if ( null === $field ) {
				continue;
			}

			$this->fields[] = $field;
		}

		return $this->fields;
	}

	/**
	 * TODO: Retrieve form's submissions
	 */
	public function get_submissions() {

	}

	/**
	 * TODO: Retrieve IDs of posts that use this form's shortcode
	 */
	public function get_linked_posts() {

	}

	/**
	 * Render form's HTML on front-end
	 */
	public function render_form() {

		$thankyou_msg = get_post_meta( $this->form_id, 'mf_form_ty_msg', true );

		?>
			<form id="mf_form_<?php echo esc_attr( $this->form_id ); ?>" class="mf_form__form" action="" method="post" enctype="multipart/form-data">
				<?php

				do_action( 'mf_before_form_fields', $this->form_id );

				foreach ( (array) $this->fields as $field ) {

					if ( ! $field instanceof Field ) {
						continue;
					}

					$field->render_field();
				}

				do_action( 'mf_after_form_fields', $this->form_id );
				?>
				<input type="hidden" name="form_id" data-validate="number" class="mf_form__id" value="<?php echo absint( $this->form_id ); ?>" />
				<input type="hidden" name="wp_rest_nonce" class="mf_form__wp_rest_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" />
			</form>

			<div class="mf_form__success">
				<?php
				if ( ! empty( $thankyou_msg ) ) {

					echo wp_kses(
						str_replace( "\r\n", '<br>', $thankyou_msg ),
						[
							'br'     => [],
							'strong' => [],
							'em'     => [],
							'b'      => [],
							'br'     => [],
							'span'   => [
								'class' => [],
							],
							'a'      => [
								'href'    => [],
								'_target' => [],
							],
						]
					);

				} else {
					?>
				<p><?php echo esc_html__( 'Thank You! We have received your form submission.', 'macs_forms' ); ?>
				<?php } ?>
			</div>
			<div class="mf_form__errors">
				<p><?php echo esc_html__( 'We are sorry, the form submission failed. Please try again.', 'macs_forms' ); ?>
			</div>
		<?php
	}

	/**
	 * Render form fields for dashboard edit screen (Form Builder)
	 */
	public function render_admin_form_fields() {

		if ( empty( $this->fields ) ) {
			return;
		}

		foreach ( $this->fields as $field ) {
			$field->render_admin_field_form();
		}
	}

	/**
	 * Validates and formats form fields settings pulled from post meta
	 *
	 * @return array
	 */
	private function format_fields_data( $fields_data ) {

		$form_fields = [];

		if ( ! is_array( $fields_data ) ) {
			return $form_fields;
		}

		foreach ( $fields_data as $field ) {

			if ( empty( $field ) ) {
				continue;
			}

			$field_data = [];

			foreach ( (array) $field as $setting ) {

				if ( empty( $setting->name ) ) {
					continue;
				}

				// Overwrite key if it's field ID setting
				$name = 'mf_field_id' === $setting->name ? 'id' : $setting->name;

				$field_data[ $name ] = $setting->value;
			}

			// Make sure all required data is provided
			if ( false === $this->validate_form_field_data( $field_data ) ) {
				continue;
			}

			$form_fields[] = $field_data;
		}

		return $form_fields;
	}

	/**
	 * Validate form field's settings and make sure field type is supported
	 */
	private function validate_form_field_data( $field_settings ) {

		$field_types = new Field_Types();

		if ( empty( $field_settings['type'] ) ) {
			return false;
		}

		if ( empty( $field_settings['id'] ) ) {
			return false;
		}

		// Check against allowed fields array
		if ( null === $field_types->get_field_class( $field_settings['type'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Submit form
	 * Store form's submission
	 * @TODO: do custom action if set
	 *
	 * @return mixed
	 */
	public function submit( $input_data ) {

		$pre_sub = MACS_Forms\Builder::get_instance()->make_submission();
		$sub     = $pre_sub->insert( $input_data );

		// Failed at inserting post, return Submission object with errors
		if ( empty( $sub ) ) {
			return $pre_sub->get_errors();
		}

		// Post data to Pardot
		$this->maybe_post_to_pardot( $sub );

		// Send notifications
		$this->send_notifications( $sub );

		// Return \WP_Post id
		return $sub;
	}

	/**
	 * Get custom error messages for form fields and return as array field ID => error message
	 *
	 * @return array
	 */
	public function get_error_messages_array() {
		if ( empty( $this->fields ) ) {
			return [];
		}

		$messages = [];

		foreach ( (array) $this->fields as $field ) {

			if ( empty( $field->error_msg ) ) {
				continue;
			}

			$messages[ $field->id ] = $field->error_msg;
		}

		return $messages;
	}

	/* Posts form submission data to Pardot handler if configured to
	 *
	 * @param Submission $sub
	 * @return bool
	 */
	protected function maybe_post_to_pardot( $sub ) {

		// get form settings from form post
		$has_pardot = get_post_meta( $this->form_id, 'mf_form_has_pardot', true );
		$pardot_url = get_post_meta( $this->form_id, 'mf_form_pardot_handler', true );

		if ( empty( $has_pardot ) || empty( $pardot_url ) || empty( $sub->fields ) ) {
			return false;
		}

		$body = [];

		foreach ( (array) $sub->fields as $field ) {
			if ( empty( $field->pardot_handler ) ) {
				continue;
			}

			$value = is_array( $field->saved_value ) ? implode( ';', $field->saved_value ) : $field->saved_value;

			$body[ $field->pardot_handler ] = $value;
		}

		// Send the fields to Pardot form handler.
		$response = wp_remote_post(
			$pardot_url,
			[
				'body'        => $body,
				'redirection' => 0,
			]
		);

		// Handle the error. Should be internal not user facing.
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			do_action( 'mf_pardot_submission_fail', $this->form_id, $sub->sub_id, $body );
			return false;
		}

		return true;
	}

	protected function send_notifications( $sub ) {

		// get form settings from form post
		$should_notify = get_post_meta( $this->form_id, 'mf_form_admin_notify', true );
		$mail_to       = get_post_meta( $this->form_id, 'mf_form_admin_custom_mail', true );

		if ( empty( $should_notify ) || empty( $mail_to ) || empty( $sub->fields ) ) {
			return;
		}

		// build mail body
		$message  = sprintf( '<p>Form: %1$s, (ID %2$s)</p>', $this->name, $this->form_id );
		$message .= '<table><thead><th>Field</th><th>Value</th></thead><tbody>';

		foreach ( (array) $sub->fields as $field ) {
			if ( empty( $field->label ) || 'submit' === $field->type ) {
				continue;
			}

			$value = is_array( $field->saved_value ) ? implode( ';', $field->saved_value ) : $field->saved_value;

			$message .= sprintf( '<tr><td>%1$s</td><td>%2$s</td></tr>', esc_html( $field->label ), esc_html( $value ) );
		}

		$message .= '</table></tbody>';

		// define headers
		$headers   = [];
		$headers[] = 'From: Capgemini WCMS';
		$headers[] = 'Reply-To: noreply@capgemini.com';
		$headers[] = 'Content-Type: text/html';
		$headers[] = 'charset=utf-8';

		wp_mail( $mail_to, 'Capgemini WCMS Form Submission', $message, $headers );
	}
}
