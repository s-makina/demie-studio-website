<?php
// Instagram strip — ported from index-3.html (wptb-instagram--gallery).
// Editable via a Demie Gallery named "instagram"; falls back to the bundled images.
$ig_url = function_exists('demie_social_url') && demie_social_url('instagram') ? demie_social_url('instagram') : 'https://www.instagram.com/';
$ig_cards = function_exists('demie_v2_gallery_cards') ? demie_v2_gallery_cards(5, 'instagram') : [];
if (!$ig_cards) {
    for ($i = 1; $i <= 5; $i++) {
        $ig_cards[] = ['title' => '', 'loc' => '', 'img' => DEMIE_URI . '/assets/img/instagram/' . $i . '.jpg', 'kind' => 'photo'];
    }
}
?>
<div class="wptb-instagram--gallery bg-brand-deep">
  <div class="flex items-center justify-center flex-wrap md:flex-nowrap">
    <?php foreach ($ig_cards as $card) : ?>
    <div class="w-1/2 md:w-1/5">
      <a href="<?php echo esc_url($ig_url); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e('Follow us on Instagram', 'demie-v2'); ?>">
        <img src="<?php echo esc_url($card['img']); ?>" alt="<?php echo esc_attr($card['title'] ?: __('Instagram', 'demie-v2')); ?>" class="w-full h-auto aspect-square object-cover block" loading="lazy">
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
