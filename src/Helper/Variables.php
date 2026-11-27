<?php

/**
 * @package      GPX-TrackMap
 * @copyright    Copyright (c) 2026 Perlimpin.fr All rights reserved
 * @license      GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Perlimpinpin\Plugin\Content\Gpxtm\Helper;

// Empêche l'accès direct au fichier
\defined('_JEXEC') or die;

/**
 * Catalogue de toutes les variables utilisables dans une balise {gpxtrackmap}.
 *
 * Chaque ligne : [nature, nom, type, valeur par défaut, description, options, indication]
 *  - nature : js (variable du moteur JavaScript), plg (variable du plugin), tpl (balise de modèle), syn (syntaxe)
 *  - type   : bool, num, str, mixed (variables JavaScript) ; pbool, pstr (variables du plugin)
 */
final class Variables
{
	// Types des variables JavaScript, indexés par nom en minuscules : [nom exact, type]
	private static ?array $jsTypes = null;

	// Retourne les types des variables du moteur JavaScript
	public static function jsTypes(): array
	{
		if (self::$jsTypes === null) {
			self::$jsTypes = [];

			foreach (self::sections() as $section) {
				foreach ($section['rows'] as $row) {
					if ($row[0] === 'js') {
						self::$jsTypes[strtolower($row[1])] = [$row[1], $row[2]];
					}
				}
			}
		}

		return self::$jsTypes;
	}

	// Variables du moteur JavaScript du plugin (les autres variables du catalogue ne sont pas utilisées par ce moteur)
	private const SUPPORTED = [
		'scrollwheelzoom', 'showmaptypecontroll', 'fullscreenbutton', 'currentlocationbutton',
		'legende', 'legende_info', 'legende_trk', 'legende_rte', 'legende_wpt',
		'trackover', 'trackclick', 'shtrname', 'shtrcmt', 'shtrdesc', 'shtrx', 'shtrt', 'shtrtwob', 'shtrh', 'shtrrr', 'shtrs', 'shtrv', 'shtrpace',
		'shtrhr', 'shtrcad', 'shtratemp', 'shtrlatlon', 'shtrtges', 'shtrtgeswob', 'shtrvmitt', 'shtrvmittwob', 'shtrvmittpace', 'shtrvmittpacewob',
		'shwpname', 'shwptooltip', 'shwpcmt', 'shwpdesc', 'shwptime', 'shwplatlon',
		'arrowtrack', 'arrowtrackcol', 'arrowsymbol', 'tcols', 'twidth', 'topac', 'trcolmod', 'trcolgraph', 'gintensity', 'trackwpt', 'trackwptname', 'shtrstart', 'shtrziel', 'rcols', 'rwidth', 'ropac',
		'defaulticon', 'gpxtracks', 'gpxrouten', 'gpxwegpunkte', 'bildpfad', 'gpxpfad', 'groesseminibild',
	];

	// Retourne les sections et leurs variables utilisables : les variables du moteur absentes de SUPPORTED sont retirées
	// et chaque section garde son numéro d'origine ('n') pour retrouver son titre traduit
	public static function sections(): array
	{
		$out = [];

		foreach (self::allSections() as $n => $section) {
			$rows = array_values(array_filter($section['rows'], static fn ($row) => $row[0] !== 'js' || \in_array(strtolower($row[1]), self::SUPPORTED, true)));

			if ($rows) {
				$out[] = ['title' => $section['title'], 'rows' => $rows, 'n' => $n];
			}
		}

		return $out;
	}

	// Catalogue complet (avant filtrage)
	private static function allSections(): array
	{
		return [
			[
				'title' => 'Fonction',
				'rows'  => [
					['js', 'Bestaetigung', 'bool', 'true', 'Demande au visiteur s’il accepte le chargement de contenus externes (images de cartes et éventuellement scripts)', null, null],
					['js', 'Doclang', 'str', '"auto"', 'Langue des textes de la carte et des graphiques', ['"auto"', '"de"', '"en"', '"fr"', '"es"', '"it"', '"nl"'], null],
					['js', 'Unit', 'str', '"si"', 'Unités : m/km (si), mile/ft et température en °F (us), mile/ft (en), NM/ft (air), sm/ft (water)', ['"si"', '"us"', '"en"', '"air"', '"water"'], null],
					['js', 'Linktarget', 'str', '""', 'Ouverture des liens : "" = fenêtre actuelle, "popup" = fenêtre popup, autre texte = nouvelle fenêtre ou nouvel onglet (le texte est utilisé comme attribut target)', ['""', '"popup"', '"nom_de_cible"'], null],
					['js', 'Popup_Pars', 'str', '"width=900,height=790,screenX=970,screenY=0,status=yes,scrollbars=yes"', 'Paramètres de la fenêtre popup (avec Linktarget="popup")', null, null],
					['js', 'Dateitrenner', 'str', '","', 'Caractère qui sépare plusieurs noms de fichiers GPX', null, null],
					['js', 'Maxzoomemove', 'num', '30', 'Niveau de zoom lors du déplacement du marqueur sur la carte (1 à 30 ; 30 = désactivé)', null, null],
					['plg', 'showelevation', 'pbool', null, 'Affiche le graphique du dénivelé (uniquement si le fichier GPX contient les altitudes)', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'showslope', 'pbool', null, 'Affiche le graphique de la pente (uniquement si le fichier GPX contient les altitudes)', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'showspeed', 'pbool', null, 'Affiche le graphique de la vitesse (uniquement si le fichier GPX contient les heures)', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'showcombined', 'pbool', null, 'Affiche un graphique unique qui combine dénivelé, pente et vitesse ; le visiteur coche les courbes à afficher', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'combcurves', 'pstr', null, 'Courbes affichées au départ dans le graphique combiné : h (dénivelé), s (pente), v (vitesse)', null, 'ex. h,s,v ; valeur par défaut : réglage de l’administration'],
					['plg', 'cpcolor_h', 'pstr', null, 'Graphique combiné : couleur de la courbe du dénivelé ; vide = couleur de chaque trace', null, 'code #rrggbb ; valeur par défaut : réglage de l’administration'],
					['plg', 'cpline_h', 'pstr', null, 'Graphique combiné : forme de la ligne de la courbe du dénivelé', null, 'solid / dashed / dotted / dashdot / longdash ; valeur par défaut : réglage de l’administration'],
					['plg', 'cpcolor_s', 'pstr', null, 'Graphique combiné : couleur de la courbe de la pente ; vide = couleur de chaque trace', null, 'code #rrggbb ; valeur par défaut : réglage de l’administration'],
					['plg', 'cpline_s', 'pstr', null, 'Graphique combiné : forme de la ligne de la courbe de la pente', null, 'solid / dashed / dotted / dashdot / longdash ; valeur par défaut : réglage de l’administration'],
					['plg', 'cpcolor_v', 'pstr', null, 'Graphique combiné : couleur de la courbe de la vitesse ; vide = couleur de chaque trace', null, 'code #rrggbb ; valeur par défaut : réglage de l’administration'],
					['plg', 'cpline_v', 'pstr', null, 'Graphique combiné : forme de la ligne de la courbe de la vitesse', null, 'solid / dashed / dotted / dashdot / longdash ; valeur par défaut : réglage de l’administration'],
					['plg', 'difcolors', 'pbool', null, 'Donne une couleur différente à chaque fichier GPX de la page (trace et graphiques)', null, 'valeur par défaut : réglage de l’administration'],
				],
			],
			[
				'title' => 'Template',
				'rows'  => [
					['plg', 'tmpl', 'pstr', null, 'Fichier modèle (template) utilisé par défaut, situé dans le dossier tmpl du plugin', null, 'tmpl0.html à tmpl5.html, tmpl-complet.html'],
					['plg', 'ctmpl', 'pstr', null, 'Fichier modèle utilisé pour cette carte uniquement', null, 'ex. ctmpl=tmpl1.html'],
					['plg', 'map_width', 'pstr', null, 'Largeur de la carte (remplace %MAPWIDTH%)', null, 'ex. 100% ou 600px'],
					['plg', 'map_height', 'pstr', null, 'Hauteur de la carte (remplace %MAPHEIGHT%)', null, 'ex. 400px'],
					['plg', 'hpmap_width', 'pstr', null, 'Largeur des graphiques (remplace %MAP_HP_WIDTH%)', null, 'ex. 100% ou 600px'],
					['plg', 'hpmap_height', 'pstr', null, 'Hauteur des graphiques (remplace %MAP_HP_HEIGHT%)', null, 'ex. 150px'],
					['plg', 'spmap_width', 'pstr', null, 'Largeur du graphique de la pente (remplace %MAP_SP_WIDTH%)', null, 'ex. 100% ou 600px'],
					['plg', 'spmap_height', 'pstr', null, 'Hauteur du graphique de la pente (remplace %MAP_SP_HEIGHT%)', null, 'ex. 150px'],
					['plg', 'vpmap_width', 'pstr', null, 'Largeur du graphique de la vitesse (remplace %MAP_VP_WIDTH%)', null, 'ex. 100% ou 600px'],
					['plg', 'vpmap_height', 'pstr', null, 'Hauteur du graphique de la vitesse (remplace %MAP_VP_HEIGHT%)', null, 'ex. 150px'],
					['plg', 'cpmap_width', 'pstr', null, 'Largeur du graphique combiné (remplace %MAP_CP_WIDTH%)', null, 'ex. 100% ou 600px'],
					['plg', 'cpmap_height', 'pstr', null, 'Hauteur du graphique combiné (remplace %MAP_CP_HEIGHT%)', null, 'ex. 250px'],
					['tpl', '%MAPID%', '', null, 'Identifiant unique de la carte ; on y ajoute _hp, _sp… pour les graphiques', null, null],
					['tpl', '%GPXVIEW%', '', null, 'Remplacé par gpxview:fichier.gpx:Style (classe de la carte)', null, null],
					['tpl', '%MAPWIDTH%', '', null, 'Largeur de la carte', null, null],
					['tpl', '%MAPHEIGHT%', '', null, 'Hauteur de la carte', null, null],
					['tpl', '%MAP_HP_WIDTH%', '', null, 'Largeur des graphiques', null, null],
					['tpl', '%MAP_HP_HEIGHT%', '', null, 'Hauteur des graphiques', null, null],
					['tpl', '%MAP_SP_WIDTH% / %MAP_SP_HEIGHT%', '', null, 'Dimensions du graphique de la pente', null, null],
					['tpl', '%MAP_VP_WIDTH% / %MAP_VP_HEIGHT%', '', null, 'Dimensions du graphique de la vitesse', null, null],
					['tpl', '%MAP_CP_WIDTH% / %MAP_CP_HEIGHT%', '', null, 'Dimensions du graphique combiné', null, null],
					['tpl', '%GPXHREF%', '', null, 'URL du fichier GPX', null, null],
					['tpl', '%GPXDOWN%', '', null, 'Bouton de téléchargement du GPX et avertissement éventuel', null, null],
					['tpl', '%MAPID%_hp', '', null, 'Graphique de l’altitude en fonction de la distance', null, null],
					['tpl', '%MAPID%_hpt', '', null, 'Graphique de l’altitude en fonction du temps', null, null],
					['tpl', '%MAPID%_sp', '', null, 'Graphique de la pente en fonction de la distance', null, null],
					['tpl', '%MAPID%_spt', '', null, 'Graphique de la pente en fonction du temps', null, null],
					['tpl', '%MAPID%_vp', '', null, 'Graphique de la vitesse en fonction de la distance', null, null],
					['tpl', '%MAPID%_vpt', '', null, 'Graphique de la vitesse en fonction du temps', null, null],
					['tpl', '%MAPID%_hrp', '', null, 'Graphique de la fréquence cardiaque en fonction de la distance', null, null],
					['tpl', '%MAPID%_hrpt', '', null, 'Graphique de la fréquence cardiaque en fonction du temps', null, null],
					['tpl', '%MAPID%_cadp', '', null, 'Graphique de la cadence en fonction de la distance', null, null],
					['tpl', '%MAPID%_cadpt', '', null, 'Graphique de la cadence en fonction du temps', null, null],
					['tpl', '%MAPID%_atempp', '', null, 'Graphique de la température en fonction de la distance', null, null],
					['tpl', '%MAPID%_atemppt', '', null, 'Graphique de la température en fonction du temps', null, null],
					['tpl', '%MAPID%_wpt', '', null, 'Graphique de la distance en fonction du temps', null, null],
					['tpl', '%MAPID%_cp', '', null, 'Graphique combiné : dénivelé, pente et vitesse avec cases à cocher', null, null],
					['tpl', '%MAPID%_profiles', '', null, 'Élément qui entoure les graphiques (adapte leur taille quand il est redimensionné)', null, null],
				],
			],
			[
				'title' => 'Affichage de la carte',
				'rows'  => [
					['syn', 'fichier.gpx:Style', '', null, 'Type de carte, indiqué après le nom du fichier, par exemple {gpxtrackmap}trace.gpx:OSM{/gpxtrackmap}. Les noms s’écrivent sans tenir compte des majuscules. IGN_… : cartes de la France ; Open_Sea, Hiking et Cycling : calques à utiliser avec la variable Overlay', ['Satellit', 'OSM', 'OSMDE', 'Open_Topo', 'TopPlusOpen', 'IGN_Satellite', 'IGN_Plan', 'IGN_Topo', 'No_Map', 'Open_Sea', 'Hiking', 'Cycling'], null],
					['plg', 'mapstyle', 'pstr', null, 'Carte affichée par défaut : numéro ou nom. 0 = Satellit, 1 = OSM, 2 = OSMDE, 3 = Open Topo, 4 = TopPlusOpen, 5 = IGN Satellite, 6 = IGN Plan, 7 = IGN Topo, 8 = Pas de carte', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'maps', 'pstr', null, 'Cartes proposées dans le sélecteur de cartes : numéros ou noms séparés par des virgules. Les numéros 0 à 8 sont les cartes de la variable mapstyle, 9 = Open Sea, 10 = Hiking, 11 = Cycling (calques) ; vide = toutes', null, 'ex. maps=0,1,3,12 ; valeur par défaut : réglage de l’administration'],
					['js', 'Maptype', 'str', '""', 'Carte affichée (une des valeurs de fichier.gpx:Style ci-dessus)', null, null],
					['js', 'Overlay', 'str', '""', 'Calque superposé à la carte (une des valeurs de fichier.gpx:Style ci-dessus) ; vide = aucun calque', null, null],
					['js', 'Defaultmaptype', 'str', '"OSMDE"', 'Carte affichée quand aucune n’est précisée (une des valeurs de fichier.gpx:Style ci-dessus)', null, null],
					['js', 'Show_ign', 'bool', 'true', 'Propose les cartes IGN dans le sélecteur de cartes (France uniquement) ; activé par le plugin', null, null],
					['plg', 'recenter', 'pbool', null, 'Affiche sur la carte un bouton qui recentre la vue sur l’ensemble du GPX', null, 'valeur par défaut : réglage de l’administration'],
					['js', 'Showmaptypecontroll', 'bool', 'true', 'Affiche le sélecteur de cartes', null, null],
					['js', 'Scrollwheelzoom', 'bool', 'true', 'Active ou désactive le zoom avec la molette de la souris', null, null],
					['js', 'Fullscreenbutton', 'bool', 'false', 'Affiche en haut à droite de la carte un bouton plein écran', null, null],
					['js', 'Currentlocationbutton', 'bool', 'false', 'Affiche un bouton pour afficher la position actuelle et la direction du mouvement', null, null],
				],
			],
			[
				'title' => 'Légende',
				'rows'  => [
					['js', 'Legende', 'bool', 'true', 'Affiche ou masque la légende au-dessus de la carte', null, null],
					['js', 'Legende_fnm', 'bool', 'true', 'Affiche les noms de fichiers dans la légende', null, null],
					['js', 'Legende_fnm_lm', 'mixed', 'false', 'Affiche après le nom du fichier la date (d), l’heure (t) ou les deux (dt) de dernière modification', ['false', '"d"', '"t"', '"dt"'], null],
					['js', 'Legende_rr', 'bool', 'true', 'Affiche le dénivelé positif dans la légende', null, null],
					['js', 'Legende_trk', 'bool', 'true', 'Affiche les traces dès l’ouverture de la page', null, null],
					['js', 'Legende_rte', 'bool', 'true', 'Affiche les routes dès l’ouverture de la page', null, null],
					['js', 'Legende_wpt', 'bool', 'true', 'Affiche les waypoints dès l’ouverture de la page', null, null],
				],
			],
			[
				'title' => 'Étiquette d’informations sur la carte',
				'rows'  => [
					['js', 'Shwpname', 'bool', 'true', 'Affiche le contenu du champ name dans l’info du waypoint', null, null],
					['js', 'Shwptooltip', 'bool', 'false', 'Affiche le contenu du champ name au-dessus du waypoint, dans une info-bulle (osm uniquement)', null, null],
					['js', 'Shwpcmt', 'bool', 'true', 'Affiche le contenu du champ cmt dans l’info du waypoint', null, null],
					['js', 'Shwpdesc', 'bool', 'false', 'Affiche le contenu du champ desc dans l’info du waypoint', null, null],
					['js', 'Shwptime', 'bool', 'false', 'Affiche l’heure dans l’info du waypoint', null, null],
					['js', 'Shwplatlon', 'bool', 'false', 'Affiche les coordonnées géographiques dans l’info du waypoint', null, null],
				],
			],
			[
				'title' => 'Informations de la trace',
				'rows'  => [
					['js', 'Trackover', 'bool', 'true', 'Affiche l’info de la trace ou de la route quand la souris la survole', null, null],
					['js', 'Legende_info', 'bool', 'false', 'Affiche les informations de la trace ou de la route dans la ligne de légende', null, null],
					['js', 'Shtrcmt', 'bool', 'false', 'Affiche le contenu du champ cmt dans l’info de la trace', null, null],
					['js', 'Shtrdesc', 'bool', 'false', 'Affiche le contenu du champ desc dans l’info de la trace', null, null],
					['js', 'Shtrtges', 'bool', 'false', 'Affiche la durée totale de la trace', null, null],
					['js', 'Shtrtgeswob', 'bool', 'false', 'Affiche la durée totale en mouvement', null, null],
					['js', 'Shtrvmitt', 'bool', 'false', 'Affiche la vitesse moyenne dans l’info de la trace', null, null],
					['js', 'Shtrvmittwob', 'bool', 'false', 'Affiche la vitesse moyenne en tenant compte des pauses', null, null],
					['js', 'Shtrvmittpace', 'bool', 'false', 'Affiche la vitesse moyenne en min/km', null, null],
					['js', 'Shtrvmittpacewob', 'bool', 'false', 'Affiche la vitesse moyenne en min/km en tenant compte des pauses', null, null],
				],
			],
			[
				'title' => 'Informations de la trace au-dessus de la carte',
				'rows'  => [
					['plg', 'showtrackinfo', 'pbool', null, 'Affiche au-dessus de la carte les informations de chaque trace (modèle tmpl-complet) : distance, dénivelés, altitudes, pente, durées, vitesses…', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_name', 'pbool', null, 'Au-dessus de la carte : nom de la trace', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_cmt', 'pbool', null, 'Au-dessus de la carte : commentaire', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_desc', 'pbool', null, 'Au-dessus de la carte : description', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_dist', 'pbool', null, 'Au-dessus de la carte : distance', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_gain', 'pbool', null, 'Au-dessus de la carte : dénivelé cumulé', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_alt', 'pbool', null, 'Au-dessus de la carte : altitude minimale et maximale', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_slope', 'pbool', null, 'Au-dessus de la carte : pente minimale et maximale', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_date', 'pbool', null, 'Au-dessus de la carte : date du trajet', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_time', 'pbool', null, 'Au-dessus de la carte : durée totale', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_moving', 'pbool', null, 'Au-dessus de la carte : durée en mouvement', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_speed', 'pbool', null, 'Au-dessus de la carte : vitesse moyenne', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_speedmov', 'pbool', null, 'Au-dessus de la carte : vitesse moyenne en mouvement', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_pace', 'pbool', null, 'Au-dessus de la carte : allure', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_pacemov', 'pbool', null, 'Au-dessus de la carte : allure en mouvement', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_hr', 'pbool', null, 'Au-dessus de la carte : fréquence cardiaque moyenne', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_cad', 'pbool', null, 'Au-dessus de la carte : cadence moyenne', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'bar_temp', 'pbool', null, 'Au-dessus de la carte : température moyenne', null, 'valeur par défaut : réglage de l’administration'],
				],
			],
			[
				'title' => 'Informations de la bulle',
				'rows'  => [
					['js', 'Trackclick', 'bool', 'true', 'Affiche l’info de la trace ou de la route quand on clique dessus', null, null],
					['plg', 'showclickalt', 'pbool', null, 'Affiche l’altitude du point cliqué dans l’infobulle de la trace (équivalent de Shtrh)', null, 'valeur par défaut : réglage de l’administration'],
					['js', 'Shtrname', 'bool', 'false', 'Affiche le contenu du champ name dans l’info de la trace', null, null],
					['js', 'Shtrx', 'bool', 'true', 'Affiche la distance dans l’info de la trace', null, null],
					['js', 'Shtrt', 'bool', 'true', 'Affiche la durée dans l’info de la trace', null, null],
					['js', 'Shtrtwob', 'bool', 'false', 'Affiche la durée en mouvement dans l’info de la trace', null, null],
					['js', 'Shtrtabs', 'mixed', 'false', 'Affiche l’heure absolue au lieu du temps écoulé depuis le départ : dans l’info au clic ou au survol (k), comme graduation de l’axe du graphique (p), ou les deux (kp)', ['false', '"k"', '"p"', '"kp"'], null],
					['js', 'Movevmin', 'num', '1', 'Vitesse minimale pour considérer qu’il y a mouvement', null, null],
					['js', 'Shtrh', 'bool', 'true', 'Affiche l’altitude dans l’info de la trace', null, null],
					['js', 'Shtrrr', 'bool', 'true', 'Affiche le dénivelé positif cumulé dans l’info de la trace', null, null],
					['js', 'Shtrs', 'bool', 'true', 'Affiche la pente dans l’info de la trace', null, null],
					['js', 'Shtrv', 'bool', 'true', 'Affiche la vitesse dans l’info de la trace', null, null],
					['js', 'Shtrpace', 'bool', 'false', 'Affiche l’allure (pace) dans l’info de la trace', null, null],
					['js', 'Shtrhr', 'bool', 'true', 'Affiche la fréquence cardiaque dans l’info de la trace', null, null],
					['js', 'Shtrcad', 'bool', 'true', 'Affiche la cadence de pédalage dans l’info de la trace', null, null],
					['js', 'Shtratemp', 'bool', 'true', 'Affiche la température dans l’info de la trace', null, null],
					['js', 'Shtrlatlon', 'bool', 'false', 'Affiche les coordonnées géographiques du point de la trace', null, null],
				],
			],
			[
				'title' => 'Graphique',
				'rows'  => [
					['plg', 'gcolors', 'pstr', null, 'Couleurs des graphiques de cette carte, dans l’ordre des fichiers GPX ; vide = couleur de chaque trace', null, 'ex. gcolors=#ff8800,#0088ff'],
					['plg', 'gcolor', 'pstr', null, 'Couleur des graphiques (raccourci de gcolors pour un seul fichier GPX)', null, 'ex. gcolor=#ff8800'],
					['js', 'Plotframecol', 'str', '"black"', 'Couleur du cadre du graphique', null, null],
					['js', 'Plotgridcol', 'str', '"gray"', 'Couleur de la grille', null, null],
					['js', 'Plotlabelcol', 'str', '"black"', 'Couleur des légendes des axes', null, null],
					['js', 'Plotmarkercol', 'str', '"black"', 'Couleur du marqueur dans le graphique', null, null],
					['js', 'Profilfillopac', 'num', '0', 'Saturation de la couleur sous la courbe du profil (0 à 1 ; 0 = désactivé)', null, null],
					['plg', 'satprofile', 'pstr', null, 'Saturation de la couleur sous la courbe du profil (0 à 1) ; équivalent de Profilfillopac', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'profilecolor', 'pstr', null, 'Couleur de la première trace et des profils', null, 'code #rrggbb ; valeur par défaut : réglage de l’administration'],
				],
			],
			[
				'title' => 'Fichier GPX',
				'rows'  => [
					['js', 'Gpxtracks', 'bool', 'true', 'Charge les traces du fichier GPX', null, null],
					['js', 'Gpxrouten', 'bool', 'true', 'Charge les routes du fichier GPX', null, null],
					['js', 'Gpxwegpunkte', 'bool', 'true', 'Charge les waypoints du fichier GPX', null, null],
					['js', 'Gpxpfad', 'str', '""', 'Chemin du dossier des fichiers GPX (déjà renseigné par le plugin)', null, null],
					['plg', 'gpxpath', 'pstr', null, 'Dossier des fichiers GPX, dans le dossier images du site', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'downloadgpx', 'pbool', null, 'Affiche le bouton de téléchargement du fichier GPX', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'downloadtext', 'pstr', null, 'Texte du bouton de téléchargement', null, 'vide = texte par défaut de la langue'],
					['plg', 'downloadcolor', 'pstr', null, 'Couleur du bouton de téléchargement', null, 'code #rrggbb ; valeur par défaut : réglage de l’administration'],
					['js', 'Tracks_verbinden', 'bool', 'false', 'Relie les traces dans le graphique (axe de distance ou de temps continu)', null, null],
					['js', 'Tracks_dateiuebergreifend_verbinden', 'bool', 'false', 'Relie aussi les traces de fichiers différents dans le graphique', null, null],
					['js', 'Tracksort', 'bool', 'true', 'Classe les traces du graphique par heure de départ', null, null],
					['js', 'Displaycolor', 'bool', 'false', 'Reprend la couleur de ligne du fichier GPX (actuellement Garmin uniquement)', null, null],
					['js', 'Laengen3d', 'bool', 'false', 'Tient compte des dénivelés dans le calcul des distances', null, null],
					['js', 'Tkorr', 'bool', 'true', 'Affiche l’heure selon le fuseau horaire du navigateur (sinon UTC)', null, null],
					['js', 'Tdiff', 'num', '0', 'Décalage horaire, en heures, pour l’heure affichée dans la fenêtre d’info', null, null],
					['js', 'Usegpxbounds', 'bool', 'false', 'Cadre la carte d’après la balise bounds du fichier GPX', null, null],
					['js', 'Readspeed', 'bool', 'true', 'Utilise la balise speed du fichier GPX si elle existe', null, null],
					['js', 'Speedfaktor', 'num', '3.6', 'Facteur d’étalonnage de la balise speed (3,6 pour des m/s ; 1,609344 pour des mph ; 1 pour des km/h)', null, null],
					['js', 'Unwraplon', 'bool', 'true', 'Corrige les longitudes lors du passage de la ligne des 180°', null, null],
					['js', 'Hglatt', 'bool', 'false', 'Lisse le profil d’altitude à l’affichage', null, null],
					['js', 'Hglattlaen', 'num', '500', 'Longueur de l’intervalle de lissage du profil d’altitude, en mètres (pour le calcul de la pente et, si besoin, l’affichage)', null, null],
					['js', 'Vglatt', 'bool', 'false', 'Lisse le profil de vitesse', null, null],
					['js', 'Vglattlaen', 'num', '100', 'Longueur de l’intervalle de lissage du profil de vitesse, en mètres', null, null],
					['js', 'Hfaktor', 'num', '1', 'Facteur d’étalonnage de l’altitude', null, null],
					['js', 'Sfaktor', 'num', '1', 'Facteur d’étalonnage de la pente', null, null],
					['js', 'Vfaktor', 'num', '1', 'Facteur d’étalonnage de la vitesse', null, null],
					['js', 'Wfaktor', 'num', '1', 'Facteur d’étalonnage de la distance', null, null],
					['js', 'Tfaktor', 'num', '1', 'Facteur d’étalonnage de la température', null, null],
					['js', 'Toffset', 'num', '0', 'Décalage d’étalonnage de la température', null, null],
				],
			],
			[
				'title' => 'Affichage de la trace',
				'rows'  => [
					['plg', 'colors', 'pstr', null, 'Couleurs des traces de cette carte, dans l’ordre des fichiers GPX ; les graphiques reprennent la couleur de leur trace', null, 'ex. colors=#ff0000,#0000ff pour a.gpx,b.gpx'],
					['plg', 'color', 'pstr', null, 'Couleur de la trace (raccourci de colors pour un seul fichier GPX)', null, 'ex. color=#0000ff'],
					['js', 'Tcols', 'str', '"#ff0000,#00ff00,#0000ff,#eeee00,#ff00ff,#00ffff,#000000"', 'Couleurs des traces (codes #rrggbb séparés par des virgules)', null, null],
					['js', 'Twidth', 'num', '2', 'Épaisseur de la ligne des traces', null, null],
					['js', 'Topac', 'num', '0.8', 'Opacité de la couleur des traces (0 à 1)', null, null],
					['js', 'Trcolmod', 'str', '""', 'Colore la trace selon l’altitude (h), la pente (s), la vitesse (v), la cadence (cad), la fréquence cardiaque (hr) ou la température (atemp)', ['""', '"h"', '"s"', '"v"', '"hr"', '"cad"', '"atemp"'], null],
					['js', 'Trcolgraph', 'bool', 'false', 'Colore aussi les graphiques avec l’échelle de Trcolmod (la courbe prend, en chaque point, la couleur de la valeur choisie)', null, null],
					['js', 'Gintensity', 'str', '""', 'Intensité de la couleur des graphiques selon les données : couleur claire pour les valeurs faibles, de plus en plus intense pour les fortes. true = selon la courbe elle-même ; h, s ou v = selon l’altitude, la pente ou la vitesse', ['""', '"true"', '"h"', '"s"', '"v"'], null],
					['js', 'Trackwpt', 'str', '"off"', 'Mode « un repère par trace » (dossier de GPX) : seul un repère par fichier est affiché ; un clic sur le repère affiche la trace et ses graphiques. Choix du repère : start (départ), finish (arrivée), highest (point culminant), first ou last (premier ou dernier waypoint du fichier), name (waypoint dont le nom contient Trackwptname)', ['"off"', '"start"', '"finish"', '"highest"', '"first"', '"last"', '"name"'], null],
					['js', 'Trackwptname', 'str', '""', 'Avec Trackwpt=name : texte cherché dans le nom des waypoints de chaque fichier (sans tenir compte des majuscules) ; à défaut, le premier waypoint du fichier, puis le départ', null, null],
					['js', 'Arrowtrack', 'bool', 'false', 'Affiche sur la trace une flèche qui indique le sens du déplacement', null, null],
					['js', 'Arrowtrackcol', 'str', '""', 'Couleur de la flèche ; si vide, la couleur de la trace est utilisée', null, null],
					['js', 'Arrowsymbol', 'str', '"➤"', 'Symbole de la flèche, pour les traces et les routes', null, null],
					['js', 'Shtrstart', 'bool', 'false', 'Marque le début de la trace', null, null],
					['js', 'Shtrziel', 'bool', 'false', 'Marque la fin de la trace', null, null],
					['js', 'Trackmarker', 'mixed', '""', 'Distance en km entre les marqueurs placés sur la trace ; vide = aucun marqueur (osm uniquement)', null, null],
				],
			],
			[
				'title' => 'Affichage au suivi de la souris',
				'rows'  => [
					['js', 'Ocol', 'str', '"#000000"', 'Couleur de la trace ou de la route au survol ; si vide, la couleur d’origine est conservée (osm uniquement)', null, null],
					['js', 'Owidth', 'num', '3', 'Épaisseur de la ligne de la trace ou de la route au survol', null, null],
				],
			],
			[
				'title' => 'Waypoints',
				'rows'  => [
					['js', 'Shwpshadow', 'bool', 'true', 'Affiche les icônes des waypoints avec une ombre', null, null],
					['js', 'Wpcluster', 'bool', 'false', 'Regroupe en cluster les waypoints trop proches les uns des autres', null, null],
					['js', 'Defaulticon', 'str', '""', 'Icône affichée quand le fichier GPX n’en précise aucune ou une icône inconnue ; la liste des icônes est dans l’onglet Waypoint', null, null],
				],
			],
			[
				'title' => 'Photos',
				'rows'  => [
					['plg', 'showimages', 'pbool', null, 'Affiche sous la carte les photos portant le nom du fichier GPX', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'geoimg', 'pbool', null, 'Affiche sur la carte les photos contenant des coordonnées GPS', null, 'valeur par défaut : réglage de l’administration'],
					['plg', 'gpximagepath', 'pstr', null, 'Dossier des photos, dans le dossier images du site', null, 'valeur par défaut : réglage de l’administration'],
					['js', 'Bildpfad', 'str', '""', 'Chemin du dossier des images (déjà renseigné par le plugin)', null, null],
					['js', 'Groesseminibild', 'num', '60', 'Taille en pixels de l’aperçu des waypoints image (149 au maximum)', null, null],
					['js', 'Bildwegpunkticon', 'str', '"Bild"', 'Nom de l’icône des waypoints image ; si vide, le nom de l’icône du fichier GPX est utilisé', null, null],
				],
			],
			[
				'title' => 'Routes : paramètres spécifiques',
				'rows'  => [
					['js', 'Rcols', 'str', '"#800000,#008000,#000080,#808000,#800080,#008080,#808080"', 'Couleurs des routes (codes #rrggbb séparés par des virgules)', null, null],
					['js', 'Rwidth', 'num', '2', 'Épaisseur de la ligne des routes', null, null],
					['js', 'Ropac', 'num', '0.8', 'Opacité de la couleur des routes (0 à 1)', null, null],
					['js', 'Shrtstart', 'bool', 'false', 'Marque le début de la route', null, null],
					['js', 'Shrtziel', 'bool', 'false', 'Marque la fin de la route', null, null],
					['js', 'Shrtcmt', 'bool', 'false', 'Affiche le contenu du champ cmt dans l’info de la route', null, null],
					['js', 'Shrtdesc', 'bool', 'false', 'Affiche le contenu du champ desc dans l’info de la route', null, null],
					['js', 'Arrowroute', 'bool', 'false', 'Affiche sur la route une flèche qui indique le sens du déplacement (symbole : Arrowsymbol)', null, null],
					['js', 'Arrowroutecol', 'str', '""', 'Couleur de la flèche ; si vide, la couleur de la route est utilisée', null, null],
					['js', 'Routemarker', 'mixed', '""', 'Distance en km entre les marqueurs placés sur la route ; vide = aucun marqueur (osm uniquement)', null, null],
				],
			],
		];
	}
}
