        </main>
        <!-- End Main Wrapper-->

        <footer class="footer style1 bg-image-2" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/background/bg-5.png'); ?>');">
            <div class="footer-top">
                <div class="container">
                    <div class="footer--inner">
                        <div class="row">
                            <div class="col-lg-4 col-md-4 col-sm-6 mb-5 mb-md-0">
                                <div class="footer-widget">
                                    <div class="footer-nav">
                                        <?php demie_render_footer_menu('footer-left'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4 col-md-4 mb-5 mb-md-0 order-1 order-md-0">
                                <div class="footer-widget text-center">
                                    <div class="logo mr-bottom-55">
                                        <a href="<?php echo esc_url(home_url('/')); ?>" class="logo-wordmark"><?php demie_logo_wordmark(); ?></a>
                                    </div>

                                    <h6 class="widget-title"><?php esc_html_e('Sign up for all the latest', 'demie-photography'); ?> <br> <?php esc_html_e('news and offers', 'demie-photography'); ?></h6>
                                    <p class="footer-note"><?php printf(__('Call or WhatsApp us on %s to book your session.', 'demie-photography'), '<a href="' . esc_url(demie_whatsapp_url()) . '" target="_blank" rel="noopener">' . esc_html(demie_phone()) . '</a>'); ?></p>
                                </div>
                            </div>

                            <div class="col-lg-4 col-md-4 col-sm-6 mb-5 mb-md-0">
                                <div class="footer-widget text-md-end">
                                    <div class="footer-nav">
                                        <?php demie_render_footer_menu('footer-right'); ?>
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
                            <p><?php esc_html_e('Demie Photography, All Rights Reserved', 'demie-photography'); ?> &copy; <?php echo esc_html(date('Y')); ?></p>
                        </div>
                        <div class="social-box style-oval">
                            <?php demie_render_social_box('icons'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </footer>

        <div class="totop">
            <a href="#"><i class="bi bi-chevron-up"></i></a>
        </div>

        <a class="wa-float" href="<?php echo esc_url(demie_whatsapp_url()); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e('Chat with Demie Photography on WhatsApp', 'demie-photography'); ?>"><i class="bi bi-whatsapp"></i></a>

        <?php wp_footer(); ?>
    </body>
</html>
