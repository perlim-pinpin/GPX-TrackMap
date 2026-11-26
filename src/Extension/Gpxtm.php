<?php

/**
 * @version      2.2.3
 * @package      GPX-TrackMap
 * @author       Perlimpinpin - https://www.perlimpinpin.fr
 * @copyright    Copyright (c) 2026 Perlimpin.fr All rights reserved
 * @license      GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Perlimpinpin\Plugin\Content\Gpxtm\Extension;

// Empêche l'accès direct au fichier
\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Perlimpinpin\Plugin\Content\Gpxtm\Helper\Variables;

final class Gpxtm extends CMSPlugin implements SubscriberInterface
{
	// Expression régulière pour repérer les balises {gpxtrackmap}...{/gpxtrackmap}
	private const GPXTM_TAG_REGEX = '/\{gpxtrackmap.*?\}(.*?)\{\/gpxtrackmap\}/is';

	// Paramètres autorisés côté PHP (en plus des variables du moteur JavaScript, voir Helper/Variables.php)
	private const PLUGIN_VARS = [
		"showimages","geoimg","downloadgpx","showelevation","tmpl","ctmpl","map_width","map_height","hpmap_width","mapstyle",
		"hpmap_height","gpxpath","gpximagepath","usenavigator","phpLegende","phpFullscreenbutton","phpBestaetigung","satprofile","profilecolor",
		"showslope","showspeed","difcolors","downloadtext","downloadcolor","showcombined","combcurves",
		"spmap_width","spmap_height","vpmap_width","vpmap_height","cpmap_width","cpmap_height",
		"cpcolor_h","cpcolor_s","cpcolor_v","cpline_h","cpline_s","cpline_v","colors","color","gcolors","gcolor","showclickalt","showtrackinfo","maps","defaulticon","recenter","bar_name","bar_cmt","bar_desc","bar_dist","bar_gain","bar_alt","bar_slope","bar_date","bar_time","bar_moving","bar_speed","bar_speedmov","bar_pace","bar_pacemov","bar_hr","bar_cad","bar_temp",
	];

	// Formes de ligne du graphique combiné (et noms français acceptés dans la balise)
	private const LINE_STYLES = [
		'solid' => 'solid', 'dashed' => 'dashed', 'dotted' => 'dotted', 'dashdot' => 'dashdot', 'longdash' => 'longdash',
		'plein' => 'solid', 'tirets' => 'dashed', 'pointilles' => 'dotted', 'tiretspoints' => 'dashdot', 'longstirets' => 'longdash',
	];

	// Paramètres PHP de type oui/non (convertis en 1 ou 0)
	private const PLUGIN_BOOL = [
		"showimages","geoimg","downloadgpx","showelevation","showslope","showspeed","showcombined","showclickalt","showtrackinfo","recenter","difcolors","bar_name","bar_cmt","bar_desc","bar_dist","bar_gain","bar_alt","bar_slope","bar_date","bar_time","bar_moving","bar_speed","bar_speedmov","bar_pace","bar_pacemov","bar_hr","bar_cad","bar_temp","usenavigator","phpLegende","phpFullscreenbutton","phpBestaetigung",
	];

	// Styles de carte disponibles (l'index correspond au paramètre "mapstyle")
	// Cartes disponibles : numéro => [clé du fond de carte, nom du style dans la balise]. 0 à 8 : fonds de carte ; 9 à 11 : calques
	private const MAPS = [
		0 => ['satellit', 'Satellit'],        1 => ['osm', 'OSM'],                 2  => ['osmde', 'OSMDE'],
		3 => ['opentopo', 'Open_Topo'],       4 => ['topplusopen', 'TopPlusOpen'], 5  => ['ignsatellite', 'IGN_Satellite'],
		6 => ['ignplan', 'IGN_Plan'],         7 => ['igntopo', 'IGN_Topo'],        8  => ['nomap', 'No_Map'],
		9 => ['opensea', 'Open_Sea'],         10 => ['hiking', 'Hiking'],          11 => ['cycling', 'Cycling'],
	];

	// Options oui/non réglables dans l'administration  :
	// informations de la trace au clic (Shtr*) et waypoints (Shwp*, Wpcluster)
	private const SWITCHES = [
		'shtrname' => 0, 'shtrcmt' => 0, 'shtrdesc' => 0, 'shtrx' => 1, 'shtrcad' => 1, 'shtrhr' => 1, 'shtratemp' => 1, 'shtrt' => 1,
		'shtrtwob' => 0, 'shtrtges' => 0, 'shtrtgeswob' => 0, 'shtrrr' => 1, 'shtrs' => 1, 'shtrv' => 1, 'shtrpace' => 0,
		'shtrvmitt' => 0, 'shtrvmittwob' => 0, 'shtrvmittpace' => 0, 'shtrvmittpacewob' => 0, 'shtrlatlon' => 0,
		'trackover' => 1, 'trackclick' => 1, 'legende_info' => 0,
		'shwpname' => 1, 'shwptooltip' => 0, 'shwpcmt' => 1, 'shwpdesc' => 0, 'shwptime' => 0, 'shwplatlon' => 0,
	];

	// Informations de la trace affichées au-dessus de la carte (modèle tmpl-complet) : élément => affiché par défaut
	private const BAR = ['name' => 0, 'cmt' => 0, 'desc' => 0, 'dist' => 1, 'gain' => 1, 'alt' => 1, 'slope' => 1, 'date' => 1, 'time' => 0, 'moving' => 0, 'speed' => 0, 'speedmov' => 0, 'pace' => 0, 'pacemov' => 0, 'hr' => 1, 'cad' => 1, 'temp' => 1];

	// Variables dont les valeurs texte sont ramenées en minuscules (insensibles à la casse)
	private const LOWER_VALUES = ['Doclang', 'Unit', 'Trcolmod', 'Legende_fnm_lm', 'Shtrtabs'];

	// Version : ajoutée à l'adresse des scripts pour que le navigateur recharge les fichiers modifiés
	private const VERSION = '2.2.3';

	// Dossier des modèles personnalisés (hors du plugin : conservé lors des mises à jour et des réinstallations)
	public const CUSTOM_TMPL = '/media/gpxtm/tmpl';

	// Nombre maximal de fichiers GPX lus dans un dossier
	private const MAX_FOLDER_FILES = 30;

	// Chemin du plugin sur le disque et sous forme d'URL (sans slash final)
	private const PLG_REL = '/plugins/content/gpxtm';

	// Nombre de fichiers GPX déjà affichés sur la page : sert à décaler les couleurs entre les cartes
	private static int $colorOffset = 0;

	// Les réglages communs (GPXTM_CONFIG) ne sont écrits qu'une fois par page
	private static bool $configDone = false;

	/**
	 * Déclare les événements écoutés par le plugin (Joomla 4/5/6)
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepare' => 'onContentPrepare',
		];
	}

	// Événement Joomla déclenché lors de la préparation du contenu
	public function onContentPrepare(ContentPrepareEvent $event): void
	{
		$context = $event->getContext();
		$article = $event->getItem();

		// Ne rien faire sur un élément sans texte
		if (!\is_object($article) || !isset($article->text) || !\is_string($article->text)) {
			return;
		}

		// Ne rien faire pendant l'indexation par Smart Search
		if ($context === 'com_finder.indexer') {
			return;
		}

		// Dans les blogs de catégorie, on supprime simplement les balises
		if ($context === 'com_content.category') {
			$this->showtextonly($article);
			return;
		}

		// Ne rien faire sur la description de catégorie
		if ($context === 'com_content.category.description') {
			return;
		}

		// Si aucune balise {gpxtrackmap n'est présente, on sort
		if (stripos($article->text, '{gpxtrackmap') === false) {
			return;
		}

		$this->showMaps($article);
	}

	// Supprime les balises {gpxtrackmap} du texte (sans afficher de carte)
	private function showtextonly(object $article): void
	{
		$gpxTagMatches = self::findMatches($article->text);

		if ($gpxTagMatches === null) {
			return;
		}

		foreach ($gpxTagMatches[0] as $tagAndContents) {
			$article->text = str_replace($tagAndContents, "", $article->text);
		}
	}

	// Recherche toutes les balises {gpxtrackmap} (null si aucune n'est trouvée)
	private static function findMatches(string $articleText): ?array
	{
		$tagMatches = [];

		$result = preg_match_all(self::GPXTM_TAG_REGEX, $articleText, $tagMatches, PREG_PATTERN_ORDER);

		if ($result === false) {
			throw new \RuntimeException('Error while finding gpxtrackmap tags');
		}

		if ($result === 0) {
			return null;
		}

		return $tagMatches;
	}

	// Nettoie un morceau de balise : supprime le HTML ajouté par l'éditeur, décode les entités, retire les espaces (y compris insécables)
	private static function cleanToken(string $text): string
	{
		$text = strip_tags($text);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim((string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $text));
	}

	// Retire les guillemets (droits ou typographiques) autour d'une valeur
	private static function unquote(string $value): string
	{
		return trim($value, " \t\n\r\"'“”‘’«»");
	}

	// Dossier de fichiers GPX : « dossier/ », « dossier/* » ou « * » donnent tous les .gpx du dossier (null : ce n'est pas un dossier)
	private static function expandFolder(string $entry, string $baseDir): ?array
	{
		$e = trim(str_replace('\\', '/', $entry));

		if ($e === '') {
			return null;
		}

		if (substr($e, -1) === '/') {
			$sub = $e;
		} elseif (preg_match('#(^|/)\*(\.gpx)?$#i', $e)) {
			$sub = (string) preg_replace('#(^|/)\*(\.gpx)?$#i', '', $e);
		} else {
			return null;
		}

		$parts = [];

		foreach (explode('/', $sub) as $segment) {
			$segment = trim($segment);

			if ($segment === '' || $segment === '.') {
				continue;
			}

			if ($segment === '..' || !preg_match('/^[\p{L}\p{N}._\-+() ]+$/u', $segment)) {
				return [];
			}

			$parts[] = $segment;
		}

		$subPath = implode('/', $parts);
		$dir     = rtrim($baseDir . $subPath, '/') . '/';
		$found   = [];

		foreach ((array) glob($dir . '*.[gG][pP][xX]') as $file) {
			$found[] = ($subPath !== '' ? $subPath . '/' : '') . basename($file);
		}

		natcasesort($found);

		return \array_slice(array_values($found), 0, self::MAX_FOLDER_FILES);
	}

	// Nettoie un chemin de fichier GPX : sous-dossiers permis, pas de « .. » ni de caractères spéciaux, extension .gpx obligatoire
	private static function cleanGpxPath(string $path): string
	{
		$parts = [];

		foreach (explode('/', trim(str_replace('\\', '/', $path))) as $segment) {
			$segment = trim($segment);

			if ($segment === '' || $segment === '.') {
				continue;
			}

			if ($segment === '..' || !preg_match('/^[\p{L}\p{N}._\-+() ]+$/u', $segment)) {
				return '';
			}

			$parts[] = $segment;
		}

		$clean = implode('/', $parts);

		return strtolower(pathinfo($clean, PATHINFO_EXTENSION)) === 'gpx' ? $clean : '';
	}

	// Retourne une dimension (largeur ou hauteur) : valeur propre, sinon valeur de secours, sinon valeur par défaut
	private static function dim(Registry $pars, string $name, string $fallback, string $default): string
	{
		$value = (string) $pars->get($name, '');

		if ($value === '' && $fallback !== '') {
			$value = (string) $pars->get($fallback, '');
		}

		return $value !== '' ? $value : $default;
	}

	// Choisit le texte (noir ou blanc) le plus lisible sur une couleur de fond
	private static function contrastColor(string $color): string
	{
		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $m)) {
			$hex = $m[1];
			if (\strlen($hex) === 3) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$lum = 0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2));

			return $lum > 150 ? '#000000' : '#ffffff';
		}

		return '#ffffff';
	}

	// Normalise un nom de carte : minuscules, sans espaces, tirets ni soulignés
	private static function normName(string $name): string
	{
		return (string) preg_replace('/[\s_\-]+/', '', strtolower($name));
	}

	// Numéro (0 à 11) d'une carte donnée par son numéro ou son nom ; null si inconnue
	private static function mapIndex(string $value, int $max = 11): ?int
	{
		$value = trim($value);
		if ($value !== '' && ctype_digit($value)) {
			return (int) $value <= $max ? (int) $value : null;
		}
		$norm = self::normName($value);
		foreach (self::MAPS as $i => $map) {
			if ($i <= $max && ($norm === self::normName($map[0]) || $norm === self::normName($map[1]))) {
				return $i;
			}
		}

		return null;
	}

	// Vérifie que les coordonnées sont au format "nombre,nombre,nombre"
	private function check_coordinates(string $cmd): string
	{
		// Précaution : évite les problèmes en cas d'oubli ou de valeur erronée
		$co = array_map('trim', explode(",", $cmd));

		if (\count($co) === 3 && is_numeric($co[0]) && is_numeric($co[1]) && is_numeric($co[2])) {
			return $co[0] . "," . $co[1] . "," . $co[2];
		}

		return "";
	}

	// Convertit une valeur en code JavaScript selon le type de la variable (null si la valeur est invalide)
	private static function jsValue(string $type, string $name, string $value): ?string
	{
		$value = self::unquote($value);
		$lower = strtolower($value);
		$json  = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

		// Valeurs énumérées : la casse n'a pas d'importance
		if (\in_array($name, self::LOWER_VALUES, true) && !is_numeric($value)) {
			$value = $lower;
		}

		// Les listes de couleurs sont séparées par des virgules, sans espaces
		if ($name === 'Tcols' || $name === 'Rcols') {
			$value = (string) preg_replace('/\s*,\s*/', ',', $value);
		}

		switch ($type) {
			case 'bool':
				if (\in_array($lower, ['true', '1', 'oui', 'yes', 'on'], true)) {
					return 'true';
				}
				if (\in_array($lower, ['false', '0', 'non', 'no', 'off'], true)) {
					return 'false';
				}
				return null;

			case 'num':
				return is_numeric($value) ? (string) ($value + 0) : null;

			case 'mixed': // booléen, nombre ou texte
				if ($lower === 'true' || $lower === 'false') {
					return $lower;
				}
				if (is_numeric($value)) {
					return (string) ($value + 0);
				}
				return json_encode($value, $json);

			default: // texte
				return json_encode($value, $json);
		}
	}

	// Vérifie et traite un paramètre de la balise (format "nom=valeur") ; les variables JavaScript sont rangées dans $data
	// (attributs data-* de la carte : chaque carte garde ses propres réglages)
	private function check_parameter(string $cmd, Registry $pars, array &$data, array &$explicit): void
	{
		$cmd = self::cleanToken($cmd);

		if ($cmd === '') {
			return;
		}

		$pos = strpos($cmd, "=");

		// Cas où l'utilisateur met un ; au lieu de : entre le GPX et le style de carte
		if ($pos === false) {
			$kaart = self::mapIndex($cmd, 8);
			if ($kaart !== null) {
				$pars->set("mapstyle", $kaart);
			}
			return;
		}

		$name  = trim(substr($cmd, 0, $pos));
		$value = trim(substr($cmd, $pos + 1));
		$key   = strtolower($name);

		// Variable du moteur JavaScript (attribut data-* de la carte)
		$jsTypes = Variables::jsTypes();
		if (isset($jsTypes[$key])) {
			[$canonical, $type] = $jsTypes[$key];
			$js = self::jsValue($type, $canonical, $value);

			if ($js !== null) {
				$raw = json_decode($js);
				$data[strtolower($canonical)] = \is_bool($raw) ? ($raw ? 'true' : 'false') : (string) $raw;
			}

			return;
		}

		// Variable du plugin (traitée côté PHP)
		$phpVars = array_combine(array_map('strtolower', self::PLUGIN_VARS), self::PLUGIN_VARS);
		if (isset($phpVars[$key])) {
			$canonical = $phpVars[$key];
			$value     = self::unquote($value);

			if (\in_array($canonical, self::PLUGIN_BOOL, true)) {
				// Tolérance aux fautes de frappe : true/false convertis en 1/0
				$c = strtolower($value);
				if (\in_array($c, ['true', '1', 'oui', 'yes', 'on'], true)) {
					$value = 1;
				} elseif (\in_array($c, ['false', '0', 'non', 'no', 'off'], true)) {
					$value = 0;
				} else {
					return;
				}
			} elseif (\in_array($canonical, ['map_width', 'map_height', 'hpmap_width', 'hpmap_height', 'spmap_width', 'spmap_height', 'vpmap_width', 'vpmap_height', 'cpmap_width', 'cpmap_height'], true)) {
				// Dimensions : chiffres, lettres, %, point, tiret (insérées dans un attribut style)
				$value = (string) preg_replace('/[^0-9a-zA-Z.%\- ]/', '', $value);
			} elseif ($canonical === 'tmpl' || $canonical === 'ctmpl') {
				// Nom de fichier modèle : sans chemin, extension .html par défaut
				$value = basename($value);
				if ($value !== '' && pathinfo($value, PATHINFO_EXTENSION) === '') {
					$value .= '.html';
				}
			} elseif ($canonical === 'gpxpath' || $canonical === 'gpximagepath') {
				// Dossiers : pas de remontée dans l'arborescence
				$value = trim(str_replace(['\\', '..'], ['/', ''], $value), '/');
			} elseif ($canonical === 'colors' || $canonical === 'color') {
				// Couleurs des traces de cette carte (codes séparés par des virgules) : « color » est un raccourci de « colors »
				$value     = (string) preg_replace('/[^#0-9a-zA-Z,]/', '', $value);
				$canonical = 'colors';
			} elseif ($canonical === 'gcolors' || $canonical === 'gcolor') {
				// Couleurs des graphiques (séparées par des virgules) : « gcolor » est un raccourci de « gcolors »
				$value     = (string) preg_replace('/[^#0-9a-zA-Z,]/', '', $value);
				$canonical = 'gcolors';
			} elseif ($canonical === 'downloadtext') {
				// Texte du bouton : sans HTML
				$value = trim(strip_tags($value));
			} elseif ($canonical === 'downloadcolor') {
				$value = (string) preg_replace('/[^#0-9a-zA-Z]/', '', $value);
			} elseif (str_starts_with($canonical, 'cpcolor_')) {
				// Couleur d'une courbe du graphique combiné (vide, none ou transparent = couleur de la trace)
				$value = (string) preg_replace('/[^#0-9a-zA-Z]/', '', $value);
				if (\in_array(strtolower($value), ['none', 'transparent'], true)) {
					$value = '';
				}
			} elseif (str_starts_with($canonical, 'cpline_')) {
				// Forme de la ligne d'une courbe du graphique combiné
				$line = self::LINE_STYLES[strtolower((string) preg_replace('/[^a-zA-Z]/', '', $value))] ?? null;
				if ($line === null) {
					return;
				}
				$value = $line;
			} elseif ($canonical === 'combcurves') {
				// Courbes du graphique combiné : h (dénivelé), s (pente), v (vitesse)
				$value = (string) preg_replace('/[^hsv,]/', '', strtolower($value));
			} elseif ($canonical === 'profilecolor') {
				$value = (string) preg_replace('/[^#0-9a-zA-Z]/', '', $value);
			} elseif ($canonical === 'satprofile') {
				if (!is_numeric($value)) {
					return;
				}
			} elseif ($canonical === 'maps') {
				// Cartes proposées dans le sélecteur : numéros ou noms séparés par des virgules
				$list = [];
				foreach (explode(',', $value) as $part) {
					$idx = self::mapIndex($part);
					if ($idx !== null) {
						$list[$idx] = $idx;
					}
				}
				if (empty($list)) {
					return;
				}
				$value = array_values($list);
			} elseif ($canonical === 'defaulticon') {
				// Nom d'icône de waypoint
				$value = (string) preg_replace('/[^A-Za-z0-9_ \-]/', '', $value);
			} elseif ($canonical === 'mapstyle') {
				$idx = self::mapIndex($value, 8);
				if ($idx === null) {
					return;
				}
				$value = $idx;
			}

			$pars->set($canonical, $value);
			$explicit[$canonical] = true; // variable écrite dans la balise (et non réglage par défaut)
		}
	}

	// Extrait la date de la trace (balise <time>) d'un contenu GPX
	private function read_make_date(string $content): string
	{
		preg_match_all('/<time>(.*?)T/s', $content, $matches);

		return $matches[1][1] ?? "";
	}

	// Remplace les balises {gpxtrackmap} de l'article par les cartes
	private function showMaps(object $article): void
	{
		$gpxTagMatches = self::findMatches($article->text);

		if ($gpxTagMatches === null) {
			return;
		}

		[$tagsAndContentsArray, $contentsArray] = $gpxTagMatches;

		$app = $this->getApplication();
		$doc = $app->getDocument();

		// Sans document HTML (flux, JSON...), on retire simplement les balises
		if (!$doc instanceof HtmlDocument) {
			$this->showtextonly($article);
			return;
		}

		// Chargement des fichiers de langue du plugin
		$app->getLanguage()->load('plg_content_gpxtm', JPATH_ADMINISTRATOR);

		// URL racine du site (gère les installations en sous-dossier)
		$rootUrl = Uri::root(true);
		$plgUrl  = $rootUrl . self::PLG_REL;
		$plgDir  = JPATH_ROOT . self::PLG_REL;

		// Bibliothèque Leaflet, moteur du plugin et graphiques
		$doc->addStyleSheet($plgUrl . "/vendor/leaflet/leaflet.css", ['version' => '1.9.4']);
		$doc->addStyleSheet($plgUrl . "/css/gpxtm-map.css", ['version' => self::VERSION]);
		$doc->addScript($plgUrl . "/vendor/leaflet/leaflet.js", ['version' => '1.9.4']);
		$doc->addScript($plgUrl . "/js/gpxtm-icons.js", ['version' => self::VERSION]);
		$doc->addScript($plgUrl . "/js/gpxtm.js", ['version' => self::VERSION]);
		$doc->addScript($plgUrl . "/js/gpxtm-charts.js", ['version' => self::VERSION]);

		// Paramètres par défaut du plugin (configurés dans l'administration)
		$parsdefaults = $this->params;
		$defjsvars    = (string) $parsdefaults->get("javascriptvars", "");
		$mapnr        = 0;

		// Langue du document (2 premières lettres)
		// Langue des textes : choix de l'administration ("auto" = langue du site / de la page)
		$langTag = (string) $this->params->get("maplang", "auto");
		if (!preg_match('/^[a-z]{2}-[A-Z]{2}$/', $langTag)) {
			$langTag = $app->getLanguage()->getTag();
		}
		try {
			$textLang = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($langTag, false);
			$textLang->load('plg_content_gpxtm', JPATH_ADMINISTRATOR);
		} catch (\Throwable $e) {
			$textLang = $app->getLanguage();
		}
		$tr      = static fn (string $key): string => $textLang->_($key);
		$doclang = substr($langTag, 0, 2);

		// Boucle de remplacement : une itération par balise trouvée
		foreach ($tagsAndContentsArray as $index => $tagAndContents) {
			// Copie des paramètres par défaut : chaque carte a ses propres réglages
			$pars = clone $parsdefaults;

			$mapnr++;

			// Identifiant de carte basé sur l'id de l'article (évite les conflits en blog de catégorie)
			$mapid = "map" . ($article->id ?? 0) . "_" . $mapnr;

			// Contenu de la balise : fichiers GPX, puis variables séparées par des points-virgules
			$gpxviewcmd = explode(";", $contentsArray[$index]);
			$gpxfiles   = self::cleanToken($gpxviewcmd[0]);

			// Les variables par défaut de l'administration passent en premier :
			// celles saisies dans l'article les remplacent
			$tokens = array_merge(explode(";", $defjsvars), \array_slice($gpxviewcmd, 1));

			$center          = "";
			$data            = []; // variables propres à cette carte (attributs data-*)
			$explicit        = []; // variables du plugin écrites dans la balise

			// Analyse des paramètres : coordonnées de centrage ou variables
			foreach ($tokens as $token) {
				$token = self::cleanToken($token);

				if ($token === '') {
					continue;
				}

				if (strpos($token, "=") === false && preg_match('/^[-+.\d]/', $token)) {
					$center = $this->check_coordinates($token);
				} else {
					$this->check_parameter($token, $pars, $data, $explicit);
				}
			}

			// Chargement de la feuille de style si l'affichage des images est activé pour cette carte
			if ($pars->get("showimages") == 1) {
				$doc->addStyleSheet($plgUrl . "/gpxtm.css", ['version' => self::VERSION]);
			}

			// Couleurs des graphiques choisies dans la balise (gcolors=#ff0000,#0000ff) ; vide = couleur de chaque trace
			$gcolList = array_values(array_filter(explode(",", (string) $pars->get("gcolors", "")), static fn ($c) => $c !== ''));

			// Choix du modèle (template) : spécifique à la carte, sinon celui par défaut.
			// Les modèles personnalisés placés dans /media/gpxtm/tmpl/ passent avant ceux du plugin et survivent aux mises à jour.
			$tmplName = basename((string) ($pars->get("ctmpl") != "" ? $pars->get("ctmpl") : $pars->get("tmpl")));
			$template = "";
			foreach ([JPATH_ROOT . self::CUSTOM_TMPL, $plgDir . "/tmpl"] as $tmplDir) {
				if ($tmplName !== "" && is_file($tmplDir . "/" . $tmplName)) {
					$template = (string) file_get_contents($tmplDir . "/" . $tmplName);
					break;
				}
			}

			// Si le modèle n'existe pas, on utilise tmpl0.html
			if ($template === "") {
				$template = (string) file_get_contents($plgDir . "/tmpl/tmpl0.html");
			}

			// Remplissage du modèle avec les valeurs
			$gpxmap = $template;
			$gpxmap = str_replace("%MAPID%", $mapid, $gpxmap);
			$gpxmap = str_replace("%MAPWIDTH%", $pars->get("map_width"), $gpxmap);
			$gpxmap = str_replace("%MAPHEIGHT%", $pars->get("map_height"), $gpxmap);
			$gpxmap = str_replace("%MAP_HP_WIDTH%", $pars->get("hpmap_width"), $gpxmap);
			$gpxmap = str_replace("%MAP_HP_HEIGHT%", $pars->get("hpmap_height"), $gpxmap);
			$gpxmap = str_replace("%MAP_SP_WIDTH%", self::dim($pars, "spmap_width", "hpmap_width", "100%"), $gpxmap);
			$gpxmap = str_replace("%MAP_SP_HEIGHT%", self::dim($pars, "spmap_height", "hpmap_height", "150px"), $gpxmap);
			$gpxmap = str_replace("%MAP_VP_WIDTH%", self::dim($pars, "vpmap_width", "hpmap_width", "100%"), $gpxmap);
			$gpxmap = str_replace("%MAP_VP_HEIGHT%", self::dim($pars, "vpmap_height", "hpmap_height", "150px"), $gpxmap);
			$gpxmap = str_replace("%MAP_CP_WIDTH%", self::dim($pars, "cpmap_width", "hpmap_width", "100%"), $gpxmap);
			$gpxmap = str_replace("%MAP_CP_HEIGHT%", self::dim($pars, "cpmap_height", "", "250px"), $gpxmap);

			// Fichiers GPX et style de carte indiqués dans la balise (fichier.gpx:style)
			$styleInTag = '';
			if (strpos($gpxfiles, ":")) {
				$ecmd       = explode(":", $gpxfiles);
				$algpx      = explode(",", $ecmd[0]);
				$styleInTag = (string) preg_replace('/[^A-Za-z0-9_ \-]/', '', $ecmd[1] ?? '');
			} else {
				$algpx = explode(",", $gpxfiles);
			}

			// Dossier des fichiers GPX (disque et URL)
			$gpxSub = trim((string) $pars->get("gpxpath"), "/\\");
			$gpxDir = JPATH_ROOT . "/images/" . $gpxSub . "/";
			$gpxUrl = $rootUrl . "/images/" . $gpxSub . "/";

			// Un dossier (dossier/ ou dossier/*) est remplacé par tous les fichiers .gpx qu'il contient
			$expanded = [];
			foreach ($algpx as $entry) {
				$list = self::expandFolder($entry, $gpxDir);
				if ($list === null) {
					$expanded[] = $entry;
				} else {
					array_push($expanded, ...$list);
				}
			}
			$algpx = $expanded;

			// Sécurité : chemins relatifs au dossier des GPX (sous-dossiers permis, jamais de « .. »), uniquement des .gpx
			$algpx = array_values(array_filter(array_map([self::class, 'cleanGpxPath'], $algpx), static fn ($f) => $f !== ''));

			// Aucun fichier GPX valide : on supprime la balise
			if (empty($algpx)) {
				$article->text = str_replace($tagAndContents, "", $article->text);
				continue;
			}

			// Classe de la carte : fichiers GPX nettoyés et style (indiqué dans la balise, sinon style par défaut)
			$style  = $styleInTag !== '' ? $styleInTag : (self::MAPS[(int) $pars->get("mapstyle", 1)] ?? self::MAPS[1])[1];
			$gpxmap = str_replace("%GPXVIEW%", "gpxtm-map", $gpxmap);

			$gpxmap = str_replace("%GPXFILES%", htmlspecialchars(implode(",", $algpx), ENT_QUOTES), $gpxmap);
			$gpxmap = str_replace("%GPXBASEURL%", htmlspecialchars($gpxUrl, ENT_QUOTES), $gpxmap);

			$gpx_content = "";
			$phpdatum    = [];

			// Lecture de chaque fichier GPX : contenu, date et liste des fichiers
			foreach ($algpx as $e) {
				$temp = is_file($gpxDir . $e) ? (string) file_get_contents($gpxDir . $e) : "";
				$phpdatum[]   = $this->read_make_date($temp);
				$gpx_content .= $temp;
			}

			// URL du premier fichier GPX
			$gpxhttppath = $gpxUrl . implode("/", array_map("rawurlencode", explode("/", $algpx[0])));
			$gpxmap = str_replace("%GPXHREF%", $gpxhttppath, $gpxmap);

			// Construction de l'avertissement (disclaimer) si activé
			if ($pars->get("disclaimer") == 1) {
				if ($phpdatum[0] != "" && ($dat = date_create($phpdatum[0]))) {
					// Avertissement avec l'année de la randonnée
					$disclaimer = "<small>" . $tr("GPXTM_JAAR_WANDELING") . date_format($dat, ' Y ') . $tr("GPXTM_DISCLAIMER") . "</small>";
				} else {
					// Avertissement sans année
					$disclaimer = "<small>" . $tr("GPXTM_DISC_ZONDER_JAAR") . "</small>";
				}
			} else {
				$disclaimer = "";
			}

			// Bouton de téléchargement du GPX (texte et couleur réglables)
			if ($pars->get("downloadgpx") == 1) {
				$btnText = trim(strip_tags((string) $pars->get("downloadtext", "")));
				if ($btnText === '') {
					$btnText = $tr("GPXTM_DOWNLOAD");
				}
				$btnColor = (string) preg_replace('/[^#0-9a-zA-Z]/', '', (string) $pars->get("downloadcolor", ""));
				if ($btnColor === '') {
					$btnColor = '#1a73e8';
				}
				$downloadgpx = "<a class=\"gpxtm-download\" href=\"" . $gpxhttppath . "\" download style=\"display:inline-block;padding:.45em 1.1em;border-radius:4px;background:"
					. $btnColor . ";color:" . self::contrastColor($btnColor) . ";text-decoration:none;font-weight:600;line-height:1.2\">"
					. htmlspecialchars($btnText, ENT_QUOTES, 'UTF-8') . "</a>  " . $disclaimer;
				$gpxmap = str_replace("%GPXDOWN%", $downloadgpx, $gpxmap);
			} else {
				$gpxmap = str_replace("%GPXDOWN%", "", $gpxmap);
			}

			// Gestion des graphiques : dénivelé (hp), pente (sp), vitesse (vp) et graphique combiné (cp)
			$hasEle  = strpos($gpx_content, "<ele>") !== false;
			$hasTime = strpos($gpx_content, "<time>") !== false || strpos($gpx_content, "<speed>") !== false;
			// Avec le graphique combiné, les graphiques séparés ne s'affichent que si la balise les demande
			$combined = $pars->get("showcombined") == 1;
			$graphs  = [
				'hp' => [$pars->get("showelevation") == 1 && $hasEle && (!$combined || isset($explicit['showelevation'])), 'hpmap'],
				'sp' => [$pars->get("showslope") == 1 && $hasEle && (!$combined || isset($explicit['showslope'])), 'spmap'],
				'vp' => [$pars->get("showspeed") == 1 && $hasTime && (!$combined || isset($explicit['showspeed'])), 'vpmap'],
				'cp' => [$pars->get("showcombined") == 1 && ($hasEle || $hasTime), 'cpmap'],
			];
			$extraGraphs = "";

			foreach ($graphs as $suffix => [$wanted, $dimKey]) {
				$idAttr = 'id="' . $mapid . '_' . $suffix . '"';

				if (strpos($gpxmap, $idAttr) !== false) {
					// Le modèle contient ce graphique : on le masque s'il n'est pas demandé
					// (ou si le fichier GPX n'a pas les données nécessaires)
					if (!$wanted) {
						$gpxmap = str_replace($idAttr, 'id="' . $mapid . '_' . $suffix . '_off" hidden', $gpxmap);
					}
				} elseif ($wanted) {
					// Le modèle ne le contient pas : on l'ajoute sous la carte
					$gWidth  = self::dim($pars, $dimKey . "_width", "hpmap_width", "100%");
					$gHeight = self::dim($pars, $dimKey . "_height", $dimKey === 'cpmap' ? "" : "hpmap_height", $dimKey === 'cpmap' ? "250px" : "150px");
					$extraGraphs .= "<div id=\"" . $mapid . "_" . $suffix . "\" style=\"margin-left:auto;margin-right:auto;width:"
						. $gWidth . ";height:" . $gHeight . "\"></div>\n";
				}
			}

			$gpxmap .= $extraGraphs;

			// Informations de la trace au-dessus de la carte (modèle tmpl-complet) : liste des éléments à afficher
			$barItems = [];
			foreach (self::BAR as $barKey => $barDefault) {
				if ($pars->get("bar_" . $barKey, $barDefault) == 1) {
					$barItems[] = $barKey;
				}
			}
			$gpxmap = str_replace('id="' . $mapid . 'trackinfo"', 'id="' . $mapid . 'trackinfo" data-items="' . implode(',', $barItems) . '"', $gpxmap);

			// Informations de la trace au-dessus de la carte (modèle tmpl-complet) : masquées si l'option est désactivée
			if ($pars->get("showtrackinfo", 1) != 1) {
				$gpxmap = str_replace('id="' . $mapid . 'trackinfo"', 'id="' . $mapid . 'trackinfo_off" hidden', $gpxmap);
			}

			// Réglages JSON de chaque graphique demandé (attribut data-gpxtm-chart lu par js/gpxtm-charts.js)
			$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
			$fill      = (float) $pars->get("satprofile");
			$fill      = ($fill > 0 && $fill <= 1) ? $fill : 0.3;
			$chartOpts = [
				'hp' => ['curves' => ['h'], 'fill' => $fill, 'gcols' => $gcolList],
				'sp' => ['curves' => ['s'], 'gcols' => $gcolList],
				'vp' => ['curves' => ['v'], 'gcols' => $gcolList],
			];

			if ($graphs['cp'][0]) {
				$curvesRaw = $pars->get("combcurves", ['h', 's', 'v']);
				if (\is_array($curvesRaw)) {
					$curvesRaw = implode(",", $curvesRaw);
				}
				$curves = array_values(array_intersect(['h', 's', 'v'], array_map('trim', explode(",", strtolower((string) $curvesRaw)))));
				if (empty($curves)) {
					$curves = ['h', 's', 'v'];
				}

				// Couleur (vide = couleur de la trace) et forme de la ligne de chaque courbe
				$defaultLines = ['h' => 'solid', 's' => 'dashed', 'v' => 'dotted'];
				$curveStyles  = [];
				foreach ($defaultLines as $key => $defaultLine) {
					$color = (string) preg_replace('/[^#0-9a-zA-Z]/', '', (string) $pars->get("cpcolor_" . $key, ""));
					if (\in_array(strtolower($color), ['none', 'transparent'], true)) {
						$color = '';
					}
					$line = self::LINE_STYLES[strtolower((string) $pars->get("cpline_" . $key, ""))] ?? $defaultLine;
					$curveStyles[$key] = ['color' => $color, 'line' => $line];
				}

				$chartOpts['cp'] = ['curves' => $curves, 'styles' => $curveStyles, 'selectable' => true, 'gcols' => $gcolList];
			}

			foreach ($chartOpts as $suffix => $chartOpt) {
				if ($graphs[$suffix][0]) {
					$gpxmap = str_replace('id="' . $mapid . '_' . $suffix . '"', 'id="' . $mapid . '_' . $suffix . '" data-gpxtm-chart=\'' . json_encode($chartOpt, $jsonFlags) . '\'', $gpxmap);
				}
			}

			// Dossier des images (disque et URL ; l'URL est aussi utilisée par le JavaScript)
			$imgSub = trim((string) $pars->get("gpximagepath"), "/\\");
			$imgDir = JPATH_ROOT . "/images/" . $imgSub . "/";
			$imgUrl = $rootUrl . "/images/" . $imgSub . "/";

			// Traitement des images si l'affichage ou la géolocalisation est activé
			$showimages = "";
			$geotagged  = "";
			if ($pars->get("showimages") == 1 || $pars->get("geoimg") == 1) {
				$geotagged  = "\n<div hidden id=\"" . $mapid . "_img\">";
				$showimages = "\n<div class=\"grid-child\"><div class=\"gpxtmrow\" >\n";

				foreach ($algpx as $e) {
					[$htmlImages, $htmlGeo] = $this->processImages($e, $imgDir, $imgUrl, $pars);
					$showimages .= $htmlImages;
					$geotagged  .= $htmlGeo;
				}

				$showimages .= "</div></div>\n";
				$geotagged  .= "</div>\n";
			}

			// Valeurs par défaut issues des réglages de l'administration, rangées dans les attributs data-* de la carte.
			// Les variables saisies dans l'article (ou dans les variables par défaut) les remplacent.
			$yesNo = static fn ($v) => $v == 1 ? "true" : "false";
			$base  = [
				'bildpfad'              => $imgUrl,
				'gpxpfad'               => $gpxUrl,
				'doclang'               => $doclang,
				'shtrh'                 => $yesNo($pars->get("showclickalt", 1)),
				'currentlocationbutton' => $yesNo($pars->get("usenavigator")),
				'shtrstart'             => $yesNo($pars->get("showstart")),
				'shtrziel'              => $yesNo($pars->get("showfinish")),
				'legende'               => $yesNo($pars->get("phpLegende")),
				'fullscreenbutton'      => $yesNo($pars->get("phpFullscreenbutton")),
				'recenter'              => $yesNo($pars->get("recenter", 1)),
				'files'                 => implode(",", $algpx),
				'style'                 => $style,
			];

			// Toutes les cartes disponibles sont proposées (IGN comprise), sauf choix contraire (variable maps)
			$mapsChoice = $pars->get("maps", []);
			if (\is_string($mapsChoice)) {
				$mapsChoice = array_filter(explode(',', $mapsChoice), 'strlen');
			}
			$mapsChoice = array_values(array_unique(array_filter(array_map('intval', (array) $mapsChoice), static fn ($i) => isset(self::MAPS[$i]))));
			if (!empty($mapsChoice) && \count($mapsChoice) < \count(self::MAPS)) {
				$base['maplist'] = implode(',', array_map(static fn ($i) => self::MAPS[$i][0], $mapsChoice));
			}

			// Options oui/non des informations de la trace et des waypoints
			foreach (self::SWITCHES as $switch => $switchDefault) {
				$base[$switch] = $yesNo($pars->get($switch, $switchDefault));
			}

			// Icône des waypoints par défaut (nom d'un fichier du dossier icons, sans extension)
			$iconName = strtolower(trim((string) preg_replace('/[^A-Za-z0-9_]/', '', (string) $pars->get("defaulticon", ""))));
			if ($iconName !== '') {
				$base['defaulticon'] = $iconName;
			}

			// Épaisseur de la trace
			if ($pars->get("trackwidth") > 0) {
				$base['twidth'] = (string) (float) $pars->get("trackwidth");
			}

			// Opacité du remplissage du profil d'altitude
			$satprofile = (float) $pars->get("satprofile");
			if ($satprofile > 0 && $satprofile <= 1) {
				$base['profilfillopac'] = (string) $satprofile;
			}

			// Couleurs des traces : la première est celle de l'administration (profilecolor)
			$firstColor = (string) preg_replace('/[^#0-9a-zA-Z]/', '', (string) $pars->get("profilecolor"));
			$palette    = [$firstColor !== '' ? $firstColor : '#ff0000', '#00ff00', '#0000ff', '#eeee00', '#ff00ff', '#00ffff', '#000000'];

			// Couleurs choisies dans la balise (colors=#ff0000,#0000ff) : une couleur par fichier GPX, dans l'ordre ;
			// les fichiers suivants reprennent les couleurs par défaut
			$custom = array_values(array_filter(explode(",", (string) $pars->get("colors", "")), static fn ($c) => $c !== ''));

			if (!empty($custom)) {
				$palette = array_merge($custom, \array_slice($palette, \count($custom)));
			} elseif ($pars->get("difcolors", 1) == 1) {
				// Sinon chaque fichier GPX de la page reçoit une couleur différente : on décale la liste
				// selon le nombre de fichiers déjà affichés. Les graphiques reprennent la couleur de leur trace.
				$shift   = self::$colorOffset % \count($palette);
				$palette = array_merge(\array_slice($palette, $shift), \array_slice($palette, 0, $shift));
			}
			self::$colorOffset += \count($algpx);

			$base['tcols'] = implode(",", $palette);

			// Couleurs des graphiques (sinon ils reprennent la couleur de leur trace)
			if (!empty($gcolList)) {
				$base['gcols'] = implode(",", $gcolList);
			}

			// Les variables de la balise remplacent les valeurs par défaut
			$data = array_merge($base, $data);

			// Coordonnées de centrage (latitude, longitude, rayon en km) : data-center
			if ($center !== "") {
				$data['center'] = $center;
			}

			// Attributs data-* ajoutés à la division de la carte : le moteur les lit pour cette carte seulement
			// Noms des attributs data-* en anglais (les variables de la balise gardent leurs noms d'origine)
			$dataNames = ['gpxpfad' => 'gpxpath', 'bildpfad' => 'imgpath', 'gpxrouten' => 'gpxroutes', 'gpxwegpunkte' => 'gpxwaypoints', 'groesseminibild' => 'thumbsize', 'legende_info' => 'legend_info', 'legende_trk' => 'legend_trk', 'legende_rte' => 'legend_rte', 'legende_wpt' => 'legend_wpt', 'legende' => 'legend', 'shtrziel' => 'shtrfinish', 'showmaptypecontroll' => 'layercontrol', 'scrollwheelzoom' => 'scrollzoom', 'currentlocationbutton' => 'locatebutton'];
			$attrs = "";
			foreach ($data as $dataName => $dataValue) {
				$dataName = $dataNames[strtolower((string) $dataName)] ?? $dataName;
				$attrs .= " data-" . $dataName . "=\"" . htmlspecialchars((string) $dataValue, ENT_QUOTES, 'UTF-8') . "\"";
			}
			$needle = 'id="' . $mapid . '"';
			$pos    = strpos($gpxmap, $needle);
			if ($pos !== false) {
				$gpxmap = substr_replace($gpxmap, $needle . $attrs, $pos, \strlen($needle));
			}

			// Réglages communs à toutes les cartes de la page (une seule fois) : accord du visiteur, langue et textes
			$config = "";
			if (!self::$configDone) {
				self::$configDone = true;
				$keys   = ['dist' => 'DIST', 'alt' => 'ALT', 'gain' => 'GAIN', 'slope' => 'SLOPE', 'speed' => 'SPEED', 'pace' => 'PACE', 'dur' => 'DUR', 'moving' => 'MOVING',
					'elapsed' => 'ELAPSED', 'time' => 'TIME', 'start' => 'START', 'finish' => 'FINISH', 'hr' => 'HR', 'cad' => 'CAD', 'temp' => 'TEMP', 'coord' => 'COORD',
					'avgspeed' => 'AVGSPEED', 'avgspeedmov' => 'AVGSPEEDMOV', 'avgpace' => 'AVGPACE', 'avgpacemov' => 'AVGPACEMOV', 'fullscreen' => 'FULLSCREEN',
					'locate' => 'LOCATE', 'recenter' => 'RECENTER', 'consentQ' => 'CONSENT_Q', 'consentNo' => 'CONSENT_NO', 'nodata' => 'NODATA', 'nomap' => 'NOMAP',
					'waypoints' => 'WAYPOINTS', 'zoomIn' => 'ZOOMIN', 'zoomOut' => 'ZOOMOUT'];
				$texts = [];
				foreach ($keys as $jsKey => $langKey) {
					$texts[$jsKey] = $tr("GPXTM_JS_" . $langKey);
				}
				$texts['recenter'] = $tr("GPXTM_RECENTER_TIP");
				$texts['curveH']   = $tr("GPXTM_CP_ELE");
				$texts['curveS']   = $tr("GPXTM_CP_SLOPE");
				$texts['curveV']   = $tr("GPXTM_CP_SPEED");
				$texts['none']     = $tr("GPXTM_CP_NONE");
				$texts['follow']   = $tr("GPXTM_CP_FOLLOW");
				$texts['auto']     = $tr("GPXTM_CP_AUTO");
				$config = "<script>window.GPXTM_CONFIG=" . json_encode([
					'consent' => $pars->get("phpBestaetigung", 1) == 1,
					'iconUrl' => $plgUrl . '/icons/',
					'lang'    => $doclang,
					't'       => $texts,
				], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";</script>";
			}

			// Assemblage final : carte + images + réglages + images géolocalisées
			$gpxmap = $gpxmap . $showimages . $config . $geotagged;

			// Remplacement de la balise dans l'article par la carte
			$article->text = str_replace($tagAndContents, $gpxmap, $article->text);
		}
	}

	/**
	 * Recherche les images associées à un fichier GPX et prépare leur affichage.
	 *
	 * @return array [html des images affichées, html des images géolocalisées]
	 */
	private function processImages(string $gpxfile, string $imgDir, string $imgUrl, Registry $pars): array
	{
		$ei = [];
		$ii = 1;

		do {
			// Première tentative : nom_du_gpx(1).JPG
			$basename = str_replace(".gpx", "(" . $ii . ").JPG", $gpxfile);
			$jpgexist = is_file($imgDir . $basename);

			// Seconde tentative : nom_du_gpx1.jpg
			if (!$jpgexist) {
				$basename = str_replace(".gpx", $ii . ".jpg", $gpxfile);
				$jpgexist = is_file($imgDir . $basename);
			}

			// Lecture des données EXIF de l'image trouvée
			if ($jpgexist) {
				$ei[$basename] = self::readinfoEXIF($imgDir . $basename, $imgUrl . $basename);
			}

			$ii++;
		} while ($jpgexist);

		$showimages = "";
		$geotagged  = "";

		// Affichage des images sous la carte, avec leur légende
		if ($pars->get("showimages") == 1) {
			foreach ($ei as $key => $value) {
				$title = htmlspecialchars($value[0], ENT_QUOTES);
				$showimages .= "<div class=\"gpxtmcol gpxtmcont\"><img src=\"" . $imgUrl . $key . "\" alt=\"" . $title . "\" style=\"width:100%\"/>";
				if (!empty($title)) {
					$showimages .= "<div class=\"gpxtmbot\">" . $title . "</div>";
				}
				$showimages .= "</div>\n";
			}
		}

		// Ajout des images géolocalisées pour affichage sur la carte
		if ($pars->get("geoimg") == 1) {
			foreach ($ei as $value) {
				$geotagged .= $value[1];
			}
		}

		return [$showimages, $geotagged];
	}

	// Convertit une fraction EXIF "num/den" en nombre décimal
	private static function exifFraction(string $value): float
	{
		$parts = explode('/', $value);
		$num   = (float) ($parts[0] ?? 0);
		$den   = (float) ($parts[1] ?? 1);

		return $den != 0.0 ? $num / $den : 0.0;
	}

	// Lit les données EXIF d'une image : titre et coordonnées GPS
	private static function readinfoEXIF(string $fullname, string $jpg): array
	{
		$title     = "";
		$geolonlat = "";

		// Le module EXIF de PHP peut être absent
		if (!\function_exists('exif_read_data')) {
			return ["", ""];
		}

		// Récupère toutes les données EXIF (@ : la lecture génère beaucoup d'avertissements)
		$exif = @exif_read_data($fullname, null, true);

		if ($exif === false) {
			// Aucune donnée EXIF trouvée
			return ["", ""];
		}

		// Titre de l'image (description)
		if (!empty($exif['IFD0']['ImageDescription'])) {
			$title = trim($exif['IFD0']['ImageDescription']);
		}

		// Les données de géolocalisation existent
		if (!empty($exif['GPS']['GPSLatitude']) && !empty($exif['GPS']['GPSLongitude'])) {
			$lat = $exif['GPS']['GPSLatitude'];
			$lon = $exif['GPS']['GPSLongitude'];

			// Conversion degrés/minutes/secondes en degrés décimaux
			$lati = self::exifFraction($lat[0]) + self::exifFraction($lat[1]) / 60.0 + self::exifFraction($lat[2]) / 3600.0;
			$loni = self::exifFraction($lon[0]) + self::exifFraction($lon[1]) / 60.0 + self::exifFraction($lon[2]) / 3600.0;

			// Hémisphères sud et ouest : valeurs négatives
			if (($exif['GPS']['GPSLatitudeRef'] ?? 'N') === 'S') {
				$lati = -$lati;
			}
			if (($exif['GPS']['GPSLongitudeRef'] ?? 'E') === 'W') {
				$loni = -$loni;
			}

			// Lien contenant les coordonnées, lu par le JavaScript
			$geolonlat = "<a href=\"" . $jpg . "\" data-geo=\"lat:" . $lati . ",lon:" . $loni . "\">" . htmlspecialchars($title, ENT_QUOTES) . "</a>";
		}

		return [$title, $geolonlat];
	}
}
