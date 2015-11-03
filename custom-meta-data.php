<?php
/*
Plugin Name: Custom Meta Data WP-API Plugin
Description: This is a custom GR plugin that will add custom fields to the meta data in posts
Version: 1.0
Author: Matt Polky
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/ 
function cptui_register_my_cpts() {
	$labels = array(
		"name" => "GRPosts",
		"singular_name" => "GRPost",
		"menu_name" => "My GR Posts",
		"all_items" => "All GR Posts",
		"add_new" => "Add New",
		"add_new_item" => "Add New GR Post",
		"edit" => "Edit",
		"edit_item" => "Edit GR Post",
		"new_item" => "New GR Post",
		"view" => "View",
		"view_item" => "View GR Post",
		"search_items" => "Search GR Posts",
		"not_found" => "No GR Posts found",
		"not_found_in_trash" => "No GR Posts found in Trash",
		"parent" => "Parent Post",
		);

	$args = array(
		"labels" => $labels,
		"description" => "Custom GRPost Type",
		"public" => true,
		"show_ui" => true,
		"has_archive" => true,
		"show_in_menu" => true,
		"exclude_from_search" => false,
		"capability_type" => "post",
		"map_meta_cap" => true,
		"hierarchical" => false,
		"rewrite" => array( "slug" => "grpost", "with_front" => true ),
		"query_var" => true,
		"menu_icon" => 'dashicons-admin-site',
		"taxonomies" => array( "category", "post_tag" )
	);
	register_post_type( "grpost", $args );

	// End of cptui_register_my_cpts()
}
add_action('init', 'cptui_register_my_cpts');

function customMetaData_plugin_init()
{
	global $customMetaData_plugin;
	$customMetaData_plugin = new CustomMetaData_plugin();
	// Add custom data filters.
	add_filter('json_prepare_post', array( $customMetaData_plugin, 'add_custom_fields_meta' ), 10, 3);
	add_filter('json_query_vars', array( $customMetaData_plugin, 'my_allow_meta_query' ), 10, 3);
	add_filter('json_prepare_page', array( $customMetaData_plugin, 'add_custom_fields_meta' ), 10, 3);
	add_filter('json_prepare_attachment', array( $customMetaData_plugin, 'add_custom_fields_meta' ), 10, 3);
	add_filter('json_prepare_term', array( $customMetaData_plugin, 'add_custom_taxonomies_meta' ), 10, 2);
	add_filter('json_prepare_user', array( $customMetaData_plugin, 'add_custom_users_meta' ), 10, 2);
}
add_action('wp_json_server_before_serve', 'customMetaData_plugin_init');
class CustomMetaData_plugin {
	/**
	 * Add custom fields to meta data on a post.
	 *
	 * @param array $data {
	 *     @type string|null $key Meta key
	 *     @type string|null $key Meta value
	 * }
	 * @param array $post Post data
	 * @param string $context Context for the prepared post.
	 * @return WP_Error|array Filtered data
	*/
	public function add_custom_fields_meta($data, $post, $context) {
        $customFields = (array) get_fields($post['ID']);
	    $data['meta'] = array_merge($data['meta'], $customFields);
	    return $data;
	}
	/**
	 * Add custom taxonomies to meta data on a post.
	 *
	 * @param array $data {
	 *     @type string|null $key Meta key
	 *     @type string|null $key Meta value
	 * }
	 * @param array $post Post data
	 * @return WP_Error|array Filtered data
	*/
	public function add_custom_taxonomies_meta($data, $post) {
	    $customTaxonomies = (array) get_fields($post->taxonomy."_". $post->term_id);
	    $data['meta'] = array_merge($data['meta'], $customTaxonomies);
	    return $data;
	}
	
	/**
	 * Add custom users to meta data on a post.
	 *
	 * @param array $data {
	 *     @type string|null $key Meta key
	 *     @type string|null $key Meta value
	 * }
	 * @param array $post Post data
	 * @return WP_Error|array Filtered data
	*/
	public function add_custom_users_meta($data, $post) {
		$customUsers = (array) get_fields("user_". $response['ID']);
	    $data['meta'] = array_merge($data['meta'], $customUsers);
	    return $data;
	}

	public function my_allow_meta_query( $valid_vars ) {
		$valid_vars = array_merge( $valid_vars, array( 'meta_key', 'meta_value' ) );
		return $valid_vars;
	}
}
?>
