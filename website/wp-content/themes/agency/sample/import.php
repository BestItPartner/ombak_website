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
  'term_id' => 6,
  'name' => 'Galleries',
  'slug' => 'galleries',
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
  'name' => 'Updates',
  'slug' => 'updates',
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
  'name' => 'Works',
  'slug' => 'works',
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
  'term_id' => 20,
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
  'term_id' => 21,
  'name' => 'Sports',
  'slug' => 'sports',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 20,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 24,
  'name' => 'World',
  'slug' => 'world',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 20,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 25,
  'name' => 'Culture',
  'slug' => 'culture',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 20,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 26,
  'name' => 'Lifestyle',
  'slug' => 'lifestyle',
  'term_group' => 0,
  'taxonomy' => 'category',
  'description' => '',
  'parent' => 20,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 33,
  'name' => 'Slider',
  'slug' => 'slider',
  'term_group' => 0,
  'taxonomy' => 'slider-category',
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
  'name' => 'Themes',
  'slug' => 'themes',
  'term_group' => 0,
  'taxonomy' => 'portfolio-category',
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
  'name' => 'Illustrations',
  'slug' => 'illustrations',
  'term_group' => 0,
  'taxonomy' => 'portfolio-category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 13,
  'name' => 'Photos',
  'slug' => 'photos',
  'term_group' => 0,
  'taxonomy' => 'portfolio-category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 36,
  'name' => 'Galleries',
  'slug' => 'galleries',
  'term_group' => 0,
  'taxonomy' => 'portfolio-category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 37,
  'name' => 'Featured',
  'slug' => 'featured',
  'term_group' => 0,
  'taxonomy' => 'portfolio-category',
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
  'name' => 'Services',
  'slug' => 'services',
  'term_group' => 0,
  'taxonomy' => 'highlight-category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 12,
  'name' => 'Features',
  'slug' => 'features',
  'term_group' => 0,
  'taxonomy' => 'highlight-category',
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
  'name' => 'Testimonials',
  'slug' => 'testimonials',
  'term_group' => 0,
  'taxonomy' => 'testimonial-category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 34,
  'name' => 'Team',
  'slug' => 'team',
  'term_group' => 0,
  'taxonomy' => 'testimonial-category',
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
  'name' => 'Executive',
  'slug' => 'executive',
  'term_group' => 0,
  'taxonomy' => 'team-category',
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
  'name' => 'General',
  'slug' => 'general',
  'term_group' => 0,
  'taxonomy' => 'team-category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 16,
  'name' => 'Main Menu',
  'slug' => 'main-menu',
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
  'term_id' => 17,
  'name' => 'Footer Menu',
  'slug' => 'footer-menu',
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
  'ID' => 73,
  'post_date' => '2012-11-03 22:10:16',
  'post_date_gmt' => '2012-11-03 22:10:16',
  'post_content' => 'Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero. Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis tincidunt eget, dignissim non tortor.',
  'post_title' => 'New Team Member',
  'post_excerpt' => '',
  'post_name' => 'new-team-member',
  'post_modified' => '2017-08-23 02:54:53',
  'post_modified_gmt' => '2017-08-23 02:54:53',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=73',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'updates',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 77,
  'post_date' => '2012-11-06 22:20:13',
  'post_date_gmt' => '2012-11-06 22:20:13',
  'post_content' => 'Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero. Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc.',
  'post_title' => 'New Photo Gallery',
  'post_excerpt' => '',
  'post_name' => 'new-photo-gallery',
  'post_modified' => '2017-08-23 02:54:43',
  'post_modified_gmt' => '2017-08-23 02:54:43',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=77',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'works',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 80,
  'post_date' => '2012-11-11 22:22:29',
  'post_date_gmt' => '2012-11-11 22:22:29',
  'post_content' => 'Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero. Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum.',
  'post_title' => 'Looking for Models',
  'post_excerpt' => '',
  'post_name' => 'looking-for-models',
  'post_modified' => '2017-08-23 02:54:41',
  'post_modified_gmt' => '2017-08-23 02:54:41',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=80',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'works',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 86,
  'post_date' => '2012-10-25 22:25:03',
  'post_date_gmt' => '2012-10-25 22:25:03',
  'post_content' => 'Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Nam risus velit, rhoncus eget consectetur id, posuere at ligula. Vivamus imperdiet diam ac tortor tempus posuere. Curabitur at arcu id turpis posuere bibendum. Sed commodo mauris eget diam pretium cursus. In sagittis feugiat mauris, in ultrices mauris lacinia eu. Fusce augue velit, vulputate elementum semper congue, rhoncus adipiscing nisl. Curabitur vel risus eros, sed eleifend arcu.',
  'post_title' => 'New Theme Released',
  'post_excerpt' => '',
  'post_name' => 'new-theme-released',
  'post_modified' => '2017-08-23 02:54:54',
  'post_modified_gmt' => '2017-08-23 02:54:54',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=86',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'updates, works',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 104,
  'post_date' => '2012-11-11 22:49:07',
  'post_date_gmt' => '2012-11-11 22:49:07',
  'post_content' => 'Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis tincidunt eget, dignissim non tortor.',
  'post_title' => 'Editing a Video',
  'post_excerpt' => '',
  'post_name' => 'editing-a-video',
  'post_modified' => '2017-08-23 02:54:39',
  'post_modified_gmt' => '2017-08-23 02:54:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=104',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'works',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 109,
  'post_date' => '2012-11-11 22:53:34',
  'post_date_gmt' => '2012-11-11 22:53:34',
  'post_content' => 'Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero. Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis.',
  'post_title' => 'Lightbox Image',
  'post_excerpt' => '',
  'post_name' => 'lightbox-image',
  'post_modified' => '2017-08-23 02:54:37',
  'post_modified_gmt' => '2017-08-23 02:54:37',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=109',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'lightbox_link' => 'https://themify.me/demo/themes/agency/files/2012/11/154232705.jpg',
  ),
  'tax_input' => 
  array (
    'category' => 'galleries',
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
  'post_date' => '2012-11-11 22:55:09',
  'post_date_gmt' => '2012-11-11 22:55:09',
  'post_content' => 'In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero. Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod.',
  'post_title' => 'Lightbox Video',
  'post_excerpt' => '',
  'post_name' => 'lightbox-video',
  'post_modified' => '2017-08-23 02:54:35',
  'post_modified_gmt' => '2017-08-23 02:54:35',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=112',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'lightbox_link' => 'http://vimeo.com/6929537',
  ),
  'tax_input' => 
  array (
    'category' => 'works',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 115,
  'post_date' => '2012-11-11 22:56:00',
  'post_date_gmt' => '2012-11-11 22:56:00',
  'post_content' => 'Donec consequat eros eget lectus dictum sit amet ultrices neque sodales. Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim.',
  'post_title' => 'iFrame Window',
  'post_excerpt' => '',
  'post_name' => 'iframe-window',
  'post_modified' => '2017-08-23 02:54:32',
  'post_modified_gmt' => '2017-08-23 02:54:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=115',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
    'lightbox_link' => 'https://themify.me?iframe=true&width=100%&height=100%',
  ),
  'tax_input' => 
  array (
    'category' => 'works',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1833,
  'post_date' => '2008-06-26 01:30:51',
  'post_date_gmt' => '2008-06-26 01:30:51',
  'post_content' => 'Etiam lorem sapien, vestibulum ut nisl sed, egestas dignissim enim. Nam lacus massa, pellentesque eget pulvinar vitae, sagittis eget justo. Maecenas bibendum sit amet odio et sodales. Praesent cursus mattis tortor, ut vestibulum purus venenatis at.',
  'post_title' => 'Watercolor',
  'post_excerpt' => '',
  'post_name' => 'watercolor',
  'post_modified' => '2017-08-23 02:55:12',
  'post_modified_gmt' => '2017-08-23 02:55:12',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1833',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'culture',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1822,
  'post_date' => '2008-06-26 23:38:36',
  'post_date_gmt' => '2008-06-26 23:38:36',
  'post_content' => 'Donec auctor consectetur tellus, in hendrerit urna vulputate non. Ut elementum fringilla purus. Nam dui erat, porta eu gravida sit amet, ornare sit amet sem.',
  'post_title' => 'Dirt Championship',
  'post_excerpt' => '',
  'post_name' => 'dirt-championship',
  'post_modified' => '2017-08-23 02:54:55',
  'post_modified_gmt' => '2017-08-23 02:54:55',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1822',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'sports',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1836,
  'post_date' => '2008-06-26 01:31:26',
  'post_date_gmt' => '2008-06-26 01:31:26',
  'post_content' => 'Cras tristique feugiat neque sed vestibulum. Sed eu urna quis lacus aliquet fermentum vel sed risus. Integer laoreet pretium interdum. Proin consequat consequat feugiat. Integer pellentesque faucibus aliquet.',
  'post_title' => 'Living Art',
  'post_excerpt' => '',
  'post_name' => 'living-art',
  'post_modified' => '2017-08-23 02:55:10',
  'post_modified_gmt' => '2017-08-23 02:55:10',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1836',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'culture',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1839,
  'post_date' => '2008-06-26 01:33:00',
  'post_date_gmt' => '2008-06-26 01:33:00',
  'post_content' => 'In convallis quis est fermentum sollicitudin. Phasellus nec purus elit. Aenean tempus tincidunt dolor, quis auctor diam auctor non. Quisque at fermentum purus, a aliquet arcu.',
  'post_title' => 'Long Exposures',
  'post_excerpt' => '',
  'post_name' => 'long-exposures',
  'post_modified' => '2017-08-23 02:55:08',
  'post_modified_gmt' => '2017-08-23 02:55:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1839',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'culture',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1845,
  'post_date' => '2008-06-26 01:36:35',
  'post_date_gmt' => '2008-06-26 01:36:35',
  'post_content' => 'Donec hendrerit, lectus in dapibus consequat, libero arcu dignissim turpis, id dictum odio felis eget ante. In ullamcorper pulvinar rutrum. In id neque pulvinar, tempor orci ac, tincidunt libero. Fusce ultricies arcu at mauris semper bibendum.',
  'post_title' => 'Cooking Courses',
  'post_excerpt' => '',
  'post_name' => 'cooking-courses',
  'post_modified' => '2017-08-23 02:55:07',
  'post_modified_gmt' => '2017-08-23 02:55:07',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1845',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'world',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1849,
  'post_date' => '2008-06-26 01:38:43',
  'post_date_gmt' => '2008-06-26 01:38:43',
  'post_content' => 'Phasellus dui erat, tincidunt pulvinar tempor at, lacinia eu lacus. Aenean euismod tellus laoreet turpis viverra facilisis. Nunc eu viverra eros, et facilisis dui. Sed pretium id risus eu tincidunt.',
  'post_title' => 'Maritime Shipping',
  'post_excerpt' => '',
  'post_name' => 'maritime-shipping',
  'post_modified' => '2017-08-23 02:55:05',
  'post_modified_gmt' => '2017-08-23 02:55:05',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1849',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'world',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1852,
  'post_date' => '2008-06-26 01:42:25',
  'post_date_gmt' => '2008-06-26 01:42:25',
  'post_content' => 'In lobortis vehicula lectus, et venenatis velit euismod sit amet. Morbi egestas malesuada turpis, dictum consequat mauris scelerisque ac. Mauris luctus commodo lorem, pulvinar sollicitudin ante porttitor id.',
  'post_title' => 'Water Town',
  'post_excerpt' => '',
  'post_name' => 'water-town',
  'post_modified' => '2017-08-23 02:55:03',
  'post_modified_gmt' => '2017-08-23 02:55:03',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1852',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'world',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1857,
  'post_date' => '2008-06-26 02:46:21',
  'post_date_gmt' => '2008-06-26 02:46:21',
  'post_content' => 'Nullam fringilla facilisis ultricies. Ut volutpat ultricies rutrum. In laoreet, nunc et auctor condimentum, enim lacus lacinia dolor, non accumsan leo nisl id lorem. Duis vehicula et turpis fringilla hendrerit.',
  'post_title' => 'Remote Places',
  'post_excerpt' => '',
  'post_name' => 'remote-places',
  'post_modified' => '2017-08-23 02:55:02',
  'post_modified_gmt' => '2017-08-23 02:55:02',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1857',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1860,
  'post_date' => '2008-06-26 02:47:20',
  'post_date_gmt' => '2008-06-26 02:47:20',
  'post_content' => 'Duis eget tellus nisl. Donec porta orci vel iaculis porta. Vivamus aliquet, ligula et tempus mattis, tortor ipsum eleifend massa, ac gravida dui est quis dui.',
  'post_title' => 'Evening Rides',
  'post_excerpt' => '',
  'post_name' => 'evening-rides',
  'post_modified' => '2017-08-23 02:55:00',
  'post_modified_gmt' => '2017-08-23 02:55:00',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1860',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1863,
  'post_date' => '2008-06-26 02:48:34',
  'post_date_gmt' => '2008-06-26 02:48:34',
  'post_content' => 'Proin vitae lectus eu turpis sollicitudin sagittis. Aliquam nunc odio, semper lacinia tincidunt a, dapibus vitae dolor. Class aptent taciti sociosqu ad litora torquent per conubia.',
  'post_title' => 'Learn Something New',
  'post_excerpt' => '',
  'post_name' => 'learn-something-new',
  'post_modified' => '2017-08-23 02:54:59',
  'post_modified_gmt' => '2017-08-23 02:54:59',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1863',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1865,
  'post_date' => '2008-06-26 02:49:39',
  'post_date_gmt' => '2008-06-26 02:49:39',
  'post_content' => 'Vivamus pharetra magna fermentum tincidunt imperdiet. Aenean venenatis sollicitudin odio in ultrices. Proin a nibh at dolor rhoncus pulvinar. Nullam eget tincidunt enim.',
  'post_title' => 'Clean Air',
  'post_excerpt' => '',
  'post_name' => 'clean-air',
  'post_modified' => '2017-08-23 02:54:57',
  'post_modified_gmt' => '2017-08-23 02:54:57',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?p=1865',
  'menu_order' => 0,
  'post_type' => 'post',
  'meta_input' => 
  array (
    'layout' => 'default',
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_date' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1060,
  'post_date' => '2012-11-18 20:16:23',
  'post_date_gmt' => '2012-11-18 20:16:23',
  'post_content' => 'Use [ highlight ] posts to display short list items with icon such as feature list or services. You can choose between 4-column, 3-column, 2-column or fullwidth. For examples: you can display a 3-column list from "Services" category and a 4-column list from "Features" category.
<h2>A List <em>of</em> Services</h2>
[highlight category="services" style="grid3" limit="3"]
<h2>A List <em>of</em> Features</h2>
[highlight category="features" style="grid4" limit="4"]
<h2>Column Examples</h2>
<h3>4-Column Large Icon</h3>
[highlight style="grid4" limit="8" image_w="200" image_h="200"]
<h3>3-Column</h3>
[highlight style="grid3" limit="6" image_w="80" image_h="80"]
<h3>2-Column</h3>
[highlight style="grid2" limit="4" image_w="100" image_h="100"]
<h3>Fullwidth</h3>
[highlight style="full" limit="2" image_w="100" image_h="100"]',
  'post_title' => 'Highlight Shortcode',
  'post_excerpt' => '',
  'post_name' => 'highlight-shortcode',
  'post_modified' => '2012-11-21 22:32:51',
  'post_modified_gmt' => '2012-11-21 22:32:51',
  'post_content_filtered' => '',
  'post_parent' => 1054,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1060',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
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
  'ID' => 1066,
  'post_date' => '2012-11-18 20:21:17',
  'post_date_gmt' => '2012-11-18 20:21:17',
  'post_content' => 'Use [ team ] posts to display team members of your company. You can choose between 4-column, 3-column, 2-column or fullwidth. The team members can be categorized. For example, you can display a list of "Executive" category and another list of "General" category. Image, content, title, etc. can be toggled with parameters.
<h2>Executives</h2>
[team category="3" style="grid3" limit="3" image_width="300" image_height="180" display="none"]
<h2>General</h2>
[team category="4" style="grid4" limit="4" image_width="300" image_height="180" display="none"]
<h2>Column Examples</h2>
<h3>4-Column</h3>
[team style="grid4" limit="4" image_width="300" image_height="180"]
<h3>3-Column</h3>
[team style="grid3" limit="3" image_width="300" image_height="180"]
<h3>2-Column</h3>
[team style="grid2" limit="2" image_width="300" image_height="180"]
<h3>Fullwidth</h3>
[team style="full" limit="1" image_width="300" image_height="180"]',
  'post_title' => 'Team Shortcode',
  'post_excerpt' => '',
  'post_name' => 'team-shortcode',
  'post_modified' => '2012-11-18 20:21:17',
  'post_modified_gmt' => '2012-11-18 20:21:17',
  'post_content_filtered' => '',
  'post_parent' => 1054,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1066',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-post\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"hide_page_title\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_query_post_type\\":[\\"default\\"],\\"_edit_lock\\":[\\"1353536898:2\\"]}',
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
  'ID' => 1076,
  'post_date' => '2012-11-18 20:31:56',
  'post_date_gmt' => '2012-11-18 20:31:56',
  'post_content' => 'Use [ portfolio ] posts to display your work samples. You can choose between 4-column, 3-column, 2-column or fullwidth. The portfolio posts can be categorized. For example, you can display a list of "Theme Design" category and another list of "Illustration" category. Image, content, title, etc. can be toggled with parameters. If the portfolio post has gallery, it will shows a slider instead of a static featured image.
<h2>All Portfolio Posts</h2>
[portfolio style="grid4" limit="12" display="none" page_nav="yes"]
<h2>Galleries <em>with</em> Date</h2>
[portfolio category="6" style="grid4" limit="4" display="none" post_date="yes"]
<h2>Themes <em>with</em> Excerpt</h2>
[portfolio category="7" style="grid4" limit="4" display="excerpt"]
<h2>Illustrations</h2>
[portfolio category="10" style="grid4" limit="4" display="none"]
<h2>Column Examples</h2>
<h3>4-Column</h3>
[portfolio category="13" style="grid4" limit="4"]
<h3>3-Column</h3>
[portfolio category="13" style="grid3" limit="3" image_w="306" image_h="180"]
<h3>2-Column</h3>
[portfolio category="13" style="grid2" limit="2" image_w="474" image_h="250"]
<h3>Fullwidth</h3>
[portfolio category="13" style="full" limit="1" image_w="978" image_h="500"]',
  'post_title' => 'Portfolio Shortcode',
  'post_excerpt' => '',
  'post_name' => 'portfolio-shortcode',
  'post_modified' => '2012-11-18 20:31:56',
  'post_modified_gmt' => '2012-11-18 20:31:56',
  'post_content_filtered' => '',
  'post_parent' => 1054,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1076',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-post\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"hide_page_title\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_query_post_type\\":[\\"default\\"],\\"_edit_lock\\":[\\"1370448855:32\\"]}',
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
  'ID' => 1210,
  'post_date' => '2012-11-20 00:32:23',
  'post_date_gmt' => '2012-11-20 00:32:23',
  'post_content' => '<h2>Photo Galleries</h2>
[portfolio category="galleries" style="grid4" limit="20" display="none" post_date="yes"]
<h2>WordPress Themes</h2>
[portfolio category="themes" style="grid4" limit="20" display="excerpt"]
<h2>Illustrations</h2>
[portfolio category="illustrations" style="grid4" limit="20" display="none"]
<h2>Photos</h2>
[portfolio category="photos" style="grid4" limit="20" display="none"]',
  'post_title' => 'Portfolio - Sectioned',
  'post_excerpt' => '',
  'post_name' => 'portfolio-sectioned',
  'post_modified' => '2012-11-21 22:41:18',
  'post_modified_gmt' => '2012-11-21 22:41:18',
  'post_content_filtered' => '',
  'post_parent' => 1104,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1210',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-post\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"hide_page_title\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_edit_lock\\":[\\"1435155265:115\\"]}',
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
  'ID' => 1243,
  'post_date' => '2012-11-21 01:28:50',
  'post_date_gmt' => '2012-11-21 01:28:50',
  'post_content' => '[portfolio style="grid3" limit="9"  image_w="306" image_h="180" display="none" page_nav="yes"]',
  'post_title' => 'Portfolio - Grid3',
  'post_excerpt' => '',
  'post_name' => 'portfolio-grid3',
  'post_modified' => '2012-11-21 01:28:50',
  'post_modified_gmt' => '2012-11-21 01:28:50',
  'post_content_filtered' => '',
  'post_parent' => 1104,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1243',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-post\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"hide_page_title\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_edit_lock\\":[\\"1384457690:31\\"]}',
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
  'ID' => 1247,
  'post_date' => '2012-11-21 01:30:35',
  'post_date_gmt' => '2012-11-21 01:30:35',
  'post_content' => '[portfolio style="grid2" limit="8"  image_w="474" image_h="250" display="none" page_nav="yes"]',
  'post_title' => 'Portfolio - Grid2',
  'post_excerpt' => '',
  'post_name' => 'portfolio-grid2',
  'post_modified' => '2012-11-21 01:30:35',
  'post_modified_gmt' => '2012-11-21 01:30:35',
  'post_content_filtered' => '',
  'post_parent' => 1104,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1247',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-post\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"hide_page_title\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_edit_lock\\":[\\"1370448790:32\\"]}',
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
  'ID' => 2153,
  'post_date' => '2013-09-10 00:04:39',
  'post_date_gmt' => '2013-09-10 00:04:39',
  'post_content' => '',
  'post_title' => 'Home',
  'post_excerpt' => '',
  'post_name' => 'home',
  'post_modified' => '2017-10-27 17:22:26',
  'post_modified_gmt' => '2017-10-27 17:22:26',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=2153',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'yes',
    'order' => 'desc',
    'orderby' => 'content',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2 style=\\\\\\\\\\\\\\"text-align: center;\\\\\\\\\\\\\\">What <em>we</em> Do</h2>\\",\\"column_divider_style\\":\\"solid\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"mod_name\\":\\"highlight\\",\\"mod_settings\\":{\\"layout_highlight\\":\\"grid3\\",\\"category_highlight\\":\\"features|multiple\\",\\"post_per_page_highlight\\":\\"6\\",\\"order_highlight\\":\\"desc\\",\\"orderby_highlight\\":\\"date\\",\\"display_highlight\\":\\"excerpt\\",\\"img_width_highlight\\":\\"65\\",\\"img_height_highlight\\":\\"65\\",\\"hide_page_nav_highlight\\":\\"yes\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p class=\\\\\\\\\\\'more-link-wrap\\\\\\\\\\\'><a href=\\\\\\\\\\\'https://themify.me/demo/themes/agency/services/\\\\\\\\\\\' class=\\\\\\\\\\\'more-link\\\\\\\\\\\'>See our services →</a></p>\\\\n\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2 style=\\\\\\\\\\\\\\"text-align: center;\\\\\\\\\\\\\\">View <em>featured</em> Work</h2>\\",\\"text_align\\":\\"center\\",\\"column_divider_style\\":\\"solid\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"mod_name\\":\\"portfolio\\",\\"mod_settings\\":{\\"layout_portfolio\\":\\"grid4\\",\\"type_query_portfolio\\":\\"category\\",\\"category_portfolio\\":\\"galleries|multiple\\",\\"post_per_page_portfolio\\":\\"4\\",\\"order_portfolio\\":\\"desc\\",\\"orderby_portfolio\\":\\"date\\",\\"display_portfolio\\":\\"none\\",\\"img_width_portfolio\\":\\"222\\",\\"img_height_portfolio\\":\\"155\\",\\"hide_post_date_portfolio\\":\\"yes\\",\\"hide_post_meta_portfolio\\":\\"yes\\",\\"hide_page_nav_portfolio\\":\\"yes\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\",\\"font_size_title_unit\\":\\"px\\",\\"line_height_title_unit\\":\\"px\\",\\"font_size_meta_unit\\":\\"px\\",\\"line_height_meta_unit\\":\\"px\\",\\"font_size_date_unit\\":\\"px\\",\\"line_height_date_unit\\":\\"px\\",\\"font_size_content_unit\\":\\"px\\",\\"line_height_content_unit\\":\\"px\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p class=\\\\\\\\\\\'more-link-wrap\\\\\\\\\\\'><a href=\\\\\\\\\\\'https://themify.me/demo/themes/agency/portfolio/\\\\\\\\\\\' class=\\\\\\\\\\\'more-link\\\\\\\\\\\'>See more work →</a></p>\\\\n\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2 style=\\\\\\\\\\\\\\"text-align: center;\\\\\\\\\\\\\\">What People <em>are</em> Saying</h2>\\",\\"text_align\\":\\"center\\",\\"column_divider_style\\":\\"solid\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"mod_name\\":\\"testimonial\\",\\"mod_settings\\":{\\"layout_testimonial\\":\\"grid2\\",\\"category_testimonial\\":\\"testimonials|multiple\\",\\"post_per_page_testimonial\\":\\"4\\",\\"order_testimonial\\":\\"desc\\",\\"orderby_testimonial\\":\\"date\\",\\"display_testimonial\\":\\"content\\",\\"img_width_testimonial\\":\\"80\\",\\"img_height_testimonial\\":\\"80\\",\\"hide_post_title_testimonial\\":\\"yes\\",\\"hide_page_nav_testimonial\\":\\"yes\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p class=\\\\\\\\\\\'more-link-wrap\\\\\\\\\\\'><a href=\\\\\\\\\\\'https://themify.me/demo/themes/agency/testimonials/\\\\\\\\\\\' class=\\\\\\\\\\\'more-link\\\\\\\\\\\'>Read more testimonials →</a></p>\\\\n\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2 style=\\\\\\\\\\\\\\"text-align: center;\\\\\\\\\\\\\\">Read <em>our</em> Blog</h2>\\",\\"text_align\\":\\"center\\",\\"column_divider_style\\":\\"solid\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"mod_name\\":\\"post\\",\\"mod_settings\\":{\\"layout_post\\":\\"grid2-thumb\\",\\"category_post\\":\\"works|multiple\\",\\"post_per_page_post\\":\\"2\\",\\"order_post\\":\\"desc\\",\\"orderby_post\\":\\"date\\",\\"display_post\\":\\"excerpt\\",\\"hide_page_nav_post\\":\\"yes\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2 style=\\\\\\\\\\\\\\"text-align: center;\\\\\\\\\\\\\\">Our Team</h2>\\",\\"text_align\\":\\"center\\",\\"column_divider_style\\":\\"solid\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\"}},{\\"mod_name\\":\\"plain-text\\",\\"mod_settings\\":{\\"plain_text\\":\\"[team style=\\\\\\\\\\\'grid4\\\\\\\\\\\' limit=\\\\\\\\\\\'4\\\\\\\\\\\' display=\\\\\\\\\\\'none\\\\\\\\\\\']\\",\\"font_size_unit\\":\\"px\\",\\"line_height_unit\\":\\"px\\",\\"checkbox_padding_apply_all\\":\\"padding\\",\\"checkbox_margin_apply_all\\":\\"margin\\",\\"border_top_style\\":\\"solid\\",\\"border_right_style\\":\\"solid\\",\\"border_bottom_style\\":\\"solid\\",\\"border_left_style\\":\\"solid\\",\\"checkbox_border_apply_all\\":\\"border\\"}}],\\"styling\\":[]}],\\"styling\\":[]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[],\\"styling\\":[]}],\\"styling\\":[]}]',
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
  'ID' => 1275,
  'post_date' => '2012-11-21 03:20:11',
  'post_date_gmt' => '2012-11-21 03:20:11',
  'post_content' => '[col grid="3-2 first"]
[map address="Yonge St. and Eglinton Ave, Toronto, Ontario, Canada" width=100% height=600px]
[/col]

[col grid="3-1"]
<h3>Direction</h3>
We are located at Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu.
<h3>Address</h3>
123 Street Name,
City, Province
23446
<h3>Phone</h3>
236-298-2828
<h3>Hours</h3>
Mon - Fri : 11:00am - 10:00pm
Sat : 11:00am - 2:00pm
Sun : 12:00am - 11:00pm
[/col]',
  'post_title' => 'Map',
  'post_excerpt' => '',
  'post_name' => 'map',
  'post_modified' => '2012-11-21 03:20:11',
  'post_modified_gmt' => '2012-11-21 03:20:11',
  'post_content_filtered' => '',
  'post_parent' => 1272,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1275',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-post\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"hide_page_title\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"]}',
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
  'ID' => 1295,
  'post_date' => '2012-11-21 04:09:15',
  'post_date_gmt' => '2012-11-21 04:09:15',
  'post_content' => '',
  'post_title' => 'Home 2',
  'post_excerpt' => '',
  'post_name' => 'home-2',
  'post_modified' => '2013-09-10 21:29:21',
  'post_modified_gmt' => '2013-09-10 21:29:21',
  'post_content_filtered' => '',
  'post_parent' => 2153,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1295',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'yes',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Galleries<\\\\/h2>\\"}},{\\"mod_name\\":\\"portfolio\\",\\"mod_settings\\":{\\"layout_portfolio\\":\\"grid4\\",\\"category_portfolio\\":\\"galleries|multiple\\",\\"post_per_page_portfolio\\":\\"4\\",\\"order_portfolio\\":\\"desc\\",\\"orderby_portfolio\\":\\"date\\",\\"display_portfolio\\":\\"none\\",\\"img_width_portfolio\\":\\"300\\",\\"img_height_portfolio\\":\\"200\\",\\"hide_post_date_portfolio\\":\\"yes\\",\\"hide_post_meta_portfolio\\":\\"yes\\",\\"hide_page_nav_portfolio\\":\\"yes\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p class=\\\\\\\\\\\\\\"more-link-wrap\\\\\\\\\\\\\\"><a href=\\\\\\\\\\\\\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/agency\\\\/portfolio\\\\/\\\\\\\\\\\\\\" class=\\\\\\\\\\\\\\"more-link\\\\\\\\\\\\\\">See more work \\\\u2192<\\\\/a><\\\\/p>\\\\n\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Features<\\\\/h2>\\"}},{\\"mod_name\\":\\"highlight\\",\\"mod_settings\\":{\\"layout_highlight\\":\\"grid3\\",\\"category_highlight\\":\\"0|multiple\\",\\"post_per_page_highlight\\":\\"6\\",\\"order_highlight\\":\\"desc\\",\\"orderby_highlight\\":\\"date\\",\\"display_highlight\\":\\"content\\",\\"img_width_highlight\\":\\"100\\",\\"img_height_highlight\\":\\"100\\",\\"hide_page_nav_highlight\\":\\"yes\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p class=\\\\\\\\\\\\\\"more-link-wrap\\\\\\\\\\\\\\"><a href=\\\\\\\\\\\\\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/agency\\\\/services\\\\/\\\\\\\\\\\\\\" class=\\\\\\\\\\\\\\"more-link\\\\\\\\\\\\\\">See our services \\\\u2192<\\\\/a><\\\\/p>\\\\n\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Testimonials<\\\\/h2>\\"}},{\\"mod_name\\":\\"testimonial\\",\\"mod_settings\\":{\\"layout_testimonial\\":\\"grid3\\",\\"category_testimonial\\":\\"testimonials|multiple\\",\\"post_per_page_testimonial\\":\\"3\\",\\"order_testimonial\\":\\"desc\\",\\"orderby_testimonial\\":\\"date\\",\\"display_testimonial\\":\\"content\\",\\"img_width_testimonial\\":\\"80\\",\\"img_height_testimonial\\":\\"80\\",\\"hide_page_nav_testimonial\\":\\"yes\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p class=\\\\\\\\\\\\\\"more-link-wrap\\\\\\\\\\\\\\"><a href=\\\\\\\\\\\\\\"https:\\\\/\\\\/themify.me\\\\/demo\\\\/themes\\\\/agency\\\\/testimonials\\\\/\\\\\\\\\\\\\\" class=\\\\\\\\\\\\\\"more-link\\\\\\\\\\\\\\">Read more testimonials \\\\u2192<\\\\/a><\\\\/p>\\\\n\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Team<\\\\/h2><p>\\\\u00a0[team style=\\\\\\\\\\\\\\"grid2\\\\\\\\\\\\\\" limit=\\\\\\\\\\\\\\"4\\\\\\\\\\\\\\" display=\\\\\\\\\\\\\\"excerpt\\\\\\\\\\\\\\"]<\\\\/p>\\"}}]}]}]',
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
  'ID' => 1300,
  'post_date' => '2012-11-21 04:29:35',
  'post_date_gmt' => '2012-11-21 04:29:35',
  'post_content' => '',
  'post_title' => 'Home 3',
  'post_excerpt' => '',
  'post_name' => 'home-3',
  'post_modified' => '2013-09-10 21:29:14',
  'post_modified_gmt' => '2013-09-10 21:29:14',
  'post_content_filtered' => '',
  'post_parent' => 2153,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1300',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'yes',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    'builder_switch_frontend' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Blog Post Slider<\\\\/h2>\\"}},{\\"mod_name\\":\\"slider\\",\\"mod_settings\\":{\\"layout_display_slider\\":\\"blog\\",\\"blog_category_slider\\":\\"0|multiple\\",\\"slider_category_slider\\":\\"|single\\",\\"portfolio_category_slider\\":\\"|single\\",\\"posts_per_page_slider\\":\\"6\\",\\"display_slider\\":\\"none\\",\\"layout_slider\\":\\"slider-default\\",\\"img_w_slider\\":\\"215\\",\\"img_h_slider\\":\\"155\\",\\"visible_opt_slider\\":\\"4\\",\\"auto_scroll_opt_slider\\":\\"4\\",\\"scroll_opt_slider\\":\\"1\\",\\"speed_opt_slider\\":\\"normal\\",\\"effect_slider\\":\\"scroll\\",\\"wrap_slider\\":\\"yes\\",\\"show_nav_slider\\":\\"yes\\",\\"show_arrow_slider\\":\\"yes\\"}}]}]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"grid_class\\":\\"col4-2 first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Highlight<\\\\/h2>\\"}},{\\"mod_name\\":\\"highlight\\",\\"mod_settings\\":{\\"layout_highlight\\":\\"fullwidth\\",\\"category_highlight\\":\\"0|multiple\\",\\"post_per_page_highlight\\":\\"2\\",\\"order_highlight\\":\\"desc\\",\\"orderby_highlight\\":\\"date\\",\\"display_highlight\\":\\"excerpt\\",\\"img_width_highlight\\":\\"80\\",\\"img_height_highlight\\":\\"80\\",\\"hide_page_nav_highlight\\":\\"yes\\"}}]},{\\"grid_class\\":\\"col4-2 last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Team<\\\\/h2><p>[team style=\\\\\\\\\\\'grid2\\\\\\\\\\\' limit=\\\\\\\\\\\'4\\\\\\\\\\\' display=\\\\\\\\\\\'none\\\\\\\\\\\']<\\\\/p>\\"}}]}]},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"grid_class\\":\\"col-full first last\\",\\"modules\\":[{\\"mod_name\\":\\"portfolio\\",\\"mod_settings\\":{\\"layout_portfolio\\":\\"grid4\\",\\"category_portfolio\\":\\"0|multiple\\",\\"post_per_page_portfolio\\":\\"4\\",\\"order_portfolio\\":\\"desc\\",\\"orderby_portfolio\\":\\"date\\",\\"display_portfolio\\":\\"none\\",\\"img_width_portfolio\\":\\"222\\",\\"img_height_portfolio\\":\\"160\\",\\"hide_post_date_portfolio\\":\\"yes\\",\\"hide_post_meta_portfolio\\":\\"yes\\",\\"hide_page_nav_portfolio\\":\\"yes\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<h2>Contact Us<\\\\/h2>\\"}}]}]},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"grid_class\\":\\"col4-2 first\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[map address=\\\\\\\\\\\'Yonge St. and Eglinton Ave, Toronto, Ontario, Canada\\\\\\\\\\\' width=100% height=540px]<\\\\/p>\\"}}]},{\\"grid_class\\":\\"col4-2 last\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"content_text\\":\\"<p>[contact-form-7 id=\\\\\\\\\\\'1273\\\\\\\\\\\' title=\\\\\\\\\\\'Untitled\\\\\\\\\\\']<\\\\/p>\\"}}]}]}]',
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
  'ID' => 1056,
  'post_date' => '2012-11-18 20:13:53',
  'post_date_gmt' => '2012-11-18 20:13:53',
  'post_content' => 'Use [ testimonial ] posts to display testimonials from your clients/customers. You can choose between 4-column, 3-column, 2-column or fullwidth. The image, content, title can be toggled with parameters.
<h3>4-Column</h3>
[testimonial style="grid4" limit="4" image_width="300" image_height="180"]
<h3>4-Column Without Picture</h3>
[testimonial style="grid4" limit="4" image="no"]
<h3>3-Column</h3>
[testimonial style="grid3" limit="3" image_width="300" image_height="180"]
<h3>2-Column</h3>
[testimonial style="grid2" limit="2" title="yes" image_width="300" image_height="180"]
<h3>Fullwidth</h3>
[testimonial style="full" limit="1" title="yes" image_width="300" image_height="180"]',
  'post_title' => 'Testimonial Shortcode',
  'post_excerpt' => '',
  'post_name' => 'testimonial-shortcode',
  'post_modified' => '2012-11-18 20:13:53',
  'post_modified_gmt' => '2012-11-18 20:13:53',
  'post_content_filtered' => '',
  'post_parent' => 1054,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1056',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_query_post_type\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_title\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"allow_sorting\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"hide_page_title\\":[\\"default\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"layout\\":[\\"list-post\\"],\\"_edit_last\\":[\\"2\\"]}',
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
  'ID' => 660,
  'post_date' => '2011-04-04 05:38:34',
  'post_date_gmt' => '2011-04-04 05:38:34',
  'post_content' => '',
  'post_title' => 'Section 2 Column',
  'post_excerpt' => '',
  'post_name' => 'section-2-column',
  'post_modified' => '2011-04-04 05:38:34',
  'post_modified_gmt' => '2011-04-04 05:38:34',
  'post_content_filtered' => '',
  'post_parent' => 662,
  'guid' => 'https://themify.me/demo/themes/blogfolio/?page_id=660',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'yes',
    'layout' => 'grid2',
    'posts_per_page' => '2',
    'display_content' => 'none',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'yes',
    'hide_image' => 'default',
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
  'ID' => 668,
  'post_date' => '2011-04-29 21:16:48',
  'post_date_gmt' => '2011-04-29 21:16:48',
  'post_content' => '',
  'post_title' => 'Section 2 Column Thumb',
  'post_excerpt' => '',
  'post_name' => 'section-2-column-thumb',
  'post_modified' => '2011-04-29 21:16:48',
  'post_modified_gmt' => '2011-04-29 21:16:48',
  'post_content_filtered' => '',
  'post_parent' => 662,
  'guid' => 'https://themify.me/demo/themes/blogfolio/?page_id=668',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'yes',
    'layout' => 'grid2-thumb',
    'posts_per_page' => '2',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'post_modified' => '2011-04-03 00:52:10',
  'post_modified_gmt' => '2011-04-03 00:52:10',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=542',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'list-post',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 547,
  'post_date' => '2011-04-03 00:53:59',
  'post_date_gmt' => '2011-04-03 00:53:59',
  'post_content' => '',
  'post_title' => 'Full - 4 Column',
  'post_excerpt' => '',
  'post_name' => '4-column',
  'post_modified' => '2011-04-03 00:53:59',
  'post_modified_gmt' => '2011-04-03 00:53:59',
  'post_content_filtered' => '',
  'post_parent' => 542,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=547',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid4',
    'posts_per_page' => '8',
    'display_content' => 'none',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 551,
  'post_date' => '2011-04-03 00:56:20',
  'post_date_gmt' => '2011-04-03 00:56:20',
  'post_content' => '',
  'post_title' => 'Full - 3 Column',
  'post_excerpt' => '',
  'post_name' => '3-column',
  'post_modified' => '2011-04-03 00:56:20',
  'post_modified_gmt' => '2011-04-03 00:56:20',
  'post_content_filtered' => '',
  'post_parent' => 542,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=551',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'grid3',
    'posts_per_page' => '9',
    'display_content' => 'none',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 553,
  'post_date' => '2011-04-03 00:56:45',
  'post_date_gmt' => '2011-04-03 00:56:45',
  'post_content' => '',
  'post_title' => 'Full - 2 Column',
  'post_excerpt' => '',
  'post_name' => '2-column',
  'post_modified' => '2011-04-03 00:56:45',
  'post_modified_gmt' => '2011-04-03 00:56:45',
  'post_content_filtered' => '',
  'post_parent' => 542,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=553',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid2',
    'posts_per_page' => '8',
    'display_content' => 'none',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 555,
  'post_date' => '2011-04-03 00:57:19',
  'post_date_gmt' => '2011-04-03 00:57:19',
  'post_content' => '',
  'post_title' => 'Full - Large Image List',
  'post_excerpt' => '',
  'post_name' => 'large-image-list',
  'post_modified' => '2011-04-03 00:57:19',
  'post_modified_gmt' => '2011-04-03 00:57:19',
  'post_content_filtered' => '',
  'post_parent' => 542,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=555',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'list-large-image',
    'posts_per_page' => '5',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 558,
  'post_date' => '2011-04-03 00:59:31',
  'post_date_gmt' => '2011-04-03 00:59:31',
  'post_content' => '',
  'post_title' => 'Full - Thumb Image List',
  'post_excerpt' => '',
  'post_name' => 'thumb-image-list',
  'post_modified' => '2011-04-03 00:59:31',
  'post_modified_gmt' => '2011-04-03 00:59:31',
  'post_content_filtered' => '',
  'post_parent' => 542,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=557',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'list-thumb-image',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 559,
  'post_date' => '2011-04-03 01:00:03',
  'post_date_gmt' => '2011-04-03 01:00:03',
  'post_content' => '',
  'post_title' => 'Full - 2 Column Thumb',
  'post_excerpt' => '',
  'post_name' => '2-column-thumb',
  'post_modified' => '2011-04-03 01:00:03',
  'post_modified_gmt' => '2011-04-03 01:00:03',
  'post_content_filtered' => '',
  'post_parent' => 542,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=559',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid2-thumb',
    'posts_per_page' => '8',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 563,
  'post_date' => '2011-04-03 01:02:18',
  'post_date_gmt' => '2011-04-03 01:02:18',
  'post_content' => '',
  'post_title' => 'SB Left - 4 Column',
  'post_excerpt' => '',
  'post_name' => '4-column',
  'post_modified' => '2011-04-03 01:02:18',
  'post_modified_gmt' => '2011-04-03 01:02:18',
  'post_content_filtered' => '',
  'post_parent' => 561,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=563',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1 sidebar-left',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid4',
    'posts_per_page' => '8',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 566,
  'post_date' => '2011-04-03 01:03:05',
  'post_date_gmt' => '2011-04-03 01:03:05',
  'post_content' => '',
  'post_title' => 'SB Left - 3 Column',
  'post_excerpt' => '',
  'post_name' => '3-column',
  'post_modified' => '2011-04-03 01:03:05',
  'post_modified_gmt' => '2011-04-03 01:03:05',
  'post_content_filtered' => '',
  'post_parent' => 561,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=566',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1 sidebar-left',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid3',
    'posts_per_page' => '9',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 568,
  'post_date' => '2011-04-03 01:03:28',
  'post_date_gmt' => '2011-04-03 01:03:28',
  'post_content' => '',
  'post_title' => 'SB Left - 2 Column',
  'post_excerpt' => '',
  'post_name' => '2-column',
  'post_modified' => '2011-04-03 01:03:28',
  'post_modified_gmt' => '2011-04-03 01:03:28',
  'post_content_filtered' => '',
  'post_parent' => 561,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=568',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid2',
    'posts_per_page' => '8',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 570,
  'post_date' => '2011-04-03 01:03:48',
  'post_date_gmt' => '2011-04-03 01:03:48',
  'post_content' => '',
  'post_title' => 'SB Left - Large Image List',
  'post_excerpt' => '',
  'post_name' => 'large-image-list',
  'post_modified' => '2011-04-03 01:03:48',
  'post_modified_gmt' => '2011-04-03 01:03:48',
  'post_content_filtered' => '',
  'post_parent' => 561,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=570',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1 sidebar-left',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'list-large-image',
    'posts_per_page' => '5',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 572,
  'post_date' => '2011-04-03 01:04:11',
  'post_date_gmt' => '2011-04-03 01:04:11',
  'post_content' => '',
  'post_title' => 'SB Left - Thumb Image List',
  'post_excerpt' => '',
  'post_name' => 'thumb-image-list',
  'post_modified' => '2011-04-03 01:04:11',
  'post_modified_gmt' => '2011-04-03 01:04:11',
  'post_content_filtered' => '',
  'post_parent' => 561,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=572',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1 sidebar-left',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'list-thumb-image',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 574,
  'post_date' => '2011-04-03 01:08:16',
  'post_date_gmt' => '2011-04-03 01:08:16',
  'post_content' => '',
  'post_title' => 'SB Left - 2 Column Thumb',
  'post_excerpt' => '',
  'post_name' => '2-column-thumb',
  'post_modified' => '2011-04-03 01:08:16',
  'post_modified_gmt' => '2011-04-03 01:08:16',
  'post_content_filtered' => '',
  'post_parent' => 561,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=574',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1 sidebar-left',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid2-thumb',
    'posts_per_page' => '8',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 578,
  'post_date' => '2011-04-03 01:09:38',
  'post_date_gmt' => '2011-04-03 01:09:38',
  'post_content' => '',
  'post_title' => 'SB Right - 4 Column',
  'post_excerpt' => '',
  'post_name' => '4-column',
  'post_modified' => '2011-04-03 01:09:38',
  'post_modified_gmt' => '2011-04-03 01:09:38',
  'post_content_filtered' => '',
  'post_parent' => 576,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=578',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid4',
    'posts_per_page' => '8',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 580,
  'post_date' => '2011-04-03 01:10:05',
  'post_date_gmt' => '2011-04-03 01:10:05',
  'post_content' => '',
  'post_title' => 'SB Right - 3 Column',
  'post_excerpt' => '',
  'post_name' => '3-column',
  'post_modified' => '2011-04-03 01:10:05',
  'post_modified_gmt' => '2011-04-03 01:10:05',
  'post_content_filtered' => '',
  'post_parent' => 576,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=580',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid3',
    'posts_per_page' => '9',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 582,
  'post_date' => '2011-04-03 01:10:24',
  'post_date_gmt' => '2011-04-03 01:10:24',
  'post_content' => '',
  'post_title' => 'SB Right - 2 Column',
  'post_excerpt' => '',
  'post_name' => '2-column',
  'post_modified' => '2011-04-03 01:10:24',
  'post_modified_gmt' => '2011-04-03 01:10:24',
  'post_content_filtered' => '',
  'post_parent' => 576,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=582',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid2',
    'posts_per_page' => '8',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 584,
  'post_date' => '2011-04-03 01:10:47',
  'post_date_gmt' => '2011-04-03 01:10:47',
  'post_content' => '',
  'post_title' => 'SB Right - Large Image List',
  'post_excerpt' => '',
  'post_name' => 'large-image-list',
  'post_modified' => '2011-04-03 01:10:47',
  'post_modified_gmt' => '2011-04-03 01:10:47',
  'post_content_filtered' => '',
  'post_parent' => 576,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=584',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'list-large-image',
    'posts_per_page' => '5',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 592,
  'post_date' => '2011-04-03 01:11:13',
  'post_date_gmt' => '2011-04-03 01:11:13',
  'post_content' => '',
  'post_title' => 'SB Right - Thumb Image List',
  'post_excerpt' => '',
  'post_name' => 'thumb-image-list',
  'post_modified' => '2011-04-03 01:11:13',
  'post_modified_gmt' => '2011-04-03 01:11:13',
  'post_content_filtered' => '',
  'post_parent' => 576,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=586',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'list-thumb-image',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 593,
  'post_date' => '2011-04-03 01:11:50',
  'post_date_gmt' => '2011-04-03 01:11:50',
  'post_content' => '',
  'post_title' => 'SB Right - 2 Column Thumb',
  'post_excerpt' => '',
  'post_name' => '2-column-thumb',
  'post_modified' => '2011-04-03 01:11:50',
  'post_modified_gmt' => '2011-04-03 01:11:50',
  'post_content_filtered' => '',
  'post_parent' => 576,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=588',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'grid2-thumb',
    'posts_per_page' => '8',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 652,
  'post_date' => '2011-04-04 05:33:51',
  'post_date_gmt' => '2011-04-04 05:33:51',
  'post_content' => '',
  'post_title' => 'Section 4 Column',
  'post_excerpt' => '',
  'post_name' => 'section-4-column',
  'post_modified' => '2011-04-04 05:33:51',
  'post_modified_gmt' => '2011-04-04 05:33:51',
  'post_content_filtered' => '',
  'post_parent' => 662,
  'guid' => 'https://themify.me/demo/themes/blogfolio/?page_id=652',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'yes',
    'layout' => 'grid4',
    'posts_per_page' => '4',
    'display_content' => 'none',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'yes',
    'hide_image' => 'default',
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
  'ID' => 654,
  'post_date' => '2011-04-04 05:34:36',
  'post_date_gmt' => '2011-04-04 05:34:36',
  'post_content' => '',
  'post_title' => 'Section 3 Column',
  'post_excerpt' => '',
  'post_name' => 'section-3-column',
  'post_modified' => '2011-04-04 05:34:36',
  'post_modified_gmt' => '2011-04-04 05:34:36',
  'post_content_filtered' => '',
  'post_parent' => 662,
  'guid' => 'https://themify.me/demo/themes/blogfolio/?page_id=654',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'yes',
    'layout' => 'grid3',
    'posts_per_page' => '3',
    'display_content' => 'none',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'yes',
    'hide_image' => 'default',
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
  'ID' => 1497,
  'post_date' => '2013-06-14 20:52:50',
  'post_date_gmt' => '2013-06-14 20:52:50',
  'post_content' => '',
  'post_title' => 'Blog - 4-Column',
  'post_excerpt' => '',
  'post_name' => 'blog-4-column',
  'post_modified' => '2013-06-14 20:55:12',
  'post_modified_gmt' => '2013-06-14 20:55:12',
  'post_content_filtered' => '',
  'post_parent' => 1150,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1497',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'grid4',
    'display_content' => 'none',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
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
  'ID' => 1150,
  'post_date' => '2012-11-19 19:32:13',
  'post_date_gmt' => '2012-11-19 19:32:13',
  'post_content' => '',
  'post_title' => 'Blog',
  'post_excerpt' => '',
  'post_name' => 'blog',
  'post_modified' => '2013-06-28 23:33:35',
  'post_modified_gmt' => '2013-06-28 23:33:35',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1150',
  'menu_order' => 1,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-thumb-image',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-thumb-image\\"],\\"page_layout\\":[\\"default\\"],\\"hide_page_title\\":[\\"default\\"],\\"query_category\\":[\\"0\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"excerpt\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_edit_lock\\":[\\"1413824106:115\\"],\\"image_width\\":[\\"\\"],\\"image_height\\":[\\"\\"]}',
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
  'post_modified' => '2011-04-03 01:01:38',
  'post_modified_gmt' => '2011-04-03 01:01:38',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=561',
  'menu_order' => 1,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1 sidebar-left',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'list-post',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 1104,
  'post_date' => '2012-11-18 23:16:27',
  'post_date_gmt' => '2012-11-18 23:16:27',
  'post_content' => 'Use [ portfolio ] posts to display your work samples. You can choose between 4-column, 3-column, 2-column or fullwidth. The portfolio posts can be categorized. For example, you can display a list of "Theme Design" category and another list of "Illustration" category. Image, content, title, etc. can be toggled with parameters. If the portfolio post has gallery, it will shows a slider instead of a static featured image.

[portfolio style="grid4" limit="8" display="none" page_nav="yes"]',
  'post_title' => 'Portfolio',
  'post_excerpt' => '',
  'post_name' => 'portfolio',
  'post_modified' => '2012-11-21 22:29:34',
  'post_modified_gmt' => '2012-11-21 22:29:34',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1104',
  'menu_order' => 2,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_query_post_type\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_title\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"allow_sorting\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"hide_page_title\\":[\\"default\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"layout\\":[\\"list-post\\"],\\"_edit_last\\":[\\"2\\"],\\"_edit_lock\\":[\\"1455567008:115\\"]}',
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
  'post_modified' => '2011-04-03 01:09:14',
  'post_modified_gmt' => '2011-04-03 01:09:14',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/bizco/?page_id=576',
  'menu_order' => 2,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar1',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'layout' => 'list-post',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
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
  'ID' => 1129,
  'post_date' => '2012-11-19 19:00:56',
  'post_date_gmt' => '2012-11-19 19:00:56',
  'post_content' => 'Use [ team ] posts to display team members of your company. You can choose between 4-column, 3-column, 2-column or fullwidth. The team members can be categorized. For example, you can display a list of "Executive" category and another list of "General" category. Image, content, title, etc. can be toggled with parameters.
<h2>Executives</h2>
[team category="executive" style="grid2" limit="21" image_w="120" image_h="120" display="excerpt"]
<h2>General</h2>
[team category="general" style="grid4" limit="20" display="none"]',
  'post_title' => 'Team',
  'post_excerpt' => '',
  'post_name' => 'team',
  'post_modified' => '2012-11-21 22:33:16',
  'post_modified_gmt' => '2012-11-21 22:33:16',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1129',
  'menu_order' => 3,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"display_content\\":[\\"content\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"hide_page_title\\":[\\"default\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"layout\\":[\\"list-post\\"],\\"_edit_last\\":[\\"2\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_edit_lock\\":[\\"1403618037:90\\"]}',
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
  'ID' => 662,
  'post_date' => '2011-04-04 05:39:35',
  'post_date_gmt' => '2011-04-04 05:39:35',
  'post_content' => '',
  'post_title' => 'Sections',
  'post_excerpt' => '',
  'post_name' => 'sections',
  'post_modified' => '2011-04-04 05:39:35',
  'post_modified_gmt' => '2011-04-04 05:39:35',
  'post_content_filtered' => '',
  'post_parent' => 409,
  'guid' => 'https://themify.me/demo/themes/blogfolio/?page_id=662',
  'menu_order' => 3,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'yes',
    'layout' => 'list-thumb-image',
    'posts_per_page' => '2',
    'display_content' => 'excerpt',
    'hide_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'yes',
    'hide_image' => 'default',
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
  'ID' => 1171,
  'post_date' => '2012-11-19 20:38:22',
  'post_date_gmt' => '2012-11-19 20:38:22',
  'post_content' => 'Use [ highlight ] posts to display short list items with icon such as feature list or services. You can choose between 4-column, 3-column, 2-column or fullwidth. For examples: you can display a 3-column list from "Services" category and a 4-column list from "Features" category.

[highlight category="services" style="grid3" limit="3" image_w="90" image_h="90"]
<h2>Features</h2>
[highlight category="features" style="grid3" limit="6" image_w="70" image_h="70"]',
  'post_title' => 'Services',
  'post_excerpt' => '',
  'post_name' => 'services',
  'post_modified' => '2012-11-21 22:32:27',
  'post_modified_gmt' => '2012-11-21 22:32:27',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1171',
  'menu_order' => 4,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-post\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"hide_page_title\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_edit_lock\\":[\\"1370448828:32\\"]}',
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
  'ID' => 1272,
  'post_date' => '2012-11-21 03:19:29',
  'post_date_gmt' => '2012-11-21 03:19:29',
  'post_content' => 'Download this contact plugin: <a href="http://wordpress.org/extend/plugins/contact-form-7">Contact Form 7</a>.

[contact-form-7 id="1273" title="Untitled"]',
  'post_title' => 'Contact',
  'post_excerpt' => '',
  'post_name' => 'contact',
  'post_modified' => '2012-11-21 03:19:29',
  'post_modified_gmt' => '2012-11-21 03:19:29',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1272',
  'menu_order' => 5,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-post\\"],\\"page_layout\\":[\\"default\\"],\\"hide_page_title\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_edit_lock\\":[\\"1418068259:113\\"]}',
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
  'ID' => 1054,
  'post_date' => '2012-11-18 20:13:36',
  'post_date_gmt' => '2012-11-18 20:13:36',
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
[map address="Yonge St. and Eglinton Ave, Toronto, Ontario, Canada" width=100% height=400px]',
  'post_title' => 'Shortcodes',
  'post_excerpt' => '',
  'post_name' => 'shortcodes',
  'post_modified' => '2012-11-18 20:13:36',
  'post_modified_gmt' => '2012-11-18 20:13:36',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1054',
  'menu_order' => 6,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
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
  'ID' => 1146,
  'post_date' => '2012-11-19 19:24:02',
  'post_date_gmt' => '2012-11-19 19:24:02',
  'post_content' => 'Vivamus imperdiet diam ac tortor tempus posuere. Curabitur at arcu id turpis posuere bibendum. Sed commodo mauris eget diam pretium cursus. In sagittis feugiat mauris, in ultrices mauris lacinia eu. Fusce augue velit, vulputate elementum semper congue, rhoncus adipiscing nisl. Curabitur vel risus eros, sed eleifend arcu. Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis massa.

[testimonial style="grid2" limit="20" title="yes"]',
  'post_title' => 'Testimonials',
  'post_excerpt' => '',
  'post_name' => 'testimonials',
  'post_modified' => '2012-11-22 01:08:29',
  'post_modified_gmt' => '2012-11-22 01:08:29',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?page_id=1146',
  'menu_order' => 7,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'hide_page_title' => 'default',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-post',
    'display_content' => 'content',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
    'hide_image' => 'default',
    'unlink_image' => 'default',
    'hide_navigation' => 'default',
    '_themify_builder_settings_json' => '{\\"_edit_last\\":[\\"2\\"],\\"layout\\":[\\"list-post\\"],\\"page_layout\\":[\\"sidebar-none\\"],\\"hide_page_title\\":[\\"default\\"],\\"section_categories\\":[\\"default\\"],\\"allow_sorting\\":[\\"default\\"],\\"display_content\\":[\\"content\\"],\\"hide_title\\":[\\"default\\"],\\"unlink_title\\":[\\"default\\"],\\"hide_date\\":[\\"default\\"],\\"hide_meta\\":[\\"default\\"],\\"hide_image\\":[\\"default\\"],\\"unlink_image\\":[\\"default\\"],\\"hide_navigation\\":[\\"default\\"],\\"_edit_lock\\":[\\"1353546904:2\\"]}',
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
  'post_title' => 'Blog Layouts',
  'post_excerpt' => '',
  'post_name' => 'layouts',
  'post_modified' => '2013-01-09 19:04:25',
  'post_modified_gmt' => '2013-01-09 19:04:25',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'http://demo.themify.me/bizco',
  'menu_order' => 8,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'default',
    'hide_page_title' => 'default',
    'query_category' => '0',
    'section_categories' => 'default',
    'allow_sorting' => 'default',
    'layout' => 'list-thumb-image',
    'display_content' => 'excerpt',
    'image_width' => '240',
    'image_height' => '160',
    'hide_title' => 'default',
    'unlink_title' => 'default',
    'hide_date' => 'default',
    'hide_meta' => 'default',
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
  'ID' => 13,
  'post_date' => '2012-11-11 18:29:05',
  'post_date_gmt' => '2012-11-11 18:29:05',
  'post_content' => 'Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Nam risus velit, rhoncus eget consectetur id, posuere at ligula. Vivamus imperdiet diam ac tortor tempus posuere.

[button link="https://themify.me/themes/agency"]Buy This Theme[/button]',
  'post_title' => 'Awesome Slider',
  'post_excerpt' => '',
  'post_name' => 'awesome-slider',
  'post_modified' => '2017-10-27 17:22:26',
  'post_modified_gmt' => '2017-10-27 17:22:26',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=slider&#038;p=13',
  'menu_order' => 0,
  'post_type' => 'slider',
  'meta_input' => 
  array (
    'layout' => 'slider-default',
    'image_width' => '500',
    'image_height' => '0',
  ),
  'tax_input' => 
  array (
    'slider-category' => 'slider',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 141,
  'post_date' => '2012-10-12 17:19:18',
  'post_date_gmt' => '2012-10-12 17:19:18',
  'post_content' => 'In tue dolor fringilla lacus, ut rho. Sed sagittis, elit egestas rutrum vehicula, neqncus turpis augue vitae libero. Nam risus velit, rhoncus eget consectetur id, posuere at ligula. Vivamus imperdiet diam ac tortor tempus posuere.',
  'post_title' => 'Video Slide',
  'post_excerpt' => '',
  'post_name' => 'video-slide',
  'post_modified' => '2017-10-27 17:22:26',
  'post_modified_gmt' => '2017-10-27 17:22:26',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=slider&#038;p=141',
  'menu_order' => 0,
  'post_type' => 'slider',
  'meta_input' => 
  array (
    'layout' => 'slider-default',
    'video_url' => 'http://vimeo.com/6929537',
  ),
  'tax_input' => 
  array (
    'slider-category' => 'slider',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1288,
  'post_date' => '2012-09-21 03:26:37',
  'post_date_gmt' => '2012-09-21 03:26:37',
  'post_content' => 'The image caption is egestas rutrum vehicula, the get consectetur id, posuere at ligula. Vivamus imperdiet diam ac tortor tempus posuer. Whaz it neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Nam risus velit, rhoncus eget consectetur id, posuere at ligula. Vivamus imperdiet diam ac tortor tempus posuere.',
  'post_title' => 'Image Caption',
  'post_excerpt' => '',
  'post_name' => 'image-caption',
  'post_modified' => '2017-10-27 17:22:26',
  'post_modified_gmt' => '2017-10-27 17:22:26',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=slider&#038;p=1288',
  'menu_order' => 0,
  'post_type' => 'slider',
  'meta_input' => 
  array (
    'layout' => 'slider-image-caption',
    'image_width' => '978',
    'image_height' => '400',
  ),
  'tax_input' => 
  array (
    'slider-category' => 'slider',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1319,
  'post_date' => '2012-08-21 05:10:28',
  'post_date_gmt' => '2012-08-21 05:10:28',
  'post_content' => 'Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis dignissim non tortor.

[gallery link="file" columns="1" order="DESC"]',
  'post_title' => 'Gallery Slide',
  'post_excerpt' => '',
  'post_name' => 'gallery-slide',
  'post_modified' => '2017-10-27 17:22:26',
  'post_modified_gmt' => '2017-10-27 17:22:26',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=slider&#038;p=1319',
  'menu_order' => 0,
  'post_type' => 'slider',
  'meta_input' => 
  array (
    'layout' => 'slider-default',
    'image_width' => '500',
    'image_height' => '400',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'slider-category' => 'slider',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 30,
  'post_date' => '2012-11-11 19:43:35',
  'post_date_gmt' => '2012-11-11 19:43:35',
  'post_content' => 'Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis tincidunt eget, dignissim non tortor.',
  'post_title' => 'Black & White',
  'post_excerpt' => '',
  'post_name' => 'black-white',
  'post_modified' => '2017-08-23 02:56:33',
  'post_modified_gmt' => '2017-08-23 02:56:33',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=30',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_gallery_shortcode' => '[gallery link="file" columns="1" ids="35,34,33,32,31"]',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'featured, galleries',
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
  'post_date' => '2012-11-11 19:52:16',
  'post_date_gmt' => '2012-11-11 19:52:16',
  'post_content' => 'Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero. Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod.',
  'post_title' => 'Classy',
  'post_excerpt' => '',
  'post_name' => 'classy',
  'post_modified' => '2017-08-23 02:56:31',
  'post_modified_gmt' => '2017-08-23 02:56:31',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=38',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_gallery_shortcode' => '[gallery link="file" columns="1" ids="42,41,40,39"]',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'galleries',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 43,
  'post_date' => '2012-11-11 19:54:06',
  'post_date_gmt' => '2012-11-11 19:54:06',
  'post_content' => 'Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero. Sed sed luctus purus.',
  'post_title' => 'Retro',
  'post_excerpt' => '',
  'post_name' => 'retro',
  'post_modified' => '2017-08-23 02:56:30',
  'post_modified_gmt' => '2017-08-23 02:56:30',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=43',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_gallery_shortcode' => '[gallery link="file" columns="1" ids="47,46,45,44" link="file"]',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'galleries',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 49,
  'post_date' => '2012-11-11 19:58:37',
  'post_date_gmt' => '2012-11-11 19:58:37',
  'post_content' => 'Sed ultrices felis ut justo suscipit vestibulum. Pellentesque nisl nisi, vehicula vitae hendrerit vel, mattis eget mauris. Donec consequat eros eget lectus dictum sit amet ultrices neque sodales. Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac.',
  'post_title' => 'Vintage',
  'post_excerpt' => '',
  'post_name' => 'vintage',
  'post_modified' => '2017-08-23 02:56:27',
  'post_modified_gmt' => '2017-08-23 02:56:27',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=49',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_gallery_shortcode' => '[gallery link="file" columns="1" ids="53,52,51,50"]',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'featured, galleries',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 55,
  'post_date' => '2012-10-29 20:06:35',
  'post_date_gmt' => '2012-10-29 20:06:35',
  'post_content' => 'Curabitur vitae velit ligula, vitae lobortis massa. Mauris mattis est quis dolor venenatis vitae pharetra diam gravida. Vivamus dignissim, ligula vel ultricies varius, nibh velit pretium leo, vel placerat ipsum risus luctus purus. Nullam rutrum quam ut massa ultricies sed blandit sapien fermentum. Curabitur venenatis vehicula mattis. Nunc eleifend consectetur odio sit amet viverra. Ut euismod ligula eu tellus interdum mattis ac eu nulla. Phasellus cursus, lacus quis convallis aliquet, dolor urna ullamcorper mi, eget dapibus velit est vitae nisi. Aliquam erat nulla, sodales at imperdiet vitae, convallis vel dui.',
  'post_title' => 'Postline',
  'post_excerpt' => 'Facebook\'s Timeline Theme',
  'post_name' => 'postline',
  'post_modified' => '2017-08-23 02:56:48',
  'post_modified_gmt' => '2017-08-23 02:56:48',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=55',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'featured, themes',
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
  'post_date' => '2012-10-29 20:16:24',
  'post_date_gmt' => '2012-10-29 20:16:24',
  'post_content' => 'Fusce augue velit, vulputate elementum semper congue, rhoncus adipiscing nisl. Curabitur vel risus eros, sed eleifend arcu. Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis massa. Mauris mattis est quis dolor venenatis vitae pharetra diam gravida. Vivamus dignissim, ligula vel ultricies varius, nibh velit pretium leo, vel placerat ipsum risus luctus purus. Nullam rutrum quam ut massa ultricies sed blandit sapien fermentum. Curabitur venenatis vehicula mattis. Nunc eleifend consectetur odio sit amet viverra. Ut euismod ligula eu tellus interdum mattis ac eu nulla.',
  'post_title' => 'Fullscreen',
  'post_excerpt' => 'Responsive fullscreen gallery',
  'post_name' => 'fullscreen',
  'post_modified' => '2017-08-23 02:56:46',
  'post_modified_gmt' => '2017-08-23 02:56:46',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=59',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'themes',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 61,
  'post_date' => '2012-10-29 20:17:12',
  'post_date_gmt' => '2012-10-29 20:17:12',
  'post_content' => 'In sagittis feugiat mauris, in ultrices mauris lacinia eu. Fusce augue velit, vulputate elementum semper congue, rhoncus adipiscing nisl. Curabitur vel risus eros, sed eleifend arcu. Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis massa. Mauris mattis est quis dolor venenatis vitae pharetra diam gravida. Vivamus dignissim, ligula vel ultricies varius, nibh velit pretium leo, vel placerat ipsum risus luctus purus. Nullam rutrum quam ut massa ultricies sed bleat.',
  'post_title' => 'PhotoTouch',
  'post_excerpt' => 'Photo swipe gallery theme',
  'post_name' => 'phototouch',
  'post_modified' => '2017-08-23 02:56:43',
  'post_modified_gmt' => '2017-08-23 02:56:43',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=61',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'themes',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 63,
  'post_date' => '2012-10-29 20:17:54',
  'post_date_gmt' => '2012-10-29 20:17:54',
  'post_content' => 'Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Nam risus velit, rhoncus eget consectetur id, posuere at ligula. Vivamus imperdiet diam ac tortor tempus posuere. Curabitur at arcu id turpis posuere bibendum. Sed commodo mauris eget diam pretium cursus. In sagittis feugiat mauris, in ultrices mauris lacinia eu. Fusce augue velit, vulputate elementum semper congue, rhoncus adipiscing nisl. Curabitur vel risus eros, sed eleifend arcu. Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis massa. Mauris mattis est quis dolor venenatis vitae pharetra diam gravida.',
  'post_title' => 'ShopDock',
  'post_excerpt' => 'Ajax ecommerce theme',
  'post_name' => 'shopdock',
  'post_modified' => '2017-08-23 02:56:41',
  'post_modified_gmt' => '2017-08-23 02:56:41',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=63',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'themes',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 91,
  'post_date' => '2012-11-01 22:30:41',
  'post_date_gmt' => '2012-11-01 22:30:41',
  'post_content' => 'Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis massa. Mauris mattis est quis dolor venenatis vitae pharetra diam gravida. Vivamus dignissim, ligula vel ultricies varius, nibh velit pretium leo, vel placerat ipsum risus luctus purus. Nullam rutrum quam ut massa ultricies sed blandit sapien fermentum. Curabitur venenatis vehicula mattis. Nunc eleifend consectetur odio sit amet viverra. Ut euismod ligula eu tellus interdum mattis ac eu nulla. Phasellus cursus, lacus quis convallis aliquet.',
  'post_title' => 'Koi Illustration',
  'post_excerpt' => '',
  'post_name' => 'koi-illustration',
  'post_modified' => '2017-08-23 02:56:38',
  'post_modified_gmt' => '2017-08-23 02:56:38',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=91',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'featured, illustrations',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1081,
  'post_date' => '2012-11-01 20:39:59',
  'post_date_gmt' => '2012-11-01 20:39:59',
  'post_content' => 'Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac congue non egestas quis, condimentum ut arcu arcu. The piscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc.',
  'post_title' => 'Abstract Peacock Illustration',
  'post_excerpt' => '',
  'post_name' => 'koi-illustration-2',
  'post_modified' => '2017-08-23 02:56:40',
  'post_modified_gmt' => '2017-08-23 02:56:40',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=1081',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'illustrations',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 97,
  'post_date' => '2012-11-01 22:41:15',
  'post_date_gmt' => '2012-11-01 22:41:15',
  'post_content' => 'Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero.',
  'post_title' => 'Phoenix Illustration',
  'post_excerpt' => '',
  'post_name' => 'phoenix-illustration',
  'post_modified' => '2017-08-23 02:56:37',
  'post_modified_gmt' => '2017-08-23 02:56:37',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=97',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'illustrations',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 100,
  'post_date' => '2012-11-01 22:43:23',
  'post_date_gmt' => '2012-11-01 22:43:23',
  'post_content' => 'Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero.',
  'post_title' => 'Poster Design',
  'post_excerpt' => '',
  'post_name' => 'poster-design',
  'post_modified' => '2017-08-23 02:56:35',
  'post_modified_gmt' => '2017-08-23 02:56:35',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=100',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'illustrations',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1089,
  'post_date' => '2012-09-18 20:49:59',
  'post_date_gmt' => '2012-09-18 20:49:59',
  'post_content' => 'Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis tincidunt eget, dignissim non tortor.',
  'post_title' => 'Just a Photo',
  'post_excerpt' => '',
  'post_name' => 'just-a-photo',
  'post_modified' => '2017-08-23 02:56:54',
  'post_modified_gmt' => '2017-08-23 02:56:54',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=1089',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'photos',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1091,
  'post_date' => '2012-09-18 20:51:12',
  'post_date_gmt' => '2012-09-18 20:51:12',
  'post_content' => 'Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero. Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu.',
  'post_title' => 'Photo Two',
  'post_excerpt' => '',
  'post_name' => 'photo-two',
  'post_modified' => '2017-08-23 02:56:53',
  'post_modified_gmt' => '2017-08-23 02:56:53',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=1091',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'photos',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1093,
  'post_date' => '2012-09-18 20:52:05',
  'post_date_gmt' => '2012-09-18 20:52:05',
  'post_content' => 'Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac. Vestibulum congue nisl magna.',
  'post_title' => 'Shot Number Three',
  'post_excerpt' => '',
  'post_name' => 'shot-number-three',
  'post_modified' => '2017-08-23 02:56:51',
  'post_modified_gmt' => '2017-08-23 02:56:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=1093',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'photos',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1095,
  'post_date' => '2012-09-18 20:52:37',
  'post_date_gmt' => '2012-09-18 20:52:37',
  'post_content' => 'Ut euismod ligula eu tellus interdum mattis ac eu nulla. Phasellus cursus, lacus quis convallis aliquet, dolor urna ullamcorper mi, eget dapibus velit est vitae nisi. Aliquam erat nulla, sodales at imperdiet vitae, convallis vel dui. Sed ultrices felis ut justo suscipit vestibulum. Pellentesque nisl nisi, vehicula vitae hendrerit vel, mattis eget mauris. Donec consequat eros eget lectus dictum sit amet ultrices neque sodales. Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis.',
  'post_title' => 'Beautiful Shot',
  'post_excerpt' => '',
  'post_name' => 'beautiful-shot',
  'post_modified' => '2017-08-23 02:56:50',
  'post_modified_gmt' => '2017-08-23 02:56:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=portfolio&#038;p=1095',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    'hide_post_title' => 'default',
    'unlink_post_title' => 'default',
    'hide_post_date' => 'default',
    'hide_post_meta' => 'default',
    'hide_post_image' => 'default',
    'unlink_post_image' => 'default',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'photos',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 121,
  'post_date' => '2012-10-01 23:09:29',
  'post_date_gmt' => '2012-10-01 23:09:29',
  'post_content' => 'Donec consequat eros eget lectus dictum sit amet ultrices neque sodales liquam metus diam.',
  'post_title' => 'SEO',
  'post_excerpt' => '',
  'post_name' => 'seo',
  'post_modified' => '2017-08-23 02:58:02',
  'post_modified_gmt' => '2017-08-23 02:58:02',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=121',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 123,
  'post_date' => '2012-10-02 23:10:02',
  'post_date_gmt' => '2012-10-02 23:10:02',
  'post_content' => 'In gravida arcu ut neque ornare vitae sem mollis metus rutrum non malesuada metus fermentum.',
  'post_title' => 'eCommerce',
  'post_excerpt' => '',
  'post_name' => 'ecommerce',
  'post_modified' => '2017-08-23 02:58:00',
  'post_modified_gmt' => '2017-08-23 02:58:00',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=123',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 126,
  'post_date' => '2012-10-03 23:11:57',
  'post_date_gmt' => '2012-10-03 23:11:57',
  'post_content' => 'Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condiment.',
  'post_title' => 'Security',
  'post_excerpt' => '',
  'post_name' => 'security',
  'post_modified' => '2017-08-23 02:57:58',
  'post_modified_gmt' => '2017-08-23 02:57:58',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=126',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 128,
  'post_date' => '2012-10-04 23:12:56',
  'post_date_gmt' => '2012-10-04 23:12:56',
  'post_content' => 'Sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis.',
  'post_title' => 'Invoice & Reports',
  'post_excerpt' => '',
  'post_name' => 'invoice-reports',
  'post_modified' => '2017-08-23 02:57:56',
  'post_modified_gmt' => '2017-08-23 02:57:56',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=128',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 130,
  'post_date' => '2012-10-06 23:14:22',
  'post_date_gmt' => '2012-10-06 23:14:22',
  'post_content' => 'Aliquam metus diam, mattis fringilla adi of the piscing at, lacinia at nulla. Fusce ut sem est.',
  'post_title' => 'Tablet',
  'post_excerpt' => '',
  'post_name' => 'tablet',
  'post_modified' => '2017-08-23 02:57:55',
  'post_modified_gmt' => '2017-08-23 02:57:55',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=130',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 132,
  'post_date' => '2012-10-07 23:15:08',
  'post_date_gmt' => '2012-10-07 23:15:08',
  'post_content' => 'Aliquam erat nulla, sodales at imperdiet vitae, convallis vel des felis ut justo suscipit vestibulum.',
  'post_title' => 'Online Backup',
  'post_excerpt' => '',
  'post_name' => 'online-backup',
  'post_modified' => '2017-08-23 02:57:53',
  'post_modified_gmt' => '2017-08-23 02:57:53',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=132',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 134,
  'post_date' => '2012-09-01 23:16:04',
  'post_date_gmt' => '2012-09-01 23:16:04',
  'post_content' => 'Aliquam erat nulla, sodales at imperdiet vitae, convallisd ultrices felis ut justo suscipit vestibulum.',
  'post_title' => 'Android Compatible',
  'post_excerpt' => '',
  'post_name' => 'android-compatible',
  'post_modified' => '2017-08-23 02:58:06',
  'post_modified_gmt' => '2017-08-23 02:58:06',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=134',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 137,
  'post_date' => '2012-09-02 23:24:50',
  'post_date_gmt' => '2012-09-02 23:24:50',
  'post_content' => 'Sed sagittis, elit egestas rutrum vehicul ue dolor fringilla lacus, ut rhoncus turpis augue vitae libero.',
  'post_title' => 'iOS App',
  'post_excerpt' => '',
  'post_name' => 'ios-app',
  'post_modified' => '2017-08-23 02:58:03',
  'post_modified_gmt' => '2017-08-23 02:58:03',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=137',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1019,
  'post_date' => '2012-11-12 22:50:30',
  'post_date_gmt' => '2012-11-12 22:50:30',
  'post_content' => 'Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis.',
  'post_title' => 'Auto Sync',
  'post_excerpt' => '',
  'post_name' => 'wordpress-support',
  'post_modified' => '2017-08-23 02:57:46',
  'post_modified_gmt' => '2017-08-23 02:57:46',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=1019',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
    'external_link' => 'https://themify.me/',
    'builder_switch_frontend' => '0',
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1015,
  'post_date' => '2012-11-12 22:48:21',
  'post_date_gmt' => '2012-11-12 22:48:21',
  'post_content' => 'In gravida arcu ut neque ornare vitae rut primis in faucibus to rpri mis in faucibus um turpis vehicula.',
  'post_title' => 'Geo Locator',
  'post_excerpt' => '',
  'post_name' => 'geo-locator',
  'post_modified' => '2017-08-23 02:57:49',
  'post_modified_gmt' => '2017-08-23 02:57:49',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=1015',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1017,
  'post_date' => '2012-11-12 22:49:47',
  'post_date_gmt' => '2012-11-12 22:49:47',
  'post_content' => 'Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis dignissim non tortor.',
  'post_title' => 'Credit Cards',
  'post_excerpt' => '',
  'post_name' => 'credit-cards',
  'post_modified' => '2017-08-23 02:57:48',
  'post_modified_gmt' => '2017-08-23 02:57:48',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=1017',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1012,
  'post_date' => '2012-11-12 22:44:05',
  'post_date_gmt' => '2012-11-12 22:44:05',
  'post_content' => 'Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero.',
  'post_title' => 'App Store',
  'post_excerpt' => '',
  'post_name' => 'app-store',
  'post_modified' => '2017-08-23 02:57:51',
  'post_modified_gmt' => '2017-08-23 02:57:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=1012',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2075,
  'post_date' => '2008-10-03 23:11:57',
  'post_date_gmt' => '2008-10-03 23:11:57',
  'post_content' => 'Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condiment.',
  'post_title' => 'Security',
  'post_excerpt' => '',
  'post_name' => 'security-2',
  'post_modified' => '2017-08-23 02:58:19',
  'post_modified_gmt' => '2017-08-23 02:58:19',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=126',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2076,
  'post_date' => '2008-10-04 23:12:56',
  'post_date_gmt' => '2008-10-04 23:12:56',
  'post_content' => 'Sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis.',
  'post_title' => 'Invoice &amp; Reports',
  'post_excerpt' => '',
  'post_name' => 'invoice-reports-2',
  'post_modified' => '2017-08-23 02:58:17',
  'post_modified_gmt' => '2017-08-23 02:58:17',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=128',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2077,
  'post_date' => '2008-10-07 23:15:08',
  'post_date_gmt' => '2008-10-07 23:15:08',
  'post_content' => 'Aliquam erat nulla, sodales at imperdiet vitae, convallis vel des felis ut justo suscipit vestibulum.',
  'post_title' => 'Online Backup',
  'post_excerpt' => '',
  'post_name' => 'online-backup-2',
  'post_modified' => '2017-08-23 02:58:14',
  'post_modified_gmt' => '2017-08-23 02:58:14',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=132',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2080,
  'post_date' => '2008-11-12 22:44:05',
  'post_date_gmt' => '2008-11-12 22:44:05',
  'post_content' => 'Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero.',
  'post_title' => 'App Store',
  'post_excerpt' => '',
  'post_name' => 'app-store-2',
  'post_modified' => '2017-08-23 02:58:12',
  'post_modified_gmt' => '2017-08-23 02:58:12',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=1012',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2081,
  'post_date' => '2008-11-12 22:48:21',
  'post_date_gmt' => '2008-11-12 22:48:21',
  'post_content' => 'In gravida arcu ut neque ornare vitae rut primis in faucibus to rpri mis in faucibus um turpis vehicula.',
  'post_title' => 'Geo Locator',
  'post_excerpt' => '',
  'post_name' => 'geo-locator-2',
  'post_modified' => '2017-08-23 02:58:10',
  'post_modified_gmt' => '2017-08-23 02:58:10',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=1015',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2082,
  'post_date' => '2008-11-12 22:49:47',
  'post_date_gmt' => '2008-11-12 22:49:47',
  'post_content' => 'Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis dignissim non tortor.',
  'post_title' => 'Credit Cards',
  'post_excerpt' => '',
  'post_name' => 'credit-cards-2',
  'post_modified' => '2017-08-23 02:58:08',
  'post_modified_gmt' => '2017-08-23 02:58:08',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=1017',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2083,
  'post_date' => '2008-11-12 22:50:30',
  'post_date_gmt' => '2008-11-12 22:50:30',
  'post_content' => 'Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis.',
  'post_title' => 'Auto Sync',
  'post_excerpt' => '',
  'post_name' => 'wordpress-support-2',
  'post_modified' => '2017-08-23 02:58:07',
  'post_modified_gmt' => '2017-08-23 02:58:07',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=1019',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'features',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2085,
  'post_date' => '2008-10-06 23:14:22',
  'post_date_gmt' => '2008-10-06 23:14:22',
  'post_content' => 'Aliquam metus diam, mattis fringilla adi of the piscing at, lacinia at nulla. Fusce ut sem est.',
  'post_title' => 'Tablet',
  'post_excerpt' => '',
  'post_name' => 'tablet-2',
  'post_modified' => '2017-08-23 02:58:16',
  'post_modified_gmt' => '2017-08-23 02:58:16',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=highlight&#038;p=130',
  'menu_order' => 0,
  'post_type' => 'highlight',
  'meta_input' => 
  array (
  ),
  'tax_input' => 
  array (
    'highlight-category' => 'services',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 24,
  'post_date' => '2012-11-02 19:36:13',
  'post_date_gmt' => '2012-11-02 19:36:13',
  'post_content' => 'Curabitur at arcu id turpis posuere bibendum. Sed commodo mauris eget diam pretium cursus. In gravida arcu ut neque ornare vitae rutrum turpis vehicula.',
  'post_title' => 'Best Services',
  'post_excerpt' => '',
  'post_name' => 'best-services',
  'post_modified' => '2017-08-23 02:59:09',
  'post_modified_gmt' => '2017-08-23 02:59:09',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=24',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Jessica',
    '_testimonial_link' => 'https://themify.me',
    '_testimonial_company' => 'Themify',
    '_testimonial_position' => 'Manager',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 27,
  'post_date' => '2012-11-02 19:39:01',
  'post_date_gmt' => '2012-11-02 19:39:01',
  'post_content' => 'Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis tincidunt eget, dignissim non tortor.',
  'post_title' => 'Extremely Happy',
  'post_excerpt' => '',
  'post_name' => 'extremely-happy',
  'post_modified' => '2017-08-23 02:59:07',
  'post_modified_gmt' => '2017-08-23 02:59:07',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=27',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'April',
    '_testimonial_link' => 'http://icondock.com',
    '_testimonial_company' => 'IconDock',
    '_testimonial_position' => 'Designer',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 66,
  'post_date' => '2012-11-09 20:28:43',
  'post_date_gmt' => '2012-11-09 20:28:43',
  'post_content' => 'Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu.',
  'post_title' => 'Super Awesome!',
  'post_excerpt' => '',
  'post_name' => 'super-awesome',
  'post_modified' => '2017-08-23 02:59:05',
  'post_modified_gmt' => '2017-08-23 02:59:05',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=66',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Lisa',
    '_testimonial_company' => 'Company',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 70,
  'post_date' => '2012-11-01 00:06:47',
  'post_date_gmt' => '2012-11-01 00:06:47',
  'post_content' => 'Vestibulum congue nisl magna. Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero.',
  'post_title' => 'Great Support',
  'post_excerpt' => '',
  'post_name' => 'great-support',
  'post_modified' => '2017-08-23 02:59:11',
  'post_modified_gmt' => '2017-08-23 02:59:11',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=70',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Natalie',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1152,
  'post_date' => '2012-11-19 19:58:11',
  'post_date_gmt' => '2012-11-19 19:58:11',
  'post_content' => 'Mauris mattis est quis dolor venenatis vitae pharetra diam gravida. Vivamus dignissim, ligula vel ultricies varius, nibh velit pretium leo, vel placerat ipsum risus luctus purus.',
  'post_title' => 'Best Services in Town!',
  'post_excerpt' => '',
  'post_name' => 'best-services-in-town',
  'post_modified' => '2017-08-23 02:59:04',
  'post_modified_gmt' => '2017-08-23 02:59:04',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=1152',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Eva',
    '_testimonial_link' => 'https://themify.me',
    '_testimonial_company' => 'A & D',
    '_testimonial_position' => 'Manager',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1154,
  'post_date' => '2012-10-19 19:59:04',
  'post_date_gmt' => '2012-10-19 19:59:04',
  'post_content' => 'Pellentesque nisl nisi, vehicula vitae hendrerit vel, mattis eget mauris. Donec consequat eros eget lectus dictum sit amet ultrices neque sod quat eros eget lectus dictum sit amet ultrices in the ales.',
  'post_title' => 'Lucky We Found You',
  'post_excerpt' => '',
  'post_name' => 'lucky-we-found-you',
  'post_modified' => '2017-08-23 02:59:15',
  'post_modified_gmt' => '2017-08-23 02:59:15',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=1154',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Michelle',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1156,
  'post_date' => '2012-11-19 19:59:53',
  'post_date_gmt' => '2012-11-19 19:59:53',
  'post_content' => 'Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum tu. Cras a fringilla nunc. Suspendisse volutpat, eros cong rpis vehicula.',
  'post_title' => 'Exceeded Our Expectation',
  'post_excerpt' => '',
  'post_name' => 'exceeded-our-expectation',
  'post_modified' => '2017-08-23 02:59:01',
  'post_modified_gmt' => '2017-08-23 02:59:01',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=1156',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Rachel',
    '_testimonial_company' => 'Flower Shop',
    '_testimonial_position' => 'Owner',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1162,
  'post_date' => '2012-10-19 20:05:25',
  'post_date_gmt' => '2012-10-19 20:05:25',
  'post_content' => 'Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis tincidunt eget, dignissim non tortor.',
  'post_title' => 'Great Services',
  'post_excerpt' => '',
  'post_name' => 'great-services',
  'post_modified' => '2017-08-23 02:59:13',
  'post_modified_gmt' => '2017-08-23 02:59:13',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=1162',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Sharon',
    '_testimonial_company' => 'Social Company',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2108,
  'post_date' => '2008-06-11 21:26:15',
  'post_date_gmt' => '2008-06-11 21:26:15',
  'post_content' => 'Fusce ultrices placerat sem at rutrum. Etiam bibendum ac sapien in vulputate. Maecenas commodo elementum gravida. Vivamus odio odio, pulvinar vel leo id, fringilla ullamcorper odio.',
  'post_title' => 'Carl Schmidt',
  'post_excerpt' => '',
  'post_name' => 'carl-schmidt',
  'post_modified' => '2017-08-23 02:59:28',
  'post_modified_gmt' => '2017-08-23 02:59:28',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?post_type=testimonial&#038;p=59',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Carl Schmidt',
    '_testimonial_link' => 'http://themify.com',
    '_testimonial_company' => 'Themify',
    '_testimonial_position' => 'HR Manager',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'team',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2109,
  'post_date' => '2008-06-11 21:28:42',
  'post_date_gmt' => '2008-06-11 21:28:42',
  'post_content' => 'Sed volutpat tristique metus eget suscipit. Donec aliquam eget purus id cursus. Integer ut arcu scelerisque, porttitor eros nec, placerat eros.',
  'post_title' => 'Clara Ray',
  'post_excerpt' => '',
  'post_name' => 'clara-ray',
  'post_modified' => '2017-08-23 02:59:26',
  'post_modified_gmt' => '2017-08-23 02:59:26',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?post_type=testimonial&#038;p=61',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Clara Ray',
    '_testimonial_link' => 'http://themify.com',
    '_testimonial_company' => 'Themify',
    '_testimonial_position' => 'Vice President',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'team',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2110,
  'post_date' => '2008-06-11 21:31:55',
  'post_date_gmt' => '2008-06-11 21:31:55',
  'post_content' => 'Maecenas in orci nunc. Curabitur velit sapien, mollis vel aliquam et, dignissim consequat eros. Curabitur egestas quam dapibus arcu egestas mollis.',
  'post_title' => 'Diana Jones',
  'post_excerpt' => '',
  'post_name' => 'diana-jones',
  'post_modified' => '2017-08-23 02:59:25',
  'post_modified_gmt' => '2017-08-23 02:59:25',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?post_type=testimonial&#038;p=63',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Diana Jones',
    '_testimonial_link' => 'http://themify.com',
    '_testimonial_company' => 'Themify',
    '_testimonial_position' => 'CFO',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'team',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2111,
  'post_date' => '2008-06-11 21:33:02',
  'post_date_gmt' => '2008-06-11 21:33:02',
  'post_content' => 'Aliquam euismod aliquet nunc, mollis consectetur sapien congue eu. Pellentesque erat mauris, varius non posuere sit amet, tempor ac velit.',
  'post_title' => 'Patricia Wolf',
  'post_excerpt' => '',
  'post_name' => 'patricia-wolf',
  'post_modified' => '2017-08-23 02:59:23',
  'post_modified_gmt' => '2017-08-23 02:59:23',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/builder/?post_type=testimonial&#038;p=65',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Patricia Wolf',
    '_testimonial_link' => 'http://themify.com',
    '_testimonial_company' => 'Themify',
    '_testimonial_position' => 'CEO',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'team',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1589,
  'post_date' => '2008-11-02 19:39:01',
  'post_date_gmt' => '2008-11-02 19:39:01',
  'post_content' => 'Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis tincidunt eget, dignissim non tortor.',
  'post_title' => 'Extremely Happy',
  'post_excerpt' => '',
  'post_name' => 'extremely-happy-2',
  'post_modified' => '2017-08-23 02:59:22',
  'post_modified_gmt' => '2017-08-23 02:59:22',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=27',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'April',
    '_testimonial_link' => 'http://icondock.com',
    '_testimonial_company' => 'IconDock',
    '_testimonial_position' => 'Designer',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1590,
  'post_date' => '2008-11-09 20:28:43',
  'post_date_gmt' => '2008-11-09 20:28:43',
  'post_content' => 'Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu.',
  'post_title' => 'Super Awesome!',
  'post_excerpt' => '',
  'post_name' => 'super-awesome-2',
  'post_modified' => '2017-08-23 02:59:20',
  'post_modified_gmt' => '2017-08-23 02:59:20',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=66',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Lisa',
    '_testimonial_company' => 'Company',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1591,
  'post_date' => '2008-11-19 19:58:11',
  'post_date_gmt' => '2008-11-19 19:58:11',
  'post_content' => 'Mauris mattis est quis dolor venenatis vitae pharetra diam gravida. Vivamus dignissim, ligula vel ultricies varius, nibh velit pretium leo, vel placerat ipsum risus luctus purus.',
  'post_title' => 'Best Services in Town!',
  'post_excerpt' => '',
  'post_name' => 'best-services-in-town-2',
  'post_modified' => '2017-08-23 02:59:18',
  'post_modified_gmt' => '2017-08-23 02:59:18',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=1152',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Eva',
    '_testimonial_link' => 'https://themify.me',
    '_testimonial_company' => 'A & D',
    '_testimonial_position' => 'Manager',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1592,
  'post_date' => '2008-11-19 19:59:53',
  'post_date_gmt' => '2008-11-19 19:59:53',
  'post_content' => 'Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis. In gravida arcu ut neque ornare vitae rutrum tu. Cras a fringilla nunc. Suspendisse volutpat, eros cong rpis vehicula.',
  'post_title' => 'Exceeded Our Expectation',
  'post_excerpt' => '',
  'post_name' => 'exceeded-our-expectation-2',
  'post_modified' => '2017-08-23 02:59:16',
  'post_modified_gmt' => '2017-08-23 02:59:16',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=testimonial&#038;p=1156',
  'menu_order' => 0,
  'post_type' => 'testimonial',
  'meta_input' => 
  array (
    '_testimonial_name' => 'Rachel',
    '_testimonial_company' => 'Flower Shop',
    '_testimonial_position' => 'Owner',
  ),
  'tax_input' => 
  array (
    'testimonial-category' => 'testimonials',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 4,
  'post_date' => '2012-11-11 02:04:34',
  'post_date_gmt' => '2012-11-11 02:04:34',
  'post_content' => 'Sed sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis augue vitae libero. Nam risus velit, rhoncus eget consectetur id, posuere at ligula. Vivamus imperdiet diam ac tortor tempus posuere. Curabitur at arcu id turpis posuere bibendum. Sed commodo mauris eget diam pretium cursus.',
  'post_title' => 'John Smith',
  'post_excerpt' => '',
  'post_name' => 'john-smith',
  'post_modified' => '2017-08-23 03:00:01',
  'post_modified_gmt' => '2017-08-23 03:00:01',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=4',
  'menu_order' => 0,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'CEO',
  ),
  'tax_input' => 
  array (
    'team-category' => 'executive',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 9,
  'post_date' => '2012-11-10 18:18:19',
  'post_date_gmt' => '2012-11-10 18:18:19',
  'post_content' => 'Vivamus dignissim, ligula vel ultricies varius, nibh velit pretium leo, vel placerat ipsum risus luctus purus. Nullam rutrum quam ut massa ultricies sed blandit sapien fermentum. Curabitur venenatis vehicula mattis. Nunc eleifend consectetur odio sit',
  'post_title' => 'Peter Johnson',
  'post_excerpt' => '',
  'post_name' => 'peter-johnson',
  'post_modified' => '2017-08-23 03:00:03',
  'post_modified_gmt' => '2017-08-23 03:00:03',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=9',
  'menu_order' => 1,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'CFO',
  ),
  'tax_input' => 
  array (
    'team-category' => 'executive',
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
  'post_date' => '2012-11-11 01:17:04',
  'post_date_gmt' => '2012-11-11 01:17:04',
  'post_content' => 'Fusce augue velit, vulputate elementum semper congue, rhoncus adipiscing nisl. Curabitur vel risus eros, sed eleifend arcu. Donec porttitor hendrerit diam et blandit. Curabitur vitae velit ligula, vitae lobortis massa. Mauris mattis est quis dolor venenatis vitae pharetra diam gravida.',
  'post_title' => 'Lori Rose',
  'post_excerpt' => '',
  'post_name' => 'lori-rose',
  'post_modified' => '2017-08-23 03:00:04',
  'post_modified_gmt' => '2017-08-23 03:00:04',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=7',
  'menu_order' => 2,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'Vice President',
  ),
  'tax_input' => 
  array (
    'team-category' => 'executive',
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
  'post_date' => '2012-11-09 18:18:37',
  'post_date_gmt' => '2012-11-09 18:18:37',
  'post_content' => 'Ut euismod ligula eu tellus interdum mattis ac eu nulla. Phasellus cursus, lacus quis convallis aliquet, dolor urna ullamcorper mi, eget dapibus velit est vitae nisi. Aliquam erat nulla, sodales at imperdiet vitae, convallis vel dui. Sed ultrices felis ut justo.',
  'post_title' => 'Mary Jess',
  'post_excerpt' => '',
  'post_name' => 'mary-jess',
  'post_modified' => '2017-08-23 03:00:06',
  'post_modified_gmt' => '2017-08-23 03:00:06',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=11',
  'menu_order' => 3,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'HR Manager',
  ),
  'tax_input' => 
  array (
    'team-category' => 'executive',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1227,
  'post_date' => '2012-11-20 00:50:14',
  'post_date_gmt' => '2012-11-20 00:50:14',
  'post_content' => 'Aliquam faucibus turpis at libero consectetur euismod. Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque.',
  'post_title' => 'Terresa',
  'post_excerpt' => '',
  'post_name' => 'terresa',
  'post_modified' => '2017-08-23 03:00:07',
  'post_modified_gmt' => '2017-08-23 03:00:07',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=1227',
  'menu_order' => 4,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'Office Girl',
  ),
  'tax_input' => 
  array (
    'team-category' => 'general',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1225,
  'post_date' => '2012-11-20 00:48:49',
  'post_date_gmt' => '2012-11-20 00:48:49',
  'post_content' => 'Vivamu mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis tincidunt eget, dignissim non tortor.',
  'post_title' => 'Mia',
  'post_excerpt' => '',
  'post_name' => 'mia',
  'post_modified' => '2017-08-23 03:00:09',
  'post_modified_gmt' => '2017-08-23 03:00:09',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=1225',
  'menu_order' => 5,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'Assistant',
  ),
  'tax_input' => 
  array (
    'team-category' => 'general',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1223,
  'post_date' => '2012-11-20 00:47:46',
  'post_date_gmt' => '2012-11-20 00:47:46',
  'post_content' => 'Vivamus imperd sagittis, elit egestas rutrum vehicula, neque dolor fringilla lacus, ut rhoncus turpis vitae libero. Nam risus velit, rhoncus eget consectetur id, posuere at ligula. Vivamus imperdiet diam ac tortor tempus posuere. Curabitur at arcu id turpis posuere bibendum. Sed commodo mauris eget diam pretium cursus.',
  'post_title' => 'Peter',
  'post_excerpt' => '',
  'post_name' => 'peter',
  'post_modified' => '2017-08-23 03:00:12',
  'post_modified_gmt' => '2017-08-23 03:00:12',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=1223',
  'menu_order' => 6,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'Support',
  ),
  'tax_input' => 
  array (
    'team-category' => 'general',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1220,
  'post_date' => '2012-11-20 00:46:13',
  'post_date_gmt' => '2012-11-20 00:46:13',
  'post_content' => 'Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui, vitae vulputate elit metus ac arcu. Mauris consequat rhoncus dolor id sagittis. Cras tortor elit, aliquet quis tincidunt eget, dignissim non torto. Curabitur at arcu id turpis posuere bibendum. Sed commodo mauris eget diam pretium cursus.',
  'post_title' => 'Sassy',
  'post_excerpt' => '',
  'post_name' => 'sassy',
  'post_modified' => '2017-08-23 03:00:12',
  'post_modified_gmt' => '2017-08-23 03:00:12',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=1220',
  'menu_order' => 7,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'Sales',
  ),
  'tax_input' => 
  array (
    'team-category' => 'general',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 22,
  'post_date' => '2012-11-01 19:30:55',
  'post_date_gmt' => '2012-11-01 19:30:55',
  'post_content' => 'Pellentesque nisl nisi, vehicula vitae hendrerit vel, mattis eget mauris. Donec consequat eros eget lectus dictum sit amet ultrices neque sodales. Aliquam metus diam, mattis fringilla adipiscing at, lacinia at nulla. Fusce ut sem est. In eu sagittis felis.',
  'post_title' => 'Julie',
  'post_excerpt' => '',
  'post_name' => 'julie',
  'post_modified' => '2017-08-23 03:00:14',
  'post_modified_gmt' => '2017-08-23 03:00:14',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=22',
  'menu_order' => 8,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'Office Manager',
  ),
  'tax_input' => 
  array (
    'team-category' => 'general',
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
  'post_date' => '2012-11-01 19:30:08',
  'post_date_gmt' => '2012-11-01 19:30:08',
  'post_content' => 'Nam nunc lectus, congue non egestas quis, condimentum ut arcu. Nulla placerat, tortor non egestas rutrum, mi turpis adipiscing dui, et mollis turpis tortor vel orci. Cras a fringilla nunc. Suspendisse volutpat, eros congue scelerisque iaculis, magna odio sodales dui.',
  'post_title' => 'Suzan',
  'post_excerpt' => '',
  'post_name' => 'suzan',
  'post_modified' => '2017-08-23 03:00:16',
  'post_modified_gmt' => '2017-08-23 03:00:16',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=20',
  'menu_order' => 9,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'Store Manager',
  ),
  'tax_input' => 
  array (
    'team-category' => 'general',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 18,
  'post_date' => '2012-11-01 19:29:42',
  'post_date_gmt' => '2012-11-01 19:29:42',
  'post_content' => 'In gravida arcu ut neque ornare vitae rutrum turpis vehicula. Nunc ultrices sem mollis metus rutrum non malesuada metus fermentum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Pellentesque interdum rutrum quam, a pharetra est pulvinar ac.',
  'post_title' => 'Jerry',
  'post_excerpt' => '',
  'post_name' => 'jerry',
  'post_modified' => '2017-08-23 03:00:18',
  'post_modified_gmt' => '2017-08-23 03:00:18',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=18',
  'menu_order' => 10,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'Designer',
  ),
  'tax_input' => 
  array (
    'team-category' => 'general',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 16,
  'post_date' => '2012-11-01 19:28:47',
  'post_date_gmt' => '2012-11-01 19:28:47',
  'post_content' => 'Ut vulputate odio id dui convallis in adipiscing libero condimentum. Nunc et pharetra enim. Praesent pharetra, neque et luctus tempor, leo sapien faucibus leo, a dignissim turpis ipsum sed libero. Sed sed luctus purus. Aliquam faucibus turpis at libero consectetur euismod.',
  'post_title' => 'Rosana',
  'post_excerpt' => '',
  'post_name' => 'rosana',
  'post_modified' => '2017-08-23 03:00:19',
  'post_modified_gmt' => '2017-08-23 03:00:19',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?post_type=team&#038;p=16',
  'menu_order' => 11,
  'post_type' => 'team',
  'meta_input' => 
  array (
    'content_width' => 'default_width',
    '_team_title' => 'Secretary',
  ),
  'tax_input' => 
  array (
    'team-category' => 'general',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 2159,
  'post_date' => '2013-09-10 00:41:20',
  'post_date_gmt' => '2013-09-10 00:41:20',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '2159',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=2159',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '2153',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1428,
  'post_date' => '2012-11-21 22:27:50',
  'post_date_gmt' => '2012-11-21 22:27:50',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1428',
  'post_modified' => '2012-11-21 22:27:50',
  'post_modified_gmt' => '2012-11-21 22:27:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1428',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1171',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1420,
  'post_date' => '2012-11-21 22:27:02',
  'post_date_gmt' => '2012-11-21 22:27:02',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1420',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 2153,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1420',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2159',
    '_menu_item_object_id' => '1295',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1429,
  'post_date' => '2012-11-21 22:27:50',
  'post_date_gmt' => '2012-11-21 22:27:50',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1429',
  'post_modified' => '2012-11-21 22:27:50',
  'post_modified_gmt' => '2012-11-21 22:27:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1429',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1146',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1419,
  'post_date' => '2012-11-21 22:27:02',
  'post_date_gmt' => '2012-11-21 22:27:02',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1419',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 2153,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1419',
  'menu_order' => 3,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '2159',
    '_menu_item_object_id' => '1300',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1422,
  'post_date' => '2012-11-21 22:27:02',
  'post_date_gmt' => '2012-11-21 22:27:02',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1422',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1422',
  'menu_order' => 4,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1104',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1431,
  'post_date' => '2012-11-21 22:27:50',
  'post_date_gmt' => '2012-11-21 22:27:50',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1431',
  'post_modified' => '2012-11-21 22:27:50',
  'post_modified_gmt' => '2012-11-21 22:27:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1431',
  'menu_order' => 4,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1129',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1423,
  'post_date' => '2012-11-21 22:27:02',
  'post_date_gmt' => '2012-11-21 22:27:02',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1423',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 1104,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1423',
  'menu_order' => 5,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '1422',
    '_menu_item_object_id' => '1247',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1432,
  'post_date' => '2012-11-21 22:27:50',
  'post_date_gmt' => '2012-11-21 22:27:50',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1432',
  'post_modified' => '2012-11-21 22:27:50',
  'post_modified_gmt' => '2012-11-21 22:27:50',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1432',
  'menu_order' => 5,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1104',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1424,
  'post_date' => '2012-11-21 22:27:02',
  'post_date_gmt' => '2012-11-21 22:27:02',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1424',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 1104,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1424',
  'menu_order' => 6,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '1422',
    '_menu_item_object_id' => '1243',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1425,
  'post_date' => '2012-11-21 22:27:02',
  'post_date_gmt' => '2012-11-21 22:27:02',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1425',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 1104,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1425',
  'menu_order' => 7,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '1422',
    '_menu_item_object_id' => '1210',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1416,
  'post_date' => '2012-11-21 22:27:02',
  'post_date_gmt' => '2012-11-21 22:27:02',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1416',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1416',
  'menu_order' => 8,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1150',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1426,
  'post_date' => '2012-11-21 22:27:03',
  'post_date_gmt' => '2012-11-21 22:27:03',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1426',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1426',
  'menu_order' => 9,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1272',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1427,
  'post_date' => '2012-11-21 22:27:03',
  'post_date_gmt' => '2012-11-21 22:27:03',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1427',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 1272,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1427',
  'menu_order' => 10,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '1426',
    '_menu_item_object_id' => '1275',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1415,
  'post_date' => '2012-11-21 22:27:02',
  'post_date_gmt' => '2012-11-21 22:27:02',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1415',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1415',
  'menu_order' => 11,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1171',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1417,
  'post_date' => '2012-11-21 22:27:02',
  'post_date_gmt' => '2012-11-21 22:27:02',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1417',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1417',
  'menu_order' => 12,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1146',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 1421,
  'post_date' => '2012-11-21 22:27:03',
  'post_date_gmt' => '2012-11-21 22:27:03',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '1421',
  'post_modified' => '2015-01-25 16:03:39',
  'post_modified_gmt' => '2015-01-25 16:03:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/agency/?p=1421',
  'menu_order' => 13,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '1129',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-menu',
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

	$widgets = get_option( "widget_text" );
$widgets[1002] = array (
  'title' => 'Featured Works',
  'text' => '[portfolio style="grid2" category="7" limit="2" image_w="120" image_h="0"]',
  'filter' => true,
  'visual' => true,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_themify-feature-posts" );
$widgets[1003] = array (
  'title' => 'Recent Posts',
  'category' => '0',
  'show_count' => '3',
  'show_date' => 'on',
  'show_thumb' => 'on',
  'show_excerpt' => NULL,
  'hide_title' => NULL,
  'thumb_width' => '30',
  'thumb_height' => '30',
  'excerpt_length' => '55',
);
update_option( "widget_themify-feature-posts", $widgets );

$widgets = get_option( "widget_themify-twitter" );
$widgets[1004] = array (
  'title' => 'Latest Tweets',
  'username' => 'themify',
  'show_count' => '3',
  'hide_timestamp' => 'on',
  'show_follow' => 'on',
  'follow_text' => '→ Follow me',
);
update_option( "widget_themify-twitter", $widgets );

$widgets = get_option( "widget_themify-social-links" );
$widgets[1005] = array (
  'title' => '',
  'show_link_name' => NULL,
  'open_new_window' => NULL,
  'icon_size' => 'icon-medium',
  'orientation' => 'horizontal',
);
update_option( "widget_themify-social-links", $widgets );



$sidebars_widgets = array (
  'sidebar-main' => 
  array (
    0 => 'text-1002',
    1 => 'themify-feature-posts-1003',
    2 => 'themify-twitter-1004',
  ),
  'social-widget' => 
  array (
    0 => 'themify-social-links-1005',
  ),
); 
update_option( "sidebars_widgets", $sidebars_widgets );

$menu_locations = array();
$menu = get_terms( "nav_menu", array( "slug" => "main-menu" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["main-nav"] = $menu[0]->term_id;
$menu = get_terms( "nav_menu", array( "slug" => "footer-menu" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["footer-nav"] = $menu[0]->term_id;
set_theme_mod( "nav_menu_locations", $menu_locations );


$homepage = get_posts( array( 'name' => 'home', 'post_type' => 'page' ) );
			if( is_array( $homepage ) && ! empty( $homepage ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $homepage[0]->ID );
			}
			
	ob_start(); ?>a:196:{s:15:"setting-favicon";s:0:"";s:23:"setting-custom_feed_url";s:0:"";s:19:"setting-header_html";s:0:"";s:19:"setting-footer_html";s:0:"";s:23:"setting-search_settings";s:0:"";s:16:"setting-page_404";s:1:"0";s:21:"setting-feed_settings";s:0:"";s:21:"setting-webfonts_list";s:11:"recommended";s:24:"setting-webfonts_subsets";s:0:"";s:22:"setting-default_layout";s:8:"sidebar1";s:27:"setting-default_post_layout";s:16:"list-thumb-image";s:30:"setting-default_layout_display";s:7:"content";s:25:"setting-default_more_text";s:4:"More";s:21:"setting-index_orderby";s:4:"date";s:19:"setting-index_order";s:4:"DESC";s:26:"setting-default_post_title";s:0:"";s:33:"setting-default_unlink_post_title";s:0:"";s:25:"setting-default_post_meta";s:0:"";s:25:"setting-default_post_date";s:0:"";s:26:"setting-default_post_image";s:0:"";s:33:"setting-default_unlink_post_image";s:0:"";s:31:"setting-image_post_feature_size";s:5:"blank";s:24:"setting-image_post_width";s:0:"";s:25:"setting-image_post_height";s:0:"";s:32:"setting-default_page_post_layout";s:8:"sidebar1";s:31:"setting-default_page_post_title";s:0:"";s:38:"setting-default_page_unlink_post_title";s:0:"";s:30:"setting-default_page_post_meta";s:0:"";s:30:"setting-default_page_post_date";s:0:"";s:31:"setting-default_page_post_image";s:0:"";s:38:"setting-default_page_unlink_post_image";s:0:"";s:38:"setting-image_post_single_feature_size";s:5:"blank";s:31:"setting-image_post_single_width";s:0:"";s:32:"setting-image_post_single_height";s:0:"";s:27:"setting-default_page_layout";s:8:"sidebar1";s:23:"setting-hide_page_title";s:0:"";s:23:"setting-hide_page_image";s:0:"";s:33:"setting-page_featured_image_width";s:0:"";s:34:"setting-page_featured_image_height";s:0:"";s:22:"setting-comments_pages";s:2:"on";s:33:"setting-portfolio_slider_autoplay";s:4:"5000";s:31:"setting-portfolio_slider_effect";s:5:"slide";s:41:"setting-portfolio_slider_transition_speed";s:3:"500";s:38:"setting-default_portfolio_index_layout";s:12:"sidebar-none";s:43:"setting-default_portfolio_index_post_layout";s:5:"grid4";s:39:"setting-default_portfolio_index_display";s:4:"none";s:37:"setting-default_portfolio_index_title";s:0:"";s:49:"setting-default_portfolio_index_unlink_post_title";s:0:"";s:41:"setting-default_portfolio_index_post_meta";s:0:"";s:41:"setting-default_portfolio_index_post_date";s:0:"";s:48:"setting-default_portfolio_index_image_post_width";s:0:"";s:49:"setting-default_portfolio_index_image_post_height";s:0:"";s:39:"setting-default_portfolio_single_layout";s:12:"sidebar-none";s:38:"setting-default_portfolio_single_title";s:0:"";s:50:"setting-default_portfolio_single_unlink_post_title";s:0:"";s:42:"setting-default_portfolio_single_post_meta";s:0:"";s:42:"setting-default_portfolio_single_post_date";s:0:"";s:49:"setting-default_portfolio_single_image_post_width";s:0:"";s:50:"setting-default_portfolio_single_image_post_height";s:0:"";s:22:"themify_portfolio_slug";s:7:"project";s:34:"setting-default_team_single_layout";s:12:"sidebar-none";s:38:"setting-default_team_single_hide_title";s:0:"";s:40:"setting-default_team_single_unlink_title";s:0:"";s:38:"setting-default_team_single_hide_image";s:0:"";s:40:"setting-default_team_single_unlink_image";s:0:"";s:44:"setting-default_team_single_image_post_width";s:0:"";s:45:"setting-default_team_single_image_post_height";s:0:"";s:17:"themify_team_slug";s:4:"team";s:53:"setting-customizer_responsive_design_tablet_landscape";s:4:"1024";s:43:"setting-customizer_responsive_design_tablet";s:3:"768";s:43:"setting-customizer_responsive_design_mobile";s:3:"480";s:33:"setting-mobile_menu_trigger_point";s:4:"1200";s:24:"setting-gallery_lightbox";s:8:"lightbox";s:26:"setting-page_builder_cache";s:2:"on";s:27:"setting-script_minification";s:7:"disable";s:27:"setting-page_builder_expiry";s:1:"2";s:19:"setting-entries_nav";s:8:"numbered";s:22:"setting-slider_enabled";s:2:"on";s:29:"setting-slider_posts_category";s:1:"0";s:27:"setting-slider_posts_slides";s:0:"";s:30:"setting-slider_default_display";s:7:"content";s:25:"setting-slider_hide_title";s:0:"";s:19:"setting-slider_auto";s:4:"4000";s:21:"setting-slider_effect";s:5:"slide";s:20:"setting-slider_speed";s:4:"2000";s:24:"setting-homepage_welcome";s:701:"<h2>Welcome <em>to</em> Themify</h2>
<p>Agency theme is clean, minimal, and responsive WordPress theme meant for design agencies. Create complete agency or portfolio with custom post types: <a href="https://themify.me/demo/themes/agency/shortcodes/portfolio-shortcode/">Portfolio</a>, <a href="https://themify.me/demo/themes/agency/shortcodes/highlight-shortcode/">Highlight</a>, <a href="https://themify.me/demo/themes/agency/shortcodes/testimonial-shortcode/">Testimonial</a>, and <a href="https://themify.me/demo/themes/agency/shortcodes/team-shortcode/">Team</a>.</p>

[button link="https://themify.me/themes/agency"]Learn More[/button] [button link="https://themify.me/themes"]Sign Up[/button]";s:22:"setting-footer_widgets";s:17:"footerwidget-3col";s:24:"setting-footer_text_left";s:0:"";s:25:"setting-footer_text_right";s:0:"";s:27:"setting-global_feature_size";s:5:"large";s:22:"setting-link_icon_type";s:10:"image-icon";s:32:"setting-link_type_themify-link-0";s:10:"image-icon";s:33:"setting-link_title_themify-link-0";s:7:"Twitter";s:32:"setting-link_link_themify-link-0";s:26:"http://twitter.com/themify";s:31:"setting-link_img_themify-link-0";s:93:"https://themify.me/demo/themes/agency/wp-content/themes/agency/themify/img/social/twitter.png";s:32:"setting-link_type_themify-link-2";s:10:"image-icon";s:33:"setting-link_title_themify-link-2";s:7:"Google+";s:32:"setting-link_link_themify-link-2";s:45:"https://plus.google.com/102333925087069536501";s:31:"setting-link_img_themify-link-2";s:97:"https://themify.me/demo/themes/agency/wp-content/themes/agency/themify/img/social/google-plus.png";s:32:"setting-link_type_themify-link-1";s:10:"image-icon";s:33:"setting-link_title_themify-link-1";s:8:"Facebook";s:32:"setting-link_link_themify-link-1";s:27:"http://facebook.com/themify";s:31:"setting-link_img_themify-link-1";s:94:"https://themify.me/demo/themes/agency/wp-content/themes/agency/themify/img/social/facebook.png";s:32:"setting-link_type_themify-link-3";s:10:"image-icon";s:33:"setting-link_title_themify-link-3";s:7:"YouTube";s:32:"setting-link_link_themify-link-3";s:0:"";s:31:"setting-link_img_themify-link-3";s:93:"https://themify.me/demo/themes/agency/wp-content/themes/agency/themify/img/social/youtube.png";s:32:"setting-link_type_themify-link-4";s:10:"image-icon";s:33:"setting-link_title_themify-link-4";s:9:"Pinterest";s:32:"setting-link_link_themify-link-4";s:0:"";s:31:"setting-link_img_themify-link-4";s:95:"https://themify.me/demo/themes/agency/wp-content/themes/agency/themify/img/social/pinterest.png";s:22:"setting-link_field_ids";s:171:"{"themify-link-0":"themify-link-0","themify-link-2":"themify-link-2","themify-link-1":"themify-link-1","themify-link-3":"themify-link-3","themify-link-4":"themify-link-4"}";s:23:"setting-link_field_hash";s:1:"5";s:30:"setting-page_builder_is_active";s:6:"enable";s:41:"setting-page_builder_animation_appearance";s:0:"";s:42:"setting-page_builder_animation_parallax_bg";s:0:"";s:46:"setting-page_builder_animation_parallax_scroll";s:6:"mobile";s:55:"setting-page_builder_responsive_design_tablet_landscape";s:4:"1024";s:45:"setting-page_builder_responsive_design_tablet";s:3:"768";s:45:"setting-page_builder_responsive_design_mobile";s:3:"680";s:23:"setting-hooks_field_ids";s:2:"[]";s:27:"setting-custom_panel-editor";s:7:"default";s:27:"setting-custom_panel-author";s:7:"default";s:32:"setting-custom_panel-contributor";s:7:"default";s:25:"setting-customizer-editor";s:7:"default";s:25:"setting-customizer-author";s:7:"default";s:30:"setting-customizer-contributor";s:7:"default";s:22:"setting-backend-editor";s:7:"default";s:22:"setting-backend-author";s:7:"default";s:27:"setting-backend-contributor";s:7:"default";s:23:"setting-frontend-editor";s:7:"default";s:23:"setting-frontend-author";s:7:"default";s:28:"setting-frontend-contributor";s:7:"default";s:4:"skin";s:87:"https://themify.me/demo/themes/agency/wp-content/themes/agency/themify/img/non-skin.gif";s:27:"setting-search_exclude_post";s:0:"";s:31:"setting-search_settings_exclude";s:0:"";s:29:"setting-search_exclude_slider";s:0:"";s:32:"setting-search_exclude_portfolio";s:0:"";s:32:"setting-search_exclude_highlight";s:0:"";s:34:"setting-search_exclude_testimonial";s:0:"";s:27:"setting-search_exclude_team";s:0:"";s:23:"setting-exclude_img_rss";s:0:"";s:30:"setting-default_excerpt_length";s:0:"";s:20:"setting-excerpt_more";s:0:"";s:27:"setting-auto_featured_image";s:0:"";s:22:"setting-comments_posts";s:0:"";s:23:"setting-post_author_box";s:0:"";s:24:"setting-post_nav_disable";s:0:"";s:25:"setting-post_nav_same_cat";s:0:"";s:29:"setting-portfolio_nav_disable";s:0:"";s:30:"setting-portfolio_nav_same_cat";s:0:"";s:26:"setting-portfolio_comments";s:0:"";s:33:"setting-disable_responsive_design";s:0:"";s:31:"setting-lightbox_content_images";s:0:"";s:18:"setting-cache_gzip";s:0:"";s:19:"setting-exclude_rss";s:0:"";s:27:"setting-exclude_search_form";s:0:"";s:29:"setting-fixed_header_disabled";s:0:"";s:29:"setting-footer_text_left_hide";s:0:"";s:30:"setting-footer_text_right_hide";s:0:"";s:38:"setting-page_builder_disable_shortcuts";s:0:"";s:34:"setting-page_builder_exc_accordion";s:0:"";s:28:"setting-page_builder_exc_box";s:0:"";s:32:"setting-page_builder_exc_buttons";s:0:"";s:32:"setting-page_builder_exc_callout";s:0:"";s:32:"setting-page_builder_exc_divider";s:0:"";s:38:"setting-page_builder_exc_fancy-heading";s:0:"";s:32:"setting-page_builder_exc_feature";s:0:"";s:32:"setting-page_builder_exc_gallery";s:0:"";s:34:"setting-page_builder_exc_highlight";s:0:"";s:29:"setting-page_builder_exc_icon";s:0:"";s:30:"setting-page_builder_exc_image";s:0:"";s:36:"setting-page_builder_exc_layout-part";s:0:"";s:28:"setting-page_builder_exc_map";s:0:"";s:29:"setting-page_builder_exc_menu";s:0:"";s:35:"setting-page_builder_exc_plain-text";s:0:"";s:34:"setting-page_builder_exc_portfolio";s:0:"";s:29:"setting-page_builder_exc_post";s:0:"";s:37:"setting-page_builder_exc_service-menu";s:0:"";s:31:"setting-page_builder_exc_slider";s:0:"";s:28:"setting-page_builder_exc_tab";s:0:"";s:43:"setting-page_builder_exc_testimonial-slider";s:0:"";s:36:"setting-page_builder_exc_testimonial";s:0:"";s:29:"setting-page_builder_exc_text";s:0:"";s:30:"setting-page_builder_exc_video";s:0:"";s:31:"setting-page_builder_exc_widget";s:0:"";s:35:"setting-page_builder_exc_widgetized";s:0:"";s:34:"setting-custom_panel-wpseo_manager";s:7:"default";s:33:"setting-custom_panel-wpseo_editor";s:7:"default";s:32:"setting-customizer-wpseo_manager";s:7:"default";s:31:"setting-customizer-wpseo_editor";s:7:"default";s:29:"setting-backend-wpseo_manager";s:7:"default";s:28:"setting-backend-wpseo_editor";s:7:"default";s:30:"setting-frontend-wpseo_manager";s:7:"default";s:29:"setting-frontend-wpseo_editor";s:7:"default";s:16:"setting-fontello";s:0:"";}<?php $themify_data = unserialize( ob_get_clean() );

	// fix the weird way "skin" is saved
	if( isset( $themify_data['skin'] ) ) {
		$parsed_skin = parse_url( $themify_data['skin'], PHP_URL_PATH );
		$basedir_skin = basename( dirname( $parsed_skin ) );
		$themify_data['skin'] = trailingslashit( get_template_directory_uri() ) . 'skins/' . $basedir_skin . '/style.css';
	}

	themify_set_data( $themify_data );
	
}
themify_do_demo_import();