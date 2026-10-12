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

## Check that the container works

```sh
./bin/wp eval 'echo get_class(apply_filters("achttienvijftien/container", null)), PHP_EOL;'
curl -s http://localhost:8080 | grep Havenpost
```

The second command should show the `<!-- Havenpost: Symfony service container is running -->`
comment that the demo service `HelloService` adds to the footer.

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

## Commands

```sh
./bin/wp plugin list                  # WP-CLI as the app user (chmod +x bin/* if needed)
./bin/composer require vendor/package
docker compose exec --user app php sh # shell (use --user app, not root)
docker compose down -v                # stop and delete the database (keeps ./app)
```

## Notes

- Development only: default passwords and salts, `WP_ENV=development`, no TLS, no page cache.
- Not tested in my sandbox (no Docker or network there); the first run will show if anything needs adjusting.
- `achttienvijftien/wp-turbo-bundle` is not installed. It requires PHP 8.3 (already used here) and
  must be listed in `config/bundles.php`.
