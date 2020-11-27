<?php
/**
 * Template to display share buttons
 * @package themify
 * @since 1.0.0
 */

global $themify;
?>

<div class="share clearfix">

	<?php if( 'yes' == themify_get( 'setting-twitter' ) ) : ?>
	<div class="share-twitter">
		<a href="https://twitter.com/share" class="twitter-share-button"><?php _e('Tweet', 'themify'); ?></a>
		<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>
	</div>
	<?php endif; ?>

	<?php if( 'yes' == themify_get( 'setting-facebook' ) ) : ?>
	<div class="share-facebook">
		<div id="fb-root"></div>
		<script>(function(d, s, id) {
		  var js, fjs = d.getElementsByTagName(s)[0];
		  if (d.getElementById(id)) return;
		  js = d.createElement(s); js.id = id;
		  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";
		  fjs.parentNode.insertBefore(js, fjs);
		}(document, 'script', 'facebook-jssdk'));</script>
		<div class="fb-like" data-href="<?php the_permalink(); ?>" data-send="true" data-layout="button_count" data-width="100" data-show-faces="false"></div>
	</div>
	<?php endif; ?>

	<?php if( 'yes' == themify_get( 'setting-googleplus' ) ) : ?>
	<div class="share-google-plus">
		<div class="g-plusone"></div>
		<script type="text/javascript">
		  (function() {
			var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
			po.src = 'https://apis.google.com/js/plusone.js';
			var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
		  })();
		</script>
	</div>
	<?php endif; ?>

	<?php if( 'yes' == themify_get( 'setting-stumbleupon' ) ) : ?>
	<div class="share-stumbleupon">
		<su:badge layout="1" location="<?php the_permalink(); ?>"></su:badge>
		<script>
		(function() {
          var li = document.createElement('script'); li.type = 'text/javascript'; li.async = true;
          li.src = '//platform.stumbleupon.com/1/widgets.js'; 
          var s = document.getElementsByTagName('script')[0];s.parentNode.insertBefore(li, s);
        })();
		</script>
	</div>
	<?php endif; ?>

	<?php if( 'yes' == themify_get( 'setting-pinterest' ) ) : ?>
	<div class="share-pinterest">
		<a href="http://pinterest.com/pin/create/button/?url=<?php the_permalink(); ?>" class="pin-it-button" count-layout="horizontal"><?php _e( 'Pin it', 'themify' ); ?></a>
		<script>
		(function() { 
          var li = document.createElement('script'); li.type = 'text/javascript';li.async = true;
          li.src = '//assets.pinterest.com/js/pinit.js'; 
          var s = document.getElementsByTagName('script')[0];s.parentNode.insertBefore(li, s);
        })();
		</script>
	</div>
	<?php endif; ?>

	<?php if( 'yes' == themify_get( 'setting-linkedin' ) ) : ?>
	<div class="share-linkedin">
		<script type="in/share" data-url="<?php the_permalink(); ?>"></script>
		<script>
		(function() {
          var li = document.createElement('script'); li.type = 'text/javascript'; li.async = true;
          li.src = '//platform.linkedin.com/in.js'; 
          var s = document.getElementsByTagName('script')[0];s.parentNode.insertBefore(li, s);
        })();
		</script>
	</div>
	<?php endif; ?>

</div>
<!-- /.share -->