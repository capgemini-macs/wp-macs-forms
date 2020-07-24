<?php
/**
 * Class for Appended Form module
 */

namespace Proper_Forms\Forms;

use Proper_Forms;

class Appended_Form {

	private $forms_controller;

	/**
	 * Constructor
	 */
	public function __construct( Forms $forms_controller ) {
		$this->forms_controller = $forms_controller;
	}

	/**
	 * Adds meta boxes on post and page edit screens
	 * @see 'add_meta_boxes' hook
	 */
	public function add_appended_form_metabox() {
		add_meta_box(
			'proper_forms_selector',
			__( 'Append A Form', 'proper-forms' ),
			[ $this, 'render_appended_form_metabox' ],
			apply_filters( 'pf_appended_form_post_types', [ 'post', 'page' ] ),
			'side',
			'low'
		);
	}

	/**
	 * Prints meta box markup
	 */
	public function render_appended_form_metabox() {
		$post_id       = ! empty( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
		$options_array = [];
		$all_forms     = Proper_Forms\Admin::get_instance()->get_all_published_forms();
		$value         = get_post_meta( $post_id, 'proper_forms_form', true );

		// Use nonce for verification
		wp_nonce_field( 'proper_forms_append_form', 'pf_append_form' );

		foreach ( $all_forms as $form_id => $form_title ) {
			$options_array[] = sprintf(
				'<option value="%1$d" %2$s>%3$s (ID: %1$d)</option>',
				intval( $form_id ),
				selected( intval( $form_id ), intval( $value ), false ),
				esc_html( $form_title )
			);
		}

		?>
		<select id="proper_form_select" name="proper_form_select">
			<option value="0">-- <?php esc_html_e( 'None', 'proper-forms' ); ?></option>
			<?php echo implode( "\n", $options_array ); // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped early ?>
		</select>
		<?php
	}

	/**
	 * Saves data
	 * @see 'save_post' hook
	 * @param int
	 */
	public function appended_form_save_postdata( $post_id ) {

		if ( ! isset( $_POST['pf_append_form'] ) ) {
			return $post_id;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check nonce
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['pf_append_form'] ), 'proper_forms_append_form' ) ) {
			return $post_id;
		}

		// Check permissions for the post type
		if ( empty( $_POST['post_type'] ) ) {
			return $post_id;
		}

		$post_type = sanitize_text_field( $_POST['post_type'] );

		if ( ! current_user_can( "edit_{$post_type}", $post_id ) ) {
			return $post_id;
		}

		// Save data
		$post_id = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0;
		$form_id = isset( $_POST['proper_form_select'] ) ? absint( $_POST['proper_form_select'] ) : 0;
		if ( empty( $form_id ) ) {
			delete_post_meta( $post_id, 'proper_forms_form' );
		} else {
			update_post_meta( $post_id, 'proper_forms_form', $form_id );
		}
	}

	/**
	 * Appends form after content if post meta is there
	 * @see 'the_content' hook
	 * @param string $content
	 *
	 * @return string
	 */
	public function append_form_to_content( $content ) {
		global $post;

		if ( ! in_the_loop() ) {
			return $content;
		}

		if ( empty( $post->ID ) ) {
			return $content;
		}

		$form_id = get_post_meta( $post->ID, 'proper_forms_form', true );

		if ( empty( $form_id ) ) {
			return $content;
		}

		$form_markup = $this->forms_controller->form_shortcode_callback(
			[
				'id' => $form_id,
			]
		);

		return $content . $form_markup;
	}
}
