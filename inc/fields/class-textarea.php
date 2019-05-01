<?php

namespace MACS_Forms\Fields;

class TextArea extends Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'textarea';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Textarea';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-text';

	/**
	 * Render the field on front-end
	 */
	public function render_field() {
		?>
			<div class="mf_field mf_field--textarea <?php echo esc_attr( $this->get_render_required_class() ); ?>" data-validate="text">
				<label for="<?php echo esc_attr( $this->id ); ?>" /><?php echo esc_html( $this->label ); ?>
					<?php echo wp_kses_post( $this->get_render_required_symbol() ); ?>
				</label>
				<textarea id="<?php echo esc_attr( $this->id ); ?>" class="mf_field__input empty" name="<?php echo esc_attr( $this->id ); ?>" <?php echo esc_attr( $this->get_render_required() ); ?> ></textarea>
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
					'label'   => __( 'Make this field Required field:', 'wp-macs-forms' ),
					'value'   => $this->get_value( 'is_required' ),
					'checked' => checked( 1, $this->get_value( 'is_required' ), false ),
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
		if ( true === $this->is_required && is_empty( $input ) ) {
			return new \WP_Error( 'missing_required_field', __( 'Required Field is missing', 'wp-macs-forms' ), $this->name );
		}

		return sanitize_textarea_field( $input );
	}
}
