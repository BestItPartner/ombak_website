<?php
/***************************************************************************
 *  					Theme Functions
 * 	----------------------------------------------------------------------
 * 						DO NOT EDIT THIS FILE
 *	----------------------------------------------------------------------
 * 
 *  					Copyright (C) Themify
 * 						http://themify.me
 *
 *  To add custom PHP functions to the theme, create a child theme (https://themify.me/docs/child-theme) and add it to the child theme functions.php file.
 *  They will be added to the theme automatically.
 * 
 ***************************************************************************/

/////// Actions ////////
// Init post, page and additional post types if they exist
add_action( 'after_setup_theme', 'themify_theme_after_setup_theme' );

// Enqueue scripts and styles required by theme
add_action( 'wp_enqueue_scripts', 'themify_theme_enqueue_scripts', 11 );

// Browser compatibility
add_action( 'wp_head', 'themify_viewport_tag' );


// Register custom menu
add_action( 'init', 'themify_register_custom_nav');

// Register sidebars
add_action( 'widgets_init', 'themify_theme_register_sidebars' );

// Add additional sidebar
add_action( 'themify_content_after', 'themify_theme_add_sidebar_alt' );

/////// Filters ////////

if ( ! function_exists( 'themify_theme_enqueue_scripts' ) ) {
	/**
	 * Enqueue Stylesheets and Scripts
	 * @since 1.0.0
	 */
	function themify_theme_enqueue_scripts() {
		// Get theme version for Themify theme scripts and styles
		$theme_version = wp_get_theme()->display( 'Version' );

		///////////////////
		//Enqueue styles
		///////////////////

		// Themify base styling
		wp_enqueue_style( 'theme-style', themify_enque( THEME_URI . '/style.css' ), array(), $theme_version );

		// Themify Media Queries CSS
		wp_enqueue_style( 'themify-media-queries', themify_enque(THEME_URI . '/media-queries.css'), array(), $theme_version);

		// Themify child base styling
		if( is_child_theme() ) {
			wp_enqueue_style( 'theme-style-child', themify_enque( get_stylesheet_uri() ), array(), $theme_version );
		}

		///////////////////
		//Enqueue scripts
		///////////////////

		// Nice scroll plugin
		// Load this desktop only
		$ua=$_SERVER['HTTP_USER_AGENT'];
		$browser = ((strpos($ua,'iPhone')!==false)||(strpos($ua,'iPod')!==false)||(strpos($ua,'iPad')!==false)||(strpos($ua,'Android')!==false));
		if ($browser == false){
			wp_enqueue_script( 'theme-scroll', THEME_URI . '/js/jquery.scroll.min.js', array('jquery'), $theme_version, true );
		}

		// Slide mobile navigation menu
		wp_enqueue_script( 'slide-nav', themify_enque(THEME_URI . '/js/themify.sidemenu.js'), array( 'jquery' ), $theme_version, true );

		// Themify internal scripts
		wp_enqueue_script( 'theme-script',	themify_enque(THEME_URI . '/js/themify.script.js'), array('jquery'), $theme_version, true );

		// Inject variable values in gallery script
		wp_localize_script( 'theme-script', 'themifyScript', apply_filters('themify_script_vars',
			array(
				'themeURI' 		=> THEME_URI,
				'lightbox' 		=> themify_lightbox_vars_init(),
				'lightboxContext' => apply_filters('themify_lightbox_context', '#pagewrap'),
				'fixedHeader' 	=> themify_check('setting-fixed_header_disabled')? '': 'fixed-header',
				'ajax_nonce'   	=> wp_create_nonce('ajax_nonce'),
				'ajax_url'	   	=> admin_url( 'admin-ajax.php' ),
				'events'		=> themify_is_touch() ? 'click' : 'mouseenter',
				'top_nav_side'  => is_rtl() ? 'right' : 'left',
				'main_nav_side' => is_rtl() ? 'left' : 'right'
			)
		));

		// WordPress internal script to move the comment box to the right place when replying to a user
		if ( is_single() || is_page() ) wp_enqueue_script( 'comment-reply' );
	}
}

/**
 * Load Google fonts used by the theme
 *
 * @return array
 */
function themify_theme_google_fonts( $fonts ) {
	/* translators: If there are characters in your language that are not supported by Oswald, translate this to 'off'. Do not translate into your own language. */
	if ( 'off' !== _x( 'on', 'Oswald font: on or off', 'themify' ) ) {
		$fonts['oswald'] = 'Oswald';
	}
	/* translators: If there are characters in your language that are not supported by Open Sans, translate this to 'off'. Do not translate into your own language. */
	if ( 'off' !== _x( 'on', 'Open Sans font: on or off', 'themify' ) ) {
		$fonts['open-sans'] = 'Open+Sans';
		$fonts['open-sans-300'] = 'Open+Sans:300';
	}

	return $fonts;
}
add_filter( 'themify_google_fonts', 'themify_theme_google_fonts' );

if ( ! function_exists( 'themify_viewport_tag' ) ) {
	/**
	 * Add viewport tag for responsive layouts
	 * @since 1.0.0
	 */
	function themify_viewport_tag(){
		echo "\n".'<meta name="viewport" content="width=device-width, initial-scale=1">'."\n";
	}
}

/* Custom Write Panels
/***************************************************************************/

if ( ! function_exists( 'themify_theme_after_setup_theme' ) ) {
	/**
	 * Register theme support.
	 *
	 * Initialize custom panel with its definitions.
	 * Custom panel definitions are located in admin/post-type-TYPE.php
	 *
	 * @since 1.0.7
	 */
	function themify_theme_after_setup_theme() {
		// Enable WordPress feature image
		add_theme_support( 'post-thumbnails' );

		// Load Themify Mega Menu
		add_theme_support( 'themify-mega-menu' );

		///////////////////////////////////////
		// Build Write Panels
		///////////////////////////////////////

		// Include definitions for custom panels
		foreach( array(	'post',	'page' ) as $cpt ) {
			// Load field definitions for custom post type
			require_once('admin/post-type-'.$cpt.'.php');
		}
		themify_build_write_panels( apply_filters('themify_theme_meta_boxes',
			array(
				array(
					'name'		=> __('Post Options', 'themify'), // Name displayed in box
					'id' => 'post-options',
					'options'	=> $post_meta_box, 	// Field options
					'pages'	=> 'post'					// Pages to show write panel
				),
				array(
					'name'		=> __('Page Options', 'themify'),
					'id' => 'page-options',
					'options'	=> $page_meta_box,
					'pages'	=> 'page'
				),
				array(
					"name"		=> __('Query Posts', 'themify'),
					'id' => 'query-posts',
					"options"	=> $query_post_meta_box,
					"pages"	=> "page"
				)
			)
		));
	}
}

/* Custom Functions
/***************************************************************************/

if ( ! function_exists( 'themify_register_custom_nav' ) ) {
	/**
	 * Register Custom Menu Function
	 * @since 1.0.0
	 */
	function themify_register_custom_nav() {
		register_nav_menus( array(
			'top-nav' => __( 'Top Navigation', 'themify' ),
			'main-nav' => __( 'Main Navigation', 'themify' ),
			'footer-nav' => __( 'Footer Navigation', 'themify' ),
		));
	}
}

if ( ! function_exists( 'themify_default_main_nav' ) ) {
	/**
	 * Default Main Nav Function
	 * @since 1.0.0
	 */
	function themify_default_main_nav() {
		echo '<ul id="main-nav" class="main-nav clearfix">';
			wp_list_pages('title_li=');
		echo '</ul>';
	}
}

if ( ! function_exists( 'themify_theme_register_sidebars' ) ) {
	/**
	 * Register sidebars
	 * @since 1.0.0
	 */
	function themify_theme_register_sidebars() {
		$sidebars = array(
			array(
				'name' => __('Sidebar Wide', 'themify'),
				'id' => 'sidebar-main',
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div>',
				'before_title' => '<h4 class="widgettitle">',
				'after_title' => '</h4>',
			),
			array(
				'name' => __('Sidebar Narrow', 'themify'),
				'id' => 'sidebar-alt',
				'before_widget' => '<div class="widgetwrap"><div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div></div>',
				'before_title' => '<h4 class="widgettitle">',
				'after_title' => '</h4>',
			),
			array(
				'name' => __('Sidebar Wide 2A', 'themify'),
				'id' => 'sidebar-main-2a',
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div>',
				'before_title' => '<h4 class="widgettitle">',
				'after_title' => '</h4>',
			),
			array(
				'name' => __('Sidebar Wide 2B', 'themify'),
				'id' => 'sidebar-main-2b',
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div>',
				'before_title' => '<h4 class="widgettitle">',
				'after_title' => '</h4>',
			),
			array(
				'name' => __('Sidebar Wide 3', 'themify'),
				'id' => 'sidebar-main-3',
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div>',
				'before_title' => '<h4 class="widgettitle">',
				'after_title' => '</h4>',
			),
			array(
				'name' => __('Social Widget', 'themify'),
				'id' => 'social-widget',
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div>',
				'before_title' => '<strong class="widgettitle">',
				'after_title' => '</strong>',
			),
			array(
				'name' => __('Header Widget', 'themify'),
				'id' => 'header-widget',
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div>',
				'before_title' => '<strong class="widgettitle">',
				'after_title' => '</strong>',
			),
			array(
				'name' => __('Before Content Widget', 'themify'),
				'id' => 'before-content-widget',
				'before_widget' => '<div class="widgetwrap"><div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div></div>',
				'before_title' => '<h4 class="widgettitle">',
				'after_title' => '</h4>',
			),
			array(
				'name' => __('After Content Widget', 'themify'),
				'id' => 'after-content-widget',
				'before_widget' => '<div class="widgetwrap"><div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div></div>',
				'before_title' => '<h4 class="widgettitle">',
				'after_title' => '</h4>',
			),
			array(
				'name' => __('Footer Social Widget', 'themify'),
				'id' => 'footer-social-widget',
				'before_widget' => '<div id="%1$s" class="widget %2$s">',
				'after_widget' => '</div>',
				'before_title' => '<strong class="widgettitle">',
				'after_title' => '</strong>',
			),
		);
		foreach( $sidebars as $sidebar ) {
			register_sidebar( $sidebar );
		}

		// Footer Sidebars
		themify_register_grouped_widgets();
	}
}

if( ! function_exists('themify_theme_add_sidebar_alt') ) {
	/**
	 * Includes narrow left sidebar
	 * @since 1.0.0
	 */
	function themify_theme_add_sidebar_alt() {
		global $themify;
		if( 'sidebar2' == $themify->layout || 'sidebar2 content-left' == $themify->layout || 'sidebar2 content-right' == $themify->layout ): ?>
			<!-- sidebar-narrow -->
			<?php get_template_part( 'includes/sidebar-alt'); ?>
			<!-- /sidebar-narrow -->
		<?php endif;
	}
}

if( ! function_exists('themify_theme_breaking_news') ) {
	/**
	 * Returns or echoes the breaking news list
	 * @param $term
	 * @param string $tag
	 * @param string $taxonomy
	 * @return mixed|void
	 * @since 1.0.0
	 */
	function themify_theme_breaking_news( $term, $tag = 'li', $taxonomy = 'category', $limit = null ) {
		$tax_query = ($taxonomy == 'category' && $term == 'all-categories') ? '' : array(
			array(
				'taxonomy' => $taxonomy,
				'field' => 'slug',
				'terms' => $term
		));
		$posts = get_posts( array(
			'tax_query' => $tax_query,
			'suppress_filters' => false,
			'posts_per_page' => isset( $limit ) ? $limit : 5
		));
		$html = '';
		if( $posts ) {
			foreach($posts as $post) {
				$news = sprintf('<a href="%s" title="%s">%s</a>',
					get_permalink( $post->ID ),
					esc_attr(strip_tags(get_the_title( $post->ID ))),
					get_the_title( $post->ID )
				);
				if( '' != $tag ) {
					$html .= "<$tag>$news</$tag>";
				} else {
					$html .= $news;
				}
			}
		}
		return apply_filters('themify_theme_breaking_news', $html);
	}
}

if( ! function_exists('themify_theme_comment') ) {
	/**
	 * Custom Theme Comment
	 * @param object $comment Current comment.
	 * @param array $args Parameters for comment reply link.
	 * @param int $depth Maximum comment nesting depth.
	 * @since 1.0.0
	 */
	function themify_theme_comment($comment, $args, $depth) {
	   $GLOBALS['comment'] = $comment; ?>

		<li id="comment-<?php comment_ID() ?>">
			<p class="comment-author">
				<?php printf('%s <cite>%s</cite>', get_avatar( $comment, $size='75' ), get_comment_author_link()); ?>
				<br />
				<small class="comment-time">
					<?php comment_date( apply_filters('themify_comment_date', '') ); ?>
					@
					<?php comment_time( apply_filters('themify_comment_time', '') ); ?>
					<?php edit_comment_link( __('Edit', 'themify'),' [',']'); ?>
				</small>
			</p>
			<div class="commententry">
				<?php if ($comment->comment_approved == '0') : ?>
					<p><em><?php _e('Your comment is awaiting moderation.', 'themify') ?></em></p>
				<?php endif; ?>
				<?php comment_text(); ?>
			</div>
			<p class="reply">
				<?php comment_reply_link(array_merge( $args, array('add_below' => 'comment', 'depth' => $depth, 'reply_text' => __( 'Reply', 'themify' ), 'max_depth' => $args['max_depth']))) ?>
			</p>
		<?php
	}
}

// Add Themify wrappers
remove_action( 'woocommerce_before_main_content', 'themify_before_shop_content', 20);
remove_action( 'woocommerce_after_main_content', 'themify_after_shop_content', 20);
// Add Themify sidebar
remove_action( 'themify_content_after', 'themify_wc_compatibility_sidebar', 10);

// Add Themify wrappers
add_action( 'woocommerce_before_main_content', 'themify_theme_before_shop_content', 20);
add_action( 'woocommerce_after_main_content', 'themify_theme_after_shop_content', 20);

if(!function_exists('themify_theme_before_shop_content')) {
	/**
	 * Add initial portion of wrapper
	 * @since 1.4.6
	 */
	function themify_theme_before_shop_content() { ?>
		<!-- layout -->
		<div id="layout" class="pagewidth clearfix">

			<div id="contentwrap">

				<?php themify_content_before(); // Hook ?>

				<!-- content -->
				<div id="content" class="<?php echo (is_product() || is_shop()) ? 'list-post':''; ?>">

					<?php
					if(!themify_check('setting-hide_shop_breadcrumbs')) {
						themify_breadcrumb_before();
						woocommerce_breadcrumb();
						themify_breadcrumb_after();
					}
					themify_content_start(); // Hook
	}
}

if(!function_exists('themify_theme_after_shop_content')) {
	/**
	 * Add end portion of wrapper
	 * @since 1.4.6
	 */
	function themify_theme_after_shop_content() {
					global $themify;
					if (is_search() && is_post_type_archive() ) {
						add_filter( 'woo_pagination_args', 'woocommerceframework_add_search_fragment', 10 );
					}
					themify_content_end(); // Hook ?>

				</div>
				<!-- /#content -->

			<?php themify_content_after() // Hook ?>

			</div>
			<!-- /#contentwrap -->

			<?php
			/////////////////////////////////////////////
			// Sidebar
			/////////////////////////////////////////////
			if( $themify->layout != 'sidebar-none' ): get_sidebar(); endif; ?>

		</div><!-- /#layout -->
	<?php
	}
}

/**
 * Set the fixed-header selector for the scroll highlight script
 *
 * @since 1.1.3
 */
function themify_theme_scroll_highlight_vars( $vars ) {
	$vars['fixedHeaderSelector'] = '#headerwrap.fixed-header';
	return $vars;
}
add_filter( 'themify_builder_scroll_highlight_vars', 'themify_theme_scroll_highlight_vars' );

/**
 * Change number of posts displayed in mega menu
 *
 * @return array
 */
function themify_theme_mega_menu_query( $args ) {
	$args['posts_per_page'] = 4;
	return $args;
}
add_filter( 'themify_mega_menu_query', 'themify_theme_mega_menu_query' );
