<?php

declare(strict_types=1);

namespace Havenpost\Hello;

/**
 * Demo service: proves the Symfony container is booted and wired into WordPress.
 * Replace it with your own services.
 */
final class HelloService
{
    public function register(): void
    {
        add_action('wp_footer', static function (): void {
            echo "\n<!-- Havenpost: Symfony service container is running -->\n";
        });
    }
}
