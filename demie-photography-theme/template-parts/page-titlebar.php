<?php
/**
 * Reusable page titlebar (wptb-page-heading).
 * Args:
 *   title  (string) Page title text.
 *   bg     (string) Filename under assets/img/background/ (default page-header-bg-6.jpg).
 */

if (!defined('ABSPATH')) exit;

$demie_title = isset($args['title']) ? $args['title'] : get_the_title();
$demie_bg    = isset($args['bg']) ? $args['bg'] : 'page-header-bg-6.jpg';
?>
<!-- Page Header -->
<div class="wptb-page-heading">
    <div class="wptb-item--inner" style="background-image: url('<?php echo esc_url(DEMIE_URI . '/assets/img/background/' . $demie_bg); ?>');">
        <div class="wptb-item-layer wptb-item-layer-one">
            <img src="<?php echo esc_url(DEMIE_URI . '/assets/img/more/circle.png'); ?>" alt="img">
        </div>
        <h2 class="wptb-item--title"><?php echo esc_html($demie_title); ?></h2>
    </div>
</div>
