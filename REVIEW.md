# Snippet A: WordPress plugin
1) Kritiek: een database update call op elke paginaweergave die PHP bereikt (via de 'wp' hook)
Wat is verkeerd: Elke single post doet een read request op de postmeta en daarna een update request. Dat is een update per weergave.
Wat betekent dit voor Havenpost: Op piekmomenten wordt de server overbelast door de vele update calls. 
Wat zou ik veranderen: Het tellen van de weergave op een andere manier regelen.

2) Kritiek: de populaire lijst verloopt nooit en wordt enkel geinvalideert op de save_post
Wat is verkeerd: De functie set_transient heeft geen verlooptijd en de enige invalidatie is save_post. Een bericht geforceerd verwijderen triggert save_post niet. 
Wat betekent dit voor Havenpost: Een artikel dat is gedepubliceerd of verwijderd, mogelijk om juridische redenen, kan als kop en link in de zijbalk "populair" op elke pagina blijven staan zolang die pagina's in de cache zitten. Buiten opslagacties om verandert de lijst bovendien nooit, dus "populair" is het in feite niet.
Wat zou ik veranderen: Ik zou de lijst een korte TTL geven en een artikel expliciet uit de lijst halen bij een depublicatie of verwijdering. 

3) Middel: save_post is de verkeerde hook, dus purges zijn ruis en kunnen te vroeg afgaan
Wat is verkeerd: De hook save_post wordt afgevuurd voor revisies, autosaves, auto-drafts, concepten en elk berichttype, vaak meerdere keren per opslagactie, en kan draaien voordat andere plugins hun meta en termen hebben opgeslagen.
Wat betekent dit voor Havenpost: De proxy wordt overspoeld met purges voor URL's die nooit publiek waren.
Wat zou ik veranderen: Hook op wp_after_insert_post of transition_post_status, exit bij revisies en autosaves en beperk tot de publieke berichttypen.


# Snippet B: Turbo Frame controller
1) Kritiek: de abonneebeslissing zit in een publiek gedeelde cache
Wat er mis is: Zowel de volledige tekst als de paywall gaan met public, s-maxage=3600 en zonder Vary de proxy in, dus de eerste bezoeker bepaalt wat iedereen een uur krijgt. De browser houdt het antwoord bovendien 5 minuten vast.

Wat dit betekent voor Havenpost. Of de premium-tekst lekt naar alle anonieme bezoekers, of betalende abonnees krijgen de paywall.

Wat ik zou veranderen. Cache de paywall publiek, houd abonneeverzoeken buiten de gedeelde cache en valideer het token op de origin met Cache-Control: private.


# Snippet C: Varnish configuration
Voor deze snippet heb ik geen reviews genoteerd, daar ik geen ervaring heb met Varnish caching en ik mij hier vanwege de beperkte tijd voor deze opdracht niet verder in verdiept heb.