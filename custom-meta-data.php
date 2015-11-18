<?php
/*
Plugin Name: Custom Meta Data WP-API Plugin
Description: This is a custom GR plugin that will add custom fields to the meta data in posts
Version: 1.0
Author: Matt Polky
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

function grpost_api_init() {
	global $grpost_api;

	$grpost_api = new GrPost_Api();
	add_filter( 'json_endpoints', array( $grpost_api, 'register_routes' ) );
}

add_action( 'wp_json_server_before_serve', 'grpost_api_init' );

class GrPost_Api {
	public function register_routes( $routes ) {
		$routes['/grposts'] = array(
			array( array( $this, 'get_posts'), WP_JSON_Server::READABLE )
		);

		return $routes;
	}

	private function get_term_id($slug, $taxonomy) {
		global $wpdb;

		if (!is_string($slug) || !is_string($taxonomy)) {
			return NULL;
		}
		$row = $wpdb->get_col($wpdb->prepare(
			"SELECT a.term_id
			FROM wp_terms a JOIN wp_term_taxonomy b ON a.term_id = b.term_id
			WHERE a.slug = %s AND b.taxonomy = %s;", $slug, $taxonomy));
		if (count($row) == 1 && ctype_digit($row[0])) {
			return intval($row[0]);
		}
		return NULL;
	}

	public function get_posts($format = NULL, $tag = NULL, $category = NULL, $page = 1, $count = 10) {
		global $wpdb;

		$page_start_index = ($page - 1) * $count;

		$formatFilter = NULL;

		if (is_string($format)) {
			$format = strtolower($format);
			if (in_array($format, array('watch', 'read', 'listen', 'download'))) {
				$formatFilter = " AND format.meta_value = '$format'";
			} else {
				return array();
			}			
		}

		$termFilter = '';

		$tagId = $this->get_term_id($tag, 'post_tag');
		if ($tagId == NULL && $tag != NULL) {
			return array();
		}
		if ($tagId != NULL) {
			$termFilter = "$termFilter INNER JOIN wp_term_relationships rel on grpost.ID = rel.object_id AND rel.term_taxonomy_id = $tagId";
		}

		$categoryId = $this->get_term_id($category, 'category');
		if ($categoryId == NULL && $category != NULL) {
			return array();
		}
		if ($categoryId != NULL) {
			$termFilter = "$termFilter INNER JOIN wp_term_relationships rel on grpost.ID = rel.object_id AND rel.term_taxonomy_id = $categoryId";
		}

		$sql = $wpdb->prepare(
			"SELECT grpost.*, 
			GROUP_CONCAT(tax.term_taxonomy_id) AS CategoryIdList
			FROM (SELECT grpost.ID AS PostId,
					users.display_name as Author,
					grpost.post_title AS Title,
					CASE
						WHEN agent.meta_value = 'null' OR agent.meta_value IS NULL THEN NULL
						ELSE agent.meta_value
					END AS AgentId,
					excerpt.meta_value AS Excerpt,
					grpost.post_date AS PostDateUtc,
					grpost.post_name AS Slug,
					url.meta_value AS Url,
					format.meta_value AS Format,
					thumbnail_post.guid AS ThumbnailImageUrl
				FROM wp_posts grpost
					LEFT JOIN wp_users users on grpost.post_author = users.ID
					LEFT JOIN wp_postmeta agent on grpost.ID = agent.post_id AND agent.meta_key = 'agent'
					LEFT JOIN wp_postmeta excerpt on grpost.ID = excerpt.post_id AND excerpt.meta_key = 'excerpt'
					LEFT JOIN wp_postmeta url on grpost.ID = url.post_id AND url.meta_key = 'url'
					LEFT JOIN wp_postmeta format on grpost.ID = format.post_id AND format.meta_key = 'format'
					LEFT JOIN wp_postmeta thumbnail_image on grpost.ID = thumbnail_image.post_id AND thumbnail_image.meta_key = 'thumbnail_image'
					LEFT JOIN wp_posts thumbnail_post ON thumbnail_post.ID = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(thumbnail_image.meta_value, ':', -1), '}', 1) AS SIGNED)
					$termFilter
				WHERE grpost.post_type = 'grpost' AND grpost.post_status = 'publish' $formatFilter
				ORDER BY agentId DESC, grpost.post_date DESC
				LIMIT %d, %d
			) grpost
				LEFT JOIN wp_term_relationships rel on grpost.PostId = rel.object_id
				LEFT JOIN wp_term_taxonomy tax on rel.term_taxonomy_id = tax.term_taxonomy_id
			WHERE tax.taxonomy = 'category'
			GROUP BY PostId
			ORDER BY grpost.AgentId DESC, grpost.PostDateUtc DESC", $page_start_index, $count);

		$results = $wpdb->get_results($sql, OBJECT);

		foreach($results as $result)
		{
			$result->PostId = intval($result->PostId);
			$result->AgentId = intval($result->AgentId);
			$result->Categories = array_map(intval, explode(',', $result->CategoryIdList));
			unset($result->CategoryIdList);
		}

		return $results;
	}
}

add_action('init', 'limit_editor_capabilities');
add_action('init', 'cptui_register_grpost');
add_action('init', 'cptui_register_promotion');
add_action('init', 'cptui_register_agent');

function limit_editor_capabilities() {
	$role = get_role('editor');
	$role->remove_cap('manage_categories');
}

function cptui_register_grpost() {
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
}

function cptui_register_promotion() {	
	$labels = array(
		"name" => "Promotion",
		"singular_name" => "Promotion",
		"menu_name" => "My Promotions",
		"all_items" => "All Promotion",
		"add_new" => "Add New",
		"add_new_item" => "Add New Promotion",
		"edit" => "Edit",
		"edit_item" => "Edit Promotion",
		"new_item" => "New Promotion",
		"view" => "View",
		"view_item" => "View Promotion",
		"search_items" => "Search Promotions",
		"not_found" => "No promotions found",
		"not_found_in_trash" => "No promotions found in Trash",
		"parent" => "Parent Promotion",
		);
	$args = array(
		"labels" => $labels,
		"description" => "Promotion for PX",
		"public" => true,
		"show_ui" => true,
		"has_archive" => true,
		"show_in_menu" => true,
		"exclude_from_search" => false,
		"capability_type" => "post",
		"map_meta_cap" => true,
		"hierarchical" => false,
		"rewrite" => array( "slug" => "promotion", "with_front" => true ),
		"query_var" => true,
		"menu_icon" => 'dashicons-megaphone',
		"taxonomies" => array( "category" )
	);
	register_post_type( "promotion", $args );
}

function cptui_register_agent() {
	$labels = array(
		"name" => "Agent",
		"singular_name" => "Agent",
		"menu_name" => "My Agents",
		"all_items" => "All Agents",
		"add_new" => "Add New",
		"add_new_item" => "Add New Agent",
		"edit" => "Edit",
		"edit_item" => "Edit Agent",
		"new_item" => "New Agent",
		"view" => "View",
		"view_item" => "View Agent",
		"search_items" => "Search Agents",
		"not_found" => "No Agents found",
		"not_found_in_trash" => "No Agents found in Trash",
		"parent" => "Parent Agent",
		);
	$args = array(
		"labels" => $labels,
		"description" => "Agent for PX",
		"public" => true,
		"show_ui" => true,
		"has_archive" => true,
		"show_in_menu" => true,
		"exclude_from_search" => false,
		"capability_type" => "post",
		"map_meta_cap" => true,
		"hierarchical" => false,
		"rewrite" => array( "slug" => "agent", "with_front" => true ),
		"query_var" => true,
		"menu_icon" => 'dashicons-businessman',
		"taxonomies" => array( "category" )
	);
	register_post_type( "agent", $args );
}

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
			SELECT DISTINCT terms2.term_id as ID, terms2.name as name, t2.count as tag_count, terms2.slug as slug
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
		return $tags;
	}
}
?>
