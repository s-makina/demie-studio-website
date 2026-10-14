<?php
/**
 * Shared renderer — single source of truth for gallery HTML.
 *
 * Used by the shortcode, the AJAX load-more handler, and (via do_shortcode)
 * the theme's Gallery page wrapper, so initial paint and appended markup
 * cannot drift apart.
 *
 * Decision: docs/adr/0002-gallery-as-plugin.md
 */

if (!defined('ABSPATH')) exit;

/**
 * The 13 Kimono portfolio layouts, grouped by mechanics.
 *
 * - masonry / masonry-2        Isotope mixed-height
 * - classic-2/3                uniform Isotope (effect-fly)
 * - standard-2/3               uniform Isotope (effect-tilt, has-radius)
 * - modern-2/3                 uniform Isotope (effect-gradient)
 * - tiles-2/3                  uniform Isotope (effect-tilt, style-masonry)
 * - carousel                   Swiper slider (per_page/pagination are no-ops)
 * - overlapping                CSS-effect collage grid
 * - distortion                 WebGL hover effect (falls back to plain grid)
 * - filterable                 Isotope + All/Photos/Videos buttons (grid-3, effect-fly)
 *
 * cols: 0 = layout defines its own widths (masonry spans / fixed widths).
 */
function demie_g_layouts() {
    return apply_filters('demie_g_layouts', [
        'masonry'      => ['wrapper' => 'effect-gradient has-radius', 'grid' => 'grid gutter-10', 'cols' => 0],
        'masonry-2'    => ['wrapper' => 'style-masonry effect-blur',  'grid' => 'grid grid-3 gutter-10', 'cols' => 0],
        'classic-2'    => ['wrapper' => 'effect-fly',                 'grid' => 'grid grid-2 gutter-30', 'cols' => 2],
        'classic-3'    => ['wrapper' => 'effect-fly',                 'grid' => 'grid grid-3 gutter-30', 'cols' => 3],
        'standard-2'   => ['wrapper' => 'has-radius effect-tilt',     'grid' => 'grid grid-2 gutter-30', 'cols' => 2],
        'standard-3'   => ['wrapper' => 'has-radius effect-tilt',     'grid' => 'grid grid-3 gutter-30', 'cols' => 3],
        'modern-2'     => ['wrapper' => 'effect-gradient has-radius', 'grid' => 'grid grid-2 gutter-10', 'cols' => 2],
        'modern-3'     => ['wrapper' => 'effect-gradient has-radius', 'grid' => 'grid grid-3 gutter-10', 'cols' => 3],
        'tiles-2'      => ['wrapper' => 'style-masonry effect-tilt',  'grid' => 'grid grid-2 gutter-100', 'cols' => 2],
        'tiles-3'      => ['wrapper' => 'style-masonry effect-tilt',  'grid' => 'grid grid-3 gutter-50', 'cols' => 3],
        'carousel'     => ['wrapper' => '',                           'grid' => '', 'cols' => 0, 'carousel' => true],
        'overlapping'  => ['wrapper' => 'demie-g-overlap',            'grid' => 'demie-g-overlap__grid', 'cols' => 0, 'static' => true],
        'distortion'   => ['wrapper' => 'has-radius effect-tilt',     'grid' => 'grid gutter-30', 'cols' => 0, 'distortion' => true],
        'filterable'   => ['wrapper' => 'effect-fly',                 'grid' => 'grid grid-3 gutter-30', 'cols' => 3, 'filterable' => true],
    ]);
}

/**
 * Isotope-backed layouts (self-initialized by gallery.js on .demie-g-grid).
 */
function demie_g_is_isotope_layout($layout) {
    $def = demie_g_layouts();
    return isset($def[$layout]) && empty($def[$layout]['carousel']) && empty($def[$layout]['static']);
}

/**
 * Normalize + validate shortcode/AJAX args into the canonical render state.
 */
function demie_g_normalize_args($args) {
    $layouts = demie_g_layouts();

    $defaults = [
        'gallery_id' => 0,
        'id'         => 0,
        'slug'       => '',
        'latest'     => '',
        'layout'     => 'masonry',
        'per_page'   => 24,
        'pagination' => 'load_more',
        'columns'    => 3,
        'page'       => 0,
        'items_only' => false,
    ];
    $args = wp_parse_args(is_array($args) ? $args : [], $defaults);

    $args['gallery_id'] = (int) $args['gallery_id'];

    // Resolve id/slug/latest when no explicit gallery_id was given (same rule
    // the shortcode uses), so theme wrappers can pass any of these forms.
    if (!$args['gallery_id'] && (!empty($args['id']) || '' !== (string) $args['slug'] || !empty($args['latest']))) {
        $args['gallery_id'] = demie_g_resolve_gallery_id($args);
    }

    $args['layout']     = isset($layouts[$args['layout']]) ? $args['layout'] : 'masonry';
    $args['per_page']   = max(1, (int) $args['per_page']);

    $pagination = strtolower((string) $args['pagination']);
    $args['pagination'] = in_array($pagination, ['load_more', 'numbered', 'none'], true) ? $pagination : 'load_more';

    // Conflicting attrs resolve gracefully (ADR-0002):
    // - pagination="none" renders everything and ignores per_page;
    // - carousel ignores pagination + per_page entirely (it's a slider);
    // - load_more on a static layout (overlapping) degrades to none.
    $is_carousel = !empty($layouts[$args['layout']]['carousel']);
    $is_static   = !empty($layouts[$args['layout']]['static']);

    if ($is_carousel) {
        $args['pagination'] = 'none';
    } elseif ($is_static && 'load_more' === $args['pagination']) {
        $args['pagination'] = 'none';
    }

    if ('none' === $args['pagination']) {
        $args['per_page'] = 0; // render all
    }

    return $args;
}

/**
 * Render a gallery. Returns HTML (never echoes) so callers can embed it.
 *
 * @param array $args gallery_id|id|slug|latest, layout, per_page, pagination, columns, page (AJAX only).
 */
function demie_g_render_gallery($args = []) {
    $args = demie_g_normalize_args($args);

    $gallery_id = $args['gallery_id'];
    $layout     = $args['layout'];
    $def        = demie_g_layouts()[$layout];

    $media = $gallery_id ? demie_g_get_media($gallery_id) : [];

    if (!$gallery_id || !$media) {
        // No gallery found / no media: notice for admins, silent for visitors.
        if (current_user_can('edit_posts')) {
            return '<p style="border:1px dashed #c3c4c7;padding:8px 12px;">'
                . esc_html__('Demie Gallery: no published gallery with media found. Create one under Galleries, or pass id="…" to the shortcode.', 'demie-gallery')
                . '</p>';
        }
        return '';
    }

    $total = count($media);

    // Page: explicit (AJAX), else the numbered-pagination query var, else 1.
    $page = isset($args['page']) && (int) $args['page'] > 0
        ? (int) $args['page']
        : max(1, (int) get_query_var('gallery-page', 1));
    $per_page = (int) $args['per_page'];

    // Clamp to the last valid page: over-range ?gallery-page=N (stale links)
    // shows the final page instead of a blank section.
    if ($per_page > 0) {
        $page = min($page, max(1, (int) ceil($total / $per_page)));
    } else {
        $page = 1;
    }

    $offset   = $per_page > 0 ? ($page - 1) * $per_page : 0;

    $paged_items = $per_page > 0 ? array_slice($media, $offset, $per_page) : $media;

    // Nothing left for this page (over-scrolled numbered page, or empty append).
    if (!$paged_items) {
        return '';
    }

    $has_more = $per_page > 0 && ($offset + count($paged_items)) < $total;

    // Masonry span patterns must continue across pages (page 2 picks up where
    // page 1 left off), so seed the position counter with the page offset.
    $GLOBALS['demie_g_item_index'] = $offset;

    // AJAX append mode: return ONLY the item markup for this slice — the
    // browser inserts it into the existing grid (same grid-item template as
    // the initial paint, so no drift).
    if (!empty($args['items_only'])) {
        ob_start();
        foreach ($paged_items as $item) {
            demie_g_template('grid-item', ['item' => $item, 'layout' => $layout, 'gallery_id' => $gallery_id]);
        }
        return trim(ob_get_clean());
    }

    // Data block for AJAX: the load-more handler reconstructs the exact same
    // render state from these attributes.
    $data_attrs = sprintf(
        ' data-demie-gallery="%1$s" data-gallery-id="%2$d" data-page="%3$d" data-per-page="%4$d" data-layout="%5$s" data-columns="%6$d" data-pagination="%7$s" data-total="%8$d"',
        esc_attr(wp_json_encode([
            'gallery_id' => $gallery_id,
            'page'       => $page,
            'per_page'   => $per_page,
            'layout'     => $layout,
            'columns'    => $args['columns'],
        ])),
        $gallery_id,
        $page,
        $per_page,
        esc_attr($layout),
        (int) $args['columns'],
        esc_attr($args['pagination']),
        $total
    );

    $classes = 'demie-g-wrap demie-g-layout-' . $layout;
    if ($args['pagination']) {
        $classes .= ' demie-g-pag-' . $args['pagination'];
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr($classes); ?>"<?php echo $data_attrs; // contains escaped, trusted values ?>>
            <?php if ($desc = get_post_meta($gallery_id, DEMIE_G_META_DESC, true)) : ?>
                <div class="demie-g-desc"><p><?php echo esc_html($desc); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($def['filterable'])) :
                // Filter buttons: All | Photos | Videos (kind from the data layer).
                $counts = ['photo' => 0, 'video' => 0];
                foreach ($media as $item) {
                    $counts[demie_g_kind($item)]++;
                }
                ?>
                <div class="portfolio-filters-content">
                    <div class="filters-button-group">
                        <button type="button" class="button is-checked" data-filter="*"><?php esc_html_e('All', 'demie-gallery'); ?></button>
                        <button type="button" class="button" data-filter=".demie-g-photo"><?php echo esc_html(sprintf(__('Photos (%d)', 'demie-gallery'), $counts['photo'])); ?></button>
                        <button type="button" class="button" data-filter=".demie-g-video"><?php echo esc_html(sprintf(__('Videos (%d)', 'demie-gallery'), $counts['video'])); ?></button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($def['carousel'])) : ?>
                <div class="swiper-container swiper-gallery-two has-radius demie-g-swiper">
                    <div class="swiper-wrapper">
                        <?php
                        foreach ($paged_items as $item) {
                            demie_g_template('grid-item-carousel', ['item' => $item, 'gallery_id' => $gallery_id]);
                        }
                        ?>
                    </div>
                    <div class="wptb-swiper-navigation style2">
                        <div class="wptb-swiper-arrow swiper-button-prev"></div>
                        <div class="wptb-swiper-arrow swiper-button-next"></div>
                    </div>
                </div>
            <?php else : ?>
                <div class="<?php echo esc_attr($def['wrapper']); ?>">
                    <div class="<?php echo esc_attr($def['grid']); ?> demie-g-grid clearfix">
                        <div class="grid-sizer"></div>
                        <?php
                        foreach ($paged_items as $item) {
                            demie_g_template('grid-item', ['item' => $item, 'layout' => $layout, 'gallery_id' => $gallery_id]);
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php demie_g_template('pager', [
                'args'       => $args,
                'total'      => $total,
                'has_more'   => $has_more,
                'page'       => $page,
                'per_page'   => $per_page,
                'gallery_id' => $gallery_id,
            ]); ?>
    </div>
    <?php
    return trim(ob_get_clean());
}

/**
 * Load a plugin template with a tiny, explicit variable scope. Templates live
 * in demie-gallery/templates/{name}.php and can be overridden by dropping a
 * file of the same name into the theme's demie-gallery/ folder.
 */
function demie_g_template($name, $vars = []) {
    $name = preg_replace('/[^a-z0-9\-_]/', '', (string) $name);

    $override = locate_template('demie-gallery/' . $name . '.php');
    $file     = $override ? $override : DEMIE_G_DIR . 'templates/' . $name . '.php';

    if (!file_exists($file)) {
        return;
    }

    // Templates receive $item / $layout / $gallery_id / $args etc. explicitly.
    // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled, local scope
    extract($vars);

    include $file;
}

/**
 * Numbered-pagination query var: rewrite-free ?gallery-page=N works on any
 * page without colliding with core's /page/2/ rewriting (ADR-0002).
 */
add_filter('query_vars', function ($vars) {
    $vars[] = 'gallery-page';
    return $vars;
});
