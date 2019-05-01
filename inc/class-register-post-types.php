<?php
/**
 * Class for registering plugin post types
 */

namespace MACS_Forms;

class Register_Post_Types {

	/**
	 * Register mf_form post type
	 */
	public function register_mf_form() {

		$forms_args = [
			'labels'             => [
				'name'          => __( 'Forms' ),
				'singular_name' => _x( 'Form', 'post type singular name', 'wp-macs-forms' ),
				'add_new'       => __( 'Add New', 'wp-macs-forms' ),
				'add_new_item'  => __( 'Add New Form', 'wp-macs-forms' ),
				'edit_item'     => __( 'Edit Form', 'wp-macs-forms' ),
				'new_item'      => __( 'New Form', 'wp-macs-forms' ),
				'view_item'     => __( 'View Form', 'wp-macs-forms' ),
				'search_items'  => __( 'Search Forms', 'wp-macs-forms' ),
			],
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'has_archive'        => true,
			'show_in_menu'       => true,
			'show_in_rest'       => false,
			'hierarchical'       => false,
			'menu_icon'          => 'dashicons-list-view',
			'rewrite'            => [ 'slug' => 'mf_form' ],
			'supports'           => [ 'title' ],
			'capability_type'    => 'mf_form',
			'capabilities'       => [
				'publish_posts'       => 'publish_mf_forms',
				'edit_posts'          => 'edit_mf_forms',
				'edit_others_posts'   => 'edit_others_mf_forms',
				'delete_posts'        => 'delete_mf_forms',
				'delete_others_posts' => 'delete_others_mf_forms',
				'read_private_posts'  => 'read_private_mf_forms',
				'edit_post'           => 'edit_mf_form',
				'delete_post'         => 'delete_mf_form',
				'read_post'           => 'read_mf_form',
			],
		];

		register_post_type( 'mf_form', $forms_args );
	}

	/**
	 * Register mf_sub post type
	 */
	public function register_mf_sub() {

		$subs_args = [
			'labels'             => [
				'name'          => __( 'Submissions' ),
				'singular_name' => _x( 'Submission', 'post type singular name', 'wp-macs-forms' ),
			],
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'query_var'          => true,
			'has_archive'        => false,
			'show_in_menu'       => 'edit.php?post_type=mf_form',
			'show_in_rest'       => false,
			'hierarchical'       => false,
			'rewrite'            => [ 'slug' => 'mf_sub' ],
			'supports'           => [ 'custom-fields' ],
			'capability_type'    => 'mf_sub',
			'capabilities'       => [
				'publish_posts'       => 'publish_mf_subs',
				'edit_posts'          => 'edit_mf_subs',
				'edit_others_posts'   => 'edit_others_mf_subs',
				'delete_posts'        => 'delete_mf_subs',
				'delete_others_posts' => 'delete_others_mf_subs',
				'read_private_posts'  => 'read_private_mf_subs',
				'edit_post'           => 'edit_mf_sub',
				'delete_post'         => 'delete_mf_sub',
				'read_post'           => 'read_mf_sub',
			],
		];

		register_post_type( 'mf_sub', $subs_args );
	}

	public function register_mf_file() {
		$file_args = [
			'labels'             => [
				'name'          => __( 'Submitted Files' ),
				'singular_name' => _x( 'File', 'post type singular name', 'wp-macs-forms' ),
			],
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'query_var'          => true,
			'has_archive'        => false,
			'show_in_menu'       => 'edit.php?post_type=mf_form',
			'show_in_rest'       => false,
			'hierarchical'       => false,
			'rewrite'            => [ 'slug' => 'mf_file' ],
			'supports'           => [ 'custom-fields' ],
			'capability_type'    => 'mf_file',
			'capabilities'       => [
				'publish_posts'       => 'publish_mf_file',
				'edit_posts'          => 'edit_mf_file',
				'edit_others_posts'   => 'edit_others_mf_file',
				'delete_posts'        => 'delete_mf_file',
				'delete_others_posts' => 'delete_others_mf_file',
				'read_private_posts'  => 'read_private_mf_file',
				'edit_post'           => 'edit_mf_file',
				'delete_post'         => 'delete_mf_file',
				'read_post'           => 'read_mf_file',
			],
		];

		register_post_type( 'mf_file', $file_args );
	}
}
