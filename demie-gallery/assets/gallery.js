/**
 * Demie Gallery — front-end behaviour.
 *
 * The theme's isotope-init.js binds directly to $('.grid') (and its load-more
 * hides items beyond 10), so this plugin never relies on theme bindings: it
 * self-initializes Isotope on its own .demie-g-grid containers, and all click
 * handling is delegated so markup appended by AJAX works without rebinding.
 */
(function ($) {
    'use strict';

    /** Init (or refresh) one grid container. */
    function initGrid($wrap) {
        var $grid = $wrap.find('.demie-g-grid');
        if (!$grid.length || typeof $.fn.isotope !== 'function') {
            return;
        }

        if ($grid.data('isotope')) {
            $grid.isotope('destroy');
        }

        $grid.isotope({
            itemSelector: '.grid-item',
            percentPosition: true,
            layoutMode: 'masonry',
            transformsEnabled: true,
            transitionDuration: '700ms',
            resize: true,
            fitWidth: true,
            columnWidth: '.grid-sizer'
        });

        $grid.imagesLoaded().progress(function () {
            $grid.isotope('layout');
        });

        // The theme binds hoverdir directly on page load, so appended items
        // never get it — bind it here per-grid instead.
        if (typeof $.fn.hoverdir === 'function' && $wrap.hasClass('demie-g-layout-filterable')) {
            $grid.find('.grid-item').each(function () {
                if (!$.data(this, 'hoverdir')) {
                    $(this).hoverdir();
                }
            });
        }
    }

    /** Init the Swiper carousel for one wrap (theme provides Swiper). */
    function initCarousel($wrap) {
        var $el = $wrap.find('.demie-g-swiper');
        if (!$el.length || typeof Swiper !== 'function') {
            return;
        }

        if ($el[0].swiper) {
            $el[0].swiper.destroy(true, true);
        }

        /* Same settings as the theme's .swiper-gallery-two binding. */
        $el[0].swiper = new Swiper($el[0], {
            loop: true,
            autoplay: { delay: 3000 },
            speed: 1500,
            slidesPerView: 1,
            spaceBetween: 30,
            centeredSlides: false,
            navigation: {
                nextEl: $el.find('.swiper-button-next')[0],
                prevEl: $el.find('.swiper-button-prev')[0]
            },
            pagination: { el: $el.find('.swiper-pagination')[0], clickable: true },
            breakpoints: {
                992: { slidesPerView: 2, spaceBetween: 30, centeredSlides: false },
                1200: { slidesPerView: 2, spaceBetween: 85, centeredSlides: true }
            }
        });
    }

    /** Load more: fetch page N+1 for this wrap and append the items. */
    function loadMore($wrap, $btn) {
        if ($btn.hasClass('is-loading')) {
            return;
        }

        var data = {
            action: 'demie_g_load_more',
            nonce: window.demieGallery ? window.demieGallery.nonce : '',
            gallery_id: parseInt($wrap.attr('data-gallery-id'), 10) || 0,
            page: parseInt($wrap.attr('data-page'), 10) || 1,
            per_page: parseInt($wrap.attr('data-per-page'), 10) || 0,
            layout: $wrap.attr('data-layout') || 'masonry',
            columns: parseInt($wrap.attr('data-columns'), 10) || 3
        };

        $btn.addClass('is-loading');
        $wrap.attr('data-page', data.page + 1);

        $.post(window.demieGallery ? window.demieGallery.ajaxUrl : '', data)
            .done(function (res) {
                if (!res || !res.success || !res.data) {
                    $btn.removeClass('is-loading');
                    return;
                }

                var $items = $(res.data.items);

                // Mark for fade-in, append, then reveal after Isotope layout.
                $items.addClass('demie-g-entering');

                var $grid = $wrap.find('.demie-g-grid');
                if ($grid.length && $grid.data('isotope')) {
                    $grid.isotope('insert', $items);
                } else if ($grid.length) {
                    $grid.append($items);
                }

                // Re-init/relayout after insertion.
                setTimeout(function () {
                    $items.removeClass('demie-g-entering');
                    initGrid($wrap);
                    initCarousel($wrap);
                }, 50);

                $wrap.attr('data-total', res.data.total);

                if (!res.data.has_more) {
                    $btn.closest('.demie-g-load-more-wrap').fadeOut(200, function () {
                        $(this).remove();
                    });
                }
            })
            .fail(function () {
                $btn.removeClass('is-loading');
                // Restore the page pointer so a retry isn't skipped.
                $wrap.attr('data-page', data.page);
            })
            .always(function () {
                $btn.removeClass('is-loading');
            });
    }

    $(function () {
        // One init pass per gallery wrap on the page.
        $('.demie-g-wrap').each(function () {
            var $wrap = $(this);
            initGrid($wrap);
            initCarousel($wrap);
        });

        /* Delegated: Load More (works for every wrap, incl. AJAX-appended UI). */
        $(document).on('click', '.demie-g-load-more', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('.demie-g-wrap');
            loadMore($wrap, $(this));
        });

        /* Delegated: filter buttons (All | Photos | Videos). */
        $(document).on('click', '.demie-g-wrap .filters-button-group .button', function (e) {
            e.preventDefault();

            var $btn = $(this);
            var $group = $btn.closest('.filters-button-group');
            var $wrap = $btn.closest('.demie-g-wrap');
            var $grid = $wrap.find('.demie-g-grid');

            $group.find('.is-checked').removeClass('is-checked');
            $btn.addClass('is-checked');

            if ($grid.length && $grid.data('isotope')) {
                $grid.isotope({ filter: $btn.attr('data-filter') || '*' });
            }
        });
    });

})(jQuery);
