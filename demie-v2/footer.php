</main>

<footer class="bg-brand-deep text-brand-stone py-20 px-6 md:px-14 border-t border-white/10" id="contact">
  <div class="max-w-7xl mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-12 mb-16">
      <div class="md:col-span-4">
        <a class="inline-block mb-4" href="<?php echo esc_url(home_url('/')); ?>">
          <span class="font-serif text-3xl tracking-luxury uppercase text-white font-normal"><?php esc_html_e('Demie Photography', 'demie-v2'); ?></span>
          <span class="text-[9px] tracking-widest2 uppercase text-brand-gold block font-sans"><?php esc_html_e('Visual Storytellers', 'demie-v2'); ?></span>
        </a>
        <p class="text-sm text-brand-stone/70 font-light leading-relaxed max-w-sm mb-6">
          <?php esc_html_e('A boutique visual studio capturing luxury weddings and cinematic love stories across Malawi and worldwide destinations.', 'demie-v2'); ?>
        </p>
        <p class="font-serif italic text-brand-champagne text-lg"><?php esc_html_e('“Stories worth remembering.”', 'demie-v2'); ?></p>
      </div>
      <div class="md:col-span-2">
        <h4 class="text-xs uppercase tracking-widest text-white font-medium mb-4"><?php esc_html_e('Navigation', 'demie-v2'); ?></h4>
        <?php
        if (has_nav_menu('footer')) {
            wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'menu_class' => 'space-y-2.5 text-xs text-brand-stone/80 font-light tracking-wide', 'items_wrap' => '<ul class="%2$s">%3$s</ul>']);
        } else {
            echo '<ul class="space-y-2.5 text-xs text-brand-stone/80 font-light tracking-wide">';
            foreach ([[__('Home', 'demie-v2'), home_url('/')], [__('Our Ethos', 'demie-v2'), '#statement'], [__('Weddings', 'demie-v2'), demie_page_url('gallery')], [__('Featured Story', 'demie-v2'), '#featured-story'], [__('Films & Stills', 'demie-v2'), demie_page_url('services')], [__('Kind Words', 'demie-v2'), '#testimonials']] as [$label, $url]) {
                echo '<li><a class="hover:text-brand-champagne transition-colors" href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
            }
            echo '</ul>';
        }
        ?>
      </div>
      <div class="md:col-span-2">
        <h4 class="text-xs uppercase tracking-widest text-white font-medium mb-4"><?php esc_html_e('Social', 'demie-v2'); ?></h4>
        <ul class="space-y-2.5 text-xs text-brand-stone/80 font-light tracking-wide">
          <?php
          $networks = function_exists('demie_social_networks') ? demie_social_networks() : [];
          if ($networks) {
              foreach ($networks as $name => $net) {
                  echo '<li><a class="hover:text-brand-champagne transition-colors" href="' . esc_url($net['url']) . '" target="_blank" rel="noopener">' . esc_html(ucfirst($name)) . '</a></li>';
              }
          } else {
              foreach ([['Instagram', 'https://instagram.com'], ['TikTok Cinema', 'https://tiktok.com'], ['Vimeo Pro', 'https://vimeo.com'], ['Pinterest Boards', 'https://pinterest.com'], ['Facebook', 'https://facebook.com']] as [$label, $url]) {
                  echo '<li><a class="hover:text-brand-champagne transition-colors" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($label) . '</a></li>';
              }
          }
          ?>
        </ul>
      </div>
      <div class="md:col-span-4">
        <h4 class="text-xs uppercase tracking-widest text-white font-medium mb-4"><?php esc_html_e('Studio Direct', 'demie-v2'); ?></h4>
        <div class="space-y-3 text-xs text-brand-stone/80 font-light">
          <p>
            <span class="block text-[10px] uppercase tracking-widest text-brand-gold"><?php esc_html_e('General Inquiries', 'demie-v2'); ?></span>
            <a class="hover:text-white transition-colors text-sm font-sans" href="mailto:<?php echo esc_attr(function_exists('demie_email') ? demie_email() : 'demiestudios@gmail.com'); ?>"><?php echo esc_html(function_exists('demie_email') ? demie_email() : 'demiestudios@gmail.com'); ?></a>
          </p>
          <p>
            <span class="block text-[10px] uppercase tracking-widest text-brand-gold"><?php esc_html_e('Client Line', 'demie-v2'); ?></span>
            <a class="hover:text-white transition-colors text-sm font-sans" href="<?php echo esc_url(function_exists('demie_phone_url') ? demie_phone_url() : 'tel:+265884444802'); ?>"><?php echo esc_html(function_exists('demie_phone') ? demie_phone() : '+265 884 44 48 02'); ?></a>
          </p>
          <p>
            <span class="block text-[10px] uppercase tracking-widest text-brand-gold"><?php esc_html_e('Main Studio', 'demie-v2'); ?></span>
            <span class="text-white/90"><?php echo esc_html(function_exists('demie_location') ? demie_location() : 'Chilomoni, Blantyre, Malawi'); ?></span>
          </p>
        </div>
      </div>
    </div>
    <div class="gold-divider my-10"></div>
    <div class="flex flex-col sm:flex-row items-center justify-between text-xs text-brand-stone/50 font-light">
      <p><?php printf(esc_html__('© %s Demie Photography. All rights reserved.', 'demie-v2'), esc_html(date('Y'))); ?></p>
      <div class="flex space-x-6 mt-4 sm:mt-0">
        <a class="hover:text-brand-champagne transition-colors" href="#"><?php esc_html_e('Privacy Policy', 'demie-v2'); ?></a>
        <a class="hover:text-brand-champagne transition-colors" href="#"><?php esc_html_e('Terms of Commission', 'demie-v2'); ?></a>
        <a class="hover:text-brand-champagne transition-colors" href="#"><?php esc_html_e('Client Portal', 'demie-v2'); ?></a>
      </div>
    </div>
  </div>
</footer>

<?php if (function_exists('demie_whatsapp_url')) : ?>
<a class="fixed bottom-6 right-6 z-40 w-12 h-12 rounded-full bg-brand-champagne text-brand-charcoal flex items-center justify-center shadow-xl hover:bg-white transition-colors" href="<?php echo esc_url(demie_whatsapp_url()); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e('Chat on WhatsApp', 'demie-v2'); ?>">✆</a>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
