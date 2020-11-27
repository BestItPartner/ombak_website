<?php
if(!class_exists('Themify_Team')){
	/**
	 * Class to create teams
	 */
	class Themify_Team extends Themify_Shortcode {
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

			if ( '' == $unlink_image || 'no' == $unlink_image ) {
				$image_link = true;
			} else {
				$image_link = false;
			}

			/** Collect markup generated to be returned later
			 *  @var string */
			$out = '';
			if( $posts ) {
				global $post;
				$out .= '<div class="loops-wrapper shortcode ' . $post_type  . ' ' . $style . ' ">';
				foreach ($posts as $post) {
					setup_postdata($post);

					if ( 'yes' == $use_original_dimensions ) {
						$image_width = themify_check('image_width')? themify_get('image_width') : $image_w;
						$image_height = themify_check('image_height')? themify_get('image_height') : $image_h;
					} else {
						$image_width = $image_w;
						$image_height = $image_h;
					}

					$out .= '<article id="team-'.get_the_ID().'"  class="post clearfix">';

						$out .= $this->post_image($image, isset( $image_width )? $image_width : '', isset( $image_height ) ? $image_height : '', $image_link);
						$out .= '<div class="post-content">';

							$out .= $this->team_header( $title, $post->ID, $unlink_title );
							$out .= $this->display($display);
							$out .= $this->edit_link($post->ID);

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
		function team_header( $title, $post_id, $unlink_title = '' ) {
			if( 'yes' == $title ) {
				if ( '' == $unlink_title || 'no' == $unlink_title ) {
					$before = '<a href="' . get_permalink( $post_id ) . '">';
					$after = '</a>';
				} else {
					$before = '';
					$after = '';
				}

				$out = sprintf(
					'<p class="team-info">
						%s
						<span class="team-name">
							%s
						</span>
						%s
						%s
					</p>',
					$before,
					get_the_title(),
					$after,
					themify_check( '_team_title' )? '<em class="team-title">'.themify_get('_team_title').'</em>': ''
				);

				return $out;
			}
			return '';
		}
	}
}

/***************************************************
 * Themify Theme Settings Module
 ***************************************************/

if ( ! function_exists( 'themify_default_team_single_layout' ) ) {
	/**
	 * Default Single Team Layout
	 * @param array $data
	 * @return string
	 */
	function themify_default_team_single_layout( $data=array() ) {
		/**
		 * Associative array containing theme settings
		 * @var array
		 */
		$data = themify_get_data();

		/**
		 * Sidebar Layout Options
		 * @var array
		 */
		$sidebar_options = array(
			array(
				'value' => 'sidebar1',
				'img' => 'images/layout-icons/sidebar1.png',
				'title' => __('Sidebar Right', 'themify')
			),
			array(
				'value' => 'sidebar1 sidebar-left',
				'img' => 'images/layout-icons/sidebar1-left.png',
				'title' => __('Sidebar Left', 'themify')
			),
			array(
				'value' => 'sidebar-none',
				'img' => 'images/layout-icons/sidebar-none.png',
				'title' => __('No Sidebar', 'themify'),
				'selected' => true
			)
		);

		/**
		 * Variable prefix key
		 * @var string
		 */
		$prefix = 'setting-default_team_single_';

		/**
		 * Sidebar Layout
		 * @var string
		 */
		$layout = isset( $data[$prefix.'layout'] ) ? $data[$prefix.'layout'] : 'sidebar-none';

		/**
		 * Basic default options '', 'yes', 'no'
		 * @var array
		 */
		$default_options = array(
			array('name'=>'','value'=>''),
			array('name'=>__('Yes', 'themify'),'value'=>'yes'),
			array('name'=>__('No', 'themify'),'value'=>'no')
		);

		/**
		 * HTML for settings panel
		 * @var string
		 */
		$output = '';

		/**
		 * Sidebar Layout
		 */
		$output .= '<p>
						<span class="label">' . __('Team Sidebar Option', 'themify') . '</span>';
						foreach($sidebar_options as $option){
							if(($layout == '' || !$layout || !isset($layout)) && $option['selected']){
								$layout = $option['value'];
							}
							if($layout == $option['value']){
								$class = 'selected';
							} else {
								$class = '';
							}
							$output .= '<a href="#" class="preview-icon '.$class.'" title="'.$option['title'].'"><img src="'.THEME_URI.'/'.$option['img'].'" alt="'.$option['value'].'"  /></a>';
						}
						$output .= '<input type="hidden" name="'.$prefix.'layout" class="val" value="'.$layout.'" />';
		$output .= '</p>';

		/**
		 * Hide Team Title
		 */
		$output .= '<p>
						<span class="label">' . __('Hide Team Title', 'themify') . '</span>
						<select name="'.$prefix.'hide_title">' .
							themify_options_module($default_options, $prefix.'hide_title') . '
						</select>
					</p>';

		/**
		 * Unlink Team Title
		 */
		$output .=	'<p>
						<span class="label">' . __('Unlink Team Title', 'themify') . '</span>
						<select name="'.$prefix.'unlink_title">' .
							themify_options_module($default_options, $prefix.'unlink_title') . '
						</select>
					</p>';
		/**
		 * Hide Featured Image
		 */
		$output .= '<p>
						<span class="label">' . __('Hide Featured Image', 'themify') . '</span>
						<select name="'.$prefix.'hide_image">' .
							themify_options_module($default_options, $prefix.'hide_image') . '
						</select>
					</p>';

		/**
		 * Unlink Featured Image
		 */
		$output .= '<p>
						<span class="label">' . __('Unlink Featured Image', 'themify') . '</span>
						<select name="'.$prefix.'unlink_image">' .
							themify_options_module($default_options, $prefix.'unlink_image') . '
						</select>
					</p>';

		/**
		 * Image Dimensions
		 */
		$team_image_post_width = isset( $data[$prefix.'image_post_width'] ) ? $data[$prefix.'image_post_width'] : '';
		$team_image_post_height = isset( $data[$prefix.'image_post_height'] ) ? $data[$prefix.'image_post_height'] : '';
		$output .= '
			<p class="show_if_enabled_img_php">
				<span class="label">' . __('Image Size', 'themify') . '</span>
				<input type="text" class="width2" name="'.$prefix.'image_post_width" value="'.$team_image_post_width.'" /> ' . __('width', 'themify') . ' <small>(px)</small>
				<input type="text" class="width2" name="'.$prefix.'image_post_height" value="'.$team_image_post_height.'" /> ' . __('height', 'themify') . ' <small>(px)</small>
			</p>';

		return $output;
	}
}

if ( ! function_exists( 'themify_team_slug' ) ) {
	/**
	 * Team Slug
	 * @param array $data
	 * @return string
	 */
	function themify_team_slug( $data=array() ) {
		$data = themify_get_data();
		$team_slug = isset($data['themify_team_slug'])? $data['themify_team_slug']: apply_filters('themify_team_rewrite', 'team');
		return '
			<p>
				<span class="label">' . __('Team Base Slug', 'themify') . '</span>
				<input type="text" name="themify_team_slug" value="'.$team_slug.'" class="slug-rewrite">
				<br />
				<span class="pushlabel"><small>' . __('Use only lowercase letters, numbers, underscores and dashes.', 'themify') . '</small></span>
				<br />
				<span class="pushlabel"><small>' . sprintf(__('After changing this, go to <a href="%s">permalinks</a> and click "Save changes" to refresh them.', 'themify'), admin_url('options-permalink.php')) . '</small></span><br />
			</p>';
	}
}

if ( ! function_exists( 'themify_single_team_layout_condition' ) ) {
	/**
	 * Catches condition to filter body class when it's a singular team view
	 * @param $condition
	 * @return bool
	 */
	function themify_single_team_layout_condition( $condition ) {
		return $condition || is_singular( 'team' );
	}
	add_filter('themify_default_layout_condition', 'themify_single_team_layout_condition', 13);
}
if ( ! function_exists( 'themify_single_team_default_layout' ) ) {
	/**
	 * Filters sidebar layout body class to output the correct one when it's a singular team view
	 * @param $class
	 * @return mixed|string
	 */
	function themify_single_team_default_layout( $class ) {
		if ( is_singular( 'team' ) ) {
			$layout = 'setting-default_team_single_layout';
			$class = themify_check( $layout )? themify_get( $layout ) : 'sidebar1';
		}
		return $class;
	}
	add_filter('themify_default_layout', 'themify_single_team_default_layout', 13);
}
