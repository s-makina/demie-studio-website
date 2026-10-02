</main>

<footer class="footer style1 bg-image-2" id="contact" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/background/bg-5.png'); ?>');">
  <div class="footer-top">
    <div class="container">
      <div class="footer--inner">
        <div class="footer-row">
          <div class="footer-col footer-col--left">
            <div class="footer-widget">
              <div class="footer-nav">
                <?php if (has_nav_menu('footer')) : ?>
                  <?php wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'fallback_cb' => false]); ?>
                <?php else : ?>
                  <ul>
                    <li class="menu-item"><a href="<?php echo esc_url(demie_page_url('about-us')); ?>"><?php esc_html_e('About Us', 'demie-v2'); ?></a></li>
                    <li class="menu-item"><a href="<?php echo esc_url(demie_page_url('services')); ?>"><?php esc_html_e('Our Services', 'demie-v2'); ?></a></li>
                    <li class="menu-item"><a href="<?php echo esc_url(demie_page_url('gallery')); ?>"><?php esc_html_e('Gallery', 'demie-v2'); ?></a></li>
                    <li class="menu-item"><a href="<?php echo esc_url(demie_page_url('blog')); ?>"><?php esc_html_e('Blog', 'demie-v2'); ?></a></li>
                    <li class="menu-item"><a href="<?php echo esc_url(demie_page_url('contact')); ?>"><?php esc_html_e('Contact Us', 'demie-v2'); ?></a></li>
                  </ul>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="footer-col footer-col--center">
            <div class="footer-widget text-center">
              <div class="logo mr-bottom-55">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php demie_logo_wordmark(); ?></a>
              </div>

              <h6 class="widget-title"><?php esc_html_e('Sign up for all the latest', 'demie-v2'); ?> <br> <?php esc_html_e('news and offers', 'demie-v2'); ?> </h6>
              <form class="newsletter-form" method="post" action="#">
                <div class="form-group">
                  <input type="email" name="email" class="form-control" placeholder="<?php esc_attr_e('Enter your email', 'demie-v2'); ?>" required>
                </div>
                <button type="submit" class="btn btn-two">
                  <span class="btn-wrap">
                    <span class="text-first"><?php esc_html_e('Subscribe', 'demie-v2'); ?></span>
                    <span class="text-second"><i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i></span>
                  </span>
                </button>
              </form>
            </div>
          </div>

          <div class="footer-col footer-col--right">
            <div class="footer-widget text-md-end">
              <div class="footer-nav">
                <ul>
                  <li class="menu-item"><a href="<?php echo esc_url(demie_page_url('contact')); ?>"><?php esc_html_e('Booking', 'demie-v2'); ?></a></li>
                  <li class="menu-item"><a href="#"><?php esc_html_e('Products', 'demie-v2'); ?></a></li>
                  <li class="menu-item"><a href="<?php echo esc_url(demie_page_url('blog')); ?>"><?php esc_html_e('Recent Posts', 'demie-v2'); ?></a></li>
                  <li class="menu-item"><a href="<?php echo esc_url(demie_page_url('blog')); ?>"><?php esc_html_e('Latest News', 'demie-v2'); ?></a></li>
                  <li class="menu-item"><a href="<?php echo esc_url(demie_page_url('contact')); ?>"><?php esc_html_e('Contact Us', 'demie-v2'); ?></a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer Bottom Part -->
  <div class="footer-bottom">
    <div class="container">
      <div class="footer-bottom-inner">
        <div class="copyright">
          <p><?php printf(esc_html__('Demie Photography, All Rights Reserved &copy; %s', 'demie-v2'), esc_html(date('Y'))); ?></p>
        </div>
        <div class="social-box style-oval">
          <?php
          if (function_exists('demie_social_networks') && demie_social_networks()) :
              demie_render_social_box('icons');
          else :
              ?>
              <ul>
                <li><a href="#" class="bi bi-facebook" aria-label="Facebook"></a></li>
                <li><a href="#" class="bi bi-instagram" aria-label="Instagram"></a></li>
                <li><a href="#" class="bi bi-linkedin" aria-label="LinkedIn"></a></li>
                <li><a href="#" class="bi bi-behance" aria-label="Behance"></a></li>
              </ul>
          <?php endif; ?>
        </div>
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
