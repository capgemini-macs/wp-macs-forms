<?php
/**
 * Builder
 * Makes objects, implements WP Cache
 */

namespace MACS_Forms;

use MACS_Forms\Fields\Field_Types as Field_Types;
use MACS_Forms\Forms\Form as Form;
use MACS_Forms\Submissions\Submission as Submission;
use MACS_Forms\Files\File as File;

class Builder {

	/**
	 * Sinlgeton instance of the class
	 *
	 * @var Builder
	 */
	private static $instance;

	/**
	 * Available form field types
	 */
	private $field_types;

	/**
	 * Returns singleton instance of the class
	 *
	 * @return Builder
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) && ! self::$instance instanceof Builder ) {

			$field_types = new Field_Types();

			self::$instance = new Builder( $field_types );
		}

		return self::$instance;
	}

	protected function __construct( Field_Types $field_types ) {
		$this->field_types = $field_types;
	}

	/**
	 * Create new Form object or get existing one from cache if ID is defined.
	 * @NOTE: Cache is set by Forms/update_form_cache() method, hooked to
	 *        'added_post_meta' and 'update_post_meta' actions.
	 *
	 * @param int $form_id
	 *
	 * @return Form
	 */
	public function make_form( $form_id = 0 ) {

		if ( ! $form_id || ! absint( $form_id ) ) {
			return new Form();
		}

		$cache_key     = sprintf( 'mf_form_%d', $form_id );
		$cached_object = wp_cache_get( $cache_key, 'macs_forms' );

		if ( $cached_object instanceof Form ) {
			return $cached_object;
		}

		return new Form( $form_id );
	}

	/**
	 * Create new Submission object or get existing one from cache if ID is defined.
	 * @NOTE: Cache is set by Submissions/update_form_cache() method, hooked to
	 *        'save_post_mf_sub' action.
	 *
	 * @param int $sub_id
	 *
	 * @return Submission
	 */
	public function make_submission( $sub_id = 0 ) {
		if ( ! $sub_id || ! absint( $sub_id ) ) {
			return new Submission();
		}

		$cache_key     = sprintf( 'mf_sub_%d', $sub_id );
		$cached_object = wp_cache_get( $cache_key, 'macs_forms' );
		if ( $cached_object instanceof Submission ) {
			return $cached_object;
		}

		return new Submission( $sub_id );
	}

	/**
	 * Create new Field object according to type
	 *
	 * @NOTE: Not implementing cache here as it doesn't make much sense
	 *        and would make virtually no difference in performace.
	 *        Instances of Field objects are stored within cached Form objects anyway.
	 *
	 * @param string $type
	 * @param array  $args
	 *
	 * @return mixed (object|null)
	 */
	public function make_field( $type, $args = [] ) {

		$field_class = $this->field_types->get_field_class( $type );

		if ( $field_class ) {
			return new $field_class( $args );
		}

		return null;
	}

	/**
	 * Create new File post
	 */
	public function make_file( $file_id = 0 ) {
		if ( ! $file_id || ! absint( $file_id ) ) {
			return new File();
		}

		return new File( $file_id );
	}
}
