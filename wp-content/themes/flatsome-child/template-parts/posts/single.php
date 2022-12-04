<?php if ( have_posts() ) : ?>

<?php /* Start the Loop */ ?>

<?php while ( have_posts() ) : the_post(); ?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="article-inner <?php flatsome_blog_article_classes(); ?>">
		<?php
			if(flatsome_option('blog_post_style') == 'default' || flatsome_option('blog_post_style') == 'inline'){
				get_template_part('template-parts/posts/partials/entry-header', flatsome_option('blog_posts_header_style') );
			}
		?>
		<?php get_template_part( 'template-parts/posts/content', 'single' ); ?>
	</div>
</article>

<?php endwhile; ?>

<?php else : ?>

	<?php get_template_part( 'no-results', 'index' ); ?>

<?php endif; ?>

<?php
/*
 * Code hiển thị bài viết liên quan trong cùng 1 category
 */
$categories = get_the_category(get_the_ID());
if ($categories){
    echo '<div class="bai-viet-lien-quan">';
    $category_ids = array();
    foreach($categories as $individual_category) $category_ids[] = $individual_category->term_id;
    $args=array(
        'category__in' => $category_ids,
        'post__not_in' => array(get_the_ID()),
        'posts_per_page' => 6, // So bai viet dc hien thi
    );
    $my_query = new wp_query($args);
    if( $my_query->have_posts() ):
        echo '<h2>Có thể bạn quan tâm:</h2><ul>';
        while ($my_query->have_posts()):$my_query->the_post();
            ?>
            <li>

            <div class="box-image">
                 <a href="<?php the_permalink() ?>"><?php the_post_thumbnail('large'); ?></a>
            </div>
             <a href="<?php the_permalink() ?>"><h3 class="tieu-de-bai-viet"><?php the_title(); ?></h3>
                        </a>
            </li>
            <?php
        endwhile;
        echo '</ul>';
    endif; wp_reset_query();
    echo '</div>';
}
?>
