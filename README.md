# Havenpost: WordPress + Symfony service container in Docker

Local development setup: nginx, PHP 8.3-FPM, MariaDB. WordPress runs as a Composer project
([Roots Bedrock](https://roots.io/bedrock/)) with
[`achttienvijftien/service-container`](https://github.com/achttienvijftien/service-container),
the Symfony DI container for WordPress.

## Start

```sh
docker compose up --build
```

The first start takes a few minutes: the `php` container scaffolds Bedrock into `./app`,
installs WordPress and the service container with Composer, and runs `wp core install`.
Follow it with `docker compose logs -f php` (nginx answers 502 until it says `Ready`).

- Site: http://localhost:8080
- Admin: http://localhost:8080/wp/wp-admin (`admin` / `admin`)

Later starts skip everything that is already done. Optional overrides: copy `.env.example` to `.env`.
On Linux set `HOST_UID`/`HOST_GID` (`id -u`, `id -g`) and rebuild, so files in `./app` are yours.

## Where things live (in `./app`)

| Path | Purpose |
| --- | --- |
| `config/services.yaml` | Service definitions; `Havenpost\` is autowired from `src/` |
| `src/` | Your PHP services (PSR-4 namespace `Havenpost\`) |
| `web/app/mu-plugins/havenpost-bootstrap.php` | Gets services from the container on `achttienvijftien/container_booted` |
| `var/cache` | Compiled container (git-ignored) |

The container reads `config/services.yaml`, `config/parameters/<env>.yaml`,
`config/packages/*.yaml` and `config/bundles.php`. In `development` (`WP_ENV`) it recompiles
automatically when config changes; in other environments clear `var/cache` after changing it.

## Caching

Havenpost uses two separate caching layers.

### Varnish: full-page cache

Varnish caches eligible public HTML responses and is accessed through http://localhost:8080.

The current Varnish configuration uses a 60-second TTL and 10 seconds of grace. Requests containing certain authentication cookies, authorization headers, excluded paths, or query strings bypass the cache. Responses that set cookies or have unsuitable status, content type, or cache-control headers are also not cached.

The X-Cache response header indicates whether Varnish served a cached object (HIT) or did not serve one (MISS). A MISS does not guarantee that the response was stored.

If Varnish configuration changes, restart the service:

`docker compose restart varnish`

### Memcached: WordPress object cache

Memcached stores individual objects used by WordPress, rather than complete HTML pages. PHP connects to the Memcached service at memcached:11211 on the internal Docker network.

Verify that the PHP extension is loaded:

`docker compose exec php php -m | grep -i '^memcached$'`

Verify that WordPress reports a persistent object cache:

`docker compose exec --user app php wp eval 'var_dump(wp_using_ext_object_cache());'`

A result of bool(true) indicates that WordPress is using a persistent object-cache implementation. The object-cache.php drop-in must be installed in the active WordPress content directory and configured correctly.

### Cache invalidation

Varnish and Memcached are independent layers. Clearing or invalidating one does not automatically clear the other.

If the Havenpost cache-invalidation plugin and Varnish PURGE handling are configured, content changes should invalidate affected page URLs. Verify that publication, updates, unpublishing, deletion, slug changes, and category changes invalidate all relevant URLs.