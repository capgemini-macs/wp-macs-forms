<?php

namespace MACS_Forms\Fields;

class Numbers extends Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'numbers';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Numbers';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-plus';// to change

	/**
	 * Render the field on front-end
	 */
	public function render_field() {
		?>
			<div class="mf_field mf_field--number <?php echo esc_attr( $this->get_render_required_class() ); ?>" data-validate="number">
				<label for="<?php echo esc_attr( $this->id ); ?>" /><?php echo esc_html( $this->label ); ?></label>
				<input type="number" id="<?php echo esc_attr( $this->id ); ?>" class="mf_field__input empty" name="<?php echo esc_attr( $this->id ); ?>" <?php echo esc_attr( $this->get_render_required() ); ?> />
			</div>
		<?php
	}

	/**
	 * Render the field's settings panel in Form Builder
	 */
	public function render_field_settings() {
		?>
		<div class="mf-row">
			<?php
			$this->render_option(
				[
					'type'  => 'text',
					'name'  => 'label',
					'label' => __( 'Field label:', 'wp-macs-forms' ),
					'value' => $this->get_value( 'label' ),
				]
			);

			$this->render_option(
				[
					'type'  => 'text',
					'name'  => 'error_msg',
					'label' => __( 'Error message:', 'wp-macs-forms' ),
					'value' => $this->get_value( 'error_msg' ),
				]
			);

			$this->render_option(
				[
					'type'  => 'text',
					'name'  => 'pardot_handler',
					'label' => __( 'Field key:', 'wp-macs-forms' ),
					'value' => $this->get_value( 'pardot_handler' ),
				]
			);

			$this->render_option(
				[
					'type'    => 'checkbox',
					'name'    => 'is_required',
					'label'   => __( 'Make this field required:', 'wp-macs-forms' ),
					'value'   => $this->get_value( 'is_required' ),
					'checked' => '',
					'class'   => '',
				]
			);
			?>
		</div>
		<?php
	}

	/**
	 * Function to use for validation of user input on this field type
	 */
	public function validate_input( $input ) {

		if ( ! is_numeric( $input ) ) {
			return new \WP_Error( 'missing_number_field', __( 'Number field is missing', 'macs_forms' ), $this->name );
		}

		if ( true === $this->is_required && is_empty( $input ) || ! is_numeric( $input ) ) {
			return new \WP_Error( 'missing_required_field', __( 'Required Field is missing', 'macs_forms' ), $this->name );
		}

		return sanitize_text_field( $input );

	}
}
