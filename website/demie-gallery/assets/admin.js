/**
 * Demie Gallery — admin media builder.
 *
 * State lives in the DOM: each .demie-g-tile carries data-type/data-id/data-url;
 * every change re-serializes the tile order into the hidden _demie_g_media
 * input, which save_post then persists server-side (re-sanitized).
 */
(function ($) {
    'use strict';

    /** Serialize current tile order into the hidden input + refresh badges. */
    function sync() {
        var items = [];
        $('#demie-g-grid .demie-g-tile').each(function () {
            var $tile = $(this);
            var type  = $tile.attr('data-type');
            var item  = type === 'embed'
                ? { type: 'embed', url: $tile.attr('data-url') }
                : { type: 'attachment', id: parseInt($tile.attr('data-id'), 10) || 0 };
            if (item.type === 'attachment' && !item.id) return;
            if (item.type === 'embed' && !item.url) return;
            items.push(item);
        });
        $('#demie-g-media-input').val(JSON.stringify(items));

        var $grid = $('#demie-g-grid');
        $grid.toggleClass('is-empty', items.length === 0);
        $('.demie-g-empty-hint').toggleClass('hidden', items.length > 0);
        $('.demie-g-count-badge').text(items.length > 0
            ? items.length + (items.length === 1 ? ' item' : ' items')
            : '');
    }

    /** Build a tile node matching the server-rendered one (inc/admin-ui.php). */
    function makeTile(item) {
        var $tile = $('<div class="demie-g-tile" />')
            .attr('data-type', item.type)
            .attr('data-id', item.type === 'attachment' ? item.id : 0)
            .attr('data-url', item.type === 'embed' ? item.url : '');

        $tile.append($('<span class="demie-g-tile-handle dashicons dashicons-menu" />'));

        $tile.append($('<img />', { src: item.thumb || demieGAdmin.placeholder, alt: '' }));

        $tile.append(
            $('<span class="demie-g-tile-badge" />')
                .addClass('demie-g-tile-badge--' + (item.kind || 'photo'))
                .text(item.kind === 'video' ? demieGAdmin.videoLabel : demieGAdmin.photoLabel)
        );
        $tile.append($('<button type="button" class="demie-g-tile-remove">&times;</button>'));
        return $tile;
    }

    $(function () {
        var $grid = $('#demie-g-grid');
        if (!$grid.length) return;

        // Drag-and-drop ordering (jQuery UI Sortable ships with WP admin).
        $grid.sortable({
            items: '.demie-g-tile',
            handle: '.demie-g-tile-handle, img',
            cursor: 'move',
            tolerance: 'pointer',
            update: sync
        });
        $grid.disableSelection();

        sync();

        /* ---------- Add Media (Media Library, images + videos) ---------- */

        var frame = null;
        $('#demie-g-add-media').on('click', function (e) {
            e.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: demieGAdmin.selectTitle,
                multiple: 'add',
                library: { type: 'image,video' },
                button: { text: demieGAdmin.addToGallery }
            });

            frame.on('select', function () {
                var existing = {};
                $('#demie-g-grid .demie-g-tile[data-type="attachment"]').each(function () {
                    existing[$(this).attr('data-id')] = true;
                });

                frame.state().get('selection').each(function (attachment) {
                    var id = attachment.id;
                    if (existing[id]) return; // already in the gallery

                    var isVideo = attachment.get('type') === 'video';
                    var thumb = '';
                    if (isVideo) {
                        thumb = attachment.get('image') && attachment.get('image').src
                            ? attachment.get('image').src
                            : demieGAdmin.placeholder;
                    } else {
                        var sizes = attachment.get('sizes');
                        thumb = sizes && sizes.medium ? sizes.medium.url
                              : (sizes && sizes.thumbnail ? sizes.thumbnail.url
                              : (attachment.get('url') || demieGAdmin.placeholder));
                    }

                    $('#demie-g-grid').append(makeTile({
                        type: 'attachment',
                        id: id,
                        kind: isVideo ? 'video' : 'photo',
                        thumb: thumb
                    }));
                });

                sync();
            });

            frame.open();
        });

        /* ---------- Add Video Link (embed items) ---------- */

        $('#demie-g-add-video').on('click', function (e) {
            e.preventDefault();

            var $input = $('#demie-g-video-url');
            var url = $.trim($input.val());
            if (!url) return;

            var $btn = $(this).prop('disabled', true);
            var $feedback = $('<span class="demie-g-feedback" />').insertAfter($btn);

            $.post(demieGAdmin.ajaxUrl, {
                action: 'demie_g_validate_embed',
                nonce: demieGAdmin.nonce,
                url: url
            }).done(function (res) {
                if (res && res.success) {
                    $('#demie-g-grid').append(makeTile({
                        type: 'embed',
                        url: res.data.item.url,
                        kind: 'video',
                        thumb: res.data.thumb
                    }));
                    $input.val('');
                    sync();
                } else {
                    $feedback.text((res && res.data && res.data.message) || demieGAdmin.invalidUrl)
                        .addClass('is-error');
                }
            }).fail(function () {
                $feedback.text(demieGAdmin.invalidUrl).addClass('is-error');
            }).always(function () {
                $btn.prop('disabled', false);
                setTimeout(function () { $feedback.fadeOut(200, function () { $(this).remove(); }); }, 3000);
            });
        });

        // Enter in the URL field acts as "Add Video Link".
        $('#demie-g-video-url').on('keydown', function (e) {
            if (e.key === 'Enter' || e.which === 13) {
                e.preventDefault();
                $('#demie-g-add-video').trigger('click');
            }
        });

        /* ---------- Remove (delegated; tiles are created dynamically) ---------- */

        $grid.on('click', '.demie-g-tile-remove', function (e) {
            e.preventDefault();
            $(this).closest('.demie-g-tile').remove();
            sync();
        });
    });
})(jQuery);
