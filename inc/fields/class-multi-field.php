<?php
/**
 * Abstract class for Form Builder fields that support multiple options
 */

namespace Proper_Forms\Fields;

abstract class Multi_Field extends Field {

	/**
	 * Options string
	 *
	 * @var string
	 */
	public $options = '';

		/**
	 * Options string
	 *
	 * @var string
	 */
	public $default_option = '';

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
				'col'     => 12,
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

			case 'key_value_pairs':
				$options = $this->options_to_array( $args['value'] );
				ob_start();
				?>
				<label for="<?php echo esc_attr( $args['name'] ); ?>">
					<?php echo esc_html__( $args['label'] ); ?>
				</label>

				<table class="pf_key_value_table">
					<thead>
						<th><?php esc_html_e( 'Value', 'proper-forms' ); ?></th>
						<th><?php esc_html_e( 'Label', 'proper-forms' ); ?></th>
						<th></th>
					</thead>
					<tbody>
						<?php if ( ! count( $options ) ) : ?>
						<tr>
							<td><input type="text"  /></td>
							<td><input type="text" /></td>
							<td><button class="pf_delete_row" aria-label="<?php echo esc_attr__( 'Delete this row', 'proper-forms' ); ?>"><span class="dashicons dashicons-no"></span></button></td>
						</tr>
							<?php
							else :
								foreach ( $options as $value => $label ) :
									?>
									<tr>
										<td><input type="text" value="<?php echo esc_attr( $value ); ?>" /></td>
										<td><input type="text" value="<?php echo esc_attr( $label ); ?>" /></td>
										<td><button class="pf_delete_row" aria-label="<?php echo esc_attr__( 'Delete this row', 'proper-forms' ); ?>"><span class="dashicons dashicons-no"></span></button></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
					</tbody>
				</table>

				<button class="pf_table_add_row" aria-label="<?php echo esc_attr__( 'Delete this row', 'proper-forms' ); ?>"><span class="dashicons dashicons-plus"></span> <?php echo esc_html( 'Add option', 'proper-forms' ); ?></button>

				<input name="<?php echo esc_attr( $args['name'] ); ?>" type="hidden" value="<?php echo esc_attr( $args['value'] ); ?>" />

				<?php
				$input = ob_get_clean();
				break;

				case 'checkbox':
					$input = sprintf(
						'<label for="%1$s">%2$s</label><br /><input id="%1$s" class="%3$s" name="%1$s" type="checkbox" value="1" %4$s />',
						esc_attr( $args['name'] ),
						esc_html( $args['label'] ),
						esc_attr( $args['class'] ),
						checked( 1, $args['value'], false )
					);
				break;

				case 'textarea':
					$input = sprintf(
						'<label for="%1$s">%2$s</label><br /><textarea id="%1$s" rows="3" name="%1$s" type="text">%3$s</textarea>',
						esc_attr( $args['name'] ),
						esc_html( $args['label'] ),
						esc_html( str_replace( "rn", "\n", $args['value'] ) )
					);

				break;

		}
		?>
		<div class="pf-cell-<?php echo esc_attr( $args['col'] ); ?> pf_setting"><?php echo $input; // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped early ?></div>
		<?php
	}

	/**
	 * Converts string with saved options (delimited with ~ and | ) into an array
	 *
	 * @param string $options_string
	 * @return array
	 */
	protected function options_to_array( $options_string ) {
		if ( empty( $options_string ) ) {
			return [];
		}

		$options_array = [];
		$options       = explode( '~', $options_string );

		foreach ( $options as $option ) {

			$vals = explode( '|', $option );

			if ( 2 !== count( $vals ) ) {
				continue;
			}

			if ( empty( $vals[1] ) || empty( $vals[0] ) ) {
				continue;
			}

			$options_array[ $vals[1] ] = $vals[0];
		}

		return $options_array;
	}
}
