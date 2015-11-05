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

	    //Add post tag information to categories
	    $args = array( 'categories' => $data['ID']);
		$tags = $this->get_category_tags($args);
		$data['post_tags'] = $tags;
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

	private function get_category_tags($args)
	{
		global $wpdb;
		$tags = $wpdb->get_results
		("
			SELECT DISTINCT terms2.term_id as tag_id, terms2.name as tag_name, null as tag_link, t2.count as tag_count
			FROM
				wp_posts as p1
				LEFT JOIN wp_term_relationships as r1 ON p1.ID = r1.object_ID
				LEFT JOIN wp_term_taxonomy as t1 ON r1.term_taxonomy_id = t1.term_taxonomy_id
				LEFT JOIN wp_terms as terms1 ON t1.term_id = terms1.term_id,

				wp_posts as p2
				LEFT JOIN wp_term_relationships as r2 ON p2.ID = r2.object_ID
				LEFT JOIN wp_term_taxonomy as t2 ON r2.term_taxonomy_id = t2.term_taxonomy_id
				LEFT JOIN wp_terms as terms2 ON t2.term_id = terms2.term_id
			WHERE
				t1.taxonomy = 'category' AND p1.post_status = 'publish' AND terms1.term_id IN (".$args['categories'].") AND
				t2.taxonomy = 'post_tag' AND p2.post_status = 'publish'
				AND p1.ID = p2.ID
			ORDER by tag_count DESC
		");

		$count = 0;
		foreach ($tags as $tag) {
			$tags[$count]->tag_link = get_tag_link($tag->tag_id);
			$count++;
		}
		return $tags;
	}
}
?>
