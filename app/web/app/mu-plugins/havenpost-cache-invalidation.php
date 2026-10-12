<?php
/**
 * Plugin Name: Havenpost Cache Invalidation
 * Description: Purges affected Varnish URLs when WordPress content changes.
 */

defined('ABSPATH') || exit;

/**
 * Purge a URL from the Varnish service.
 *
 * The Host header must match the host used by visitors, because
 * Varnish commonly includes the host in its cache key.
 */
function havenpost_purge_varnish_urls(array $urls): void
{
    $home = wp_parse_url(home_url('/'));

    if (empty($home['host'])) {
        return;
    }

    $host = $home['host'];

    if (!empty($home['port'])) {
        $host .= ':' . $home['port'];
    }

    $urls = array_unique(array_filter($urls));

    foreach ($urls as $url) {
        $parts = wp_parse_url($url);

        if (empty($parts['path'])) {
            continue;
        }

        $path = $parts['path'];

        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $response = wp_remote_request(
            'http://varnish' . $path,
            [
                'method'      => 'PURGE',
                'headers'     => [
                    'Host' => $host,
                ],
                'timeout'     => 2,
                'blocking'    => false,
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(
                'Havenpost Varnish purge failed: ' .
                $response->get_error_message()
            );
        }
    }
}

/**
 * Collect the post permalink, home page, relevant category archives,
 * and public post-type archive.
 */
function havenpost_post_cache_urls(
    int $post_id,
    ?WP_Post $post = null,
    ?string $old_permalink = null
): array {
    $post = $post ?: get_post($post_id);

    if (!$post || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return [];
    }

    $urls = [home_url('/')];

    if ($old_permalink) {
        $urls[] = $old_permalink;
    }

    // Include the current permalink for public content.
    if (is_post_type_viewable($post->post_type)) {
        $urls[] = get_permalink($post_id);
    }

    // Category archives affected by this post.
    if ($post->post_type === 'post') {
        $term_ids = wp_get_object_terms(
            $post_id,
            'category',
            ['fields' => 'ids']
        );

        if (!is_wp_error($term_ids)) {
            foreach ($term_ids as $term_id) {
                $term_link = get_term_link((int) $term_id, 'category');

                if (!is_wp_error($term_link)) {
                    $urls[] = $term_link;
                }
            }
        }
    }

    // Public custom post-type archives, if applicable.
    $post_type = get_post_type_object($post->post_type);

    if ($post_type && is_post_type_viewable($post_type) && $post_type->has_archive) {
        $archive = get_post_type_archive_link($post->post_type);

        if ($archive) {
            $urls[] = $archive;
        }
    }

    return $urls;
}

/**
 * Published content updates, including changes to the slug.
 */
add_action(
    'post_updated',
    function (int $post_id, WP_Post $post_after, WP_Post $post_before): void {
        $old_permalink = get_permalink($post_before);

        $urls = havenpost_post_cache_urls(
            $post_id,
            $post_after,
            $old_permalink ?: null
        );

        havenpost_purge_varnish_urls($urls);
    },
    10,
    3
);

/**
 * Publish/unpublish transitions. Capture the old URL before deletion
 * or status changes can make it unavailable.
 */
add_action(
    'transition_post_status',
    function (string $new_status, string $old_status, WP_Post $post): void {
        if ($new_status === $old_status) {
            return;
        }

        $urls = havenpost_post_cache_urls((int) $post->ID, $post);

        havenpost_purge_varnish_urls($urls);
    },
    10,
    3
);

/**
 * Delete while the original permalink and taxonomy relationships exist.
 */
add_action(
    'before_delete_post',
    function (int $post_id, WP_Post $post): void {
        $urls = havenpost_post_cache_urls((int) $post_id, $post);

        havenpost_purge_varnish_urls($urls);
    },
    10,
    2
);

/**
 * Category assignments changed. Purge the post, current category
 * archives, and home page.
 */
add_action(
    'set_object_terms',
    function (
        int $object_id,
        $terms,
        array $tt_ids,
        string $taxonomy,
        bool $append,
        array $old_tt_ids
    ): void {
        if ($taxonomy !== 'category') {
            return;
        }

        $urls = havenpost_post_cache_urls($object_id);

        // Include category archives that the post used to belong to.
        foreach ($old_tt_ids as $tt_id) {
            $old_term = get_term_by('term_taxonomy_id', $tt_id, 'category');

            if ($old_term && !is_wp_error($old_term)) {
                $link = get_term_link($old_term);

                if (!is_wp_error($link)) {
                    $urls[] = $link;
                }
            }
        }

        havenpost_purge_varnish_urls($urls);
    },
    10,
    6
);
