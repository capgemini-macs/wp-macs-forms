<?php
/**
 * General Forms controller
 * Deals with common forms functions such as shortcodes, AJAX submisson callback,
 * general form builder functionalities and form's front-end scripts
 */

namespace MACS_Forms\Forms;

use MACS_Forms;
use MACS_Forms\Forms\Form as Form;

class Forms {

	/**
	 * Singleton instance of a class
	 *
	 * @var Forms
	 */
	private static $instance;

	/**
	 * Get a singleton instance of the class
	 *
	 * @return object (Forms)
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) && ! self::$instance instanceof Forms ) {
			self::$instance = new Forms;
		}

		return self::$instance;
	}

	/**
	 * Forms constructor
	 */
	protected function __construct() {
		$this->init();
	}

	/**
	 * Initialise the class and run hooks
	 */
	protected function init() {

		// Save Form Builder data
		add_action( 'save_post_mf_form', [ $this, 'save_form_settings' ], 10, 2 );
		add_action( 'post_updated', [ $this, 'reset_forms_list_after_update' ], 90, 3 );
		add_action( 'save_post', [ $this, 'add_form_usage_meta' ], 10, 2 );

		// Cache controll
		add_action( 'added_post_meta', [ $this, 'update_form_cache' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'update_form_cache' ], 10, 4 );
		add_action( 'delete_post', [ $this, 'clean_form_cache' ], 10, 1 );

		// Ajax submission
		add_action( 'wp_ajax_nopriv_mf_submit_form', [ $this, 'submit_form_callback' ] );

		// Front end scripts
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ], 20 );
		add_filter( 'script_loader_tag', [ $this, 'add_asyncdefer_attributes' ], 10, 2 );

		// Shortcodes
		add_shortcode( 'macs_form', [ $this, 'form_shortcode_callback' ] );

		// Form preview
		add_filter( 'template_include', [ $this, 'form_preview_template_redirect' ], 99 );
		add_filter( 'the_content', [ $this, 'form_preview_filter_content' ], 199 );
		add_filter( 'the_title', [ $this, 'form_preview_filter_title' ], 199, 2 );

	}

	/**
	 * Register and localize front-end scripts
	 */
	public function frontend_scripts() {

		wp_enqueue_style( 'wp-macs-forms', sprintf( '%1$s/wp-macs-forms/assets/css/wp-macs-forms-front.css', plugins_url() ), [], MACS_Forms\VERSION );

		wp_enqueue_script( 'wp-macs-forms', sprintf( '%s/wp-macs-forms/assets/js/wp-macs-forms-front.js', plugins_url() ), [ 'jquery', 'lodash' ], MACS_Forms\VERSION, true );

		wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js' );

		wp_localize_script(
			'wp-macs-forms',
			'PF',
			[
				'ajaxURL'   => admin_url( '/admin-ajax.php' ),
				'ajaxNonce' => wp_create_nonce( 'mf_form_submission' ),
				'strings'   => [
					'remove_file'       => __( 'remove file', 'wp-macs-forms' ),
					'select_file'       => __( 'Select File', 'wp-macs-forms' ),
					'uploading'         => __( 'uploading...', 'wp-macs-forms' ),
					'file_selected'     => __( 'File selected', 'wp-macs-forms' ),
					'default_error'     => __( 'This field\'s value is invalid!', 'wp-macs-forms' ),
					'required_error'    => __( 'This field is required!', 'wp-macs-forms' ),
					'email_error'       => __( 'Please enter a valid email address!', 'wp-macs-forms' ),
					'date_format_error' => __( 'Please enter date in valid format!', 'wp-macs-forms' ),
				],
			]
		);
	}

	/**
	* Add async amd defer attributes to google recaptcha script
	*
	* @param  string  $tag     The original script tag
	* @param  string  $handle  Registered script handle
	* @return string  $tag
	*/
	function add_asyncdefer_attributes( $tag, $handle ) {

		if ( is_admin() || 'google-recaptcha' !== $handle ) {
			return $tag;
		}

		return str_replace( '<script ', '<script async defer ', $tag );
	}

	/**
	 * Retrieve data from Form Builder metaboxes and save it as post meta.
	 * Callback for save_post hook.
	 */
	public function save_form_settings( $post_id, $post ) {

		$data = filter_input_array(
			INPUT_POST,
			[
				'mf_fields'                 => [
					'filter' => FILTER_DEFAULT,
					'flags'  => FILTER_REQUIRE_ARRAY,
				],
				'_mf_nonce'                 => FILTER_SANITIZE_STRING,
				'mf_form_has_pardot'        => FILTER_VALIDATE_BOOLEAN,
				'mf_form_pardot_handler'    => FILTER_VALIDATE_URL,
				'mf_form_admin_notify'      => FILTER_VALIDATE_BOOLEAN,
				'mf_form_admin_custom_mail' => FILTER_VALIDATE_EMAIL,
				'mf_form_ty_msg'            => FILTER_DEFAULT,
			]
		);

		if ( empty( $data['_mf_nonce'] ) || ! wp_verify_nonce( $data['_mf_nonce'], 'mf_save_data' ) ) {
			return;
		}

		$mf_fields = $data['mf_fields'];

		if ( empty( $mf_fields ) ) {
			return;
		}

		$mf_fields = array_map(
			function( $field ) use ( $post_id ) {

				$field_data = json_decode( html_entity_decode( $field ) );

				if ( null !== $field_data ) {
					$field_data[] = (object) [
						'name'  => 'datasource_id',
						'value' => $post_id,
					];
					return $field_data;
				}
			},
			$mf_fields
		);

		update_post_meta( $post_id, 'mf_fields', $mf_fields );

		// update form settings
		update_post_meta( $post_id, 'mf_form_has_pardot', $data['mf_form_has_pardot'] );
		update_post_meta( $post_id, 'mf_form_pardot_handler', $data['mf_form_pardot_handler'] );
		update_post_meta( $post_id, 'mf_form_admin_notify', $data['mf_form_admin_notify'] );
		update_post_meta( $post_id, 'mf_form_admin_custom_mail', $data['mf_form_admin_custom_mail'] );
		update_post_meta( $post_id, 'mf_form_ty_msg', $data['mf_form_ty_msg'] );

		// clear all forms list cache
		wp_cache_delete( 'all_forms_array', 'macs_forms' );
	}

	/**
	 * Create Form object and store it in cache when Form post meta is updated or added
	 *
	 * @param int    $meta_id
	 * @param int    $object_id
	 * @param string $meta_key
	 * @param string $meta_value
	 */
	public function update_form_cache( $meta_id, $object_id, $meta_key, $meta_value ) {

		if ( 'mf_fields' !== $meta_key || ! in_array( get_post_status( $object_id ), [ 'draft', 'publish' ], true ) ) {
			return;
		}

		$cache_key = sprintf( 'mf_form_%d', $object_id );

		// First delete old cache - otherwise Builder would return the cached object and we'd end up with the same data.
		wp_cache_delete( $cache_key, 'macs_forms' );

		$form_object = MACS_Forms\Builder::get_instance()->make_form( $object_id );

		// Cache the updated form object.
		wp_cache_set( $cache_key, $form_object, 'macs_forms' );
	}

	/**
	 * Delete form object from cache when form post is deleted
	 *
	 * @param int $post_id
	 */
	public function clean_form_cache( $post_id ) {
		if ( 'mf_form' !== get_post_type( $post_id ) ) {
			return;
		}
		$cache_key = sprintf( 'mf_form_%d', $post_id );
		wp_cache_delete( $cache_key, 'macs_forms' );
		wp_cache_delete( 'all_forms_array', 'macs_forms' );
	}

	/**
	 * Clean cache after the form post is updated
	 *
	 * @param int $post_id
	 * @param WP_Post $post_after
	 * @param WP_Post $post_before
	 */
	public function reset_forms_list_after_update( $post_id, $post_after, $post_before ) {
		if ( 'mf_form' !== $post_after->post_type ) {
			return;
		}
		wp_cache_delete( 'all_forms_array', 'macs_forms' );
	}

	/**
	 * Get a list of all existing forms and its details
	 * @TODO
	 */
	public function get_forms() {
		return [];
	}

	/**
	 * MACS Forms Shortcode callback.
	 * Renders form by ID.
	 *
	 * @return string
	 */
	public function form_shortcode_callback( $atts, $content = null ) {
		// Shortcode attributes
		$atts = shortcode_atts(
			[
				'id'      => 0,
				'classes' => '',
			],
			$atts
		);

		$form_id      = absint( $atts['id'] );
		$form_classes = explode( ',', $atts['classes'] );

		if ( ! $form_id ) {
			return;
		}

		return $this->get_form_markup( $form_id, $form_classes );
	}

	/**
	 * AJAX callback for form submission attempt
	 */
	public function submit_form_callback() {

		$data = filter_input_array(
			INPUT_POST,
			[
				'nonce_mf_submit'    => FILTER_SANITIZE_STRING,
				'form_data'          => FILTER_SANITIZE_STRING,
				'recaptcha_response' => FILTER_SANITIZE_STRING,
			]
		);

		// Validate nonce before processign data.
		check_ajax_referer( 'mf_form_submission', 'nonce_mf_submit' );

		$data['form_data'] = json_decode( html_entity_decode( $data['form_data'] ), true );

		$form_id = absint( $data['form_data']['form_id'] );

		if ( empty( $data['recaptcha_response'] ) || ! $this->validate_recaptcha( $data['recaptcha_response'] ) ) {
			wp_send_json_error( 'invalid or missing recaptcha response', 409 );
			wp_die();
		}

		if ( empty( $form_id ) ) {
			wp_send_json_error( new \WP_Error( 'no_form_id', __( 'No form ID provided on submission', 'macs_forms' ) ) );
			wp_die();
		}

		$form = MACS_Forms\Builder::get_instance()->make_form( $form_id );
		$sub  = $form->submit( $data['form_data'] );

		// sucessful sumission returns WP Post id (int), array means trouble
		if ( is_array( $sub ) ) {
			wp_send_json_error( $sub );
			wp_die();
		}

		wp_send_json_success( $sub );

		wp_die();

	}

	/**
	 * Validates google reCaptcha response
	 */
	private function validate_recaptcha( $user_response ) {

		if ( is_multisite() ) {
			$options = get_blog_option( 1, 'mf_settings' );
		} else {
			$options = get_option( 'mf_settings' );
		}

		$secret = ! empty( $options['captcha_secret'] ) ? $options['captcha_secret'] : '';

		if ( empty( $secret ) ) {
			return false;
		}

		$google_response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			[
				'timeout' => 30,
				'headers' => [],
				'body'    => [
					'secret'   => $secret,
					'response' => $user_response,
				],
			]
		);

		if ( is_wp_error( $google_response ) ) {
			return false;
		}

		if ( empty( $google_response['response']['code'] ) || 200 !== $google_response['response']['code'] ) {
			return false;
		}

		$response_array = json_decode( $google_response['body'] );

		if ( empty( $response_array->success ) || true !== $response_array->success ) {
			return false;
		}

		return true;
	}

	/**
	 * Registers Ninja Forms shortcode if the option is set and Ninja Forms plugin is not active.
	 *
	 */
	public function add_ninja_forms_shortcode_support() {
		$options = get_option( 'mf_settings' );

		if ( empty( $options['nf_shortcode'] ) ) {
			return;
		}

		remove_shortcode( 'ninja_form' );
		add_shortcode( 'ninja_form', [ $this, 'nf_shortcode_callback' ] );
	}

	/**
	 * Ninja Forms shortcode callback for backward compatibility.
	 *
	 * Requires meta data from PF migration CLI.
	 *
	 * @return string
	 */
	public function nf_shortcode_callback( $atts, $content = null ) {
		// Shortcode attributes
		$atts = shortcode_atts(
			[
				'id'      => 0,
				'classes' => '',
			],
			$atts
		);

		$form_id      = absint( $atts['id'] );
		$form_classes = explode( ',', $atts['classes'] );

		if ( ! $form_id ) {
			return;
		}

		$form_id = $this->get_form_by_nf_id( $form_id );

		return $this->get_form_markup( $form_id, $form_classes );
	}

	/**
	 * Builds form markup
	 *
	 * @param $form_id      int
	 * @param $form_classes mixed (string|array)
	 *
	 * @return string
	 */
	private function get_form_markup( $form_id, $form_classes ) {

		if ( ! $form_id ) {
			return;
		}

		$classes    = apply_filters( 'mf_form_container_class', $form_classes, $form_id );
		$classes    = implode( ' ', (array) $classes );
		$form       = MACS_Forms\Builder::get_instance()->make_form( $form_id );
		$error_msgs = $form->get_error_messages_array();

		// Pass custom error messages to the script
		wp_localize_script(
			'wp-macs-forms',
			'PF_ERR',
			$error_msgs
		);

		// Output
		ob_start();

		do_action( 'mf_before_rendered_form', $form_id );
		?>
			<div class="mf_forms__container <?php esc_attr( $classes ); ?>">
				<?php $form->render_form(); ?>
			</div>
		<?php

		do_action( 'mf_after_rednered_form', $form_id );

		return ob_get_clean();
	}

	/**
	 * Retrieves forms migrated from Ninja Forms based on their original ID
	 *
	 * @param  int $nf_form_id
	 *
	 * @return int
	 */
	private function get_form_by_nf_id( $nf_form_id ) {

		$mf_form_id = wp_cache_get( "nf_to_mf_id_{ $nf_form_id }", 'macs_forms' );

		if ( false === $mf_form_id ) {

			$forms_query = new \WP_Query(
				[
					'post_type'           => 'mf_form',
					'posts_per_page'      => 1,
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'post_status'         => 'publish',
					'sub_query'           => false,
					'meta_query'          => [ // phpcs:ignore
						[
							'key'     => 'nf_form_id',
							'value'   => $nf_form_id,
							'compare' => '=',
							'type'    => 'NUMERIC',
						],
					],
				]
			);

			while ( $forms_query->have_posts() ) :
				$forms_query->the_post();
				$mf_form_id = get_the_ID();
			endwhile;

			wp_reset_query();

			wp_cache_set( "nf_to_mf_id_{ $nf_form_id }", $mf_form_id, 'macs_forms', MONTH_IN_SECONDS * 3 );
		}

		return $mf_form_id;
	}

	/**
	 * TODO: Save additional meta for posts that use MACS Forms shortcodes.
	 */
	public function add_form_usage_meta( $post_id, $post ) {

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( in_array( $post->post_status, get_post_types( [ 'public' => false ] ), true ) ) {
			return;
		}

		preg_match_all( '/\[macs_form id=(?:"|\')?(\d+)(?:"|\')?\]/', $post->post_content, $matches, PREG_SET_ORDER, 0 );

		if ( ! empty( $matches ) ) {
			$ids = [];
			foreach ( $matches as $match ) {
				$ids[] = $match[1];
			}

			update_post_meta( $post->ID, 'has_mf_form', $ids );

		} else {

			delete_post_meta( $post->ID, 'has_mf_form' );
		}

	}

	/**
	 * Returns form object from default forms settings by slug
	 *
	 * @param string $form_slug
	 */
	public function get_default( $form_slug ) {
		$mf_options = get_option( 'mf_settings' );
		$form_id    = ! empty( $mf_options[ "mf_default_{$form_slug}" ] ) ? absint( $mf_options[ "mf_default_{$form_slug}" ] ) : 0;

		if ( empty( $form_id ) ) {
			return false;
		}

		return MACS_Forms\Builder::get_instance()->make_form( $form_id );
	}

	/**
	 * Loads preview template (or single.php if former doesn't exist) when mf_form_preview query param is present in URL
	 *
	 * @param string $template default template
	 *
	 * @return string
	 */
	public function form_preview_template_redirect( $template ) {

		if ( ! is_user_logged_in() ) {
			return $template;
		}

		$mf_preview_id = filter_input( INPUT_GET, 'mf_form_preview', FILTER_VALIDATE_INT );

		if ( empty( $mf_preview_id ) ) {
			return $template;
		}

		$new_template = locate_template( [ 'mf-form-preview.php', 'single.php' ] );

		if ( empty( $new_template ) ) {
			return $template;
		}

		return $new_template;
	}

	/**
	 * Switches content for shortcode output when mf_form_preview query param is present in URL
	 *
	 * @param string $content default content
	 *
	 * @return string
	 */
	public function form_preview_filter_content( $content ) {

		if ( ! is_user_logged_in() ) {
			return $content;
		}

		$mf_preview_id = filter_input( INPUT_GET, 'mf_form_preview', FILTER_VALIDATE_INT );

		if ( empty( $mf_preview_id ) ) {
			return $content;
		}

		return do_shortcode( sprintf( '[macs_form id="%d"]', $mf_preview_id ) );
	}

	/**
	 * Shows form ID in the title on form preview
	 *
	 * @param string $content default title
	 *
	 * @return string
	 */
	public function form_preview_filter_title( $title, $id ) {

		if ( ! is_user_logged_in() ) {
			return $title;
		}

		$mf_preview_id = filter_input( INPUT_GET, 'mf_form_preview', FILTER_VALIDATE_INT );

		if ( empty( $mf_preview_id ) ) {
			return $title;
		}

		if ( get_queried_object_id() !== $id ) {
			return $title;
		}

		return sprintf( 'Preview: MACS Form ID: %d', $mf_preview_id );
	}
}
