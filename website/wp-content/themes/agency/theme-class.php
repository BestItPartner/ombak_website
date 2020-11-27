<?php
/**
 * Theme Class
 *
 * Class that provides special functions for the theme.
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Load base shortcode class
require_once('theme-class-shortcodes.php');

// Load shortcode classes
foreach (array( 'highlight', 'team', 'portfolio', 'testimonial') as $type) {
	require_once ('theme-class-'.$type.'.php');
	add_filter( "builder_is_{$type}_active", '__return_true' );
}

if(!class_exists('Themify_Agency')){
	class Themify_Agency{
	
		// Custom Post Types
		static $slider = 'slider';
		static $portfolio = 'portfolio';
		static $highlight = 'highlight';
		static $testimonial = 'testimonial';
		static $team = 'team';
		
		function set_portfolio_taxonomy($query){
			if( is_tax('portfolio-category')){
				$query->set('post_type', 'portfolio');
			}
		}

		function __construct(){
			add_action( 'init', array( $this, 'register' ) );
			add_filter( 'themify_post_types', array(&$this, 'extend_post_types'));
			add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
			// Hide 'View' link in custom taxonomy term list for Highlight Category
			add_filter( 'highlight-category_row_actions', array( $this, 'hide_term_view_link' ) );

			//Create shortcode
			$cpts = array(
				// Portfolio shortcode parameters
				self::$portfolio => array(
					'id' => '',
					'title' => '',
					'unlink_title' => '',
					'image' => 'yes', // no
					'image_w' => '',
					'image_h' => '',
					'display' => 'none', // excerpt, content
					'post_meta' => '', // yes
					'post_date' => '', // yes
					'more_link' => false, // true goes to post type archive, and admits custom link
					'more_text' => __('More &rarr;', 'themify'),
					'limit' => 4,
					'category' => 'all', // integer category ID
					'order' => 'DESC', // ASC
					'orderby' => 'date', // title, rand
					'style' => '', // grid3, grid2
					'sorting' => 'no', // yes
					'page_nav' => 'no', // yes
					'paged' => '0', // internal use for pagination, dev: previously was 1
					// slider parameters
					'autoplay' => '',
					'effect' => '',
					'timeout' => '',
					'speed' => '',
					'feature' => 'gallery', // image
				),
				// Highlight shortcode parameters
				self::$highlight => array(
					'id' => '',
					'title' => 'yes', // no
					'image' => 'yes', // no
					'image_w' => 68,
					'image_h' => 68,
					'display' => 'content', // excerpt, none
					'more_link' => false, // true goes to post type archive, and admits custom link
					'more_text' => __('More &rarr;', 'themify'),
					'limit' => 6,
					'category' => 'all', // integer category ID
					'order' => 'DESC', // ASC
					'orderby' => 'date', // title, rand
					'style' => 'grid3', // grid4, grid2, list-post
					'section_link' => false // true goes to post type archive, and admits custom link
				),
				// Testimonial shortcode parameters
				self::$testimonial => array(
					'id' => '',
					'title' => 'no', // no
					'image' => 'yes', // no
					'image_w' => 80,
					'image_h' => 80,
					'display' => 'content', // excerpt, none
					'more_link' => false, // true goes to post type archive, and admits custom link
					'more_text' => __('More &rarr;', 'themify'),
					'limit' => 4,
					'category' => 'all', // integer category ID
					'order' => 'DESC', // ASC
					'orderby' => 'date', // title, rand
					'style' => 'grid2', // grid3, grid4, list-post
					'show_author' => 'yes', // no
					'section_link' => false // true goes to post type archive, and admits custom link
				),
				// Team shortcode parameters
				self::$team => array(
					'id' => '',
					'title' => 'yes', // no
					'image' => 'yes', // no
					'unlink_title' => '',
					'unlink_image' => '',
					'image_w' => 80,
					'image_h' => 80,
					'display' => 'content', // excerpt, none
					'more_link' => false, // true goes to post type archive, and admits custom link
					'more_text' => __('More &rarr;', 'themify'),
					'limit' => 4,
					'category' => 'all', // integer category ID
					'order' => 'DESC', // ASC
					'orderby' => 'date', // title, rand
					'style' => 'grid4', // grid3, grid2, list-post
					'section_link' => false, // true goes to post type archive, and admits custom link
					'use_original_dimensions' => 'no' // yes
				)
			);
			foreach ($cpts as $shortcode => $options) {
				$class_name = apply_filters('themify_theme_class_shortcodes', 'Themify_' . ucwords($shortcode));
				$new_class = new $class_name
				(array(
					'prefix' => $shortcode,
					'atts' => $options
				));
			}
		}
	
		function register() {
			/**
			 * @var array Custom Post Types to create with its plural and singular forms
			 */
			$cpts = array(
				self::$slider => array(
					'plural' => __('Slides', 'themify'),
					'singular' => __('Slide', 'themify'),
					'supports' => array('title', 'editor', 'author', 'custom-fields'),
					'no_single' => true,
				),
				self::$portfolio => array(
					'plural' => __('Portfolios', 'themify'),
					'singular' => __('Portfolio', 'themify'),
					'rewrite' => themify_check('themify_portfolio_slug')? themify_get('themify_portfolio_slug') : apply_filters('themify_portfolio_rewrite', 'project'),
                                        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'excerpt','author')
				),
				self::$highlight => array(
					'plural' => __('Highlights', 'themify'),
					'singular' => __('Highlight', 'themify'),
					'no_single' => true,
				),
				self::$testimonial => array(
					'plural' => __('Testimonials', 'themify'),
					'singular' => __('Testimonial', 'themify'),
					'no_single' => true,
				),
				self::$team => array(
					'plural' => __('Teams', 'themify'),
					'singular' => __('Team', 'themify'),
					'rewrite' => themify_check('themify_team_slug')? themify_get('themify_team_slug') : apply_filters('themify_team_rewrite', 'team')
				)
			);
			$position = 52;
			$cpts = apply_filters( 'themify_theme_custom_post_types', $cpts );
			foreach( $cpts as $key => $cpt ){
				register_post_type( $key, array(
					'labels' => array(
						'name' => $cpt['plural'],
						'singular_name' => $cpt['singular'],
						'add_new' => __( 'Add New', 'background' ),
						'add_new_item' => sprintf(__( 'Add New %s', 'background' ), $cpt['singular']),
						'edit_item' => sprintf(__( 'Edit %s', 'background' ), $cpt['singular']),
						'new_item' => sprintf(__( 'New %s', 'background' ), $cpt['singular']),
						'view_item' => sprintf(__( 'View %s', 'background' ), $cpt['singular']),
						'search_items' => sprintf(__( 'Search %s', 'background' ), $cpt['plural']),
						'not_found' => sprintf(__( 'No %s found', 'background' ), $cpt['plural']),
						'not_found_in_trash' => sprintf(__( 'No %s found in Trash', 'background' ), $cpt['plural']),
						'menu_name' => $cpt['plural']
					),
					'supports' => isset($cpt['supports'])? $cpt['supports'] : array('title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'),
					'menu_position' => $position++,
					'hierarchical' => false,
					'public' => true,
					'show_ui' => true,
					'show_in_menu' => true,
					'show_in_nav_menus' => isset( $cpt['no_single'] )? false : true,
					'publicly_queryable' => true,
					'rewrite' => array( 'slug' => isset($cpt['rewrite'])? $cpt['rewrite']: strtolower($cpt['singular']) ),
					'query_var' => true,
					'can_export' => true,
					'capability_type' => 'post'
				));
				register_taxonomy( $key.'-category', array($key), array(
					'labels' => array(
						'name' => sprintf(__( '%s Categories', 'themify' ), $cpt['singular']),
						'singular_name' => sprintf(__( '%s Category', 'themify' ), $cpt['singular']),
						'search_items' => sprintf(__( 'Search %s Categories', 'themify' ), $cpt['singular']),
						'popular_items' => sprintf(__( 'Popular %s Categories', 'themify' ), $cpt['singular']),
						'all_items' => sprintf(__( 'All Categories', 'themify' ), $cpt['singular']),
						'parent_item' => sprintf(__( 'Parent %s Category', 'themify' ), $cpt['singular']),
						'parent_item_colon' => sprintf(__( 'Parent %s Category:', 'themify' ), $cpt['singular']),
						'edit_item' => sprintf(__( 'Edit %s Category', 'themify' ), $cpt['singular']),
						'update_item' => sprintf(__( 'Update %s Category', 'themify' ), $cpt['singular']),
						'add_new_item' => sprintf(__( 'Add New %s Category', 'themify' ), $cpt['singular']),
						'new_item_name' => sprintf(__( 'New %s Category', 'themify' ), $cpt['singular']),
						'separate_items_with_commas' => sprintf(__( 'Separate %s Category with commas', 'themify' ), $cpt['singular']),
						'add_or_remove_items' => sprintf(__( 'Add or remove %s Category', 'themify' ), $cpt['singular']),
						'choose_from_most_used' => sprintf(__( 'Choose from the most used %s Category', 'themify' ), $cpt['singular']),
						'menu_name' => sprintf(__( '%s Category', 'themify' ), $cpt['singular']),
					),
					'public' => true,
					'show_in_nav_menus' => isset( $cpt['no_single'] )? false : true,
					'show_ui' => true,
					'show_tagcloud' => true,
					'hierarchical' => true,
					'rewrite' => true,
					'query_var' => true
				));
				add_filter('manage_edit-'.$key.'-category_columns', array(&$this, 'taxonomy_header'), 10, 2);
				add_filter('manage_'.$key.'-category_custom_column', array(&$this, 'taxonomy_column_id'), 10, 3);
			}
		}
	
		/**
		 * Includes this custom post to array of cpts managed by Themify
		 * @param Array
		 * @return Array
		 */
		function extend_post_types($types){
			return array_merge($types, array(self::$slider, self::$portfolio, self::$highlight, self::$testimonial, self::$team));
		}

		/**
		 * Hide 'View' link in custom taxonomy terms list.
		 *
		 * @since 1.3.9
		 *
		 * @param $actions
		 * @return mixed
		 */
		function hide_term_view_link( $actions ) {
			unset( $actions['view'] );
			return $actions;
		}

		/**
		 * Customize custom post type messages
		 * @param $messages
		 * @return mixed
		 */
		function post_updated_messages( $messages ) {
			global $post_type, $post, $post_ID;
			switch( $post_type ) {
				case self::$portfolio:
					$view = esc_url( get_permalink( $post_ID ) );
					$preview = esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) );
					$messages[$post_type] = array(
						0 => '',
						1 => sprintf( __( 'Portfolio updated. <a href="%s">View portfolio</a>.', 'themify' ), $view ),
						2 => __( 'Custom field updated.', 'themify' ),
						3 => __( 'Custom field deleted.', 'themify' ),
						4 => __( 'Portfolio updated.', 'themify' ),
						5 => isset( $_GET['revision'] ) ? sprintf( __( 'Portfolio restored to revision from %s', 'themify' ), wp_post_revision_title( ( int ) $_GET['revision'], false ) ) : false,
						6 => sprintf( __( 'Portfolio published. <a href="%s">View portfolio</a>.', 'themify' ), $view ),
						7 => __( 'Portfolio saved.', 'themify' ),
						8 => sprintf( __( 'Portfolio submitted. <a href="%s">Preview portfolio</a>.', 'themify' ), $preview ),
						9 => sprintf( __( 'Portfolio scheduled for: <strong>%s</strong>.', 'themify' ),
							date_i18n( __( 'M j, Y @ G:i', 'themify' ), strtotime( $post->post_date ) ) ),
						10 => sprintf( __( 'Portfolio draft updated. <a href="%s">Preview portfolio</a>.', 'themify' ), $preview ),
					);
					break;
				case self::$team:
					$view = esc_url( get_permalink( $post_ID ) );
					$preview = esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) );
					$messages[$post_type] = array(
						0 => '',
						1 => sprintf( __( 'Team updated. <a href="%s">View team</a>.', 'themify' ), $view ),
						2 => __( 'Custom field updated.', 'themify' ),
						3 => __( 'Custom field deleted.', 'themify' ),
						4 => __( 'Team updated.', 'themify' ),
						5 => isset( $_GET['revision'] ) ? sprintf( __( 'Team restored to revision from %s', 'themify' ), wp_post_revision_title( ( int ) $_GET['revision'], false ) ) : false,
						6 => sprintf( __( 'Team published. <a href="%s">View team</a>.', 'themify' ), $view ),
						7 => __( 'Team saved.', 'themify' ),
						8 => sprintf( __( 'Team submitted. <a href="%s">Preview team</a>.', 'themify' ), $preview ),
						9 => sprintf( __( 'Team scheduled for: <strong>%s</strong>.', 'themify' ),
							date_i18n( __( 'M j, Y @ G:i', 'themify' ), strtotime( $post->post_date ) ) ),
						10 => sprintf( __( 'Team draft updated. <a href="%s">Preview team</a>.', 'themify' ), $preview ),
					);
					break;
				case self::$slider:
					$messages[$post_type] = array(
						0 => '',
						1 => __( 'Slide updated.', 'themify' ),
						2 => __( 'Custom field updated.', 'themify' ),
						3 => __( 'Custom field deleted.', 'themify' ),
						4 => __( 'Slide updated.', 'themify' ),
						5 => isset( $_GET['revision'] ) ? sprintf( __( 'Slide restored to revision from %s', 'themify' ), wp_post_revision_title( ( int ) $_GET['revision'], false ) ) : false,
						6 => __( 'Slide published.', 'themify' ),
						7 => __( 'Slide saved.', 'themify' ),
						8 => __( 'Slide submitted.', 'themify' ),
						9 => sprintf( __( 'Slide scheduled for: <strong>%s</strong>.', 'themify' ),
							date_i18n( __( 'M j, Y @ G:i', 'themify' ), strtotime( $post->post_date ) ) ),
						10 => __( 'Slide draft updated.', 'themify' ),
					);
					break;
				case self::$highlight:
					$messages[$post_type] = array(
						0 => '',
						1 => __( 'Highlight updated.', 'themify' ),
						2 => __( 'Custom field updated.', 'themify' ),
						3 => __( 'Custom field deleted.', 'themify' ),
						4 => __( 'Highlight updated.', 'themify' ),
						5 => isset( $_GET['revision'] ) ? sprintf( __( 'Highlight restored to revision from %s', 'themify' ), wp_post_revision_title( ( int ) $_GET['revision'], false ) ) : false,
						6 => __( 'Highlight published.', 'themify' ),
						7 => __( 'Highlight saved.', 'themify' ),
						8 => __( 'Highlight submitted.', 'themify' ),
						9 => sprintf( __( 'Highlight scheduled for: <strong>%s</strong>.', 'themify' ),
							date_i18n( __( 'M j, Y @ G:i', 'themify' ), strtotime( $post->post_date ) ) ),
						10 => __( 'Highlight draft updated.', 'themify' ),
					);
					break;
				case self::$testimonial:
					$messages[$post_type] = array(
						0 => '',
						1 => __( 'Testimonial updated.', 'themify' ),
						2 => __( 'Custom field updated.', 'themify' ),
						3 => __( 'Custom field deleted.', 'themify' ),
						4 => __( 'Testimonial updated.', 'themify' ),
						5 => isset( $_GET['revision'] ) ? sprintf( __( 'Testimonial restored to revision from %s', 'themify' ), wp_post_revision_title( ( int ) $_GET['revision'], false ) ) : false,
						6 => __( 'Testimonial published.', 'themify' ),
						7 => __( 'Testimonial saved.', 'themify' ),
						8 => __( 'Testimonial submitted.', 'themify' ),
						9 => sprintf( __( 'Testimonial scheduled for: <strong>%s</strong>.', 'themify' ),
							date_i18n( __( 'M j, Y @ G:i', 'themify' ), strtotime( $post->post_date ) ) ),
						10 => __( 'Testimonial draft updated.', 'themify' ),
					);
					break;
			}
			return $messages;
		}
		
		/**
		 * Display an additional column in categories list
		 * @since 1.1.8
		 */
		function taxonomy_header($cat_columns){
		    $cat_columns['cat_id'] = 'ID';
		    return $cat_columns;
		}
		/**
		 * Display ID in additional column in categories list
		 * @since 1.1.8
		 */
		function taxonomy_column_id($null, $column, $termid){
			return $termid;
		}
	}
}

?>