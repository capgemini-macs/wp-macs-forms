<?php

namespace Proper_Forms\Fields;

class Text extends Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'text';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Text Field';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-editor-textcolor';

	/**
	 * Render the field on front-end
	 */
	public function render_field() {
		?>
			<div class="pf_field pf_field--text <?php echo esc_attr( $this->get_render_required_class() ); ?>" data-validate="text">
				<label for="<?php echo esc_attr( $this->id ); ?>"><?php echo esc_html( $this->label ); ?>
					<?php echo wp_kses_post( $this->get_render_required_symbol() ); ?></label>
				<input type="text" id="<?php echo esc_attr( $this->id ); ?>" class="pf_field__input empty" name="<?php echo esc_attr( $this->id ); ?>" <?php echo esc_attr( $this->get_render_required() ); ?> />
			</div>
		<?php
	}

	/**
	 * Render the field's settings panel in Form Builder
	 */
	public function render_field_settings() {
		?>
		<div class="pf-row">
			<?php
			$this->render_option(
				[
					'type'    => 'text',
					'name'    => 'label',
					'label'   => __( 'Field label:', 'proper-forms' ),
					'value'   => $this->get_value( 'label' ),
					'checked' => '',
					'class'   => '',
				]
			);

			$this->render_option(
				[
					'type'    => 'text',
					'name'    => 'error_msg',
					'label'   => __( 'Error message:', 'proper-forms' ),
					'value'   => $this->get_value( 'error_msg' ),
					'checked' => '',
					'class'   => '',
				]
			);

			$this->render_option(
				[
					'type'    => 'text',
					'name'    => 'pardot_handler',
					'label'   => __( 'Field key:', 'proper-forms' ),
					'value'   => $this->get_value( 'pardot_handler' ),
					'checked' => '',
					'class'   => '',
				]
			);

			$this->render_option(
				[
					'type'    => 'checkbox',
					'name'    => 'is_required',
					'label'   => __( 'Make this field Required field:', 'proper-forms' ),
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
			return new \WP_Error( 'missing_required_field', __( 'Required Field is missing', 'proper-forms' ), $this->name );
		}

		return sanitize_text_field( $input );
	}
}
