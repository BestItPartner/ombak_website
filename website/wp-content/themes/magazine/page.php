<?php
/**
 * Template for page view including query categories
 * @package themify
 * @since 1.0.0
 */
?>

<?php get_header(); ?>

<?php
/** Themify Default Variables
 *  @var object */
global $themify; ?>

<!-- layout-container -->
<div id="layout" class="pagewidth clearfix">

	<div id="contentwrap">

		<?php themify_content_before(); // hook ?>
		<!-- content -->
		<div id="content" class="clearfix">
			<?php themify_content_start(); // hook ?>

			<?php
			/////////////////////////////////////////////
			// 404
			/////////////////////////////////////////////
			if(is_404()): ?>
				<h1 class="page-title"><?php _e('404','themify'); ?></h1>
				<p><?php _e( 'Page not found.', 'themify' ); ?></p>
				<?php if( current_user_can('administrator') ): ?>
					<p><?php _e( '@admin Learn how to create a <a href="https://themify.me/docs/custom-404" target="_blank">custom 404 page</a>.', 'themify' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php
			/////////////////////////////////////////////
			// PAGE
			/////////////////////////////////////////////
			?>
			<?php if ( ! is_404() && have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<div id="page-<?php the_ID(); ?>" class="type-page">

				<!-- page-title -->
				<?php if($themify->page_title != "yes"): ?>
					
					<time datetime="<?php the_time( 'o-m-d' ); ?>"></time>
					<h1 class="page-title"><?php the_title(); ?></h1>
				<?php endif; ?>
				<!-- /page-title -->

				<div class="page-content entry-content">

					<?php if ( $themify->hide_page_image != 'yes' && has_post_thumbnail() ) : ?>
						<figure class="post-image"><?php themify_image( "{$themify->auto_featured_image}w={$themify->image_page_single_width}&h={$themify->image_page_single_height}&ignore=true" ); ?></figure>
					<?php endif; ?>

					<?php the_content(); ?>

					<?php wp_link_pages(array('before' => '<p class="post-pagination"><strong>'.__('Pages:','themify').'</strong> ', 'after' => '</p>', 'next_or_number' => 'number')); ?>

					<?php edit_post_link(__('Edit','themify'), '[', ']'); ?>

					<!-- comments -->
					<?php if(!themify_check('setting-comments_pages') && $themify->query_category == ""): ?>
						<?php comments_template(); ?>
					<?php endif; ?>
					<!-- /comments -->

				</div>
				<!-- /.post-content -->

				</div><!-- /.type-page -->
			<?php endwhile; endif; ?>

			<?php
			/////////////////////////////////////////////
			// Query Category
			/////////////////////////////////////////////
			?>
			<?php if( $themify->query_category != '' ) :
				// Query posts action based on global $themify options
				do_action( 'themify_custom_query_posts' );
				
				if( have_posts() ) : ?>
					<!-- loops-wrapper -->
					<div id="loops-wrapper" class="loops-wrapper <?php echo $themify->layout . ' ' . $themify->post_layout; ?>">

						<?php while(have_posts()) : the_post(); ?>
							<?php get_template_part('includes/loop', 'query'); ?>
						<?php endwhile; ?>

					</div>
					<!-- /loops-wrapper -->

					<?php if ($themify->page_navigation != 'yes'): ?>
						<?php get_template_part( 'includes/pagination'); ?>
					<?php endif; ?>

				<?php endif; ?>

				<?php wp_reset_query(); ?>

			<?php endif; ?>

			<?php themify_content_end(); // hook ?>
		</div>
		<!-- /content -->
		<?php themify_content_after(); // hook ?>

	</div>
	<!-- /#contentwrap -->

	<?php
	/////////////////////////////////////////////
	// Sidebar
	/////////////////////////////////////////////
	if ($themify->layout != 'sidebar-none'): get_sidebar(); endif; ?>

	

</div>
<!-- /layout-container -->

<?php get_footer(); ?>
