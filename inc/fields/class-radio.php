<?php

namespace MACS_Forms\Fields;

class Radio extends Multi_Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'radio';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Radio buttons';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-marker';

	/**
	 * Options string
	 *
	 * @var string
	 */
	public $default_option = '';

	/**
	 * Render the field on front-end
	 */
	public function render_field() {
		?>
			<div class="mf_field mf_field--radios <?php echo esc_attr( $this->get_render_required_class() ); ?>" data-validate="radio">
				<label for="<?php echo esc_attr( $this->id ); ?>"><?php echo esc_html( $this->label ); ?></label>
				<?php echo wp_kses_post( $this->get_render_required_symbol() ); ?>
				<ul>
				<?php
				$c             = 0;
				$current_value = ! empty( $this->saved_value ) ? $this->saved_value : $this->default_option;

				foreach ( $this->options_to_array( $this->options ) as $value => $label ) :
						$input_id = sprintf( '%1$s_%2$d', $this->id, $c );
					?>
						<li>
							<input type="radio" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $this->id ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $current_value, $value ); ?>>
							<label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); ?></label>
						</li>
					<?php
					$c++;
				endforeach;
				?>
				</ul>
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
					'type'    => 'text',
					'name'    => 'pardot_handler',
					'label'   => __( 'Field key:', 'wp-macs-forms' ),
					'value'   => $this->get_value( 'pardot_handler' ),
					'checked' => '',
					'class'   => '',
				]
			);

			$this->render_option(
				[
					'type'  => 'key_value_pairs',
					'name'  => 'options',
					'label' => __( 'Options:', 'wp-macs-forms' ),
					'value' => $this->get_value( 'options' ),
				]
			);

			$this->render_option(
				[
					'type'  => 'text',
					'name'  => 'default_option',
					'label' => __( 'Default Value:', 'wp-macs-forms' ),
					'value' => $this->get_value( 'default_option' ),
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

		return sanitize_text_field( $input );
	}
}
