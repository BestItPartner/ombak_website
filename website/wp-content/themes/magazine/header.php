<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<?php
/** Themify Default Variables
 *  @var object */
	global $themify; ?>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<!-- wp_header -->
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php themify_body_start(); // hook ?>
<div id="pagewrap" class="hfeed site">

	<div id="headerwrap">

		<div id="nav-bar">
			<div class="pagewidth clearfix">
				<?php if( has_nav_menu( 'top-nav' ) ) : ?>
					<a id="menu-icon-top" href="#"><i class="fa fa-list-ul icon-list-ul"></i></a>
					<?php if (function_exists('wp_nav_menu')) { ?>
					<nav id="top-nav-mobile-menu" class="sidemenu sidemenu-off" role="navigation">
						<?php wp_nav_menu(array('theme_location' => 'top-nav' , 'fallback_cb' => '' , 'container'  => '' , 'menu_id' => 'top-nav' , 'menu_class' => 'top-nav')); ?>
					</nav>
					<?php } ?>
				<?php endif; ?>

				<div class="social-widget">
					<?php dynamic_sidebar('social-widget'); ?>

					<?php if(!themify_check('setting-exclude_rss')): ?>
						<div class="rss">
							<a href="<?php themify_theme_feed_link(); ?>" class="hs-rss-link">
								<i class="fa fa-icon icon-rss"></i>
							</a>
						</div>
					<?php endif ?>
				</div>
				<!-- /.social-widget -->

				<?php if(!themify_check('setting-exclude_search_form')): ?>
					<div id="searchform-wrap">
						<div id="search-icon" class="mobile-button"></div>
						<?php get_search_form(); ?>
					</div>
					<!-- /#searchform-wrap -->
				<?php endif ?>

			</div>
		</div>
		<!-- /#nav-bar -->

		<?php themify_header_before(); // hook ?>

		<header id="header" class="pagewidth clearfix" itemscope="itemscope" itemtype="https://schema.org/WPHeader">

			<?php themify_header_start(); // hook ?>

			<div class="hgroup">
				<?php echo themify_logo_image('site_logo'); ?>
				<?php echo themify_site_description(); ?>
			</div>

			<a id="menu-icon" href="#"><i class="fa fa-list-ul icon-list-ul"></i></a>
			<nav id="mobile-menu" class="sidemenu sidemenu-off" role="navigation" itemscope="itemscope" itemtype="https://schema.org/SiteNavigationElement">
				<?php themify_theme_main_menu(); ?>
				<!-- /#main-nav -->
			</nav>

			<?php themify_header_end(); // hook ?>

		</header>
		<!-- /#header -->

        <?php themify_header_after(); // hook ?>

	</div>
	<!-- /#headerwrap -->

	<div class="header-widget pagewidth">
		<?php dynamic_sidebar('header-widget'); ?>
	</div>
	<!--/header widget -->

	<?php if( '' != themify_get('setting-breaking_news') ) : ?>
		<?php get_template_part( 'includes/breaking-news'); ?>
	<?php endif; // end breaking news ?>

	<div id="body" class="clearfix">

    <?php themify_layout_before(); //hook ?>
