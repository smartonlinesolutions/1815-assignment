vcl 4.1;

backend default {
    .host = "nginx";
    .port = "80";

    .connect_timeout = 5s;
    .first_byte_timeout = 30s;
    .between_bytes_timeout = 30s;
}

sub vcl_recv {
    # Only cache safe, read-only requests.
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Never cache requests with authorization credentials.
    if (req.http.Authorization) {
        return (pass);
    }

    # Bypass WordPress admin, login, previews and API requests.
    if (req.url ~ "^/wp-admin(?:/|$)"
        || req.url ~ "^/wp-login\.php"
        || req.url ~ "[?&]preview="
        || req.url ~ "^/wp-json(?:/|$)"
        || req.url ~ "^/wp-cron\.php"
        || req.url ~ "^/xmlrpc\.php") {
        return (pass);
    }

    # Common WooCommerce routes.
    if (req.url ~ "^/(cart|checkout|my-account)(?:/|$)") {
        return (pass);
    }

    # Bypass WordPress authenticated and personalized sessions.
    if (req.http.Cookie ~ "(wordpress_logged_in_|wordpress_sec_|wp-postpass_|comment_author_|woocommerce_items_in_cart|woocommerce_cart_hash)") {
        return (pass);
    }

    # Avoid caching query-string URLs during initial development.
    if (req.url ~ "\?") {
        return (pass);
    }

    return (hash);
}

sub vcl_backend_response {
    # Never cache a response that sets cookies.
    if (beresp.http.Set-Cookie) {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Cache only successful public HTML responses.
    if (beresp.status != 200
        || beresp.http.Content-Type !~ "text/html") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Respect origin cache-control directives, including
    # private, no-store and no-cache.
    if (beresp.http.Cache-Control ~ "(?i)(private|no-store|no-cache)") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Short lifetime suitable for local development.
    set beresp.ttl = 60s;
    set beresp.grace = 10s;
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }

    # Remove internal diagnostics from the response.
    unset resp.http.X-Varnish;
    unset resp.http.Via;
}