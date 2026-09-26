<?php
/**
 * Template Name: About Us
 * Source: about.html
 */

get_header();

get_template_part('template-parts/page-titlebar', null, [
    'title' => get_the_title() ?: __('About Us', 'demie-photography'),
    'bg'    => 'page-header-bg-4.jpg',
]);
?>

<!-- About Demie Photography -->
<section class="wptb-about-one bg-image-2" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture.png'); ?>');">
    <div class="container">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <div class="wptb-image-single mr-bottom-90 wow fadeInUp">
            <div class="wptb-item--inner">
                <div class="wptb-item--image">
                    <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/7.png'); ?>" alt="img">
                </div>
            </div>
        </div>

        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="wptb-heading">
                    <div class="wptb-item--inner">
                        <h6 class="wptb-item--subtitle"><span>01 //</span> <?php esc_html_e('About Us', 'demie-photography'); ?></h6>
                        <h1 class="wptb-item--title"><?php esc_html_e('About', 'demie-photography'); ?> <span><?php esc_html_e('Demie Photography', 'demie-photography'); ?></span></h1>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mt-4 mt-lg-0">
                <div class="entry-content demie-page-content">
                    <?php the_content(); ?>
                </div>
            </div>
        </div>
        <?php endwhile; endif; ?>
    </div>
</section>

<?php
// Pull the remainder of the About page (FAQ / team / testimonial / CTA) from the static build.
// Kept static like 99carex does for brochure pages; edit here to change content.
?>

<!-- FAQ -->
<section class="wptb-faq-one bg-image pb-0" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/background/bg-8.jpg'); ?>');">
    <div class="container">
        <div class="row">
            <div class="col-lg-5">
                <div class="wptb-heading">
                    <div class="wptb-item--inner">
                        <h6 class="wptb-item--subtitle"><span>02 //</span> <?php esc_html_e('F.A.Q', 'demie-photography'); ?></h6>
                        <h1 class="wptb-item--title"><?php esc_html_e('Frequently', 'demie-photography'); ?> <br> <span><?php esc_html_e('Ask Questions', 'demie-photography'); ?></span></h1>
                        <p class="wptb-item--description"><?php esc_html_e('We are always ready to help you. Visit our studio in Chilomoni, Blantyre, or reach us any time.', 'demie-photography'); ?></p>
                        <div class="wptb-item--button">
                            <a class="btn btn-two creative text-uppercase" href="<?php echo esc_url(demie_whatsapp_url()); ?>" target="_blank" rel="noopener">
                                <span class="btn-wrap">
                                    <span class="text-first"><?php esc_html_e('Ask on WhatsApp', 'demie-photography'); ?></span>
                                    <span class="text-second"> <i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i> </span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mt-5 mt-lg-0">
                <div class="accordion" id="accordionFaq">
                    <?php
                    $demie_faqs = [
                        [__('What areas do you serve?', 'demie-photography'), __('We are based in Chilomoni, Blantyre, and cover sessions across Malawi, including Lilongwe and surrounding areas.', 'demie-photography')],
                        [__('How do I book a session?', 'demie-photography'), __('Call or WhatsApp us on +265 884 44 48 02, or send a message through the contact form. We will confirm your date and package.', 'demie-photography')],
                        [__('How long does a session take?', 'demie-photography'), __('Portrait sessions usually take 1–2 hours. Weddings and events are quoted for a full or half day depending on your schedule.', 'demie-photography')],
                        [__('When will we receive our photos?', 'demie-photography'), __('Sneak peeks are delivered within a few days. Full edited galleries are typically ready within 2–3 weeks.', 'demie-photography')],
                    ];
                    foreach ($demie_faqs as $demie_i => $demie_faq) :
                        $demie_open = 0 === $demie_i;
                        ?>
                        <div class="accordion-item<?php echo $demie_open ? ' active' : ''; ?>">
                            <h2 class="accordion-header" id="demie-faq-h<?php echo esc_attr($demie_i); ?>">
                                <button class="accordion-button<?php echo $demie_open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#demie-faq-c<?php echo esc_attr($demie_i); ?>" aria-expanded="<?php echo $demie_open ? 'true' : 'false'; ?>">
                                    <?php echo esc_html($demie_faq[0]); ?>
                                </button>
                            </h2>
                            <div id="demie-faq-c<?php echo esc_attr($demie_i); ?>" class="accordion-collapse collapse<?php echo $demie_open ? ' show' : ''; ?>" aria-labelledby="demie-faq-h<?php echo esc_attr($demie_i); ?>" data-bs-parent="#accordionFaq">
                                <div class="accordion-body">
                                    <?php echo esc_html($demie_faq[1]); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact strip -->
<section class="bg-dark-200 pd-bottom-80">
    <div class="container">
        <?php demie_render_contact_form(); ?>
    </div>
</section>

<?php get_footer(); ?>
