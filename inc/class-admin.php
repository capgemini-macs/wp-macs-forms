<?php
/**
 * Main Admin controller
 * Sets up meta boxes and admin scripts.
 */

namespace MACS_Forms;
use MACS_Forms\Fields\Field_Types as Field_Types;
use MACS_Forms\Forms\Form as Form;

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
		add_action( 'add_meta_boxes_mf_form', [ $this, 'add_form_meta_boxes' ], 10, 1 );
		add_action( 'add_meta_boxes_mf_sub', [ $this, 'add_sub_meta_boxes' ], 10, 1 );
		add_action( 'add_meta_boxes_mf_file', [ $this, 'add_file_meta_boxes' ], 10, 1 );
		add_action( 'do_meta_boxes', [ $this, 'metaboxes_cleanup' ], 100, 0 );

		// admin scripts & styles
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ], 20, 0 );

		// settings page
		add_action( 'admin_menu', [ $this, 'add_mf_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'mf_settings_init' ] );

		// forms admin page columns
		add_filter( 'manage_mf_form_posts_columns', [ $this, 'forms_posts_columns' ], 10, 1 );
		add_action( 'manage_mf_form_posts_custom_column', [ $this, 'forms_shortcode_column_content' ], 10, 2 );

		// submissions admin page columns
		add_filter( 'manage_mf_sub_posts_columns', [ $this, 'subs_posts_columns' ], 10, 1 );
		add_action( 'manage_mf_sub_posts_custom_column', [ $this, 'subs_form_column_content' ], 10, 2 );
		add_filter( 'manage_edit-mf_sub_sortable_columns', [ $this, 'subs_sortable_columns' ] );
		add_action( 'pre_get_posts', [ $this, 'subs_by_form_order' ] );
		add_filter( 'parse_query', [ $this, 'subs_parse_filter' ] );
		add_action( 'restrict_manage_posts', [ $this, 'subs_forms_dropdown_filter' ] );

		// tinyMCE shortcode button
		add_filter( 'mce_external_plugins', [ $this, 'load_mce_plugin_script' ], 10, 1 );
		add_filter( 'mce_buttons', [ $this, 'add_mce_button' ], 10, 1 );
		add_action( 'wp_ajax_populate_forms', [ $this, 'tinymce_populate_forms' ] );

		// Unique title check
		add_filter( 'wp_insert_post_data', [ $this, 'force_draft_on_non_unique' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'mf_admin_notices' ] );

		// Form preview button
		add_action( 'post_submitbox_misc_actions', [ $this, 'add_form_preview_btn' ] );
	}

	/**
	 * Enqueue admin styles and scripts
	 */
	public function admin_scripts() {

		if ( ! in_array( get_current_screen()->id, [ 'mf_form', 'mf_sub', 'mf_file' ], true ) ) {
			return;
		}

		wp_enqueue_style( 'wp-macs-forms', sprintf( '%1$s/wp-macs-forms/assets/css/wp-macs-forms-admin.css', plugins_url() ), [], VERSION );

		wp_enqueue_script( 'cg-mf-admin-js', sprintf( '%1$s/wp-macs-forms/assets/js/wp-macs-forms-admin.js', plugins_url() ), [ 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ], VERSION, true );

		wp_localize_script(
			'cg-mf-admin-js',
			'MF',
			[
				'ajaxURL'           => admin_url( 'admin-ajax.php' ),
				'postId'            => filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT ),
				'string_delete_row' => esc_attr__( 'Delete this row', 'wp-macs-forms' ),
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
		$mce_plugin_url = sprintf( '%1$s/wp-macs-forms/assets/js/wp-macs-forms-tinymce.js', plugins_url() );

		$plugin_array['macs_form'] = $mce_plugin_url;
		return $plugin_array;
	}

	/**
	 * Adds button to the TinyMCE editor
	 *
	 * @param  array $buttons
	 * @return array
	 */
	public function add_mce_button( $buttons ) {
		array_push( $buttons, 'macs_form' );
		return $buttons;
	}

	public function tinymce_populate_forms() {

		$query = new \WP_Query(
			[
				'post_type'      => 'mf_form',
				'post_status'    => [ 'publish' ],
				'posts_per_page' => 50,
				'no_found_rows'  => true,
			]
		);

		$forms_by_id = [];

		while ( $query->have_posts() ) :
			$query->the_post();
			$forms_by_id[ get_the_ID() ] = get_the_title();
		endwhile;
		wp_reset_query();
		wp_send_json_success( $forms_by_id );
	}


	/**
	 * Add meta boxes for Form Builder
	 *
	 * @param string $post_type
	 */
	public function add_form_meta_boxes( $post_type ) {

		add_meta_box(
			'mf_form_usage_box',
			__( 'Form settings', 'wp-macs-forms' ),
			[ $this, 'render_meta_box_infobox' ],
			[ 'mf_form' ],
			'normal',
			'high'
		);

		add_meta_box(
			'mf_form_builder',
			__( 'Form Builder', 'wp-macs-forms' ),
			[ $this, 'render_meta_box_builder' ],
			[ 'mf_form' ],
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
		<div class="mf_builder mf-box">
			<div class="mf-row">
				<div class="mf_builder__canvas mf-cell-8">
					<ul class="mf_list mf_list--canvas sortable">
						<?php $form->render_admin_form_fields(); ?>
					</ul>
				</div>
				<div class="mf_builder__picker mf-cell-4">
					<h3 class="mf-subtitle"><?php esc_html_e( 'Available Fields', 'wp-macs-forms' ); ?></h3>

					<ul class="mf_list mf_list--picker">
						<?php $this->render_available_fields_picker(); ?>
					</ul>
				</div>

				<?php wp_nonce_field( 'mf_save_data', '_mf_nonce' ); ?>
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

	/**
	 * TODO: Form infobox meta box output
	 *
	 * @param \WP_Post $post post object
	 */
	public function render_meta_box_infobox( $post ) {
		$has_pardot        = get_post_meta( $post->ID, 'mf_form_has_pardot', true ) ?: '';
		$pardot_handler    = get_post_meta( $post->ID, 'mf_form_pardot_handler', true ) ?: '';
		$has_notification  = get_post_meta( $post->ID, 'mf_form_admin_notify', true ) ?: '';
		$notification_mail = get_post_meta( $post->ID, 'mf_form_admin_custom_mail', true ) ?: get_option( 'admin_email' );
		$custom_ty_msg     = get_post_meta( $post->ID, 'mf_form_ty_msg', true ) ?: '';
		$shortcode         = sprintf( '[macs_form id="%d"]', absint( $post->ID ) );
		?>
		<div class="mf_infobox mf-box">

			<div class="mf-row">
				<div class="mf-cell-12">
					<h3 <h3 class="mf-subtitle"><?php esc_html_e( 'Form shortcode', 'wp-macs-forms' ); ?></h3>
				</div>
				<div class="mf-cell-4 mf_setting">
					<input type="text" value="<?php echo esc_attr( $shortcode ); ?>" readonly />
				</div>
			</div>

			<div class="mf-row">
				<div class="mf-cell-12">
					<h3 <h3 class="mf-subtitle"><?php esc_html_e( 'Email notification', 'wp-macs-forms' ); ?></h3>
				</div>
				<div class="mf-cell-12 mf_setting">
					<input id="mf_form_admin_notify" class="" name="mf_form_admin_notify" type="checkbox" value="1" <?php checked( true, $has_notification ); ?>>
					<label for="mf_form_admin_notify"><?php echo esc_html( 'Send admin notification after submission is sent', 'wp-macs-forms' ); ?></label>
				</div>

				<div class="mf-cell-8 mf_setting">
					<label for="mf_form_admin_custom_mail"><?php echo esc_html( 'Custom admin e-mail for notifications', 'wp-macs-forms' ); ?></label><br />
					<input id="mf_form_admin_custom_mail" class="" name="mf_form_admin_custom_mail" type="email" value="<?php echo esc_attr( $notification_mail ); ?>" />
				</div>
			</div>

			<div class="mf-row">
				<div class="mf-cell-12">
					<h3 <h3 class="mf-subtitle"><?php esc_html_e( 'Pardot integration', 'wp-macs-forms' ); ?></h3>
				</div>
				<div class="mf-cell-12 mf_setting">
					<input id="mf_form_has_pardot" class="" name="mf_form_has_pardot" type="checkbox" value="1" <?php checked( true, $has_pardot ); ?>>
					<label for="mf_form_has_pardot"><?php esc_html_e( 'Connect this form to Pardot handler', 'wp-macs-forms' ); ?></label>
				</div>

				<div class="mf-cell-8 mf_setting">
					<label for="mf_form_pardot_handler"><?php esc_html_e( 'Pardot handler URL', 'wp-macs-forms' ); ?></label><br />
					<input id="mf_form_pardot_handler" class="mf_pardot_rel" name="mf_form_pardot_handler" type="text" value="<?php echo esc_attr( $pardot_handler ); ?>" />
				</div>
			</div>

			<div class="mf-row">
				<div class="mf-cell-12">
					<h3 <h3 class="mf-subtitle"><?php esc_html_e( 'Trigger WP Action', 'wp-macs-forms' ); ?></h3>
				</div>
				<div class="mf-cell-12 mf_setting">
					<input id="mf_form_trigger_action" class="" name="mf_form_trigger_action" type="checkbox" value="1">
					<label for="mf_form_trigger_action"><?php echo esc_html( 'Trigger custom WP action', 'wp-macs-forms' ); ?></label>
				</div>

				<div class="mf-cell-8 mf_setting">
					<label for="mf_form_action_handler"><?php echo esc_html( 'WP action handler', 'wp-macs-forms' ); ?></label><br />
					<input id="mf_form_action_handler" class="" name="mf_form_action_handler" type="text" value="<?php echo esc_attr( '' ); ?>" />
				</div>
			</div>

			<div class="mf-row">
				<div class="mf-cell-12">
					<h3 <h3 class="mf-subtitle"><?php esc_html_e( 'Thank you message', 'wp-macs-forms' ); ?></h3>
				</div>

				<div class="mf-cell-8 mf_setting">
					<label for="mf_form_ty_msg"><?php echo esc_html( 'Custom "Thank You" message', 'wp-macs-forms' ); ?></label><br />
					<textarea id="mf_form_ty_msg" style="width:66%;" rows="3" name="mf_form_ty_msg" type="text"><?php echo esc_html( $custom_ty_msg ); ?></textarea>
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
			'mf_file_data',
			__( 'File data', 'wp-macs-forms' ),
			[ $this, 'render_meta_box_file_data' ],
			[ 'mf_file' ],
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
		<div class="mf_file mf-box">
			<div class="mf-row">
				<div class="mf-cell-12">
					<p>
					<?php
					echo esc_html__( 'Submitted on: ', 'wp-macs-forms' );
					echo esc_html( $file_post->post_date );
					?>
					</p>

					<?php if ( ! empty( $file->sub_id ) ) : ?>
					<p><?php esc_html_e( 'Attached to submission:', 'wp-macs-forms' ); ?> <a href="<?php echo esc_url( get_permalink( $file->sub_id ) ); ?>"><?php echo esc_html( get_the_title( $file->sub_id ) ); ?></a></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="mf-row">
				<div class="mf-cell-12">
					<div class="mf-row">
						<div class="mf-cell-4">
							<strong><?php echo esc_html( 'File title', 'wp-macs-forms' ); ?></strong>
						</div>
						<div class="mf-cell-8">
							<span><?php echo esc_html( $file->title ); ?></span>
						</div>
					</div>
					<div class="mf-row">
						<div class="mf-cell-4">
							<strong><?php echo esc_html( 'File type', 'wp-macs-forms' ); ?></strong>
						</div>
						<div class="mf-cell-8">
							<span><?php echo esc_html( $file->filetype ); ?></span>
						</div>
					</div>
					<div class="mf-row">
						<div class="mf-cell-4">
							<strong><?php echo esc_html( 'File URL', 'wp-macs-forms' ); ?></strong>
						</div>
						<div class="mf-cell-8">
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
			'mf_sub_data',
			__( 'Submission data', 'wp-macs-forms' ),
			[ $this, 'render_meta_box_sub_data' ],
			[ 'mf_sub' ],
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
		<div class="mf_submission mf-box">
			<div class="mf-row">
				<div class="mf-cell-12">
					<p>
					<?php
					echo esc_html__( 'Submitted on: ', 'macs_forms' );
					echo esc_html( $submission_post->post_date );
					?>
					</p>

					<p>Form: <a href="<?php echo esc_url( get_edit_post_link( $sub->form_id ) ); ?>"><?php echo esc_html( get_the_title( $sub->form_id ) ); ?></a></p>
				</div>
			</div>
			<div class="mf-row">
				<div class="mf-cell-12">
					<?php
					foreach ( (array) $sub->fields as $field ) :
						if ( 'submit' === $field->type ) {
							continue;
						}
						?>
						<div class="mf-row">
							<div class="mf-cell-4">
								<strong><?php echo esc_html( $field->label ); ?>:</strong>
							</div>
							<div class="mf-cell-4">
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

		$columns['shortcode'] = __( 'Shortcode', 'macs_forms' );
		$columns['date']      = __( 'Date', 'macs_forms' );

		return $columns;
	}

	/**
	 * Populate shortcode column with form shortcode
	 * @param $column
	 * @param $post_id
	 */
	public function forms_shortcode_column_content( $column, $post_id ) {
		if ( 'shortcode' === $column ) {
			$shortcode = sprintf( '[macs_form id="%d"]', absint( $post_id ) );
			echo sprintf( '<pre>%s</pre>', esc_html( $shortcode ) );

		}
	}

	/**
	 * Add form column to submissions
	 */
	public function subs_posts_columns( $columns ) {
		$columns['form'] = __( 'Form', 'macs_forms' );

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
		$is_subs_edit_page = 'edit.php' === $pagenow && 'mf_sub' === $post_type;

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
		$is_subs_edit_page = 'edit.php' === $pagenow && 'mf_sub' === $post_type;

		if ( ! is_admin() || ! $is_subs_edit_page || empty( $form_filter_val ) ) {
			return;
		}

		$query->set( 'meta_key', 'form_id' );
		$query->set( 'meta_value', $form_filter_val );
	}

	public function subs_forms_dropdown_filter() {
		$filter_query = new \WP_Query(
			[
				'post_type'           => 'mf_form',
				'posts_per_page'      => 100,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'post_status'         => 'publish',
				'sub_query'           => false,
			]
		);

		$options  = [];
		$selected = filter_input( INPUT_GET, 'filter-form', FILTER_VALIDATE_INT ) ?? '';

		while ( $filter_query->have_posts() ) :
			$filter_query->the_post();

			$options[] = sprintf( '<option value="%1$d" %2$s>%3$s</option>', get_the_ID(), selected( get_the_ID(), $selected, false ), get_the_title() );
		endwhile;

		wp_reset_query();

		printf(
			'<select name="filter-form"><option value="">%1$s</option>%2$s</select>',
			esc_html__( 'Any form', 'macs_forms' ),
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

	/**
	 * Remove useless metaboxes
	 */
	public function metaboxes_cleanup() {
		$screens = [ 'mf_form', 'mf_file', 'mf_sub' ];

		remove_meta_box( 'postexcerpt', $screens, 'normal' );
		remove_meta_box( 'trackbacksdiv', $screens, 'normal' );
		remove_meta_box( 'commentstatusdiv', $screens, 'normal' );
		remove_meta_box( 'commentsdiv', $screens, 'normal' );
	}

	public function add_mf_admin_menu() {
		add_submenu_page(
			'options-general.php',
			'MACS Forms',
			'MACS Forms',
			'manage_options',
			'macs_forms',
			[ $this, 'mf_options_page' ]
		);
	}

	public function mf_settings_init() {

		register_setting( 'mf_settings_page', 'mf_settings' );

		$default_forms = apply_filters( 'mf_default_forms', [] );

		// SECTIONS

		add_settings_section(
			'macs_forms_settings_general',
			__( 'General Settings', 'macs_forms' ),
			null,
			'mf_settings_page'
		);

		add_settings_section(
			'macs_forms_settings_captcha',
			__( 'CAPTCHA settings', 'macs_forms' ),
			null,
			'mf_settings_page'
		);

		add_settings_section(
			'macs_forms_settings_nf',
			__( 'Ninja Forms compatibility', 'macs_forms' ),
			null,
			'mf_settings_page'
		);

		if ( ! empty( $default_forms ) ) {
			add_settings_section(
				'macs_forms_settings_default',
				__( 'Default Forms', 'macs_forms' ),
				null,
				'mf_settings_page'
			);
		}

		// FIELDS

		// 1, Captcha
		add_settings_field(
			'cipher_key',
			__( 'CIPHER key', 'macs_forms' ),
			[ $this, 'mf_cipher_key_render' ],
			'mf_settings_page',
			'macs_forms_settings_general'
		);

		// 2. Captcha
		add_settings_field(
			'captcha_key',
			__( 'Google reCAPTCHA site key', 'macs_forms' ),
			[ $this, 'mf_captcha_key_render' ],
			'mf_settings_page',
			'macs_forms_settings_captcha'
		);

		add_settings_field(
			'captcha_secret',
			__( 'Google reCAPTCHA secret', 'macs_forms' ),
			[ $this, 'mf_captcha_secret_render' ],
			'mf_settings_page',
			'macs_forms_settings_captcha'
		);

		// 3. Ninja Forms related
		add_settings_field(
			'mf_nf_shortcode',
			__( 'Shortcodes', 'macs_forms' ),
			[ $this, 'mf_nf_shortcode_render' ],
			'mf_settings_page',
			'macs_forms_settings_nf'
		);

		// 4. Default Forms

		foreach ( $default_forms as $slug => $name ) {

			$mf_options = get_option( 'mf_settings' );
			$value      = ! empty( $mf_options[ "mf_default_{$slug}" ] ) ? $mf_options[ "mf_default_{$slug}" ] : '';
			$all_forms  = $this->get_all_published_forms();

			add_settings_field(
				"mf_default_{$slug}",
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
						'<select name="mf_settings[%s]"><option value="">%s</option>%s</select>',
						"mf_default_{$slug}", // phpcs:ignore 
						esc_html__( 'None', 'wp-macs-forms' ),
						implode( "\n", $options_array ) // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped early
					);
				},
				'mf_settings_page',
				'macs_forms_settings_default'
			);
		}
	}
	
	public function mf_cipher_key_render() {
		$options = get_option( 'mf_settings' );
		$value   = ! empty( $options['cipher_key'] ) ? $options['cipher_key'] : '';
		?>
		<input type="text" name="mf_settings[cipher_key]" value="<?php echo esc_attr( $value ); ?>">
		<?php
	}


	public function mf_captcha_secret_render() {
		$options = get_option( 'mf_settings' );
		$value   = ! empty( $options['captcha_secret'] ) ? $options['captcha_secret'] : '';
		?>
		<input type="text" name="mf_settings[captcha_secret]" value="<?php echo esc_attr( $value ); ?>">
		<?php
	}

	public function mf_captcha_key_render() {
		$options = get_option( 'mf_settings' );
		$value   = ! empty( $options['captcha_key'] ) ? $options['captcha_key'] : '';
		?>
		<input type="text" name="mf_settings[captcha_key]" value="<?php echo esc_attr( $value ); ?>">
		<?php
	}

	public function mf_nf_shortcode_render() {
		$options = get_option( 'mf_settings' );
		$value   = ! empty( $options['nf_shortcode'] ) ? $options['nf_shortcode'] : '';
		?>
		<label for="mf_nf_shortcode">
		<input name="mf_settings[nf_shortcode]" type="checkbox" id="mf_nf_shortcode" value="1" <?php checked( 1, $value ); ?>>
		Support Ninja Forms shortcodes</label>
		<?php
	}

	public function mf_options_page() {
		?>
		<form action='options.php' method='post'>
			<h2>MACS Forms</h2>
			<?php
			settings_fields( 'mf_settings_page' );
			do_settings_sections( 'mf_settings_page' );
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
	protected function get_all_published_forms() {

		$forms_array = wp_cache_get( 'all_forms_array', 'macs_forms' );

		if ( false === $forms_array ) {

			$forms_query = new \WP_Query(
				[
					'post_type'           => 'mf_form',
					'posts_per_page'      => 500, //phpcs:ignore
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'post_status'         => 'publish',
					'sub_query'           => false,
				]
			);

			$forms_array = [];

			while ( $forms_query->have_posts() ) :
				$forms_query->the_post();
				$forms_array[ get_the_ID() ] = get_the_title();
			endwhile;

			wp_reset_query();

			wp_cache_set( 'all_forms_array', $forms_array, 'macs_forms', MONTH_IN_SECONDS );
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

		if ( 'mf_form' !== $data['post_type'] ) {
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
	public function mf_admin_notices() {

		if ( 1 !== filter_input( INPUT_GET, 'non_unique_title', FILTER_VALIDATE_INT ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'A Form with the same title already exists on this site. This Form has been saved as DRAFT. You have to change its title to be able to publish it.', 'wp-macs-forms' ); ?></strong></p>
		</div>
		<?php
	}

	/**
	 * Renders preview btn on non-published form posts
	 *
	 * @param WP_Post $post
	 */
	public function add_form_preview_btn( $post ) {

		if ( 'mf_form' !== $post->post_type ) {
			return;
		}

		$preview_url = add_query_arg( 'mf_form_preview', absint( $post->ID ), home_url() );
		?>
		<div class="misc-pub-section mf_preview_btn" style="float:left;">
			<a class="preview button" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" id="mf_post-preview"><?php echo esc_html__( 'Preview Form', 'wp-macs-forms' ); ?><span class="screen-reader-text"> <?php echo esc_html__( '(opens in a new window)', 'wp-macs-forms' ); ?> </span></a>
		</div>
		<?php
	}
}
