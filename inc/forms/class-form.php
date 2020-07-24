<?php
/**
 * Single Form Class
 */

namespace Proper_Forms\Forms;

use Proper_Forms;
use Proper_Forms\Fields\Field as Field;
use Proper_Forms\Fields\Field_Types as Field_Types;
use Proper_Forms\Submissions\Submission as Submission;

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
	 * Thank you message
	 *
	 * @var string
	 */
	public $thank_you_message = '';

	/**
	 * Redirect url
	 *
	 * @var string
	 */
	public $redirect_url = '';

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

		// get form settings
		$this->thank_you_message = get_post_meta( $this->form_id, 'pf_form_ty_msg', true );
		$this->redirect_url      = get_post_meta( $this->form_id, 'pf_form_redirect_url', true );
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

		$fields_data = get_post_meta( $this->form_id, 'pf_fields', true );

		// format and validate fields data
		$fields_settings = $this->format_fields_data( $fields_data );

		// Create field objects
		foreach ( $fields_settings as $field_data ) {

			$field = Proper_Forms\Builder::get_instance()->make_field( $field_data['type'], $field_data );

			if ( null === $field ) {
				continue;
			}

			$this->fields[] = $field;
		}

		return $this->fields;
	}

	/**
	 * Form's submissions
	 */
	public static function get_all_submissions( $form_id ) {

		$form = Proper_Forms\Builder::get_instance()->make_form( $form_id );

		if ( empty( $form->fields ) ) {
			return;
		}

		$page_number = 1;
		$keep_going  = 1;
		$data        = [];

		while ( $keep_going ) {

			$query = new \WP_Query(
				[
					'post_type'           => 'pf_sub',
					'posts_per_page'      => 100,
					'paged'               => $page_number,
					'post_status'         => 'publish',
					'meta_query'          => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						[
							'key'     => 'form_id',
							'value'   => $form_id,
							'compare' => '=',
						],
					],
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'fields'              => 'ids',
				]
			);

			if ( ! $query->have_posts() ) {
				$keep_going = 0;
				continue;
			}

			// Get submission data
			foreach ( $query->posts as $sub_id ) {
				$downloader = new Proper_Forms\Submissions\Downloads( $form_id );
				$data[]     = $downloader->get_submission_data_array( $sub_id, $form );
			}

			$page_number++;
		}

		return $data;
	}

	/**
	 * Form's last submissions
	 */
	public static function get_last_submission( $form_id ) {

		$form = Proper_Forms\Builder::get_instance()->make_form( $form_id );

		if ( empty( $form->fields ) ) {
			return;
		}

		$data = [];

		$query = new \WP_Query(
			[
				'post_type'           => 'pf_sub',
				'posts_per_page'      => 1,
				'paged'               => 1,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'post_status'         => 'publish',
				'meta_query'          => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => 'form_id',
						'value'   => $form_id,
						'compare' => '=',
					],
				],
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'fields'              => 'ids',
			]
		);

		// Get submission data
		foreach ( $query->posts as $sub_id ) {
			$downloader = new Proper_Forms\Submissions\Downloads( $form_id );
			$data[]     = $downloader->get_submission_data_array( $sub_id, $form );
		}

		return $data;
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

		$thankyou_msg = get_post_meta( $this->form_id, 'pf_form_ty_msg', true );

		// Remove old Ninja Forms placeholders.
		$thankyou_msg = preg_replace_callback(
			'/{field:(.[\S]+)}/m',
			function( $matches ) {
				return '';
			},
			$thankyou_msg
		);
		?>
			<form id="pf_form_<?php echo esc_attr( $this->form_id ); ?>" class="pf_form__form" action="" method="post" enctype="multipart/form-data">
				<?php

				do_action( 'pf_before_form_fields', $this->form_id );

				foreach ( (array) $this->fields as $field ) {

					if ( ! $field instanceof Field ) {
						continue;
					}

					$field->render_field();
				}

				do_action( 'pf_after_form_fields', $this->form_id );
				?>
				<input type="hidden" name="form_id" data-validate="number" class="pf_form__id" value="<?php echo absint( $this->form_id ); ?>" />
				<input type="hidden" name="form_title" class="pf_form__title" value="<?php echo esc_attr( $this->name ); ?>" />
				<input type="hidden" name="wp_rest_nonce" class="pf_form__wp_rest_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" />
			</form>

			<div class="pf_form__success">
				<?php
				if ( ! empty( $thankyou_msg ) ) {

					echo wp_kses(
						str_replace( "\r\n", '<br>', $thankyou_msg ),
						[
							'br'     => [],
							'strong' => [],
							'b'      => [],
							'em'     => [],
							'u'      => [],
							'p'      => [
								'class' => [],
								'style' => [],
							],
							'h1'     => [
								'class' => [],
								'style' => [],
							],
							'h2'     => [
								'class' => [],
								'style' => [],
							],
							'h3'     => [
								'class' => [],
								'style' => [],
							],
							'h4'     => [
								'class' => [],
								'style' => [],
							],
							'h5'     => [
								'class' => [],
								'style' => [],
							],
							'h6'     => [
								'class' => [],
								'style' => [],
							],
							'ul'     => [],
							'ol'     => [],
							'li'     => [
								'class' => [],
								'style' => [],
							],
							'span'   => [
								'class' => [],
								'style' => [],
							],
							'a'      => [
								'href'   => [],
								'title'  => [],
								'target' => [],
								'rel'    => [],
							],
						]
					);
				} else {
					?>
				<p><?php echo esc_html__( 'Thank You! We have received your form submission.', 'proper-forms' ); ?>
				<?php } ?></p>
			</div>
			<div class="pf_form__errors">
				<p><?php echo esc_html__( 'We are sorry, the form submission failed. Please try again.', 'proper-forms' ); ?></p>
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
				$name = 'pf_field_id' === $setting->name ? 'id' : $setting->name;

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

		$pre_sub = Proper_Forms\Builder::get_instance()->make_submission();
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
		$has_pardot = get_post_meta( $this->form_id, 'pf_form_has_pardot', true );
		$pardot_url = get_post_meta( $this->form_id, 'pf_form_pardot_handler', true );

		// Get submission object
		$sub = Proper_Forms\Builder::get_instance()->make_submission( $sub );

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
			do_action( 'pf_pardot_submission_fail', $this->form_id, $sub->sub_id, $body );
			return false;
		}

		return true;
	}

	protected function send_notifications( $sub ) {

		// get form settings from form post
		$should_notify = get_post_meta( $this->form_id, 'pf_form_admin_notify', true );
		$mail_to       = get_post_meta( $this->form_id, 'pf_form_admin_custom_mail', true );

		if ( empty( $should_notify ) || empty( $mail_to ) ) {
			return;
		}

		$pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
		preg_match_all( $pattern, $mail_to, $matches );

		$admins_mail_to = $matches[0];

		// Get submission object
		$sub = Proper_Forms\Builder::get_instance()->make_submission( $sub );

		if ( empty( $sub->fields ) ) {
			return;
		}

		// build mail body
		$message  = sprintf( '<h3>Form: %1$s, (ID %2$s)</h3>', $this->name, $this->form_id );
		$message .= '<table style="width:100%"><thead style="background:#f3f3f3"><th style="width:30%; min-width:150px; ">Field</th><th style="width:70%">Value</th></thead><tbody>';

		foreach ( (array) $sub->fields as $field ) {
			if ( empty( $field->label ) || 'submit' === $field->type ) {
				continue;
			}

			$value = is_array( $field->saved_value ) ? implode( ';', $field->saved_value ) : $field->saved_value;

			if ( 'file_upload' === $field->type ) {
				$file  = Proper_Forms\Builder::get_instance()->make_file( $field->saved_value );
				$value = '<a href="' . esc_url( $file->url ) . '">' . esc_html( $field->saved_value ) . '</a>';
			}

			$message .= sprintf( '<tr><td style="padding:8px">%1$s</td><td style="padding:8px">%2$s</td></tr>', esc_html( $field->label ), wp_kses_post( $value ) );
		}

		$message .= '</table></tbody>';

		// define headers
		$headers   = [];
		$headers[] = 'From: Capgemini WCMS';
		$headers[] = 'Reply-To: noreply@capgemini.com';
		$headers[] = 'Content-Type: text/html';
		$headers[] = 'charset=utf-8';

		wp_mail( $admins_mail_to, 'Capgemini WCMS Form Submission', $message, $headers );

	}
}
