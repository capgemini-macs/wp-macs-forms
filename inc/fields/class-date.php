<?php

namespace MACS_Forms\Fields;

class Date extends Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'date';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Date Field';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-calendar-alt';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $format = 'DD/MM/YYYY';

	/**
	 * Render the field on front-end
	 */
	public function render_field() {
		?>
			<div class="mf_field mf_field--date <?php echo esc_attr( $this->get_render_required_class() ); ?>" data-validate="date" data-format="<?php echo esc_attr( $this->format ); ?>">
				<label for="<?php echo esc_attr( $this->id ); ?>" /><?php echo esc_html( $this->label ); ?>
					<?php echo wp_kses_post( $this->get_render_required_symbol() ); ?></label>

				<input type="text" id="<?php echo esc_attr( $this->id ); ?>" class="mf_field__input empty" name="<?php echo esc_attr( $this->id ); ?>" <?php echo esc_attr( $this->get_render_required() ); ?> />

				<div class="mf_field_info">
				<?php if ( ! empty( $this->format ) ) : ?>
					<span><?php echo esc_html__( 'Please use date format: ', 'wp-macs-forms' ); ?><?php echo esc_html( $this->format ); ?></span><br />
				<?php endif; ?>
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
					'type'    => 'text',
					'name'    => 'label',
					'label'   => __( 'Field label:', 'wp-macs-forms' ),
					'value'   => $this->get_value( 'label' ),
					'checked' => '',
					'class'   => '',
				]
			);

			$this->render_option(
				[
					'type'    => 'text',
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

			$this->render_option(
				[
					'type'    => 'dropdown',
					'name'    => 'format',
					'label'   => __( 'Date Format:', 'wp-macs-forms' ),
					'value'   => $this->get_value( 'format' ),
					'options' => [
						'DD/MM/YYYY' => __( 'DD/MM/YYYY', 'wp-macs-forms' ),
						'DD-MM-YYYY' => __( 'DD-MM-YYYY', 'wp-macs-forms' ),
						'DD.MM.YYYY' => __( 'DD.MM.YYYY', 'wp-macs-forms' ),
						'MM/DD/YYYY' => __( 'MM/DD/YYYY', 'wp-macs-forms' ),
						'MM-DD-YYYY' => __( 'MM-DD-YYYY', 'wp-macs-forms' ),
						'MM.DD.YYYY' => __( 'MM.DD.YYYY', 'wp-macs-forms' ),
						'YYYY-MM-DD' => __( 'YYYY-MM-DD', 'wp-macs-forms' ),
						'YYYY/MM/DD' => __( 'YYYY/MM/DD', 'wp-macs-forms' ),
						'YYYY.MM.DD' => __( 'YYYY.MM.DD', 'wp-macs-forms' ),
					],
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

			?>
		</div>
		<?php
	}

	private function validate_date( $input ) {

		$regex_by_format = [
			'DD/MM/YYYY' => '/(\d{2})\/(\d{2})\/(\d{4})/',
			'DD-MM-YYYY' => '/(\d{2})\-(\d{2})\-(\d{4})/',
			'DD.MM.YYYY' => '/(\d{2})\.(\d{2})\.(\d{4})/',
			'MM/DD/YYYY' => '/(\d{2})\/(\d{2})\/(\d{4})/',
			'MM-DD-YYYY' => '/(\d{2})\-(\d{2})\-(\d{4})/',
			'MM.DD.YYYY' => '/(\d{2})\.(\d{2})\.(\d{4})/',
			'YYYY-MM-DD' => '/(\d{4})\-(\d{2})\-(\d{2})/',
			'YYYY/MM/DD' => '/(\d{4})\/(\d{2})\/(\d{2})/',
			'YYYY.MM.DD' => '/(\d{4})\.(\d{2})\.(\d{2})/',
		];

		$re = $regex_by_format[ $this->format ];

		preg_match( $re, $input, $matches );

		if ( empty( $matches ) || 4 !== count( $matches ) ) {
			return false;
		}

		// cehck date components according to format
		switch ( substr( $this->format, 0, 2 ) ) {
			case 'MM':
				return checkdate( $matches[1], $matches[2], $matches[3] );
				break;

			case 'DD':
				break;
				return checkdate( $matches[2], $matches[1], $matches[3] );
			default:
				return checkdate( $matches[2], $matches[3], $matches[1] );
				break;
		}
	}

	/**
	 * Function to use for validation of user input on this field type
	 */
	public function validate_input( $input ) {

		if ( true === $this->is_required && is_empty( $input ) ) {
			return new \WP_Error( 'missing_required_field', __( 'Required Field is missing', 'wp-macs-forms' ), $this->name );
		}

		if ( false === $this->validate_date( $input ) ) {
			return new \WP_Error( 'wrong_date', __( 'Date Field does not contain date in valid format', 'wp-macs-forms' ), $this->name );
		}

		return sanitize_text_field( $input );
	}
}
