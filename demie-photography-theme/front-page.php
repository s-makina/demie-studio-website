<?php

get_header();

$services_url = demie_page_url('services');
$gallery_url  = demie_page_url('gallery');
$about_url    = demie_page_url('about-us');
$contact_url  = demie_page_url('contact');
$blog_url     = demie_page_url('blog');
?>

<!-- Slider Section -->
<section class="wptb-slider style2">
    <div class="swiper-container wptb-swiper-slider-two">
        <!-- swiper slides -->
        <div class="swiper-wrapper">
            <!-- Slide Item -->
            <div class="swiper-slide">
                <div class="wptb-slider--item">
                    <div class="wptb-slider--image" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/slider/4.jpg'); ?>');"></div>
                    <div class="wptb-slider--inner">
                        <!-- Layer Image -->
                        <div class="wptb-item-layer wptb-item-layer-one">
                            <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/slider/layer-3.png'); ?>" alt="img">
                        </div>
                        <div class="wptb-heading">
                            <div class="wptb-item--inner">
                                <h1 class="wptb-item--title"><?php esc_html_e('Demie Photography', 'demie-photography'); ?></h1>
                                <h6 class="wptb-item--subtitle"><?php esc_html_e('Weddings', 'demie-photography'); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Slide Item -->

            <!-- Slide Item -->
            <div class="swiper-slide">
                <div class="wptb-slider--item">
                    <div class="wptb-slider--image" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/slider/5.jpg'); ?>');"></div>
                    <div class="wptb-slider--inner">
                        <!-- Layer Image -->
                        <div class="wptb-item-layer wptb-item-layer-one">
                            <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/slider/layer-3.png'); ?>" alt="img">
                        </div>
                        <div class="wptb-heading">
                            <div class="wptb-item--inner">
                                <h1 class="wptb-item--title"><?php esc_html_e('Demie Photography', 'demie-photography'); ?></h1>
                                <h6 class="wptb-item--subtitle"><?php esc_html_e('Portraits', 'demie-photography'); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Slide Item -->

            <!-- Slide Item -->
            <div class="swiper-slide">
                <div class="wptb-slider--item">
                    <div class="wptb-slider--image" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/slider/6.jpg'); ?>');"></div>
                    <div class="wptb-slider--inner">
                        <!-- Layer Image -->
                        <div class="wptb-item-layer wptb-item-layer-one">
                            <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/slider/layer-3.png'); ?>" alt="img">
                        </div>
                        <div class="wptb-heading">
                            <div class="wptb-item--inner">
                                <h1 class="wptb-item--title"><?php esc_html_e('Demie Photography', 'demie-photography'); ?></h1>
                                <h6 class="wptb-item--subtitle"><?php esc_html_e('Events', 'demie-photography'); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Slide Item -->
        </div>
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
            <ul>
                <li><a href="https://www.facebook.com/people/Demie-photography/100063646432000/"><?php esc_html_e('FB', 'demie-photography'); ?></a></li>
                <li><a href="https://www.instagram.com/"><?php esc_html_e('IG', 'demie-photography'); ?></a></li>
                <li><a href="https://www.youtube.com/"><?php esc_html_e('YT', 'demie-photography'); ?></a></li>
                <li><a href="https://www.dribbble.com/"><?php esc_html_e('DR', 'demie-photography'); ?></a></li>
            </ul>
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
                $demie_home_services = [
                    ['icon-1.svg', __('Wedding Photography', 'demie-photography'), __('Timeless wedding photography that tells the story of your day, from preparations to the last dance.', 'demie-photography'), true],
                    ['icon-2.svg', __('Drone Cinematography', 'demie-photography'), __('Stunning aerial views of venues, ceremonies and landscapes across Malawi.', 'demie-photography'), false],
                    ['icon-3.svg', __('Wedding Cinematography', 'demie-photography'), __('Cinematic films that let you relive every vow, speech and celebration.', 'demie-photography'), false],
                    ['icon-4.svg', __('Personal Portfolio Shoot', 'demie-photography'), __('Studio and on-location portraits, editorial looks and personal branding sessions.', 'demie-photography'), false],
                ];
                foreach ($demie_home_services as $demie_service) :
                    ?>
                    <div class="col-md-3 wow fadeInLeft">
                        <div class="wptb-icon-box6 mb-md-0<?php echo !empty($demie_service[3]) ? ' active highlight' : ''; ?>">
                            <div class="wptb-item--inner">
                                <div class="wptb-item--icon">
                                    <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/services/' . $demie_service[0]); ?>" alt="img">
                                </div>
                                <div class="wptb-item--holder">
                                    <h4 class="wptb-item--title"><a href="<?php echo esc_url($services_url); ?>"><?php echo esc_html($demie_service[1]); ?></a></h4>
                                    <p class="wptb-item--description"><?php echo esc_html($demie_service[2]); ?></p>
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
                        <h6 class="wptb-item--subtitle"><span>02 //</span> <?php esc_html_e('About Agency', 'demie-photography'); ?></h6>
                        <h1 class="wptb-item--title"><?php esc_html_e('Demie Photography captures', 'demie-photography'); ?> <span><?php esc_html_e('All of Your', 'demie-photography'); ?></span> <br> <?php esc_html_e('beautiful memories', 'demie-photography'); ?></h1>
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
                            <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/7.png'); ?>" alt="img">

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
                    <h3><?php esc_html_e('About Demie Photography', 'demie-photography'); ?></h3>
                    <p class="wptb-about--text-one"><?php esc_html_e('Demie Photography is a photography studio based in Chilomoni, Blantyre, serving couples, families and brands across Malawi.', 'demie-photography'); ?></p>
                    <p><?php esc_html_e('From weddings and portraits to events and drone cinematography, our team captures the moments that matter with care, creativity and a personal touch. Hire Demie Photography for your next event.', 'demie-photography'); ?></p>
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

<!-- Our Portfolio -->
<section class="wptb-project">
    <div class="container">
        <div class="wptb-heading">
            <div class="wptb-item--inner text-center">
                <h6 class="wptb-item--subtitle"><span>03//</span> <?php esc_html_e('Our Portfolio', 'demie-photography'); ?></h6>
                <h1 class="wptb-item--title"> <?php esc_html_e('Demie Photography captures', 'demie-photography'); ?> <span><?php esc_html_e('All of Your', 'demie-photography'); ?></span> <br>
                    <?php esc_html_e('beautiful memories', 'demie-photography'); ?></h1>
            </div>
        </div>

        <div class="effect-gradient has-radius">
            <div class="grid gutter-10 clearfix">
                <div class="grid-sizer"></div>
                <div class="row">
                    <?php
                    $demie_projects = [
                        [1, 'col-md-4', __('Bright Boho Sunshine', 'demie-photography')],
                        [2, 'col-md-4', __('Golden Hour Sessions', 'demie-photography')],
                        [3, 'col-md-4', __('Studio Portraits', 'demie-photography')],
                        [4, 'col-md-8', __('Weddings & Celebrations', 'demie-photography')],
                        [5, 'col-md-8', __('Events & Gatherings', 'demie-photography')],
                        [6, 'col-md-4', __('Faces of Blantyre', 'demie-photography')],
                    ];
                    foreach ($demie_projects as $demie_project) :
                        ?>
                        <div class="grid-item <?php echo esc_attr($demie_project[1]); ?>">
                            <div class="wptb-item--inner">
                                <div class="wptb-item--image">
                                    <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/projects/4/' . $demie_project[0] . '.jpg'); ?>" alt="img">
                                    <a class="wptb-item--link" href="<?php echo esc_url($gallery_url); ?>"><i class="bi bi-chevron-right"></i></a>
                                </div>

                                <div class="wptb-item--holder">
                                    <div class="wptb-item--meta">
                                        <h4><a href="<?php echo esc_url($gallery_url); ?>"><?php echo esc_html($demie_project[2]); ?></a></h4>
                                        <p><?php esc_html_e('By Demie Photography', 'demie-photography'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="wptb-item--button text-center mt-5">
            <a class="btn btn-two text-uppercase" href="<?php echo esc_url($gallery_url); ?>">
                <span class="btn-wrap">
                    <span class="text-first"><?php esc_html_e('See All Projects', 'demie-photography'); ?></span>
                    <span class="text-second"> <i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i> </span>
                </span>
            </a>
        </div>
    </div>
</section>

<!-- Agency Experience -->
<section class="wptb-agency-experience bg-image pb-xl-0" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/background/bg-13.jpg'); ?>');">
    <div class="container">

        <div class="row">
            <div class="col-lg-8 mb-5 mb-lg-0">
                <div class="wptb-heading">
                    <div class="wptb-item--inner">
                        <h1 class="wptb-item--title lg mb-5"><?php esc_html_e('20 Amazing', 'demie-photography'); ?> <br> <span class="text-outline"><?php esc_html_e('Photographers', 'demie-photography'); ?></span></h1>
                        <p class="wptb-item--description"><?php esc_html_e('The talent at Demie Photography runs wide and deep. From weddings to events and drone work, our team members are some of the finest photographers in the industry, capturing beautiful memories across Malawi.', 'demie-photography'); ?></p>

                        <div class="wptb-agency-experience--item">
                            <span>15+</span> <?php esc_html_e('Years Experience', 'demie-photography'); ?>
                        </div>
                    </div>

                    <div class="wptb-image-single d-none d-xl-block wow fadeInUp">
                        <div class="wptb-item--inner">
                            <div class="wptb-item--image">
                                <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/3.png'); ?>" alt="img">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 ps-lg-5 mt-5">
                <div class="wptb-counter1 style1 mr-bottom-100 wow skewIn">
                    <div class="wptb-item--inner">
                        <div class="wptb-item--holder d-flex align-items-center">
                            <div class="wptb-item--value"><span class="odometer" data-count="50"></span><span class="suffix">+</span></div>
                            <div class="wptb-item--text"><?php esc_html_e('Professional Cameras', 'demie-photography'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="wptb-counter1 style1 mr-bottom-100 wow skewIn">
                    <div class="wptb-item--inner">
                        <div class="wptb-item--holder d-flex align-items-center">
                            <div class="wptb-item--value"><span class="odometer" data-count="90"></span><span class="suffix">+</span></div>
                            <div class="wptb-item--text"><?php esc_html_e('Photography Props', 'demie-photography'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="wptb-counter1 style1 wow skewIn">
                    <div class="wptb-item--inner">
                        <div class="wptb-item--holder d-flex align-items-center">
                            <div class="wptb-item--value"><span class="odometer" data-count="300"></span><span class="suffix"></span></div>
                            <div class="wptb-item--text"><?php esc_html_e('Events Covered', 'demie-photography'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonial -->
<section class="wptb-testimonial-one testimonial-colored bg-image" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/background/bg-16.jpg'); ?>');">
    <div class="container">
        <div class="row">
            <div class="col-lg-7">
                <div class="swiper-container swiper-testimonial">
                    <!-- swiper slides -->
                    <div class="swiper-wrapper">
                        <?php
                        $demie_testimonials = [
                            [__('I had an amazing photography session with team Demie Photography, highly recommended. They have an amazing atmosphere in their studio. I would love to visit again.', 'demie-photography'), 'Rachel Jackson', __('Blantyre', 'demie-photography'), '4.jpg'],
                            [__('Demie captured our wedding beautifully. Every special moment of the day is there in the photos — we could not be happier with the results.', 'demie-photography'), 'Helen Jordan', __('Lilongwe', 'demie-photography'), '5.jpg'],
                            [__('Professional, friendly and creative. Our family portraits came out stunning and the whole session was so much fun. Thank you Demie Photography!', 'demie-photography'), 'Chikondi Banda', __('Chilomoni', 'demie-photography'), '6.jpg'],
                        ];
                        foreach ($demie_testimonials as $demie_testimonial) :
                            ?>
                            <div class="swiper-slide">
                                <div class="wptb-testimonial1">
                                    <div class="wptb-item--inner">
                                        <div class="wptb-item--holder">
                                            <div class="d-flex align-items-center justify-content-between mr-bottom-25">
                                                <div class="wptb-item--meta-rating">
                                                    <i class="bi bi-star-fill"></i>
                                                    <i class="bi bi-star-fill"></i>
                                                    <i class="bi bi-star-fill"></i>
                                                    <i class="bi bi-star-fill"></i>
                                                    <i class="bi bi-star-fill"></i>
                                                </div>

                                                <div class="wptb-item--icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="57" height="45" viewBox="0 0 57 45" fill="none">
                                                        <path d="M51.5137 38.5537C56.8209 32.7938 56.2866 25.3969 56.2697 25.3125V2.8125C56.2697 2.06658 55.9734 1.35121 55.4459 0.823763C54.9185 0.296317 54.2031 0 53.4572 0H36.5822C33.48 0 30.9572 2.52281 30.9572 5.625V25.3125C30.9572 26.0584 31.2535 26.7738 31.781 27.3012C32.3084 27.8287 33.0238 28.125 33.7697 28.125H42.4266C42.3671 29.5155 41.9517 30.8674 41.22 32.0513C39.7913 34.3041 37.0997 35.8425 33.2156 36.6188L30.9572 37.0688V45H33.7697C41.5969 45 47.5678 42.8316 51.5137 38.5537ZM20.5566 38.5537C25.8666 32.7938 25.3294 25.3969 25.3125 25.3125V2.8125C25.3125 2.06658 25.0162 1.35121 24.4887 0.823763C23.9613 0.296317 23.2459 0 22.5 0H5.625C2.52281 0 0 2.52281 0 5.625V25.3125C0 26.0584 0.296316 26.7738 0.823762 27.3012C1.35121 27.8287 2.06658 28.125 2.8125 28.125H11.4694C11.41 29.5155 10.9945 30.8674 10.2628 32.0513C8.83406 34.3041 6.1425 35.8425 2.25844 36.6188L0 37.0688V45H2.8125C10.6397 45 16.6106 42.8316 20.5566 38.5537Z" fill="#B45309"/>
                                                    </svg>
                                                </div>
                                            </div>

                                            <p class="wptb-item--description"> &ldquo;<?php echo esc_html($demie_testimonial[0]); ?>&rdquo;</p>
                                            <div class="wptb-item--meta">
                                                <div class="wptb-item--image">
                                                    <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/testimonial/' . $demie_testimonial[3]); ?>" alt="img">
                                                </div>
                                                <div class="wptb-item--meta-left">
                                                    <h4 class="wptb-item--title"><?php echo esc_html($demie_testimonial[1]); ?></h4>
                                                    <h6 class="wptb-item--designation"><?php echo esc_html($demie_testimonial[2]); ?></h6>
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
                        <h6 class="wptb-item--subtitle"><span>04 //</span> <?php esc_html_e('Latest News', 'demie-photography'); ?></h6>
                        <h1 class="wptb-item--title mb-0"><?php esc_html_e('Our Photography', 'demie-photography'); ?><br>
                            <span><?php esc_html_e('Related Blog', 'demie-photography'); ?></span></h1>
                    </div>

                    <div class="col-lg-6">
                        <p class="wptb-item--description"><?php esc_html_e('We are deeply passionate about', 'demie-photography'); ?> <span><?php esc_html_e('catching your lovely memories on camera', 'demie-photography'); ?></span>
                            <?php esc_html_e('and conveying your love for every moment of life as a whole.', 'demie-photography'); ?></p>
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
                    <h1 class="wptb-item--title"> <?php esc_html_e('Get In Touch', 'demie-photography'); ?></h1>
                    <div class="wptb-item--description"> <?php esc_html_e('Contact us for a great photography session & beautiful captured moments', 'demie-photography'); ?> </div>
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
                                <p class="wptb-item--description">facebook.com/Demie-photography</p>
                                <a href="https://www.facebook.com/people/Demie-photography/100063646432000/" class="wptb-item--link"><?php esc_html_e('Visit Now', 'demie-photography'); ?></a>
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
                                <p class="wptb-item--description"><?php esc_html_e('Chilomoni, Blantyre, Malawi', 'demie-photography'); ?></p>
                                <a href="https://www.google.com/maps/search/?api=1&query=Chilomoni%2C%20Blantyre%2C%20Malawi" target="_blank" rel="noopener" class="wptb-item--link"><?php esc_html_e('View Map', 'demie-photography'); ?></a>
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
                    <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/instagram/' . $i . '.jpg'); ?>" alt="img">
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <div class="wptb-item--button">
        <a class="btn btn-two" href="https://www.instagram.com/" target="_blank" rel="noopener">
            <span class="btn-wrap">
                <span class="text-first"><?php esc_html_e('Follow Us on Instagram', 'demie-photography'); ?></span>
                <span class="text-second"> <i class="bi bi-instagram"></i> <i class="bi bi-instagram"></i> </span>
            </span>
        </a>
    </div>
</div>

<?php get_footer(); ?>
