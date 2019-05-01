<?php
/**
 * General Submissions controller
 * Deals with common submissions functions such as exports and caches
 */

namespace MACS_Forms\Submissions;

use MACS_Forms;

class Submissions {

	/**
	 * Singleton instance of a class
	 *
	 * @var Submissions
	 */
	private static $instance;

	/**
	 * Get a singleton instance of the class
	 *
	 * @return object (Submissions)
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) && ! self::$instance instanceof Submissions ) {
			self::$instance = new Submissions;
		}

		return self::$instance;
	}

	/**
	 * Submissions constructor
	 */
	protected function __construct() {
		$this->init();
	}

	/**
	 * Initialise the class and run hooks
	 */
	protected function init() {

		// Create encrypted files relationship
		add_action( 'save_post_mf_sub', [ $this, 'attach_files' ], 10, 2 );

		// Cache controll
		add_action( 'save_post_mf_sub', [ $this, 'update_form_cache' ], 30, 2 );
		add_action( 'delete_post', [ $this, 'clean_form_cache' ], 10, 1 );
	}

	/**
	 * Create submission object and store it in cache
	 *
	 * @param int      $submission_post_id mf_sub post ID
	 * @param \WP_Post $submission_post    mf_sub post object
	 */
	public function update_form_cache( $submission_post_id, $submission_post ) {

		if ( ! in_array( $submission_post->post_status, [ 'draft', 'publish' ], true ) ) {
			return;
		}

		$cache_key = sprintf( 'mf_sub_%d', $submission_post_id );

		// First delete old cache - otherwise Builder would return the cached object and we'd end up with the same data.
		wp_cache_delete( $cache_key, 'macs_forms' );

		$submission_object = MACS_Forms\Builder::get_instance()->make_submission( $submission_post_id );

		// Cache the updated form object.
		wp_cache_set( $cache_key, $submission_object, 'macs_forms', 2 * MONTH_IN_SECONDS );
	}

	/**
	 * Delete Submission object from cache when submission post is deleted.
	 *
	 * @param int $post_id
	 */
	public function clean_form_cache( $post_id ) {
		if ( 'mf_sub' !== get_post_type( $post_id ) ) {
			return;
		}
		$cache_key = sprintf( 'mf_sub_%d', $post_id );
		wp_cache_delete( $cache_key, 'macs_forms' );
	}

	/**
	 * Check for File Uploads fields and attach file posts to this submission
	 *
	 * @param int $submission_post_id
	 */
	public function attach_files( $submission_post_id ) {
		$sub = MACS_Forms\Builder::get_instance()->make_submission( $submission_post_id );

		foreach ( (array) $sub->fields as $field ) {
			if ( ! empty( $field->type ) && 'file_upload' === $field->type && ! empty( $field->saved_value ) ) {
				$file = MACS_Forms\Builder::get_instance()->make_file( $field->saved_value );
				$file->add_to_submission( $submission_post_id );
			}
		}
	}
}
