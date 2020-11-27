<?php if(!is_single()) { global $more; $more = 0; } //enable more link ?>
<?php
/** Themify Default Variables
 *  @var object */
global $themify; ?>

<?php themify_post_before(); //hook ?>
<article id="team-<?php the_id(); ?>" <?php post_class("team-post clearfix "); ?>>
	<?php themify_post_start(); // hook ?>
	<?php if ( 'yes' != $themify->hide_image ) : ?>

		<?php
		// Display Featured Image
		if( $post_image = themify_get_image( 'ignore=true&' . $themify->auto_featured_image . $themify->image_setting . "w=" . $themify->width . "&h=" . $themify->height  ) ) : ?>
			<?php themify_before_post_image(); // hook ?>
			<figure class="post-image <?php echo $themify->image_align; ?>">
				<?php if( 'yes' == $themify->unlink_image): ?>
					<?php echo $post_image; ?>
				<?php else: ?>
					<a href="<?php echo themify_get_featured_image_link(); ?>"><?php echo $post_image; ?><?php themify_zoom_icon(); ?></a>
				<?php endif // unlink image ?>
			</figure>
			<?php themify_after_post_image(); // hook ?>
		<?php endif; // post image ?>

	<?php endif; // hide image ?>

	<div class="post-content">

		<?php if('yes' != $themify->hide_title): ?>
			<?php themify_before_post_title(); // hook ?>
			<div class="team-info">
				<?php if ( 'yes' != $themify->unlink_title ) : ?>
					<h1 class="team-name"><a href="<?php echo themify_get_featured_image_link(); ?>"><?php the_title(); ?></a></h1>
				<?php else: ?>
					<h1 class="team-name"><?php the_title(); ?></h1>
				<?php endif; // unlink title ?>
				<?php if ( themify_check( '_team_title' ) ) : ?>
					<em class="team-title"><?php echo themify_get( '_team_title' ); ?></em>
				<?php endif; ?>
			</div>
			<?php themify_after_post_title(); // hook ?>
		<?php endif; // hide title ?>

		<div class="entry-content">

		<?php if ( 'excerpt' == $themify->display_content && ! is_attachment() ) : ?>

			<?php the_excerpt(); ?>

			<?php if( themify_check('setting-excerpt_more') ) : ?>
				<p><a href="<?php the_permalink(); ?>" class="more-link"><?php echo themify_check('setting-default_more_text')? themify_get('setting-default_more_text') : __('More &rarr;', 'themify') ?></a></p>
			<?php endif; ?>

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
<?php themify_post_after(); // hook ?>
