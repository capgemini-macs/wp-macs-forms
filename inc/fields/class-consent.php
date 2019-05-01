<?php

namespace MACS_Forms\Fields;

class Consent extends Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'consent';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Consent';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-info
';

	/**
	 * Render the field on front-end
	 */
	public function render_field() {
		?>
			<div class="mf_field mf_field--consent mf-required" data-validate="checkboxes">
				<div class="mf_consent__wrapper">
					<input type="checkbox" id="<?php echo esc_attr( $this->id ); ?>" name="<?php echo esc_attr( $this->id ); ?>[]" value="<?php echo esc_attr( $this->id ); ?>" required>
					<label for="<?php echo esc_attr( $this->id ); ?>">

						<?php
						echo wp_kses(
							sprintf( '%s<span class="mf-required--symbol">*</span>', str_replace( 'rn', '<br>', $this->label ) ),
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
						?>
					</label>
				</div>
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
					'type'  => 'textarea',
					'name'  => 'label',
					'label' => __( 'Field label (textarea):', 'wp-macs-forms' ),
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
					'label' => __( 'Pardot key:', 'wp-macs-forms' ),
					'value' => $this->get_value( 'pardot_handler' ),
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
			return new \WP_Error( 'missing_required_field', __( 'Required Field is missing', 'macs_forms' ), $this->name );
		}

		$output = array_map(
			function( $item ) {
				return sanitize_textarea_field( $item );
			},
			(array) $input
		);

		return $output;
	}
}
