<?php
/**
 * Template to display posts related to the current post.
 * @package themify
 * @since 1.0.0
 */

global $themify, $post;

// Try getting terms from category taxonomy
$taxonomy = 'category';
$terms = get_the_terms( $post->ID , $taxonomy);

// If it doesn't return terms, try post_tag taxonomy
if ( false === $terms ) {
	$taxonomy = 'post_tag';
	$terms = get_the_terms( $post->ID , $taxonomy );
}

if ( false !== $terms ) {
	// Excluded posts in each loop
	$do_not_duplicate[] = $post->ID;

	// Setup related posts options
	$key = 'setting-related_posts_';

	// Entries count and limit
	$entries_limit = themify_check( $key . 'limit' )? (int)themify_get( $key . 'limit' ) : 3;

	// Dimensions of related post image
	$related_width = themify_check( $key . 'width' )? themify_get( $key . 'width' ) : '65';
	$related_height = themify_check( $key . 'height' )? themify_get( $key . 'height' ) : '65';

	// We only need the term objects
	$terms = array_values( $terms );

	// Duplicate term objects in array until the required count
	for ( $i = 0; $i < $entries_limit; $i ) {
		$terms = array_merge( $terms, $terms );
		$i = count( $terms );
	}
	?>
	<div class="related-posts">
		<h4 class="related-title"><?php _e('Related Posts', 'themify'); ?></h4>
		<?php
		foreach ( $terms as $term ) :
			if( 0 == $entries_limit ) {
				break;
			} else {
				$entries_limit = $entries_limit - 1;
			}
			$posts = get_posts( apply_filters( 'themify_related_posts', array (
				'tax_query' => array(
					array(
						'taxonomy' => $taxonomy,
						'field' => 'slug',
						'terms' => array( $term->slug )
					)
				),
				'posts_per_page' => 1,
				'ignore_sticky_posts' => 1,
				'post__not_in' => $do_not_duplicate
			) ) );
			if ( $posts ) :
				$term_link = get_term_link( $term, $taxonomy );
				if ( is_wp_error( $term_link ) )
					$term_link = '';

				foreach ( $posts as $post ) :
					setup_postdata($post);
					$do_not_duplicate[] = $post->ID;
					?>
					<article class="post type-post clearfix">
						<?php
						if ( $post_image = themify_get_image( $themify->auto_featured_image . "w=$related_width&h=$related_height&ignore=true" ) ) :
						?>
							<figure class="post-image">
								<a href="<?php the_permalink(); ?>">
									<?php echo $post_image; ?>
								</a>
							</figure>
						<?php endif; // end if post image ?>
						<div class="post-content">
							<p class="post-meta">
								<span class="post-category"><a href="<?php echo $term_link; ?>"><?php echo $term->name; ?></a> / </span>
							</p>
							<h1 class="post-title">
								<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a>
							</h1>
						</div>
						<!-- /.post-content -->

						

					</article>
					<?php
				endforeach; // end for each post
				wp_reset_postdata();
			endif; // end if posts
		endforeach; // end for each term ?>
	</div>
	<!-- /.related -->
<?php
}
?>
