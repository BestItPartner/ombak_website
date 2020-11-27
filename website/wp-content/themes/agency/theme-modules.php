<?php

/*
To add custom modules to the theme, create a new 'custom-modules.php' file in the theme folder.
They will be added to the theme automatically.
*/

/* 	Custom Modules
/***************************************************************************/
		
	///////////////////////////////////////////
	// Homepage Feature Box Function
	///////////////////////////////////////////
	function themify_slider($data=array()){
		$data = themify_get_data();
		
		/** Slider values @var array */
		$slider_ops = array( __('On', 'themify') => 'on', __('Off', 'themify') => 'off' );
		/** Slider status */
		$enabled_display = '';
		if ( '' == themify_get( 'setting-slider_enabled' ) || 'on' == themify_get( 'setting-slider_enabled' ) ) {
			$enabled_checked = "selected='selected'";
		} else {
			$enabled_display = "style='display:none;'";	
		}
		
		$display_options = array(
			__('Full Content', 'themify') => 'content',
			__('Excerpt', 'themify') => 'excerpt',
			__('None', 'themify') => 'none'
		);
		$title_options = array('','yes','no');
		$auto_options = array('yes','no');
		$speed_options = array(__('Slow', 'themify') => 3000, __('Normal', 'themify') => 2000, __('Fast', 'themify') => 1000);
		$effect_options = array(__('Slide', 'themify') =>'slide', __('Fade', 'themify') =>'fade');
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

		/**
		 * HTML for settings panel
		 * @var string
		 */
		$output = '<p><span class="label">' . __('Enable Slider', 'themify') . '</span> <select name="setting-slider_enabled" class="feature_box_enabled_check">';
		/** Iterate through slider options */
		foreach ( $slider_ops as $key => $val ) {
			$output .= '<option value="'.$val.'" ' . selected( themify_get( 'setting-slider_enabled' ), $val, false ) . '>' . $key . '</option>';
		}
		$output .= '</select>' . '</p>
					<div class="feature_box_enabled_display" '.$enabled_display.'>';
		
		$output .= '<p><span class="label">' . __('Slider Category', 'themify') . '</span>';
		$output .= wp_dropdown_categories(array("taxonomy" => "slider-category", "show_option_all" => __('All Categories', 'themify'), "hide_empty" => 0, "echo" => 0, "name" => "setting-slider_posts_category", "selected" => themify_get( 'setting-slider_posts_category' )));
		$output .= '<input type="text" name="setting-slider_posts_slides" value="' . themify_get( 'setting-slider_posts_slides' ) . '" class="width2" /> ' . __('number of slides', 'themify') . ' 
					</p>';
					
		$output .= '<p class="feature_box_posts">
					<span class="label">' . __('Display', 'themify') . '</span>
					<select name="setting-slider_default_display">';
					
					foreach($display_options as $option => $value) {
						if ( isset( $data['setting-slider_default_display'] ) && ( $value == $data['setting-slider_default_display'] ) ) {
							$output .= '<option value="'.$value.'" selected="selected">'.$option.'</option>';
						} else {
							$output .= '<option value="'.$value.'">'.$option.'</option>';
						}
					}			
		$output .= '</select>
					</p>';
					
		$output .= '<p class="feature_box_posts">
					<span class="label">' . __('Hide Title', 'themify') . '</span>
					<select name="setting-slider_hide_title">
					';
					foreach($title_options as $option) {
						if ( isset( $data['setting-slider_hide_title'] ) && ( $option == $data['setting-slider_hide_title'] ) ) {
							$output .= '<option value="'.$option.'" selected="selected">'.$option.'</option>';
						} else {
							$output .= '<option value="'.$option.'">'.$option.'</option>';
						}
					}			
		$output .= '</select>
					</p>';
		
		$output .= '<hr />
					<p>
					<span class="label">' . __('Auto Play', 'themify') . '</span> 
					<select name="setting-slider_auto">';
					foreach ( $auto_options as $name => $val ) {
						$output .= '<option value="'.$val.'" ' . selected( themify_get( 'setting-slider_auto' ), isset( $data['setting-slider_auto'] ) ? $val: 4000, false ) . '>'.$name.'</option>';
					}
	$output .= '	</select>
				</p>';
		
		$output .= '<p>
					<span class="label">' . __('Effect', 'themify') . '</span> 
					<select name="setting-slider_effect">';
					foreach ( $effect_options as $name => $val ) {
						$output .= '<option value="'.$val.'" ' . selected( themify_get( 'setting-slider_effect' ), isset( $data['setting-slider_effect'] ) ? $val: 'slide', false ) . '>'.$name.'</option>';
					}
		$output .= '</select>
				</p>';
				
		$output .= '<p>
						<span class="label">' . __('Transition Speed', 'themify') . '</span> 
						<select name="setting-slider_speed">';
						foreach ( $speed_options as $name => $val ) {
							$output .= '<option value="'.$val.'" ' . selected( themify_get( 'setting-slider_speed' ), isset( $data['setting-slider_speed'] ) ? $val: 2000, false ) . '>'.$name.'</option>';
						}
		$output .= '	</select>
					</p>
					</div>';
		return $output;
	}
	///////////////////////////////////////////
	// Homepage Feature Box Function - Action
	///////////////////////////////////////////
	function themify_slider_action($data=array()){
		$data = themify_get_data();
		if ( ! themify_check( 'setting-slider_speed' ) || themify_get( 'setting-slider_speed' ) == '' ) {
			$data['setting-slider_speed'] = '2000';
		}
		if ( ! themify_check( 'setting-slider_auto' ) || themify_get( 'setting-slider_auto' ) == '' || 'yes' == themify_get( 'setting-slider_auto' ) || 'no' == themify_get( 'setting-slider_auto' ) ) {
			if ( 'yes' == themify_get( 'setting-slider_auto' ) ) {
				$data['setting-slider_auto'] = '4000';
			} elseif ( 'no' == themify_get( 'setting-slider_auto' ) ) {
				$data['setting-slider_auto'] = 'off';
			}
		}
		if ( ! themify_check( 'setting-slider_auto' ) || themify_get( 'setting-slider_auto' ) == '' ) {
			$data['setting-slider_effect'] = "slide";
		}
		?>				
		<!-- slider -->
		<script>
		/////////////////////////////////////////////
		// Slider	 							
		/////////////////////////////////////////////
		jQuery(document).ready(function($){
			$('#slider').flexslider({
				animation: "<?php echo $data['setting-slider_effect']; ?>",
				slideshow: <?php echo ( isset( $data['setting-slider_auto'] ) && 'off' != $data['setting-slider_auto'] ) ? 'true': 'false'; ?>,
				slideshowSpeed: <?php echo ( isset( $data['setting-slider_auto'] ) && 'off' != $data['setting-slider_auto'] ) ? $data['setting-slider_auto'] : '4000'; ?>,
				animationSpeed: <?php echo ( isset( $data['setting-slider_speed'] ) && 'off' != $data['setting-slider_speed'] ) ? $data['setting-slider_speed'] : '0'; ?>,
				animationLoop: true, 
				directionNav: true,
				prevText: "&laquo;",
				nextText: "&raquo;",
				pauseOnHover: true
			});
		});
		</script>
        <?php
	}
	add_action('wp_footer', 'themify_slider_action');

	///////////////////////////////////////////
	// Default Page Layout Module - Action
	///////////////////////////////////////////
	function themify_default_page_layout($data=array()){
		$data = themify_get_data();
		
		$options = array(
			array('value' => 'sidebar1', 'img' => 'images/layout-icons/sidebar1.png', 'selected' => true, 'title' => __('Sidebar Right', 'themify')),
			array('value' => 'sidebar1 sidebar-left', 'img' => 'images/layout-icons/sidebar1-left.png', 'title' => __('Sidebar Left', 'themify')),
			array('value' => 'sidebar-none', 'img' => 'images/layout-icons/sidebar-none.png', 'title' => __('No Sidebar', 'themify'))
		);
		
		$default_options = array(
			array('name'=>'','value'=>''),
			array('name'=>__('Yes', 'themify'),'value'=>'yes'),
			array('name'=>__('No', 'themify'),'value'=>'no')
		);
							 
		$val = isset( $data['setting-default_page_layout'] ) ? $data['setting-default_page_layout'] : '';
		
		$output = '<p>
						<span class="label">' . __('Page Sidebar Option', 'themify') . '</span>';
		foreach ( $options as $option ) {
			if ( ( '' == $val || ! $val || ! isset( $val ) ) && ( isset( $option['selected'] ) && $option['selected'] ) ) { 
				$val = $option['value'];
			}
			if ( $val == $option['value'] ) { 
				$class = "selected";
			} else {
				$class = "";	
			}
			$output .= '<a href="#" class="preview-icon '.$class.'" title="'.$option['title'].'"><img src="'.THEME_URI.'/'.$option['img'].'" alt="'.$option['value'].'"  /></a>';	
		}
		$output .= '<input type="hidden" name="setting-default_page_layout" class="val" value="'.$val.'" /></p>';
		$output .= '<p>
						<span class="label">' . __('Hide Title in All Pages', 'themify') . '</span>
						
						<select name="setting-hide_page_title">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-hide_page_title'] ) && ( $title_option['value'] == $data['setting-hide_page_title'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
						
						
		$output .=	'</select>
					</p>';
		/**
		 * Hide Feauted images in All Pages
		 */
		$output .= '<p>
                    <span class="label">' . __('Hide Featured Image', 'themify') . '</span>
                    <select name="setting-hide_page_image">' .
                        themify_options_module($default_options, 'setting-hide_page_image') . '
                    </select>
                </p>';
		/**
		 * Featured Image dimensions
		 */
		$output .= '<p>
					<span class="label">' . __('Image Size', 'themify') . '</span>
					<input type="text" class="width2" name="setting-page_featured_image_width" value="' . themify_get( 'setting-page_featured_image_width' ) . '" /> ' . __('width', 'themify') . ' <small>(px)</small>
					<input type="text" class="width2 show_if_enabled_img_php" name="setting-page_featured_image_height" value="' . themify_get( 'setting-page_featured_image_height' ) . '" /> <span class="show_if_enabled_img_php">' . __('height', 'themify') . ' <small>(px)</small></span>
					<br /><span class="pushlabel show_if_enabled_img_php"><small>' . __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify') . '</small></span>
				</p>';
				
		if ( isset( $data['setting-comments_pages'] ) && $data['setting-comments_pages'] ) {
			$pages_checked = "checked='checked'";	
		}
		$output .= '<p><span class="label">' . __('Page Comments', 'themify') . '</span><label for="setting-comments_pages"><input type="checkbox" id="setting-comments_pages" name="setting-comments_pages" '.checked( themify_get( 'setting-comments_pages' ), 'on', false ).' /> ' . __('Disable comments in all Pages', 'themify') . '</label></p>';	
		
		return $output;													 
	}
	
	///////////////////////////////////////////
	// Default Index Layout Module - Action
	///////////////////////////////////////////
	function themify_default_layout($data=array()){
		$data = themify_get_data();

		$prefix = 'setting-default_';
		
		if ( ! isset( $data['setting-default_more_text'] ) || '' == $data['setting-default_more_text'] ) {
			$more_text = __('More', 'themify');
		} else {
			$more_text = $data['setting-default_more_text'];
		}
		
		$default_options = array(
								array('name'=>'','value'=>''),
								array('name'=>__('Yes', 'themify'),'value'=>'yes'),
								array('name'=>__('No', 'themify'),'value'=>'no')
								);
		$default_layout_options = array(
								array('name' => __('Full Content', 'themify'),'value'=>'content'),
								array('name' => __('Excerpt', 'themify'),'value'=>'excerpt'),
								array('name' => __('None', 'themify'),'value'=>'none')
								);	
		$default_post_layout_options = array(
			array('value' => 'list-post', 'img' => 'images/layout-icons/list-post.png', 'title' => __('List Post', 'themify'), "selected" => true),
			array('value' => 'grid4', 'img' => 'images/layout-icons/grid4.png', 'title' => __('Grid 4', 'themify')),
			array('value' => 'grid3', 'img' => 'images/layout-icons/grid3.png', 'title' => __('Grid 3', 'themify')),
			array('value' => 'grid2', 'img' => 'images/layout-icons/grid2.png', 'title' => __('Grid 2', 'themify')),
			array('value' => 'list-large-image', 'img' => 'images/layout-icons/list-large-image.png', 'title' => __('List Large Image', 'themify')),
			array('value' => 'list-thumb-image', 'img' => 'images/layout-icons/list-thumb-image.png', 'title' => __('List Thumb Image', 'themify')),
			array('value' => 'grid2-thumb', 'img' => 'images/layout-icons/grid2-thumb.png', 'title' => __('Grid 2 Thumb', 'themify')),
		);
															 	 
	$options = array(
		array('value' => 'sidebar1', 'img' => 'images/layout-icons/sidebar1.png', 'selected' => true, 'title' => __('Sidebar Right', 'themify')),
		array('value' => 'sidebar1 sidebar-left', 'img' => 'images/layout-icons/sidebar1-left.png', 'title' => __('Sidebar Left', 'themify')),
		array('value' => 'sidebar-none', 'img' => 'images/layout-icons/sidebar-none.png', 'title' => __('No Sidebar', 'themify'))
	);
						 
		$val = isset( $data['setting-default_layout'] ) ? $data['setting-default_layout'] : '';
		
		/**
		 * HTML for settings panel
		 * @var string
		 */
		$output = '<div class="themify-info-link">' . __( 'Here you can set the <a href="https://themify.me/docs/default-layouts">Default Layouts</a> for WordPress archive post layout (category, search, archive, tag pages, etc.), single post layout (single post page), and the static Page layout. The default single post and page layout can be override individually on the post/page > edit > Themify Custom Panel.', 'themify' ) . '</div>';

		$output .= '<p>
						<span class="label">' . __('Archive Sidebar Option', 'themify') . '</span>';
		foreach ( $options as $option ) {
			if ( ( '' == $val || ! $val || ! isset( $val ) ) && ( isset( $option['selected'] ) && $option['selected'] ) ) { 
				$val = $option['value'];
			}
			if ( $val == $option['value'] ) { 
				$class = "selected";
			} else {
				$class = "";	
			}
			$output .= '<a href="#" class="preview-icon '.$class.'" title="'.$option['title'].'"><img src="'.THEME_URI.'/'.$option['img'].'" alt="'.$option['value'].'"  /></a>';	
		}
		
		$output .= '<input type="hidden" name="setting-default_layout" class="val" value="'.$val.'" />';
		$output .= '</p>';
		$output .= '<p>
						<span class="label">' . __('Post Layout', 'themify') . '</span>';
						
		$val = isset( $data['setting-default_post_layout'] ) ? $data['setting-default_post_layout'] : '';
		
		foreach ( $default_post_layout_options as $option ) {
			if ( ( '' == $val || ! $val || ! isset( $val ) ) && ( isset( $option['selected'] ) && $option['selected'] ) ) { 
				$val = $option['value'];
			}
			if ( $val == $option['value'] ) { 
				$class = "selected";
			} else {
				$class = "";	
			}
			$output .= '<a href="#" class="preview-icon '.$class.'" title="'.$option['title'].'"><img src="'.THEME_URI.'/'.$option['img'].'" alt="'.$option['value'].'"  /></a>';	
		}
		
		$output .= '<input type="hidden" name="setting-default_post_layout" class="val" value="'.$val.'" />
					</p>
					<p>
						<span class="label">' . __('Display Content', 'themify') . '</span> 
						<select name="setting-default_layout_display">';
						foreach ( $default_layout_options as $layout_option ) {
							if ( isset( $data['setting-default_layout_display'] ) && ( $layout_option['value'] == $data['setting-default_layout_display'] ) ) {
								$output .= '<option selected="selected" value="'.$layout_option['value'].'">'.$layout_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$layout_option['value'].'">'.$layout_option['name'].'</option>';
							}
						}
		$output .=	'	</select>
					</p>';

		/**
		 * Excerpt length
		 */
		$output .= '<p style="display:none">
						<span class="pushlabel vertical-grouped">
							<label>
								<input class="width2" type="text" value="' . ( isset( $data[ $prefix . 'excerpt_length' ] ) ? esc_attr( $data[ $prefix . 'excerpt_length' ] ) : '' ) . '" name="' . esc_attr( $prefix ) . 'excerpt_length"> '
								. __( 'Excerpt length (enter number of words)', 'themify' ) . '
							</label>
						</span>
					</p>';
		
		/**
		 * More Text
		 */
		$output .=	'<p>
						<span class="label">' . __('More Text', 'themify') . '</span>
						<input type="text" name="setting-default_more_text" value="'.$more_text.'">

						<span class="pushlabel vertical-grouped"><label for="setting-excerpt_more"><input type="checkbox" value="1" id="setting-excerpt_more" name="setting-excerpt_more" '.checked( themify_get( 'setting-excerpt_more' ), 1, false ).'/> ' . __('Display more link button in excerpt mode as well.', 'themify') . '</label></span>
					</p>';
					
		/**
		 * Order & OrderBy Options
		 */
		if( function_exists( 'themify_post_sorting_options' ) ) 
			$output .= themify_post_sorting_options('setting-index_order', $data);
		
		/**
		 * Hide Post Title
		 */
		$output .=	'<p>
						<span class="label">' . __('Hide Post Title', 'themify') . '</span>
						
						<select name="setting-default_post_title">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_post_title'] ) && ( $title_option['value'] == $data['setting-default_post_title'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>
					<p>
						<span class="label">' . __('Unlink Post Title', 'themify') . '</span>
						
						<select name="setting-default_unlink_post_title">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_unlink_post_title'] ) && ( $title_option['value'] == $data['setting-default_unlink_post_title'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>
					<p>
						<span class="label">' . __('Hide Post Meta', 'themify') . '</span>
						
						<select name="setting-default_post_meta">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_post_meta'] ) && ( $title_option['value'] == $data['setting-default_post_meta'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>
					<p>
						<span class="label">' . __('Hide Post Date', 'themify') . '</span>
						
						<select name="setting-default_post_date">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_post_date'] ) && ( $title_option['value'] == $data['setting-default_post_date'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>
					<p>
						<span class="label">' . __('Auto Featured Image', 'themify') . '</span>

						<label for="setting-auto_featured_image"><input type="checkbox" value="1" id="setting-auto_featured_image" name="setting-auto_featured_image" '.checked( themify_get( 'setting-auto_featured_image' ), 1, false ).'/> ' . __('If no featured image is specified, display first image in content.', 'themify') . '</label>
					</p>

					<p>
						<span class="label">' . __('Hide Featured Image', 'themify') . '</span>

						<select name="setting-default_post_image">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_post_image'] ) && ( $title_option['value'] == $data['setting-default_post_image'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>
					<p>
						<span class="label">' . __('Unlink Featured Image', 'themify') . '</span>
						
						<select name="setting-default_unlink_post_image">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_unlink_post_image'] ) && ( $title_option['value'] == $data['setting-default_unlink_post_image'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>';
		
		$output .= themify_feature_image_sizes_select('image_post_feature_size');
		$data = themify_get_data();
		$options = array( 'left', 'right' );

		$output .= '<p class="show_if_enabled_img_php">
						<span class="label">' . __('Image Size', 'themify') . '</span>  
						<input type="text" class="width2" name="setting-image_post_width" value="' . themify_get( 'setting-image_post_width' ) . '" /> ' . __('width', 'themify') . ' <small>(px)</small>  
						<input type="text" class="width2" name="setting-image_post_height" value="' . themify_get( 'setting-image_post_height' ) . '" /> <span>' . __('height', 'themify') . ' <small>(px)</small></span>
						<br /><span class="pushlabel"><small>' . __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify') . '</small></span>
					</p>';
		return $output;
	}
	
	///////////////////////////////////////////
	// Default Single Post Layout
	///////////////////////////////////////////
	function themify_default_post_layout($data=array()){
		
		$data = themify_get_data();
		
		$default_options = array(
								array('name'=>'','value'=>''),
								array('name'=>__('Yes', 'themify'),'value'=>'yes'),
								array('name'=>__('No', 'themify'),'value'=>'no')
								);
		
		$val = isset( $data['setting-default_page_post_layout'] ) ? $data['setting-default_page_post_layout'] : '';

		$output = '<p>
						<span class="label">' . __('Post Sidebar Option', 'themify') . '</span>';
						
		$options = array(
			array('value' => 'sidebar1', 'img' => 'images/layout-icons/sidebar1.png', 'title' => __('Sidebar Right', 'themify'), "selected" => true),
			array('value' => 'sidebar1 sidebar-left', 'img' => 'images/layout-icons/sidebar1-left.png', 'title' => __('Sidebar Left', 'themify')),
			array('value' => 'sidebar-none', 'img' => 'images/layout-icons/sidebar-none.png', 'title' => __('No Sidebar', 'themify'))
		);
										
		foreach ( $options as $option ) {
			if ( ( '' == $val || ! $val || ! isset( $val ) ) && ( isset( $option['selected'] ) && $option['selected'] ) ) { 
				$val = $option['value'];
			}
			if ( $val == $option['value'] ) { 
				$class = "selected";
			} else {
				$class = "";	
			}
			$output .= '<a href="#" class="preview-icon '.$class.'" title="'.$option['title'].'"><img src="'.THEME_URI.'/'.$option['img'].'" alt="'.$option['value'].'"  /></a>';	
		}
		
		$output .= '<input type="hidden" name="setting-default_page_post_layout" class="val" value="'.$val.'" />';
		$output .= '</p>
					<p>
						<span class="label">' . __('Hide Post Title', 'themify') . '</span>
						
						<select name="setting-default_page_post_title">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_page_post_title'] ) && ( $title_option['value'] == $data['setting-default_page_post_title'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>
					<p>
						<span class="label">' . __('Unlink Post Title', 'themify') . '</span>
						
						<select name="setting-default_page_unlink_post_title">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_page_unlink_post_title'] ) && ( $title_option['value'] == $data['setting-default_page_unlink_post_title'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>
					<p>
						<span class="label">' . __('Hide Post Meta', 'themify') . '</span>
						
						<select name="setting-default_page_post_meta">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_page_post_meta'] ) && ( $title_option['value'] == $data['setting-default_page_post_meta'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>
					<p>
						<span class="label">' . __('Hide Post Date', 'themify') . '</span>
						
						<select name="setting-default_page_post_date">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_page_post_date'] ) && ( $title_option['value'] == $data['setting-default_page_post_date'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p>
					<p>
						<span class="label">' . __('Hide Featured Image', 'themify') . '</span>
						
						<select name="setting-default_page_post_image">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_page_post_image'] ) && ( $title_option['value'] == $data['setting-default_page_post_image'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .=	'</select>
					</p><p>
						<span class="label">' . __('Unlink Featured Image', 'themify') . '</span>
						
						<select name="setting-default_page_unlink_post_image">';
						foreach ( $default_options as $title_option ) {
							if ( isset( $data['setting-default_page_unlink_post_image'] ) && ( $title_option['value'] == $data['setting-default_page_unlink_post_image'] ) ) {
								$output .= '<option selected="selected" value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							} else {
								$output .= '<option value="'.$title_option['value'].'">'.$title_option['name'].'</option>';
							}
						}
		$output .= '</select></p>';
		$output .= themify_feature_image_sizes_select('image_post_single_feature_size');
		$output .= '<p class="show_if_enabled_img_php">
				<span class="label">' . __('Image Size', 'themify') . '</span>
						<input type="text" class="width2" name="setting-image_post_single_width" value="' . themify_get( 'setting-image_post_single_width' ) . '" /> ' . __('width', 'themify') . ' <small>(px)</small>  
						<input type="text" class="width2" name="setting-image_post_single_height" value="' . themify_get( 'setting-image_post_single_height' ) . '" /> <span class="show_if_enabled_img_php">' . __('height', 'themify') . ' <small>(px)</small></span>
						<br /><span class="pushlabel"><small>' . __('Enter height = 0 to disable vertical cropping with img.php enabled', 'themify') . '</small></span>
					</p>';
		if ( themify_check( 'setting-comments_posts' ) ) {
			$comments_posts_checked = "checked='checked'";	
		}
		$output .= '<p><span class="label">' . __('Post Comments', 'themify') . '</span><label for="setting-comments_posts"><input type="checkbox" id="setting-comments_posts" name="setting-comments_posts" '.checked( themify_get( 'setting-comments_posts' ), 'on', false ).' /> ' . __('Disable comments in all Posts', 'themify') . '</label></p>';	
		
		if ( themify_check( 'setting-post_author_box' ) ) {
			$author_box_checked = "checked='checked'";	
		}
		$output .= '<p><span class="label">' . __('Show Author Box', 'themify') . '</span><label for="setting-post_author_box"><input type="checkbox" id="setting-post_author_box" name="setting-post_author_box" '.checked( themify_get( 'setting-post_author_box' ), 'on', false ).' /> ' . __('Show author box in all Posts', 'themify') . '</label></p>';

		// Post Navigation
		$pre = 'setting-post_nav_';
		$output .= '
		<p>
			<span class="label">' . __('Post Navigation', 'themify') . '</span>
			<label for="'.$pre.'disable">
				<input type="checkbox" id="'.$pre.'disable" name="'.$pre.'disable" '. checked( themify_get( $pre.'disable' ), 'on', false ) .'/> ' . __('Remove Post Navigation', 'themify') . '
				</label>
		<span class="pushlabel vertical-grouped">
				<label for="'.$pre.'same_cat">
					<input type="checkbox" id="'.$pre.'same_cat" name="'.$pre.'same_cat" '. checked( themify_get( $pre.'same_cat' ), 'on', false ) .'/> ' . __('Show only posts in the same category', 'themify') . '
				</label>
			</span>
		</p>';	
			
		return $output;
	}

if ( ! function_exists( 'themify_setting_fixed_header' ) ) {
	/**
	 * Fixed Header Setting
	 * @return string
	 */
	function themify_setting_fixed_header() {
		$pre = 'setting-fixed_header_disabled';

		$output = '<p><label for="'.$pre.'"><input type="checkbox" id="'.$pre.'" name="'.$pre.'" '.checked( themify_get( $pre ), 'on', false ).'/> ' . __('Check here to disable Fixed Header', 'themify') . '</label></p>';

		return $output;
	}
}

?>