<?php
if(!class_exists('Themify_Shortcode')){
	/**
	 * Base class for shortcode creation
	 */
	class Themify_Shortcode {

		var $instance = 0;
		var $prefix = '';
		var $atts = array();

		function __construct($args = array()) {
			$defaults = array(
				'prefix' => ''
			);
			$args = wp_parse_args($args, $defaults);
			$this->prefix = $args['prefix'];
			$this->atts = $args['atts'];
			add_action( 'init', array(&$this, 'init') );
		}

		/**
		 * Overridable function that runs at the beginning of the shortcode output
		 */
		function shortcode_start(){}

		/**
		 * Initialization function
		 */
		function init() {
			add_shortcode( $this->prefix, array( $this, 'init_shortcode' ) );
			add_shortcode( 'themify_'.$this->prefix.'_posts', array( $this, 'init_shortcode' ));
		}

		/**
		 * Add shortcode to WP
		 * @param $atts Array shortcode attributes
		 * @return String
		 * @since 1.0.0
		 */
		function init_shortcode($atts) {
			$this->shortcode_start();
			$this->instance++;

			return do_shortcode($this->shortcode( shortcode_atts($this->atts, $atts), $this->prefix ));
		}

		/**
		 * Parses the arguments given as category to see if they are category IDs or slugs and returns a proper tax_query
		 * @param $category
		 * @param $post_type
		 * @return array
		 */
		function parse_category_args($category, $post_type){
			if( 'all' != $category){
				$tax_query_terms = explode(',', $category);
				if(preg_match('#[a-z]#', $category)){
					return array( array(
						'taxonomy' => $post_type . '-category',
						'field' => 'slug',
						'terms' => $tax_query_terms
					));
				} else {
					return array( array(
						'taxonomy' => $post_type . '-category',
						'field' => 'id',
						'terms' => $tax_query_terms
					));
				}
			}
		}

		/**
		 * Register and/or enqueue scripts and stylesheets to use later
		 * @since 1.0.0
		 */
		function register_scripts_styles(){
			// Register styles
			//wp_register_style($this->prefix.'-css', get_template_directory_uri().'/js/'.$this->prefix.'.css', array(), wp_get_theme()->Version);
			//wp_register_script($this->prefix.'-js', get_template_directory_uri().'/js/'.$this->prefix.'js', array('jquery'), wp_get_theme()->Version);
		}

		/**
		 * Function to override in inherited classes
		 * @param array Shortcode attributes that have already been parsed with shortcode_atts
		 */
		function shortcode( $atts = array(), $post_type ) { }

		/**
		 * Returns the excerpt or the content based on user input
		 * @param string
		 * @return string
		 */
		function display($display = 'content'){
			if( 'excerpt' == $display ){
				return get_the_excerpt();
			} elseif( 'content' == $display ){
				//global $more; $more = 0;
				return apply_filters('the_content', get_the_content());
			}
			return '';
		}

		/**
		 * Returns link wrapped in paragraph either to the post type archive page or a custom location
		 * @param bool|string False does nothing, true goes to archive page, custom string sets custom location
		 * @param string Text to link
		 * @return string
		 */
		function section_link($more_link = false, $more_text, $post_type){
			if( $more_link ){
				if( 'true' == $more_link ){
					$more_link = get_post_type_archive_link($post_type);
				}
				return '<p class="more-link-wrap"><a href="' . esc_url($more_link) . '" class="more-link">' . $more_text . '</a></p>';
			}
			return '';
		}

		/**
		 * Returns the post type title wrapped in a specified tag
		 * @param string $title Whether to show the title or not
		 * @param string $tag HTML element used to wrap the title text
		 * @param bool $add_link Pass false to avoid linking the title
		 * @return string
		 */
		function post_title($title, $tag = 'h1', $add_link = true){
			if( 'yes' == $title ){
				if($add_link){
					$link_before = '<a href="'.$this->get_entry_link().'" title="'.the_title_attribute('echo=0').'">';
					$link_after = '</a>';
				} else {
					$link_before = '';
					$link_after = '';
				}
				return '<'.$tag.' class="post-title">' . $link_before . get_the_title() . $link_after . '</'.$tag.'>';
			}
			return '';
		}

		/**
		 * Returns the post image according to specified dimensions
		 * @param string $image Whether to show the image or not
		 * @param number $width
		 * @param number $width
		 * @return string
		 */
		function post_image($image, $width, $height, $add_link = true){
			if( 'yes' == $image ){
				if($add_link){
					$before = '<a href="'.$this->get_entry_link().'" title="'.the_title_attribute('echo=0').'">';
					$zoom_icon = themify_zoom_icon(false);
					$after = $zoom_icon . '</a>';
				} else {
					$before = '';
					$after = '';
				}
				$out = '<figure class="post-image">';
					$out .= $before . themify_get_image("w=$width&h=$height&ignore=true") . $after;
				$out .= '</figure>';
				return $out;
			}
			return '';
		}

		function get_entry_link($link_images = true, $img_id = ''){

			if ( themify_get('external_link') != '') {
			$link = esc_url(themify_get('external_link'));
			} elseif ( themify_get('lightbox_link') != '') {
				$link = esc_url(themify_get('lightbox_link'));
				if(themify_check('iframe_url')) {
					$do_iframe = '?iframe=true&width=100%&height=100%';
				} else {
					$do_iframe = '';
				}
				$link = $link . $do_iframe . '" class="themify_lightbox';
			} else {
				$link = get_permalink();
			}

			return $link;
		}

		/**
		 * Returns a link to edit the post
		 * @param number $post_id
		 * @return string
		 */
		function edit_link($post_id){
			return current_user_can('edit_others_posts')? '<p>[<a href="' . get_edit_post_link($post_id) . '">' . __('Edit', 'string') . '</a>]</p>' : '';
		}

		/**
		 * Parses gallery shortcode retrieving attachment IDs and returns an array with the queried objects
		 * @return array Attachments referenced in [gallery]
		 */
		public function get_images_from_gallery_shortcode(){
			$sc_gallery = preg_replace('#\[gallery(.*)ids="([0-9|,]*)"(.*)\]#i', '$2', themify_get('_gallery_shortcode'));
			$image_ids = explode(',', str_replace(' ', '', $sc_gallery));

			// Check if portfolio has more than one image in gallery
			return get_posts(array(
				'post__in' => $image_ids,
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'numberposts' => -1,
				'orderby' => 'post__in',
				'order' => 'ASC'
			));
		}
	}
}