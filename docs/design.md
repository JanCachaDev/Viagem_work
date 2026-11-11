# Návrh: Mapa parcel okresu Jičín

## Cíl

Webová aplikace, která zobrazí všechny parcely okresu Jičín na mapě a po kliknutí
ukáže informace o parcele. Hlavní kritérium je **plynulost i při pohledu na celý okres**.

## Rozhodnutí

| Otázka | Rozhodnutí | Proč |
|---|---|---|
| Rozsah | celý okres Jičín (RÚIAN kód okresu 3604, ~110 obcí) | zadání to oceňuje; architektura je stejná pro 4 KÚ i 110 obcí |
| Data live vs. předem | **předem** z výměnného formátu RÚIAN (VFR, soubory `OB_<kód>_UKSH`) | WFS ČÚZK vrací jen jednotky objektů na dotaz (`max. 1` pro bbox dotaz) – celý okres z něj zobrazit nejde |
| Live data | jen detail jedné parcely přes INSPIRE WFS (`GetFeatureById`) s cache | ukáže aktuální stav a porovná ho s importem |
| Úložiště | PostgreSQL + PostGIS (Docker) | prostorové indexy, `ST_AsMVT`, `ST_CurveToLine` – standardní nástroje, PHP zůstává tenké |
| Přenos geometrie | Mapbox Vector Tiles generované PostGISem | prohlížeč dostává jen to, co je vidět, v rozlišení odpovídajícím zoomu |
| Cache dlaždic | soubory na disku, nginx je servíruje přímo (`try_files`), PHP jen při cache miss | druhé zobrazení dlaždice nezatíží PHP ani DB; `bin/warm-tiles.php` umí cache předgenerovat |
| Backend | čisté PHP 8.3, vlastní malý router, PDO, Composer jen pro autoload a PHPUnit | zadání preferuje čisté PHP; aplikace má 4 endpointy, framework by byl balast |
| Frontend | MapLibre GL JS, jedna statická stránka bez build kroku | WebGL vykreslení vektorových dlaždic je rychlé; bez buildu jednoduché spuštění |

## Architektura

```
prohlížeč (MapLibre) ──► nginx ──► var/cache/tiles/{z}/{x}/{y}.pbf  (hit)
                              └──► php-fpm public/index.php           (miss / API)
                                        └──► PostGIS
                                        └──► WFS ČÚZK (live detail)
bin/import.php ──► vdp.cuzk.cz (VFR + číselníky) ──► PostGIS
```

### Import (`bin/import.php`)
1. Stáhne číselníky ISKN (druh pozemku, způsob využití, způsob ochrany, BPEJ s úřední cenou).
2. Ze státního souboru `ST_UZSZ` vybere obce okresu 3604 (nebo použije obce zadané parametrem).
3. Pro každou obec stáhne `OB_<kód>_UKSH.xml.zip`, proudově ho čte přes `XMLReader`
   a ukládá katastrální území a parcely. GML geometrie (včetně oblouků `ArcString`/`Circle`)
   převádí PHP na WKT s křivkami a PostGIS je linearizuje (`ST_CurveToLine`).
4. Spočítá hranici okresu, smaže cache dlaždic.

### Dlaždice
- vrstva `parcels` od zoomu 9; pod zoomem 14 jen parcely větší než ~2×2 px se zjednodušenou
  geometrií, takže celý okres vypadá jako mapa kultur a dlaždice zůstávají malé,
- vrstva `cadastral_areas` (hranice a názvy KÚ),
- vrstva `district` (hranice okresu).
- zdroj má `maxzoom 15`, vyšší zoomy MapLibre „overzoomuje“ (přesnost ~ 0,3 m).

### API
- `GET /tiles/{z}/{x}/{y}.pbf`
- `GET /api/parcels/{id}` – detail (KÚ, obec, číslo, výměra, druh, využití, ochrany, BPEJ, orientační úřední cena)
- `GET /api/parcels/{id}/live` – porovnání s WFS ČÚZK
- `GET /api/search?q=Jičín 1234/5` – vyhledání parcely
- `GET /api/meta` – legenda (druhy pozemku), datum dat, počty

### Frontend
Barvy podle druhu pozemku + legenda s filtrem, hover, panel detailu, vyhledávání,
přepínač podkladu (OSM, ortofoto ČÚZK, katastrální mapa WMS ČÚZK).

## Testování
PHPUnit: převod GML→WKT, parser VFR (na malém vzorovém XML), router, souřadnice dlaždic,
parser vyhledávacího dotazu. Ruční/Playwright ověření v prohlížeči nad celým okresem.
