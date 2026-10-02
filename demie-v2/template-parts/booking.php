<section class="py-24 md:py-36 px-6 md:px-14 bg-brand-charcoal text-white relative overflow-hidden" id="booking">
  <div class="absolute inset-0 z-0 opacity-20">
    <img alt="<?php esc_attr_e('Wedding mood', 'demie-v2'); ?>" class="w-full h-full object-cover object-center" src="https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=2000&q=80">
  </div>
  <div class="absolute inset-0 bg-gradient-to-b from-brand-charcoal via-brand-charcoal/90 to-brand-deep z-0"></div>
  <div class="max-w-4xl mx-auto text-center relative z-10 reveal-on-scroll">
    <div class="flex items-center justify-center space-x-3 mb-4">
      <span class="w-8 h-[1px] bg-brand-gold"></span>
      <span class="text-xs uppercase tracking-widest2 text-brand-champagne font-sans font-medium"><?php esc_html_e('Reach Out • We’d Love to Hear From You', 'demie-v2'); ?></span>
      <span class="w-8 h-[1px] bg-brand-gold"></span>
    </div>
    <h2 class="font-serif text-4xl sm:text-6xl md:text-7xl font-normal text-brand-cream mb-6 tracking-tight">
      <?php echo esc_html(demie_v2_get('demie_v2_booking_title', 'Have a Question?')); ?>
    </h2>
    <p class="max-w-xl mx-auto text-base sm:text-lg text-white/80 font-light font-sans leading-relaxed mb-10">
      <?php echo esc_html(demie_v2_get('demie_v2_booking_text', 'Whether you’re planning a celebration, exploring packages, or simply want to reach out — send us a note and we’ll get back to you.')); ?>
    </p>

    <form class="demie-contact-form max-w-2xl mx-auto text-left grid grid-cols-1 sm:grid-cols-2 gap-4 mb-10" method="post">
      <input type="text" name="name" required placeholder="<?php esc_attr_e('Your Name *', 'demie-v2'); ?>" class="demie-v2-input">
      <input type="email" name="email" required placeholder="<?php esc_attr_e('Email *', 'demie-v2'); ?>" class="demie-v2-input">
      <input type="tel" name="phone" placeholder="<?php esc_attr_e('Phone / WhatsApp', 'demie-v2'); ?>" class="demie-v2-input">
      <input type="text" name="subject" placeholder="<?php esc_attr_e('Subject / How Can We Help?', 'demie-v2'); ?>" class="demie-v2-input">
      <textarea name="message" required rows="4" placeholder="<?php esc_attr_e('Tell us your story *', 'demie-v2'); ?>" class="demie-v2-input sm:col-span-2"></textarea>
      <div class="sm:col-span-2 text-center">
        <button type="submit" class="btn-luxury px-9 py-4 bg-brand-champagne text-brand-charcoal hover:bg-white text-xs uppercase tracking-luxury font-medium transition-all shadow-xl">
          <?php esc_html_e('Send Message', 'demie-v2'); ?>
        </button>
        <div class="demie-form-feedback" role="status" aria-live="polite" hidden></div>
      </div>
    </form>

    <p class="text-[11px] uppercase tracking-widest text-brand-champagne/70 mt-10">
      <?php
      if (function_exists('demie_phone')) {
          printf(esc_html__('Prefer to talk? Call or WhatsApp %s', 'demie-v2'), esc_html(demie_phone()));
      } else {
          esc_html_e('Currently accepting inquiries for 2025 and 2026 celebrations worldwide', 'demie-v2');
      }
      ?>
    </p>
  </div>
</section>
