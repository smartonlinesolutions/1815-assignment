<?php
/**
 * Havenpost child theme of Twenty Twenty-Five.
 *
 * Presentation only. Premium protection and subscriber checks live in the services
 * Havenpost\Article\PremiumGate and Havenpost\Subscription\SubscriptionChecker.
 */

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'havenpost-theme',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get('Version')
    );
});

// Category page: the 20 latest articles, newest first, without pagination.
add_action('pre_get_posts', static function (WP_Query $query): void {
    if (is_admin() || !$query->is_main_query() || !$query->is_category()) {
        return;
    }

    $query->set('posts_per_page', 20);
    $query->set('orderby', 'date');
    $query->set('order', 'DESC');
    $query->set('no_found_rows', true); // no total count needed without pagination
});
