<?php
/**
 * General Files controller
 */

namespace MACS_Forms\Files;

use MACS_Forms;

class Encrypted_Files {

	/**
	 * Cipher key for openssl encryption
	 *
	 */
	private $cipher_key;


	public function __construct() {
		// Cipher key for openssl
		$this->cipher_key = get_option( 'mf_cipher_key_render' );
	}

	/**
	 * Initialise the class and run hooks
	 */
	public function init() {
		// AJAX callbacks
		add_action( 'wp_ajax_mf_fileupload', [ $this, 'fileupload_ajax_callback' ] );
		add_action( 'wp_ajax_nopriv_mf_fileupload', [ $this, 'fileupload_ajax_callback' ] );
		add_action( 'wp_ajax_mf_decode_file', [ $this, 'mf_decode_file_callback' ] );
	}

	protected function validate_upload_data() {
		$form_id    = filter_input( INPUT_POST, 'form_id', FILTER_SANITIZE_STRING );
		$field_id   = filter_input( INPUT_POST, 'field_id', FILTER_SANITIZE_STRING );
		$file       = array_shift( $_FILES ); // phpcs:ignore
		$error_code = isset( $file['error'] ) ? $file['error'] : 'unknown';

		if ( empty( $field_id ) ) {
			return new \WP_Error( 'file_upload_error', __( 'Unknown upload field ID', 'wp-macs-forms' ) );
		}

		if ( false === check_ajax_referer( 'mf-fileupload-' . $field_id, 'nonce', false ) ) {
			return new \WP_Error( 'file_upload_error', __( 'Nonce error', 'wp-macs-forms' ) );
		}

		if ( empty( $form_id ) ) {
			return new \WP_Error( 'file_upload_error', __( 'Form ID missing', 'wp-macs-forms' ) );
		}

		if ( empty( $file['name'] ) || empty( $file['type'] ) || empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'file_upload_error', __( 'File data missing', 'wp-macs-forms' ) );
		}

		// retrieve field configuration from form settings
		$upload_field_config = $this->get_upload_field( $form_id, $field_id );

		if ( empty( $upload_field_config ) ) {
			return new \WP_Error( 'file_upload_error', __( 'Missing upload field configuration', 'wp-macs-forms' ) );
		}

		if ( false === $this->check_allowed_filetype( $file['name'], $upload_field_config ) ) {
			return new \WP_Error( 'file_upload_error', __( 'File type not allowed', 'wp-macs-forms' ) );
		}

		if ( false === $this->check_allowed_filesize( $file['size'], $upload_field_config ) ) {
			return new \WP_Error( 'file_upload_error', __( 'File is too big', 'wp-macs-forms' ) );
		}

		if ( 0 !== $error_code ) {
			return new \WP_Error( 'file_upload_error', $this->get_upload_error_message( $error_code ) );
		}

		$file_data = [
			'file'     => $file,
			'form_id'  => $form_id,
			'field_id' => $field_id,
		];

		return $file_data;
	}

	public function fileupload_ajax_callback() {

		$file_data = $this->validate_upload_data();

		if ( is_wp_error( $file_data ) ) {
			wp_send_json_error( $file_data->get_error_message() );
			wp_die();
		}

		// Create new File object

		$file      = $file_data['file'];
		$contents  = file_get_contents( $file['tmp_name'] ); //phpcs:ignore
		$encrypted = openssl_encrypt( $contents, 'AES128', $cipher_key );

		$file_post = MACS_Forms\Builder::get_instance()->make_file();
		$file_id   = $file_post->insert(
			[
				'content'    => $encrypted,
				'title'      => $file['name'],
				'temp_title' => $file['tmp_name'],
				'filetype'   => $file['type'],
				'field_id'   => $file_data['field_id'],
			]
		);

		// Return results

		if ( 0 === $file_id ) {
			wp_send_json_error( __( 'Couldn\'t insert temporary file to WP database', 'wp-macs-forms' ) );
			wp_die();
		}

		$result = [
			'file_post_id' => $file_id,
			'file_data'    => $file,
		];

		wp_send_json_success( $result );
		exit;
	}

	public function mf_decode_file_callback( $file_id ) {
		$file_id = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );

		if ( empty( $file_id ) ) {
			/* translators: %s is the invalid URL parameter value */
			wp_die( sprintf( esc_html__( 'Invalid file ID: %s', 'wp-macs-forms' ), esc_html( filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT ) ) ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You don\'t have the proper permission to view this file.', 'wp-macs-forms' ) );
		}

		$file = MACS_Forms\Builder::get_instance()->make_file( $file_id );
		$file->output();
	}

	/**
	 * Checks if file type is allowed by form settings
	 *
	 * @param string $file_name    uploaded file name with extension
	 * @param array  $field_config corresponding upload field settings
	 */
	protected function check_allowed_filetype( $file_name, $field_config ) {
		$filetype  = wp_check_filetype( $file_name );
		$ext       = $filetype['ext'];
		$whitelist = ! empty( $field_config['allowed_extensions'] ) ? $this->allowed_extensions_to_array( $field_config['allowed_extensions'] ) : $this->get_default_allowed_extensions();

		return in_array( $ext, $whitelist, true );
	}

	/**
	 * Retrieves a list of allowed filetypes based on site settings
	 *
	 * @return array
	 */
	protected function get_default_allowed_extensions() {
		$allowed = get_site_option( 'upload_filetypes' );
		return explode( ' ', $allowed );
	}

	/**
	 * Retrieves a list of allowed filetypes based on form settings
	 *
	 * @param string $ext_string comma separated list of file extensions
	 *
	 * @return array
	 */
	protected function allowed_extensions_to_array( $ext_string ) {
		$ext_array = explode( ',', $ext_string );
		return array_map(
			function( $item ) {
				return preg_replace( '/[^A-Za-z0-9]/', '', $item );
			},
			$ext_array
		);
	}

	/**
	 * Validate file size
	 *
	 * @param int   $filesize uploaded file size in bytes
	 * @param array $field_config corresponding upload field settings
	 *
	 * @return bool
	 */
	protected function check_allowed_filesize( $filesize, $field_config ) {

		// 10 MB is hardcoded maximum file size
		if ( $filesize > 10485760 ) {
			return false;
		}

		if ( empty( $field_config['max_filesize'] ) ) {
			return true;
		}

		return $filesize / 1000 <= $field_config['max_filesize'];
	}

	/**
	 * Retrieves upload field object from form based on form and field ids
	 *
	 * @param int    $form_id  form post ID
	 * @param string $field_id PF field ID
	 *
	 * @return array
	 */
	protected function get_upload_field( $form_id, $field_id ) {
		$form         = MACS_Forms\Builder::get_instance()->make_form( $form_id );
		$upload_field = [];

		if ( ! empty( $form->fields ) ) {
			$upload_field = wp_list_filter( $form->fields, [ 'id' => $field_id ] );
		}

		if ( empty( $upload_field ) ) {
			return [];
		}

		return (array) array_shift( $upload_field );
	}

	/**
	 * Translates error codes into text message
	 *
	 * @param int $error_code
	 *
	 * @return string
	 */
	protected function get_upload_error_message( $error_code ) {
		$php_upload_errors = [
			1 => __( 'The uploaded file exceeds the upload_max_filesize directive in php.ini', 'wp-macs-forms' ),
			2 => __( 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 'wp-macs-forms' ),
			3 => __( 'The uploaded file was only partially uploaded', 'wp-macs-forms' ),
			4 => __( 'No file was uploaded', 'wp-macs-forms' ),
			6 => __( 'Missing a temporary folder', 'wp-macs-forms' ),
			7 => __( 'Failed to write file to disk.', 'wp-macs-forms' ),
			8 => __( 'A PHP extension stopped the file upload.', 'wp-macs-forms' ),
		];

		return isset( $php_upload_errors[ $error_code ] ) ? $php_upload_errors[ $error_code ] : __( 'Unknown upload error', 'wp-macs-forms' );
	}
}
