<?php
/**
 * Template to display breaking news slider
 * @package themify
 * @since 1.0.0
 */

// Get Breaking News taxonomy
$breaking_news_tax = themify_get('setting-breaking_news_tax');

$suffix = ( 'post_tag' == $breaking_news_tax ) ? '_tag' : '';

// Get Breaking News term
$breaking_news_term = themify_get( 'setting-breaking_news' . $suffix );
$breaking_news_limit = themify_get( 'setting-breaking_news_limit' );

// Get Breaking News title
$breaking_news_title = get_term_by('slug', $breaking_news_term, $breaking_news_tax);

// Get Breaking News slider options
$key = 'setting-breaking_news_slider';
$autoplay = themify_check($key.'_autoplay')? themify_get($key.'_autoplay'): '4000';
$effect = themify_check($key.'_effect')? themify_get($key.'_effect'): 'scroll';
$speed = themify_check($key.'_transition_speed')? themify_get($key.'_transition_speed'): '500';

?>

<div class="breaking-news clearfix pagewidth">

	<div class="breaking-news-loader"></div>
	
	<div class="slideshow-wrap">

		<h3 class="breaking-news-category"><?php echo ($breaking_news_tax == 'category' && $breaking_news_term == 'all-categories') ? __('Breaking News', 'themify') : $breaking_news_title->name; ?></h3>

		<ul class="breaking-news-posts slideshow" data-previd="breaking-news-prev" data-pauseid="breaking-news-play_pause" data-nextid="breaking-news-next" data-autoplay="<?php echo $autoplay; ?>" data-effect="<?php echo $effect; ?>" data-speed="<?php echo $speed; ?>">
			<?php echo themify_theme_breaking_news( $breaking_news_term, 'li', $breaking_news_tax, $breaking_news_limit ); ?>
		</ul>

		<div class="breaking-news-nav">
			<a href="#" id="breaking-news-prev" class="breaking-news-prev"></a>
			<a href="#" id="breaking-news-play_pause" class="breaking-news-play_pause playing"></a>
			<a href="#" id="breaking-news-next" class="breaking-news-next"></a>
		</div>

	</div>
	<!-- /.slideshow-wrap -->

</div>
<!-- /.breaking-news -->