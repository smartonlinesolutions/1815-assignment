<?php

declare(strict_types=1);

namespace Havenpost\Article;

use Havenpost\Subscription\SubscriptionChecker;

/**
 * Premium articles: anonymous visitors only get the intro (the post excerpt),
 * subscribers get the whole article.
 *
 * The protection lives here and not in the theme, so it also covers the REST API,
 * feeds and any other theme. It fails closed: when in doubt, the visitor gets the paywall.
 */
final class PremiumGate
{
    public const META_KEY = '_havenpost_premium';

    private bool $building_excerpt = false;

    public function __construct(
        private readonly SubscriptionChecker $subscriptions,
    ) {
    }

    public function register(): void
    {
        // Editor UI: a "Premium" checkbox next to the post.
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post_post', [$this, 'save_meta_box']);

        // Protection.
        add_filter('the_content', [$this, 'gate_content'], 1);
        add_filter('get_the_excerpt', [$this, 'begin_excerpt'], 9);
        add_filter('get_the_excerpt', [$this, 'end_excerpt'], 11);
        add_action('template_redirect', [$this, 'send_cache_headers']);

        // Presentation: a "Premium" badge after the title.
        add_filter('render_block_core/post-title', [$this, 'add_badge'], 10, 3);

        // Admin: a "Premium" column in the posts overview.
        add_filter('manage_post_posts_columns', [$this, 'add_column']);
        add_action('manage_post_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_action('admin_head-edit.php', [$this, 'column_styles']);
    }

    public function is_premium(int $post_id): bool
    {
        return '1' === get_post_meta($post_id, self::META_KEY, true);
    }

    /**
     * Replaces the article body with a paywall notice unless the visitor may read it.
     * The intro is shown separately by the template (post excerpt block).
     */
    public function gate_content(string $content): string
    {
        // WordPress builds a missing excerpt by running the_content and trimming it to
        // ~55 words. Let that through: it is the short teaser, not the article.
        if ($this->building_excerpt) {
            return $content;
        }

        $post = get_post();
        if (!$post instanceof \WP_Post || !$this->is_premium($post->ID)) {
            return $content;
        }

        // Newsroom staff (real WordPress users) can always read and preview.
        if (current_user_can('edit_post', $post->ID) || $this->subscriptions->is_subscriber()) {
            return $content;
        }

        return $this->paywall();
    }

    public function begin_excerpt(string $excerpt): string
    {
        $this->building_excerpt = true;

        return $excerpt;
    }

    public function end_excerpt(string $excerpt): string
    {
        $this->building_excerpt = false;

        return $excerpt;
    }

    /**
     * A subscriber's version of a premium article must never end up in a shared cache.
     * The anonymous version is identical for everyone and can be cached as usual.
     */
    public function send_cache_headers(): void
    {
        if (!is_singular('post') || !$this->is_premium(get_queried_object_id())) {
            return;
        }

        if ($this->subscriptions->is_subscriber()) {
            header('Cache-Control: private, no-store, max-age=0');
        }
    }

    public function add_badge(string $html, array $block, \WP_Block $instance): string
    {
        $post_id = (int) ($instance->context['postId'] ?? 0);
        if ($post_id < 1 || '' === $html || !$this->is_premium($post_id)) {
            return $html;
        }

        $badge = sprintf(' <span class="havenpost-badge">%s</span>', esc_html__('Premium', 'havenpost'));

        return preg_replace('#</h([1-6])>#', $badge . '</h$1>', $html, 1) ?? $html;
    }

    /**
     * Adds a "Premium" column right after the title in Posts > All Posts.
     *
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public function add_column(array $columns): array
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ('title' === $key) {
                $result['havenpost_premium'] = __('Premium', 'havenpost');
            }
        }

        // Another plugin removed the title column: append instead.
        return isset($result['havenpost_premium'])
            ? $result
            : $result + ['havenpost_premium' => __('Premium', 'havenpost')];
    }

    public function render_column(string $column, int $post_id): void
    {
        if ('havenpost_premium' !== $column) {
            return;
        }

        if ($this->is_premium($post_id)) {
            printf(
                '<span class="dashicons dashicons-star-filled" style="color:#dba617" aria-hidden="true"></span> %s',
                esc_html__('Premium', 'havenpost')
            );

            return;
        }

        printf(
            '<span aria-hidden="true">&ndash;</span><span class="screen-reader-text">%s</span>',
            esc_html__('Free', 'havenpost')
        );
    }

    public function column_styles(): void
    {
        echo '<style>.column-havenpost_premium{width:90px}</style>';
    }

    public function add_meta_box(): void
    {
        add_meta_box(
            'havenpost-premium',
            __('Premium', 'havenpost'),
            [$this, 'render_meta_box'],
            'post',
            'side',
            'high'
        );
    }

    public function render_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('havenpost_premium', 'havenpost_premium_nonce');

        printf(
            '<label><input type="checkbox" name="havenpost_premium" value="1" %s> %s</label>',
            checked($this->is_premium($post->ID), true, false),
            esc_html__('Premium article: anonymous visitors only see the intro (excerpt).', 'havenpost')
        );
    }

    public function save_meta_box(int $post_id): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $nonce = isset($_POST['havenpost_premium_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['havenpost_premium_nonce']))
            : '';

        if (!wp_verify_nonce($nonce, 'havenpost_premium') || !current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!empty($_POST['havenpost_premium'])) {
            update_post_meta($post_id, self::META_KEY, '1');
        } else {
            delete_post_meta($post_id, self::META_KEY);
        }
    }

    private function paywall(): string
    {
        $url = apply_filters('havenpost_subscribe_url', home_url('/subscribe/'));

        return sprintf(
            '<aside class="havenpost-paywall"><p><strong>%s</strong></p><p>%s</p><p><a class="wp-element-button" href="%s">%s</a></p></aside>',
            esc_html__('The rest of this article is for subscribers.', 'havenpost'),
            esc_html__('Subscribe to keep reading. Already a subscriber? Sign in with your subscription account.', 'havenpost'),
            esc_url($url),
            esc_html__('Subscribe', 'havenpost')
        );
    }
}
