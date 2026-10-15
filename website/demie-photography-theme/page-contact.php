<?php
/**
 * Template Name: Contact
 * Source: contact-1.html (map replaced with an address card — no API key needed)
 */

get_header();

$demie_h_l1 = demie_current_meta('_demie_h_l1', __('Get In Touch', 'demie-photography'));
$demie_h_desc = demie_current_meta('_demie_h_desc', __('Contact us for a great photography session & beautiful captured moments', 'demie-photography'));
?>

<!-- Contact -->
<section class="wptb-contact-form style1 bg-image-2" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/background/bg-9.jpg'); ?>');">
    <div class="wptb-item-layer both-version">
        <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-2.png'); ?>" alt="">
        <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-2-light.png'); ?>" alt="">
    </div>
    <div class="container">
        <div class="wptb-form--wrapper">
            <div class="wptb-heading">
                <div class="wptb-item--inner text-center">
                    <h1 class="wptb-item--title"><?php echo esc_html($demie_h_l1); ?></h1>
                    <div class="wptb-item--description"><?php echo esc_html($demie_h_desc); ?></div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 offset-lg-2">
                    <?php demie_render_contact_form(); ?>
                </div>
            </div>
        </div>

        <div class="wptb-office-address mr-top-100">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="wptb-icon-box1 wow fadeInLeft">
                        <div class="wptb-item--inner flex-start">
                            <div class="wptb-item--icon"><i class="bi bi-envelope"></i></div>
                            <div class="wptb-item--holder">
                                <h3 class="wptb-item--title"><?php esc_html_e('Email Us', 'demie-photography'); ?></h3>
                                <p class="wptb-item--description"><?php echo esc_html(demie_email()); ?></p>
                                <a href="mailto:<?php echo esc_attr(demie_email()); ?>" class="wptb-item--link"><?php esc_html_e('Write Now', 'demie-photography'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 px-md-5">
                    <div class="wptb-icon-box1 wow fadeInLeft">
                        <div class="wptb-item--inner flex-start">
                            <div class="wptb-item--icon"><i class="bi bi-phone"></i></div>
                            <div class="wptb-item--holder">
                                <h3 class="wptb-item--title"><?php esc_html_e('Book Us', 'demie-photography'); ?></h3>
                                <p class="wptb-item--description"><?php echo esc_html(demie_phone()); ?></p>
                                <a href="<?php echo esc_url(demie_whatsapp_url()); ?>" target="_blank" rel="noopener" class="wptb-item--link"><?php esc_html_e('WhatsApp Now', 'demie-photography'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="wptb-icon-box1 wow fadeInLeft">
                        <div class="wptb-item--inner flex-start">
                            <div class="wptb-item--icon"><i class="bi bi-geo-alt"></i></div>
                            <div class="wptb-item--holder">
                                <h3 class="wptb-item--title"><?php esc_html_e('Studio Address', 'demie-photography'); ?></h3>
                                <p class="wptb-item--description"><?php echo esc_html(demie_location()); ?></p>
                                <a href="<?php echo esc_url(demie_maps_url()); ?>" target="_blank" rel="noopener" class="wptb-item--link"><?php esc_html_e('View Map', 'demie-photography'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
