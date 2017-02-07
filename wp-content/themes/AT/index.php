<?php
/*
* Theme: PREMIUMPRESS CORE FRAMEWORK FILE
* Url: www.premiumpress.com
* Author: Mark Fail
*
* THIS FILE WILL BE UPDATED WITH EVERY UPDATE
* IF YOU WANT TO MODIFY THIS FILE, CREATE A CHILD THEME
*
* http://codex.wordpress.org/Child_Themes
*/
if (!defined('THEME_VERSION')) {	header('HTTP/1.0 403 Forbidden'); exit; }
?>

<?php get_header($CORE->pageswitch()); ?>

<?php if (have_posts()) : ?>

<div class="panel panel-default">

	<div class="panel-heading"><?php if(is_category()){ single_cat_title(); }else{ the_title(); } ?></div>

		 <div class="panel-body">

         <div class="list_style row">

		<?php  while (have_posts()) : the_post(); ?>

        <?php get_template_part( 'content', $post->post_type ); ?>

		<?php endwhile; ?>

        </div>

		</div>
</div>

<?php echo $CORE->PAGENAV(); ?>

<?php else: ?>

<?php get_template_part( 'search', 'noresults' ); ?>

<?php endif; ?>

<?php get_footer($CORE->pageswitch()); ?>
