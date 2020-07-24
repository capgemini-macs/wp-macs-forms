<?php
/**
 * Single Downloads Class
 */

namespace Proper_Forms\Submissions;

use Proper_Forms;
use Proper_Forms\Fields\Field_Types as Field_Types;

class Downloads {

	/**
	 * Related form ID (WP_Post ID)
	 *
	 * @var int
	 */
	protected $form_id = 0;

	/**
	 * Data for output
	 *
	 * @var int
	 */
	protected $data = [];

	/**
	 * Constructor
	 */
	public function __construct( $form_id = 0 ) {
		$this->form_id = absint( $form_id );
	}

	/**
	 * Gets submissions for download in reasonable batches
	 * This is expected to be called rarely and only by site admins
	 */
	public function get_submissions( $page_limit = 0, $page = 1 ) {

		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'edit_pf_subs' ) ) {
			return;
		}

		$form = Proper_Forms\Builder::get_instance()->make_form( $this->form_id );

		if ( empty( $form->fields ) ) {
			return;
		}

		$page_number = $page;
		$keep_going  = 1;
		$data        = [];

		while ( $keep_going ) {

			$query = new \WP_Query(
				[
					'post_type'           => 'pf_sub',
					'posts_per_page'      => 100,
					'paged'               => $page_number,
					'post_status'         => 'publish',
					'meta_query'          => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						[
							'key'     => 'form_id',
							'value'   => $this->form_id,
							'compare' => '=',
						],
					],
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'fields'              => 'ids',
				]
			);

			if ( ! $query->have_posts() ) {
				$keep_going = 0;
				continue;
			}

			// Get submission data
			foreach ( $query->posts as $sub_id ) {

				$data[] = $this->get_submission_data_array( $sub_id, $form );
			}

			if ( $page_number === $page_limit ) {
				$keep_going = 0;
				continue;
			}
			$page_number++;
		}

		$this->data = $this->format_data( $data );

		return $this->data;
	}

	/**
	 * Retrieves data directly from submission post meta
	 * @param int $sub_id
	 * @param Proper_Forms\Form $form
	 */
	public function get_submission_data_array( $sub_id, $form ) {

		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'manage_options' ) ) {
			return [];
		}

		if ( empty( $form->fields ) ) {
			return [];
		}

		$submission_data = [
			'Sub_ID'   => $sub_id,
			'sub_date' => get_the_time( 'Y/m/d', $sub_id ),
		];

		foreach ( $form->fields as $field ) {

			// ignore "submit" field type
			if ( 'submit' === $field->type ) {
				continue;
			}

			$label = wp_kses( $field->label, [] );
			$value = get_post_meta( $sub_id, $field->id, true );

			// Overwrite consent fields value (it's true if anything was submitted here)
			if ( 'consent' === $field->type && ! empty( $value ) ) {
				$submission_data[ $label ] = 1;

			} elseif ( 'file_upload' === $field->type && ! empty( $value ) && is_numeric( $value ) ) { // Get file url if it's an upload field

				$file = Proper_Forms\Builder::get_instance()->make_file( $value );

				if ( ! empty( $file->url ) ) {

					$value = esc_url_raw( $file->url );
				}

				$submission_data[ $label ] = $value;

			} else { // For all other fields get value as string
				$submission_data[ $label ] = is_array( $value ) ? implode( ', ', $value ) : $value;
			}
		}

		return $submission_data;
	}

	/**
	 * Formats data as array
	 */
	protected function format_data( $data = [] ) {

		$columns = [ 'Sub_ID' ];
		$rows    = [];

		// Make sure we get all the column names in a separate array (rows may be inconsistent if form was modified after already having some submissions)
		foreach ( $data as $row ) {
			foreach ( $row as $field_label => $field_value ) {
				if ( ! in_array( $field_label, $columns, true ) ) {
					$columns[] = $field_label;
				}
			}

			$rows[] = $row;
		}

		// looping again through data array to add missing fields to rows
		foreach ( $columns as $col ) {
			foreach ( $rows as &$final_row ) {
				$final_row[ $col ] = ! isset( $final_row[ $col ] ) ? '' : $final_row[ $col ];
			}
		}

		return array_merge( [ $columns ], $rows );
	}

	/**
	 * Outputs csv file
	 */
	public function output_csv_file( $filename ) {

		if ( ! current_user_can( 'edit_pf_subs' ) ) {
			return;
		}

		$temp_file = get_temp_dir() . $filename;
		$f         = fopen( $temp_file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		//Add BOM to fix UTF-8 in Excel sheets
		fputs( $f, $bom = ( chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fput

		foreach ( $this->data as $row ) {
			fputcsv( $f, $row, ',', '"' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		}

		rewind( $f );
		fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		header( 'Content-Type: application/csv' );
		header( "Content-Disposition: attachment; filename={$filename}" );
		header( 'Pragma: no-cache' );
		readfile( $temp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		unlink( $temp_file ); // phpcs:ignore: WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		exit;
	}
}
