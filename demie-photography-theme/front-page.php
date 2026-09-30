<?php

get_header();

$services_url = demie_page_url('services');
$gallery_url  = demie_page_url('gallery');
$about_url    = demie_page_url('about-us');
$contact_url  = demie_page_url('contact');
$blog_url     = demie_page_url('blog');

$demie_slides       = demie_get_slides();
$demie_services_all = demie_get_services();
$demie_projects     = demie_get_portfolio(6);
$demie_testimonials = demie_get_testimonials();
?>

<!-- Slider Section -->
<section class="wptb-slider style2">
    <div class="swiper-container wptb-swiper-slider-two">
        <?php if ($demie_slides) : ?>
        <!-- swiper slides -->
        <div class="swiper-wrapper">
            <?php foreach ($demie_slides as $demie_i => $demie_slide) :
                $demie_img      = get_the_post_thumbnail_url($demie_slide, 'full');
                $demie_bg       = $demie_img ? $demie_img : DEMIE_URI . '/assets/img/slider/' . ($demie_i + 4) . '.jpg';
                $demie_subtitle = get_post_meta($demie_slide->ID, '_demie_subtitle', true);
                ?>
                <!-- Slide Item -->
                <div class="swiper-slide">
                    <div class="wptb-slider--item">
                        <div class="wptb-slider--image" style="background-image: url('<?php echo esc_url($demie_bg); ?>');"></div>
                        <div class="wptb-slider--inner">
                            <!-- Layer Image -->
                            <div class="wptb-item-layer wptb-item-layer-one">
                                <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/slider/layer-3.png'); ?>" alt="img">
                            </div>
                            <div class="wptb-heading">
                                <div class="wptb-item--inner">
                                    <h1 class="wptb-item--title"><?php echo esc_html(get_the_title($demie_slide) ?: __('Demie Photography', 'demie-photography')); ?></h1>
                                    <h6 class="wptb-item--subtitle"><?php echo esc_html($demie_subtitle); ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Slide Item -->
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- Left Pane -->
    <div class="wptb-left-pane justify-content-center">
        <div class="logo">
            <h6><?php esc_html_e('Our Works', 'demie-photography'); ?></h6>
        </div>
    </div>

    <!-- Right Pane -->
    <div class="wptb-right-pane">
        <div class="social-box style-oval">
            <?php demie_render_social_box('labels'); ?>
        </div>
    </div>

    <!-- Bottom Pane -->
    <div class="wptb-bottom-pane justify-content-center">
        <!-- pagination dots -->
        <div class="wptb-swiper-dots style2">
            <div class="swiper-pagination"></div>
        </div>

        <!-- Swiper Navigation -->
        <div class="wptb-swiper-navigation style3">
            <div class="wptb-swiper-arrow swiper-button-prev"></div>
            <div class="wptb-swiper-arrow swiper-button-next"></div>
        </div>
    </div>
</section>

<!-- About Demie Photography -->
<section class="wptb-about-two">
    <div class="container">
        <!-- Services -->
        <div class="pd-bottom-100">
            <div class="row">
                <?php
                $demie_home_services = array_slice($demie_services_all, 0, 4);
                foreach ($demie_home_services as $demie_i => $demie_service) :
                    $demie_icon = get_the_post_thumbnail_url($demie_service, 'full');
                    ?>
                    <div class="col-md-3 wow fadeInLeft">
                        <div class="wptb-icon-box6 mb-md-0<?php echo 0 === $demie_i ? ' active highlight' : ''; ?>">
                            <div class="wptb-item--inner">
                                <div class="wptb-item--icon">
                                    <img src="<?php echo esc_url($demie_icon ? $demie_icon : DEMIE_URI . '/assets/img/services/icon-' . ($demie_i + 1) . '.svg'); ?>" alt="img">
                                </div>
                                <div class="wptb-item--holder">
                                    <h4 class="wptb-item--title"><a href="<?php echo esc_url($services_url); ?>"><?php echo esc_html(get_the_title($demie_service)); ?></a></h4>
                                    <p class="wptb-item--description"><?php echo esc_html(demie_service_short($demie_service)); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="wptb-heading">
            <div class="wptb-item--inner">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <h1 class="wptb-item--title"><?php demie_heading_h1('_demie_h_about_l1', '_demie_h_about_l2', '_demie_h_about_l3', __('Demie Photography captures', 'demie-photography'), __('All of Your', 'demie-photography'), __('beautiful memories', 'demie-photography')); ?></h1>
                    </div>
                    <div class="col-lg-5 text-lg-end">
                        <div class="wptb-item--button">
                            <a href="<?php echo esc_url($contact_url); ?>" class="btn btn-two creative text-uppercase">
                                <span class="btn-wrap">
                                    <span class="text-first"><?php esc_html_e('Book us Now', 'demie-photography'); ?></span>
                                    <span class="text-second"><i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i></span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="wptb-image-single wow fadeInUp">
                    <div class="wptb-item--inner">
                        <div class="wptb-item--image position-relative">
                            <img src="<?php echo esc_url(demie_image_url('_demie_img_about', 'more/7.png')); ?>" alt="img">

                            <div class="wptb-item--button round-button">
                                <a class="btn btn-two" href="<?php echo esc_url($about_url); ?>">
                                    <span class="btn-wrap">
                                        <span class="text-first"><?php esc_html_e('Explore Us', 'demie-photography'); ?></span>
                                        <span class="text-second"> <i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i> </span>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="wptb-item-layer wptb-item-layer-one both-version">
                        <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/light-2.png'); ?>" alt="img">
                        <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/light-2-light.png'); ?>" alt="img">
                    </div>
                </div>
            </div>

            <div class="col-md-6 ps-md-5 mt-4 mt-md-0">
                <div class="wptb-about--text ps-md-5">
                    <h3><?php echo esc_html(demie_current_meta('_demie_about_heading', __('About Demie Photography', 'demie-photography'))); ?></h3>
                    <p class="wptb-about--text-one"><?php echo esc_html(demie_current_meta('_demie_about_p1', __('Demie Photography is a photography studio based in Chilomoni, Blantyre, serving couples, families and brands across Malawi.', 'demie-photography'))); ?></p>
                    <p><?php echo esc_html(demie_current_meta('_demie_about_p2', __('From weddings and portraits to events and drone cinematography, our team captures the moments that matter with care, creativity and a personal touch. Hire Demie Photography for your next event.', 'demie-photography'))); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="wptb-item-layer wptb-item-layer-two">
        <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-5.png'); ?>" alt="img">
    </div>
    <div class="wptb-item-layer wptb-item-layer-three">
        <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-4.png'); ?>" alt="img">
    </div>
</section>

<!-- Our Gallery -->
<section class="wptb-project">
    <div class="container">
        <div class="wptb-heading">
            <div class="wptb-item--inner text-center">
                <h1 class="wptb-item--title"><?php demie_heading_h1('_demie_h_portfolio_l1', '_demie_h_portfolio_l2', '_demie_h_portfolio_l3', __('Demie Photography captures', 'demie-photography'), __('All of Your', 'demie-photography'), __('beautiful memories', 'demie-photography')); ?></h1>
            </div>
        </div>

        <?php if (function_exists('demie_g_render_gallery')) : ?>
            <?php
            // Use a dedicated "homepage" gallery (slug: homepage)
            echo demie_g_render_gallery([
                'slug'       => 'homepage',
                'layout'     => 'masonry',
                'per_page'   => 6,
                'pagination' => 'none',
            ]);
            ?>
        <?php else : ?>
            <!-- Fallback to portfolio if gallery plugin inactive -->
            <?php if ($demie_projects) : ?>
            <div class="effect-gradient has-radius">
                <div class="grid gutter-10 clearfix">
                    <div class="grid-sizer"></div>
                    <div class="row">
                        <?php
                        $demie_spans = ['col-md-4', 'col-md-4', 'col-md-4', 'col-md-8', 'col-md-8', 'col-md-4'];
                        foreach ($demie_projects as $demie_i => $demie_project) :
                            $demie_span   = isset($demie_spans[$demie_i]) ? $demie_spans[$demie_i] : 'col-md-4';
                            $demie_img    = demie_portfolio_img($demie_project, 'full');
                            $demie_src    = $demie_img ? $demie_img : DEMIE_URI . '/assets/img/projects/4/' . (($demie_i % 6) + 1) . '.jpg';
                            $demie_title  = get_the_title($demie_project);
                            ?>
                            <div class="grid-item <?php echo esc_attr($demie_span); ?>">
                                <div class="wptb-item--inner">
                                    <div class="wptb-item--image">
                                        <img src="<?php echo esc_url($demie_src); ?>" alt="<?php echo esc_attr($demie_title); ?>">
                                        <a class="wptb-item--link" href="<?php echo esc_url($demie_src); ?>" data-fancybox="portfolio" data-caption="<?php echo esc_attr($demie_title); ?>"><i class="bi bi-chevron-right"></i></a>
                                    </div>

                                    <div class="wptb-item--holder">
                                        <div class="wptb-item--meta">
                                            <h4><a href="<?php echo esc_url($demie_src); ?>" data-fancybox="portfolio" data-caption="<?php echo esc_attr($demie_title); ?>"><?php echo esc_html($demie_title); ?></a></h4>
                                            <p><?php esc_html_e('By Demie Photography', 'demie-photography'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="wptb-item--button text-center mt-5">
            <a class="btn btn-two text-uppercase" href="<?php echo esc_url($gallery_url); ?>">
                <span class="btn-wrap">
                    <span class="text-first"><?php esc_html_e('See All Gallery', 'demie-photography'); ?></span>
                    <span class="text-second"> <i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i> </span>
                </span>
            </a>
        </div>
    </div>
</section>

<!-- How It Works (booking process) -->
<section class="wptb-agency-experience bg-image pb-xl-0" style="background-image: url('<?php echo esc_url(demie_image_url('_demie_img_exp_bg', 'background/bg-13.jpg')); ?>');">
    <div class="container">

        <div class="row">
            <div class="col-lg-8 mb-5 mb-lg-0">
                <div class="wptb-heading">
                    <div class="wptb-item--inner">
                        <h1 class="wptb-item--title lg"><?php echo esc_html(demie_current_meta('_demie_exp_l1', __('From First Hello', 'demie-photography'))); ?> <br> <span class="text-outline"><?php echo esc_html(demie_current_meta('_demie_exp_l2', __('to Final Gallery', 'demie-photography'))); ?></span></h1>
                        <p class="wptb-item--description"><?php echo esc_html(demie_current_meta('_demie_exp_text', __('No confusing packages or endless back-and-forth. Tell us about your wedding, portrait session or event, and we handle the rest — planning, shooting and editing — so all you have to do is show up and enjoy your moment.', 'demie-photography'))); ?></p>

                        <div class="wptb-agency-experience--item">
                            <span><?php echo esc_html(demie_current_meta('_demie_exp_badge_number', '3')); ?></span> <?php echo esc_html(demie_current_meta('_demie_exp_badge_label', __('Simple Steps', 'demie-photography'))); ?>
                        </div>
                    </div>

                    <div class="wptb-image-single d-none d-xl-block wow fadeInUp">
                        <div class="wptb-item--inner">
                            <div class="wptb-item--image">
                                <img src="<?php echo esc_url(demie_image_url('_demie_img_exp', 'more/3.png')); ?>" alt="img">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 ps-lg-5 mt-5">
                <?php for ($demie_s = 1; $demie_s <= 3; $demie_s++) :
                    $demie_step_title = demie_current_meta("_demie_step{$demie_s}_title");
                    $demie_step_text  = demie_current_meta("_demie_step{$demie_s}_text");
                    if ($demie_step_title === '' && $demie_step_text === '') {
                        continue;
                    }
                    ?>
                    <div class="wptb-counter1 style1 wow skewIn">
                        <div class="wptb-item--inner">
                            <div class="wptb-item--holder d-flex align-items-start">
                                <span class="demie-step--number"><?php echo esc_html($demie_s); ?></span>
                                <div class="demie-step--body">
                                    <h4 class="demie-step--title"><?php echo esc_html($demie_step_title); ?></h4>
                                    <?php if ($demie_step_text !== '') : ?>
                                        <p class="demie-step--text"><?php echo esc_html($demie_step_text); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>

<!-- Testimonial -->
<section class="wptb-testimonial-one testimonial-colored bg-image" style="background-image: url('<?php echo esc_url(demie_image_url('_demie_img_testi_bg', 'background/bg-16.jpg')); ?>');">
    <div class="container">
        <div class="row">
            <div class="col-lg-7">
                <?php if ($demie_testimonials) : ?>
                <div class="swiper-container swiper-testimonial">
                    <!-- swiper slides -->
                    <div class="swiper-wrapper">
                        <?php foreach ($demie_testimonials as $demie_i => $demie_testimonial) :
                            $demie_quote    = get_post_meta($demie_testimonial->ID, '_demie_quote', true);
                            $demie_location = get_post_meta($demie_testimonial->ID, '_demie_location', true);
                            $demie_rating   = get_post_meta($demie_testimonial->ID, '_demie_rating', true);
                            $demie_photo    = get_the_post_thumbnail_url($demie_testimonial, 'thumbnail');
                            ?>
                            <div class="swiper-slide">
                                <div class="wptb-testimonial1">
                                    <div class="wptb-item--inner">
                                        <div class="wptb-item--holder">
                                            <div class="d-flex align-items-center justify-content-between mr-bottom-25">
                                                <div class="wptb-item--meta-rating">
                                                    <?php demie_stars($demie_rating !== '' ? $demie_rating : 5); ?>
                                                </div>

                                                <div class="wptb-item--icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="57" height="45" viewBox="0 0 57 45" fill="none">
                                                        <path d="M51.5137 38.5537C56.8209 32.7938 56.2866 25.3969 56.2697 25.3125V2.8125C56.2697 2.06658 55.9734 1.35121 55.4459 0.823763C54.9185 0.296317 54.2031 0 53.4572 0H36.5822C33.48 0 30.9572 2.52281 30.9572 5.625V25.3125C30.9572 26.0584 31.2535 26.7738 31.781 27.3012C32.3084 27.8287 33.0238 28.125 33.7697 28.125H42.4266C42.3671 29.5155 41.9517 30.8674 41.22 32.0513C39.7913 34.3041 37.0997 35.8425 33.2156 36.6188L30.9572 37.0688V45H33.7697C41.5969 45 47.5678 42.8316 51.5137 38.5537ZM20.5566 38.5537C25.8666 32.7938 25.3294 25.3969 25.3125 25.3125V2.8125C25.3125 2.06658 25.0162 1.35121 24.4887 0.823763C23.9613 0.296317 23.2459 0 22.5 0H5.625C2.52281 0 0 2.52281 0 5.625V25.3125C0 26.0584 0.296316 26.7738 0.823762 27.3012C1.35121 27.8287 2.06658 28.125 2.8125 28.125H11.4694C11.41 29.5155 10.9945 30.8674 10.2628 32.0513C8.83406 34.3041 6.1425 35.8425 2.25844 36.6188L0 37.0688V45H2.8125C10.6397 45 16.6106 42.8316 20.5566 38.5537Z" fill="#B45309"/>
                                                    </svg>
                                                </div>
                                            </div>

                                            <p class="wptb-item--description"> &ldquo;<?php echo esc_html($demie_quote !== '' ? $demie_quote : get_the_title($demie_testimonial)); ?>&rdquo;</p>
                                            <div class="wptb-item--meta">
                                                <div class="wptb-item--image">
                                                    <?php if ($demie_photo) : ?>
                                                        <img src="<?php echo esc_url($demie_photo); ?>" alt="<?php echo esc_attr(get_the_title($demie_testimonial)); ?>">
                                                    <?php else : ?>
                                                        <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/testimonial/' . ($demie_i + 4) . '.jpg'); ?>" alt="<?php echo esc_attr(get_the_title($demie_testimonial)); ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="wptb-item--meta-left">
                                                    <h4 class="wptb-item--title"><?php echo esc_html(get_the_title($demie_testimonial)); ?></h4>
                                                    <h6 class="wptb-item--designation"><?php echo esc_html($demie_location); ?></h6>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Swiper Navigation -->
                    <div class="wptb-swiper-navigation style1">
                        <div class="wptb-swiper-arrow swiper-button-prev"></div>
                        <div class="wptb-swiper-arrow swiper-button-next"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Blog Grid -->
<section class="wptb-blog-grid-one pb-0">
    <div class="container">
        <div class="wptb-heading">
            <div class="wptb-item--inner">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h1 class="wptb-item--title mb-0"><?php demie_heading_h1('_demie_h_blog_l1', '_demie_h_blog_l2', null, __('Our Photography', 'demie-photography'), __('Related Blog', 'demie-photography')); ?></h1>
                    </div>

                    <div class="col-lg-6">
                        <p class="wptb-item--description"><?php echo esc_html(demie_current_meta('_demie_h_blog_desc', __('We are deeply passionate about catching your lovely memories on camera and conveying your love for every moment of life as a whole.', 'demie-photography'))); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="wptb-blog--inner">
            <div class="row">
                <?php
                $demie_blog = new WP_Query([
                    'posts_per_page'      => 3,
                    'ignore_sticky_posts' => true,
                    'no_found_rows'       => true,
                ]);
                if ($demie_blog->have_posts()) :
                    $demie_i = 0;
                    while ($demie_blog->have_posts()) :
                        $demie_blog->the_post();
                        $demie_i++;
                        ?>
                        <div class="col-lg-4 col-sm-6">
                            <div class="wptb-blog-grid1<?php echo 1 === $demie_i ? ' active highlight' : ''; ?> wow fadeInLeft">
                                <div class="wptb-item--inner">
                                    <div class="wptb-item--image">
                                        <a href="<?php the_permalink(); ?>" class="wptb-item-link">
                                            <?php if (has_post_thumbnail()) : ?>
                                                <?php the_post_thumbnail('medium_large'); ?>
                                            <?php else : ?>
                                                <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/blog/' . $demie_i . '.jpg'); ?>" alt="<?php the_title_attribute(); ?>">
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="wptb-item--holder">
                                        <div class="wptb-item--date"><?php echo esc_html(get_the_date()); ?></div>
                                        <h4 class="wptb-item--title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>

                                        <div class="wptb-item--meta">
                                            <div class="wptb-item--author"><?php esc_html_e('By', 'demie-photography'); ?> <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>"><?php the_author(); ?></a></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                    // No posts yet: show the template's placeholder cards.
                    $demie_placeholders = [
                        [__('Beginners guide to start your photography journey', 'demie-photography'), '25 Sep 2023'],
                        [__('Twenty photography tips to make photos amazing', 'demie-photography'), '22 Sep 2023'],
                        [__('Best spots for photography in Malawi', 'demie-photography'), '22 Sep 2023'],
                    ];
                    foreach ($demie_placeholders as $demie_i => $demie_ph) :
                        ?>
                        <div class="col-lg-4 col-sm-6">
                            <div class="wptb-blog-grid1<?php echo 0 === $demie_i ? ' active highlight' : ''; ?> wow fadeInLeft">
                                <div class="wptb-item--inner">
                                    <div class="wptb-item--image">
                                        <a href="<?php echo esc_url($blog_url); ?>" class="wptb-item-link"><img src="<?php echo esc_url(DEMIE_URI . '/assets/img/blog/' . ($demie_i + 1) . '.jpg'); ?>" alt="img"></a>
                                    </div>
                                    <div class="wptb-item--holder">
                                        <div class="wptb-item--date"><?php echo esc_html($demie_ph[1]); ?></div>
                                        <h4 class="wptb-item--title"><a href="<?php echo esc_url($blog_url); ?>"><?php echo esc_html($demie_ph[0]); ?></a></h4>

                                        <div class="wptb-item--meta">
                                            <div class="wptb-item--author"><?php esc_html_e('By', 'demie-photography'); ?> <a href="<?php echo esc_url($blog_url); ?>">Demie</a></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;
                endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Contact -->
<section class="wptb-contact-form style1">
    <div class="wptb-item-layer both-version">
        <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-2.png'); ?>" alt="">
        <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/texture-2-light.png'); ?>" alt="">
    </div>
    <div class="container">
        <div class="wptb-form--wrapper">
            <div class="wptb-heading">
                <div class="wptb-item--inner text-center">
                    <h1 class="wptb-item--title"><?php echo esc_html(demie_current_meta('_demie_h_contact_l1', __('Get In Touch', 'demie-photography'))); ?></h1>
                    <div class="wptb-item--description"><?php echo esc_html(demie_current_meta('_demie_h_contact_desc', __('Contact us for a great photography session & beautiful captured moments', 'demie-photography'))); ?></div>
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
                            <div class="wptb-item--icon"><i class="bi bi-globe"></i></div>
                            <div class="wptb-item--holder">
                                <h3 class="wptb-item--title"><?php esc_html_e('Our Website', 'demie-photography'); ?></h3>
                                <p class="wptb-item--description"><?php echo esc_html(parse_url(demie_get('facebook'), PHP_URL_HOST) ?: demie_get('facebook')); ?></p>
                                <a href="<?php echo esc_url(demie_get('facebook')); ?>" target="_blank" rel="noopener" class="wptb-item--link"><?php esc_html_e('Visit Now', 'demie-photography'); ?></a>
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
                                <a href="<?php echo esc_url(demie_phone_url()); ?>" class="wptb-item--link"><?php esc_html_e('Call Now', 'demie-photography'); ?></a>
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

<!-- Instagram -->
<div class="wptb-instagram--gallery">
    <div class="wptb-item--inner d-flex align-items-center justify-content-center flex-wrap flex-md-nowrap">
        <?php for ($i = 1; $i <= 5; $i++) : ?>
            <div class="wptb-item">
                <div class="wptb-item--image">
                    <img src="<?php echo esc_url(demie_image_url('_demie_img_insta_' . $i, 'instagram/' . $i . '.jpg')); ?>" alt="img">
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <div class="wptb-item--button">
        <a class="btn btn-two" href="<?php echo esc_url(demie_get('instagram') ?: 'https://www.instagram.com/'); ?>" target="_blank" rel="noopener">
            <span class="btn-wrap">
                <span class="text-first"><?php esc_html_e('Follow Us on Instagram', 'demie-photography'); ?></span>
                <span class="text-second"> <i class="bi bi-instagram"></i> <i class="bi bi-instagram"></i> </span>
            </span>
        </a>
    </div>
</div>

<?php get_footer(); ?>
