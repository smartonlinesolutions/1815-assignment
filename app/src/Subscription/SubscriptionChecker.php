<?php

declare(strict_types=1);

namespace Havenpost\Subscription;

/**
 * Decides whether the current visitor is a subscriber.
 *
 * Subscribers are not WordPress users: the external subscription system issues a
 * token that arrives in a cookie. No cookie means no outbound call, so anonymous
 * traffic never touches the external system.
 */
final class SubscriptionChecker
{
    private const CACHE_GROUP = 'havenpost';
    private const POSITIVE_TTL = 60; // seconds a valid token is trusted
    private const NEGATIVE_TTL = 15; // also used when the provider fails (fail closed, don't hammer it)

    private ?bool $subscriber = null;

    public function is_subscriber(): bool
    {
        return $this->subscriber ??= $this->check();
    }

    private function check(): bool
    {
        $token = $this->token_from_cookie();
        if (null === $token) {
            return false;
        }

        // Local development shortcut so you can test without the real provider.
        $dev_token = getenv('HAVENPOST_DEV_SUBSCRIBER_TOKEN');
        if (
            is_string($dev_token) && '' !== $dev_token
            && in_array(wp_get_environment_type(), ['local', 'development'], true)
            && hash_equals($dev_token, $token)
        ) {
            return true;
        }

        return $this->validate_with_provider($token);
    }

    private function token_from_cookie(): ?string
    {
        $name = getenv('HAVENPOST_SUBSCRIPTION_COOKIE');
        $name = is_string($name) && '' !== $name ? $name : 'havenpost_token';

        if (!isset($_COOKIE[$name]) || !is_string($_COOKIE[$name])) {
            return null;
        }

        $token = trim(wp_unslash($_COOKIE[$name]));
        if ('' === $token || strlen($token) > 2048 || 1 !== preg_match('/^[A-Za-z0-9._~+\/=-]+$/', $token)) {
            return null;
        }

        return $token;
    }

    /**
     * PLACEHOLDER: adapt to the real subscription API.
     * Assumes: POST with "Authorization: Bearer <token>", answering 200 {"active": true}.
     * Fails closed: any error means "not a subscriber".
     * Results are cached with the object cache, which only persists across requests
     * when a persistent backend (e.g. Redis) is installed.
     */
    private function validate_with_provider(string $token): bool
    {
        $url = getenv('HAVENPOST_SUBSCRIPTION_API_URL');
        if (!is_string($url) || '' === $url) {
            return false;
        }

        $key = 'sub_' . hash('sha256', $token);
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if (false !== $cached) {
            return '1' === $cached;
        }

        $response = wp_remote_post($url, [
            'timeout'     => 2,
            'redirection' => 0,
            'headers'     => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        $valid = false;
        $ttl = self::NEGATIVE_TTL;

        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($data) && true === ($data['active'] ?? false)) {
                $valid = true;
                $ttl = self::POSITIVE_TTL;
            }
        }

        wp_cache_set($key, $valid ? '1' : '0', self::CACHE_GROUP, $ttl);

        return $valid;
    }
}
