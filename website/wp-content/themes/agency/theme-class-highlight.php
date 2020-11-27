<?php
if(!class_exists('Themify_Highlight')){
	/**
	 * Class to create highlights
	 */
	class Themify_Highlight extends Themify_Shortcode {
		function shortcode($atts = array(), $post_type){
			extract($atts);
			// Parameters to get posts

			$args = array(
				'post_type' => $post_type,
				'posts_per_page' => $limit,
				'order' => $order,
				'orderby' => $orderby,
				'suppress_filters' => false
			);
			$args['tax_query'] = $this->parse_category_args($category, $post_type);

			// Single post type
			if( '' != $id ){
				$args['p'] = intval($id);
			}
			// Get posts according to parameters
			$posts = get_posts( apply_filters('themify_'.$post_type.'_shortcode_args', $args) );

			/** Collect markup generated to be returned later
			 *  @var string */
			$out = '';
			if( $posts ) {
				global $post;
				$out .= '<div class="loops-wrapper shortcode ' . $post_type  . ' ' . $style . ' ">';
				foreach ($posts as $post) {
					setup_postdata($post);

					$image_width = themify_check('image_width')? themify_get('image_width') : $image_w;
					$image_height = themify_check('image_height')? themify_get('image_height') : $image_h;

					$out .= '<article id="highlight-'.get_the_ID().'" class="post clearfix">';
						$out .= $this->post_image($image, $image_width, $image_height, themify_check('external_link') || themify_check('lightbox_link'));
						$out .= '<div class="post-content">';
							$out .= $this->post_title($title, 'h1', themify_check('external_link') || themify_check('lightbox_link'));
							$out .= $this->display($display);
							$out .= $this->edit_link($post->ID);
						$out .= '</div>';
						$out .= '<!-- /.post-content -->';
						$out .= '<meta>';
					$out .= '</article>';
					$out .= '<!-- / .post -->';
				} wp_reset_postdata();
				$out .= $this->section_link($more_link, $more_text, $post_type);
				$out .= '</div>';
			}
			return $out;
		}
	}
}
