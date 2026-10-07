Planning:
45 min: Part 1: Code review

Part 2: Build
5 min: prioriteiten bepalen
165 min: uitvoeren part 2

25 min: Part 3: Design note


Prioritering deel 2 (Musts):
8) The whole stack starts with a single command, such as docker compose up or wp-env start. 
1) A theme or plugin with an article page and a category page listing the 20 latest articles. On premium articles, anonymous visitors only see the intro; subscribers see everything.
3) At least two cache layers: an HTTP cache (reverse proxy) and WordPress's object cache with a persistent backend, such as Memcached with our wp-memcached drop-in. Invalidation is targeted: no flushing everything.
4) Invalidation handles at least publishing, updating, unpublishing, deleting, slug changes and category changes.
5) Premium content never reaches anonymous visitors through any cache layer.
6) Corrections show up everywhere within 30 seconds, with at least two PHP containers running.

Later:
2) Your own logic runs as services in the container, configured in config/services.yaml.
7) Automated tests proving that 5 and 6 hold.


Tijd gespendeerd:
- 45 minuten Part 1: code Review
- 15 minuten Docker installeren en configureren (extra)
- 30 minuten Docker installatie maken voor project

Notities:
- Wat hulp ingeschakeld van Claude om een Docker setup te maken (zelf geen ervaring mee)
