<?php if(!is_single()) { global $more; $more = 0; } //enable more link ?>
<?php
/** Themify Default Variables
 *  @var object */
global $themify; ?>

<?php
$categories = wp_get_object_terms(get_the_id(), 'portfolio-category');
$class = '';
foreach($categories as $cat){
	$class .= ' cat-'.$cat->term_id;
}
?>

<?php themify_post_before(); //hook ?>
<article class="post clearfix portfolio <?php echo implode(' ', get_post_class($class)); ?>">
	<?php themify_post_start(); // hook ?>
	<?php if($themify->hide_image != 'yes') { ?>
		<?php themify_before_post_image(); // hook ?>
		<div class="post-image">
			<?php
			if(themify_check('_gallery_shortcode')){
				// Get images from [gallery]
				$sc_gallery = preg_replace('#\[gallery(.*)ids="([0-9|,]*)"(.*)\]#i', '$2', themify_get('_gallery_shortcode'));
				$image_ids = explode(',', str_replace(' ', '', $sc_gallery));
				$gallery_images = get_posts(array(
					'post__in' => $image_ids,
					'post_type' => 'attachment',
					'post_mime_type' => 'image',
					'numberposts' => -1,
					'orderby' => 'post__in',
					'order' => 'ASC'
				));

				$autoplay = themify_check('setting-portfolio_slider_autoplay')?
								themify_get('setting-portfolio_slider_autoplay'): '4000';

				$effect = themify_check('setting-portfolio_slider_effect')?
						themify_get('setting-portfolio_slider_effect'):	'scroll';

				$speed = themify_check('setting-portfolio_slider_transition_speed')?
						themify_get('setting-portfolio_slider_transition_speed'): '500';
				?>
				<div id="portfolio-slider-<?php the_id(); ?>" class="slideshow-wrap">
					<ul class="slideshow" data-id="portfolio-slider-<?php the_id(); ?>" data-autoplay="<?php echo $autoplay; ?>" data-effect="<?php echo $effect; ?>" data-speed="<?php echo $speed; ?>">
						<?php
						foreach ( $gallery_images as $gallery_image ) { ?>
							<li>
								<?php if( 'yes' != $themify->unlink_image) : ?><a href="<?php echo themify_get_featured_image_link(); ?>"><?php endif; ?>
									<?php
									echo $themify->portfolio_image($gallery_image->ID, $themify->width, $themify->height);
									?>
									<?php themify_zoom_icon(); ?>
								<?php if( 'yes' != $themify->unlink_image) : ?></a><?php endif; ?>
								<?php
								if(themify_loop_is_singular('portfolio')){
									if('' != $img_caption = $gallery_image->post_excerpt) { ?>
										<div class="slider-image-caption"><?php echo $img_caption; ?></div>
								<?php
									}
								}
								?>
							</li>
						<?php }	?>
					</ul>
				</div>
			<?php
			} else {

				//otherwise display the featured image
				if( $post_image = themify_get_image('ignore=true&'.$themify->auto_featured_image . $themify->image_setting . "w=".$themify->width."&h=".$themify->height ) ){ ?>
					<figure class="post-image <?php echo $themify->image_align; ?>">
						<?php if( 'yes' == $themify->unlink_image): ?>
							<?php echo $post_image; ?>
						<?php else: ?>
							<a href="<?php echo themify_get_featured_image_link(); ?>"><?php echo $post_image; ?><?php themify_zoom_icon(); ?></a>
						<?php endif; ?>
					</figure>
				<?php } // end if post image
			}
			?>
			<?php themify_after_post_image(); // hook ?>
		</div><!-- .post-image -->
	<?php } //post meta ?>

	<div class="post-content">
		<?php if($themify->hide_meta != 'yes'): ?>
			<p class="post-meta entry-meta">
                            <?php $terms = get_the_term_list( get_the_id(), get_post_type().'-category', '<span class="post-category">', ', ', ' <span class="separator">/</span></span>' );
                                    if(!is_wp_error($terms)){
                                            echo $terms;
                                    }
                            ?>
			</p>
		<?php endif; //post meta ?>

		<?php if($themify->hide_title != 'yes'): ?>
			<?php themify_post_title(); ?>
		<?php endif; //post title ?>

		<?php if($themify->hide_date != 'yes'): ?>
			<time datetime="<?php the_time('o-m-d') ?>" class="post-date entry-date updated"><?php echo get_the_date( apply_filters( 'themify_loop_date', '' ) ) ?></time>

		<?php endif; //post date ?>

		<div class="entry-content">

		<?php if ( 'excerpt' == $themify->display_content && ! is_attachment() ) : ?>

			<?php the_excerpt(); ?>

		<?php elseif ( 'none' == $themify->display_content && ! is_attachment() ) : ?>

		<?php else: ?>

			<?php the_content(themify_check('setting-default_more_text')? themify_get('setting-default_more_text') : __('More &rarr;', 'themify')); ?>

		<?php endif; //display content ?>

		</div><!-- /.entry-content -->

		<?php edit_post_link(__('Edit', 'themify'), '<span class="edit-button">[', ']</span>'); ?>
	</div>
	<!-- /.post-content -->
	<?php themify_post_end(); // hook ?>

</article>
<!-- /.post -->
<?php themify_post_after(); //hook ?>
