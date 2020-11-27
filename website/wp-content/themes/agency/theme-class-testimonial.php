<?php
if(!class_exists('Themify_Testimonial')){
	/**
	 * Class to create testimonials
	 */
	class Themify_Testimonial extends Themify_Shortcode {
		function shortcode($atts = array(), $post_type){
			extract($atts);
			$args = array(
				'post_type' => $post_type,
				'posts_per_page' => $limit,
				'order' => $order,
				'orderby' => $orderby,
				'suppress_filters' => false
			);
			$args['tax_query'] = $this->parse_category_args($category, $post_type);

			$posts = get_posts( apply_filters('themify_'.$post_type.'_shortcode_args', $args) );
			/** Collect markup generated to be returned later
			 *  @var string */
			$out = '';
			if( $posts ){
				global $post;
				$out .= '<div class="loops-wrapper shortcode ' . $post_type  . ' ' . $style . ' ">';
				foreach ($posts as $post) {
					setup_postdata($post);

					$image_width = themify_check('image_width')? themify_get('image_width') : $image_w;
					$image_height = themify_check('image_height')? themify_get('image_height') : $image_h;

					$out .= '<article id="testimonial-'.get_the_ID().'" class="post clearfix">';
						$out .= $this->post_image($image, $image_width, $image_height, false);
						$out .= '<div class="post-content">';
							$out .= $this->post_title($title, 'h3', false);
							$out .= $this->display($display);
							$out .= $this->edit_link($post->ID);
							if( 'yes' == $show_author ){
								$out .= '<p class="testimonial-author">';
								$out .= $this->author_name($post, $show_author);
								$out .= '</p><!-- /testimonial-author -->';
							}
						$out .= '</div>';
						$out .= '<!-- /.post-content -->';
					$out .= '</article>';
					$out .= '<!-- / .post -->';
				}
				wp_reset_postdata();
				$out .= $this->section_link($more_link, $more_text, $post_type);
				$out .= '</div>';
			}
			return $out;
		}
		function author_name($post, $show_author){
			$out = '';
			if( 'yes' == $show_author){
				if( $author = get_post_meta( $post->ID, '_testimonial_name', true ) )
					$out .= '<span class="dash"></span><cite class="testimonial-name">' . $author . '</cite> <br/>';

				if( $position = get_post_meta( $post->ID, '_testimonial_position', true ) )
					$out .= '<em class="testimonial-title">' . $position;

					if( $link = get_post_meta( $post->ID, '_testimonial_link', true ) ){
						if( $position ){
							$out .= ', ';
						}
						else {
							$out .= '<em class="testimonial-title">';
						}
						$out .= '<a href="'.esc_url($link).'">';
					}

						if( $company = get_post_meta( $post->ID, '_testimonial_company', true ) )
							$out .= $company;
						else
							$out .= $link;

					if( $link ) $out .= '</a>';

				$out .= '</em>';

				return $out;
			}
			return '';
		}
	}
}
