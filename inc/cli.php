<?php
/**
 * CLI Commands for Proper Forms management
 */

namespace Proper_Forms\CLI;
use Proper_Forms\Forms\Forms;

class Command extends \WPCOM_VIP_CLI_Command {

	/**
	 * Add post meta to all posts that use Proper Forms shortcode
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : test without changing the data
	 * ---
	 * default: 1
	 * ---
	 *
	 * ## EXAMPLES
	 *   wp pf flag-posts-with-pf --dry-run=1
	 *
	 * @alias flag-posts-with-pf
	 */
	public function flag_used_forms( $args, $args_assoc ) {

		$default_args = [
			'dry-run' => 1,
		];

		$args_assoc = wp_parse_args( $args_assoc, $default_args );
		$is_dry_run = $args_assoc['dry-run'];

		\WP_CLI::line( '' );
		\WP_CLI::line( 'Running FLAG POSTS WITH PF command on site: ' . get_home_url() );

		if ( $is_dry_run ) {
			\WP_CLI::line( '' );
			\WP_CLI::line( ':::   DRY RUN is ON    :::' );
			\WP_CLI::line( '::: sit back and relax :::' );
		}

		$pf_controller    = Forms::get_instance();
		$pf_settings      = get_option( 'pf_settings' );
		$uses_ninja_forms = ! empty( $pf_settings['nf_shortcode'] ) ? 1 : 0;

		$this->start_bulk_operation();

		$page      = 1;
		$has_posts = 1;
		$flagged   = 0;
		$cleared   = 0;
		$no_forms  = 0;

		while ( $has_posts ) {

			$query = new \WP_Query(
				[
					'post_status'         => [ 'publish', 'draft', 'private', 'pending', 'future' ],
					'post_type'           => get_post_types( [ 'public' => true ] ),
					'posts_per_page'      => 100,
					'ignore_sticky_posts' => true,
					'paged'               => $page,
				]
			);

			\WP_CLI::line( '' );
			\WP_CLI::line( sprintf( '::: Processing chunk %d-%d :::', ( 100 * ( $page - 1 ) ), ( 100 * $page ) ) );
			\WP_CLI::line( '' );

			if ( ! $query->have_posts() ) {
				$has_posts = 0;
				continue;
			}

			while ( $query->have_posts() ) {
				$query->the_post();

				$post_id  = get_the_ID();
				$form_ids = [];


				\WP_CLI::line( '' );
				\WP_CLI::line( '----------' );
				\WP_CLI::line( "PROCESSING POST ID [{$post_id}]" );

				/**
				 * Grab used Forms IDs
				 * We are looking both in content and post meta
				 */

				// Check post meta pointing to Proper Forms
				$form_meta_keys = apply_filters( 'pf_form_related_meta', [] );
				$non_empty_keys = [];

				if ( is_array( $form_meta_keys ) ) {
					foreach ( $form_meta_keys as $meta_key ) {
						$used_form_id = get_post_meta( $post_id, $meta_key, true );
						if ( ! empty( $used_form_id ) ) {
							$non_empty_keys[] = $meta_key;
							$form_ids[]       = $used_form_id;
						}
					}
				}

				/*
				 * Getting the forms IDs in two steps
				 * 1. from Proper Forms shortcode
				 * 2. from Ninja Forms shortcode if it's supported
				 */

				// Proper Forms
				preg_match_all( '/\[proper_form id=(?:"|\')?(\d+)(?:"|\')?\]/', get_the_content(), $matches, PREG_SET_ORDER, 0 );

				foreach ( $matches as $match ) {
					$form_ids[] = $match[1];
				}

				// Ninja Forms
				if ( $uses_ninja_forms ) {
					preg_match_all( '/\[ninja_form id=(?:"|\')?(\d+)(?:"|\')?\]/', get_the_content(), $nf_matches, PREG_SET_ORDER, 0 );

					foreach( $nf_matches as $nf_match ) {
						$migrated_pf = $pf_controller->get_form_by_nf_id( $nf_match[1] );
						if ( ! empty( $migrated_pf ) ) {
							\WP_CLI::line( "    Info: [{$post_id}] Uses NF shortcode [{$nf_match[1]}] mapped to Proper Form id [{$migrated_pf}]" );
							$form_ids[] = $migrated_pf;
						}
					}
				}

				$form_ids = array_unique( $form_ids );

				/**
				 * Manage deleted forms IDs
				 * If the post was using forms before it should be stored in post meta.
				 * We compare this meta with currently found post IDs and remove reference
				 * on forms that are not there anymore
				 */
				$previous_forms = get_post_meta( $post_id, 'has_pf_form', true );

				if ( ! empty( $previous_forms ) ) {

					$deleted_forms = array_diff( (array) $previous_forms, $form_ids );

					if ( ! empty( $deleted_forms ) ) {
					
						\WP_CLI::line( "[-] Found outdated forms usage data." );
					
						foreach ( $deleted_forms as $deleted_form_id ) {
							$form_usage_meta = get_post_meta( $deleted_form_id, 'used_in', true ) ?: [];
							$new_usage_meta  = array_unique( array_diff( $form_usage_meta, [ $post_id ] ) );
							
							\WP_CLI::line( "    Removing post {$post_id} from form's {$deleted_form_id} usage data." );
							
							if ( ! $is_dry_run ) {
								update_post_meta( $deleted_form_id, 'used_in', $new_usage_meta );
							}
						}

						$cleared++;
					}
				}

				// No shortcodes and empty modules - clear post meta and move on
				if ( empty( $form_ids ) ) {
					\WP_CLI::line( "[o] No forms curently used. Deleting [has_pf_form] flag." );
					if ( ! $is_dry_run ) {
						delete_post_meta( $post_id, 'has_pf_form' );
					}
					$no_forms++;
					continue;
				}

				\WP_CLI::line( sprintf( '[+] Found PF forms: %1$s', implode( ',', $form_ids ) ) );

				if ( ! empty( $non_empty_keys ) ) {
					foreach ( $non_empty_keys as $module_name ) {
						\WP_CLI::line( "    Info: Post uses module [{$module_name}]." );
					}
				}

				// Update Forms meta (add current post ID to the array)
				foreach ( $form_ids as $form_id ) {
					$form_usage_meta = get_post_meta( $form_id, 'used_in', true ) ?: [];
					$new_usage_meta  = array_unique( array_merge( $form_usage_meta, [ $post_id ] ) );

					\WP_CLI::line( "    Updating post meta for form: {$form_id} with post ID: {$post_id}" );
					
					if ( ! $is_dry_run ) {
						update_post_meta( $form_id, 'used_in', $new_usage_meta );
					}
				}

				// Also flag the post using a form
				\WP_CLI::line( "    Updating [has_pf_form] flag for post {$post_id}" );

				if ( ! $is_dry_run ) {
					update_post_meta( $post_id, 'has_pf_form', $form_ids );
				}

				$flagged++;
			}

			$page++;

			$this->stop_the_insanity();
			sleep( 2 );
		}

		\WP_CLI::line( "DONE! Flagged {$flagged} posts. Cleared refrences from {$cleared} forms. Posts not using forms: {$no_forms}" );

		$this->end_bulk_operation();
	}
}