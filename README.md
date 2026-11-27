# GPX-TrackMap

**Français** · [English](#english)

Plugin de contenu pour **Joomla 6** : affiche des traces GPX sur une carte interactive [Leaflet](https://leafletjs.com), avec graphiques (altitude, pente, vitesse), photos géolocalisées et bouton de téléchargement.

## Fonctions

- Carte OpenStreetMap et autres fonds (OpenTopoMap, ESRI, IGN, Waymarked Trails, OpenSeaMap…)
- Traces, routes et waypoints, avec icônes de waypoints
- Couleur de la trace selon l'altitude, la pente, la vitesse, la fréquence cardiaque, la cadence ou la température, avec barre de dégradé
- Flèches de direction sur les traces
- Dossier de GPX : un repère par trace (départ, arrivée, sommet ou waypoint au choix) ; un clic affiche la trace et ses graphiques
- Couleurs de la trace reprises sur les graphiques, et intensité de la couleur selon les données
- Graphiques d'altitude, de pente et de vitesse liés à la carte (le pointeur déplace un marqueur sur la carte)
- Photos géolocalisées
- Plein écran, recentrage, localisation
- Modèles d'affichage : carte simple, graphiques sur le côté, tous les fichiers d'un dossier
- Accord du visiteur avant le chargement des fonds de carte externes (option)
- Textes en français, anglais, allemand, néerlandais et espagnol, avec choix de la langue dans l'administration

## Prérequis

- Joomla 6
- PHP 8.3 ou plus

## Installation

1. Téléchargez `plg_content_gpxtm_vX.Y.Z_J6.zip` dans la page [Releases](../../releases) (le zip joint à la release, pas « Source code »).
2. Dans Joomla : **Système → Installer → Extensions**, puis envoyez le zip.
3. **Système → Plugins** : activez **Contenu - GPX-TrackMap**.
4. Dans l'onglet *Variables* du plugin, vous trouverez toutes les options.

Les mises à jour sont proposées automatiquement par Joomla (serveur de mises à jour intégré).

## Utilisation

Dans un article, placez le fichier `.gpx` dans le dossier prévu (réglable dans le plugin), puis écrivez :

```
{gpxtrackmap}mon-fichier.gpx{/gpxtrackmap}
```

Les réglages se placent après le nom du fichier, séparés par des points-virgules :

```
{gpxtrackmap}mon-fichier.gpx;Template=tmpl0.html{/gpxtrackmap}
{gpxtrackmap}mon-fichier.gpx;Arrowtrack=true{/gpxtrackmap}
{gpxtrackmap}mon-fichier.gpx;Trcolmod=h{/gpxtrackmap}
```

- `Template=tmpl0.html` : carte avec le profil d'altitude dessous.
- `Arrowtrack=true` : flèches de direction (`Arrowtrackcol=#ffffff` pour la couleur, `Arrowsymbol=➤` pour le symbole).
- `Trcolmod=h` : couleur de la trace selon l'altitude (autres valeurs : `s` pente, `v` vitesse, `hr` fréquence cardiaque, `cad` cadence, `atemp` température). `Trcolgraph=true` applique les mêmes couleurs aux graphiques ; `Gintensity=true` fait varier l'intensité de la couleur des graphiques selon les données.
- `Trackwpt=start` (avec `Template=tmpl-dossier.html` et un dossier) : un repère par fichier ; autres choix : `finish`, `highest`, `first`, `last`, `name` (avec `Trackwptname=sommet`).

La liste complète des variables et des modèles est dans l'onglet **Variables** de l'administration.

## Licences

- Code du plugin (PHP, JavaScript, CSS, modèles) : **GNU GPL v2 ou ultérieure**, © 2026 Perlimpin.fr
- [Leaflet](https://leafletjs.com) 1.9.4 : BSD 2-Clause
- Icônes de waypoints (SJJB) : domaine public
- Fonds de carte : respectez les conditions d'utilisation de chaque fournisseur (OpenStreetMap, ESRI, IGN, OpenTopoMap…).

Détails dans `LICENSE.txt` et `icons/LICENSE.txt`.

## Support

- Bogues et questions : [Issues](../../issues)
- Site : <https://www.perlimpinpin.fr>

---

<a id="english"></a>

# GPX-TrackMap (English)

[Français](#gpx-trackmap) · **English**

Content plugin for **Joomla 6** that displays GPX tracks on an interactive [Leaflet](https://leafletjs.com) map, with charts (elevation, slope, speed), geotagged photos and a download button.

## Features

- OpenStreetMap and other base maps (OpenTopoMap, ESRI, IGN, Waymarked Trails, OpenSeaMap…)
- Tracks, routes and waypoints, with waypoint icons
- Track colour by elevation, slope, speed, heart rate, cadence or temperature, with a gradient bar
- Direction arrows on tracks
- Folder of GPX files: one marker per track (start, finish, summit or a chosen waypoint); a click shows the track and its charts
- Track colours repeated on the charts, and colour intensity following the data
- Elevation, slope and speed charts linked to the map (the pointer moves a marker on the map)
- Geotagged photos
- Full screen, recentre and locate buttons
- Layouts: simple map, charts on the side, all files of a folder
- Visitor consent before loading external map tiles (optional)
- Texts in French, English, German, Dutch and Spanish, with a language setting in the administration

## Requirements

- Joomla 6
- PHP 8.3 or later

## Installation

1. Download `plg_content_gpxtm_vX.Y.Z_J6.zip` from the [Releases](../../releases) page (the zip attached to the release, not “Source code”).
2. In Joomla: **System → Install → Extensions**, then upload the zip.
3. **System → Plugins**: enable **Content - GPX-TrackMap**.
4. All options are in the plugin’s *Variables* tab.

Joomla offers updates automatically (built-in update server).

## Usage

In an article, put the `.gpx` file in the configured folder (set in the plugin), then write:

```
{gpxtrackmap}my-file.gpx{/gpxtrackmap}
```

Settings follow the file name, separated by semicolons:

```
{gpxtrackmap}my-file.gpx;Template=tmpl0.html{/gpxtrackmap}
{gpxtrackmap}my-file.gpx;Arrowtrack=true{/gpxtrackmap}
{gpxtrackmap}my-file.gpx;Trcolmod=h{/gpxtrackmap}
```

- `Template=tmpl0.html`: map with the elevation profile below.
- `Arrowtrack=true`: direction arrows (`Arrowtrackcol=#ffffff` for the colour, `Arrowsymbol=➤` for the symbol).
- `Trcolmod=h`: track coloured by elevation (other values: `s` slope, `v` speed, `hr` heart rate, `cad` cadence, `atemp` temperature). `Trcolgraph=true` applies the same colours to the charts; `Gintensity=true` varies the colour intensity of the charts with the data.
- `Trackwpt=start` (with `Template=tmpl-dossier.html` and a folder): one marker per file; other choices: `finish`, `highest`, `first`, `last`, `name` (with `Trackwptname=summit`).

The full list of variables and templates is in the **Variables** tab of the administration.

## Licences

- Plugin code (PHP, JavaScript, CSS, templates): **GNU GPL v2 or later**, © 2026 Perlimpin.fr
- [Leaflet](https://leafletjs.com) 1.9.4: BSD 2-Clause
- Waypoint icons (SJJB): public domain
- Base maps: please respect each provider’s terms of use (OpenStreetMap, ESRI, IGN, OpenTopoMap…).

See `LICENSE.txt` and `icons/LICENSE.txt` for details.

## Support

- Bugs and questions: [Issues](../../issues)
- Website: <https://www.perlimpinpin.fr>
