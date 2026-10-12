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
