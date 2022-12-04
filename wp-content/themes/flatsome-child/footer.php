<?php
/**
 * The template for displaying the footer.
 *
 * @package flatsome
 */

global $flatsome_opt;
?>
</main>
<footer id="footer" class="footer-wrapper">
	<?php do_action('flatsome_footer'); ?>
</footer>
</div>
<?php wp_footer(); ?>

<style>.d-btn-xt{margin:0;border-radius:2px;font-size:13px;text-transform:inherit;font-weight:500;align-items:center;justify-content:center;float:right;width:90px;height:30px;padding:0;position:absolute;bottom:5px;right:5px}</style>
<script>
(function($) {
  $(".blog-wrapper .large-9 .row .col,.blog-wrapper .large-10 .row .col,.blog-wrapper .large-12 .row .col,.home3 .product-small").each(function(){
    var linkpost = $(this).find(".col-inner a,.image-cover a").attr("href");
    $(this).find(".box-text,.box-text.text-center").append("<a href='"+ linkpost +"' class='button d-btn-xt'>Xem thêm</a>")
  });
  })(jQuery);
	(function($) {
	  $(".home-product .row-small .col").each(function(){
	    var linkpost = $(this).find(".col-inner a,.image-cover a").attr("href");
	    $(this).find(".box-text,.box-text.text-center").append("<a href='"+ linkpost +"' class='button is-link'>Xem chi tiết</a>")
	  });
	  })(jQuery);
</script>

<div class="thanh-tien-trinh">
 <div class="tientrinh"></div>
</div>
<script type="text/javascript">
jQuery(document).ready(function () {
    var pixels = jQuery(document).scrollTop();
    var pageHeight = jQuery(document).height() - jQuery(window).height();
    var tientrinh = 100 * pixels / pageHeight;
 
    jQuery("div.tientrinh").css("width", tientrinh + "%");
});
 
jQuery(document).on("scroll", function () {
    var pixels = jQuery(document).scrollTop();
    var pageHeight = jQuery(document).height() - jQuery(window).height();
    var tientrinh = 100 * pixels / pageHeight;
 
    jQuery("div.tientrinh").css("width", tientrinh + "%");
});
 
</script>
<style>
.thanh-tien-trinh {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    display: flex;
    align-items: center;
    height: 1px;
    background-color: rgba(255, 255, 255, 0.51);
    z-index: 1024;
}
 
.thanh-tien-trinh .tientrinh {
    position: absolute;
    left: 0;
    bottom: 0;
    transition: all linear 100ms;
    height: 4px;
    min-width: 0%;
    background-color: #0C5E3E;
    border-radius: 0 99px 99px 0
}
</style>
</body>
</html>
