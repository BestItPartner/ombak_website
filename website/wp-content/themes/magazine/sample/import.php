<?php

defined( 'ABSPATH' ) or die;

$GLOBALS['processed_terms'] = array();
$GLOBALS['processed_posts'] = array();

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function themify_import_post( $post ) {
	global $processed_posts, $processed_terms;

	if ( ! post_type_exists( $post['post_type'] ) ) {
		return;
	}

	/* Menu items don't have reliable post_title, skip the post_exists check */
	if( $post['post_type'] !== 'nav_menu_item' ) {
		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );
		if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
			$processed_posts[ intval( $post['ID'] ) ] = intval( $post_exists );
			return;
		}
	}

	if( $post['post_type'] == 'nav_menu_item' ) {
		if( ! isset( $post['tax_input']['nav_menu'] ) || ! term_exists( $post['tax_input']['nav_menu'], 'nav_menu' ) ) {
			return;
		}
		$_menu_item_type = $post['meta_input']['_menu_item_type'];
		$_menu_item_object_id = $post['meta_input']['_menu_item_object_id'];

		if ( 'taxonomy' == $_menu_item_type && isset( $processed_terms[ intval( $_menu_item_object_id ) ] ) ) {
			$post['meta_input']['_menu_item_object_id'] = $processed_terms[ intval( $_menu_item_object_id ) ];
		} else if ( 'post_type' == $_menu_item_type && isset( $processed_posts[ intval( $_menu_item_object_id ) ] ) ) {
			$post['meta_input']['_menu_item_object_id'] = $processed_posts[ intval( $_menu_item_object_id ) ];
		} else if ( 'custom' != $_menu_item_type ) {
			// associated object is missing or not imported yet, we'll retry later
			// $missing_menu_items[] = $item;
			return;
		}
	}

	$post_parent = ( $post['post_type'] == 'nav_menu_item' ) ? $post['meta_input']['_menu_item_menu_item_parent'] : (int) $post['post_parent'];
	$post['post_parent'] = 0;
	if ( $post_parent ) {
		// if we already know the parent, map it to the new local ID
		if ( isset( $processed_posts[ $post_parent ] ) ) {
			if( $post['post_type'] == 'nav_menu_item' ) {
				$post['meta_input']['_menu_item_menu_item_parent'] = $processed_posts[ $post_parent ];
			} else {
				$post['post_parent'] = $processed_posts[ $post_parent ];
			}
		}
	}

	/**
	 * for hierarchical taxonomies, IDs must be used so wp_set_post_terms can function properly
	 * convert term slugs to IDs for hierarchical taxonomies
	 */
	if( ! empty( $post['tax_input'] ) ) {
		foreach( $post['tax_input'] as $tax => $terms ) {
			if( is_taxonomy_hierarchical( $tax ) ) {
				$terms = explode( ', ', $terms );
				$post['tax_input'][ $tax ] = array_map( 'themify_get_term_id_by_slug', $terms, array_fill( 0, count( $terms ), $tax ) );
			}
		}
	}

	$post['post_author'] = (int) get_current_user_id();
	$post['post_status'] = 'publish';

	$old_id = $post['ID'];

	unset( $post['ID'] );
	$post_id = wp_insert_post( $post, true );
	if( is_wp_error( $post_id ) ) {
		return false;
	} else {
		$processed_posts[ $old_id ] = $post_id;

		if( isset( $post['has_thumbnail'] ) && $post['has_thumbnail'] ) {
			$placeholder = themify_get_placeholder_image();
			if( ! is_wp_error( $placeholder ) ) {
				set_post_thumbnail( $post_id, $placeholder );
			}
		}

		return $post_id;
	}
}

function themify_get_placeholder_image() {
	static $placeholder_image = null;

	if( $placeholder_image == null ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		$upload = wp_upload_bits( $post['post_name'] . '.jpg', null, $wp_filesystem->get_contents( THEMIFY_DIR . '/img/image-placeholder.jpg' ) );

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else
			return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'themify' ) );

		$post['guid'] = $upload['url'];
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		$placeholder_image = $post_id;
	}

	return $placeholder_image;
}

function themify_import_term( $term ) {
	global $processed_terms;

	if( $term_id = term_exists( $term['slug'], $term['taxonomy'] ) ) {
		if ( is_array( $term_id ) ) $term_id = $term_id['term_id'];
		if ( isset( $term['term_id'] ) )
			$processed_terms[ intval( $term['term_id'] ) ] = (int) $term_id;
		return (int) $term_id;
	}

	if ( empty( $term['parent'] ) ) {
		$parent = 0;
	} else {
		$parent = term_exists( $term['parent'], $term['taxonomy'] );
		if ( is_array( $parent ) ) $parent = $parent['term_id'];
	}

	$id = wp_insert_term( $term['name'], $term['taxonomy'], array(
		'parent' => $parent,
		'slug' => $term['slug'],
		'description' => $term['description'],
	) );
	if ( ! is_wp_error( $id ) ) {
		if ( isset( $term['term_id'] ) ) {
			$processed_terms[ intval($term['term_id']) ] = $id['term_id'];
			return $term['term_id'];
		}
	}

	return false;
}

function themify_get_term_id_by_slug( $slug, $tax ) {
	$term = get_term_by( 'slug', $slug, $tax );
	if( $term ) {
		return $term->term_id;
	}

	return false;
}

function themify_undo_import_term( $term ) {
	$term_id = term_exists( $term['slug'], $term['taxonomy'] );
	if ( $term_id ) {
		if ( is_array( $term_id ) ) $term_id = $term_id['term_id'];
		if ( isset( $term_id ) ) {
			wp_delete_term( $term_id, $term['taxonomy'] );
		}
	}
}

/**
 * Determine if a post exists based on title, content, and date
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $args array of database parameters to check
 * @return int Post ID if post exists, 0 otherwise.
 */
function themify_post_exists( $args = array() ) {
	global $wpdb;

	$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
	$db_args = array();

	foreach ( $args as $key => $value ) {
		$value = wp_unslash( sanitize_post_field( $key, $value, 0, 'db' ) );
		if( ! empty( $value ) ) {
			$query .= ' AND ' . $key . ' = %s';
			$db_args[] = $value;
		}
	}

	if ( !empty ( $args ) )
		return (int) $wpdb->get_var( $wpdb->prepare($query, $args) );

	return 0;
}

function themify_undo_import_post( $post ) {
	if( $post['post_type'] == 'nav_menu_item' ) {
		$post_exists = themify_post_exists( array(
			'post_name' => $post['post_name'],
			'post_modified' => $post['post_date'],
			'post_type' => 'nav_menu_item',
		) );
	} else {
		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );
	}
	if( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
		/**
		 * check if the post has been modified, if so leave it be
		 *
		 * NOTE: posts are imported using wp_insert_post() which modifies post_modified field
		 * to be the same as post_date, hence to check if the post has been modified,
		 * the post_modified field is compared against post_date in the original post.
		 */
		if( $post['post_date'] == get_post_field( 'post_modified', $post_exists ) ) {
			wp_delete_post( $post_exists, true ); // true: bypass trash
		}
	}
}

function themify_do_demo_import() {

	if ( isset( $GLOBALS["ThemifyBuilder_Data_Manager"] ) ) {
		remove_action( "save_post", array( $GLOBALS["ThemifyBuilder_Data_Manager"], "save_builder_text_only"), 10, 3 );
	}
$term = array (
  'term_id' => 2,
  'name' => 'Breaking News',
  'slug' => 'breaking-news',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 3,
  'name' => 'Business',
  'slug' => 'business',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 4,
  'name' => 'Entertainment',
  'slug' => 'entertainment',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 5,
  'name' => 'Featured',
  'slug' => 'featured',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 6,
  'name' => 'Life',
  'slug' => 'life',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 7,
  'name' => 'News',
  'slug' => 'news',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 8,
  'name' => 'Sport',
  'slug' => 'sport',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 9,
  'name' => 'Technology',
  'slug' => 'technology',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 10,
  'name' => 'Video',
  'slug' => 'video',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 11,
  'name' => 'World',
  'slug' => 'world',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 7,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 12,
  'name' => 'Education',
  'slug' => 'education',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 6,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 13,
  'name' => 'Fashion',
  'slug' => 'fashion',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 6,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 14,
  'name' => 'Food',
  'slug' => 'food',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 6,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 15,
  'name' => 'Local',
  'slug' => 'local',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 7,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 20,
  'name' => 'Main',
  'slug' => 'main',
  'term_group' => 0,
  'taxonomy' => 'nav_menu',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 21,
  'name' => 'Top Menu',
  'slug' => 'top-menu',
  'term_group' => 0,
  'taxonomy' => 'nav_menu',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 22,
  'name' => 'Footer Nav',
  'slug' => 'footer-nav',
  'term_group' => 0,
  'taxonomy' => 'nav_menu',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$post = array (
  'ID' => 4,
  'post_date' => '2013-08-15 05:17:12',
  'post_date_gmt' => '2013-08-15 05:17:12',
  'post_content' => 'Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris cursus porttitor libero eu condimentum. Mauris viverra ultricies leo eu volutpat. In placerat est urna, in tempor erat rutrum eget. Ut fringilla sodales leo, in pretium urna tempus sit amet. Morbi a ligula convallis, bibendum neque at, viverra eros. Quisque pulvinar gravida nisi vitae suscipit. Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna.  Lorem ipsum dolor sit amet, consectetur adipiscing elit.<!--more-->

Aliquam quis tincidunt sapien, rutrum varius lacus. Nam mattis rutrum gravida. Aenean eu porttitor mi. Vivamus fermentum adipiscing tellus, sed elementum ipsum. Integer at congue orci. Nunc eu ornare tortor. Duis posuere laoreet sodales. Aliquam pharetra erat id rutrum condimentum. Etiam nulla eros, placerat ac tempus sed, tempus ac odio. Suspendisse ac velit feugiat, commodo diam nec, vestibulum tortor. Curabitur sit amet augue sed sapien suscipit imperdiet. Nullam est libero, rhoncus in nisl non, bibendum tristique tortor. Suspendisse varius dapibus mi eu aliquet. Nulla eros diam, aliquet eget volutpat in, sodales non est. Duis leo turpis, tincidunt eu lobortis quis, hendrerit vitae ipsum. Quisque fermentum, tellus sed ornare sagittis, tellus ipsum hendrerit risus, non tempus velit nisl sagittis nisi.',
  'post_title' => 'Train Struck by Earthquake in Japan',
  'post_excerpt' => '',
  'post_name' => 'train-struck-by-earthquake-in-japan',
  'post_modified' => '2017-08-21 06:59:23',
  'post_modified_gmt' => '2017-08-21 06:59:23',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=4',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'news, world',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 7,
  'post_date' => '2013-08-15 05:22:53',
  'post_date_gmt' => '2013-08-15 05:22:53',
  'post_content' => 'Nam aliquam egestas sem malesuada venenatis. Fusce ante elit, iaculis id dapibus sit amet, pharetra a odio. Suspendisse potenti. Cras faucibus risus sit amet leo porta, id consequat lectus lobortis. Aenean sit amet eros vel ipsum convallis condimentum a non eros. Praesent consequat sapien eget erat feugiat, quis vehicula risus congue. Maecenas lobortis semper arcu, semper bibendum lorem mattis fermentum. Mauris eget nunc interdum massa eleifend elementum. Donec nec sem velit.',
  'post_title' => 'Rental Bike Scheme to Come to Over 50 Stations',
  'post_excerpt' => '',
  'post_name' => 'rental-bike-scheme-to-come-to-over-50-stations',
  'post_modified' => '2017-08-21 06:59:21',
  'post_modified_gmt' => '2017-08-21 06:59:21',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=7',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'local, news, sport',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 11,
  'post_date' => '2013-08-15 05:27:57',
  'post_date_gmt' => '2013-08-15 05:27:57',
  'post_content' => 'Aenean sollicitudin purus a nunc cursus, consectetur condimentum felis consequat. In hac habitasse platea dictumst. Interdum et malesuada fames ac ante ipsum primis in faucibus. Etiam gravida, sapien sit amet lacinia facilisis, metus mauris fermentum neque, vel lacinia ipsum nulla et urna. Morbi lobortis, orci nec pellentesque tristique, lorem est ullamcorper tortor, ut lacinia mi elit at leo. Nulla vel nibh laoreet, euismod nulla nec, porttitor augue. Pellentesque congue commodo velit in ultricies. Vivamus imperdiet ultricies elit vitae rutrum. Quisque ut erat vel tortor gravida faucibus ut et justo. Sed ultricies est id purus dapibus egestas. Praesent accumsan, erat a viverra sagittis, velit nisi varius neque, vel fermentum velit sapien in nisl. Vestibulum tempor lectus eget mi ultricies porta. Proin commodo sollicitudin tincidunt.',
  'post_title' => 'Famous Brand Fashion Week to begin from September 28',
  'post_excerpt' => '',
  'post_name' => 'famous-brand-fashion-week-to-begin-from-september-28',
  'post_modified' => '2017-08-21 06:59:19',
  'post_modified_gmt' => '2017-08-21 06:59:19',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=11',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'fashion, life, local',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 14,
  'post_date' => '2013-08-15 05:32:47',
  'post_date_gmt' => '2013-08-15 05:32:47',
  'post_content' => 'Sed lobortis tellus vitae orci vestibulum, eu pulvinar massa interdum. Nullam nec elit at nunc vestibulum tempor non ac justo. Nunc consequat est et vulputate dictum. Aenean nec volutpat lectus. In sed urna tincidunt, tristique elit id, pulvinar lectus. Etiam feugiat bibendum sagittis. Quisque id felis ut libero suscipit sagittis vitae eget turpis. Curabitur consectetur sed massa sed blandit. Donec fermentum rutrum mauris sit amet venenatis. Proin cursus euismod ante, at fringilla velit malesuada id.',
  'post_title' => 'The Best Vegan and Vegetarian Restaurants',
  'post_excerpt' => '',
  'post_name' => 'the-best-vegan-and-vegetarian-restaurants',
  'post_modified' => '2017-08-21 06:59:17',
  'post_modified_gmt' => '2017-08-21 06:59:17',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=14',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'food, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 17,
  'post_date' => '2013-08-15 05:35:05',
  'post_date_gmt' => '2013-08-15 05:35:05',
  'post_content' => 'Integer tempor risus at tincidunt gravida. Sed sollicitudin mattis massa, eu tincidunt urna tincidunt eu. Proin iaculis ligula vel nulla laoreet egestas. Nulla vulputate orci erat, sit amet pulvinar libero porta nec. Suspendisse consequat orci libero, id semper tellus tincidunt id. Phasellus eu fringilla diam.',
  'post_title' => 'Review: Very Nice Italian Restaurant',
  'post_excerpt' => '',
  'post_name' => 'review-very-nice-italian-restaurant',
  'post_modified' => '2017-08-21 06:58:37',
  'post_modified_gmt' => '2017-08-21 06:58:37',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=17',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'food, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 20,
  'post_date' => '2013-08-15 05:37:22',
  'post_date_gmt' => '2013-08-15 05:37:22',
  'post_content' => 'Donec viverra, velit sed tempus hendrerit, lectus nunc gravida purus, in semper massa ligula in lacus. Proin sed ante dui. Nunc congue quam suscipit, lobortis velit et, volutpat dui. Pellentesque aliquet leo nec sem ullamcorper tempus.',
  'post_title' => 'How to make perfect Japanese Sushi',
  'post_excerpt' => '',
  'post_name' => 'how-to-make-perfect-japanese-sushi',
  'post_modified' => '2017-08-21 06:58:35',
  'post_modified_gmt' => '2017-08-21 06:58:35',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=20',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'food, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 23,
  'post_date' => '2013-08-15 05:42:08',
  'post_date_gmt' => '2013-08-15 05:42:08',
  'post_content' => 'Fusce ac mi pretium turpis pulvinar sodales non a enim. Nullam pretium libero urna, ac sodales turpis vestibulum elementum.

<!--more-->Proin at nibh eget mi aliquam vestibulum. Integer tempor risus at tincidunt gravida. Sed sollicitudin mattis massa, eu tincidunt urna tincidunt eu. Proin iaculis ligula vel nulla laoreet egestas.',
  'post_title' => 'Interview: Mark Canlas from Awesome Restaurant',
  'post_excerpt' => '',
  'post_name' => 'interview-mark-canlas-from-awesome-restaurant',
  'post_modified' => '2017-08-21 06:58:34',
  'post_modified_gmt' => '2017-08-21 06:58:34',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=23',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'food, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 26,
  'post_date' => '2013-08-15 05:46:48',
  'post_date_gmt' => '2013-08-15 05:46:48',
  'post_content' => 'Curabitur condimentum justo eu sapien rutrum, ut interdum eros bibendum. Fusce quis quam tortor. Sed id faucibus nisi. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Mauris commodo, risus ac fringilla vulputate, velit enim molestie libero, at imperdiet justo velit a lorem. Donec elementum tempor mi vitae viverra.',
  'post_title' => 'Women\'s Dress for Summer',
  'post_excerpt' => '',
  'post_name' => 'womens-dress-for-summer',
  'post_modified' => '2017-08-21 06:58:32',
  'post_modified_gmt' => '2017-08-21 06:58:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=26',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'fashion, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 29,
  'post_date' => '2013-08-15 05:52:16',
  'post_date_gmt' => '2013-08-15 05:52:16',
  'post_content' => 'Pellentesque cursus, mauris at aliquet mattis, felis elit elementum tortor, fermentum blandit neque mauris non sem. Quisque pulvinar metus quis nulla consequat commodo. Aenean tristique ac magna nec consectetur. Aliquam nec porttitor mauris, ut p.',
  'post_title' => 'Awesome Brand\'s Winter Essentials',
  'post_excerpt' => '',
  'post_name' => 'awesome-brands-winter-essentials',
  'post_modified' => '2017-08-21 06:58:31',
  'post_modified_gmt' => '2017-08-21 06:58:31',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=29',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'fashion, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 32,
  'post_date' => '2013-08-15 05:56:22',
  'post_date_gmt' => '2013-08-15 05:56:22',
  'post_content' => 'Fusce ante elit, iaculis id dapibus sit amet, pharetra a odio. Suspendisse potenti. Cras faucibus risus sit amet leo porta, id consequat lectus lobortis. Aenean sit amet eros vel ipsum convallis condimentum a non eros. Praesent consequat sapien eget erat feugiat, quis vehicula risus congue. Maecenas lobortis semper arcu, semper bibendum lorem mattis fermentum.',
  'post_title' => 'Famous Model\'s Haircut: The Meaning Behind Her New Short Style',
  'post_excerpt' => '',
  'post_name' => 'famous-models-haircut-the-meaning-behind-her-new-short-style',
  'post_modified' => '2017-08-21 06:58:29',
  'post_modified_gmt' => '2017-08-21 06:58:29',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=32',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'fashion, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 35,
  'post_date' => '2013-08-15 06:05:38',
  'post_date_gmt' => '2013-08-15 06:05:38',
  'post_content' => 'In hac habitasse platea dictumst. Interdum et malesuada fames ac ante ipsum primis in faucibus. Etiam gravida, sapien sit amet lacinia facilisis, metus mauris fermentum neque, vel lacinia ipsum nulla et urna. Morbi lobortis, orci nec pellentesque tristique, lorem est ullamcorper tortor, ut lacinia mi elit at leo. Nulla vel nibh laoreet, euismod nulla nec, porttitor augue.',
  'post_title' => '25th Annual Pro Surf Contest Coming Next Week',
  'post_excerpt' => '',
  'post_name' => '25th-annual-pro-surf-contest-coming-next-week',
  'post_modified' => '2017-08-21 06:58:27',
  'post_modified_gmt' => '2017-08-21 06:58:27',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=35',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'local, news, sport',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 38,
  'post_date' => '2013-08-15 06:09:52',
  'post_date_gmt' => '2013-08-15 06:09:52',
  'post_content' => 'Pellentesque congue commodo velit in ultricies. Vivamus imperdiet ultricies elit vitae rutrum. Quisque ut erat vel tortor gravida faucibus ut et justo. Sed ultricies est id purus dapibus egestas. Praesent accumsan, erat a viverra sagittis, velit nisi varius neque, vel fermentum velit sapien in nisl. Vestibulum tempor lectus eget mi ultricies porta.',
  'post_title' => 'Freestyle World Ski Champs',
  'post_excerpt' => '',
  'post_name' => 'freestyle-world-ski-championships-mens',
  'post_modified' => '2017-08-21 06:58:25',
  'post_modified_gmt' => '2017-08-21 06:58:25',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=38',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'sport',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 41,
  'post_date' => '2013-08-15 06:13:22',
  'post_date_gmt' => '2013-08-15 06:13:22',
  'post_content' => 'Nullam nec elit at nunc vestibulum tempor non ac justo. Nunc consequat est et vulputate dictum. Aenean nec volutpat lectus. In sed urna tincidunt, tristique elit id, pulvinar lectus. Etiam feugiat bibendum sagittis. Quisque id felis ut libero suscipit sagittis vitae eget turpis. Curabitur consectetur sed massa sed blandit. Donec fermentum rutrum mauris sit amet venenatis.',
  'post_title' => 'Beginner\'s Guide To Mountain Biking',
  'post_excerpt' => '',
  'post_name' => 'beginners-guide-to-mountain-biking',
  'post_modified' => '2017-08-21 06:58:23',
  'post_modified_gmt' => '2017-08-21 06:58:23',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=41',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'featured, sport',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 44,
  'post_date' => '2013-08-15 06:17:48',
  'post_date_gmt' => '2013-08-15 06:17:48',
  'post_content' => 'Vestibulum mollis elit non massa imperdiet ultricies. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris cursus porttitor libero eu condimentum. Mauris viverra ultricies leo eu volutpat. In placerat est urna, in tempor erat rutrum eget. Ut fringilla sodales leo, in pretium urna tempus sit amet. Morbi a ligula convallis, bibendum neque at, viverra eros. Quisque pulvinar gravida nisi vitae suscipit. Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna.

<!--more-->Sed ultricies nunc scelerisque nisi cursus, vel ultricies turpis sagittis. Maecenas ultrices est sed mi luctus, pretium laoreet elit viverra. Donec eleifend, mauris non placerat lacinia, est orci venenatis ligula, sit amet dapibus lorem leo at neque. Mauris posuere tortor non elit dignissim, ut imperdiet lorem pretium. Sed ornare, urna et vestibulum gravida, velit est fermentum urna, ac imperdiet tortor lorem et odio. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Fusce tincidunt id est eu dignissim. Fusce enim leo, faucibus sit amet ipsum quis, iaculis ullamcorper diam. In hac habitasse platea dictumst.',
  'post_title' => 'Smartphones Outsell Basic Mobiles',
  'post_excerpt' => '',
  'post_name' => 'smartphones-outsell-basic-mobiles',
  'post_modified' => '2017-08-21 06:58:21',
  'post_modified_gmt' => '2017-08-21 06:58:21',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=44',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'business, news, world',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 47,
  'post_date' => '2013-08-15 06:22:22',
  'post_date_gmt' => '2013-08-15 06:22:22',
  'post_content' => 'Quisque varius elementum libero a bibendum. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Proin eu quam quis magna sagittis vestibulum. Nullam metus nisi, cursus vehicula varius eu, laoreet vitae mi. Donec ac odio nec tortor rhoncus suscipit eget vel tortor. Quisque molestie mauris in iaculis sollicitudin. Cras eget odio tortor. Proin auctor nibh hendrerit, scelerisque justo id, aliquam lacus. Donec pretium leo in magna blandit tempus. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec dictum, enim id fermentum tempor, dolor diam molestie mi, at fermentum nibh velit sit amet felis. In ornare nibh egestas magna ornare viverra. Quisque ultricies tellus eu est luctus tincidunt. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vestibulum adipiscing risus tempor, imperdiet nulla ut, sollicitudin turpis. Maecenas a neque non diam aliquam luctus et at erat.',
  'post_title' => 'Unemployment Unlikely to Fall Sharply',
  'post_excerpt' => '',
  'post_name' => 'unemployment-unlikely-to-fall-sharply',
  'post_modified' => '2017-08-21 06:58:18',
  'post_modified_gmt' => '2017-08-21 06:58:18',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=47',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'business, featured',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 50,
  'post_date' => '2013-08-15 06:25:33',
  'post_date_gmt' => '2013-08-15 06:25:33',
  'post_content' => 'Morbi massa purus, euismod non libero et, consequat lobortis ligula. Mauris volutpat euismod urna eget suscipit. Nunc ac semper nibh. Sed odio augue, porta aliquam sapien eget, faucibus viverra arcu. Morbi turpis diam, tincidunt eu tristique non, bibendum quis dui. Aenean in porta mi, sit amet commodo eros. Maecenas nec libero vitae tellus fringilla sollicitudin eu vel ipsum. Aenean in justo lacus. Vestibulum turpis turpis, egestas ut venenatis ut, elementum at mi. Phasellus molestie nibh in dui malesuada viverra.',
  'post_title' => 'Poll: Are You Using Web Technology to Process Payments?',
  'post_excerpt' => '',
  'post_name' => 'poll-are-you-using-web-technology-to-process-payments',
  'post_modified' => '2017-08-21 06:58:17',
  'post_modified_gmt' => '2017-08-21 06:58:17',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=50',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'business',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 53,
  'post_date' => '2013-08-15 06:27:14',
  'post_date_gmt' => '2013-08-15 06:27:14',
  'post_content' => 'Fusce magna tellus, tempus at ante nec, accumsan porta nunc. Vestibulum eleifend lobortis venenatis. Etiam volutpat massa sit amet tincidunt vulputate. Phasellus sed neque in tortor sodales lacinia. Nam venenatis tincidunt metus, in dictum diam rutrum a. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Quisque consectetur commodo lacinia. Sed in turpis ac ante dignissim pulvinar. Nam faucibus sapien eu fringilla iaculis. Suspendisse molestie odio in enim egestas vestibulum. Quisque orci augue, iaculis sit amet augue sit amet, pulvinar mattis enim. Cras sit amet sapien ligula. Cras adipiscing iaculis hendrerit.',
  'post_title' => 'Women in Leadership',
  'post_excerpt' => '',
  'post_name' => 'women-in-leadership',
  'post_modified' => '2017-08-21 06:58:15',
  'post_modified_gmt' => '2017-08-21 06:58:15',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=53',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'breaking-news, business',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 56,
  'post_date' => '2013-08-15 06:30:37',
  'post_date_gmt' => '2013-08-15 06:30:37',
  'post_content' => 'Aliquam purus mi, varius vel aliquet eu, tempus at orci. Morbi scelerisque tincidunt purus sed faucibus. Curabitur auctor gravida felis. Vestibulum venenatis lectus vel blandit aliquam. Maecenas lacinia, ante vel ullamcorper mattis, nisi turpis pellentesque nisi, ut bibendum neque turpis et elit. Cras ullamcorper eros ipsum, at scelerisque justo eleifend vitae. Integer varius nunc viverra, molestie orci sed, consequat justo. Vivamus pharetra magna et nisi bibendum volutpat. In euismod diam ut arcu tempus ullamcorper. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vestibulum sed ullamcorper nisi. Ut placerat nec erat facilisis sollicitudin. Pellentesque mollis sapien et dolor lobortis consectetur.',
  'post_title' => 'Seeking Better Teachers, Local Colleges That Train Them',
  'post_excerpt' => '',
  'post_name' => 'seeking-better-teachers-local-colleges-that-train-them',
  'post_modified' => '2017-08-21 06:58:13',
  'post_modified_gmt' => '2017-08-21 06:58:13',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=56',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'education, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 59,
  'post_date' => '2013-08-15 06:33:29',
  'post_date_gmt' => '2013-08-15 06:33:29',
  'post_content' => 'Nulla velit felis, laoreet et dolor ut, accumsan vestibulum arcu. Donec porta urna non neque tincidunt ultrices. Proin tempor lobortis sodales. Praesent condimentum in diam ut mollis. Praesent sed convallis ante. Suspendisse potenti. Nullam ullamcorper, dui non hendrerit feugiat, nibh quam rutrum tortor, et vestibulum quam purus vitae orci. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Nullam at tempor justo. Praesent fringilla varius massa, vel lacinia ligula.',
  'post_title' => 'The School for Web Programming Opens',
  'post_excerpt' => '',
  'post_name' => 'the-school-for-web-programming-opens',
  'post_modified' => '2017-08-21 06:58:11',
  'post_modified_gmt' => '2017-08-21 06:58:11',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=59',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'education, life, local',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2425,
  'post_date' => '2013-08-15 06:38:25',
  'post_date_gmt' => '2013-08-15 06:38:25',
  'post_content' => 'Vivamus dictum urna ac arcu hendrerit ultrices. Duis consectetur massa at nisi consectetur tincidunt. Maecenas massa tortor, scelerisque at ipsum vel, accumsan ultrices turpis. Suspendisse auctor id magna eu gravida. Sed commodo mi a interdum feugiat. In rhoncus scelerisque enim quis pulvinar. Maecenas blandit dapibus scelerisque. Morbi vitae urna urna. Maecenas vulputate laoreet feugiat.',
  'post_title' => 'Kids Education Events for Free',
  'post_excerpt' => '',
  'post_name' => 'kids-education-events-for-free',
  'post_modified' => '2017-08-21 06:58:09',
  'post_modified_gmt' => '2017-08-21 06:58:09',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=62',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'education, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 65,
  'post_date' => '2013-08-15 06:42:12',
  'post_date_gmt' => '2013-08-15 06:42:12',
  'post_content' => 'Vestibulum non nisi et quam scelerisque interdum in a quam. Phasellus mollis ornare libero sit amet rhoncus. Curabitur non tellus id massa laoreet mollis vitae id sapien. Maecenas euismod nec arcu ac pretium. Cras non vehicula augue. Nulla volutpat sem nisl. Proin euismod sapien ut dignissim mattis. Nulla orci augue, feugiat vitae risus vitae, venenatis vulputate turpis. Sed sed dui porta, varius odio id, pretium erat. Nam purus enim, cursus quis iaculis id, consectetur ut ante. Duis molestie pretium metus, nec dictum neque ultricies ac. Etiam sit amet sem eget turpis ultrices rhoncus non et nisi. Curabitur in tortor sed augue consectetur varius.

<!--more-->Vivamus sagittis sapien risus, a placerat tortor pellentesque sit amet. Nam luctus eget dolor ac convallis. Nam vel aliquam arcu, eu fringilla tellus. Mauris eu augue et magna adipiscing sodales quis semper sem. Aenean scelerisque eu sapien sit amet facilisis. Sed viverra, enim eget egestas tristique, purus mi tempor metus, id pellentesque lorem velit non purus. Nulla interdum tellus et malesuada adipiscing. In ut ultricies lacus. Aliquam tristique magna ut lacus commodo, in elementum nibh blandit. Fusce vel accumsan nunc. Aliquam et facilisis velit.',
  'post_title' => 'Student Interview: Maria - Awesome University',
  'post_excerpt' => '',
  'post_name' => 'student-interview-maria-awesome-university',
  'post_modified' => '2017-08-21 06:58:08',
  'post_modified_gmt' => '2017-08-21 06:58:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=65',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'education, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2426,
  'post_date' => '2013-08-15 06:50:23',
  'post_date_gmt' => '2013-08-15 06:50:23',
  'post_content' => 'Nulla non neque non enim tristique cursus. In adipiscing condimentum lacus. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris cursus porttitor libero eu condimentum. Mauris viverra ultricies leo eu volutpat. In placerat est urna, in tempor erat rutrum eget. Ut fringilla sodales leo, in pretium urna tempus sit amet. Morbi a ligula convallis, bibendum neque at, viverra eros. Quisque pulvinar gravida nisi vitae suscipit. Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna.

<!--more-->Nam sollicitudin, dui nec tincidunt accumsan, purus nulla viverra diam, ullamcorper malesuada lectus arcu id massa. Praesent consectetur nulla blandit magna pulvinar, vitae volutpat eros feugiat. Suspendisse egestas risus in elementum faucibus. Proin vitae porta quam, sit amet eleifend magna. Phasellus eu placerat mi. Aenean tempor dapibus varius. Fusce auctor vel leo eu ullamcorper. In posuere, quam nec adipiscing blandit, tortor mi consequat nisi, at faucibus leo arcu ut est. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae.',
  'post_title' => 'The Street Artists of South America',
  'post_excerpt' => '',
  'post_name' => 'the-street-artists-of-south-america',
  'post_modified' => '2017-08-21 06:58:06',
  'post_modified_gmt' => '2017-08-21 06:58:06',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=68',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'news, world',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 71,
  'post_date' => '2013-08-15 06:59:24',
  'post_date_gmt' => '2013-08-15 06:59:24',
  'post_content' => 'Ut dapibus magna id imperdiet dapibus. Praesent pretium, justo et congue consequat, magna tortor tincidunt nulla. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris cursus porttitor libero eu condimentum. Mauris viverra ultricies leo eu volutpat. In placerat est urna, in tempor erat rutrum eget. Ut fringilla sodales leo, in pretium urna tempus sit amet. Morbi a ligula convallis, bibendum neque at, viverra eros. Quisque pulvinar gravida nisi vitae suscipit. Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna.

<!--more-->Cras at nisl eros. Nullam non mauris id turpis interdum scelerisque eu ut leo. Donec suscipit porta quam at iaculis. Fusce faucibus molestie ultricies. Nulla fringilla, diam at pulvinar commodo, ante metus luctus justo, ut tincidunt urna elit in sem. Nam ac ultricies quam. Cras ullamcorper ipsum vitae nisi dapibus, sit amet convallis nunc facilisis. Praesent malesuada dapibus tincidunt. Etiam adipiscing lorem odio, sed volutpat lectus eleifend quis. Fusce auctor ligula a pellentesque ultrices. In tincidunt porta est, sit amet posuere nunc faucibus at. Praesent eu purus in enim auctor lacinia.',
  'post_title' => 'Famous Company Bought a Popular Web Service',
  'post_excerpt' => '',
  'post_name' => 'famous-company-bought-a-popular-web-service',
  'post_modified' => '2017-08-21 06:58:05',
  'post_modified_gmt' => '2017-08-21 06:58:05',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=71',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'business, news, world',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 112,
  'post_date' => '2013-08-15 15:22:19',
  'post_date_gmt' => '2013-08-15 15:22:19',
  'post_content' => 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.

<!--more-->Nullam nec velit eget velit venenatis vehicula a ac mi. Donec facilisis feugiat dui, accumsan pretium lorem consequat non. Aliquam in sem dolor. Nam non vulputate leo. Quisque facilisis convallis magna, eget luctus orci feugiat ut. Maecenas non ultricies enim. Donec vestibulum cursus sapien, eu bibendum purus dapibus nec. Proin risus lacus, viverra at est eu, vehicula sodales libero. Maecenas urna sapien, scelerisque ac lacinia sed, euismod eget arcu. Donec et arcu massa.',
  'post_title' => 'Modern Warfare 3 : Further Look Slow Mo Debut Trailer',
  'post_excerpt' => '',
  'post_name' => 'modern-warfare-3-further-look-slow-mo-debut-trailer',
  'post_modified' => '2017-08-21 06:58:02',
  'post_modified_gmt' => '2017-08-21 06:58:02',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=112',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'video_url' => 'http://www.youtube.com/watch?v=Abjx1JJO1i8',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"video\\",\\"mod_settings\\":[]}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
    'category' => 'video',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 117,
  'post_date' => '2013-08-15 15:31:33',
  'post_date_gmt' => '2013-08-15 15:31:33',
  'post_content' => 'Vivamus rhoncus vitae diam eget fermentum.

<!--more-->Ut ac dapibus dui, nec condimentum tellus. Aenean facilisis varius ligula, et aliquam est rutrum egestas. Maecenas accumsan risus sit amet libero tempus, a pulvinar sem pellentesque. Vestibulum egestas nibh vel sem condimentum, id laoreet nisl rutrum. Nulla facilisi. Cras non pharetra sem, sed commodo urna. Nullam aliquam eleifend lectus. Nunc eu libero at nisi commodo porta. Nunc orci enim, dictum ultrices ultrices vel, luctus in libero.',
  'post_title' => 'One Night On 7000 Feet',
  'post_excerpt' => '',
  'post_name' => 'one-night-on-7000-feet',
  'post_modified' => '2017-08-21 06:57:12',
  'post_modified_gmt' => '2017-08-21 06:57:12',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=117',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'video_url' => 'http://vimeo.com/32619535',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'video',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 150,
  'post_date' => '2013-08-16 05:34:08',
  'post_date_gmt' => '2013-08-16 05:34:08',
  'post_content' => 'Integer at congue orci. Nunc eu ornare tortor.

<!--more-->Duis posuere laoreet sodales. Aliquam pharetra erat id rutrum condimentum. Etiam nulla eros, placerat ac tempus sed, tempus ac odio. Suspendisse ac velit feugiat, commodo diam nec, vestibulum tortor. Curabitur sit amet augue sed sapien suscipit imperdiet. Nullam est libero, rhoncus in nisl non, bibendum tristique tortor. Suspendisse varius dapibus mi eu aliquet. Nulla eros diam, aliquet eget volutpat in, sodales non est. Duis leo turpis, tincidunt eu lobortis quis, hendrerit vitae ipsum. Quisque fermentum, tellus sed ornare sagittis, tellus ipsum hendrerit risus, non tempus velit nisl sagittis nisi.',
  'post_title' => 'Explore Views of the Burj Khalifa with Google Maps',
  'post_excerpt' => '',
  'post_name' => 'explore-views-of-the-burj-khalifa-with-google-maps',
  'post_modified' => '2017-08-21 06:57:10',
  'post_modified_gmt' => '2017-08-21 06:57:10',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=150',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'video_url' => 'http://www.youtube.com/watch?v=cn7AFhVEI5o',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'video',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 157,
  'post_date' => '2013-08-16 05:43:52',
  'post_date_gmt' => '2013-08-16 05:43:52',
  'post_content' => 'Proin condimentum turpis justo, at sagittis augue commodo et. <em id="__mceDel" style="font-size: 13px; line-height: 19px;"></em>

<!--more-->Suspendisse nisl nisl, sollicitudin ac ultricies laoreet, interdum ac enim. Maecenas viverra purus in dapibus vestibulum. Quisque a mi condimentum, aliquet turpis ac, pulvinar risus. Maecenas cursus non libero nec tempor. Pellentesque dictum mi nec convallis sollicitudin. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Cras quis bibendum nisl. Vivamus ut risus ullamcorper, mattis sem sit amet, feugiat ante. In tincidunt, neque nec egestas mattis, purus metus posuere elit, eget lacinia felis metus ac magna. Morbi dui ipsum, adipiscing vitae pulvinar a, dignissim sed urna.',
  'post_title' => 'Ferrari 512 BBi Is A Piece of Art',
  'post_excerpt' => '',
  'post_name' => 'ferrari-512-bbi-is-a-piece-of-art',
  'post_modified' => '2017-08-21 06:57:08',
  'post_modified_gmt' => '2017-08-21 06:57:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=157',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'video_url' => 'http://www.youtube.com/watch?v=Dt7uQr0YiUs',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'video',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 160,
  'post_date' => '2013-08-16 05:50:12',
  'post_date_gmt' => '2013-08-16 05:50:12',
  'post_content' => 'Curabitur nisl nunc, imperdiet sit amet sapien nec, bibendum viverra tortor. Sed pellentesque tempus sapien sit amet auctor. Praesent sollicitudin adipiscing nibh, quis rutrum arcu pretium quis. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce tempus non nisi eget faucibus. Donec non felis consectetur, accumsan velit ut, varius eros. Curabitur auctor velit ac mauris vestibulum, quis sagittis dolor facilisis. Morbi pulvinar vehicula libero, vel volutpat quam scelerisque vel.',
  'post_title' => 'Twitter turns 7',
  'post_excerpt' => '',
  'post_name' => 'twitter-turns-7',
  'post_modified' => '2017-08-21 06:57:06',
  'post_modified_gmt' => '2017-08-21 06:57:06',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=160',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'technology',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 163,
  'post_date' => '2013-08-16 06:00:49',
  'post_date_gmt' => '2013-08-16 06:00:49',
  'post_content' => 'Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris cursus porttitor libero eu condimentum. Mauris viverra ultricies leo eu volutpat. In placerat est urna, in tempor erat rutrum eget. Ut fringilla sodales leo, in pretium urna tempus sit amet. Morbi a ligula convallis, bibendum neque at, viverra eros. Quisque pulvinar gravida nisi vitae suscipit. Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc euismod mattis libero quis placerat. Mauris in mollis dui.

<!--more-->Maecenas at condimentum tellus. Donec viverra sapien nulla, eu imperdiet urna pellentesque sed. Vestibulum vel leo fermentum, lacinia risus vel, vulputate neque. Nulla ac bibendum leo. Maecenas quis pellentesque dui. Praesent elementum dolor quis arcu commodo viverra. Mauris cursus porttitor libero eu condimentum. Mauris viverra ultricies leo eu volutpat. In placerat est urna, in tempor erat rutrum eget. Ut fringilla sodales leo, in pretium urna tempus sit amet. Morbi a ligula convallis, bibendum neque at, viverra eros. Quisque pulvinar gravida nisi vitae suscipit.',
  'post_title' => 'Totem Pole Raised on West Coast',
  'post_excerpt' => '',
  'post_name' => 'totem-pole-raised-on-west-coast',
  'post_modified' => '2017-08-21 06:57:05',
  'post_modified_gmt' => '2017-08-21 06:57:05',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=163',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'local, news',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 166,
  'post_date' => '2013-08-16 06:07:42',
  'post_date_gmt' => '2013-08-16 06:07:42',
  'post_content' => 'Praesent vitae gravida nibh. Maecenas tincidunt pretium elit, ac bibendum erat commodo vel. Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum.

<!--more-->Mauris vitae porttitor velit. Sed suscipit dictum magna, sed varius ante fermentum id. Donec ut justo eget risus hendrerit egestas. Donec eu ipsum id justo ornare sagittis nec sit amet erat. Maecenas viverra arcu in tellus sagittis consequat. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Cras non venenatis diam. Morbi lobortis fringilla est faucibus gravida. Vestibulum in posuere diam, a porta nibh. Donec sed ornare magna. Sed quis erat elit. Mauris nunc arcu, viverra eget congue id, mollis vitae nibh. Maecenas tempor justo ut ornare lobortis.',
  'post_title' => 'Federal Election: Day 10 of the Campaign',
  'post_excerpt' => '',
  'post_name' => 'federal-election-day-10-of-the-campaign',
  'post_modified' => '2017-08-21 06:57:04',
  'post_modified_gmt' => '2017-08-21 06:57:04',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=166',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'featured, news, world',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 169,
  'post_date' => '2013-08-16 06:12:56',
  'post_date_gmt' => '2013-08-16 06:12:56',
  'post_content' => 'Fusce et arcu suscipit, facilisis turpis vitae, suscipit neque. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris cursus porttitor libero eu condimentum. Mauris viverra ultricies leo eu volutpat. In placerat est urna, in tempor erat rutrum eget. Ut fringilla sodales leo, in pretium urna tempus sit amet. Morbi a ligula convallis, bibendum neque at, viverra eros. Quisque pulvinar gravida nisi vitae suscipit. Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna.

<!--more-->Maecenas mi velit, mattis vitae nibh eget, volutpat iaculis neque. Nullam sed vulputate risus, vitae iaculis libero. Curabitur quis lacus tortor. Fusce luctus id felis in rutrum. Nulla in arcu eu nulla hendrerit mollis. Proin sit amet ornare felis. Maecenas et congue eros. Aliquam id tortor vitae nibh egestas vulputate quis eget sapien. Donec aliquet pellentesque hendrerit. Etiam tempor elit at odio feugiat, sed commodo augue semper. Interdum et malesuada fames ac ante ipsum primis in faucibus. Pellentesque hendrerit nisi eu elit fringilla pulvinar. Duis vitae purus sollicitudin nisi aliquet fringilla. Maecenas varius tincidunt luctus.',
  'post_title' => 'U-17 World Championship',
  'post_excerpt' => '',
  'post_name' => 'u-17-world-championship',
  'post_modified' => '2017-08-21 06:57:01',
  'post_modified_gmt' => '2017-08-21 06:57:01',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=169',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'featured, sport',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 172,
  'post_date' => '2013-08-16 06:19:50',
  'post_date_gmt' => '2013-08-16 06:19:50',
  'post_content' => 'Donec rhoncus ligula tortor, quis gravida dolor sollicitudin vitae. Vestibulum non nisi et quam scelerisque interdum in a quam. Phasellus mollis ornare libero sit amet rhoncus. Curabitur non tellus id massa laoreet mollis vitae id sapien. Maecenas euismod nec arcu ac pretium. Cras non vehicula augue. Nulla volutpat sem nisl. Proin euismod sapien ut dignissim mattis. Nulla orci augue, feugiat vitae risus vitae, venenatis vulputate turpis. Sed sed dui porta, varius odio id, pretium erat. Nam purus enim, cursus quis iaculis id, consectetur ut ante. Duis molestie pretium metus, nec dictum neque ultricies ac.

<!--more-->Suspendisse ac ligula eu ipsum rutrum mollis. Fusce feugiat faucibus quam ut hendrerit. In nunc massa, ornare dictum lobortis nec, ultricies et erat. Mauris nec tortor congue, venenatis risus porttitor, tristique ligula. Praesent vitae viverra lectus. Praesent euismod purus erat, at malesuada purus tempus feugiat.',
  'post_title' => 'Nachos Recipe That’s Just Too Easy to Make',
  'post_excerpt' => '',
  'post_name' => 'nachos-recipe-thats-just-too-easy-to-make',
  'post_modified' => '2017-08-21 06:56:59',
  'post_modified_gmt' => '2017-08-21 06:56:59',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=172',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'food, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 175,
  'post_date' => '2013-08-16 06:24:57',
  'post_date_gmt' => '2013-08-16 06:24:57',
  'post_content' => 'Cras a neque at elit placerat accumsan et id arcu. Aenean malesuada commodo luctus. In semper nisi quis lacus imperdiet, congue vulputate nisi commodo. Nullam ac vulputate est, quis bibendum massa. Nunc facilisis, dui et sodales pulvinar, ipsum felis blandit ligula, nec vehicula erat diam nec enim. Nulla at velit aliquet, lacinia est pellentesque, accumsan metus. Nulla non interdum tellus. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Curabitur consequat quis urna at laoreet.',
  'post_title' => 'Themify Debuts New Song on \'Drag &amp; Drop WordPress Themes\'',
  'post_excerpt' => '',
  'post_name' => 'themify-debuts-new-song-on-drag-drop-wordpress-themes',
  'post_modified' => '2017-08-21 06:56:57',
  'post_modified_gmt' => '2017-08-21 06:56:57',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=175',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'breaking-news, entertainment',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 178,
  'post_date' => '2013-08-16 06:30:13',
  'post_date_gmt' => '2013-08-16 06:30:13',
  'post_content' => 'Proin condimentum justo ipsum, non cursus tortor aliquam sed. Nulla lacus odio, ornare ac eros tincidunt, commodo auctor tortor. Donec sed metus id sem facilisis faucibus fringilla vitae purus. Fusce metus tellus, gravida ut lectus sed, posuere tempor mi. Quisque ac diam interdum, iaculis nulla sed, euismod leo. Integer sed pretium augue. Fusce venenatis nunc eget faucibus bibendum. Cras venenatis non elit vel cursus. Nulla quis malesuada dolor.',
  'post_title' => 'Movie Guide: Top 20 Picks',
  'post_excerpt' => '',
  'post_name' => 'movie-guide-top-20-picks',
  'post_modified' => '2017-08-21 06:56:55',
  'post_modified_gmt' => '2017-08-21 06:56:55',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=178',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'entertainment',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 181,
  'post_date' => '2013-08-16 06:35:41',
  'post_date_gmt' => '2013-08-16 06:35:41',
  'post_content' => 'Jliquam eget purus neque. Phasellus auctor nisl ut pulvinar bibendum. Cras vitae sodales tortor. Phasellus malesuada vulputate suscipit. Duis euismod faucibus interdum. Aenean et augue ac neque sollicitudin sollicitudin a ac augue. Aenean commodo fermentum dolor non auctor. Suspendisse non neque sed felis faucibus tempor eget et leo.',
  'post_title' => 'Jessica Debuts a New Look',
  'post_excerpt' => '',
  'post_name' => 'jessica-debuts-a-new-look',
  'post_modified' => '2017-08-21 06:56:54',
  'post_modified_gmt' => '2017-08-21 06:56:54',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=181',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'entertainment, featured',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 184,
  'post_date' => '2013-08-16 06:38:26',
  'post_date_gmt' => '2013-08-16 06:38:26',
  'post_content' => 'Nam feugiat adipiscing malesuada. Aenean in dui diam. Aliquam eu ultricies mauris. Praesent iaculis, arcu ac posuere auctor, tortor libero pellentesque odio, vel vehicula sapien magna nec nisl. Curabitur quis arcu lobortis, bibendum est sed, mattis odio. Donec facilisis ut magna in placerat. Aliquam tellus enim, fringilla nec eleifend et, dignissim ut dui. Maecenas vel convallis leo. Sed mi tellus, scelerisque a tincidunt vitae, dignissim a libero. Maecenas eget suscipit arcu. Quisque gravida pretium erat, vulputate euismod ipsum volutpat et. Proin iaculis consequat nibh sed vulputate. Integer nec aliquet felis, non iaculis dolor.',
  'post_title' => 'Review: Themify\'s Movie',
  'post_excerpt' => '',
  'post_name' => 'review-themifys-new-movie',
  'post_modified' => '2017-08-21 06:56:53',
  'post_modified_gmt' => '2017-08-21 06:56:53',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=184',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'breaking-news, entertainment',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 187,
  'post_date' => '2013-08-16 06:40:43',
  'post_date_gmt' => '2013-08-16 06:40:43',
  'post_content' => 'Etiam congue pharetra felis non blandit. In hac habitasse platea dictumst. Mauris interdum egestas eros, sit amet posuere arcu scelerisque pretium. Nullam vestibulum ultrices hendrerit. Duis sed porta arcu, id rhoncus mauris. Vivamus in vestibulum est, et rhoncus augue. Sed ligula massa, scelerisque non pretium a, porta vel lorem.Etiam congue pharetra felis non blandit. In hac habitasse platea dictumst. Mauris interdum egestas eros, sit amet posuere arcu scelerisque pretium. Nullam vestibulum ultrices hendrerit. Duis sed porta arcu, id rhoncus mauris. Vivamus in vestibulum est, et rhoncus augue. Sed ligula massa, scelerisque non pretium a, porta vel lorem. Fusce sit amet nisl porttitor, bibendum quam blandit, luctus neque.

<!--more-->Etiam congue pharetra felis non blandit. In hac habitasse platea dictumst. Mauris interdum egestas eros, sit amet posuere arcu scelerisque pretium. Nullam vestibulum ultrices hendrerit. Duis sed porta arcu, id rhoncus mauris. Vivamus in vestibulum est, et rhoncus augue. Sed ligula massa, scelerisque non pretium a, porta vel lorem.',
  'post_title' => 'Movie Awards: See Who\'s Nominated',
  'post_excerpt' => '',
  'post_name' => 'movie-awards-see-whos-nominated',
  'post_modified' => '2017-08-21 06:56:50',
  'post_modified_gmt' => '2017-08-21 06:56:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=187',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'entertainment, featured',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 190,
  'post_date' => '2013-08-16 06:50:18',
  'post_date_gmt' => '2013-08-16 06:50:18',
  'post_content' => 'Morbi augue metus, adipiscing ut mattis vulputate, pulvinar vitae magna. Sed in dignissim sapien. Maecenas vitae dui nisl. Mauris bibendum, neque vitae varius fermentum, nisl ligula dictum ante, et sodales ipsum neque quis sem. Quisque nec tellus et libero sollicitudin egestas. Proin luctus felis ut justo venenatis rutrum. Donec a ultricies sapien. Nulla at sapien leo. Nulla feugiat arcu magna, nec eleifend justo porttitor vel. Quisque fermentum, elit ut pulvinar aliquam, est eros tincidunt diam, ut vehicula eros velit nec massa. Vestibulum non nisi et quam scelerisque interdum in a quam. Phasellus mollis ornare libero sit amet rhoncus. Curabitur non tellus id massa laoreet mollis vitae id sapien. Maecenas euismod nec arcu ac pretium. Cras non vehicula augue. Nulla volutpat sem nisl. Proin euismod sapien ut dignissim mattis.',
  'post_title' => 'iOS7 - the Mobile OS from a Whole New Perspective',
  'post_excerpt' => '',
  'post_name' => 'ios7-the-mobile-os-from-a-whole-new-perspective',
  'post_modified' => '2017-08-21 06:56:48',
  'post_modified_gmt' => '2017-08-21 06:56:48',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=190',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'breaking-news, featured, technology',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 194,
  'post_date' => '2013-08-16 07:03:23',
  'post_date_gmt' => '2013-08-16 07:03:23',
  'post_content' => 'Morbi ultricies ultricies nibh, quis euismod mauris pellentesque et. Vestibulum luctus auctor orci ac facilisis. Aliquam laoreet sed neque sed elementum. Quisque quis lacus at lorem luctus dapibus id at lacus. Phasellus nec interdum ante. Nullam a augue velit. Morbi vel urna pulvinar, cursus arcu vel, iaculis massa. Integer posuere tincidunt mi sed aliquet. Aliquam sollicitudin ultricies mi a rhoncus. Etiam urna purus, ultrices eu erat a, fringilla pellentesque tellus. Quisque fermentum, elit ut pulvinar aliquam, est eros tincidunt diam, ut vehicula eros velit nec massa. Vestibulum non nisi et quam scelerisque interdum in a quam. Phasellus mollis ornare libero sit amet rhoncus. Curabitur non tellus id massa laoreet mollis vitae id sapien. Maecenas euismod nec arcu ac pretium. Cras non vehicula augue. Nulla volutpat sem nisl. Proin euismod sapien ut dignissim mattis.<!--more-->

Nullam vel vulputate nisl. Phasellus porttitor, neque posuere porta imperdiet, erat orci tempus magna, non vestibulum nisl nibh eget dui. Nam lacinia facilisis augue, vitae porta felis porttitor ac. Etiam et tellus ac justo rutrum accumsan id id nunc. Quisque fermentum, elit ut pulvinar aliquam, est eros tincidunt diam, ut vehicula eros velit nec massa. Vestibulum non nisi et quam scelerisque interdum in a quam. Phasellus mollis ornare libero sit amet rhoncus. Curabitur non tellus id massa laoreet mollis vitae id sapien. Maecenas euismod nec arcu ac pretium. Cras non vehicula augue. Nulla volutpat sem nisl. Proin euismod sapien ut dignissim mattis.',
  'post_title' => 'Google Glass: What You Need to Know',
  'post_excerpt' => '',
  'post_name' => 'google-glass-what-you-need-to-know',
  'post_modified' => '2017-08-21 06:56:47',
  'post_modified_gmt' => '2017-08-21 06:56:47',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=194',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'breaking-news, technology',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 197,
  'post_date' => '2013-01-16 07:08:49',
  'post_date_gmt' => '2013-01-16 07:08:49',
  'post_content' => '<span style="font-size: 13px; line-height: 19px;">Nunc lacinia massa at magna interdum feugiat. Morbi id nibh ac nunc facilisis ultricies ut id neque.</span>

<!--more--> Etiam et sem quam. Fusce vel ligula laoreet, blandit nisi a, cursus ante. Duis fringilla ipsum vestibulum quam consectetur eleifend. Nunc ac ante velit. Etiam semper elit enim, vel suscipit ligula vestibulum et. Mauris vel blandit sapien, eu iaculis tortor. Donec ac tortor sit amet augue porttitor viverra nec nec dolor. Mauris fringilla auctor lectus.',
  'post_title' => 'colAR Mix Trailer',
  'post_excerpt' => '',
  'post_name' => 'colar-mix-trailer',
  'post_modified' => '2017-08-21 06:59:26',
  'post_modified_gmt' => '2017-08-21 06:59:26',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=197',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'video_url' => 'http://www.youtube.com/watch?v=tmfXgvT9h3s',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'video',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 200,
  'post_date' => '2013-08-16 07:14:06',
  'post_date_gmt' => '2013-08-16 07:14:06',
  'post_content' => 'Sed tempor consequat magna. Duis dictum tincidunt nisl quis ultrices. Donec sollicitudin magna eu nunc aliquet luctus. Vestibulum hendrerit risus neque, eu mollis lectus condimentum non. Nullam nunc dui, gravida at ipsum nec, ultricies aliquet sapien. Vivamus non commodo mi, at ultricies nisl. Nam nec tortor egestas, aliquet lorem vel, semper turpis. In posuere faucibus arcu, sit amet ultricies neque eleifend eu. Donec placerat id elit eu sollicitudin. Phasellus vitae erat suscipit leo euismod faucibus eu sit amet dui. Etiam iaculis nec lectus fringilla sodales. Morbi tincidunt, dolor vel elementum mattis, ante lorem laoreet massa, eu facilisis sem sem at sapien.

<!--more-->

Sed tempor consequat magna. Duis dictum tincidunt nisl quis ultrices. Donec sollicitudin magna eu nunc aliquet luctus. Vestibulum hendrerit risus neque, eu mollis lectus condimentum non. Nullam nunc dui, gravida at ipsum nec, ultricies aliquet sapien. Vivamus non commodo mi, at ultricies nisl. Nam nec tortor egestas, aliquet lorem vel, semper turpis. In posuere faucibus arcu, sit amet ultricies neque eleifend eu. Donec placerat id elit eu sollicitudin. Phasellus vitae erat suscipit leo euismod faucibus eu sit amet dui. Etiam iaculis nec lectus fringilla sodales.',
  'post_title' => 'Online Learning, Support and Advice',
  'post_excerpt' => '',
  'post_name' => 'online-learning-support-and-advice',
  'post_modified' => '2017-08-21 06:56:45',
  'post_modified_gmt' => '2017-08-21 06:56:45',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=200',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'education, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 203,
  'post_date' => '2013-08-16 07:22:11',
  'post_date_gmt' => '2013-08-16 07:22:11',
  'post_content' => 'Quisque vehicula molestie turpis dapibus sodales. Sed imperdiet sollicitudin nibh. Quisque fermentum, elit ut pulvinar aliquam, est eros tincidunt diam, ut vehicula eros velit nec massa. Vestibulum non nisi et quam scelerisque interdum in a quam. Phasellus mollis ornare libero sit amet rhoncus. Curabitur non tellus id massa laoreet mollis vitae id sapien. Maecenas euismod nec arcu ac pretium. Cras non vehicula augue. Nulla volutpat sem nisl. Proin euismod sapien ut dignissim mattis. Nulla orci augue, feugiat vitae risus vitae, venenatis vulputate turpis. Sed sed dui porta, varius odio id, pretium erat. Nam purus enim, cursus quis iaculis id, consectetur ut ante. Duis molestie pretium metus, nec dictum neque ultricies ac.

<!--more-->Praesent orci arcu, pellentesque egestas sagittis id, aliquam et nibh. Fusce tempor massa libero, commodo suscipit sem rutrum at. Proin eu erat diam. Phasellus hendrerit lectus id justo tempus blandit. Aenean ornare volutpat lorem, vel pretium dolor mattis vel. Nulla orci augue, feugiat vitae risus vitae, venenatis vulputate turpis. Sed sed dui porta, varius odio id, pretium erat. Nam purus enim, cursus quis iaculis id, consectetur ut ante. Duis molestie pretium metus, nec dictum neque ultricies ac.',
  'post_title' => 'Winter Wedding Dress Show',
  'post_excerpt' => '',
  'post_name' => 'winter-wedding-dress-show',
  'post_modified' => '2017-08-21 06:56:43',
  'post_modified_gmt' => '2017-08-21 06:56:43',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=203',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'fashion, life',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2204,
  'post_date' => '2013-08-21 04:25:54',
  'post_date_gmt' => '2013-08-21 04:25:54',
  'post_content' => 'Vestibulum luctus auctor orci ac facilisis. Aliquam laoreet sed neque sed elementum. Quisque quis lacus at lorem luctus dapibus id at lacus. Phasellus nec interdum ante. Nullam a augue velit. Morbi vel urna pulvinar, cursus arcu vel, iaculis massa. Integer posuere tincidunt mi sed aliquet. Aliquam sollicitudin ultricies mi a rhoncus. Etiam urna purus, ultrices eu erat a, fringilla pellentesque tellus.Vestibulum luctus auctor orci ac facilisis. Aliquam laoreet sed neque sed elementum. Quisque quis lacus at lorem luctus dapibus id at lacus. Phasellus nec interdum ante. Nullam a augue velit. Morbi vel urna pulvinar, cursus arcu vel, iaculis massa. Integer posuere tincidunt mi sed aliquet. Aliquam sollicitudin ultricies mi a rhoncus. Etiam urna purus, ultrices eu erat a, fringilla pellentesque tellus. Morbi suscipit sapien vitae mi sollicitudin cursus.

<!--more-->Aliquam viverra velit id tellus volutpat, in facilisis quam commodo. Pellentesque mattis, lorem quis congue vulputate, libero ante viverra massa, vel imperdiet tellus neque eu eros. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Maecenas tempus viverra massa, sed ultricies justo interdum sed.',
  'post_title' => 'Google Launches Chrome New Version',
  'post_excerpt' => '',
  'post_name' => 'google-launches-chrome-new-version',
  'post_modified' => '2017-08-21 06:56:41',
  'post_modified_gmt' => '2017-08-21 06:56:41',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=2204',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'technology',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2207,
  'post_date' => '2013-08-21 04:26:52',
  'post_date_gmt' => '2013-08-21 04:26:52',
  'post_content' => 'Maecenas facilisis mauris adipiscing quam porta scelerisque. Vestibulum luctus auctor orci ac facilisis. Aliquam laoreet sed neque sed elementum. Quisque quis lacus at lorem luctus dapibus id at lacus. Phasellus nec interdum ante. Nullam a augue velit. Morbi vel urna pulvinar, cursus arcu vel, iaculis massa. Integer posuere tincidunt mi sed aliquet. Aliquam sollicitudin ultricies mi a rhoncus. Etiam urna purus, ultrices eu erat a, fringilla pellentesque tellus. Vestibulum luctus auctor orci ac facilisis. Aliquam laoreet sed neque sed elementum. Quisque quis lacus at lorem luctus dapibus id at lacus. Phasellus nec interdum ante. Nullam a augue velit. Morbi vel urna pulvinar, cursus arcu vel, iaculis massa. Integer posuere tincidunt mi sed aliquet. Aliquam sollicitudin ultricies mi a rhoncus. Etiam urna purus, ultrices eu erat a, fringilla pellentesque tellus.

<!--more-->Vestibulum luctus auctor orci ac facilisis. Aliquam laoreet sed neque sed elementum. Quisque quis lacus at lorem luctus dapibus id at lacus. Phasellus nec interdum ante. Nullam a augue velit. Morbi vel urna pulvinar, cursus arcu vel, iaculis massa. Integer posuere tincidunt mi sed aliquet. Aliquam sollicitudin ultricies mi a rhoncus. Etiam urna purus, ultrices eu erat a, fringilla pellentesque tellus.',
  'post_title' => 'MacBook Air review',
  'post_excerpt' => '',
  'post_name' => 'macbook-air-review',
  'post_modified' => '2017-08-21 06:56:39',
  'post_modified_gmt' => '2017-08-21 06:56:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=2207',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'technology',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2224,
  'post_date' => '2013-08-21 23:40:00',
  'post_date_gmt' => '2013-08-21 23:40:00',
  'post_content' => 'Maecenas facilisis mauris adipiscing quam porta scelerisque. Vestibulum luctus auctor orci ac facilisis. Aliquam laoreet sed neque sed elementum. Quisque quis lacus at lorem luctus dapibus id at lacus. Phasellus nec interdum ante. Nullam a augue velit. Morbi vel urna pulvinar, cursus arcu vel, iaculis massa. Integer posuere tincidunt mi sed aliquet.Maecenas facilisis mauris adipiscing quam porta scelerisque. Vestibulum luctus auctor orci ac facilisis. Aliquam laoreet sed neque sed elementum. Quisque quis lacus at lorem luctus dapibus id at lacus. Phasellus nec interdum ante. Nullam a augue velit. Morbi vel urna pulvinar, cursus arcu vel, iaculis massa. Integer posuere tincidunt mi sed aliquet.

<!--more-->

Themify Builder, a drag and drop framework for WordPress that allows you to create any kind of layouts by dragging and dropping the content blocks on the frontend. Completely responsive that works on desktop, tablet, and mobile devices.

All Themify WordPress themes now include the ability to create designs for your website by dragging and dropping. Build beautiful, responsive layouts that work for desktop, tablets, and mobile using our intuitive "what you see is what you get" drag &amp; drop framework with live edits and previews.',
  'post_title' => 'Themify Builder',
  'post_excerpt' => '',
  'post_name' => 'themify-builder',
  'post_modified' => '2017-08-21 06:56:36',
  'post_modified_gmt' => '2017-08-21 06:56:36',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?p=2224',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'video_url' => 'https://www.youtube.com/watch?v=hHw6VKKjcpQ',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'category' => 'video',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2424,
  'post_date' => '2013-08-15 04:40:38',
  'post_date_gmt' => '2013-08-15 04:40:38',
  'post_content' => 'Nullam facilisis lectus diam, in aliquet mi adipiscing quis. Suspendisse tincidunt semper nulla, ut feugiat dui elementum id. Etiam egestas semper purus, eget tempus nibh mollis nec. In iaculis turpis risus, vel bibendum purus tristique a. Pellentesque ligula turpis, aliquam sit amet sodales a, dictum vel nisi. Etiam in purus aliquam, gravida elit ut, fringilla neque. Praesent in massa purus. Suspendisse ac est non arcu mattis lobortis id dictum metus. Mauris odio eros, malesuada ut ipsum eu, malesuada blandit nunc. Aliquam eu nisl dui. Quisque vehicula porta dolor, volutpat mollis sem egestas in.',
  'post_title' => 'About',
  'post_excerpt' => '',
  'post_name' => 'about',
  'post_modified' => '2017-08-21 07:00:15',
  'post_modified_gmt' => '2017-08-21 07:00:15',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?page_id=2',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 84,
  'post_date' => '2013-08-15 07:22:25',
  'post_date_gmt' => '2013-08-15 07:22:25',
  'post_content' => '',
  'post_title' => 'Home',
  'post_excerpt' => '',
  'post_name' => 'home',
  'post_modified' => '2017-08-21 07:00:18',
  'post_modified_gmt' => '2017-08-21 07:00:18',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?page_id=84',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar2',
    'content_width' => 'default_width',
    'hide_page_title' => 'yes',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"slider\\",\\"mod_settings\\":{\\"layout_display_slider\\":\\"blog\\",\\"blog_category_slider\\":\\"world|multiple\\",\\"slider_category_slider\\":\\"|single\\",\\"portfolio_category_slider\\":\\"|single\\",\\"testimonial_category_slider\\":\\"|single\\",\\"posts_per_page_slider\\":\\"3\\",\\"order_slider\\":\\"desc\\",\\"orderby_slider\\":\\"date\\",\\"display_slider\\":\\"none\\",\\"hide_post_title_slider\\":\\"no\\",\\"unlink_post_title_slider\\":\\"no\\",\\"hide_feat_img_slider\\":\\"no\\",\\"unlink_feat_img_slider\\":\\"no\\",\\"layout_slider\\":\\"slider-overlay\\",\\"img_w_slider\\":\\"520\\",\\"img_h_slider\\":\\"340\\",\\"visible_opt_slider\\":\\"1\\",\\"auto_scroll_opt_slider\\":\\"4\\",\\"scroll_opt_slider\\":\\"1\\",\\"speed_opt_slider\\":\\"normal\\",\\"effect_slider\\":\\"scroll\\",\\"pause_on_hover_slider\\":\\"resume\\",\\"wrap_slider\\":\\"yes\\",\\"show_nav_slider\\":\\"yes\\",\\"show_arrow_slider\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"tab\\",\\"mod_settings\\":{\\"layout_tab\\":\\"minimal\\",\\"color_tab\\":\\"default\\",\\"tab_appearance_tab\\":\\"|\\",\\"tab_content_tab\\":[{\\"title_tab\\":\\"Sport\\",\\"text_tab\\":\\"<p>[list_posts category=\\\\\\\\\\\'sport\\\\\\\\\\\' style=\\\\\\\\\\\'grid3\\\\\\\\\\\' limit=\\\\\\\\\\\'3\\\\\\\\\\\' post_date=\\\\\\\\\\\'no\\\\\\\\\\\']</p>\\"},{\\"title_tab\\":\\"Entertainment \\",\\"text_tab\\":\\"<p>[list_posts category=\\\\\\\\\\\'entertainment\\\\\\\\\\\' style=\\\\\\\\\\\'grid3\\\\\\\\\\\' limit=\\\\\\\\\\\'3\\\\\\\\\\\' post_date=\\\\\\\\\\\'no\\\\\\\\\\\']</p>\\"},{\\"title_tab\\":\\"Technology\\",\\"text_tab\\":\\"<p>[list_posts category=\\\\\\\\\\\'technology\\\\\\\\\\\' style=\\\\\\\\\\\'grid3\\\\\\\\\\\' limit=\\\\\\\\\\\'3\\\\\\\\\\\' post_date=\\\\\\\\\\\'no\\\\\\\\\\\']</p>\\"}],\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"0\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Education\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"education|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"245\\",\\"img_height_post\\":\\"156\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Food\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"food|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"245\\",\\"img_height_post\\":\\"156\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"education|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"offset_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"65\\",\\"img_height_post\\":\\"65\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"food|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"offset_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"65\\",\\"img_height_post\\":\\"65\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Videos\\",\\"layout_post\\":\\"grid2\\",\\"category_post\\":\\"video|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"245\\",\\"img_height_post\\":\\"156\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"animation_effect\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_repeat\\":\\"\\",\\"background_color\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top\\":\\"40\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\"}},{\\"row_order\\":\\"5\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Entertainment\\",\\"layout_post\\":\\"grid3\\",\\"category_post\\":\\"entertainment|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"245\\",\\"img_height_post\\":\\"156\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]}],\\"styling\\":{\\"row_width\\":\\"\\",\\"row_height\\":\\"\\",\\"animation_effect\\":\\"\\",\\"background_type\\":\\"image\\",\\"background_slider\\":\\"\\",\\"background_slider_mode\\":\\"\\",\\"background_video\\":\\"\\",\\"background_image\\":\\"\\",\\"background_repeat\\":\\"\\",\\"background_color\\":\\"\\",\\"font_family\\":\\"default\\",\\"font_color\\":\\"\\",\\"font_size\\":\\"\\",\\"font_size_unit\\":\\"\\",\\"line_height\\":\\"\\",\\"line_height_unit\\":\\"\\",\\"text_align\\":\\"\\",\\"link_color\\":\\"\\",\\"text_decoration\\":\\"\\",\\"padding_top\\":\\"\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right\\":\\"\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom\\":\\"\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left\\":\\"\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top\\":\\"20\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right\\":\\"\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom\\":\\"\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left\\":\\"\\",\\"margin_left_unit\\":\\"px\\",\\"border_top_color\\":\\"\\",\\"border_top_width\\":\\"\\",\\"border_top_style\\":\\"\\",\\"border_right_color\\":\\"\\",\\"border_right_width\\":\\"\\",\\"border_right_style\\":\\"\\",\\"border_bottom_color\\":\\"\\",\\"border_bottom_width\\":\\"\\",\\"border_bottom_style\\":\\"\\",\\"border_left_color\\":\\"\\",\\"border_left_width\\":\\"\\",\\"border_left_style\\":\\"\\",\\"custom_css_row\\":\\"\\",\\"row_anchor\\":\\"\\"}},{\\"row_order\\":\\"6\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 92,
  'post_date' => '2013-08-15 14:20:50',
  'post_date_gmt' => '2013-08-15 14:20:50',
  'post_content' => 'Download this contact plugin: <a href="http://wordpress.org/extend/plugins/contact-form-7">Contact Form 7</a>.

[contact-form-7 id="91" title="Contact"]',
  'post_title' => 'Contact',
  'post_excerpt' => '',
  'post_name' => 'contact',
  'post_modified' => '2017-08-21 07:00:16',
  'post_modified_gmt' => '2017-08-21 07:00:16',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine/?page_id=92',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 408,
  'post_date' => '2010-10-06 21:16:00',
  'post_date_gmt' => '2010-10-07 01:16:00',
  'post_content' => '<h3>Buttons</h3>
[button style="orange" link="https://themify.me"]Orange[/button] [button style="blue"]Blue[/button] [button style="pink"]Pink[/button] [button style="green"]Green[/button] [button style="red"]Red[/button] [button style="black"]Black[/button]

[hr]

[button style="small"]Small[/button]

[button]Default[/button]

[button style="large"]Large[/button] [button style="xlarge"]Xlarge[/button]

[hr]

[button style="orange small"]Orange Small[/button] [button style="blue"]Blue[/button] [button style="green large"]Green Large[/button] [button style="red xlarge"]Red Xlarge[/button]

[hr]
<h3>Columns</h3>
[col grid="2-1 first"]
<h4>col 2-1</h4>
Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Nam risus velit, rhoncus eg.

[/col]

[col grid="2-1"]
<h4>col 2-1</h4>
Curabitur vel risus eros, sed eleifend arcu. Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis massa.

[/col]

[hr]

[col grid="3-1 first"]
<h4>col 3-1</h4>
Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Nam risus velit, rhoncus eg.

[/col]

[col grid="3-1"]
<h4>col 3-1</h4>
Curabitur vel risus eros, sed eleifend arcu. Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis massa.

[/col]

[col grid="3-1"]
<h4>col 3-1</h4>
Vivamus dignissim, ligula velt pretium leo, vel placerat ipsum risus luctus purus. Tos, sed eleifend arcu. Donec porttitor hendrerit.

[/col]

[hr]

[col grid="4-1 first"]
<h4>col 4-1</h4>
Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Nam risus velit, rhoncus eget co.

[/col]

[col grid="4-1"]
<h4>col 4-1</h4>
Curabitur vel risus eros, sed eleifend arcu. Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis mas.

[/col]

[col grid="4-1"]
<h4>col 4-1</h4>
Vivamus dignissim, ligula velt pretium leo, vel placerat ipsum risus luctus purus. Tos, sed eleifend arcu. Donec porttitor hendrerit diam.

[/col]

[col grid="4-1"]
<h4>col 4-1</h4>
Donec porttitor hendrerit diam et blandit. Curabitur vel risus eros, sed eleifend arcu. Curabitur vitae velit ligula, vitae lobortis mas.

[/col]

[hr]

[col grid="4-2 first"]
<h4>col 4-2</h4>
Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Nam risus velit, rhoncus eget cout rhoncus turpis augue vitae libero.

[/col]

[col grid="4-1"]
<h4>col 4-1</h4>
Curabitur vel risus eros, sed eleifend arcu. Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis mas.

[/col]

[col grid="4-1"]
<h4>col 4-1</h4>
Vivamus dignissim, ligula velt pretium leo, vel placerat ipsum risus luctus purus. Tos, sed eleifend arcu. Donec porttitor hendrerit diam.

[/col]
<h3>Horizontal Rules</h3>
[hr]

[hr color="pink"]

[hr color="red"]

[hr color="light-gray"]

[hr color="dark-gray"]

[hr color="black"]

[hr color="orange"]

[hr color="yellow"]

[hr color="white"]
<h3>Quote</h3>
[quote]Vivamus in risus non lacus vehicula vestibulum. In magna leo, malesuada eget pulvinar ut, pellentesque a arcu. Praesent rutrum feugiat nibh elementum posuere. Nulla volutpat porta enim vel consectetur. Etiam orci eros, blandit nec egestas eget, pharetra eget leo. Morbi lobortis adipiscing massa tincidunt dignissim. Nulla lobortis laoreet risus, tempor accumsan sem congue vitae. Cras laoreet hendrerit erat, id porttitor nunc blandit adipiscing. [/quote]
<h3>Map</h3>
[map address="Yonge St. and Eglinton Ave, Toronto, Ontario, Canada" width=100% height=400px]
<h3></h3>',
  'post_title' => 'Shortcodes',
  'post_excerpt' => '',
  'post_name' => 'shortcodes',
  'post_modified' => '2017-08-21 07:00:34',
  'post_modified_gmt' => '2017-08-21 07:00:34',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/wp-content/uploads/image-placeholder.jpg',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'default',
    'display_content' => 'none',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 409,
  'post_date' => '2010-10-08 11:15:14',
  'post_date_gmt' => '2010-10-08 15:15:14',
  'post_content' => '',
  'post_title' => 'Layouts',
  'post_excerpt' => '',
  'post_name' => 'layouts',
  'post_modified' => '2017-08-21 07:00:20',
  'post_modified_gmt' => '2017-08-21 07:00:20',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/wp-content/uploads/image-placeholder.jpg',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-thumb-image',
    'image_width' => '240',
    'image_height' => '160',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 542,
  'post_date' => '2011-04-03 00:52:10',
  'post_date_gmt' => '2011-04-03 00:52:10',
  'post_content' => '',
  'post_title' => 'Fullwidth',
  'post_excerpt' => '',
  'post_name' => 'fullwidth',
  'post_modified' => '2017-08-21 07:00:27',
  'post_modified_gmt' => '2017-08-21 07:00:27',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/wp-content/uploads/image-placeholder.jpg',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-1 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\" News\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"news|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"226\\",\\"img_height_post\\":\\"180\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Featured\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"featured|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"offset_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"content\\",\\"img_width_post\\":\\"500\\",\\"img_height_post\\":\\"300\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col4-1 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Technology\\",\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"technology|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"100\\",\\"img_height_post\\":\\"100\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}},{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Fashion\\",\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"fashion|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"100\\",\\"img_height_post\\":\\"100\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-1 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Business\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"business|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"312\\",\\"img_height_post\\":\\"200\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Entertainment\\",\\"layout_post\\":\\"grid2\\",\\"category_post\\":\\"entertainment|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"312\\",\\"img_height_post\\":\\"200\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\",\\"font_family\\":\\"default\\",\\"text_align_left\\":\\"left\\",\\"text_align_center\\":\\"center\\",\\"text_align_right\\":\\"right\\",\\"text_align_justify\\":\\"justify\\",\\"padding_top_unit\\":\\"px\\",\\"padding_right_unit\\":\\"px\\",\\"padding_bottom_unit\\":\\"px\\",\\"padding_left_unit\\":\\"px\\",\\"margin_top_unit\\":\\"px\\",\\"margin_right_unit\\":\\"px\\",\\"margin_bottom_unit\\":\\"px\\",\\"margin_left_unit\\":\\"px\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col4-1 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Sport\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"sport|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"312\\",\\"img_height_post\\":\\"200\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-1 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Food\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"food|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"226\\",\\"img_height_post\\":\\"150\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-1\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Entertainment\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"entertainment|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"226\\",\\"img_height_post\\":\\"150\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col4-1\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Fashion\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"fashion|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"226\\",\\"img_height_post\\":\\"150\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"3\\",\\"grid_class\\":\\"col4-1 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Technology\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"technology|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"226\\",\\"img_height_post\\":\\"150\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Video\\",\\"layout_post\\":\\"grid4\\",\\"category_post\\":\\"video|multiple\\",\\"post_per_page_post\\":\\"4\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"226\\",\\"img_height_post\\":\\"150\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Local\\",\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"local|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"60\\",\\"img_height_post\\":\\"60\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"World\\",\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"world|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"60\\",\\"img_height_post\\":\\"60\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col3-1 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Editors\\\\\\\\\\\' Picks\\",\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"featured|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"60\\",\\"img_height_post\\":\\"60\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"5\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 561,
  'post_date' => '2011-04-03 01:01:38',
  'post_date_gmt' => '2011-04-03 01:01:38',
  'post_content' => '',
  'post_title' => 'Sidebar Left',
  'post_excerpt' => '',
  'post_name' => 'sidebar-left',
  'post_modified' => '2017-08-21 07:00:29',
  'post_modified_gmt' => '2017-08-21 07:00:29',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/wp-content/uploads/image-placeholder.jpg',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1 sidebar-left',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"news|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"720\\",\\"img_height_post\\":\\"320\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"news|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"offset_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"338\\",\\"img_height_post\\":\\"250\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"news|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"offset_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"65\\",\\"img_height_post\\":\\"65\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"mod_title_text\\":\\"News in Pictures\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2 first\\",\\"modules\\":[{\\"mod_name\\":\\"slider\\",\\"mod_settings\\":{\\"layout_display_slider\\":\\"blog\\",\\"blog_category_slider\\":\\"fashion|multiple\\",\\"slider_category_slider\\":\\"|single\\",\\"portfolio_category_slider\\":\\"|single\\",\\"posts_per_page_slider\\":\\"3\\",\\"display_slider\\":\\"none\\",\\"hide_post_title_slider\\":\\"yes\\",\\"layout_slider\\":\\"slider-default\\",\\"img_w_slider\\":\\"327\\",\\"img_h_slider\\":\\"207\\",\\"visible_opt_slider\\":\\"1\\",\\"auto_scroll_opt_slider\\":\\"4\\",\\"scroll_opt_slider\\":\\"1\\",\\"speed_opt_slider\\":\\"normal\\",\\"effect_slider\\":\\"scroll\\",\\"wrap_slider\\":\\"yes\\",\\"show_nav_slider\\":\\"no\\",\\"show_arrow_slider\\":\\"no\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2 last\\",\\"modules\\":[{\\"mod_name\\":\\"gallery\\",\\"mod_settings\\":{\\"shortcode_gallery\\":\\"[gallery ids=\\\\\\\\\\\'188,185,182,176,27,30\\\\\\\\\\\']\\",\\"thumb_w_gallery\\":\\"100\\",\\"thumb_h_gallery\\":\\"100\\",\\"appearance_gallery\\":\\"rounded\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Food\\",\\"layout_post\\":\\"grid3\\",\\"category_post\\":\\"food|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"259\\",\\"img_height_post\\":\\"160\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"5\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Thechnology\\",\\"layout_post\\":\\"grid4\\",\\"category_post\\":\\"technology|multiple\\",\\"post_per_page_post\\":\\"4\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"327\\",\\"img_height_post\\":\\"200\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"6\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 576,
  'post_date' => '2011-04-03 01:09:14',
  'post_date_gmt' => '2011-04-03 01:09:14',
  'post_content' => '',
  'post_title' => 'Sidebar Right',
  'post_excerpt' => '',
  'post_name' => 'sidebar-right',
  'post_modified' => '2017-08-21 07:00:34',
  'post_modified_gmt' => '2017-08-21 07:00:34',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/wp-content/uploads/image-placeholder.jpg',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"slider\\",\\"mod_settings\\":{\\"layout_display_slider\\":\\"blog\\",\\"blog_category_slider\\":\\"breaking-news|multiple\\",\\"slider_category_slider\\":\\"|single\\",\\"portfolio_category_slider\\":\\"|single\\",\\"posts_per_page_slider\\":\\"5\\",\\"display_slider\\":\\"none\\",\\"layout_slider\\":\\"slider-default\\",\\"img_w_slider\\":\\"720\\",\\"img_h_slider\\":\\"320\\",\\"visible_opt_slider\\":\\"1\\",\\"auto_scroll_opt_slider\\":\\"4\\",\\"scroll_opt_slider\\":\\"1\\",\\"speed_opt_slider\\":\\"normal\\",\\"effect_slider\\":\\"scroll\\",\\"wrap_slider\\":\\"yes\\",\\"show_nav_slider\\":\\"yes\\",\\"show_arrow_slider\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Editors\\\\\\\\\\\' Picks\\",\\"layout_post\\":\\"grid3\\",\\"category_post\\":\\"featured|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"hide_feat_img_post\\":\\"yes\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"World\\",\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"world|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Local\\",\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"local|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"tab\\",\\"mod_settings\\":{\\"layout_tab\\":\\"minimal\\",\\"color_tab\\":\\"default\\",\\"tab_content_tab\\":[{\\"title_tab\\":\\"Fashion\\",\\"text_tab\\":\\"<p>[list_posts category=\\\\\\\\\\\'8\\\\\\\\\\\' style=\\\\\\\\\\\'grid3\\\\\\\\\\\' limit=\\\\\\\\\\\'3\\\\\\\\\\\' post_date=\\\\\\\\\\\'no\\\\\\\\\\\' image_w=\\\\\\\\\\\'216\\\\\\\\\\\' image_h=\\\\\\\\\\\'150\\\\\\\\\\\']</p>\\"},{\\"title_tab\\":\\"Education\\",\\"text_tab\\":\\"<p>[list_posts category=\\\\\\\\\\\'6\\\\\\\\\\\' style=\\\\\\\\\\\'grid3\\\\\\\\\\\' limit=\\\\\\\\\\\'3\\\\\\\\\\\' post_date=\\\\\\\\\\\'no\\\\\\\\\\\' image_w=\\\\\\\\\\\'216\\\\\\\\\\\' image_h=\\\\\\\\\\\'150\\\\\\\\\\\']</p>\\"},{\\"title_tab\\":\\"Food\\",\\"text_tab\\":\\"<p>[list_posts category=\\\\\\\\\\\'7\\\\\\\\\\\' style=\\\\\\\\\\\'grid3\\\\\\\\\\\' limit=\\\\\\\\\\\'3\\\\\\\\\\\' post_date=\\\\\\\\\\\'no\\\\\\\\\\\' image_w=\\\\\\\\\\\'216\\\\\\\\\\\' image_h=\\\\\\\\\\\'150\\\\\\\\\\\']</p>\\"}]}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Sports\\",\\"layout_post\\":\\"grid4\\",\\"category_post\\":\\"sport|multiple\\",\\"post_per_page_post\\":\\"4\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"153\\",\\"img_height_post\\":\\"100\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"5\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2157,
  'post_date' => '2013-08-19 23:00:02',
  'post_date_gmt' => '2013-08-19 23:00:02',
  'post_content' => '',
  'post_title' => '2 Sidebars',
  'post_excerpt' => '',
  'post_name' => '2-sidebars',
  'post_modified' => '2017-08-21 07:00:21',
  'post_modified_gmt' => '2017-08-21 07:00:21',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine/?page_id=2157',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar2',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2161,
  'post_date' => '2013-08-19 23:32:38',
  'post_date_gmt' => '2013-08-19 23:32:38',
  'post_content' => '',
  'post_title' => '2 Sidebars with Narrow Left',
  'post_excerpt' => '',
  'post_name' => '2-sidebars-with-narrow-left',
  'post_modified' => '2017-08-21 07:00:24',
  'post_modified_gmt' => '2017-08-21 07:00:24',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine/?page_id=2161',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar2 content-right',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"slider\\",\\"mod_settings\\":{\\"layout_display_slider\\":\\"blog\\",\\"blog_category_slider\\":\\"news|multiple\\",\\"slider_category_slider\\":\\"|single\\",\\"portfolio_category_slider\\":\\"|single\\",\\"posts_per_page_slider\\":\\"3\\",\\"display_slider\\":\\"none\\",\\"unlink_post_title_slider\\":\\"no\\",\\"hide_feat_img_slider\\":\\"no\\",\\"unlink_feat_img_slider\\":\\"no\\",\\"layout_slider\\":\\"slider-caption-overlay\\",\\"img_w_slider\\":\\"520\\",\\"img_h_slider\\":\\"340\\",\\"visible_opt_slider\\":\\"1\\",\\"auto_scroll_opt_slider\\":\\"4\\",\\"scroll_opt_slider\\":\\"1\\",\\"speed_opt_slider\\":\\"normal\\",\\"effect_slider\\":\\"scroll\\",\\"wrap_slider\\":\\"yes\\",\\"show_nav_slider\\":\\"yes\\",\\"show_arrow_slider\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Technology\\",\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"technology|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"200\\",\\"img_height_post\\":\\"160\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-2 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Food\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"food|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"340\\",\\"img_height_post\\":\\"191\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Fashion\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"fashion|multiple\\",\\"post_per_page_post\\":\\"3\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"152\\",\\"img_height_post\\":\\"100\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-2 first\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Video\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"video|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1 last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Sport\\",\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"sport|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"152\\",\\"img_height_post\\":\\"100\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"column_alignment\\":\\"\\",\\"styling\\":[]},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2163,
  'post_date' => '2013-08-19 23:33:04',
  'post_date_gmt' => '2013-08-19 23:33:04',
  'post_content' => '',
  'post_title' => '2 Sidebars with Narrow Right',
  'post_excerpt' => '',
  'post_name' => '2-sidebars-with-narrow-right',
  'post_modified' => '2017-08-21 07:00:26',
  'post_modified_gmt' => '2017-08-21 07:00:26',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine/?page_id=2163',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar2 content-left',
    'content_width' => 'default_width',
    'hide_page_title' => 'default',
    'order' => 'desc',
    'orderby' => 'date',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"layout_post\\":\\"list-post\\",\\"category_post\\":\\"news|multiple\\",\\"post_per_page_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"518\\",\\"img_height_post\\":\\"300\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"layout_post\\":\\"list-thumb-image\\",\\"category_post\\":\\"news|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"offset_post\\":\\"1\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"img_width_post\\":\\"200\\",\\"img_height_post\\":\\"160\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Life\\",\\"layout_post\\":\\"grid3\\",\\"category_post\\":\\"life|multiple\\",\\"post_per_page_post\\":\\"6\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"155\\",\\"img_height_post\\":\\"105\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"mod_title_post\\":\\"Video\\",\\"layout_post\\":\\"grid2\\",\\"category_post\\":\\"video|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"none\\",\\"img_width_post\\":\\"245\\",\\"img_height_post\\":\\"156\\",\\"hide_post_date_post\\":\\"yes\\",\\"hide_post_meta_post\\":\\"yes\\",\\"hide_page_nav_post\\":\\"yes\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2401,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2401',
  'post_modified' => '2016-05-23 22:13:44',
  'post_modified_gmt' => '2016-05-23 22:13:44',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2401/',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '7',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => 'mega',
      1 => 'icon-bullhorn',
    ),
    '_themify_mega_menu_item' => '1',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2428,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2428',
  'post_modified' => '2015-06-01 21:19:57',
  'post_modified_gmt' => '2015-06-01 21:19:57',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2428/',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '2424',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'top-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2430,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2430',
  'post_modified' => '2015-03-10 21:32:32',
  'post_modified_gmt' => '2015-03-10 21:32:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2430/',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '84',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2403,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2403',
  'post_modified' => '2016-05-23 22:13:44',
  'post_modified_gmt' => '2016-05-23 22:13:44',
  'post_content_filtered' => '',
  'post_parent' => 7,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2403/',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2401',
    '_menu_item_object_id' => '15',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => 'icon-group',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2407,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2407',
  'post_modified' => '2015-03-10 21:32:32',
  'post_modified_gmt' => '2015-03-10 21:32:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2407/',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '7',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2427,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2427',
  'post_modified' => '2015-06-01 21:19:57',
  'post_modified_gmt' => '2015-06-01 21:19:57',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2427/',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '92',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'top-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2402,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2402',
  'post_modified' => '2016-05-23 22:13:44',
  'post_modified_gmt' => '2016-05-23 22:13:44',
  'post_content_filtered' => '',
  'post_parent' => 7,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2402/',
  'menu_order' => 3,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2401',
    '_menu_item_object_id' => '11',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => 'icon-globe',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2406,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2406',
  'post_modified' => '2015-03-10 21:32:32',
  'post_modified_gmt' => '2015-03-10 21:32:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2406/',
  'menu_order' => 3,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '6',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2397,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2397',
  'post_modified' => '2016-05-23 22:13:45',
  'post_modified_gmt' => '2016-05-23 22:13:45',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2397/',
  'menu_order' => 4,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '6',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => 'mega',
    ),
    '_themify_mega_menu_item' => '1',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2409,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2409',
  'post_modified' => '2015-03-10 21:32:32',
  'post_modified_gmt' => '2015-03-10 21:32:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2409/',
  'menu_order' => 4,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '8',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2399,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2399',
  'post_modified' => '2016-05-23 22:13:45',
  'post_modified_gmt' => '2016-05-23 22:13:45',
  'post_content_filtered' => '',
  'post_parent' => 6,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2399/',
  'menu_order' => 5,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2397',
    '_menu_item_object_id' => '14',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2408,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2408',
  'post_modified' => '2015-03-10 21:32:32',
  'post_modified_gmt' => '2015-03-10 21:32:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2408/',
  'menu_order' => 5,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '3',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2400,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2400',
  'post_modified' => '2016-05-23 22:13:45',
  'post_modified_gmt' => '2016-05-23 22:13:45',
  'post_content_filtered' => '',
  'post_parent' => 6,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2400/',
  'menu_order' => 6,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2397',
    '_menu_item_object_id' => '13',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2431,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2431',
  'post_modified' => '2015-03-10 21:32:32',
  'post_modified_gmt' => '2015-03-10 21:32:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2431/',
  'menu_order' => 6,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '2424',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2412,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2412',
  'post_modified' => '2016-05-23 22:13:45',
  'post_modified_gmt' => '2016-05-23 22:13:45',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2412/',
  'menu_order' => 7,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2397',
    '_menu_item_object_id' => '4',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2429,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2429',
  'post_modified' => '2015-03-10 21:32:32',
  'post_modified_gmt' => '2015-03-10 21:32:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2429/',
  'menu_order' => 7,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '92',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2398,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2398',
  'post_modified' => '2016-05-23 22:13:45',
  'post_modified_gmt' => '2016-05-23 22:13:45',
  'post_content_filtered' => '',
  'post_parent' => 6,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2398/',
  'menu_order' => 8,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2397',
    '_menu_item_object_id' => '12',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2411,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2411',
  'post_modified' => '2016-05-23 22:13:46',
  'post_modified_gmt' => '2016-05-23 22:13:46',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2411/',
  'menu_order' => 9,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '9',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => 'icon-laptop',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2449,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2449',
  'post_modified' => '2016-05-23 22:13:46',
  'post_modified_gmt' => '2016-05-23 22:13:46',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2449/',
  'menu_order' => 10,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '409',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2454,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2454',
  'post_modified' => '2016-05-23 22:13:46',
  'post_modified_gmt' => '2016-05-23 22:13:46',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2454/',
  'menu_order' => 11,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2449',
    '_menu_item_object_id' => '542',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2453,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2453',
  'post_modified' => '2016-05-23 22:13:46',
  'post_modified_gmt' => '2016-05-23 22:13:46',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2453/',
  'menu_order' => 12,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2449',
    '_menu_item_object_id' => '561',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2452,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2452',
  'post_modified' => '2016-05-23 22:13:46',
  'post_modified_gmt' => '2016-05-23 22:13:46',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2452/',
  'menu_order' => 13,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2449',
    '_menu_item_object_id' => '576',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2451,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2451',
  'post_modified' => '2016-05-23 22:13:47',
  'post_modified_gmt' => '2016-05-23 22:13:47',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2451/',
  'menu_order' => 14,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2449',
    '_menu_item_object_id' => '2161',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2450,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2450',
  'post_modified' => '2016-05-23 22:13:47',
  'post_modified_gmt' => '2016-05-23 22:13:47',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2450/',
  'menu_order' => 15,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2449',
    '_menu_item_object_id' => '2163',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2413,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => '',
  'post_title' => 'More',
  'post_excerpt' => '',
  'post_name' => 'more',
  'post_modified' => '2016-05-23 22:13:47',
  'post_modified_gmt' => '2016-05-23 22:13:47',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/more/',
  'menu_order' => 16,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'custom',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '2413',
    '_menu_item_object' => 'custom',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
    '_menu_item_url' => '#',
    '_themify_mega_menu_column' => '1',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2433,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2433',
  'post_modified' => '2016-05-23 22:13:48',
  'post_modified_gmt' => '2016-05-23 22:13:48',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2433/',
  'menu_order' => 17,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2413',
    '_menu_item_object_id' => '409',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2434,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2434',
  'post_modified' => '2016-05-23 22:13:48',
  'post_modified_gmt' => '2016-05-23 22:13:48',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2434/',
  'menu_order' => 18,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2433',
    '_menu_item_object_id' => '542',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2435,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2435',
  'post_modified' => '2016-05-23 22:13:48',
  'post_modified_gmt' => '2016-05-23 22:13:48',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2435/',
  'menu_order' => 19,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2433',
    '_menu_item_object_id' => '561',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2436,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2436',
  'post_modified' => '2016-05-23 22:13:49',
  'post_modified_gmt' => '2016-05-23 22:13:49',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2436/',
  'menu_order' => 20,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2433',
    '_menu_item_object_id' => '576',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2447,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2447',
  'post_modified' => '2016-05-23 22:13:49',
  'post_modified_gmt' => '2016-05-23 22:13:49',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2447/',
  'menu_order' => 21,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2433',
    '_menu_item_object_id' => '2161',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2446,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2446',
  'post_modified' => '2016-05-23 22:13:49',
  'post_modified_gmt' => '2016-05-23 22:13:49',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2446/',
  'menu_order' => 22,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2433',
    '_menu_item_object_id' => '2163',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2417,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => '',
  'post_title' => 'Categories',
  'post_excerpt' => '',
  'post_name' => 'categories',
  'post_modified' => '2016-05-23 22:13:50',
  'post_modified_gmt' => '2016-05-23 22:13:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/categories/',
  'menu_order' => 23,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'custom',
    '_menu_item_menu_item_parent' => '2413',
    '_menu_item_object_id' => '2417',
    '_menu_item_object' => 'custom',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
    '_menu_item_url' => '#',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2405,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2405',
  'post_modified' => '2016-05-23 22:13:50',
  'post_modified_gmt' => '2016-05-23 22:13:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2405/',
  'menu_order' => 24,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2417',
    '_menu_item_object_id' => '8',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2415,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2415',
  'post_modified' => '2016-05-23 22:13:50',
  'post_modified_gmt' => '2016-05-23 22:13:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2415/',
  'menu_order' => 25,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2417',
    '_menu_item_object_id' => '2',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2416,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2416',
  'post_modified' => '2016-05-23 22:13:50',
  'post_modified_gmt' => '2016-05-23 22:13:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2416/',
  'menu_order' => 26,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2417',
    '_menu_item_object_id' => '5',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2404,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2404',
  'post_modified' => '2016-05-23 22:13:51',
  'post_modified_gmt' => '2016-05-23 22:13:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2404/',
  'menu_order' => 27,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2417',
    '_menu_item_object_id' => '3',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2410,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2410',
  'post_modified' => '2016-05-23 22:13:51',
  'post_modified_gmt' => '2016-05-23 22:13:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2410/',
  'menu_order' => 28,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'taxonomy',
    '_menu_item_menu_item_parent' => '2417',
    '_menu_item_object_id' => '10',
    '_menu_item_object' => 'category',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2418,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => '',
  'post_title' => 'Social',
  'post_excerpt' => '',
  'post_name' => 'social',
  'post_modified' => '2016-05-23 22:13:51',
  'post_modified_gmt' => '2016-05-23 22:13:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/social/',
  'menu_order' => 29,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'custom',
    '_menu_item_menu_item_parent' => '2413',
    '_menu_item_object_id' => '2418',
    '_menu_item_object' => 'custom',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
    '_menu_item_url' => '#',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2419,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => '',
  'post_title' => 'Twitter',
  'post_excerpt' => '',
  'post_name' => 'twitter',
  'post_modified' => '2016-05-23 22:13:51',
  'post_modified_gmt' => '2016-05-23 22:13:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/twitter/',
  'menu_order' => 30,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'custom',
    '_menu_item_menu_item_parent' => '2418',
    '_menu_item_object_id' => '2419',
    '_menu_item_object' => 'custom',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
    '_menu_item_url' => 'https://twitter.com/themify',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2420,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => '',
  'post_title' => 'Facebook',
  'post_excerpt' => '',
  'post_name' => 'facebook',
  'post_modified' => '2016-05-23 22:13:52',
  'post_modified_gmt' => '2016-05-23 22:13:52',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/facebook/',
  'menu_order' => 31,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'custom',
    '_menu_item_menu_item_parent' => '2418',
    '_menu_item_object_id' => '2420',
    '_menu_item_object' => 'custom',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
    '_menu_item_url' => 'https://www.facebook.com/themify',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2421,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => '',
  'post_title' => 'YouTube',
  'post_excerpt' => '',
  'post_name' => 'youtube',
  'post_modified' => '2016-05-23 22:13:52',
  'post_modified_gmt' => '2016-05-23 22:13:52',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/youtube/',
  'menu_order' => 32,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'custom',
    '_menu_item_menu_item_parent' => '2418',
    '_menu_item_object_id' => '2421',
    '_menu_item_object' => 'custom',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
    '_menu_item_url' => 'http://www.youtube.com/user/themifyme',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2422,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => '',
  'post_title' => 'Google +',
  'post_excerpt' => '',
  'post_name' => 'google',
  'post_modified' => '2016-05-23 22:13:52',
  'post_modified_gmt' => '2016-05-23 22:13:52',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/google/',
  'menu_order' => 33,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'custom',
    '_menu_item_menu_item_parent' => '2418',
    '_menu_item_object_id' => '2422',
    '_menu_item_object' => 'custom',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
    '_menu_item_url' => 'https://plus.google.com',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2423,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => '',
  'post_title' => 'Pinterest',
  'post_excerpt' => '',
  'post_name' => 'pinterest',
  'post_modified' => '2016-05-23 22:13:52',
  'post_modified_gmt' => '2016-05-23 22:13:52',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/pinterest/',
  'menu_order' => 34,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'custom',
    '_menu_item_menu_item_parent' => '2418',
    '_menu_item_object_id' => '2423',
    '_menu_item_object' => 'custom',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
    '_menu_item_url' => 'http://pinterest.com/',
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2414,
  'post_date' => '2014-10-10 16:39:32',
  'post_date_gmt' => '2014-10-10 16:39:32',
  'post_content' => '',
  'post_title' => 'Pages',
  'post_excerpt' => '',
  'post_name' => 'pages',
  'post_modified' => '2016-05-23 22:13:52',
  'post_modified_gmt' => '2016-05-23 22:13:52',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/pages/',
  'menu_order' => 35,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'custom',
    '_menu_item_menu_item_parent' => '2413',
    '_menu_item_object_id' => '2414',
    '_menu_item_object' => 'custom',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2437,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2437',
  'post_modified' => '2016-05-23 22:13:53',
  'post_modified_gmt' => '2016-05-23 22:13:53',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2437/',
  'menu_order' => 36,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2414',
    '_menu_item_object_id' => '408',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2432,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2432',
  'post_modified' => '2016-05-23 22:13:53',
  'post_modified_gmt' => '2016-05-23 22:13:53',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2432/',
  'menu_order' => 37,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2414',
    '_menu_item_object_id' => '2424',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2448,
  'post_date' => '2014-10-10 16:39:35',
  'post_date_gmt' => '2014-10-10 16:39:35',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2448',
  'post_modified' => '2016-05-23 22:13:53',
  'post_modified_gmt' => '2016-05-23 22:13:53',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/magazine2/2014/10/10/2448/',
  'menu_order' => 38,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2414',
    '_menu_item_object_id' => '92',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}


function themify_import_get_term_id_from_slug( $slug ) {
	$menu = get_term_by( "slug", $slug, "nav_menu" );
	return is_wp_error( $menu ) ? 0 : (int) $menu->term_id;
}

	$widgets = get_option( "widget_themify-feature-posts" );
$widgets[1002] = array (
  'title' => 'Featured Posts',
  'category' => '0',
  'show_count' => '2',
  'show_date' => 'on',
  'show_thumb' => 'on',
  'display' => 'none',
  'hide_title' => NULL,
  'thumb_width' => '50',
  'thumb_height' => '50',
  'excerpt_length' => '55',
  'orderby' => 'date',
  'order' => 'DESC',
);
update_option( "widget_themify-feature-posts", $widgets );

$widgets = get_option( "widget_themify-flickr" );
$widgets[1003] = array (
  'title' => 'Flickr',
  'username' => '52839779@N02',
  'show_count' => '15',
  'show_link' => NULL,
);
update_option( "widget_themify-flickr", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1004] = array (
  'title' => 'About',
  'text' => 'Welcome to <a href="#">Magazine</a> website, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Vestibulum adipiscing rutrum nulla, vitae interdum urna posuere in. ',
  'filter' => false,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1005] = array (
  'title' => '',
  'text' => '<a href="https://themify.me"><img src="https://themify.me/demo/themes/magazine/files/2014/10/ad-300x250.jpg" alt="sample image" /></a>',
  'filter' => false,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1006] = array (
  'title' => '',
  'text' => '<a href="https://themify.me"><img src="https://themify.me/demo/themes/magazine/files/2014/10/ad-skyscapper.jpg" alt="ad" /></a>',
  'filter' => false,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_categories" );
$widgets[1007] = array (
  'title' => 'Categories',
  'count' => 1,
  'hierarchical' => 1,
  'dropdown' => 0,
);
update_option( "widget_categories", $widgets );

$widgets = get_option( "widget_themify-feature-posts" );
$widgets[1008] = array (
  'title' => 'Featured',
  'category' => '5',
  'show_count' => '3',
  'show_date' => NULL,
  'show_thumb' => 'on',
  'display' => 'none',
  'hide_title' => NULL,
  'thumb_width' => '160',
  'thumb_height' => '100',
  'excerpt_length' => '55',
  'orderby' => 'date',
  'order' => 'DESC',
);
update_option( "widget_themify-feature-posts", $widgets );

$widgets = get_option( "widget_themify-twitter" );
$widgets[1009] = array (
  'title' => 'Latest Tweets',
  'username' => 'themify',
  'show_count' => '3',
  'hide_timestamp' => NULL,
  'show_follow' => NULL,
  'follow_text' => '→ Follow me',
  'include_retweets' => NULL,
  'exclude_replies' => NULL,
);
update_option( "widget_themify-twitter", $widgets );

$widgets = get_option( "widget_themify-social-links" );
$widgets[1010] = array (
  'title' => '',
  'show_link_name' => NULL,
  'open_new_window' => NULL,
  'icon_size' => 'icon-medium',
  'orientation' => 'horizontal',
);
update_option( "widget_themify-social-links", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1011] = array (
  'title' => '',
  'text' => '<a href="https://themify.me"><img src="https://themify.me/demo/themes/magazine/files/2014/10/ad-leaderboard.jpg" alt="sample image" /></a>',
  'filter' => false,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1012] = array (
  'title' => '',
  'text' => '<a href="https://themify.me"><img src="https://themify.me/demo/themes/magazine/files/2014/10/ad-250x250.jpg" alt="ad" /></a>',
  'filter' => false,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_themify-social-links" );
$widgets[1013] = array (
  'title' => '',
  'show_link_name' => NULL,
  'open_new_window' => NULL,
  'icon_size' => 'icon-large',
  'orientation' => 'horizontal',
);
update_option( "widget_themify-social-links", $widgets );



$sidebars_widgets = array (
  'wp_inactive_widgets' => 
  array (
    0 => 'themify-feature-posts-1002',
    1 => 'themify-flickr-1003',
    2 => 'text-1004',
  ),
  'sidebar-main' => 
  array (
    0 => 'text-1005',
  ),
  'sidebar-alt' => 
  array (
    0 => 'text-1006',
  ),
  'sidebar-main-2a' => 
  array (
    0 => 'categories-1007',
  ),
  'sidebar-main-2b' => 
  array (
    0 => 'themify-feature-posts-1008',
  ),
  'sidebar-main-3' => 
  array (
    0 => 'themify-twitter-1009',
  ),
  'social-widget' => 
  array (
    0 => 'themify-social-links-1010',
  ),
  'header-widget' => 
  array (
    0 => 'text-1011',
  ),
  'after-content-widget' => 
  array (
    0 => 'text-1012',
  ),
  'footer-social-widget' => 
  array (
    0 => 'themify-social-links-1013',
  ),
); 
update_option( "sidebars_widgets", $sidebars_widgets );

$menu_locations = array();
$menu = get_terms( "nav_menu", array( "slug" => "top-menu" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["top-nav"] = $menu[0]->term_id;
$menu = get_terms( "nav_menu", array( "slug" => "main" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["main-nav"] = $menu[0]->term_id;
$menu = get_terms( "nav_menu", array( "slug" => "footer-nav" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["footer-nav"] = $menu[0]->term_id;
set_theme_mod( "nav_menu_locations", $menu_locations );


$homepage = get_posts( array( 'name' => 'home', 'post_type' => 'page' ) );
			if( is_array( $homepage ) && ! empty( $homepage ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $homepage[0]->ID );
			}
			
	ob_start(); ?>a:74:{s:16:"setting-page_404";s:1:"0";s:21:"setting-webfonts_list";s:11:"recommended";s:22:"setting-default_layout";s:8:"sidebar2";s:27:"setting-default_post_layout";s:5:"grid2";s:30:"setting-default_layout_display";s:7:"content";s:25:"setting-default_more_text";s:4:"More";s:21:"setting-index_orderby";s:4:"date";s:19:"setting-index_order";s:4:"DESC";s:31:"setting-image_post_feature_size";s:5:"blank";s:32:"setting-default_page_post_layout";s:8:"sidebar2";s:38:"setting-image_post_single_feature_size";s:5:"blank";s:27:"setting-default_page_layout";s:8:"sidebar2";s:53:"setting-customizer_responsive_design_tablet_landscape";s:4:"1024";s:43:"setting-customizer_responsive_design_tablet";s:3:"768";s:43:"setting-customizer_responsive_design_mobile";s:3:"680";s:33:"setting-mobile_menu_trigger_point";s:4:"1200";s:24:"setting-gallery_lightbox";s:8:"lightbox";s:26:"setting-page_builder_cache";s:2:"on";s:27:"setting-script_minification";s:7:"disable";s:27:"setting-page_builder_expiry";s:1:"2";s:19:"setting-entries_nav";s:8:"numbered";s:25:"setting-breaking_news_tax";s:8:"category";s:21:"setting-breaking_news";s:13:"breaking-news";s:37:"setting-breaking_news_slider_autoplay";s:4:"4000";s:35:"setting-breaking_news_slider_effect";s:5:"slide";s:34:"setting-breaking_news_slider_speed";s:3:"500";s:21:"setting-related_posts";s:3:"yes";s:16:"setting-facebook";s:3:"yes";s:15:"setting-twitter";s:3:"yes";s:18:"setting-googleplus";s:3:"yes";s:17:"setting-pinterest";s:2:"no";s:19:"setting-stumbleupon";s:2:"no";s:16:"setting-linkedin";s:2:"no";s:22:"setting-footer_widgets";s:17:"footerwidget-3col";s:27:"setting-global_feature_size";s:5:"blank";s:22:"setting-link_icon_type";s:9:"font-icon";s:32:"setting-link_type_themify-link-0";s:10:"image-icon";s:33:"setting-link_title_themify-link-0";s:7:"Twitter";s:31:"setting-link_img_themify-link-0";s:98:"https://themify.me/demo/themes/magazine2/wp-content/themes/magazine/themify/img/social/twitter.png";s:32:"setting-link_type_themify-link-1";s:10:"image-icon";s:33:"setting-link_title_themify-link-1";s:8:"Facebook";s:31:"setting-link_img_themify-link-1";s:99:"https://themify.me/demo/themes/magazine2/wp-content/themes/magazine/themify/img/social/facebook.png";s:32:"setting-link_type_themify-link-2";s:10:"image-icon";s:33:"setting-link_title_themify-link-2";s:7:"Google+";s:31:"setting-link_img_themify-link-2";s:102:"https://themify.me/demo/themes/magazine2/wp-content/themes/magazine/themify/img/social/google-plus.png";s:32:"setting-link_type_themify-link-3";s:10:"image-icon";s:33:"setting-link_title_themify-link-3";s:7:"YouTube";s:31:"setting-link_img_themify-link-3";s:98:"https://themify.me/demo/themes/magazine2/wp-content/themes/magazine/themify/img/social/youtube.png";s:32:"setting-link_type_themify-link-4";s:10:"image-icon";s:33:"setting-link_title_themify-link-4";s:9:"Pinterest";s:31:"setting-link_img_themify-link-4";s:100:"https://themify.me/demo/themes/magazine2/wp-content/themes/magazine/themify/img/social/pinterest.png";s:32:"setting-link_type_themify-link-5";s:9:"font-icon";s:33:"setting-link_title_themify-link-5";s:7:"Twitter";s:32:"setting-link_link_themify-link-5";s:26:"http://twitter.com/themify";s:33:"setting-link_ficon_themify-link-5";s:10:"fa-twitter";s:32:"setting-link_type_themify-link-6";s:9:"font-icon";s:33:"setting-link_title_themify-link-6";s:8:"Facebook";s:32:"setting-link_link_themify-link-6";s:27:"http://facebook.com/themify";s:33:"setting-link_ficon_themify-link-6";s:11:"fa-facebook";s:32:"setting-link_type_themify-link-7";s:9:"font-icon";s:33:"setting-link_title_themify-link-7";s:7:"Google+";s:33:"setting-link_ficon_themify-link-7";s:14:"fa-google-plus";s:32:"setting-link_type_themify-link-8";s:9:"font-icon";s:33:"setting-link_title_themify-link-8";s:7:"YouTube";s:32:"setting-link_link_themify-link-8";s:37:"http://www.youtube.com/user/themifyme";s:33:"setting-link_ficon_themify-link-8";s:10:"fa-youtube";s:32:"setting-link_type_themify-link-9";s:9:"font-icon";s:33:"setting-link_title_themify-link-9";s:9:"Pinterest";s:33:"setting-link_ficon_themify-link-9";s:12:"fa-pinterest";s:22:"setting-link_field_ids";s:341:"{"themify-link-0":"themify-link-0","themify-link-1":"themify-link-1","themify-link-2":"themify-link-2","themify-link-3":"themify-link-3","themify-link-4":"themify-link-4","themify-link-5":"themify-link-5","themify-link-6":"themify-link-6","themify-link-7":"themify-link-7","themify-link-8":"themify-link-8","themify-link-9":"themify-link-9"}";s:23:"setting-link_field_hash";s:2:"10";s:30:"setting-page_builder_is_active";s:6:"enable";s:46:"setting-page_builder_animation_parallax_scroll";s:6:"mobile";s:4:"skin";s:91:"https://themify.me/demo/themes/magazine/wp-content/themes/magazine/themify/img/non-skin.gif";}<?php $themify_data = unserialize( ob_get_clean() );

	// fix the weird way "skin" is saved
	if( isset( $themify_data['skin'] ) ) {
		$parsed_skin = parse_url( $themify_data['skin'], PHP_URL_PATH );
		$basedir_skin = basename( dirname( $parsed_skin ) );
		$themify_data['skin'] = trailingslashit( get_template_directory_uri() ) . 'skins/' . $basedir_skin . '/style.css';
	}

	themify_set_data( $themify_data );
	
}
themify_do_demo_import();