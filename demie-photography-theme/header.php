<?php ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- Preloader -->
<div id="preloader">
    <div class="preloader-inner">
        <div class="spinner">
            <span class="preloader-wordmark">Demie<span class="logo-sub"> Photography</span></span>
            <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/preloader-wheel.svg'); ?>" alt="img" class="wheel">
        </div>
    </div>
</div>

<!-- pointer start -->
<div class="pointer bnz-pointer" id="bnz-pointer"></div>

<!-- Main Header -->
<header class="header color-fixed">
    <!-- Lower Bar -->
    <div class="header-inner">
        <div class="container-fluid pe-0">
            <div class="d-flex align-items-center justify-content-between">
                <!-- Left Part -->
                <div class="header_left_part d-flex align-items-center">
                    <div class="logo">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="light_logo logo-wordmark"><?php demie_logo_wordmark(); ?></a>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="dark_logo logo-wordmark"><?php demie_logo_wordmark(); ?></a>
                    </div>
                </div>

                <!-- Center Part -->
                <div class="header_center_part d-none d-xl-block">
                    <div class="mainnav">
                        <?php demie_render_primary_menu(); ?>
                    </div>
                </div>

                <!-- Right Part -->
                <div class="header_right_part d-flex align-items-center">
                    <div class="aside_open wptb-element">
                        <div class="aside-open--inner">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>

                    <div class="header_search wptb-element">
                        <a href="#" class="modal_search_icon" data-bs-toggle="modal" data-bs-target="#modalSearch"><i class="bi bi-search"></i></a>
                    </div>

                    <button type="button" class="mr_menu_toggle wptb-element d-xl-none">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- End Main Header -->

<!-- Mobile Responsive Menu -->
<div class="mr_menu" data-lenis-prevent>
    <button type="button" class="mr_menu_close"><i class="bi bi-x-lg"></i></button>
    <div class="logo"></div> <!-- Keep this div empty. Logo will come here by JavaScript -->

    <h6>Menu</h6>
    <div class="mr_navmenu">
        <?php demie_render_primary_menu(); ?>
    </div>

    <h6>Contact Us</h6>
    <div class="wptb-icon-box1 style2">
        <div class="wptb-item--inner flex-start">
            <div class="wptb-item--icon"><i class="bi bi-envelope"></i></div>
            <div class="wptb-item--holder">
                <p class="wptb-item--description"><a href="mailto:<?php echo esc_attr(demie_email()); ?>"><?php echo esc_html(demie_email()); ?></a></p>
            </div>
        </div>
    </div>

    <div class="wptb-icon-box1 style2">
        <div class="wptb-item--inner flex-start">
            <div class="wptb-item--icon"><i class="bi bi-geo-alt"></i></div>
            <div class="wptb-item--holder">
                <p class="wptb-item--description"><a href="<?php echo esc_url(demie_page_url('contact')); ?>"><?php esc_html_e('Chilomoni, Blantyre, Malawi', 'demie-photography'); ?></a></p>
            </div>
        </div>
    </div>

    <div class="wptb-icon-box1 style2">
        <div class="wptb-item--inner flex-start">
            <div class="wptb-item--icon"><i class="bi bi-envelope"></i></div>
            <div class="wptb-item--holder">
                <p class="wptb-item--description"><a href="<?php echo esc_url(demie_phone_url()); ?>"><?php echo esc_html(demie_phone()); ?></a></p>
            </div>
        </div>
    </div>

    <h6>Find Our Page</h6>
    <div class="social-box">
        <ul>
            <li><a href="https://www.facebook.com/people/Demie-photography/100063646432000/"><i class="bi bi-facebook"></i></a></li>
            <li><a href="https://www.instagram.com/"><i class="bi bi-instagram"></i></a></li>
            <li><a href="https://www.linkedin.com/"><i class="bi bi-linkedin"></i></a></li>
            <li><a href="https://www.behance.com/"><i class="bi bi-behance"></i></a></li>
            <li><a href="https://www.youtube.com/"><i class="bi bi-youtube"></i></a></li>
        </ul>
    </div>
</div>

<div class="aside_info_wrapper" data-lenis-prevent>
    <button class="aside_close">Close <i class="bi bi-x-lg"></i></button>

    <div class="aside_logo logo">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="light_logo logo-wordmark"><?php demie_logo_wordmark(); ?></a>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="dark_logo logo-wordmark"><?php demie_logo_wordmark(); ?></a>
    </div>

    <div class="aside_info_inner">
        <h6>// Instagram</h6>
        <div class="insta-logo">
            <i class="bi bi-instagram"></i> demie_photography
        </div>
        <div class="wptb-instagram--gallery">
            <div class="wptb-item--inner d-flex align-items-center justify-content-center flex-wrap">
                <?php
                for ($i = 6; $i <= 11; $i++) :
                    ?>
                    <div class="wptb-item">
                        <div class="wptb-item--image">
                            <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/instagram/' . $i . '.jpg'); ?>" alt="img">
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="wptb-icon-box1 style2">
            <div class="wptb-item--inner flex-start">
                <div class="wptb-item--icon"><i class="bi bi-envelope"></i></div>
                <div class="wptb-item--holder">
                    <p class="wptb-item--description"><a href="mailto:<?php echo esc_attr(demie_email()); ?>"><?php echo esc_html(demie_email()); ?></a></p>
                </div>
            </div>
        </div>

        <div class="wptb-icon-box1 style2">
            <div class="wptb-item--inner flex-start">
                <div class="wptb-item--icon"><i class="bi bi-geo-alt"></i></div>
                <div class="wptb-item--holder">
                    <p class="wptb-item--description"><a href="<?php echo esc_url(demie_page_url('contact')); ?>"><?php esc_html_e('Chilomoni, Blantyre, Malawi', 'demie-photography'); ?></a></p>
                </div>
            </div>
        </div>

        <div class="wptb-icon-box1 style2">
            <div class="wptb-item--inner flex-start">
                <div class="wptb-item--icon"><i class="bi bi-envelope"></i></div>
                <div class="wptb-item--holder">
                    <p class="wptb-item--description"><a href="<?php echo esc_url(demie_phone_url()); ?>"><?php echo esc_html(demie_phone()); ?></a></p>
                </div>
            </div>
        </div>

        <h6>// Follow Us</h6>
        <div class="social-box style-square">
            <ul>
                <li><a href="https://www.facebook.com/people/Demie-photography/100063646432000/"><i class="bi bi-facebook"></i></a></li>
                <li><a href="https://www.instagram.com/"><i class="bi bi-instagram"></i></a></li>
                <li><a href="https://www.linkedin.com/"><i class="bi bi-linkedin"></i></a></li>
                <li><a href="https://www.behance.com/"><i class="bi bi-behance"></i></a></li>
                <li><a href="https://www.youtube.com/"><i class="bi bi-youtube"></i></a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Modal Search -->
<div class="search-modal">
    <div class="modal fade" id="modalSearch">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="search_overlay">
                    <form class="credential-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                        <div class="form-group">
                            <input type="text" name="s" class="keyword form-control" placeholder="<?php esc_attr_e('Search Here', 'demie-photography'); ?>">
                        </div>
                        <button type="submit" class="btn-search">
                            <span class="text-first"> <i class="bi bi-arrow-right"></i> </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Wrapper-->
<main class="wrapper">
