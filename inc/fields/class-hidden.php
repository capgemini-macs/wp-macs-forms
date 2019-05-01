<?php

namespace MACS_Forms\Fields;

class Hidden extends Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'hidden';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Hidden Field';

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
			<div class="mf_field mf_field--hidden <?php echo esc_attr( $this->get_render_required_class() ); ?>">
				<label for="<?php echo esc_attr( $this->id ); ?>" /><?php echo esc_html( $this->label ); ?>
					<?php echo wp_kses_post( $this->get_render_required_symbol() ); ?></label>
				<input type="hidden" id="<?php echo esc_attr( $this->id ); ?>" class="mf_field__input empty" name="<?php echo esc_attr( $this->id ); ?>" data-validate="text" value="<?php echo esc_attr( get_the_title( $this->ID ) ); ?>" <?php echo esc_attr( $this->get_render_required() ); ?> />
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
					'type'    => 'text',
					'name'    => 'label',
					'label'   => __( 'Field label:', 'wp-macs-forms' ),
					'value'   => 'Page title',
					'checked' => '',
					'class'   => '',
				]
			);

			$this->render_option(
				[
					'type'    => 'hidden',
					'name'    => 'error_msg',
					'label'   => __( 'Error message:', 'wp-macs-forms' ),
					'value'   => $this->get_value( 'error_msg' ),
					'checked' => '',
					'class'   => '',
				]
			);

			$this->render_option(
				[
					'type'    => 'text',
					'name'    => 'pardot_handler',
					'label'   => __( 'Field key:', 'wp-macs-forms' ),
					'value'   => $this->get_value( 'pardot_handler' ),
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

		if ( true === $this->is_required && is_empty( $input ) ) {
			return new \WP_Error( 'missing_required_field', __( 'Required Field is missing', 'wp-macs-forms' ), $this->name );
		}

		return sanitize_text_field( $input );
	}
}
