<?php
/**
 * Class for registering plugin post types
 */

namespace Proper_Forms;

class Register_Post_Types {

	/**
	 * Register pf_form post type
	 */
	public function register_pf_form() {

		$forms_args = [
			'labels'             => [
				'name'          => __( 'Forms' ),
				'singular_name' => _x( 'Form', 'post type singular name', 'proper-forms' ),
				'add_new'       => __( 'Add New', 'proper-forms' ),
				'add_new_item'  => __( 'Add New Form', 'proper-forms' ),
				'edit_item'     => __( 'Edit Form', 'proper-forms' ),
				'new_item'      => __( 'New Form', 'proper-forms' ),
				'view_item'     => __( 'View Form', 'proper-forms' ),
				'search_items'  => __( 'Search Forms', 'proper-forms' ),
			],
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'has_archive'        => true,
			'show_in_menu'       => true,
			'show_in_rest'       => false,
			'hierarchical'       => false,
			'menu_icon'          => 'dashicons-list-view',
			'rewrite'            => [ 'slug' => 'pf_form' ],
			'supports'           => [ 'title' ],
			'capability_type'    => 'pf_form',
			'map_meta_cap'       => true,
			'capabilities'       => [
				'publish_posts'       => 'publish_pf_forms',
				'edit_posts'          => 'edit_pf_forms',
				'edit_others_posts'   => 'edit_others_pf_forms',
				'delete_posts'        => 'delete_pf_forms',
				'delete_others_posts' => 'delete_others_pf_forms',
				'read_private_posts'  => 'read_private_pf_forms',
				'edit_post'           => 'edit_pf_form',
				'delete_post'         => 'delete_pf_form',
				'read_post'           => 'read_pf_form',
			],
		];

		register_post_type( 'pf_form', $forms_args );
	}

	/**
	 * Register pf_sub post type
	 */
	public function register_pf_sub() {

		$subs_args = [
			'labels'             => [
				'name'          => __( 'Submissions' ),
				'singular_name' => _x( 'Submission', 'post type singular name', 'proper-forms' ),
			],
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'query_var'          => true,
			'has_archive'        => false,
			'show_in_menu'       => 'edit.php?post_type=pf_form',
			'show_in_rest'       => false,
			'hierarchical'       => false,
			'rewrite'            => [ 'slug' => 'pf_sub' ],
			'supports'           => [ 'custom-fields' ],
			'capability_type'    => 'pf_sub',
			'capabilities'       => [
				'publish_posts'       => 'publish_pf_subs',
				'edit_posts'          => 'edit_pf_subs',
				'edit_others_posts'   => 'edit_others_pf_subs',
				'delete_posts'        => 'delete_pf_subs',
				'delete_others_posts' => 'delete_others_pf_subs',
				'read_private_posts'  => 'read_private_pf_subs',
				'edit_post'           => 'edit_pf_sub',
				'delete_post'         => 'delete_pf_sub',
				'read_post'           => 'read_pf_sub',
			],
		];

		register_post_type( 'pf_sub', $subs_args );
	}

	public function register_pf_file() {
		$file_args = [
			'labels'             => [
				'name'          => __( 'Submitted Files' ),
				'singular_name' => _x( 'File', 'post type singular name', 'proper-forms' ),
			],
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'query_var'          => true,
			'has_archive'        => false,
			'show_in_menu'       => 'edit.php?post_type=pf_form',
			'show_in_rest'       => false,
			'hierarchical'       => false,
			'rewrite'            => [ 'slug' => 'pf_file' ],
			'supports'           => [ 'custom-fields' ],
			'capability_type'    => 'pf_file',
			'capabilities'       => [
				'publish_posts'       => 'publish_pf_file',
				'edit_posts'          => 'edit_pf_file',
				'edit_others_posts'   => 'edit_others_pf_file',
				'delete_posts'        => 'delete_pf_file',
				'delete_others_posts' => 'delete_others_pf_file',
				'read_private_posts'  => 'read_private_pf_file',
				'edit_post'           => 'edit_pf_file',
				'delete_post'         => 'delete_pf_file',
				'read_post'           => 'read_pf_file',
			],
		];

		register_post_type( 'pf_file', $file_args );
	}
}
