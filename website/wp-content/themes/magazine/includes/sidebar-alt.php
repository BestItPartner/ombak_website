<?php
/**
 * Additional Sidebar Template
 * @package themify
 * @since 1.0.0
 */
?>

<?php themify_sidebar_alt_before(); //hook ?>

<aside id="sidebar-alt">

	<?php themify_sidebar_alt_start(); //hook ?>

	<?php dynamic_sidebar('sidebar-alt'); ?>

	<?php themify_sidebar_alt_end(); //hook ?>

</aside>

<?php themify_sidebar_alt_after(); //hook ?>
