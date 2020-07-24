<?php
/**
 * General Forms controller
 * Deals with common forms functions such as shortcodes, AJAX submisson callback,
 * general form builder functionalities and form's front-end scripts
 */

namespace Proper_Forms\Forms;

use Proper_Forms;
use Proper_Forms\Forms\Form as Form;

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
		add_action( 'save_post_pf_form', [ $this, 'save_form_settings' ], 10, 2 );
		add_action( 'post_updated', [ $this, 'reset_forms_list_after_update' ], 90, 3 );

		// Form usage post meta
		add_action( 'save_post', [ $this, 'add_form_usage_meta' ], 10, 2 );

		// Cache controll
		add_action( 'added_post_meta', [ $this, 'update_form_cache' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'update_form_cache' ], 10, 4 );
		add_action( 'delete_post', [ $this, 'clean_form_cache' ], 10, 1 );

		// Ajax submission
		add_action( 'wp_ajax_nopriv_pf_submit_form', [ $this, 'submit_form_callback' ] );
		add_action( 'wp_ajax_pf_submit_form', [ $this, 'submit_form_callback' ] );

		// Front end scripts
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ], 20 );
		add_filter( 'script_loader_tag', [ $this, 'add_asyncdefer_attributes' ], 10, 2 );

		// Shortcodes
		add_shortcode( 'proper_form', [ $this, 'form_shortcode_callback' ] );

		// Form preview
		add_filter( 'template_include', [ $this, 'form_preview_template_redirect' ], 99 );
		add_filter( 'the_content', [ $this, 'form_preview_filter_content' ], 199 );
		add_filter( 'the_title', [ $this, 'form_preview_filter_title' ], 199, 2 );

	}

	/**
	 * Register and localize front-end scripts
	 */
	public function frontend_scripts() {

		wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js' );

		wp_enqueue_style( 'cg-proper-forms', sprintf( '%s/cg-proper-forms/assets/css/proper-forms-front.css', plugins_url() ), [], Proper_Forms\VERSION );
		wp_enqueue_script( 'cg-proper-forms', sprintf( '%s/cg-proper-forms/assets/js/proper-forms-front.js', plugins_url() ), [ 'jquery' ], Proper_Forms\VERSION, true );

		wp_enqueue_style( 'datepicker', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );
		wp_enqueue_script( 'datepicker', 'https://code.jquery.com/ui/1.12.1/jquery-ui.js', [ 'jquery' ] );

		wp_localize_script(
			'cg-proper-forms',
			'PF',
			[
				'ajaxURL'   => admin_url( '/admin-ajax.php' ),
				'ajaxNonce' => wp_create_nonce( 'pf_form_submission' ),
				'strings'   => [
					'remove_file'       => __( 'remove file', 'proper-forms' ),
					'select_file'       => __( 'Select File', 'proper-forms' ),
					'uploading'         => __( 'uploading...', 'proper-forms' ),
					'file_selected'     => __( 'File selected', 'proper-forms' ),
					'default_error'     => __( 'This field\'s value is invalid!', 'proper-forms' ),
					'required_error'    => __( 'This field is required!', 'proper-forms' ),
					'email_error'       => __( 'Please enter a valid email address!', 'proper-forms' ),
					'date_format_error' => __( 'Please enter date in valid format!', 'proper-forms' ),
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
				'pf_fields'                 => [
					'filter' => FILTER_DEFAULT,
					'flags'  => FILTER_REQUIRE_ARRAY,
				],
				'_pf_nonce'                 => FILTER_SANITIZE_STRING,
				'pf_form_has_pardot'        => FILTER_VALIDATE_BOOLEAN,
				'pf_form_pardot_handler'    => FILTER_VALIDATE_URL,
				'pf_form_admin_notify'      => FILTER_VALIDATE_BOOLEAN,
				'pf_form_admin_custom_mail' => FILTER_DEFAULT,
				'pf_form_ty_msg'            => FILTER_DEFAULT,
				'pf_form_redirect_url'      => FILTER_VALIDATE_URL,
			]
		);

		if ( empty( $data['_pf_nonce'] ) || ! wp_verify_nonce( $data['_pf_nonce'], 'pf_save_data' ) ) {
			return;
		}

		$pf_fields = $data['pf_fields'];

		if ( empty( $pf_fields ) ) {
			return;
		}

		$pf_fields = array_map(
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
			$pf_fields
		);

		update_post_meta( $post_id, 'pf_fields', $pf_fields );

		// update form settings
		update_post_meta( $post_id, 'pf_form_has_pardot', $data['pf_form_has_pardot'] );
		update_post_meta( $post_id, 'pf_form_pardot_handler', $data['pf_form_pardot_handler'] );
		update_post_meta( $post_id, 'pf_form_admin_notify', $data['pf_form_admin_notify'] );
		update_post_meta( $post_id, 'pf_form_admin_custom_mail', $data['pf_form_admin_custom_mail'] );
		update_post_meta( $post_id, 'pf_form_ty_msg', $data['pf_form_ty_msg'] );
		update_post_meta( $post_id, 'pf_form_redirect_url', $data['pf_form_redirect_url'] );

		// clear all forms list cache
		wp_cache_delete( 'all_forms_array', 'proper_forms' );
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

		if ( 'pf_fields' !== $meta_key || ! in_array( get_post_status( $object_id ), [ 'draft', 'publish' ], true ) ) {
			return;
		}

		$cache_key = sprintf( 'pf_form_%d', $object_id );

		// First delete old cache - otherwise Builder would return the cached object and we'd end up with the same data.
		wp_cache_delete( $cache_key, 'proper_forms' );

		$form_object = Proper_Forms\Builder::get_instance()->make_form( $object_id );

		// Cache the updated form object.
		wp_cache_set( $cache_key, $form_object, 'proper_forms' );
	}

	/**
	 * Delete form object from cache when form post is deleted
	 *
	 * @param int $post_id
	 */
	public function clean_form_cache( $post_id ) {
		if ( 'pf_form' !== get_post_type( $post_id ) ) {
			return;
		}
		$cache_key = sprintf( 'pf_form_%d', $post_id );
		wp_cache_delete( $cache_key, 'proper_forms' );
		wp_cache_delete( 'all_forms_array', 'proper_forms' );
	}

	/**
	 * Clean cache after the form post is updated
	 *
	 * @param int $post_id
	 * @param WP_Post $post_after
	 * @param WP_Post $post_before
	 */
	public function reset_forms_list_after_update( $post_id, $post_after, $post_before ) {
		if ( 'pf_form' !== $post_after->post_type ) {
			return;
		}
		wp_cache_delete( 'all_forms_array', 'proper_forms' );
	}

	/**
	 * Get a list of all existing forms and its details
	 * @TODO
	 */
	public function get_forms() {
		return [];
	}

	/**
	 * Proper Forms Shortcode callback.
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
				'nonce_pf_submit'    => FILTER_SANITIZE_STRING,
				'form_data'          => FILTER_SANITIZE_STRING,
				'recaptcha_response' => FILTER_SANITIZE_STRING,
			]
		);

		// Validate nonce before processign data.
		check_ajax_referer( 'pf_form_submission', 'nonce_pf_submit' );

		$data['form_data'] = json_decode( html_entity_decode( $data['form_data'] ), true );

		$form_id = absint( $data['form_data']['form_id'] );

		if ( empty( $data['recaptcha_response'] ) || ! $this->validate_recaptcha( $data['recaptcha_response'] ) ) {
			wp_send_json_error( 'invalid or missing recaptcha response', 409 );
			wp_die();
		}

		if ( empty( $form_id ) ) {
			wp_send_json_error( new \WP_Error( 'no_form_id', __( 'No form ID provided on submission', 'proper-forms' ) ) );
			wp_die();
		}

		$form = Proper_Forms\Builder::get_instance()->make_form( $form_id );

		$sub    = $form->submit( $data['form_data'] );
		$ty_msg = $this->get_thank_you_message( $form, $data['form_data'] );

		// successful submission returns WP Post id (int), array means trouble
		if ( is_array( $sub ) ) {
			wp_send_json_error( $sub );
			wp_die();
		}

		wp_send_json_success(
			[
				'sub_id' => $sub,
				'ty_msg' => $ty_msg,
			]
		);

		wp_die();

	}

	/**
	 * Builds thank you message based on form settings and submitted data
	 * @param Form $form
	 * @param array $submitted_data
	 */
	private function get_thank_you_message( Form $form, $submitted_data ) {

		if ( empty( $form->thank_you_message ) ) {
			return '';
		}

		// replace placeholders from message text string with submitted data
		$thankyou_msg = preg_replace_callback(
			'/{field:(.[\S]+)}/m',
			function( $matches ) use ( $form, $submitted_data ) {
				$field = array_shift( wp_filter_object_list( (array) $form->fields, [ 'pardot_handler' => $matches[1] ] ) );
				if ( ! empty( $field->id ) && ! empty( $submitted_data[ $field->id ] ) ) {
					return $submitted_data[ $field->id ];
				}
				return '';
			},
			$form->thank_you_message
		);

		return $thankyou_msg;

	}

	/**
	 * Validates google reCaptcha response
	 */
	private function validate_recaptcha( $user_response ) {

		if ( is_multisite() ) {
			$options = get_blog_option( 1, 'pf_settings' );
		} else {
			$options = get_option( 'pf_settings' );
		}
		$secret  = ! empty( $options['captcha_secret'] ) ? $options['captcha_secret'] : '';

		if ( empty( $secret ) ) {
			return false;
		}

		$google_response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
			'timeout' => 30,
			'headers' => [],
			'body'    => [
				'secret'   => $secret,
				'response' => $user_response,
			],
		] );

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
		$options = get_option( 'pf_settings' );

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

		$nf_form_id   = absint( $atts['id'] );
		$form_classes = explode( ',', $atts['classes'] );

		if ( ! $nf_form_id ) {
			return;
		}

		$form_id = $this->get_form_by_nf_id( $nf_form_id );

		return $this->get_form_markup( $form_id, $form_classes, $nf_form_id );
	}

	/**
	 * Builds form markup
	 *
	 * @param $form_id      int
	 * @param $form_classes mixed (string|array)
	 *
	 * @return string
	 */
	private function get_form_markup( $form_id, $form_classes, $nf_legacy_id = 0 ) {

		if ( ! $form_id ) {
			return;
		}

		$classes      = apply_filters( 'pf_form_container_class', $form_classes, $form_id );
		$classes      = implode( ' ', (array) $classes );
		$form         = Proper_Forms\Builder::get_instance()->make_form( $form_id );
		$error_msgs   = $form->get_error_messages_array();
		$redirect_url = get_post_meta( $form_id, 'pf_form_redirect_url', true );

		//Pass custom error messages to the script

		wp_localize_script(
			'cg-proper-forms',
			'PF_CONFIG',
			[
				$form_id => [
					'errors'   => $error_msgs,
					'redirect' => $redirect_url,
				],
			]
		);

		// Output
		ob_start();

		do_action( 'pf_before_rendered_form', $form_id );
		?>
			<div class="pf_forms__container <?php esc_attr( $classes ); ?>">
				<?php
				$form->render_form();

				if ( ! empty( $nf_legacy_id ) ) {
					$this->render_form_editor_notice( $form_id, $nf_legacy_id );
				}
				?>
			</div>
		<?php

		do_action( 'pf_after_rednered_form', $form_id );

		return ob_get_clean();
	}

	/**
	 * Retrieves forms migrated from Ninja Forms based on their original ID
	 *
	 * @param  int $nf_form_id
	 *
	 * @return int
	 */
	public function get_form_by_nf_id( $nf_form_id ) {

		$pf_form_id = wp_cache_get( "nf_to_pf_id_{$nf_form_id}", 'proper_forms' );

		if ( false === $pf_form_id ) {

			$forms_query = new \WP_Query( [
				'post_type'           => 'pf_form',
				'posts_per_page'      => 1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'post_status'         => 'publish',
				'sub_query'           => false,
				'meta_query'          => [ // phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => 'nf_form_id',
						'value'   => $nf_form_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			] );

			while ( $forms_query->have_posts() ) :
				$forms_query->the_post();
				$pf_form_id = get_the_ID();
			endwhile;

			wp_reset_query();

			wp_cache_set( "nf_to_pf_id_{$nf_form_id}", $pf_form_id, 'proper_forms', MONTH_IN_SECONDS * 3 );
		}

		return $pf_form_id;
	}

	/**
	 * Print notice with form migration info for logged in users
	 */
	private function render_form_editor_notice( $form_id ) {
		if ( ! current_user_can('edit_posts') || ! is_int( $form_id ) ) {
			return;
		}

		$edit_link = get_edit_post_link( $form_id );

		?>
		<div class="pf_notice pf_notice--editor">
			<?php
			// translators: %1$d is a form ID, %2$s is an url
			echo wp_kses_post( sprintf( 'This form is migrated from Ninja Forms plugin and rendered with an old shortcode.<br />Current ID of this form is %1$d. You can edit it <a href="%2$s" target="_blank">here</a>.<br />To get rid of this notice change the form shortcode in the content of this post to: <strong>[proper_form id="%1$d"]</strong>. <br />No worries! This notice is not visible for site visitors - only you, The Editor, can see it.', $form_id, $edit_link ) );
			?>
		</div>
		<?php
	}

	/**
	 * Saves additional meta for posts that use Proper Forms and in form posts.
	 * @see 'save_post' hook
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function add_form_usage_meta( $post_id, $post ) {

		if ( ! in_array( $post->post_status, [ 'publish', 'draft' ], true ) ) {
			return;
		}

		if ( in_array( $post->post_status, get_post_types( [ 'public' => false ] ), true ) ) {
			return;
		}

		$form_ids = $this->grab_used_form_ids( $post );

		// Remove reference to this post on all forms posts that are not used anymore
		$this->delete_old_forms_usage_data( $post_id, $form_ids );

		if ( empty( $form_ids ) ) {
			delete_post_meta( $post_id, 'has_pf_form' );
			return;
		}

		// Update curently used Forms meta
		foreach ( $form_ids as $form_id ) {
			$form_usage_meta = get_post_meta( $form_id, 'used_in', true ) ?: [];
			$new_usage_meta  = array_unique( array_merge( $form_usage_meta, [ $post_id ] ) );
			update_post_meta( $form_id, 'used_in', $new_usage_meta );
		}

		// Update post meta with curently used posts IDs
		update_post_meta( $post_id, 'has_pf_form', $form_ids );
	}

	/**
	 * Extracts all currently used forms in a post
	 * @param \WP_Post $post
	 *
	 * @return array
	 */
	private function grab_used_form_ids( $post ) {

		$post_id  = $post instanceof \WP_Post ? $post->ID : 0;
		$form_ids = [];

		if ( empty( $post_id ) ) {
			return $form_ids;
		}

		// Check post meta pointing to Proper Forms
		$form_meta_keys = apply_filters( 'pf_form_related_meta', [] );

		if ( is_array( $form_meta_keys ) ) {
			foreach ( $form_meta_keys as $meta_key ) {
				$used_form_id = get_post_meta( $post_id, $meta_key, true );
				if ( ! empty( $used_form_id ) ) {
					$form_ids[] = $used_form_id;
				}
			}
		}

		// Extract form IDs from shortcodes in post content
		preg_match_all( '/\[proper_form id=(?:"|\')?(\d+)(?:"|\')?\]/', $post->post_content, $matches, PREG_SET_ORDER, 0 );

		if ( ! empty( $matches ) ) {
			foreach ( $matches as $match ) {
				$form_ids[] = $match[1];
			}
		}

		// If plugin is set to use Ninja Forms shortcodes, get forms mapped to old NF.

		$pf_settings = get_option( 'pf_settings' );
		if ( ! empty( $pf_settings['nf_shortcode'] ) ) {
			preg_match_all( '/\[ninja_form id=(?:"|\')?(\d+)(?:"|\')?\]/', $post->post_content, $nf_matches, PREG_SET_ORDER, 0 );
			foreach( $nf_matches as $nf_match ) {
				$migrated_pf = $this->get_form_by_nf_id( $nf_match[1] );
				if ( ! empty( $migrated_pf ) ) {
					$form_ids[] = $migrated_pf;
				}
			}
		}

		return array_unique( $form_ids );
	}

	/**
	 * Deletes reference to the post in Form post
	 * @param int   $post_id
	 * @param array $current_forms_ids
	 */
	private function delete_old_forms_usage_data( $post_id, $current_forms_ids ) {

		// Get deleted forms IDs
		$previous_forms = get_post_meta( $post_id, 'has_pf_form', true );

		if ( empty( $previous_forms ) ) {
			return;
		}

		$deleted_forms = array_diff( $previous_forms, $current_forms_ids );

		foreach ( $deleted_forms as $deleted_form_id ) {
			$form_usage_meta = get_post_meta( $deleted_form_id, 'used_in', true ) ?: [];
			$new_usage_meta  = array_unique( array_diff( $form_usage_meta, [ $post_id ] ) );
			update_post_meta( $deleted_form_id, 'used_in', $new_usage_meta );
		}
	}

	/**
	 * Returns form object from default forms settings by slug
	 *
	 * @param string $form_slug
	 */
	public function get_default( $form_slug ) {
		$pf_options = get_option( 'pf_settings' );
		$form_id    = ! empty( $pf_options[ "pf_default_{$form_slug}" ] ) ? absint( $pf_options[ "pf_default_{$form_slug}" ] ) : 0;

		if ( empty( $form_id ) ) {
			return false;
		}

		return Proper_Forms\Builder::get_instance()->make_form( $form_id );
	}

	/**
	 * Loads preview template (or single.php if former doesn't exist) when pf_form_preview query param is present in URL
	 *
	 * @param string $template default template
	 *
	 * @return string
	 */
	public function form_preview_template_redirect( $template ) {

		if ( ! is_user_logged_in() ) {
			return $template;
		}

		$pf_preview_id = filter_input( INPUT_GET, 'pf_form_preview', FILTER_VALIDATE_INT );

		if ( empty( $pf_preview_id ) ) {
			return $template;
		}

		$new_template = locate_template( [ 'pf-form-preview.php', 'single.php' ] );

		if ( empty( $new_template ) ) {
			return $template;
		}

		return $new_template;
	}

	/**
	 * Switches content for shortcode output when pf_form_preview query param is present in URL
	 *
	 * @param string $content default content
	 *
	 * @return string
	 */
	public function form_preview_filter_content( $content ) {

		if ( ! is_user_logged_in() ) {
			return $content;
		}

		$pf_preview_id = filter_input( INPUT_GET, 'pf_form_preview', FILTER_VALIDATE_INT );

		if ( empty( $pf_preview_id ) ) {
			return $content;
		}

		return do_shortcode( sprintf( '[proper_form id="%d"]', $pf_preview_id ) );
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

		$pf_preview_id = filter_input( INPUT_GET, 'pf_form_preview', FILTER_VALIDATE_INT );

		if ( empty( $pf_preview_id ) ) {
			return $title;
		}

		if ( get_queried_object_id() !== $id ) {
			return $title;
		}

		return sprintf( 'Preview: Proper Form ID: %d', $pf_preview_id );
	}
}
