<?php

namespace Proper_Forms\Fields;

class Checkbox extends Multi_Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'checkbox';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Checkboxes';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-forms';

	/**
	 * Render the field on front-end
	 */
	public function render_field() {
		?>
			<div id="<?php echo esc_attr( $this->id ); ?>" class="pf_field pf_field--checkboxes <?php echo esc_attr( $this->get_render_required_class() ); ?>" data-validate="checkboxes">
				<legend for="<?php echo esc_attr( $this->id ); ?>" /><?php echo wp_kses_post( $this->label ); ?></legend>
				<?php echo wp_kses_post( $this->get_render_required_symbol() ); ?>
				<ul>
				<?php
				$c = 0;
				foreach ( $this->options_to_array( $this->options ) as $value => $label ) :
					$input_id = sprintf( '%1$s_%2$d', $this->id, $c );
					?>
						<li>
							<input type="checkbox" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $this->id ); ?>[]" value="<?php echo esc_attr( $value ); ?>">
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
		<div class="pf-row">
			<?php
			$this->render_option(
				[
					'type'  => 'text',
					'name'  => 'label',
					'label' => __( 'Field label:', 'proper-forms' ),
					'value' => $this->get_value( 'label' ),
				]
			);

			$this->render_option(
				[
					'type'  => 'text',
					'name'  => 'error_msg',
					'label' => __( 'Error message:', 'proper-forms' ),
					'value' => $this->get_value( 'error_msg' ),
				]
			);

			$this->render_option(
				[
					'type'  => 'text',
					'name'  => 'pardot_handler',
					'label' => __( 'Field key:', 'proper-forms' ),
					'value' => $this->get_value( 'pardot_handler' ),
				]
			);

			$this->render_option(
				[
					'type'  => 'key_value_pairs',
					'name'  => 'options',
					'label' => __( 'Options:', 'proper-forms' ),
					'value' => $this->get_value( 'options' ),
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

		$output = array_map(
			function( $item ) {
				return sanitize_text_field( $item );
			},
			(array) $input
		);

		return $output;
	}
}
