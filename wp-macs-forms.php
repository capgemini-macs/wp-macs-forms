<?php
/**
 * Plugin Name: MACS Forms
 * Plugin URI: https://macs.capgemini.com
 * Description: Simple, reliable forms for WP on VIP Go. Supports Ninja Forms shortcodes for simple migration.
 * Version: 1.0.0
 * Author: Lech Dulian (Capgemini MACS PL)
 * Author URI: https://macs.capgemini.com
 * Text Domain: wp-macs-forms
 * Domain Path: /languages
*/

namespace MACS_Forms;

use MACS_Forms\Forms\Forms;
use MACS_Forms\Submissions\Submissions;
use MACS_Forms\Files\Encrypted_Files;

const VERSION = '1.0.0';

/**
 * Automatically load plugin classes.
 *
 * @param string $classname
 * @return void
 */
function autoloader( $classname ) {

	if ( false === strpos( $classname, __NAMESPACE__ ) && false === strpos( $classname, 'MACS_Forms' ) ) {
		return;
	}

	// Separate the components of a class name
	$file_path = (array) explode( '\\', $classname );

	// get base path
	$full_path = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR;

	// first element is a parent namespace
	$parent_namespace = array_shift( $file_path );

	// last element is a file name
	$class_name = array_pop( $file_path );
	$file_name  = strtolower( str_ireplace( '_', '-', $class_name ) );

	// process any path component in between of the parent namespace and a file name
	foreach ( $file_path as $subdir ) {

		if ( empty( $subdir ) ) {
			continue;
		}

		$full_path .= strtolower( $subdir ) . DIRECTORY_SEPARATOR;
	}

	// add formatted file name to the path
	$full_path .= "class-$file_name.php";

	if ( file_exists( $full_path ) ) {
		require_once( $full_path );
		return;
	}
}

// Register autoloader
spl_autoload_register( __NAMESPACE__ . '\\autoloader' );

add_action(
	'init',
	function() {

		// Register post types
		$post_types = new Register_Post_Types();
		$post_types->register_mf_form();
		$post_types->register_mf_sub();
		$post_types->register_mf_file();

		// Init main controllers
		$forms_controller = Forms::get_instance();
		Submissions::get_instance();

		// Init Builder
		Builder::get_instance();

		// Init Encrypted File Uploads
		$encrypted_files = new Encrypted_Files();
		$encrypted_files->init();

		// Add Ninja Forms shortcode support
		$forms_controller->add_ninja_forms_shortcode_support();
	}
);

// Init main admin controller
add_action(
	'init',
	function() {
		Admin::get_instance();
	}
);

// Set default allowed field types
add_filter(
	'mf_active_field_types',
	function( $fields ) {
		return array_merge(
			[
				'text'        => \MACS_Forms\Fields\Text::class,
				'textarea'    => \MACS_Forms\Fields\TextArea::class,
				'numbers'     => \MACS_Forms\Fields\Numbers::class,
				'email'       => \MACS_Forms\Fields\Email::class,
				'tel'         => \MACS_Forms\Fields\Tel::class,
				'select'      => \MACS_Forms\Fields\Select::class,
				'radio'       => \MACS_Forms\Fields\Radio::class,
				'checkbox'    => \MACS_Forms\Fields\Checkbox::class,
				'file_upload' => \MACS_Forms\Fields\File_Upload::class,
				'date'        => \MACS_Forms\Fields\Date::class,
				'country'     => \MACS_Forms\Fields\Country::class,
				'hidden'      => \MACS_Forms\Fields\Hidden::class,
				'consent'     => \MACS_Forms\Fields\Consent::class,
				'submit'      => \MACS_Forms\Fields\Submit::class,
			],
			$fields
		);
	}
);

// Template tags

/**
 * Renders a form from default forms list.
 *
 * @param string $form_slug
 */
function render_default_form( $form_slug ) {
	$form = Forms::get_instance()->get_default( $form_slug );

	if ( ! $form ) {
		return;
	}

	echo do_shortcode( sprintf( '[macs_form id="%d"]', $form->form_id ) );
}
