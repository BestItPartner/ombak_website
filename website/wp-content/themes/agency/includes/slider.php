<?php global $more; $more = 0; //enable more link ?>
<?php
/** Check if slider is enabled */
if('' == themify_get('setting-slider_enabled') || 'on' == themify_get('setting-slider_enabled')) { ?>
<?php
$args = array(
	'post_type' => 'slider',
	'posts_per_page' => '-1'
);
if(themify_get('setting-slider_posts_category') != 0) {
	$args['tax_query'] = array(
		array(
			'taxonomy' => 'slider-category',
			'field' => 'id',
			'terms' => themify_get('setting-slider_posts_category')
		)
	);
}
if(themify_check('setting-slider_posts_slides')) {
	$args['posts_per_page'] = themify_get('setting-slider_posts_slides');
}
$slides = new WP_Query( apply_filters( 'themify_slider_query_vars', $args ) );
?>
<?php if ( $slides->have_posts() ) : ?>
 
<div id="sliderwrap">

	<div id="slider-inner">

		<?php themify_slider_before(); //hook ?>
		<div id="slider" class="pagewidth">
        	<?php themify_slider_start(); //hook ?>

			<ul class="slides">

				<?php while ( $slides->have_posts() ) : $slides->the_post(); ?>

					<li id="slider-<?php echo esc_attr( $slides->post->ID ); ?>" <?php post_class(themify_get('layout')); ?>>

					<?php
					$before = '<div class="slide-image">';
					$after = '</div>';
					$link = themify_get_featured_image_link( 'no_permalink=true' );
					if( $link != ''){
						$before .= '<a href="'. $link .'" title="'. get_the_title() .'">';
						$zoom_icon = themify_check('lightbox_icon')? '<span class="zoom"></span>': '';
						$after = $zoom_icon . '</a>' . $after;
					}
									  
					if(themify_get('layout') == 'slider-image-only'){
					
						if(themify_check('video_url')){
							global $wp_embed;
							echo '<div class="slide-image">'.$wp_embed->run_shortcode('[embed]' . themify_get('video_url') . '[/embed]').'</div>';
						} else {
							echo $before;
							$w = themify_check( 'image_width' ) ? themify_get( 'image_width' ) : '978';
							$h = themify_check( 'image_height' ) ? themify_get( 'image_height' ) : '400';
							themify_image( "ignore=true&w=$w&h=$h" );
							echo $after;
						}
					
					} else if(themify_get('layout') == 'slider-content-only'){
					
						if(themify_get('setting-slider_default_display') == 'content') {
							the_content();
						} elseif(themify_get('setting-slider_default_display') == 'none') {
							// none
						} else {
							the_excerpt();
						}

					} else if(themify_get('layout') == 'slider-image-caption'){
					
						echo '<div class="image-caption-wrap">';
							if(themify_check('video_url')){
								global $wp_embed;
								echo '<div class="slide-image">'.$wp_embed->run_shortcode('[embed]' . themify_get('video_url') . '[/embed]').'</div>';
							} else {
								echo $before;
								$w = themify_check( 'image_width' ) ? themify_get( 'image_width' ) : '978';
								$h = themify_check( 'image_height' ) ? themify_get( 'image_height' ) : '400';
								themify_image( "ignore=true&w=$w&h=$h" );
								echo $after;
							}
							if((themify_get('setting-slider_default_display') != 'none') || (themify_get('setting-slider_hide_title') != 'yes')) {
								echo '<div class="slide-content">';
									if(themify_get('setting-slider_hide_title') != 'yes') {
										echo '<h3 class="slide-post-title">'.get_the_title().'</h3>';
									}
									if(themify_get('setting-slider_default_display') == 'content') {
										the_content();
									} elseif(themify_get('setting-slider_default_display') == 'none') {
										// none
									} else {
										the_excerpt();
									}
								echo '</div>';
							}
						echo '</div>';
					
					} else {

						if(themify_check('video_url')){
							global $wp_embed;
							echo '<div class="slide-image">'.$wp_embed->run_shortcode('[embed]' . themify_get('video_url') . '[/embed]').'</div>';
						} else {
							echo $before;
							$w = themify_check( 'image_width' ) ? themify_get( 'image_width' ) : '470';
							$h = themify_check( 'image_height' ) ? themify_get( 'image_height' ) : '400';
							themify_image( "ignore=true&w=$w&h=$h&class=slide-feature-image" );
							echo $after;
						}
						echo '<div class="slide-content">';
						if(themify_get('setting-slider_hide_title') != 'yes') {
							echo '<h3 class="slide-post-title">'.get_the_title().'</h3>';
						}
						if(themify_get('setting-slider_default_display') == 'content') {
							the_content();
						} elseif(themify_get('setting-slider_default_display') == 'none') {
							// none
						} else {
							the_excerpt();
						}
						echo '</div>';
					}
					?>
			
					</li>
			
				<?php endwhile; ?>
			
			</ul>
            
            <?php themify_slider_end(); //hook ?>
		</div>
		<!--/slider --> 
        <?php themify_slider_after(); //hook ?>

	</div>
	<!-- /#slider-inner -->

</div>
<!--/sliderwrap -->

<?php endif; ?>
<?php wp_reset_postdata(); ?>

<?php } ?>