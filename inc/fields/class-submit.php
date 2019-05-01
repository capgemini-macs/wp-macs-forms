<?php

namespace MACS_Forms\Fields;

class Submit extends Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'submit';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'Submit Button';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-yes';

	/**
	 * Render the field on front-end
	 */
	public function render_field() {
		if ( is_multisite() ) {
			$options = get_blog_option( 1, 'mf_settings' );
		} else {
			$options = get_option( 'mf_settings' );
		}

		if ( ! empty( $options['captcha_key'] ) ) :
			?>
			<div class="mf_field mf_field--recaptcha">
				<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $options['captcha_key'] ); ?>"></div>
			</div>
			<?php
		else :
			if ( is_user_logged_in() ) {
				echo esc_html__( 'Please set up Google reCAPTCHA site key and secret.', 'macs_forms' );
			}
		endif;
		?>

			<div class="mf_field mf_field--submit">
				<input type="submit" class="mf_field__input" id="<?php echo esc_attr( $this->id ); ?>" value="<?php echo esc_attr( $this->label ); ?>" />
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
					'label' => __( 'Button label:', 'wp-macs-forms' ),
					'value' => $this->get_value( 'label' ),
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
		return sanitize_text_field( $input );
	}
}
