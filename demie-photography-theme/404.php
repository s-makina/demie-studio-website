<?php
// 404 error page.
get_header();
?>

<section class="wptb-credential error-404">
    <div class="container">
        <div class="wptb-credential--inner text-center">
            <h1 class="wptb-item--title">404</h1>
            <h3><?php esc_html_e('Page Not Found', 'demie-photography'); ?></h3>
            <p><?php esc_html_e('The page you are looking for may have been moved, deleted or never existed.', 'demie-photography'); ?></p>
            <div class="wptb-item--button text-center">
                <a class="btn btn-two text-uppercase" href="<?php echo esc_url(home_url('/')); ?>">
                    <span class="btn-wrap">
                        <span class="text-first"><?php esc_html_e('Back to Home', 'demie-photography'); ?></span>
                        <span class="text-second"> <i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i> </span>
                    </span>
                </a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
