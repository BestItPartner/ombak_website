<?php
/*
To add custom PHP functions to the theme, create a child theme (https://themify.me/docs/child-theme) and add it to the child theme functions.php file.
They will be added to the theme automatically.
*/

/* 	Enqueue Stylesheets and Scripts
/***************************************************************************/
add_action( 'wp_enqueue_scripts', 'themify_theme_enqueue_scripts', 11 );
function themify_theme_enqueue_scripts() {
	// Get theme version for Themify theme scripts and styles
	$theme_version = wp_get_theme()->display( 'Version' );

	///////////////////
	//Enqueue styles
	///////////////////

	//Themify base styling
	wp_enqueue_style( 'theme-style', themify_enque( THEME_URI . '/style.css' ), array(), $theme_version );

	//Themify Media Queries CSS
	wp_enqueue_style( 'themify-media-queries', themify_enque(THEME_URI . '/media-queries.css'));

	//Google Web Fonts embedding
	wp_enqueue_style( 'google-fonts', themify_https_esc('http://fonts.googleapis.com/css'). '?family=Old+Standard+TT:400,400italic,700&subset=latin,latin-ext');

	// Themify child base styling
	if( is_child_theme() ) {
		wp_enqueue_style( 'theme-style-child', themify_enque( get_stylesheet_uri() ), array(), $theme_version );
	}

	///////////////////
	//Enqueue scripts
	///////////////////

	//slider
	wp_enqueue_script( 'jquery-slider', THEME_URI . '/js/jquery.slider.min.js', array('jquery'), false, true );

	//Themify internal scripts
	wp_enqueue_script( 'theme-script',	themify_enque(THEME_URI . '/js/themify.script.js'), array('jquery'), false, true );


	//Inject variable values in gallery script
	wp_localize_script( 'theme-script', 'themifyScript', array(
		'lightbox' => themify_lightbox_vars_init(),
		'lightboxContext' => apply_filters('themify_lightbox_context', '#pagewrap'),
		'fixedHeader'	=> apply_filters('themify_fixed_header', true)
	));

	//WordPress internal script to move the comment box to the right place when replying to a user
	if ( is_single() || is_page() ) wp_enqueue_script( 'comment-reply' );
}

/**
 * Add viewport tag for responsive layouts
 * @package themify
 */
function themify_viewport_tag(){
	echo "\n".'<meta name="viewport" content="width=device-width, initial-scale=1">'."\n";
}
add_action( 'wp_head', 'themify_viewport_tag' );


/* Custom Write Panels
/***************************************************************************/

	///////////////////////////////////////
	// Setup Write Panel Options
	///////////////////////////////////////
if ( ! function_exists( 'themify_theme_init_types' ) ) {
	/**
	 * Initialize custom panel with its definitions
	 * Custom panel definitions are located in admin/post-type-TYPE.php
	 * @since 1.0.7
	 */
	function themify_theme_init_types() {

		// Feature Image
		$post_image = array(
			'name' 		=> 'post_image',
			'title' 	=> __('Featured Image', 'themify'),
			'description' => '',
			'type' 		=> 'image',
			'meta'		=> array()
		);
		// Featured Image Size
		$featured_image_size = array(
			'name'	=>	'feature_size',
			'title'	=>	__('Image Size', 'themify'),
			'description' => __('Image sizes can be set at <a href="options-media.php">Media Settings</a> and <a href="https://wordpress.org/plugins/regenerate-thumbnails/" target="_blank">Regenerated</a>', 'themify'),
			'type'		 =>	'featimgdropdown',
			'display_callback' => 'themify_is_image_script_disabled'
		);
		// Image Width
		$image_width = array(
			'name' 		=> 'image_width',
			'title' 	=> __('Image Width', 'themify'),
			'description' => '',
			'type' 		=> 'textbox',
			'meta'		=> array('size'=>'small')
		);
		// Image Height
		$image_height = array(
			'name' 		=> 'image_height',
			'title' 	=> __('Image Height', 'themify'),
			'description' => __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify'),
			'type' 		=> 'textbox',
			'meta'		=> array('size'=>'small')
		);
		// External Link
		$external_link = array(
			'name' 		=> 'external_link',
			'title' 	=> __('External Link', 'themify'),
			'description' => __('Link Featured Image and Post Title to external URL', 'themify'),
			'type' 		=> 'textbox',
			'meta'		=> array()
		);

		// Post Meta Box Options
		$post_meta_box_options = array(
			// Layout
			array(
				"name" 		=> "layout",
				"title" 		=> __('Sidebar Option', 'themify'),
				"description" => "",
				"type" 		=> "layout",
				'show_title' => true,
				"meta"		=> array(
					array("value" => "default", "img" => "images/layout-icons/default.png", "selected" => true, 'title' => __('Default', 'themify')),
					array("value" => "sidebar1", "img" => "images/layout-icons/sidebar1.png", 'title' => __('Sidebar Right', 'themify')),
					array("value" => "sidebar1 sidebar-left", "img" => "images/layout-icons/sidebar1-left.png", 'title' => __('Sidebar Left', 'themify')),
					array("value" => "sidebar-none", "img" => "images/layout-icons/sidebar-none.png", 'title' => __('No Sidebar', 'themify'))
				)
			),
			// Content Width
			array(
				'name'=> 'content_width',
				'title' => __('Content Width', 'themify'),
				'description' => '',
				'type' => 'layout',
				'show_title' => true,
				'meta' => array(
					array(
						'value' => 'default_width',
						'img' => 'themify/img/default.png',
						'selected' => true,
						'title' => __( 'Default', 'themify' )
					),
					array(
						'value' => 'full_width',
						'img' => 'themify/img/fullwidth.png',
						'title' => __( 'Fullwidth', 'themify' )
					)
				)
			),
			// Post Image
			array(
				"name" 		=> "post_image",
				"title" 		=> __('Featured Image', 'themify'),
				"description" => '',
				"type" 		=> "image",
				"meta"		=> array()
			),
			// Featured Image Size
			array(
				'name'	=>	'feature_size',
				'title'	=>	__('Image Size', 'themify'),
				'description' => __('Image sizes can be set at <a href="options-media.php">Media Settings</a> and <a href="https://wordpress.org/plugins/regenerate-thumbnails/" target="_blank">Regenerated</a>', 'themify'),
				'type'		 =>	'featimgdropdown',
				'display_callback' => 'themify_is_image_script_disabled'
			),
			// Image Width
			array(
				"name" 		=> "image_width",
				"title" 		=> __('Image Width', 'themify'),
				"description" => "",
				"type" 		=> "textbox",
				"meta"		=> array("size"=>"small")
			),
			// Image Height
			array(
				"name" 		=> "image_height",
				"title" 		=> __('Image Height', 'themify'),
				"description" => __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify'),
				"type" 		=> "textbox",
				"meta"		=> array("size"=>"small")
			),
			// Hide Post Title
			array(
				"name" 		=> "hide_post_title",
				"title" 		=> __('Hide Post Title', 'themify'),
				"description" => "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Unlink Post Title
			array(
				"name" 		=> "unlink_post_title",
				"title" 		=> __('Unlink Post Title', 'themify'),
				"description" => __('Unlink post title (it will display the post title without link)', 'themify'),
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Hide Post Meta
			array(
				"name" 		=> "hide_post_meta",
				"title" 		=> __('Hide Post Meta', 'themify'),
				"description" => "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Hide Post Date
			array(
				"name" 		=> "hide_post_date",
				"title" 		=> __('Hide Post Date', 'themify'),
				"description" => "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Hide Post Image
			array(
				"name" 		=> "hide_post_image",
				"title" 		=> __('Hide Featured Image', 'themify'),
				"description" => "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Unlink Post Image
			array(
				"name" 		=> "unlink_post_image",
				"title" 		=> __('Unlink Featured Image', 'themify'),
				"description" => __('Display the Featured Image without link', 'themify'),
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Video URL
			array(
				"name" 		=> "video_url",
				"title" 		=> __('Video URL', 'themify'),
				"description" => __('Video embed URL such as YouTube or Vimeo video url (<a href="https://themify.me/docs/video-embeds">details</a>).', 'themify'),
				"type" 		=> "textbox",
				"meta"		=> array()
			),
			// External Link
			array(
				"name" 		=> "external_link",
				"title" 		=> __('External Link', 'themify'),
				"description" => __('Link Featured Image and Post Title to external URL', 'themify'),
				"type" 		=> "textbox",
				"meta"		=> array()
			),
			// Lightbox Link + Zoom icon
			themify_lightbox_link_field()
		);


		// Page Meta Box Options
		$page_meta_box_options = array(
			// Page Layout
			array(
				"name" 		=> "page_layout",
				"title"		=> __('Sidebar Option', 'themify'),
				"description"	=> "",
				"type"		=> "layout",
				'show_title' => true,
				"meta"		=> array(
					array("value" => "default", "img" => "images/layout-icons/default.png", "selected" => true, 'title' => __('Default', 'themify')),
					array("value" => "sidebar1", "img" => "images/layout-icons/sidebar1.png", 'title' => __('Sidebar Right', 'themify')),
					array("value" => "sidebar1 sidebar-left", "img" => "images/layout-icons/sidebar1-left.png", 'title' => __('Sidebar Left', 'themify')),
					array("value" => "sidebar-none", "img" => "images/layout-icons/sidebar-none.png", 'title' => __('No Sidebar', 'themify'))
				)
			),
			// Content Width
			array(
				'name'=> 'content_width',
				'title' => __('Content Width', 'themify'),
				'description' => 'Select "Fullwidth" will remove the padding space on the content area.',
				'type' => 'layout',
				'show_title' => true,
				'meta' => array(
					array(
						'value' => 'default_width',
						'img' => 'themify/img/default.png',
						'selected' => true,
						'title' => __( 'Default', 'themify' )
					),
					array(
						'value' => 'full_width',
						'img' => 'themify/img/fullwidth.png',
						'title' => __( 'Fullwidth', 'themify' )
					)
				)
			),
			// Hide page title
			array(
				"name" 		=> "hide_page_title",
				"title"		=> __('Hide Page Title', 'themify'),
				"description"	=> "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
		    // Custom menu for page
            array(
                'name' 		=> 'custom_menu',
                'title'		=> __( 'Custom Menu', 'themify' ),
                'description'	=> '',
                'type'		=> 'dropdown',
                'meta'		=> themify_get_available_menus(),
            ),
		);

		// Query Post Meta Box Options
		$query_post_meta_box_options = array(
			// Notice
			array(
				'name' => '_query_posts_notice',
				'title' => '',
				'description' => '',
				'type' => 'separator',
				'meta' => array(
					'html' => '<div class="themify-info-link">' . sprintf( __( '<a href="%s">Query Posts</a> allows you to query WordPress posts from any category on the page. To use it, select a Query Category.', 'themify' ), 'http://themify.me/docs/query-posts' ) . '</div>'
				),
			),
			// Query Category
			array(
				"name" 		=> "query_category",
				"title"		=> __('Query Category', 'themify'),
				"description"	=> __('Select a category or enter multiple category IDs (eg. 2,5,6). Enter 0 to display all category.', 'themify'),
				"type"		=> "query_category",
				"meta"		=> array()
			),
			// Query All Post Types
			array(
				'name' => 'query_all_post_types',
				'type' => 'dropdown',
				'title' => __( 'Query All Post Types', 'themify'),
				'meta' =>array(
					array(
					'value' => '',
					'name' => '',
					),
					array(
					'value' => 'yes',
					'name' => 'Yes',
					),
					array(
					'value' => 'no',
					'name' => 'No',
					),
				)
			),
			// Descending or Ascending Order for Posts
			array(
				'name' 		=> 'order',
				'title'		=> __('Order', 'themify'),
				'description'	=> '',
				'type'		=> 'dropdown',
				'meta'		=> array(
					array('name' => __('Descending', 'themify'), 'value' => 'desc', 'selected' => true),
					array('name' => __('Ascending', 'themify'), 'value' => 'asc')
				)
			),
			// Criteria to Order By
			array(
				'name' 		=> 'orderby',
				'title'		=> __('Order By', 'themify'),
				'description'	=> '',
				'type'		=> 'dropdown',
				'meta'		=> array(
					array('name' => __('Date', 'themify'), 'value' => 'date', 'selected' => true),
					array('name' => __('Random', 'themify'), 'value' => 'rand'),
					array('name' => __('Author', 'themify'), 'value' => 'author'),
					array('name' => __('Post Title', 'themify'), 'value' => 'title'),
					array('name' => __('Comments Number', 'themify'), 'value' => 'comment_count'),
					array('name' => __('Modified Date', 'themify'), 'value' => 'modified'),
					array('name' => __('Post Slug', 'themify'), 'value' => 'name'),
					array('name' => __('Post ID', 'themify'), 'value' => 'ID'),
					array('name' => __( 'Custom Field String', 'themify' ), 'value' => 'meta_value'),
					array('name' => __( 'Custom Field Numeric', 'themify' ), 'value' => 'meta_value_num')
				),
				'hide' => 'date|rand|author|title|comment_count|modified|name|ID field-meta-key'
			),
			array(
				'name'			=> 'meta_key',
				'title'			=> __( 'Custom Field Key', 'themify' ),
				'description'	=> '',
				'type'			=> 'textbox',
				'meta'			=> array('size' => 'medium'),
				'class'			=> 'field-meta-key'
			),
			// Section Categories
			array(
				"name" 		=> "section_categories",
				"title" 		=> __('Section Categories', 'themify'),
				"description" => __('Display multiple query categories separately', 'themify'),
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Allow Sorting
			array(
				"name" 		=> "allow_sorting",
				"title"		=> __('Allow Sorting', 'themify'),
				"description"	=> __('This will add a sorting navigation', 'themify'),
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Post Layout
			array(
				"name" 		=> "layout",
				"title"		=> __('Query Post Layout', 'themify'),
				"description"	=> "",
				"type"		=> "layout",
				'show_title' => true,
				"meta"		=> array(
					array('value' => 'list-post', 'img' => 'images/layout-icons/list-post.png', 'selected' => true, 'title' => __('List Post', 'themify')),
					array('value' => 'grid4', 'img' => 'images/layout-icons/grid4.png', 'title' => __('Grid 4', 'themify')),
					array('value' => 'grid3', 'img' => 'images/layout-icons/grid3.png', 'title' => __('Grid 3', 'themify')),
					array('value' => 'grid2', 'img' => 'images/layout-icons/grid2.png', 'title' => __('Grid 2', 'themify')),
					array('value' => 'list-large-image', 'img' => 'images/layout-icons/list-large-image.png', 'title' => __('List Large Image', 'themify')),
					array('value' => 'list-thumb-image', 'img' => 'images/layout-icons/list-thumb-image.png', 'title' => __('List Thumb Image', 'themify')),
					array('value' => 'grid2-thumb', 'img' => 'images/layout-icons/grid2-thumb.png', 'title' => __('Grid 2 Thumb', 'themify'))
				)
			),
			// Posts Per Page
			array(
				"name" 		=> "posts_per_page",
				"title"		=> __('Posts per page', 'themify'),
				"description"	=> "",
				"type"		=> "textbox",
				"meta"		=> array("size" => "small")
			),

			// Display Content
			array(
				"name" 		=> "display_content",
				"title"		=> __('Display Content', 'themify'),
				"description"	=> "",
				"type"		=> "dropdown",
				'meta'		=> array(
					array( 'name' => __('Full Content', 'themify'), 'value' => 'content' ),
					array( 'name' => __('Excerpt', 'themify'), 'value' => 'excerpt', 'selected' => true ),
					array( 'name' => __('None', 'themify'), 'value' => 'none' )
				),
				'default' => 'excerpt',
			),
			// Featured Image Size
			array(
				'name'	=>	'feature_size_page',
				'title'	=>	__('Image Size', 'themify'),
				'description' => __('Image sizes can be set at <a href="options-media.php">Media Settings</a> and <a href="https://wordpress.org/plugins/regenerate-thumbnails/" target="_blank">Regenerated</a>', 'themify'),
				'type'		 =>	'featimgdropdown',
				'display_callback' => 'themify_is_image_script_disabled'
			),
			// Image Width
			array(
				"name" 		=> "image_width",
				"title" 		=> __('Image Width', 'themify'),
				"description" => "",
				"type" 		=> "textbox",
				"meta"		=> array("size"=>"small")
			),
			// Image Height
			array(
				"name" 		=> "image_height",
				"title" 		=> __('Image Height', 'themify'),
				"description" => __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify'),
				"type" 		=> "textbox",
				"meta"		=> array("size"=>"small")
			),
			// Hide Title
			array(
				"name" 		=> "hide_title",
				"title"		=> __('Hide Post Title', 'themify'),
				"description"	=> "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Unlink Post Title
			array(
				"name" 		=> "unlink_title",
				"title" 		=> __('Unlink Post Title', 'themify'),
				"description" => __('Unlink post title (it will display the post title without link)', 'themify'),
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Hide Post Date
			array(
				"name" 		=> "hide_date",
				"title"		=> __('Hide Post Date', 'themify'),
				"description"	=> "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Hide Post Meta
			array(
				"name" 		=> "hide_meta",
				"title"		=> __('Hide Post Meta', 'themify'),
				"description"	=> "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Hide Post Image
			array(
				"name" 		=> "hide_image",
				"title" 		=> __('Hide Featured Image', 'themify'),
				"description" => "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Unlink Post Image
			array(
				"name" 		=> "unlink_image",
				"title" 		=> __('Unlink Featured Image', 'themify'),
				"description" => __('Display the Featured Image without link', 'themify'),
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Page Navigation Visibility
			array(
				"name" 		=> "hide_navigation",
				"title"		=> __('Hide Page Navigation', 'themify'),
				"description"	=> "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			)
		);

		/** Slider Meta Box Options */
		$slider_meta_box = array(
			// Post Layout
			array(
				'name' 		=> 'layout',
				'title'		=> __('Slide Layout', 'themify'),
				'description'	=> '',
				'type'		=> 'layout',
				'show_title' => true,
				'meta'		=> array(
					array('value' => 'slider-default', 'img' => 'images/layout-icons/slider-default.png', 'selected' => true, 'title' => __('Default', 'themify')),
					array('value' => 'slider-image-only', 'img' => 'images/layout-icons/slider-image-only.png', 'title' => __('Image Only', 'themify')),
					array('value' => 'slider-content-only', 'img' => 'images/layout-icons/slider-content-only.png', 'title' => __('Content Only', 'themify')),
					array('value' => 'slider-image-caption','img' => 'images/layout-icons/slider-image-caption.png', 'title' => __('Image Caption', 'themify'))
				)),
			// Feature Image
			$post_image,
			// Featured Image Size
			$featured_image_size,
			// Image Width
			$image_width,
			// Image Height
			$image_height,
			// External Link
			$external_link,
			// Lightbox Link
			themify_lightbox_link_field(),
			array(
				'name' 		=> 'video_url',
				'title' 	=> __('Video URL', 'themify'),
				'description' => __('URL to embed a video instead of featured image', 'themify'),
				'type' 		=> 'textbox',
				'meta'		=> array()
			)
		);

		/** Portfolio Meta Box Options */
		$portfolio_meta_box = array(
			// Content Width
			array(
				'name'=> 'content_width',
				'title' => __('Content Width', 'themify'),
				'description' => '',
				'type' => 'layout',
				'show_title' => true,
				'meta' => array(
					array(
						'value' => 'default_width',
						'img' => 'themify/img/default.png',
						'selected' => true,
						'title' => __( 'Default', 'themify' )
					),
					array(
						'value' => 'full_width',
						'img' => 'themify/img/fullwidth.png',
						'title' => __( 'Fullwidth', 'themify' )
					)
				)
			),
			// Feature Image
			$post_image,
			// Gallery Shortcode
			array(
				'name' 		=> '_gallery_shortcode',
				'title' 	=> __('Gallery', 'themify'),
				'description' => '',
				'type' 		=> 'gallery_shortcode'
			),
			// Featured Image Size
			$featured_image_size,
			// Image Width
			$image_width,
			// Image Height
			$image_height,
			// Hide Title
			array(
				"name" 		=> "hide_post_title",
				"title"		=> __('Hide Post Title', 'themify'),
				"description"	=> "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Unlink Post Title
			array(
				"name" 		=> "unlink_post_title",
				"title" 		=> __('Unlink Post Title', 'themify'),
				"description" => __('Unlink post title (it will display the post title without link)', 'themify'),
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Hide Post Date
			array(
				"name" 		=> "hide_post_date",
				"title"		=> __('Hide Post Date', 'themify'),
				"description"	=> "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Hide Post Meta
			array(
				"name" 		=> "hide_post_meta",
				"title"		=> __('Hide Post Meta', 'themify'),
				"description"	=> "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Hide Post Image
			array(
				"name" 		=> "hide_post_image",
				"title" 		=> __('Hide Featured Image', 'themify'),
				"description" => "",
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// Unlink Post Image
			array(
				"name" 		=> "unlink_post_image",
				"title" 		=> __('Unlink Featured Image', 'themify'),
				"description" => __('Display the Featured Image without link', 'themify'),
				"type" 		=> "dropdown",
				"meta"		=> array(
					array("value" => "default", "name" => "", "selected" => true),
					array("value" => "yes", 'name' => __('Yes', 'themify')),
					array("value" => "no",	'name' => __('No', 'themify'))
				)
			),
			// External Link
			$external_link,
			// Lightbox Link
			themify_lightbox_link_field(),
			// Shortcode ID
			array(
				'name' 		=> '_post_id_info',
				'title' 	=> __('Shortcode ID', 'themify'),
				'description' => __('To show this use [portfolio id="%s"]', 'themify'),
				'type' 		=> 'post_id_info'
			)
		);

		// Highlight Meta Box Options
		$highlight_meta_box = array(
			// Feature Image
			$post_image,
			// Featured Image Size
			$featured_image_size,
			// Image Width
			$image_width,
			// Image Height
			$image_height,
			// External Link
			$external_link,
			// Lightbox Link
			themify_lightbox_link_field(),
			// Shortcode ID
			array(
				'name' 		=> '_post_id_info',
				'title' 	=> __('Shortcode ID', 'themify'),
				'description' => __('To show this use [highlight id="%s"]', 'themify'),
				'type' 		=> 'post_id_info'
			)
		);

		// Testimonial Meta Box Options
		$testimonial_meta_box = array(
			// Feature Image
			$post_image,
			// Featured Image Size
			$featured_image_size,
			// Image Width
			$image_width,
			// Image Height
			$image_height,
			// Testimonial Author Name
			array(
				'name' 		=> '_testimonial_name',
				'title' 	=> __('Testimonial Author Name', 'themify'),
				'description' => '',
				'type' 		=> 'textbox',
				'meta'		=> array()
			),
			// Testimonial Author Link
			array(
				'name' 		=> '_testimonial_link',
				'title' 	=> __('Testimonial Author Link', 'themify'),
				'description' => '',
				'type' 		=> 'textbox',
				'meta'		=> array()
			),
			// Testimonial Author Company
			array(
				'name' 		=> '_testimonial_company',
				'title' 	=> __('Testimonial Author Company', 'themify'),
				'description' => '',
				'type' 		=> 'textbox',
				'meta'		=> array()
			),
			// Testimonial Author Position
			array(
				'name' 		=> '_testimonial_position',
				'title' 	=> __('Testimonial Author Position', 'themify'),
				'description' => '',
				'type' 		=> 'textbox',
				'meta'		=> array()
			),
			// Shortcode ID
			array(
				'name' 		=> '_post_id_info',
				'title' 	=> __('Shortcode ID', 'themify'),
				'description' => __('To show this use [testimonial id="%s"]', 'themify'),
				'type' 		=> 'post_id_info'
			)
		);

		// Team Meta Box Options
		$team_meta_box = array(
			// Content Width
			array(
				'name'=> 'content_width',
				'title' => __('Content Width', 'themify'),
				'description' => '',
				'type' => 'layout',
				'show_title' => true,
				'meta' => array(
					array(
						'value' => 'default_width',
						'img' => 'themify/img/default.png',
						'selected' => true,
						'title' => __( 'Default', 'themify' )
					),
					array(
						'value' => 'full_width',
						'img' => 'themify/img/fullwidth.png',
						'title' => __( 'Fullwidth', 'themify' )
					)
				)
			),
			// Feature Image
			$post_image,
			// Featured Image Size
			$featured_image_size,
			// Image Width
			$image_width,
			// Image Height
			$image_height,
			// Team Title
			array(
				'name' 		=> '_team_title',
				'title' 	=> __('Team Member Position', 'themify'),
				'description' => '',
				'type' 		=> 'textbox',
				'meta'		=> array()
			),
			// Shortcode ID
			array(
				'name' 		=> '_post_id_info',
				'title' 	=> __('Shortcode ID', 'themify'),
				'description' => __('To show this use [team id="%s"]', 'themify'),
				'type' 		=> 'post_id_info'
			)
		);

		///////////////////////////////////////
		// Build Write Panels
		///////////////////////////////////////
		themify_build_write_panels( apply_filters('themify_theme_meta_boxes',
			array(
				array(
					'name'		=> __('Post Options', 'themify'), // Name displayed in box
					'id' => 'post-options',
					'options'	=> $post_meta_box_options, 	// Field options
					'pages'	=> 'post'					// Pages to show write panel
				),
				array(
					'name'		=> __('Page Options', 'themify'),
					'id' => 'page-options',
					'options'	=> $page_meta_box_options,
					'pages'	=> 'page'
				),
				array(
					"name"		=> __('Query Posts', 'themify'),
					'id' => 'query-posts',
					"options"	=> $query_post_meta_box_options,
					"pages"	=> "page"
				),
				array(
					'name'		=> __('Slider Options', 'themify'),
					'id' => 'slider-options',
					'options'	=> $slider_meta_box,
					'pages'	=> 'slider'
				),
				array(
					'name'	=> __('Portfolio Options', 'themify'),
					'id' => 'portfolio-options',
					'options' => $portfolio_meta_box,
					'pages'	=> 'portfolio'
				),
				array(
					'name'	=> __('Highlight Options', 'themify'),
					'id' => 'highlight-options',
					'options' => $highlight_meta_box,
					'pages'	=> 'highlight'
				),
				array(
					'name'	=> __('Testimonial Options', 'themify'),
					'id' => 'testimonial-options',
					'options' => $testimonial_meta_box,
					'pages'	=> 'testimonial'
				),
				array(
					'name'	=> __('Team Options', 'themify'),
					'id' => 'team-options',
					'options' => $team_meta_box,
					'pages'	=> 'team'
				)
			)
		));
	}
}
add_action( 'after_setup_theme', 'themify_theme_init_types' );
/* 	Custom Functions
/***************************************************************************/

	///////////////////////////////////////
	// Enable WordPress feature image
	///////////////////////////////////////
	add_theme_support( 'post-thumbnails' );

	///////////////////////////////////////
	// Filter RSS Feed to include Custom Fields
	///////////////////////////////////////
	add_filter('the_content', 'themify_post_format_custom_fields');

	function themify_post_format_custom_fields( $content ) {

		global $post, $id, $themify_check;
		if(!is_feed() || $themify_check == true){
			return $content;
		}

		if(has_post_format( 'image' ) && themify_check('post_image')) {
			$content = "<img src='".themify_get('post_image')."'><br>".$content;
		} elseif(has_post_format( 'video' ) && themify_check('video_url')) {
			$themify_check = true;
			$content = apply_filters('the_content', themify_get('video_url')) . $content;
		}
		$themify_check = false;
		return $content;
	}

	///////////////////////////////////////
	// Register Custom Menu Function
	///////////////////////////////////////
	function themify_register_custom_nav() {
		if (function_exists('register_nav_menus')) {
			register_nav_menus( array(
				'main-nav' => __( 'Main Navigation', 'themify' ),
				'footer-nav' => __( 'Footer Navigation', 'themify' ),
			) );
		}
	}

	// Register Custom Menu Function - Action
	add_action('init', 'themify_register_custom_nav');

	///////////////////////////////////////
	// Default Main Nav Function
	///////////////////////////////////////
	function themify_default_main_nav() {
		echo '<ul id="main-nav" class="main-nav clearfix">';
		wp_list_pages('title_li=');
		echo '</ul>';
	}

	///////////////////////////////////////
	// Register Sidebars
	///////////////////////////////////////
	if ( function_exists('register_sidebar') ) {
		register_sidebar(array(
			'name' => __('Sidebar', 'themify'),
			'id' => 'sidebar-main',
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget' => '</div>',
			'before_title' => '<h4 class="widgettitle">',
			'after_title' => '</h4>',
		));
		register_sidebar(array(
			'name' => __('Social Widget', 'themify'),
			'id' => 'social-widget',
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget' => '</div>',
			'before_title' => '<strong class="widgettitle">',
			'after_title' => '</strong>',
		));
	}

	///////////////////////////////////////
	// Footer Sidebars
	///////////////////////////////////////
	themify_register_grouped_widgets();

if( ! function_exists('themify_theme_comment') ) {
	/**
	 * Custom Theme Comment
	 * @param object $comment Current comment.
	 * @param array $args Parameters for comment reply link.
	 * @param int $depth Maximum comment nesting depth.
	 * @since 1.0.0
	 */
	function themify_theme_comment($comment, $args, $depth) {
	   $GLOBALS['comment'] = $comment;
	   ?>
		<li id="comment-<?php comment_ID() ?>">
			<p class="comment-author">
				<?php echo get_avatar($comment,$size='68'); ?>
				<?php printf('<cite>%s</cite>', get_comment_author_link()) ?><br />
				<small class="comment-time"><strong><?php comment_date( apply_filters( 'themify_comment_date', '' ) ); ?></strong> @ <?php comment_time( apply_filters( 'themify_comment_time', '' ) ); ?><?php edit_comment_link( __('Edit', 'themify'),' [',']') ?></small>
			</p>
			<div class="commententry">
				<?php if ($comment->comment_approved == '0') : ?>
				<p><em><?php _e('Your comment is awaiting moderation.', 'themify') ?></em></p>
				<?php endif; ?>

				<?php comment_text() ?>
			</div>
			<p class="reply">
			<?php comment_reply_link(array_merge( $args, array('add_below' => 'comment', 'depth' => $depth, 'reply_text' => __( 'Reply', 'themify' ), 'max_depth' => $args['max_depth']))) ?>
			</p>
		<?php
	}
}

	///////////////////////////////////////
	// Disable/Enable Fixed Header
	///////////////////////////////////////
	function themify_switch_fixed_header() {
		if(themify_check('setting-fixed_header_disabled')){
			return false;
		} else {
			return true;
		}
	}
	add_filter( 'themify_fixed_header', 'themify_switch_fixed_header', 10 );

// Portfolio comments filter
function portfolio_comments_open( $open, $post_id ) {
	$post = get_post( $post_id );
	! empty( $post ) && 'portfolio' == $post->post_type
		&& themify_check( 'setting-portfolio_comments' ) && ( $open = true );
	return $open;
}
add_filter( 'comments_open', 'portfolio_comments_open', 10, 2 );