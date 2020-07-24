<?php
/**
 * Abstract class for Form Builder fields
 */

namespace Proper_Forms\Fields;

abstract class Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = '';

	/**
	 * Human readable name of the field type
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * field icon from dashicon set
	 *
	 * @var string
	 */
	public $icon = '';

	/**
	 * field ID
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Datasource ID (pf_form post type ID)
	 *
	 * @var int
	 */
	public $datasource_id = 0;

	/**
	 * Is field required?
	 *
	 * @var string
	 */
	public $is_required = 0;

	/**
	 * Field Label
	 *
	 * @var string
	 */
	public $label = '';

	/**
	 * Custom error message
	 *
	 * @var string
	 */
	public $error_msg = '';

	/**
	 * Pardot handler
	 *
	 * @var string
	 */
	public $pardot_handler = '';

	/**
	 * Default option
	 *
	 * @var mixed
	 */
	public $default_option = '';

	/**
	 * Saved_value
	 *
	 * @var string
	 */
	public $saved_value = '';

	/**
	 * Data format
	 *
	 * @var string
	 */
	public $format = '';

	/**
	 * Field constructor.
	 */
	public function __construct( $args = [] ) {

		$this->populate_field( $args );
	}

	/**
	 * Populate object with data on initialisation
	 */
	public function populate_field( $args ) {
		if ( ! is_array( $args ) || empty( $args ) ) {
			return;
		}

		foreach ( $args as $key => $val ) {
			if ( property_exists( __CLASS__, $key ) ) {
				$this->{$key} = $val;
			}
		}
	}

	/**
	 * Function to use for validation of user input on this field type
	 */
	abstract public function validate_input( $input );

	/**
	 * Render the field on front-end
	 */
	abstract public function render_field();

	/**
	 * Render the field's settings panel in Form Builder
	 */
	abstract public function render_field_settings();

	/**
	 * Render the field's placeholder item for Form Builder menu
	 */
	public function render_picker_item() {
		?>
			<li class="pf_list__item pf_picker_<?php echo esc_attr( $this->type ); ?> draggable" data-type="<?php echo esc_attr( $this->type ); ?>">
				<div class="pf_list__inner">
						<div class="pf__options">

							<?php do_action( 'pf_before_config_buttons', [ $this->type, $this->id ] ); ?>

							<button class="pf_btn pf_btn--config" aria-label="<?php esc_attr_e( 'Show field settings window', 'proper-forms' ); ?>">
								<span class="dashicons dashicons-edit" aria-hidden="true"></span>
							</button>
							<button class="pf_btn pf_btn--remove" aria-label="<?php esc_attr_e( 'Remove field from form', 'proper-forms' ); ?>">
								<span class="dashicons dashicons-trash" aria-hidden="true"></span>
							</button>

							<?php do_action( 'pf_after_config_buttons', [ $this->type, $this->id ] ); ?>

						</div>
					<h3>
						<span class="dashicons <?php echo esc_attr( $this->icon ); ?>"></span>
						<span class="pf_list__title"><?php echo esc_html( $this->name ); ?></span>
					</h3>
						<div class="pf_list__config_panel">
							<?php
								$this->render_field_settings( null, null );

								// always render hidden input with Field type
								$this->render_option(
									[
										'type'  => 'hidden',
										'name'  => 'type',
										'value' => $this->type,
									]
								);
							?>
						</div>
				</div>
			</li>
		<?php
	}

	/**
	 * Render the field in dashboard on the used fields list
	 */
	public function render_admin_field_form() {
		?>
		<li class="pf_list__item pf_canvas_<?php echo esc_attr( $this->type ); ?> pf_list__item--landed" data-type="<?php echo esc_attr( $this->type ); ?>">
			<div class="pf_list__inner">
					<div class="pf__options">

						<?php do_action( 'pf_before_config_buttons', [ $this->type, $this->id ] ); ?>

						<button class="pf_btn pf_btn--config" aria-label="<?php esc_attr_e( 'Show field settings window', 'proper-forms' ); ?>">
							<span class="dashicons dashicons-edit" aria-hidden="true"></span>
						</button>
						<button class="pf_btn pf_btn--remove" aria-label="<?php esc_attr_e( 'Remove field from form', 'proper-forms' ); ?>">
							<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						</button>

						<?php do_action( 'pf_after_config_buttons', [ $this->type, $this->id ] ); ?>

					</div>
				<h3>
					<span class="dashicons <?php echo esc_attr( $this->icon ); ?>"></span>
					<span class="pf_list__title"><?php echo esc_html( $this->name ); ?></span>
				</h3>
					<div class="pf_list__config_panel">
						<?php
							$this->render_field_settings();

							// always render hidden input with Field type
							$this->render_option(
								[
									'type'  => 'hidden',
									'name'  => 'type',
									'value' => $this->type,
								]
							);

							$this->render_option(
								[
									'type'  => 'hidden',
									'name'  => 'pf_field_id',
									'value' => $this->id,
								]
							);
						?>
					</div>
			</div>
		</li>
		<?php
	}

	/**
	 * Renders HTML for field's config panel inputs.
	 *
	 * @param array $args array with keys:
	 *        [type],
	 *        [name],
	 *        [label],
	 *        [value],
	 *        [checked],
	 *        [class]
	 *
	 * @return string
	 */
	public function render_option( $args ) {

		$args = array_merge(
			[
				'type'    => '',
				'name'    => '',
				'label'   => '',
				'value'   => '',
				'checked' => '',
				'class'   => '',
				'min'     => 0,
				'max'     => '',
				'col'     => 12,
				'options' => [],
			],
			$args
		);

		if ( empty( $args['type'] ) || empty( $args['name'] ) ) {
			return '';
		}

		$input = '';

		switch ( $args['type'] ) {
			case 'text':
				$input = sprintf(
					'<label for="%1$s">%2$s</label><br /><input id="%1$s" class="%3$s" name="%1$s" type="text" value="%4$s" />',
					esc_attr( $args['name'] ),
					esc_html( $args['label'] ),
					esc_attr( $args['class'] ),
					esc_attr( $args['value'] )
				);
				break;

			case 'hidden':
				$input = sprintf(
					'<input name="%1$s" type="hidden" value="%2$s" />',
					esc_attr( $args['name'] ),
					esc_attr( $args['value'] )
				);
				break;

			case 'number':
				$input = sprintf(
					'<label for="%1$s">%2$s</label><br /><input name="%1$s" type="number" value="%3$s" min="%4$d" max="%5$d" class="%6$s" />',
					esc_attr( $args['name'] ),
					esc_html( $args['label'] ),
					esc_attr( $args['value'] ),
					intval( $args['min'] ),
					intval( $args['max'] ),
					esc_attr( $args['class'] )
				);
				break;

			case 'checkbox':
				$input = sprintf(
					'<label for="%1$s">%2$s</label><br /><input id="%1$s" class="%3$s" name="%1$s" type="checkbox" value="1" %4$s />',
					esc_attr( $args['name'] ),
					esc_html( $args['label'] ),
					esc_html( $args['class'] ),
					esc_html( $args['checked'] )
				);
				break;

			case 'dropdown':
				ob_start();
				?>

				<label for="<?php echo esc_attr( $args['name'] ); ?>">
					<?php echo esc_html( $args['label'] ); ?>
				</label>

				<select name="<?php echo esc_attr( $args['name'] ); ?>">
				<?php
				foreach ( $args['options'] as $value => $label ) {
					echo sprintf( '<option value="%1$s" %3$s>%2$s</option>', esc_attr( $value ), esc_html( $label ), selected( $value, $args['value'], false ) );
				}
				echo '</select>';

				$input = ob_get_clean();

				break;

			case 'textarea':
				$input = sprintf(
					'<label for="%1$s">%2$s</label><br /><textarea id="%1$s" rows="3" name="%1$s" type="text">%3$s</textarea>',
					esc_attr( $args['name'] ),
					esc_html( $args['label'] ),
					wp_kses(
						str_replace( "rn", "\n", $args['value'] ),
						[
							'br'     => [],
							'strong' => [],
							'em'     => [],
							'b'      => [],
							'br'     => [],
							'span' => [
								'class' => [],
							],
							'a'    => [
								'href'    => [],
								'_target' => [],
							],
						]
					)
				);
				break;
			}
		?>
			<div class="pf-cell-<?php echo esc_attr( $args['col'] ); ?> pf_setting"><?php echo $input; // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped early ?></div>
		<?php
	}

	/**
	 * Get field saved values
	 * Returns empty string if field is not set or $this->id is set to 0
	 *
	 * @param string $option option name (object field)
	 *
	 * @return mixed
	 */
	public function get_value( $option ) {
		return 0 === $this->id || empty( $this->{$option} ) ? '' : $this->{$option};
	}

	/**
	 * Return HTML required attribute if needed
	 *
	 * @return string
	 */
	public function get_render_required() {
		$required = $this->is_required ? 'required' : false;
		return $required;
	}

	/**
	 * Return HTML class for required fields if needed
	 *
	 * @return string
	 */
	public function get_render_required_class() {
		$required_class = ! empty( $this->is_required ) ? 'pf-required' : '';
		return $required_class;
	}

	/**
	 * Return HTML class with symbol for required fields if needed
	 *
	 * @return string
	 */
	public function get_render_required_symbol() {

		if ( empty( $this->is_required ) ) {
			return false;
		}

		return '&nbsp;<span class="pf-required--symbol"> * </span>';
	}

}
