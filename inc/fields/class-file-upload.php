<?php

namespace Proper_Forms\Fields;

class File_Upload extends Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'file_upload';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = 'File upload';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = 'dashicons-media-default';

	/**
	 * Allowed extensions string
	 *
	 * @var string
	 */
	public $allowed_extensions = '';

	/**
	 * Max filesize
	 *
	 * @var string
	 */
	public $max_filesize = '';

	/**
	 * Populate object with data on initialisation
	 */
	public function populate_field( $args ) {
		if ( ! is_array( $args ) || empty( $args ) ) {
			return;
		}

		foreach ( $args as $key => $val ) {
			if ( property_exists( __CLASS__, $key ) ) {
				$this->{$key} = $val;
			}
		}
	}

	/**
	 * Renders the field on front-end
	 */
	public function render_field() {
		$nonce_action = sprintf( 'pf-fileupload-%s', $this->id );
		$extensions   = implode( ', ', $this->allowed_extensions_to_array() );
		$filesize     = ! empty( $this->max_filesize ) ? round( $this->max_filesize / 1000, 2 ) : '10';
		?>

		<div class="pf_field pf_field--file <?php echo esc_attr( $this->get_render_required_class() ); ?>" data-validate="upload">
			<label for="<?php echo esc_attr( $this->id ); ?>"><?php echo esc_html( $this->label ); ?>
				<?php echo wp_kses_post( $this->get_render_required_symbol() ); ?>
			</label>
			<button class="pf_fileupload_btn"><?php echo esc_html__( 'Select File', 'proper-forms' ); ?></button>

			<div class="pf_field_info">
				<?php if ( ! empty( $extensions ) ) : ?>
					<span class=""><?php echo esc_html__( 'Allowed extensions: ', 'proper-forms' ); ?><?php echo esc_html( $extensions ); ?></span><br />
				<?php endif; ?>

				<?php if ( ! empty( $filesize ) ) : ?>
					<span class=""><?php echo esc_html__( 'Maximum file size: ', 'proper-forms' ); ?><?php echo esc_html( $filesize ); ?>MB</span>
				<?php endif; ?>
			</div>

			<input type="file" id="<?php echo esc_attr( $this->id ); ?>" class="pf_field__input onsubmit-ignore empty" name="file_<?php echo esc_attr( $this->id ); ?>" accept="<?php echo esc_attr( $this->allowed_extensions ); ?>" <?php echo esc_attr( $this->get_render_required() ); ?>/>
			<input type="hidden" name="nonce_<?php echo esc_attr( $this->id ); ?>" class="pf_fileupload_nonce onsubmit-ignore" value="<?php echo esc_attr( wp_create_nonce( $nonce_action ) ); ?>" />
			<input type="text" name="<?php echo esc_attr( $this->id ); ?>" class="pf_fileupload_callback_id" value="<?php echo esc_attr( $this->saved_value ); ?>" />
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
					'type'  => 'number',
					'name'  => 'max_filesize',
					'label' => __( 'File max size (in KB)', 'proper-forms' ),
					'value' => $this->get_value( 'max_filesize' ),
					'min'   => 0,
					'max'   => 20000,
				]
			);

			$this->render_option(
				[
					'type'  => 'text',
					'name'  => 'allowed_extensions',
					'label' => __( 'Allowed extensions (comma separated):', 'proper-forms' ),
					'value' => $this->get_value( 'allowed_extensions' ),
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
	 * Retrieves a list of allowed filetypes
	 *
	 * @param string $ext_string comma separated list of file extensions
	 *
	 * @return array
	 */
	protected function allowed_extensions_to_array() {
		if ( empty( $this->allowed_extensions ) ) {
			return [];
		}

		$ext_array = explode( ',', $this->allowed_extensions );
		return array_map(
			function( $item ) {
				return preg_replace( '/[^A-Za-z0-9]/', '', $item );
			},
			$ext_array
		);
	}

	/**
	 * Function to use for validation of user input on this field type
	 * Expecting ID of an encryted file post
	 */
	public function validate_input( $input ) {

		if ( true === $this->is_required && is_empty( $input ) ) {
			return new \WP_Error( 'missing_required_field', __( 'Required Field is missing', 'proper-forms' ), $this->name );
		}

		return absint( $input );
	}
}
