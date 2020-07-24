<?php

namespace Proper_Forms\Fields;

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
			$options = get_blog_option( 1, 'pf_settings' );
		} else {
			$options = get_option( 'pf_settings' );
		}

		$label = $this->label ?: esc_html__( 'Submit', 'proper-forms' );

		if ( ! empty( $options['captcha_key'] ) ) :
			?>
			<div class="pf_field pf_field--recaptcha">
				<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $options['captcha_key'] ); ?>"></div>
			</div>
			<?php
		else :
			if ( is_user_logged_in() ) {
				echo esc_html__( 'Please set up Google reCAPTCHA site key and secret.', 'proper-forms' );
			}
		endif;
		?>

			<div class="pf_field pf_field--submit">
				<input type="submit" class="pf_field__input" id="<?php echo esc_attr( $this->id ); ?>" value="<?php echo esc_attr( $label ); ?>" />
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
					'label' => __( 'Button label:', 'proper-forms' ),
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
