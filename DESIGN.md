Notities:
- Wat hulp ingeschakeld van Claude om een Docker setup te maken (zelf geen ervaring mee)
- Varnish cache werkte niet direct toen ik de Permalink Settings nog op Plain had staan, aangezien Varnish URL's met een query string niet cached. Daarop heb ik de permalink settings aangepast naar Post name.


1) Cache layers. For each layer: what it stores, the cache key, the TTL and how it gets invalidated.

2) Worst-case staleness. How stale can content get, per layer and end to end? Why is that acceptable?

3) Failure modes. What happens when the database, the object cache or the reverse proxy goes down?

4) Scaling. What changes when breaking news takes you from 2 to 20 PHP containers?

5) Trade-offs. What you deliberately left out, and what you'd do with more time, in order of priority.

6) Time spent. How long each part took you.
- 45 minuten: Part 1: code Review
- 15 minuten: Docker installeren en configureren
- 30 minuten: Docker installatie maken voor project (The whole stack starts with a single command, such as docker compose up or wp-env start)
- 30 minuten: A theme or plugin with an article page and a category page listing the 20 latest articles. On premium articles, anonymous visitors only see the intro; subscribers see everything.
- 45 minuten: Implement HTTP cache (reverse proxy) with Varnish.