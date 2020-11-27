<?php
if(!class_exists('Themify_Portfolio')){
	/**
	 * Class to create portfolios
	 */
	class Themify_Portfolio extends Themify_Shortcode {
		function shortcode($atts = array(), $post_type){
			extract($atts);
			// Pagination
			global $paged;
			$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
			// Parameters to get posts
			$args = array(
				'post_type' => $post_type,
				'posts_per_page' => $limit,
				'order' => $order,
				'orderby' => $orderby,
				'suppress_filters' => false,
				'paged' => $paged
			);
			// Category parameters
			$args['tax_query'] = $this->parse_category_args($category, $post_type);

			// Single post type
			if( '' != $id ){
				$args['p'] = intval($id);
			}

			// Get posts according to parameters
			$portfolio_query = new WP_Query();
			$posts = $portfolio_query->query(apply_filters('themify_'.$post_type.'_shortcode_args', $args));

			// Grid Style
			if( '' == $style ) {
				$style = themify_check('setting-default_portfolio_index_post_layout')?
							 themify_get('setting-default_portfolio_index_post_layout'):
							 'grid4';
			}

			if( is_singular('portfolio') ) {
				if( '' == $image_w ) {
					$image_w = themify_check('setting-default_portfolio_single_image_post_width')?
							themify_get('setting-default_portfolio_single_image_post_width'):
							'222';
				}
				if( '' == $image_h ) {
					$image_h = themify_check('setting-default_portfolio_single_image_post_height')?
							themify_get('setting-default_portfolio_single_image_post_height'):
							'175';
				}
				if( '' == $post_date ) {
					$post_date = themify_check('setting-default_portfolio_single_post_date')?
							themify_get('setting-default_portfolio_index_post_date'):
							'yes';
				}
				if( '' == $title ) {
					$title = themify_check('setting-default_portfolio_single_title')?
							themify_get('setting-default_portfolio_single_title'):
							'yes';
				}
				if( '' == $unlink_title ) {
					$unlink_title = themify_check('setting-default_portfolio_single_unlink_post_title')?
							themify_get('setting-default_portfolio_single_unlink_post_title'):
							'no';
				}
				if( '' == $post_meta ) {
					$post_meta = themify_check('setting-default_portfolio_single_meta')?
							themify_get('setting-default_portfolio_single_meta'):
							'yes';
				}
			} else {
				if( '' == $image_w ) {
					$image_w = themify_check('setting-default_portfolio_index_image_post_width')?
							themify_get('setting-default_portfolio_index_image_post_width'):
							'222';
				}
				if( '' == $image_h ) {
					$image_h = themify_check('setting-default_portfolio_index_image_post_height')?
							themify_get('setting-default_portfolio_index_image_post_height'):
							'175';
				}
				if( '' == $title ) {
					$title = themify_check('setting-default_portfolio_index_title')?
							themify_get('setting-default_portfolio_index_title'):
							'yes';
				}
				if( '' == $unlink_title ) {
					$unlink_title = themify_check('setting-default_portfolio_index_unlink_post_title')?
							themify_get('setting-default_portfolio_index_unlink_post_title'):
							'no';
				}
				// Reverse logic
				if( '' == $post_date ) {
					$post_date = themify_check('setting-default_portfolio_index_post_date')?
							'no' == themify_get('setting-default_portfolio_index_post_date')?
								'yes' : 'no':
							'no';
				}
				if( '' == $post_meta ) {
					$post_meta = themify_check('setting-default_portfolio_index_post_meta')?
							'no' == themify_get('setting-default_portfolio_index_post_meta')? 'yes' : 'no' :
							'no';
				}
			}

			/** Collect markup generated to be returned later
			 *  @var string */
			$out = '';
			if( $posts ) {
				global $post;

				$out .= '<div class="loops-wrapper shortcode ' . $post_type  . ' ' . $style . '">';
				foreach ($posts as $post) {
					setup_postdata($post);

					$categories = wp_get_object_terms($post->ID, $post_type . '-category');
					$class = '';
					foreach($categories as $cat) {
						$class .= ' cat-'.$cat->term_id;
					}

					$out .= '<article id="portfolio-'.get_the_ID().'"  class="post clearfix ' . implode(' ', get_post_class($class)) . '">';

						// Create Images Slider
						if ( themify_check( '_gallery_shortcode' ) && 'gallery' == $feature ) {

							// Parse gallery shortcode
							$gallery_images = $this->get_images_from_gallery_shortcode();

							if( '' == $autoplay ) {
								$autoplay = themify_check('setting-portfolio_slider_autoplay')?
										themify_get('setting-portfolio_slider_autoplay'):
										'4000';
							}
							if( '' == $effect ) {
								$effect = themify_check('setting-portfolio_slider_effect')?
										themify_get('setting-portfolio_slider_effect'):
										'scroll';
							}
							if( '' == $speed ) {
								$speed = themify_check('setting-portfolio_slider_transition_speed')?
										themify_get('setting-portfolio_slider_transition_speed'):
										'500';
							}
							$out .= '<div id="portfolio-slider-'.$post->ID.'-'.$this->instance.'" class="post-image slideshow-wrap">
									<ul class="slideshow" data-id="portfolio-slider-'.$post->ID.'-'.$this->instance.'" data-autoplay="'.$autoplay.'" data-effect="'.$effect.'" data-speed="'.$speed.'">';
							foreach ( $gallery_images as $gallery_image ) {
								$out .= '<li>';
									$out .= '<a href="'.$this->get_entry_link(false, $gallery_image->ID).'">';
									global $themify;
									$out .= $themify->portfolio_image($gallery_image->ID, $image_w, $image_h);
									$out .= '</a>';
								$out .= '</li>';
							}
							$out .= '</ul></div>';
						} else {
							// Single Image
							$out .= $this->post_image($image, $image_w, $image_h);
						}
						$images = null;
						$out .= '<div class="post-content">';
							// Post Meta
							if ('yes' == $post_meta) {
                                                            $terms = get_the_term_list($post->ID, $post_type.'-category', '<span class="post-category">', ', ', ' <span class="separator">/</span></span>');
                                                            if(!is_wp_error($terms)){
                                                                $out .= '<p class="post-meta">'.$terms. '</p>';
                                                            }
							}

							// Post Title
							$out .= $this->post_title($title, 'h1', 'yes'!=$unlink_title);

							// Post Date
							if ('yes' == $post_date) {
								$out .= '<p class="post-date">' . get_the_date() . '</p>';
							}

							// Post Content or Excerpt
							$out .= $this->display($display);
							$out .= $this->edit_link($post->ID);
						$out .= '</div>';
						$out .= '<!-- /.post-content -->';

					$out .= '</article>';
					$out .= '<!-- / .post -->';

				} wp_reset_postdata();

				// Pagination
				if('yes' == $page_nav){
					$out .= themify_get_pagenav('', '', $portfolio_query);
				}

				$out .= $this->section_link($more_link, $more_text, $post_type);
				$out .= '</div>';
			}

			return $out;
		}
	}
}

/***************************************************
 * Themify Theme Settings Module
 ***************************************************/

if ( ! function_exists( 'themify_default_portfolio_single_layout' ) ) {
	/**
	 * Default Single Portfolio Layout
	 * @param array $data
	 * @return string
	 */
	function themify_default_portfolio_single_layout($data=array()){
		/**
		 * Associative array containing theme settings
		 * @var array
		 */
		$data = themify_get_data();
		/**
		 * Variable prefix key
		 * @var string
		 */
		$prefix = 'setting-default_portfolio_single_';
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
		 * Sidebar Layout
		 * @var string
		 */
		$layout = isset( $data[$prefix.'layout'] ) ? $data[$prefix.'layout'] : 'sidebar-none';
		/**
		 * Sidebar Layout Options
		 * @var array
		 */
		$sidebar_options = array(
			array('value' => 'sidebar1', 'img' => 'images/layout-icons/sidebar1.png', 'title' => __('Sidebar Right', 'themify')),
			array('value' => 'sidebar1 sidebar-left', 'img' => 'images/layout-icons/sidebar1-left.png', 'title' => __('Sidebar Left', 'themify')),
			array('value' => 'sidebar-none', 'img' => 'images/layout-icons/sidebar-none.png', 'selected' => true, 'title' => __('No Sidebar', 'themify'))
		);
		/**
		 * HTML for settings panel
		 * @var string
		 */
		$output = '<p>
						<span class="label">' . __('Portfolio Sidebar Option', 'themify') . '</span>';
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
		$output .= '<p>
						<span class="label">' . __('Hide Portfolio Title', 'themify') . '</span>
						<select name="'.$prefix.'title">' .
							themify_options_module($default_options, $prefix.'title') . '
						</select>
					</p>';

		$output .=	'<p>
						<span class="label">' . __('Unlink Portfolio Title', 'themify') . '</span>
						<select name="'.$prefix.'unlink_post_title">' .
							themify_options_module($default_options, $prefix.'unlink_post_title') . '
						</select>
					</p>';

		$output .=	'<p>
						<span class="label">' . __('Hide Portfolio Meta', 'themify') . '</span>
						<select name="'.$prefix.'post_meta">' .
							themify_options_module($default_options, $prefix.'post_meta') . '
						</select>
					</p>';

		$output .=	'<p>
						<span class="label">' . __('Hide Portfolio Date', 'themify') . '</span>
						<select name="'.$prefix.'post_date">' .
							themify_options_module($default_options, $prefix.'post_date') . '
						</select>
					</p>';
		/**
		 * Image Dimensions
		 */
		$portfolio_image_post_width = isset( $data[$prefix.'image_post_width'] ) ? $data[$prefix.'image_post_width'] : '';
		$portfolio_image_post_height = isset( $data[$prefix.'image_post_height'] ) ? $data[$prefix.'image_post_height'] : '';
		$output .= '<p class="show_if_enabled_img_php">
						<span class="label">' . __('Image Size', 'themify') . '</span>
						<input type="text" class="width2" name="'.$prefix.'image_post_width" value="'.$portfolio_image_post_width.'" /> ' . __('width', 'themify') . ' <small>(px)</small>
						<input type="text" class="width2" name="'.$prefix.'image_post_height" value="'.$portfolio_image_post_height.'" /> ' . __('height', 'themify') . ' <small>(px)</small>
					</p>';

		// Portfolio Navigation
		$prefix = 'setting-portfolio_nav_';
		$output .= '
			<p>
				<span class="label">' . __('Portfolio Navigation', 'themify') . '</span>
				<label for="'. $prefix .'disable">
					<input type="checkbox" id="'. $prefix .'disable" name="'. $prefix .'disable" '. checked( themify_get( $prefix.'disable' ), 'on', false ) .'/> ' . __('Remove Portfolio Navigation', 'themify') . '
				</label>
				<span class="pushlabel vertical-grouped">
				<label for="'. $prefix .'same_cat">
					<input type="checkbox" id="'. $prefix .'same_cat" name="'. $prefix .'same_cat" '. checked( themify_get( $prefix. 'same_cat' ), 'on', false ) .'/> ' . __('Show only portfolios in the same category', 'themify') . '
				</label>
				</span>
			</p>';

		$output .= '
			<p>
				<span class="label">' . __('Portfolio Comments', 'themify') . '</span>
				<label for="setting-portfolio_comments">
					<input type="checkbox" id="setting-portfolio_comments" name="setting-portfolio_comments" '. checked( themify_get( 'setting-portfolio_comments' ), 'on', false ) .'/> ' . __('Enable portfolio comments', 'themify') . '
				</label>
			</p>';

		return $output;
	}
}

if ( ! function_exists( 'themify_default_portfolio_index_layout' ) ) {
	/**
	 * Default Archive Portfolio Layout
	 * @param array $data
	 * @return string
	 */
	function themify_default_portfolio_index_layout($data=array()){
		/**
		 * Associative array containing theme settings
		 * @var array
		 */
		$data = themify_get_data();
		/**
		 * Variable prefix key
		 * @var string
		 */
		$prefix = 'setting-default_portfolio_index_';
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
		 * Sidebar Layout
		 * @var string
		 */
		$layout = isset( $data[$prefix.'layout'] ) ? $data[$prefix.'layout'] : 'sidebar-none';
		/**
		 * Sidebar Layout Options
		 * @var array
		 */
		$sidebar_options = array(
			array('value' => 'sidebar1', 'img' => 'images/layout-icons/sidebar1.png', 'title' => __('Sidebar Right', 'themify')),
			array('value' => 'sidebar1 sidebar-left', 'img' => 'images/layout-icons/sidebar1-left.png', 'title' => __('Sidebar Left', 'themify')),
			array('value' => 'sidebar-none', 'img' => 'images/layout-icons/sidebar-none.png', 'selected' => true, 'title' => __('No Sidebar', 'themify'))
		);
		/**
		 * Post Layout Options
		 * @var array
		 */
		$post_layout_options = array(
			array('value' => 'grid4', 'img' => 'images/layout-icons/grid4.png', 'selected' => true, 'title' => __('Grid 4', 'themify')),
			array('value' => 'grid3', 'img' => 'images/layout-icons/grid3.png', 'title' => __('Grid 3', 'themify')),
			array('value' => 'grid2', 'img' => 'images/layout-icons/grid2.png', 'title' => __('Grid 2', 'themify'))
		);
		/**
		 * HTML for settings panel
		 * @var string
		 */
		$output = '<p>
						<span class="label">' . __('Portfolio Sidebar Option', 'themify') . '</span>';
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
		 * Post Layout
		 */
		$output .= '<p>
						<span class="label">' . __('Post Layout', 'themify') . '</span>';

						$val = isset( $data[$prefix.'post_layout'] ) ? $data[$prefix.'post_layout'] : 'grid4';

						foreach($post_layout_options as $option){
							if(($val == "" || !$val || !isset($val)) && $option['selected']){
								$val = $option['value'];
							}
							if($val == $option['value']){
								$class = "selected";
							} else {
								$class = "";
							}
							$output .= '<a href="#" class="preview-icon '.$class.'" title="'.$option['title'].'"><img src="'.THEME_URI.'/'.$option['img'].'" alt="'.$option['value'].'"  /></a>';
						}

		$output .= '	<input type="hidden" name="'.$prefix.'post_layout" class="val" value="'.$val.'" />
					</p>';
		/**
		 * Display Content
		 */
		$output .= '<p>
						<span class="label">' . __('Display Content', 'themify') . '</span>
						<select name="'.$prefix.'display">'.
							themify_options_module(array(
								array('name' => __('None', 'themify'),'value'=>'none'),
								array('name' => __('Full Content', 'themify'),'value'=>'content'),
								array('name' => __('Excerpt', 'themify'),'value'=>'excerpt')
							), $prefix.'display').'
						</select>
					</p>';

		$output .= '<p>
						<span class="label">' . __('Hide Portfolio Title', 'themify') . '</span>
						<select name="'.$prefix.'title">' .
							themify_options_module($default_options, $prefix.'title') . '
						</select>
					</p>';

		$output .=	'<p>
						<span class="label">' . __('Unlink Portfolio Title', 'themify') . '</span>
						<select name="'.$prefix.'unlink_post_title">' .
							themify_options_module($default_options, $prefix.'unlink_post_title') . '
						</select>
					</p>';

		$output .=	'<p>
						<span class="label">' . __('Hide Portfolio Meta', 'themify') . '</span>
						<select name="'.$prefix.'post_meta">' .
							themify_options_module($default_options, $prefix.'post_meta', true, 'yes') . '
						</select>
					</p>';

		$output .=	'<p>
						<span class="label">' . __('Hide Portfolio Date', 'themify') . '</span>
						<select name="'.$prefix.'post_date">' .
							themify_options_module($default_options, $prefix.'post_date', true, 'yes') . '
						</select>
					</p>';
		/**
		 * Image Dimensions
		 */
		$portfolio_image_post_width = isset( $data[$prefix.'image_post_width'] ) ? $data[$prefix.'image_post_width'] : '';
		$portfolio_image_post_height = isset( $data[$prefix.'image_post_height'] ) ? $data[$prefix.'image_post_height'] : '';
		$output .= '<p class="show_if_enabled_img_php">
						<span class="label">' . __('Image Size', 'themify') . '</span>
						<input type="text" class="width2" name="'.$prefix.'image_post_width" value="'.$portfolio_image_post_width.'" /> ' . __('width', 'themify') . ' <small>(px)</small>
						<input type="text" class="width2" name="'.$prefix.'image_post_height" value="'.$portfolio_image_post_height.'" /> ' . __('height', 'themify') . ' <small>(px)</small>
					</p>';
		return $output;
	}
}

if ( ! function_exists( 'themify_portfolio_slug' ) ) {
	/**
	 * Portfolio Slug
	 * @param array $data
	 * @return string
	 */
	function themify_portfolio_slug($data=array()){
		$data = themify_get_data();
		$portfolio_slug = isset($data['themify_portfolio_slug'])? $data['themify_portfolio_slug']: apply_filters('themify_portfolio_rewrite', 'project');
		return '
			<p>
				<span class="label">' . __('Portfolio Base Slug', 'themify') . '</span>
				<input type="text" name="themify_portfolio_slug" value="'.$portfolio_slug.'" class="slug-rewrite">
				<br />
				<span class="pushlabel"><small>' . __('Use only lowercase letters, numbers, underscores and dashes.', 'themify') . '</small></span>
				<br />
				<span class="pushlabel"><small>' . sprintf(__('After changing this, go to <a href="%s">permalinks</a> and click "Save changes" to refresh them.', 'themify'), admin_url('options-permalink.php')) . '</small></span><br />
			</p>';
	}
}

if ( ! function_exists( 'themify_portfolio_slider' ) ) {
	function themify_portfolio_slider(){
		/**
		 * Associative array containing theme settings
		 * @var array
		 */
		$data = themify_get_data();
		/**
		 * Variable prefix key
		 * @var string
		 */
		$prefix = 'setting-portfolio_slider_';
		/**
		 * Basic default options '', 'yes', 'no'
		 * @var array
		 */
		$default_options = array(
			array('name'=>__('Yes', 'themify'), 'value'=>'yes'),
			array('name'=>__('No', 'themify'), 'value'=>'no')
		);
		$auto_options = array(
			__('4 Secs (default)', 'themify') => 4000,
			__('Off', 'themify') => 'off',
			__('1 Sec', 'themify') => 1000,
			__('2 Secs', 'themify') => 2000,
			__('3 Secs', 'themify') => 3000,
			__('4 Secs', 'themify') => 4000,
			__('5 Secs', 'themify') => 5000,
			__('6 Secs', 'themify') => 6000,
			__('7 Secs', 'themify') => 7000,
			__('8 Secs', 'themify') => 8000,
			__('9 Secs', 'themify') => 9000,
			__('10 Secs', 'themify')=> 10000
		);
		$speed_options = array(
			__('Fast', 'themify') => 500,
			__('Normal', 'themify') => 1000,
			__('Slow', 'themify') => 1500
		);
		$effect_options = array(
			array('name' => __('Slide', 'themify'), 'value' => 'slide'),
			array('name' => __('Fade', 'themify'), 'value' =>'fade')
		);

		$output = '<p>
						<span class="label">' . __('Auto Play', 'themify') . '</span>
						<select name="'.$prefix.'autoplay">';
						foreach($auto_options as $name => $val){
							$output .= '<option value="'.$val.'" ' . selected( themify_get( $prefix.'autoplay' ), isset( $data[$prefix.'autoplay'] ) ? $val : 4000, false ) . '>'.$name.'</option>';
						}
		$output .= '	</select>
					</p>';
		$output .= '<p>
						<span class="label">' . __('Effect', 'themify') . '</span>
						<select name="'.$prefix.'effect">'.
						themify_options_module($effect_options, $prefix.'effect') . '
						</select>
					</p>';
		$output .= '<p>
						<span class="label">' . __('Transition Speed', 'themify') . '</span>
						<select name="'.$prefix.'transition_speed">';
						foreach($speed_options as $name => $val){
							$output .= '<option value="'.$val.'" ' . selected( themify_get( $prefix.'transition_speed' ), isset( $data[$prefix.'transition_speed'] ) ? $val : 500, false ) . '>'.$name.'</option>';
						}
		$output .= '	</select>
					</p>';

		return $output;
	}
}

function themify_theme_default_layout_condition($condition) {
	return $condition || is_singular('portfolio') || is_tax('portfolio-category');
}
function themify_theme_default_layout($class) {
	if( is_singular('post') ){
		$class = (themify_get('layout') != 'default' && themify_check('layout')) ? themify_get('layout') : themify_get('setting-default_page_post_layout');
	}
	if( is_singular('portfolio') ){
		$class = themify_check('setting-default_portfolio_single_layout') ?
					themify_get('setting-default_portfolio_single_layout'):
					'sidebar-none';
	}
	if( is_tax('portfolio-category') ){
		$class = themify_check('setting-default_portfolio_index_layout')?
					themify_get('setting-default_portfolio_index_layout'):
					'sidebar-none';
	}
	return $class;
}

function themify_theme_default_post_layout_condition($condition) {
	return $condition || is_tax('portfolio-category');
};
function themify_theme_default_post_layout($class) {
	if( is_tax('portfolio-category') ){
		$class = themify_check('setting-default_portfolio_index_post_layout')?
					themify_get('setting-default_portfolio_index_post_layout'):
					'grid4';
	}
	return $class;
};

add_filter('themify_default_layout_condition', 'themify_theme_default_layout_condition');
add_filter('themify_default_layout', 'themify_theme_default_layout');
add_filter('themify_default_post_layout_condition', 'themify_theme_default_post_layout_condition');
add_filter('themify_default_post_layout', 'themify_theme_default_post_layout');
