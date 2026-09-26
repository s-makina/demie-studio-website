<?php
/**
 * Template Name: Services
 * Source: services-1.html
 */

get_header();

get_template_part('template-parts/page-titlebar', null, [
    'title' => get_the_title() ?: __('Our Services', 'demie-photography'),
    'bg'    => 'page-header-bg-6.jpg',
]);
?>

<!-- Our Services -->
<section>
    <div class="container">
        <div class="wptb-heading">
            <div class="wptb-item--inner text-center">
                <h6 class="wptb-item--subtitle"><span>01//</span> <?php esc_html_e('Our Services', 'demie-photography'); ?></h6>
                <h1 class="wptb-item--title"><?php esc_html_e('Demie Photography offers', 'demie-photography'); ?> <span><?php esc_html_e('All of the', 'demie-photography'); ?></span> <br>
                    <?php esc_html_e('services you need', 'demie-photography'); ?></h1>
            </div>
        </div>

        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <?php if (trim(get_the_content())) : ?>
                <div class="entry-content demie-page-content text-center mb-5">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>
        <?php endwhile; endif; ?>

        <div class="row">
            <?php
            $demie_services = [
                ['icon-1.svg', __('Wedding Photography', 'demie-photography'), __('Full-day coverage of your wedding — preparations, ceremony and reception — delivered as a beautifully edited gallery.', 'demie-photography')],
                ['icon-2.svg', __('Drone Cinematography', 'demie-photography'), __('Licensed aerial photography and video for venues, events and landscapes across Malawi.', 'demie-photography')],
                ['icon-3.svg', __('Wedding Cinematography', 'demie-photography'), __('A cinematic film of your day, from the first look to the last dance, edited to music you love.', 'demie-photography')],
                ['icon-4.svg', __('Personal Portfolio Shoot', 'demie-photography'), __('Studio or on-location portrait sessions for individuals, creatives and professionals.', 'demie-photography')],
                ['icon-5.svg', __('Studio Photography', 'demie-photography'), __('Controlled studio light for newborns, families, products and editorial looks.', 'demie-photography')],
                ['icon-6.svg', __('Event Photography', 'demie-photography'), __('Corporate events, parties and ceremonies covered discreetly and delivered fast.', 'demie-photography')],
            ];
            foreach ($demie_services as $demie_i => $demie_service) :
                $demie_highlight = 0 === $demie_i;
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="wptb-icon-box6<?php echo $demie_highlight ? ' active highlight' : ''; ?> mb-md-0">
                        <div class="wptb-item--inner">
                            <div class="wptb-item--icon">
                                <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/services/' . $demie_service[0]); ?>" alt="img">
                            </div>
                            <div class="wptb-item--holder">
                                <h4 class="wptb-item--title"><?php echo esc_html($demie_service[1]); ?></h4>
                                <p class="wptb-item--description"><?php echo esc_html($demie_service[2]); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="bg-dark-200 pd-bottom-80">
    <div class="container">
        <?php demie_render_contact_form(); ?>
    </div>
</section>

<?php get_footer(); ?>
