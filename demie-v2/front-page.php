<?php
// Demie v2 homepage — assembles v2/code.html sections with dynamic data.
get_header();

get_template_part('template-parts/hero');
get_template_part('template-parts/statement');
get_template_part('template-parts/gallery');
get_template_part('template-parts/featured-story');
get_template_part('template-parts/services');
get_template_part('template-parts/why');
get_template_part('template-parts/agency');
get_template_part('template-parts/testimonials');
get_template_part('template-parts/booking');

get_footer();
