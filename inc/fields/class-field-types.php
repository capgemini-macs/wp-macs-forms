<?php
/**
 * Collection class for form fields
 */

namespace MACS_Forms\Fields;

class Field_Types {

	/**
	 * Available fields
	 *
	 * @var $fields
	 */
	private $fields = [];

	public function __construct() {
		$this->set_default_fields();
	}

	/**
	 * Set default fields
	 */
	private function set_default_fields() {
		$this->fields = apply_filters( 'mf_active_field_types', $this->fields );
	}

	/**
	 * Add field type to collection
	 *
	 * @param string $slug
	 * @param string $class_name Fully qualified class name
	 *
	 * @return bool
	 */
	public function add_field_type( $slug, $class_name ) {
		if ( isset( $this->fields[ $slug ] ) ) {
			return false;
		}

		$this->fields[ $slug ] = $class_name;
		return true;
	}

	/**
	 * Get field's class prefixed with namespace qualified name
	 * by field type slug
	 *
	 * @param string $slug
	 *
	 * @return string
	 */
	public function get_field_class( $slug ) {
		if ( ! array_key_exists( $slug, $this->fields ) ) {
			return '';
		}

		return $this->fields[ $slug ];
	}

	/**
	 * Remove field type from collection by slug
	 *
	 * @param string $slug
	 */
	public function delete_field_type( $slug ) {
		if ( ! array_key_exists( $slug, $this->fields ) ) {
			return;
		}

		unset( $this->fields[ $slug ] );
	}

	/**
	 * Return a filtered array of allowed field types
	 *
	 * @return array
	 */
	public function get_active_field_types() {
		return $this->fields;
	}

	/**
	 * Return array of allowed field types slugs
	 */
	public function get_active_fields_slugs() {
		return array_keys( $this->fields );
	}
}
