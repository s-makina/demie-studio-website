<?php
/**
 * Pager partial: load_more button | numbered links | nothing.
 *
 * In: $args (normalized render args), $total, $has_more, $page, $per_page, $gallery_id
 */

if (!defined('ABSPATH')) exit;

if ('load_more' === $args['pagination']) :
    if (!$has_more) {
        return; // everything fits on one page — no button
    }
    ?>
    <div class="demie-g-load-more-wrap text-center">
        <button type="button" class="demie-g-load-more btn btn-two">
            <span class="btn-wrap">
                <span class="text-first"><?php esc_html_e('Load More', 'demie-gallery'); ?></span>
                <span class="text-second"><i class="bi bi-arrow-up-right"></i> <i class="bi bi-arrow-up-right"></i></span>
            </span>
            <span class="demie-g-spinner" aria-hidden="true"></span>
        </button>
    </div>
    <?php
elseif ('numbered' === $args['pagination']) :
    $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;
    if ($total_pages <= 1) {
        return;
    }

    // Rewrite-free: ?gallery-page=N on the current URL (works on any page
    // without colliding with core's /page/2/ rewriting).
    $base = add_query_arg('gallery-page', '%#%');

    $links = paginate_links([
        'base'      => $base,
        'format'    => '',
        'current'   => $page,
        'total'     => $total_pages,
        'type'      => 'array',
        'prev_text' => '<i class="bi bi-chevron-left"></i>',
        'next_text' => '<i class="bi bi-chevron-right"></i>',
    ]);

    if ($links) :
        ?>
        <div class="wptb-pagination-wrap text-center">
            <ul class="pagination justify-content-center">
                <?php
                foreach ($links as $link) {
                    // Re-tag core's page-numbers classes to Kimono's page-number.
                    // paginate_links output is escaped by core.
                    echo '<li>' . str_replace('page-numbers', 'page-number', $link) . '</li>';
                }
                ?>
            </ul>
        </div>
    <?php endif;
endif;
