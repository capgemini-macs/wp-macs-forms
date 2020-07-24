<?php
/**
 * Plugin Name: Capgemini Proper Forms
 * Plugin URI: https://capgemini.com
 * Description: Simple, reliable forms for Capgemini on VIP Go
 * Version: 1.0.1
 * Author: Lech Dulian (Capgemini MACS PL)
 * Author URI: https://capgemini.com
 * Text Domain: proper-forms
 * Domain Path: /languages
*/

namespace Proper_Forms;

use Proper_Forms\Forms\Forms;
use Proper_Forms\Forms\Appended_Form;
use Proper_Forms\Submissions\Submissions;
use Proper_Forms\Submissions\Downloads;
use Proper_Forms\Files\Encrypted_Files;

const VERSION = '1.0.1';

/**
 * Automatically load plugin classes.
 *
 * @param string $classname
 * @return void
 */
function autoloader( $classname ) {

	if ( false === strpos( $classname, __NAMESPACE__ ) && false === strpos( $classname, 'Proper_Forms' ) ) {
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

// CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/inc/cli.php';
	\WP_CLI::add_command( 'pf', __NAMESPACE__ . '\\CLI\\Command' );
}


// Register autoloader
spl_autoload_register( __NAMESPACE__ . '\\autoloader' );

add_action( 'init', function() {

	// Register post types
	$post_types = new Register_Post_Types();
	$post_types->register_pf_form();
	$post_types->register_pf_sub();
	$post_types->register_pf_file();

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

	// Init Appended Form module
	$appended_form = new Appended_Form( $forms_controller );
	add_action( 'add_meta_boxes', [ $appended_form, 'add_appended_form_metabox' ] );
	add_action( 'save_post', [ $appended_form, 'appended_form_save_postdata' ] );
	add_filter( 'the_content', [ $appended_form, 'append_form_to_content' ], 20, 1 );
} );

// Init main admin controller
add_action( 'init', function() {
	Admin::get_instance();
} );

// Set default allowed field types
add_filter( 'pf_active_field_types', function( $fields ) {
	return array_merge(
		[
			'text'        => \Proper_Forms\Fields\Text::class,
			'textarea'    => \Proper_Forms\Fields\TextArea::class,
			'numbers'     => \Proper_Forms\Fields\Numbers::class,
			'email'       => \Proper_Forms\Fields\Email::class,
			'tel'         => \Proper_Forms\Fields\Tel::class,
			'select'      => \Proper_Forms\Fields\Select::class,
			'multiselect' => \Proper_Forms\Fields\Multiselect::class,
			'radio'       => \Proper_Forms\Fields\Radio::class,
			'checkbox'    => \Proper_Forms\Fields\Checkbox::class,
			'file_upload' => \Proper_Forms\Fields\File_Upload::class,
			'date'        => \Proper_Forms\Fields\Date::class,
			'country'     => \Proper_Forms\Fields\Country::class,
			'hidden'      => \Proper_Forms\Fields\Hidden::class,
			'consent'     => \Proper_Forms\Fields\Consent::class,
			'submit'      => \Proper_Forms\Fields\Submit::class,
		],
		$fields
	);
} );

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

	echo do_shortcode( sprintf( '[proper_form id="%d"]', $form->form_id ) );
}


/**
 * Load textdomain for plugin
 *
 */
function load_textdomain() {

	load_plugin_textdomain(
		'proper-forms',
		false,
		basename( dirname( __FILE__ ) ) . '/languages'
	);
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_textdomain' );
