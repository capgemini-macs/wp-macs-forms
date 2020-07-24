<?php
/**
 * Main Admin controller
 * Sets up meta boxes and admin scripts.
 */

namespace Proper_Forms;
use Proper_Forms\Fields\Field_Types as Field_Types;
use Proper_Forms\Forms\Form as Form;
use Proper_Forms\Submissions\Downloads as Downloads;

class Admin {

	/**
	 * Sinlgeton instance of the class
	 *
	 * @var Admin
	 */
	private static $instance;

	/**
	 * Returns singleton instance of the class
	 *
	 * @return object (Admin)
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) && ! self::$instance instanceof Admin ) {
			self::$instance = new Admin;

			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class, hook in
	 */
	protected function init() {

		// Edit screens
		add_action( 'add_meta_boxes_pf_form', [ $this, 'add_form_meta_boxes' ], 10, 1 );
		add_action( 'add_meta_boxes_pf_sub', [ $this, 'add_sub_meta_boxes' ], 10, 1 );
		add_action( 'add_meta_boxes_pf_file', [ $this, 'add_file_meta_boxes' ], 10, 1 );
		add_action( 'do_meta_boxes', [ $this, 'metaboxes_cleanup' ], 100, 0 );

		// admin scripts & styles
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ], 20, 0 );

		// settings page
		add_action( 'admin_menu', [ $this, 'add_pf_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'pf_settings_init' ] );

		// forms admin page columns
		add_filter( 'manage_pf_form_posts_columns', [ $this, 'forms_posts_columns' ], 10, 1 );
		add_action( 'manage_pf_form_posts_custom_column', [ $this, 'forms_admin_columns_content' ], 10, 2 );

		// submissions admin page columns
		add_filter( 'manage_pf_sub_posts_columns', [ $this, 'subs_posts_columns' ], 10, 1 );
		add_action( 'manage_pf_sub_posts_custom_column', [ $this, 'subs_form_column_content' ], 10, 2 );
		add_filter( 'manage_edit-pf_sub_sortable_columns', [ $this, 'subs_sortable_columns' ] );
		add_action( 'pre_get_posts', [ $this, 'subs_by_form_order' ] );
		add_filter( 'parse_query', [ $this, 'subs_parse_filter' ] );
		add_action( 'restrict_manage_posts', [ $this, 'subs_forms_dropdown_filter' ] );
		add_action( 'save_post_pf_form', [ $this, 'delete_forms_dropdown_cache' ] );

		// tinyMCE shortcode button
		add_filter( 'mce_external_plugins', [ $this, 'load_mce_plugin_script' ], 10, 1 );
		add_filter( 'mce_buttons', [ $this, 'add_mce_button' ], 10, 1 );
		add_action( 'wp_ajax_populate_forms', [ $this, 'tinymce_populate_forms' ] );

		// Unique title check
		add_filter( 'wp_insert_post_data', [ $this, 'force_draft_on_non_unique' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'pf_admin_notices' ] );

		// Form preview button
		add_action( 'post_submitbox_misc_actions', [ $this, 'add_form_preview_btn' ] );

		// Downloads
		add_action( 'admin_post_pf_download.csv', [ $this, 'pf_export_callback' ] );
		add_action( 'admin_footer', [ $this, 'admin_posts_list_footer' ], 10, 1 );
	}

	/**
	 * Enqueue admin styles and scripts
	 */
	public function admin_scripts() {

		if ( ! in_array( get_current_screen()->id, [ 'pf_form', 'pf_sub', 'pf_file', 'edit-pf_form' ], true ) ) {
			return;
		}

		wp_enqueue_style( 'cg-proper-forms', sprintf( '%1$s/cg-proper-forms/assets/css/proper-forms-admin.css', plugins_url() ), [], VERSION );

		wp_enqueue_script( 'cg-pf-admin-js', sprintf( '%1$s/cg-proper-forms/assets/js/proper-forms-admin.js', plugins_url() ), [ 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ], VERSION, true );

		wp_localize_script(
			'cg-pf-admin-js',
			'PF',
			[
				'ajaxURL'           => admin_url( 'admin-ajax.php' ),
				'postId'            => filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT ),
				'string_delete_row' => esc_attr__( 'Delete this row', 'proper-forms' ),
			]
		);
	}

	/**
	 * Loads TinyMCE js plugin
	 *
	 * @param  array $plugin_array
	 * @return array
	 */
	public function load_mce_plugin_script( $plugin_array ) {
		$mce_plugin_url = sprintf( '%1$s/cg-proper-forms/assets/js/proper-forms-tinymce.js?ver=1.2', plugins_url() );

		$plugin_array['proper_form'] = $mce_plugin_url;
		return $plugin_array;
	}

	/**
	 * Adds button to the TinyMCE editor
	 *
	 * @param  array $buttons
	 * @return array
	 */
	public function add_mce_button( $buttons ) {
		array_push( $buttons, 'proper_form' );
		return $buttons;
	}

	public function tinymce_populate_forms() {

		$forms_by_name = wp_cache_get( 'forms_by_name', 'proper_forms' );

		// run the query also if empty array is currently cached
		if ( empty( $forms_by_name ) ) {

			$query = new \WP_Query(
				[
					'post_type'      => 'pf_form',
					'post_status'    => [ 'publish' ],
					'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- cached
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				]
			);

			$forms_by_name = [];

			while ( $query->have_posts() ) :
				$query->the_post();
				$forms_by_name[ get_the_title() ] = get_the_ID();
			endwhile;
			wp_reset_postdata();
		}

		wp_cache_set( 'forms_by_name', $forms_by_name, 'proper_forms', DAY_IN_SECONDS );

		wp_send_json_success( $forms_by_name );
		exit;
	}


	/**
	 * Add meta boxes for Form Builder
	 *
	 * @param string $post_type
	 */
	public function add_form_meta_boxes( $post_type ) {

		add_meta_box(
			'pf_form_usage_box',
			__( 'Form settings', 'proper-forms' ),
			[ $this, 'render_meta_box_infobox' ],
			[ 'pf_form' ],
			'normal',
			'high'
		);

		add_meta_box(
			'pf_form_builder',
			__( 'Form Builder', 'proper-forms' ),
			[ $this, 'render_meta_box_builder' ],
			[ 'pf_form' ],
			'normal',
			'high'
		);
	}

	/**
	 * Form builder meta box output
	 *
	 * @param \WP_Post $post post object
	 */
	public function render_meta_box_builder( $post ) {

		$form = Builder::get_instance()->make_form( $post->ID );

		?>
		<div class="pf_builder pf-box">
			<div class="pf-row">
				<div class="pf_builder__canvas pf-cell-8">
					<ul class="pf_list pf_list--canvas sortable">
						<?php $form->render_admin_form_fields(); ?>
					</ul>
				</div>
				<div class="pf_builder__picker pf-cell-4">
					<h3 class="pf-subtitle"><?php esc_html_e( 'Available Fields', 'proper-forms' ); ?></h3>

					<ul class="pf_list pf_list--picker">
						<?php $this->render_available_fields_picker(); ?>
					</ul>
				</div>

				<?php wp_nonce_field( 'pf_save_data', '_pf_nonce' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Populate Form Builder menu with fields placeholders
	 */
	public function render_available_fields_picker() {

		$field_types = new Field_Types();

		foreach ( $field_types->get_active_fields_slugs() as $type ) {
			$field = Builder::get_instance()->make_field( $type );
			$field->render_picker_item();
		}
	}

	private function is_used_as_default( $form_id ) {
		$pf_options = get_option( 'pf_settings' );

		if ( ! is_array( $pf_options ) ) {
			return false;
		}

		unset( $pf_options['captcha_key'] );
		unset( $pf_options['captcha_secret'] );
		unset( $pf_options['nf_shortcode'] );

		if ( in_array( $form_id, array_values( $pf_options ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * TODO: Form infobox meta box output
	 *
	 * @param \WP_Post $post post object
	 */
	public function render_meta_box_infobox( $post ) {
		$has_pardot        = get_post_meta( $post->ID, 'pf_form_has_pardot', true ) ?: '';
		$pardot_handler    = get_post_meta( $post->ID, 'pf_form_pardot_handler', true ) ?: '';
		$has_notification  = get_post_meta( $post->ID, 'pf_form_admin_notify', true ) ?: '';
		$notification_mail = get_post_meta( $post->ID, 'pf_form_admin_custom_mail', true ) ?: get_option( 'admin_email' );
		$custom_ty_msg     = get_post_meta( $post->ID, 'pf_form_ty_msg', true ) ?: '';
		$used_in           = get_post_meta( $post->ID, 'used_in', true ) ?: [];
		$shortcode         = sprintf( '[proper_form id="%d"]', absint( $post->ID ) );
		$redirect_url      = get_post_meta( $post->ID, 'pf_form_redirect_url', true ) ?: '';
		?>
		<div class="pf_infobox pf-box">

			<div class="pf-row">
				<div class="pf-cell-12">
					<h3 class="pf-subtitle"><?php esc_html_e( 'Form shortcode', 'proper-forms' ); ?></h3>
				</div>
				<div class="pf-cell-4 pf_setting">
					<input type="text" value="<?php echo esc_attr( $shortcode ); ?>" readonly />
				</div>
			</div>

			<div class="pf-row">
				<div class="pf-cell-12">
					<h3 class="pf-subtitle"><?php esc_html_e( 'Email notification', 'proper-forms' ); ?></h3>
				</div>
				<div class="pf-cell-12 pf_setting">
					<input id="pf_form_admin_notify" class="" name="pf_form_admin_notify" type="checkbox" value="1" <?php checked( true, $has_notification ); ?>>
					<label for="pf_form_admin_notify"><?php echo esc_html( 'Send admin notification after submission is sent', 'proper-forms' ); ?></label>
				</div>

				<div class="pf-cell-8 pf_setting">
					<label for="pf_form_admin_custom_mail"><?php echo esc_html( 'Custom admin e-mail for notifications', 'proper-forms' ); ?></label><br />
					<textarea id="pf_form_admin_custom_mail" class="" name="pf_form_admin_custom_mail"><?php echo esc_html( $notification_mail ); ?></textarea>
				</div>
			</div>

			<div class="pf-row">
				<div class="pf-cell-12">
					<h3 class="pf-subtitle"><?php esc_html_e( 'Pardot integration', 'proper-forms' ); ?></h3>
				</div>
				<div class="pf-cell-12 pf_setting">
					<input id="pf_form_has_pardot" class="" name="pf_form_has_pardot" type="checkbox" value="1" <?php checked( true, $has_pardot ); ?>>
					<label for="pf_form_has_pardot"><?php esc_html_e( 'Connect this form to Pardot handler', 'proper-forms' ); ?></label>
				</div>

				<div class="pf-cell-8 pf_setting">
					<label for="pf_form_pardot_handler"><?php esc_html_e( 'Pardot handler URL', 'proper-forms' ); ?></label><br />
					<input id="pf_form_pardot_handler" class="pf_pardot_rel" name="pf_form_pardot_handler" type="text" value="<?php echo esc_attr( $pardot_handler ); ?>" />
				</div>
			</div>

			<div class="pf-row trigger-wp-action">
				<div class="pf-cell-12">
					<h3 class="pf-subtitle"><?php esc_html_e( 'Trigger WP Action', 'proper-forms' ); ?></h3>
				</div>
				<div class="pf-cell-12 pf_setting">
					<input id="pf_form_trigger_action" class="" name="pf_form_trigger_action" type="checkbox" value="1">
					<label for="pf_form_trigger_action"><?php echo esc_html( 'Trigger custom WP action', 'proper-forms' ); ?></label>
				</div>

				<div class="pf-cell-8 pf_setting">
					<label for="pf_form_action_handler"><?php echo esc_html( 'WP action handler', 'proper-forms' ); ?></label><br />
					<input id="pf_form_action_handler" class="" name="pf_form_action_handler" type="text" value="<?php echo esc_attr( '' ); ?>" />
				</div>
			</div>

			<div class="pf-row">
				<div class="pf-cell-12">
					<h3 class="pf-subtitle"><?php esc_html_e( 'Thank you message', 'proper-forms' ); ?></h3>
				</div>

				<div class="pf-cell-8 pf_setting">
					<label for="pf_form_ty_msg"><?php echo esc_html( 'Custom "Thank You" message', 'proper-forms' ); ?></label>
					<?php
						$editor_id      = 'pf_form_ty_msg';
						$editor_options = array( 'media_buttons' => false );

						wp_editor( $custom_ty_msg, $editor_id, $editor_options );
					?>
				</div>
			</div>

			<div class="pf-row">
				<div class="pf-cell-12">
					<h3 class="pf-subtitle"><?php esc_html_e( 'Redirect url', 'proper-forms' ); ?></h3>
				</div>

				<div class="pf-cell-8 pf_setting">
					<label for="pf_form_redirect_url"><?php echo esc_html( 'Custom redirect url', 'proper-forms' ); ?></label><br />
					<input id="pf_form_redirect_url" name="pf_form_redirect_url" type="url" value="<?php echo esc_url( $redirect_url ); ?>" />
				</div>
			</div>

			<div class="pf-row">
				<div class="pf-cell-12">
					<h3 class="pf-subtitle"><?php esc_html_e( 'Form Usage', 'proper-forms' ); ?></h3>
				</div>

				<div class="pf-cell-8 pf_setting">
					<?php if ( $this->is_used_as_default( $post->ID ) ) : ?>
						<span><span class="dashicons dashicons-admin-post"></span> Used as a default form</span></br>
					<?php
					endif;
					foreach ( (array) $used_in as $post_id ) :
						?>
						<span class="dashicons dashicons-welcome-write-blog"></span> <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a><br />
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add meta box for File
	 *
	 * @param string $post_type
	 */
	public function add_file_meta_boxes( $post_type ) {

		add_meta_box(
			'pf_file_data',
			__( 'File data', 'proper-forms' ),
			[ $this, 'render_meta_box_file_data' ],
			[ 'pf_file' ],
			'normal',
			'high'
		);
	}

	/**
	 * File data meta box output
	 *
	 * @param File $file_post post object
	 */
	public function render_meta_box_file_data( $file_post ) {

		$file = Builder::get_instance()->make_file( $file_post->ID );

		if ( empty( $file->file_id ) ) {
			return;
		}
		?>
		<div class="pf_file pf-box">
			<div class="pf-row">
				<div class="pf-cell-12">
					<p>
					<?php
					echo esc_html__( 'Submitted on: ', 'proper-forms' );
					echo esc_html( $file_post->post_date );
					?>
					</p>

					<?php if ( ! empty( $file->sub_id ) ) : ?>
					<p><?php esc_html_e( 'Attached to submission:', 'proper-forms' ); ?> <a href="<?php echo esc_url( get_permalink( $file->sub_id ) ); ?>"><?php echo esc_html( get_the_title( $file->sub_id ) ); ?></a></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="pf-row">
				<div class="pf-cell-12">
					<div class="pf-row">
						<div class="pf-cell-4">
							<strong><?php echo esc_html( 'File title', 'proper-forms' ); ?></strong>
						</div>
						<div class="pf-cell-8">
							<span><?php echo esc_html( $file->title ); ?></span>
						</div>
					</div>
					<div class="pf-row">
						<div class="pf-cell-4">
							<strong><?php echo esc_html( 'File type', 'proper-forms' ); ?></strong>
						</div>
						<div class="pf-cell-8">
							<span><?php echo esc_html( $file->filetype ); ?></span>
						</div>
					</div>
					<div class="pf-row">
						<div class="pf-cell-4">
							<strong><?php echo esc_html( 'File URL', 'proper-forms' ); ?></strong>
						</div>
						<div class="pf-cell-8">
							<span><a href="<?php echo esc_url( $file->url ); ?>"><?php echo esc_url( $file->url ); ?></a></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add meta boxes for Submissions
	 *
	 * @param string $post_type
	 */
	public function add_sub_meta_boxes( $post_type ) {

		add_meta_box(
			'pf_sub_data',
			__( 'Submission data', 'proper-forms' ),
			[ $this, 'render_meta_box_sub_data' ],
			[ 'pf_sub' ],
			'normal',
			'high'
		);
	}

	/**
	 * Submission data meta box output
	 *
	 * @param \WP_Post $post post object
	 */
	public function render_meta_box_sub_data( $submission_post ) {

		$sub = Builder::get_instance()->make_submission( $submission_post->ID );

		if ( empty( $sub->form_id ) ) {
			return;
		}
		?>
		<div class="pf_submission pf-box">
			<div class="pf-row">
				<div class="pf-cell-12">
					<p>
					<?php
					echo esc_html__( 'Submitted on: ', 'proper-forms' );
					echo esc_html( $submission_post->post_date );
					?>
					</p>

					<p>Form: <a href="<?php echo esc_url( get_edit_post_link( $sub->form_id ) ); ?>"><?php echo esc_html( get_the_title( $sub->form_id ) ); ?></a></p>
				</div>
			</div>
			<div class="pf-row">
				<div class="pf-cell-12">
					<?php
					foreach ( (array) $sub->fields as $field ) :
						if ( 'submit' === $field->type ) {
							continue;
						}
						?>
						<div class="pf-row">
							<div class="pf-cell-4">
								<strong><?php echo esc_html( $field->label ); ?>:</strong>
							</div>
							<div class="pf-cell-4">
								<?php
								if ( is_array( $field->saved_value ) ) :
									$vals = array_map(
										function( $item ) {
											return sprintf( '<span>%s</span>', $item );
										},
										$field->saved_value
									);

									echo wp_kses(
										implode( '<br />', $vals ),
										[
											'br'   => [],
											'span' => [],
										]
									);
								else :
									if ( 'file_upload' === $field->type ) {
										$this->maybe_print_file_link( $field );
									} else {
										echo sprintf( '<span>%s</span>', esc_html( $field->saved_value ) );
									}
								endif;
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add shortcode column to forms.
	 */
	public function forms_posts_columns( $columns ) {
		unset( $columns['taxonomy-cg-campaign-taxonomy'] );
		unset( $columns['date'] );

		$columns['shortcode'] = __( 'Shortcode', 'proper-forms' );
		$columns['usage'] = __( 'Used in', 'proper-forms' );
		$columns['date'] = __( 'Date', 'proper-forms' );

		return $columns;
	}

	/**
	 * Populate shortcode column with form shortcode
	 * @param $column
	 * @param $post_id
	 */
	public function forms_admin_columns_content( $column, $post_id ) {
		if ( 'shortcode' === $column ) {
			$shortcode = sprintf( '[proper_form id="%d"]', absint( $post_id ) );
			echo sprintf( '<input class="" type="text" value="%s" cols="200" readonly />', esc_attr( $shortcode ) );
		}

		if ( 'usage' === $column ) {
			$ids = get_post_meta( $post_id, 'used_in', true );
			$ids = is_array( $ids ) ? implode( ',', $ids ) : $ids;
			echo $ids ?: esc_html__( 'Unused', 'proper-forms' );
		}
	}

	/**
	 * Add form column to submissions
	 */
	public function subs_posts_columns( $columns ) {
		$columns['form'] = __( 'Form', 'proper-forms' );

		unset( $columns['status'] );
		unset( $columns['tags'] );
		unset( $columns['syndication'] );
		unset( $columns['taxonomy-cg-campaign-taxonomy'] );

		return $columns;
	}

	/**
	 * Populate form column with form title
	 * @param $column
	 * @param $post_id
	 */
	public function subs_form_column_content( $column, $post_id ) {
		if ( 'form' === $column ) {
			$form_id = get_post_meta( $post_id, 'form_id', true );
			echo ! empty( $form_id ) ? esc_html( get_the_title( $form_id ) ) : '';
		}
	}

	public function subs_sortable_columns( $columns ) {
		$columns['form'] = 'form';
		return $columns;
	}

	public function subs_by_form_order( $query ) {
		global $pagenow;
		$post_type         = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_STRING );
		$is_subs_edit_page = 'edit.php' === $pagenow && 'pf_sub' === $post_type;

		if ( ! is_admin() || ! $query->is_main_query() || ! $is_subs_edit_page ) {
			return;
		}

		if ( 'form' === $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'meta_key', 'form_id' );
			$query->set( 'meta_type', 'numeric' );
		}
	}

	public function subs_parse_filter( $query ) {
		global $pagenow;
		$post_type         = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_STRING );
		$form_filter_val   = filter_input( INPUT_GET, 'filter-form', FILTER_VALIDATE_INT );
		$is_subs_edit_page = 'edit.php' === $pagenow && 'pf_sub' === $post_type;

		if ( ! is_admin() || ! $is_subs_edit_page || empty( $form_filter_val ) ) {
			return;
		}

		$query->set( 'meta_key', 'form_id' );
		$query->set( 'meta_value', $form_filter_val );
	}

	public function subs_forms_dropdown_filter() {

		$options = wp_cache_get( 'pf_forms_dropdown', 'proper-forms' );

		if ( false === $options ) {

			$filter_query = new \WP_Query( [
				'post_type'           => 'pf_form',
				'posts_per_page'      => 500,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'post_status'         => 'publish',
				'sub_query'           => false,
				'orderby'             => 'title',
				'order'               => 'ASC',
			] );

			$options  = [];
			$selected = filter_input( INPUT_GET, 'filter-form', FILTER_VALIDATE_INT ) ?? '';

			while ( $filter_query->have_posts() ) :
				$filter_query->the_post();

				$options[] = sprintf( '<option value="%1$d" %2$s>%3$s</option>', get_the_ID(), selected( get_the_ID(), $selected, false ), get_the_title() );
			endwhile;

			wp_reset_postdata();

			wp_cache_set( 'pf_forms_dropdown', $options, 'proper-forms', 3 * DAY_IN_SECONDS );
		}

		printf(
			'<select name="filter-form"><option value="">%1$s</option>%2$s</select>',
			esc_html__( 'Any form', 'proper-forms' ),
			wp_kses(
				implode( "\n", $options ),
				[
					'option' => [
						'value'    => true,
						'selected' => true,
					],
				]
			)
		);
	}

	public function delete_forms_dropdown_cache() {
		wp_cache_delete( 'pf_forms_dropdown', 'proper-forms' );
		wp_cache_delete( 'forms_by_id', 'proper-forms' );
	}

	/**
	 * Remove useless metaboxes
	 */
	public function metaboxes_cleanup() {
		$screens = [ 'pf_form', 'pf_file', 'pf_sub' ];

		remove_meta_box( 'postexcerpt', $screens, 'normal' );
		remove_meta_box( 'trackbacksdiv', $screens, 'normal' );
		remove_meta_box( 'commentstatusdiv', $screens, 'normal' );
		remove_meta_box( 'commentsdiv', $screens, 'normal' );
	}

	public function add_pf_admin_menu() {
		add_submenu_page(
			'options-general.php',
			'Proper Forms',
			'Proper Forms',
			'manage_options',
			'proper_forms',
			[ $this, 'pf_options_page' ]
		);
	}

	public function pf_settings_init() {

		register_setting( 'pf_settings_page', 'pf_settings' );

		$default_forms = apply_filters( 'pf_default_forms', [] );

		// SECTIONS

		add_settings_section(
			'proper_forms_settings_captcha',
			__( 'CAPTCHA settings', 'proper-forms' ),
			null,
			'pf_settings_page'
		);

		add_settings_section(
			'proper_forms_settings_nf',
			__( 'Ninja Forms compatibility', 'proper-forms' ),
			null,
			'pf_settings_page'
		);

		if ( ! empty( $default_forms ) ) {
			add_settings_section(
				'proper_forms_settings_default',
				__( 'Default Forms', 'proper-forms' ),
				null,
				'pf_settings_page'
			);
		}

		// FIELDS

		// 1, Captcha
		add_settings_field(
			'captcha_key',
			__( 'Google reCAPTCHA site key', 'proper-forms' ),
			[ $this, 'pf_captcha_key_render' ],
			'pf_settings_page',
			'proper_forms_settings_captcha'
		);

		add_settings_field(
			'captcha_secret',
			__( 'Google reCAPTCHA secret', 'proper-forms' ),
			[ $this, 'pf_captcha_secret_render' ],
			'pf_settings_page',
			'proper_forms_settings_captcha'
		);

		// 2, Ninja Forms related
		add_settings_field(
			'pf_nf_shortcode',
			__( 'Shortcodes', 'proper-forms' ),
			[ $this, 'pf_nf_shortcode_render' ],
			'pf_settings_page',
			'proper_forms_settings_nf'
		);

		// 3. Default Forms

		foreach ( $default_forms as $slug => $name ) {

			$pf_options = get_option( 'pf_settings' );
			$value      = ! empty( $pf_options[ "pf_default_{$slug}" ] ) ? $pf_options[ "pf_default_{$slug}" ] : '';
			$all_forms  = $this->get_all_published_forms();

			add_settings_field(
				"pf_default_{$slug}",
				$name,
				function() use ( $slug, $value, $all_forms ) {
					$options_array = [];

					foreach ( $all_forms as $form_id => $form_title ) {
						$options_array[] = sprintf(
							'<option value="%1$d" %2$s>%3$s (ID: %1$d)</option>',
							intval( $form_id ),
							selected( intval( $form_id ), intval( $value ), false ),
							esc_html( $form_title )
						);
					}

					printf(
						'<select name="pf_settings[%s]"><option value="">%s</option>%s</select>',
						"pf_default_{$slug}", // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped early
						esc_html__( 'None', 'proper-forms' ),
						implode( "\n", $options_array ) // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped early
					);
				},
				'pf_settings_page',
				'proper_forms_settings_default'
			);
		}
	}

	public function pf_captcha_secret_render() {
		$options = get_option( 'pf_settings' );
		$value   = ! empty( $options['captcha_secret'] ) ? $options['captcha_secret'] : '';
		?>
		<input type="text" name="pf_settings[captcha_secret]" value="<?php echo esc_attr( $value ); ?>">
		<?php
	}

	public function pf_captcha_key_render() {
		$options = get_option( 'pf_settings' );
		$value   = ! empty( $options['captcha_key'] ) ? $options['captcha_key'] : '';
		?>
		<input type="text" name="pf_settings[captcha_key]" value="<?php echo esc_attr( $value ); ?>">
		<?php
	}

	public function pf_nf_shortcode_render() {
		$options = get_option( 'pf_settings' );
		$value   = ! empty( $options['nf_shortcode'] ) ? $options['nf_shortcode'] : '';
		?>
		<label for="pf_nf_shortcode">
		<input name="pf_settings[nf_shortcode]" type="checkbox" id="pf_nf_shortcode" value="1" <?php checked( 1, $value ); ?>>
		Support Ninja Forms shortcodes</label>
		<?php
	}

	public function pf_options_page() {
		?>
		<form action='options.php' method='post'>
			<h2>Proper Forms</h2>
			<?php
			settings_fields( 'pf_settings_page' );
			do_settings_sections( 'pf_settings_page' );
			submit_button();
			?>
		</form>
		<?php
	}

	protected function maybe_print_file_link( $field ) {

		if ( empty( $field->saved_value ) ) {
			return;
		}

		$file = Builder::get_instance()->make_file( $field->saved_value );
		if ( ! empty( $file->url ) && ! empty( $file->title ) ) {
			echo sprintf( '<a href="%1$s">%2$s</a>', esc_url( $file->url ), esc_html( $file->title ) );
		}
	}

	/**
	 * Retrieves all forms as ID => title array and caches it
	 */
	public function get_all_published_forms() {

		/**
		 * wp_reset_postdata() doesn't work after the WP_Query in admin context
		 * and the following custom loop overwrites global post. To avoid this we are
		 * saving $post global in local variable and restoring it later manually
		 */
		global $post;
		$edited_post = $post;

		$forms_array = wp_cache_get( 'all_forms_array', 'proper_forms' );

		if ( false === $forms_array ) {

			$forms_query = new \WP_Query( [
				'post_type'           => 'pf_form',
				'posts_per_page'      => 500,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'post_status'         => 'publish',
				'sub_query'           => false,
				'orderby'             => 'title',
				'order'               => 'ASC',
			] );

			$forms_array = [];

			while ( $forms_query->have_posts() ) :
				$forms_query->the_post();
				$forms_array[ get_the_ID() ] = get_the_title();
			endwhile;

			wp_reset_postdata();

			$post = $edited_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- see comment before the WP loop

			wp_cache_set( 'all_forms_array', $forms_array, 'proper_forms', MONTH_IN_SECONDS );
		}

		return $forms_array;
	}

	/**
	 * Checks if form post with a particular title already exists in DB
	 *
	 * @param string $form_title a title to check
	 * @param int    $post_ID    post ID of a currently processed post (optional)
	 *
	 * @return bool
	 */
	protected function is_form_title_unique( $form_title, $post_ID = 0 ) {

		$existing = $this->get_all_published_forms();

		// ignore itself
		unset( $existing[ $post_ID ] );

		if ( empty( $existing ) ) {
			return true;
		}

		if ( ! in_array( $form_title, $existing, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Filters post data and forces draft status if title is not unique.
	 *
	 * @param $data    post data to insert
	 * @param $postarr posr data array
	 *
	 * @return array
	 */
	public function force_draft_on_non_unique( $data, $postarr ) {

		if ( 'pf_form' !== $data['post_type'] ) {
			return $data;
		}

		if ( in_array( $data['post_status'], [ 'draft', 'auto-draft', 'trash' ], true ) ) {
			return $data;
		}

		if ( ! $this->is_form_title_unique( $data['post_title'], $postarr['ID'] ) ) {

			$data['post_status'] = 'draft';

			// add admin notice query var
			add_filter( 'redirect_post_location', [ $this, 'add_non_unique_notice_var' ], 99 );
		}

		return $data;
	}

	/**
	 * Adds notice query var to form edit screen
	 */
	public function add_non_unique_notice_var( $location ) {
		remove_filter( 'redirect_post_location', [ $this, 'add_non_unique_notice_var' ], 99 );
		return add_query_arg( array( 'non_unique_title' => 1 ), $location );
	}

	/**
	 * Renders custom admin notices
	 */
	public function pf_admin_notices() {

		if ( 1 !== filter_input( INPUT_GET, 'non_unique_title', FILTER_VALIDATE_INT ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'A Form with the same title already exists on this site. This Form has been saved as DRAFT. You have to change its title to be able to publish it.', 'proper-forms' ); ?></strong></p>
		</div>
		<?php
	}

	/**
	 * Renders preview btn on non-published form posts
	 *
	 * @param WP_Post $post
	 */
	public function add_form_preview_btn( $post ) {

		if ( 'pf_form' !== $post->post_type ) {
			return;
		}

		$preview_url = add_query_arg( 'pf_form_preview', absint( $post->ID ), home_url() );
		?>
		<div class="misc-pub-section pf_preview_btn" style="float:left;">
			<a class="preview button" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" id="pf_post-preview"><?php echo esc_html__( 'Preview Form', 'proper-forms' ); ?><span class="screen-reader-text"> <?php echo esc_html__( '(opens in a new window)', 'proper-forms' ); ?> </span></a>
		</div>
	<?php
	}

	/**
	 * Renders jquery code in admin footer to add "Download All Submissions" button
	 *
	 * @param $data
	 */
	public function admin_posts_list_footer( $data ) {
		global $post_type;

		if ( ! is_admin() ) {
			return false;
		}

		$form_id = filter_input( INPUT_GET, 'filter-form', FILTER_VALIDATE_INT );

		if ( $post_type === 'pf_sub' && is_int( $form_id ) && $form_id > 0 ) {
			$url = esc_url( wp_nonce_url( admin_url( "admin-post.php?action=pf_download.csv&form_id={$form_id}" ), 'pf-export', 'pf-export-nonce' ) );
			?>

			<script id="pf-downloads" type="text/javascript">
				jQuery(document).ready(function() {
					var button = '<a href="<?php echo esc_url( $url ); ?>" class="button-primary nf-download-all"><?php echo esc_html__( 'Download All Submissions', 'proper-forms' ); ?></a>';
					jQuery( '#doaction2' ).after( button );
				});
			</script>
		<?php
		}
	}

	/**
	 * Action callback for downloads
	 */
	public function pf_export_callback() {

		// Only site owners can export data
		if (
			! isset( $_GET['pf-export-nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( $_GET['pf-export-nonce'] ), 'pf-export' ) ||
			! current_user_can( 'edit_pf_subs' )
		) {
			wp_die( esc_html__( 'Sorry, you do not have sufficient privilege to perform this operation', 'proper-forms' ) );
		}

		$form_id = filter_input( INPUT_GET, 'form_id', FILTER_VALIDATE_INT );

		// Form ID param is required
		if ( ! is_int( $form_id ) || $form_id < 1 ) {
			wp_die( esc_html__( 'Sorry, the form ID is missing in the request', 'proper-forms' ) );
		}

		$sub_time  = date( 'Y-m-d-H:i' );
		$filename  = "pf_form_{$form_id}_subs_" . $sub_time . ".csv";
		$downloads = new Downloads( $form_id );
		$downloads->get_submissions();
		$downloads->output_csv_file( $filename );
	}
}
