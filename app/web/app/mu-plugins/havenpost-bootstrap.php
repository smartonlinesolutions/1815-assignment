<?php
/**
 * Plugin Name: Havenpost bootstrap
 * Description: Wires services from the Symfony container into WordPress.
 */

// Fired by achttienvijftien/service-container on muplugins_loaded.
add_action('achttienvijftien/container_booted', static function ($container): void {
    $container->get(\Havenpost\Hello\HelloService::class)->register();
    $container->get(\Havenpost\Article\PremiumGate::class)->register();
});
