<?php
require get_stylesheet_directory() . '/inc/function_op.php';

add_filter( 'gettext', function ( $strings ) {
$text = array(
	'Thêm vào giỏ hàng' => 'Thêm vào giỏ',
	'filter by price' => 'Lọc theo giá',
	'Đọc tiếp' => 'Xem chi tiết',
);
$strings = str_ireplace( array_keys( $text ), $text, $strings );
return $strings;
}, 20 );

/*========== Remove Default WordPress Image Sizes ==========*/
function dinhit_remove_default_image_sizes($sizes)
{
    unset($sizes['thumbnail']);
    unset($sizes['medium']);
    unset($sizes['large']);
    return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'dinhit_remove_default_image_sizes');
add_filter('intermediate_image_sizes_advanced', '__return_false');

// Prevent WP from adding <p> tags on pages
function disable_wp_auto_p( $content ) {
  if ( is_singular( 'page' ) ) {
    remove_filter( 'the_content', 'wpautop' );
    remove_filter( 'the_excerpt', 'wpautop' );
  }
  return $content;
}
add_filter( 'the_content', 'disable_wp_auto_p', 0 );

function breadcrumb_dinhit () {
	if ( function_exists('yoast_breadcrumb') ) {
      		yoast_breadcrumb( '<div id="breadcrumbs">','</div>' );
    	}
}
add_shortcode( 'brc', 'breadcrumb_dinhit' );

/*========== Remove categories ==========*/
// Remove product cat base
add_filter('term_link', 'devvn_no_term_parents', 1000, 3);
function devvn_no_term_parents($url, $term, $taxonomy) {
    if($taxonomy == 'product_cat'){
        $term_nicename = $term->slug;
        $url = trailingslashit(get_option( 'danh-muc-san-pham' )) . user_trailingslashit( $term_nicename, 'category' );
    }
    return $url;
}
 
// Add our custom product cat rewrite rules
function devvn_no_product_cat_parents_rewrite_rules($flash = false) {
    $terms = get_terms( array(
        'taxonomy' => 'product_cat',
        'post_type' => 'product',
        'hide_empty' => false,
    ));
    if($terms && !is_wp_error($terms)){
        foreach ($terms as $term){
            $term_slug = $term->slug;
            add_rewrite_rule($term_slug.'/?$', 'index.php?product_cat='.$term_slug,'top');
            add_rewrite_rule($term_slug.'/page/([0-9]{1,})/?$', 'index.php?product_cat='.$term_slug.'&paged=$matches[1]','top');
            add_rewrite_rule($term_slug.'/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$', 'index.php?product_cat='.$term_slug.'&feed=$matches[1]','top');
        }
    }
    if ($flash == true)
        flush_rewrite_rules(false);
}
add_action('init', 'devvn_no_product_cat_parents_rewrite_rules');

/*========== Sửa lỗi khi tạo mới taxomony bị 404 ==========*/
add_action( 'create_term', 'devvn_new_product_cat_edit_success', 10, 2 );
function devvn_new_product_cat_edit_success( $term_id, $taxonomy ) {
    //devvn_product_category_rewrite_rules(true);
}  
function devvn_remove_slug( $post_link, $post ) {
    if ( !in_array( get_post_type($post), array( 'product' ) ) || 'publish' != $post->post_status ) {
        return $post_link;
    }
    if('product' == $post->post_type){
        $post_link = str_replace( '/danh-muc-san-pham/', '/', $post_link ); //Thay cua-hang bang slug hien tai cua ban
    }else{
        $post_link = str_replace( '/' . $post->post_type . '/', '/', $post_link );
    }
    return $post_link; 
}  
add_filter( 'post_type_link', 'devvn_remove_slug', 10, 2 );

/*========== Sửa lỗi 404 sau khi đã remove slug product hoặc cua-hang ==========*/
function devvn_woo_product_rewrite_rules($flash = false) {
    global $wp_post_types, $wpdb;
    $siteLink = esc_url(home_url('/'));
    foreach ($wp_post_types as $type=>$custom_post) {
        if($type == 'product'){
            if ($custom_post->_builtin == false) {
                $querystr = "SELECT {$wpdb->posts}.post_name, {$wpdb->posts}.ID
                            FROM {$wpdb->posts} 
                            WHERE {$wpdb->posts}.post_status = 'publish' 
                            AND {$wpdb->posts}.post_type = '{$type}'";
                $posts = $wpdb->get_results($querystr, OBJECT);
                foreach ($posts as $post) {
                    $current_slug = get_permalink($post->ID);
                    $base_product = str_replace($siteLink,'',$current_slug);
                    add_rewrite_rule($base_product.'?$', "index.php?{$custom_post->query_var}={$post->post_name}", 'top');
                }
            }  
        }
    }
    if ($flash == true)
        flush_rewrite_rules(false);
}
add_action('init', 'devvn_woo_product_rewrite_rules');

/*========== Fix loi khi tao san pham moi bi 404 ==========*/
function devvn_woo_new_product_post_save($post_id){
    global $wp_post_types;
    $post_type = get_post_type($post_id);
    foreach ($wp_post_types as $type=>$custom_post) {
        if ($custom_post->_builtin == false && $type == $post_type) {
            devvn_woo_product_rewrite_rules(true);
        }
    }
}
add_action('wp_insert_post', 'devvn_woo_new_product_post_save');
/*========== END Remove categories ==========*/
