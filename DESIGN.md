# Notities:
- Wat hulp ingeschakeld van Claude om een Docker setup te maken en hoe te werken met de Service Container, Varnish Cache en Memcached (zelf geen ervaring mee)
- Varnish cache werkte niet direct toen ik de Permalink Settings nog op Plain had staan, aangezien mijn Varnish configuratie URL's met een query string niet cached. Daarop heb ik de permalink settings aangepast naar Post name.
- Het installeren van Memcached in Docker bleef problemen geven bij de Docker compose. Dit heeft mij veel tijd gekost.

# Vragen:
1) Cache layers. For each layer: what it stores, the cache key, the TTL and how it gets invalidated.
- Varnish (full-page cache): slaat volledige HTML-pagina’s op, met als cache key de URL en host. De TTL is 60 seconden, met 10 seconden grace. Bij publicatie, wijzigingen, verwijderen, slugwijzigingen en categoriewijzigingen worden relevante URL’s via PURGE ongeldig gemaakt.
- Memcached (objectcache): slaat WordPress-objecten en queryresultaten op, met keys op basis van object, groep en eventuele salt. De TTL verschilt per object en wordt bepaald door WordPress of de cachecode. WordPress invalideert relevante objecten bij wijzigingen; de exacte werking hangt af van de gebruikte object-cache-drop-in.

2) Worst-case staleness. How stale can content get, per layer and end to end? Why is that acceptable?
Varnish kan content normaal maximaal 60 seconden bewaren, met mogelijk 10 seconden extra grace. Memcached heeft geen algemene maximale veroudering: die hangt af van de TTL en of invalidatie correct werkt. Bij succesvolle PURGE-invalidation is content doorgaans sneller bijgewerkt; de TTL vormt een vangnet als invalidatie mislukt. Dit is acceptabel voor een nieuwswebsite zolang updates snel zichtbaar worden en de TTL beperkt blijft.

3) Failure modes. What happens when the database, the object cache or the reverse proxy goes down?
Als de database uitvalt, kunnen WordPress-pagina’s die de database nodig hebben niet goed worden opgebouwd; Varnish kan tijdelijk bestaande pagina’s blijven leveren. Als Memcached uitvalt, kan WordPress normaal gesproken terugvallen op directe databasequeries, maar met hogere belasting. Als Varnish uitvalt, moet verkeer rechtstreeks naar Nginx worden omgeleid; zonder die fallback is de website tijdelijk onbereikbaar.

4) Scaling. What changes when breaking news takes you from 2 to 20 PHP containers?
Hier heb ik geen antwoord op, aangezien ik hier geen ervaring mee heb.

5) Trade-offs. What you deliberately left out, and what you'd do with more time, in order of priority.
In mijn PLANNING.md document had ik reeds een prioriteiten lijst gemaakt voor de Musts. Daarvan heb ik de eerste 4 punten ook op kunnen pakken. Het punt om te werken met de services in de container heb ik tijdens de ontwikkeling naar voren gehaald. 
Bewust heb ik de geautomatiseerde tests als laatste opgenomen in de planning (en dus ook niet opgepakt nu), daar dit los staat van de functionaliteit van de website. Punten 5 en 6 heb ik nog niet op kunnen pakken, maar dat zouden mijn eerstvolgende punten zijn om op te pakken als ik meer tijd zou hebben.

6) Time spent. How long each part took you.
- 45 minuten: Part 1: code Review
- 15 minuten: Docker installeren en configureren

- 30 minuten: Docker installatie maken voor project (The whole stack starts with a single command, such as docker compose up or wp-env start)
- 30 minuten: A theme or plugin with an article page and a category page listing the 20 latest articles. On premium articles, anonymous visitors only see the intro; subscribers see everything.

- 15 minuten: Your own logic runs as services in the container, configured in config/services.yaml.
- 45 minuten: Implement HTTP cache (reverse proxy) with Varnish.

- 30 minuten: Implement Memcached drop-in 
- 45 minuten: Implemented and tested invalidation
- 15 minuten: Invullen DESIGN.md