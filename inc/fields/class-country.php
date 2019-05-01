<?php

namespace MACS_Forms\Fields;

class Country extends Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'country';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Country Field';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-list-view';

	/**
	 * Render the field on front-end
	 */
	public function render_field() {
		$current_value = ! empty( $this->saved_value ) ? $this->saved_value : $this->default_option;
		?>
			<div class="mf_field mf_field--country <?php echo esc_attr( $this->get_render_required_class() ); ?>">
				<label for="<?php echo esc_attr( $this->id ); ?>"><?php echo esc_html( $this->label ); ?>
					<?php echo wp_kses_post( $this->get_render_required_symbol() ); ?>
				</label>
				<select id="<?php echo esc_attr( $this->id ); ?>" class="mf_field__input empty" name="<?php echo esc_attr( $this->id ); ?>" data-validate="select" <?php echo esc_attr( $this->get_render_required() ); ?>>
					<?php foreach ( $this->get_country_list() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php echo selected( $value, $current_value, false ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
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
					'type'    => 'checkbox',
					'name'    => 'is_required',
					'label'   => __( 'Make this field Required field:', 'wp-macs-forms' ),
					'value'   => $this->get_value( 'is_required' ),
					'checked' => checked( 1, $this->get_value( 'is_required' ), false ),
					'class'   => '',
				]
			);

			$this->render_option(
				[
					'type'    => 'dropdown',
					'name'    => 'default_option',
					'label'   => __( 'Default country', 'wp-macs-forms' ),
					'value'   => $this->get_value( 'default_option' ),
					'options' => $this->get_country_list(),
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

	/**
	 * Defined list of countries
	 */
	public function get_country_list() {
		require __DIR__ . '/list-country.php';
		return $countries;
	}
}
