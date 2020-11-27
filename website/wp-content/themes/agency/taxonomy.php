<?php
/**
 * Template for custom taxonomy term views.
 * @package themify
 * @since 1.3.9
 */
?>
<?php get_header(); ?>

<?php
/** Themify Default Variables
 *  @var object */
global $themify; ?>

<!-- layout -->
<div id="layout" class="pagewidth clearfix">

	<?php themify_content_before(); //hook ?>
	<!-- content -->
	<div id="content">
    	<?php themify_content_start(); //hook ?>
	
		<?php 
		/////////////////////////////////////////////
		// Category Title	 							
		/////////////////////////////////////////////
		?>
		
		<h1 class="page-title"><?php single_cat_title(); ?></h1>
		<?php echo themify_get_term_description( $wp_query->query_vars['taxonomy'] ); ?>
		

		<?php 
		/////////////////////////////////////////////
		// Loop	 							
		/////////////////////////////////////////////
		?>
		<?php if (have_posts()) : ?>

			<!-- loops-wrapper -->
			<div id="loops-wrapper" class="loops-wrapper <?php echo "$themify->layout $themify->post_layout " . get_query_var( 'post_type' );
			?>">

				<?php while ( have_posts() ) : the_post(); ?>

					<?php get_template_part( 'includes/loop', $set_post_type); ?>

				<?php endwhile; ?>

			</div>
			<!-- /loops-wrapper -->

			<?php get_template_part( 'includes/pagination' ); ?>

		<?php 
		/////////////////////////////////////////////
		// Error - No Page Found	 							
		/////////////////////////////////////////////
		?>
	
		<?php else : ?>

			<p><?php _e( 'Sorry, nothing found.', 'themify' ); ?></p>
	
		<?php endif; ?>			
	
    	<?php themify_content_end(); //hook ?>
	</div>
	<!-- /#content -->
    <?php themify_content_after() //hook; ?>

	<?php 
	/////////////////////////////////////////////
	// Sidebar							
	/////////////////////////////////////////////
	 if ( $themify->layout != 'sidebar-none' ): get_sidebar(); endif; ?>

</div>
<!-- /#layout -->

<?php get_footer(); ?>