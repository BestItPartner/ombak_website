<?php
/**
 * Template for Main Sidebar
 * @package themify
 * @since 1.0.0
 */
?>
<?php if(!post_password_required()):?>
    <?php themify_sidebar_before(); // hook ?>

    <aside id="sidebar" itemscope="itemscope" itemtype="https://schema.org/WPSidebar">

            <?php themify_sidebar_start(); // hook ?>

            <?php dynamic_sidebar('sidebar-main'); ?>

            <div class="clearfix">

                    <div class="secondary">
                            <?php dynamic_sidebar('sidebar-main-2a'); ?>
                    </div>

                    <div class="secondary last">
                            <?php dynamic_sidebar('sidebar-main-2b'); ?>
                    </div>

            </div>

            <?php dynamic_sidebar('sidebar-main-3'); ?>

            <?php themify_sidebar_end(); // hook ?>

    </aside>
    <!-- /#sidebar -->

    <?php themify_sidebar_after(); // hook ?>
<?php endif;?>
