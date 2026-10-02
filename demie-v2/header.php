<?php ?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="scroll-smooth">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class('antialiased'); ?>>
<?php wp_body_open(); ?>

<header id="mainNav" class="fixed top-0 left-0 w-full z-50 transition-all duration-500 px-6 md:px-14 flex items-center justify-between text-white py-6 border-b border-white/10">
  <a class="group flex flex-col items-start focus:outline-none" href="<?php echo esc_url(home_url('/')); ?>">
    <?php demie_logo_wordmark(); ?>
    <span class="text-[9px] tracking-widest2 uppercase text-white/70 font-sans -mt-1 group-hover:text-white transition-colors"><?php esc_html_e('Photographers & Filmmakers', 'demie-v2'); ?></span>
  </a>
  <nav class="hidden md:flex items-center space-x-10 text-xs uppercase tracking-luxury font-medium text-white/90" aria-label="<?php esc_attr_e('Primary', 'demie-v2'); ?>">
    <?php demie_render_primary_menu(); ?>
  </nav>
  <div class="flex items-center space-x-5">
    <a class="btn-luxury hidden sm:inline-flex items-center justify-center px-6 py-2.5 text-[11px] uppercase tracking-widest font-medium border border-white/80 text-white hover:bg-white hover:text-brand-charcoal hover:border-white transition-all duration-300" href="<?php echo esc_url(demie_page_url('contact')); ?>">
      <?php esc_html_e('Book Your Date', 'demie-v2'); ?>
    </a>
    <button aria-label="<?php esc_attr_e('Toggle navigation menu', 'demie-v2'); ?>" class="md:hidden text-white hover:text-brand-champagne p-2 focus:outline-none" id="mobileMenuBtn">
      <svg class="w-6 h-6" fill="none" id="menuIcon" stroke="currentColor" viewBox="0 0 24 24"><path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
      <svg class="w-6 h-6 hidden" fill="none" id="closeIcon" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
    </button>
  </div>
</header>

<div id="mobileMenu" class="fixed inset-0 bg-brand-deep/95 z-40 flex flex-col justify-center items-center text-center opacity-0 pointer-events-none transition-opacity duration-300 md:hidden px-8">
  <div class="space-y-6 font-serif text-3xl text-brand-bone tracking-wide">
    <?php demie_v2_mobile_menu(); ?>
  </div>
  <div class="mt-10 pt-8 border-t border-white/10 w-full max-w-xs flex flex-col items-center">
    <a class="mobile-nav-link w-full text-center px-8 py-3 bg-brand-champagne text-brand-charcoal text-xs uppercase tracking-widest font-semibold hover:bg-white transition-colors" href="<?php echo esc_url(demie_page_url('contact')); ?>">
      <?php esc_html_e('Book Your Date', 'demie-v2'); ?>
    </a>
    <p class="text-[10px] tracking-widest uppercase text-white/50 mt-4"><?php esc_html_e('Blantyre · Lilongwe · Worldwide', 'demie-v2'); ?></p>
  </div>
</div>

<main class="wrapper">
