<?php
namespace Proper_Forms\Files;

use Proper_Forms;
use Proper_Forms\Files\Encrypted_Files as Encrypted_Files;

class File {

	/**
	 * File ID (WP_Post ID)
	 *
	 * @var int
	 */
	public $file_id = 0;

	/**
	 * File title
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * File title
	 *
	 * @var string
	 */
	public $url = '';

	/**
	 * Field ID
	 *
	 * @var int
	 */
	public $field_id = 0;

	/**
	 * Submission ID (WP_Post ID)
	 *
	 * @var int
	 */
	public $sub_id = 0;

	/**
	 * File encrypted contents
	 *
	 * @var string
	 */
	protected $encrypted_contents = '';

	/**
	 * File type
	 *
	 * @var string
	 */
	public $filetype = '';

	/**
	 * File constructor
	 */
	public function __construct( $file_post_id = 0 ) {

		$this->file_id = absint( $file_post_id );
		$this->populate_fields();
	}

	/**
	 * Retrieve data from WP DB
	 */
	protected function populate_fields() {

		if ( empty( $this->file_id ) ) {
			return;
		}

		$query = new \WP_Query(
			[
				'post_type'     => 'pf_file',
				'post_status'   => [ 'publish', 'draft' ],
				'post__in'      => [ $this->file_id ],
				'no_found_rows' => true,
			]
		);

		if ( ! $query->have_posts() ) {
			$this->file_id = 0;
			return;
		}

		while ( $query->have_posts() ) :
			$query->the_post();

			$this->title    = get_post_meta( $this->file_id, 'filename', true );
			$this->filetype = get_post_meta( $this->file_id, 'filetype', true );
			$this->field_id = get_post_meta( $this->file_id, 'field_id', true );
			$this->sub_id   = get_post_meta( $this->file_id, 'sub_id', true );
			$this->url      = add_query_arg(
				[
					'action' => 'pf_decode_file',
					'id'     => $this->file_id,
				],
				admin_url( 'admin-ajax.php' )
			);

		endwhile;
		wp_reset_query();
	}

	/**
	 * Inserts new File post to DB.
	 * Expects data to be already validated.
	 *
	 * @param array @file_data file post contents, expected keys:
	 *                         [content]    encrypted content
	 *                         [title]      uploaded file title
	 *                         [temp_title] temporary title
	 *                         [filetype]   file type
	 * @return int
	 */
	public function insert( $file_data ) {

		// All data is required
		foreach ( $file_data as $key => $value ) {
			if ( empty( $value ) ) {
				return 0;
			}
		}

		$file_post = wp_insert_post(
			[
				'post_content' => $file_data['content'],
				'post_title'   => $file_data['temp_title'],
				'post_status'  => 'draft',
				'post_type'    => 'pf_file',
				'meta_input'   => [
					'filetype' => $file_data['filetype'],
					'filename' => $file_data['title'],
					'field_id' => $file_data['field_id'],
				],
			],
			true
		);

		return $file_post;
	}

	public function add_to_submission( $sub_post_id ) {

		if ( empty( $this->file_id ) || empty( $this->field_id ) ) {
			return false;
		}

		// Make sure file exists in WP
		$file_post = get_post( $this->file_id );

		if ( empty( $file_post ) || 'draft' !== $file_post->post_status || 'pf_file' !== $file_post->post_type ) {
			return false;
		}

		$updated = wp_update_post(
			[
				'ID'          => $this->file_id,
				'post_title'  => $this->title ?: $file_post->post_title,
				'post_status' => 'publish',
			]
		);

		if ( $updated ) {
			// update file post meta
			update_post_meta( $this->file_id, 'sub_id', $sub_post_id );
		}

		return true;
	}

	protected function get_decrypted() {

		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'read_pf_files' ) ) {
			wp_die( esc_html_e( 'You don\'t have the proper permission to view this file.', 'proper-forms' ) );
		}

		if ( empty( $this->file_id ) ) {
			return '';
		}

		$file_post = get_post( $this->file_id );
		$encrypted = $file_post->post_content;

		if ( empty( $encrypted ) ) {
			wp_die( esc_html_e( 'File contents not found', 'proper-forms' ) );
		}

		return openssl_decrypt( $encrypted, 'AES128', Encrypted_Files::CIPHER_KEY );
	}

	public function output() {

		if ( empty( $this->file_id ) ) {
			wp_die( esc_html_e( 'File not found', 'proper-forms' ) );
		}

		if ( empty( $this->filetype ) ) {
			wp_die( esc_html_e( 'File type not found', 'proper-forms' ) );
		}

		if ( empty( $this->title ) ) {
			wp_die( esc_html_e( 'File title not found', 'proper-forms' ) );
		}

		$decrypted = $this->get_decrypted();

		header( sprintf( 'Content-Type: %s; charset=utf-8', $this->filetype ?: 'application/binary' ) );
		header( sprintf( 'Content-Disposition: filename=%s', $this->title ) );
		echo $decrypted; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public function download() {

		if ( empty( $this->file_id ) ) {
			wp_die( esc_html_e( 'File not found', 'proper-forms' ) );
		}

		if ( empty( $this->filetype ) ) {
			wp_die( esc_html_e( 'File type not found', 'proper-forms' ) );
		}

		if ( empty( $this->title ) ) {
			wp_die( esc_html_e( 'File title not found', 'proper-forms' ) );
		}

		$decrypted = $this->get_decrypted();

		header( 'Pragma: public' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Content-Type: application/force-download' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Type: application/download' );
		header( sprintf( 'Content-Disposition: attachment;filename=%s', $this->title ) );
		header( 'Content-Transfer-Encoding: binary' );
		echo $decrypted; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public function delete() {

	}
}
