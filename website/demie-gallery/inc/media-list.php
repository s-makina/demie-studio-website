<?php
/**
 * Gallery data layer: the ordered media list of Gallery Media Items.
 *
 * Storage: single post meta `_demie_g_media` = JSON array:
 *   [{"type":"attachment","id":123}, {"type":"embed","url":"https://youtu.be/…"}]
 * Normalized on read via demie_g_sanitize_media_list(); dangling attachment
 * IDs (deleted / never existed) are dropped on read.
 *
 * Decision: docs/adr/0002-gallery-as-plugin.md
 */

if (!defined('ABSPATH')) exit;

const DEMIE_G_META_MEDIA = '_demie_g_media';
const DEMIE_G_META_DESC  = '_demie_g_desc';

/**
 * True inside admin UI contexts where the media list must be readable
 * regardless of post status (list-table cover/count, metabox builder, save
 * handler). AJAX never counts as admin here.
 */
function demie_g_is_admin_context() {
    return is_admin() && !wp_doing_ajax() && !wp_doing_cron();
}

/**
 * Read + normalize the ordered media list for a gallery.
 *
 * @return array[] Each item: ['type' => 'attachment'|'embed', 'id'|'url' => …]
 */
function demie_g_get_media($gallery_id) {
    $gallery_id = (int) $gallery_id;
    if (!$gallery_id) {
        return [];
    }

    // Only published galleries are renderable on the front end. Without this
    // guard a draft/private gallery's media would leak to visitors. Admin
    // screens (list table cover/count) are exempted.
    if (!demie_g_is_admin_context()) {
        $gallery = get_post($gallery_id);
        if (!$gallery || 'demie_gallery' !== $gallery->post_type || 'publish' !== $gallery->post_status) {
            return [];
        }
    }

    $raw = get_post_meta($gallery_id, DEMIE_G_META_MEDIA, true);
    if (is_string($raw) && '' !== $raw) {
        $decoded = json_decode($raw, true);
        $raw     = is_array($decoded) ? $decoded : [];
    } elseif (!is_array($raw)) {
        $raw = [];
    }

    return demie_g_sanitize_media_list($raw);
}

/**
 * Sanitize a raw media list. Accepts the JSON/meta shape and tolerates legacy
 * arrays; drops everything that is not a live attachment or an http(s) URL.
 */
function demie_g_sanitize_media_list($raw) {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw     = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }

    $items = [];
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $type = isset($entry['type']) ? sanitize_key($entry['type']) : '';

        if ('attachment' === $type) {
            $id = isset($entry['id']) ? absint($entry['id']) : 0;
            if (!$id || 'attachment' !== get_post_type($id) || 'trash' === get_post_status($id)) {
                continue; // dangling — deleted or never existed
            }
            $item = ['type' => 'attachment', 'id' => $id];
            if (!empty($entry['caption'])) {
                $item['caption'] = sanitize_text_field($entry['caption']);
            }
            $items[] = $item;
        } elseif ('embed' === $type) {
            $url = isset($entry['url']) ? esc_url_raw(trim((string) $entry['url'])) : '';
            if (!$url || !demie_g_is_allowed_embed_url($url)) {
                continue;
            }
            $items[] = ['type' => 'embed', 'url' => $url];
        }
        // Unknown types are dropped silently.
    }

    return $items;
}

/**
 * Allowed embed hosts (ADR-0002: YouTube/Vimeo via oEmbed). Filterable so a
 * site can allow other oEmbed providers if it really wants to.
 */
function demie_g_is_allowed_embed_url($url) {
    if (!preg_match('#^https?://#i', (string) $url)) {
        return false;
    }

    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!is_string($host)) {
        return false;
    }
    $host = strtolower(preg_replace('/^www\./i', '', $host));

    $allowed = (array) apply_filters('demie_g_allowed_embed_hosts', [
        'youtu.be',
        'youtube.com',
        'youtube-nocookie.com',
        'm.youtube.com',
        'music.youtube.com',
        'vimeo.com',
        'player.vimeo.com',
    ]);

    foreach ($allowed as $pattern) {
        if ($host === $pattern || str_ends_with($host, '.' . $pattern)) {
            return true;
        }
    }
    return false;
}

/**
 * Number of media items in a gallery (dangling items already filtered).
 * For admin list-table use; counts regardless of post status.
 */
function demie_g_count($gallery_id) {
    return count(demie_g_get_media($gallery_id));
}

/**
 * Media kind of one Gallery Media Item: photo|video.
 */
function demie_g_kind($item) {
    if (empty($item) || !is_array($item)) {
        return 'photo';
    }
    if ('embed' === $item['type']) {
        return 'video'; // embeds are always video
    }
    $attachment = get_post($item['id']);
    if (!$attachment) {
        return 'photo';
    }
    return wp_attachment_is('video', $attachment) ? 'video' : 'photo';
}

/**
 * Cover image URL for admin list + future uses: first attachment's thumbnail,
 * or the oEmbed thumbnail for an embed-first gallery.
 */
function demie_g_cover($gallery_id) {
    foreach (demie_g_get_media($gallery_id) as $item) {
        $resolved = demie_g_resolve_item($item);
        if (!empty($resolved['thumb'])) {
            return $resolved['thumb'];
        }
    }
    return '';
}

/**
 * Resolve one Gallery Media Item into render-ready data:
 *
 *   type      attachment|embed
 *   kind      photo|video
 *   id        attachment ID (0 for embeds)
 *   url       source page URL (embeds) or '' for attachments
 *   full      full/large image URL or video source URL (attachments)
 *   thumb     grid thumbnail URL (attachment thumb, video poster, or oEmbed thumb)
 *   title     caption text
 *   embed     oEmbed HTML for embeds ('' when it cannot be fetched)
 *
 * Everything returned is already escaped-safe (URLs via esc_url, titles plain
 * text to be esc_html'ed by templates).
 */
function demie_g_resolve_item($item) {
    $resolved = [
        'type'  => 'attachment',
        'kind'  => 'photo',
        'id'    => 0,
        'url'   => '',
        'full'  => '',
        'thumb' => '',
        'title' => '',
        'embed' => '',
    ];

    if (empty($item) || !is_array($item)) {
        return $resolved;
    }

    if ('embed' === $item['type']) {
        $resolved['type'] = 'embed';
        $resolved['kind'] = 'video';
        $resolved['url']  = isset($item['url']) ? $item['url'] : '';
        $resolved['title'] = $resolved['url'];

        // oEmbed: HTML + thumbnail. Short remote timeouts; failures degrade.
        $oembed = demie_g_oembed($resolved['url']);
        if ($oembed) {
            $resolved['embed'] = $oembed['html'];
            if (!empty($oembed['thumbnail_url'])) {
                $resolved['thumb'] = $oembed['thumbnail_url'];
            }
            if (!empty($oembed['title'])) {
                $resolved['title'] = $oembed['title'];
            }
        }
        if (empty($resolved['thumb'])) {
            $resolved['thumb'] = demie_g_video_placeholder();
        }
        return $resolved;
    }

    // Attachment.
    $id          = isset($item['id']) ? (int) $item['id'] : 0;
    $attachment  = $id ? get_post($id) : null;
    if (!$attachment || 'attachment' !== $attachment->post_type) {
        return $resolved;
    }

    $resolved['id']   = $id;
    $resolved['kind'] = demie_g_kind($item);
    $resolved['title'] = isset($item['caption']) && '' !== $item['caption']
        ? $item['caption']
        : get_the_title($attachment);

    if ('video' === $resolved['kind']) {
        // Uploaded video: poster thumbnail if set, else generic tile.
        $meta        = wp_get_attachment_metadata($id);
        $poster_id   = !empty($meta['poster_id']) ? (int) $meta['poster_id'] : 0;
        $resolved['thumb'] = $poster_id ? (string) wp_get_attachment_image_url($poster_id, 'medium_large') : demie_g_video_placeholder();
        $resolved['full']  = wp_get_attachment_url($id);
    } else {
        $large = wp_get_attachment_image_src($id, 'large');
        $full  = wp_get_attachment_image_src($id, 'full');
        $resolved['thumb'] = $large ? (string) $large[0] : '';
        $resolved['full']  = $full ? (string) $full[0] : ($large ? (string) $large[0] : '');
    }

    if (empty($resolved['thumb']) && $resolved['full']) {
        $resolved['thumb'] = $resolved['full'];
    }

    return $resolved;
}

/**
 * oEmbed fetch for embed items with a per-request cache. Returns
 * ['html' => string, 'thumbnail_url' => string, 'title' => string] or null.
 */
function demie_g_oembed($url) {
    $cache_key = 'demie_g_oembed_' . md5($url);
    $cached    = get_transient($cache_key);
    if (false !== $cached) {
        return is_array($cached) ? $cached : null;
    }

    $html = wp_oembed_get($url, ['discover' => true]);
    $data = null;

    if ($html) {
        $data = ['html' => $html, 'thumbnail_url' => '', 'title' => ''];

        // Pull the thumbnail + title out of the HTML as a lightweight
        // alternative to a second HTTP call to the provider's JSON API.
        if (preg_match('#<iframe[^>]+src=["\']([^"\']+)["\']#i', $html, $m)) {
            $src = html_entity_decode($m[1], ENT_QUOTES);
            // YouTube: https://www.youtube.com/embed/VIDEO_ID
            if (preg_match('#youtube\.com/embed/([\w-]+)#i', $src, $yt)) {
                $data['thumbnail_url'] = 'https://i.ytimg.com/vi/' . $yt[1] . '/hqdefault.jpg';
            }
        }
        if (empty($data['thumbnail_url']) && preg_match('#vimeo\.com/video/(\d+)#i', $url, $vm)) {
            // Vimeo oEmbed HTML has no thumbnail; fetch the player config.
            $json = demie_g_remote_vimeo_thumb((int) $vm[1]);
            if ($json) {
                $data['thumbnail_url'] = $json;
            }
        }
        if (preg_match('#<(?:iframe|a)[^>]*>([^<]{2,120})<#i', $html, $t)) {
            $data['title'] = wp_strip_all_tags($t[1]);
        }
    }

    set_transient($cache_key, $data ? $data : 0, 12 * HOUR_IN_SECONDS);
    return $data;
}

/**
 * Vimeo thumbnail via the oEmbed endpoint (player URL → canonical oEmbed).
 */
function demie_g_remote_vimeo_thumb($video_id) {
    $response = wp_safe_remote_get(
        'https://vimeo.com/api/oembed.json?url=' . rawurlencode('https://vimeo.com/' . $video_id),
        ['timeout' => 5]
    );
    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
        return '';
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return is_array($body) && !empty($body['thumbnail_url']) ? $body['thumbnail_url'] : '';
}

/**
 * Generic play-icon tile used when a video has no poster/thumbnail.
 */
function demie_g_video_placeholder() {
    return apply_filters('demie_g_video_placeholder', DEMIE_G_URI . 'assets/img/video-placeholder.svg');
}

/**
 * Latest published gallery ID (date DESC fallback), or 0.
 */
function demie_g_latest_gallery_id() {
    $posts = get_posts([
        'post_type'      => 'demie_gallery',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ]);
    return $posts ? (int) $posts[0] : 0;
}

/**
 * Resolve a gallery ID from an explicit id/slug, or the latest published one.
 */
function demie_g_resolve_gallery_id($atts) {
    if (!empty($atts['id'])) {
        $id  = absint($atts['id']);
        $post = $id ? get_post($id) : null;
        return ($post && 'demie_gallery' === $post->post_type && 'publish' === $post->post_status) ? $id : 0;
    }
    if (!empty($atts['slug'])) {
        $post = get_page_by_path((string) $atts['slug'], OBJECT, 'demie_gallery');
        return ($post && 'publish' === $post->post_status) ? (int) $post->ID : 0;
    }
    return demie_g_latest_gallery_id();
}
