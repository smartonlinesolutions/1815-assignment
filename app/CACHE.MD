The browser inspector can help you identify Varnish, but Memcached is a server-side cache, so you usually need to check it through WordPress/PHP or Docker rather than directly in the browser.
1. Check Varnish in your browser
1. Open your site at http://localhost:8080.
2. Open DevTools → Network.
3. Select the main document request (usually /).
4. Open Headers → Response Headers.
5. Look for X-Cache.
Your VCL sets this header:
Header	Meaning
X-Cache: HIT	Varnish served a cached response.
X-Cache: MISS	Varnish fetched the response from the backend.
Try reloading the same page twice. If the response is cacheable, the second request may show HIT.
If you don't see X-Cache, check that the response passed through Varnish at port 8080 and that your vcl_deliver logic is running. Requests bypassed with return(pass) can still receive X-Cache: MISS with your current VCL, so MISS alone doesn't prove that an object was stored.
2. Check Memcached
Memcached stores WordPress objects, such as cached database query results and transients. It does not ordinarily add a header to the browser response.
You can test whether WordPress is using a persistent object cache:
docker compose exec --user app php wp eval \
'var_dump(wp_using_ext_object_cache());'


Expected output:
bool(true)


That confirms WordPress is configured to use a persistent object cache, but it doesn't alone prove that a particular page request read from Memcached.
To verify the PHP Memcached client can read and write data:
docker compose exec php php -r '
$m = new Memcached();
$m->addServer("memcached", 11211);
var_dump($m->set("cache_inspector_test", "working", 60));
var_dump($m->get("cache_inspector_test"));
'


Expected output includes:
bool(true)
string(7) "working"


3. See the difference

Varnish — full-page cache
Visible in browser DevTools → Network → Response Headers as X-Cache: HIT or MISS.




Memcached — WordPress object cache
Verify through WordPress/PHP or Memcached statistics. A Varnish HIT won't execute PHP, so it won't use Memcached for that request.



Tip: To see both layers in action, request a cacheable page twice and check X-Cache. Then temporarily bypass Varnish for a request or purge the page; when PHP executes, WordPress can use Memcached for its underlying object-cache lookups.