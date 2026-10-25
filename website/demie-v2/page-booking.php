<?php
/**
 * Template Name: Booking
 *
 * Dedicated Slotfy booking page. The tenant and origin come from
 * Settings → Demie Settings → Booking.
 */

get_header();
the_post();

get_template_part('template-parts/page-titlebar', null, [
    'title'    => get_the_title() ?: __('Book a Session', 'demie-v2'),
    'subtitle' => __('Reserve Your Date', 'demie-v2'),
]);

$demie_payment_return = isset($_GET['payment']) && 'return' === sanitize_key(wp_unslash($_GET['payment']));
$demie_has_content    = '' !== trim(get_the_content());
?>
<section class="bg-[#151515] text-brand-cream px-4 sm:px-6 md:px-14 pt-14 md:pt-20 pb-20 md:pb-28">
    <div class="max-w-8xl mx-auto">

        <?php if ($demie_payment_return) : ?>
            <div role="status" class="mb-10 border border-brand-gold/40 bg-brand-gold/10 px-5 py-4 text-sm leading-relaxed text-brand-cream">
                <strong class="block text-xs uppercase tracking-widest text-brand-champagne mb-2"><?php esc_html_e('Payment received', 'demie-v2'); ?></strong>
                <?php esc_html_e('Thank you — your payment has been received and your confirmation email is on its way. If it does not arrive within a few minutes, call or WhatsApp us and we will sort it out.', 'demie-v2'); ?>
            </div>
        <?php endif; ?>

        <div class="demie-v2-prose text-center max-w-2xl mx-auto mb-10 [&_p]:text-white/60 [&_p]:font-light">
            <?php if ($demie_has_content) : ?>
                <?php the_content(); ?>
            <?php else : ?>
                <p><?php esc_html_e('Choose your service, pick a date and time, and confirm your session online. Available dates update in real time.', 'demie-v2'); ?></p>
            <?php endif; ?>
        </div>

        <?php if (demie_slotfy_enabled()) : ?>
            <p id="slotfy-widget-error" hidden class="mb-6 text-center text-sm text-brand-champagne">
                <?php esc_html_e('The booking form did not load. Please refresh, or call us and we will book you in.', 'demie-v2'); ?>
                <a class="underline underline-offset-4 hover:text-white" href="<?php echo esc_url(demie_phone_url()); ?>"><?php echo esc_html(demie_phone()); ?></a>
            </p>
            <script>
                document.addEventListener('slotfy:error', function () {
                    var note = document.getElementById('slotfy-widget-error');
                    if (note) note.hidden = false;
                });
            </script>
            <noscript>
                <p class="mb-6 text-center text-sm text-brand-champagne">
                    <?php esc_html_e('Online booking needs JavaScript. Call or WhatsApp us and we will book you in:', 'demie-v2'); ?>
                    <a class="underline underline-offset-4 hover:text-white" href="<?php echo esc_url(demie_phone_url()); ?>"><?php echo esc_html(demie_phone()); ?></a>
                </p>
            </noscript>
            <?php demie_render_slotfy_widget(); ?>
        <?php else : ?>
            <div class="border border-white/10 bg-[#1E1C1A] px-6 py-10 md:px-10 text-center max-w-3xl mx-auto">
                <h2 class="font-serif text-3xl text-brand-cream mb-4"><?php esc_html_e('Online booking is unavailable', 'demie-v2'); ?></h2>
                <p class="text-white/60 font-light leading-relaxed max-w-xl mx-auto mb-8">
                    <?php esc_html_e('We are updating our booking calendar. Call, WhatsApp, or send us a message and we will find a date together.', 'demie-v2'); ?>
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a class="w-full sm:w-auto px-8 py-4 bg-brand-champagne text-brand-charcoal hover:bg-white text-xs uppercase tracking-luxury font-medium transition-all text-center" href="<?php echo esc_url(demie_phone_url()); ?>">
                        <?php esc_html_e('Call the studio', 'demie-v2'); ?>
                    </a>
                    <a class="w-full sm:w-auto px-8 py-4 border border-white/40 text-white hover:bg-white/10 hover:border-white text-xs uppercase tracking-luxury font-medium transition-all text-center" href="<?php echo esc_url(demie_whatsapp_url()); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('WhatsApp us', 'demie-v2'); ?>
                    </a>
                    <a class="w-full sm:w-auto px-8 py-4 border border-white/40 text-white hover:bg-white/10 hover:border-white text-xs uppercase tracking-luxury font-medium transition-all text-center" href="<?php echo esc_url(demie_page_url('contact')); ?>">
                        <?php esc_html_e('Send a message', 'demie-v2'); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</section>
<?php get_footer(); ?>
