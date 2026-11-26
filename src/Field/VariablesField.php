<?php

/**
 * @package      GPX-TrackMap
 * @copyright    Copyright (c) 2026 Perlimpin.fr All rights reserved
 * @license      GNU/GPL license: https://www.gnu.org/licenses/gpl.html
 */

namespace Perlimpinpin\Plugin\Content\Gpxtm\Field;

// Empêche l'accès direct au fichier
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Perlimpinpin\Plugin\Content\Gpxtm\Helper\Variables;

/**
 * Champ d'information : affiche la liste des variables dans l'onglet « Variables ».
 * Tous les textes sont traduits avec les fichiers de langue du plugin (fr-FR, en-GB, de-DE, nl-NL) ;
 * le français contenu dans le catalogue sert de texte de secours.
 */
class VariablesField extends FormField
{
	protected $type = 'Variables';

	// Échappe un texte pour l'affichage HTML
	private static function e(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}

	// Clé de langue d'une variable (même règle que celle qui a servi à écrire les fichiers de langue)
	private static function key(string $name): string
	{
		return 'GPXTM_V_' . trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper($name)), '_');
	}

	// Traduit une clé ; si elle est absente, le texte de secours (français) est utilisé
	private static function tr(string $key, string $fallback): string
	{
		$text = Text::_($key);

		return $text === $key ? $fallback : $text;
	}

	// Dégradé de couleurs de la barre latérale de la carte (même échelle que la barre de couleurs de la carte) : bas = valeur minimale
	private static function gradient(): string
	{
		$n     = 1000;
		$stops = [];

		for ($k = 0; $k <= 20; $k++) {
			$i   = (int) round($k * ($n - 1) / 20);
			$rgb = [];

			foreach ([0.75, 0.50, 0.25] as $pos) {
				$t     = ($i - $pos * $n) / ($n / 3);
				$rgb[] = min(255, (int) round(255 * exp(-$t * $t)));
			}

			$stops[] = 'rgb(' . implode(',', $rgb) . ') ' . ($k * 5) . '%';
		}

		$label = self::e(self::tr('GPXTM_TRCOLMOD_SCALE', 'Échelle de couleurs affichée sur le côté de la carte : du minimum (bas) au maximum (haut)'));

		return '<div style="display:flex;gap:.6em;align-items:stretch;margin-top:.5em"><span title="' . $label . '" style="display:inline-block;width:30px;min-height:90px;border:1px solid #888;background:linear-gradient(to top,'
			. implode(',', $stops) . ')"></span><small style="align-self:center">' . $label . '</small></div>';
	}

	// Construit le contenu de la colonne « Valeurs » (la valeur par défaut est soulignée)
	private static function values(array $row): string
	{
		[$kind, $name, $type, $default, , $options, $hint] = array_pad($row, 7, null);

		// Variables du plugin : la valeur par défaut est définie dans l'administration
		if ($kind === 'plg') {
			$info = $hint ? '<small>' . self::e(self::tr(self::key($name) . '_H', $hint)) . '</small>' : '';

			if ($type === 'pbool') {
				return '1 / 0' . ($info !== '' ? '<br>' . $info : '');
			}

			return $info !== '' ? $info : '<em>' . self::e(self::tr('GPXTM_VARS_TEXT', 'texte')) . '</em>';
		}

		// Balises de modèle
		if ($kind === 'tpl') {
			return '—';
		}

		// Liste d'options (la valeur par défaut est soulignée)
		if ($type === 'bool') {
			$options = ['true', 'false'];
		}

		if (\is_array($options)) {
			$items = [];
			foreach ($options as $option) {
				$text = self::e($option);
				$items[] = ($default !== null && $option === $default) ? '<u>' . $text . '</u>' : $text;
			}

			return implode(' / ', $items);
		}

		// Valeur libre : on souligne la valeur par défaut
		return '<u>' . self::e((string) $default) . '</u>';
	}

	protected function getInput(): string
	{
		// Chargement des fichiers de langue du plugin dans la langue de l'administration
		Factory::getApplication()->getLanguage()->load('plg_content_gpxtm', JPATH_ADMINISTRATOR);

		// Textes d'introduction (texte de secours en français)
		$intro = [
			'Syntaxe : <code>{gpxtrackmap}fichier.gpx;Variable1=valeur;Variable2=valeur{/gpxtrackmap}</code>. Plusieurs fichiers : <code>fichier1.gpx,fichier2.gpx</code>.',
			'La valeur par défaut est <u>soulignée</u>. Les réglages de l’administration les remplacent et les variables saisies dans l’article ont la priorité.',
			'Les valeurs « true / false » acceptent aussi 1 / 0. Les textes s’écrivent avec ou sans guillemets. Les noms de variables ne sont pas sensibles à la casse.',
			'Pour centrer la carte sur un point : <code>{gpxtrackmap}fichier.gpx;49.953814,6.023185,.7;{/gpxtrackmap}</code> (latitude, longitude, rayon en km).',
			'Chaque balise <code>{gpxtrackmap}</code> a ses propres réglages : couleurs, graphiques affichés (<code>showelevation</code>, <code>showslope</code>, <code>showspeed</code>, <code>showcombined</code>), photos, bouton, etc. Couleur des graphiques différente de celle de la trace : <code>gcolor=#ff8800</code>.',
			'Deux fichiers GPX avec des couleurs choisies : dans une seule balise <code>{gpxtrackmap}a.gpx,b.gpx;colors=#ff0000,#0000ff{/gpxtrackmap}</code>, ou dans deux balises, chacune avec sa couleur : <code>{gpxtrackmap}a.gpx;color=#ff0000{/gpxtrackmap}</code> puis <code>{gpxtrackmap}b.gpx;color=#0000ff{/gpxtrackmap}</code>. Les graphiques reprennent la couleur de leur trace.',
			'La variable <code>Scaling</code> (échelle des graphiques) n’est pas utilisable dans une balise.',
		];

		$html   = [];
		$html[] = '<div class="gpxtm-variables">';
		$html[] = '<p>' . self::tr('GPXTM_VARS_P0', $intro[0]) . '</p>';
		$html[] = '<ul>';
		for ($i = 1; $i < \count($intro); $i++) {
			$html[] = '<li>' . self::tr('GPXTM_VARS_P' . $i, $intro[$i]) . '</li>';
		}
		$html[] = '</ul>';

		$col1 = self::e(self::tr('GPXTM_VARS_COL1', 'Variable'));
		$col2 = self::e(self::tr('GPXTM_VARS_COL2', 'Signification'));
		$col3 = self::e(self::tr('GPXTM_VARS_COL3', 'Valeurs'));
		$plg  = self::e(self::tr('GPXTM_VARS_PLUGIN', '(plugin)'));

		foreach (Variables::sections() as $section) {
			$html[] = '<h3 class="mt-4">' . self::e(self::tr('GPXTM_SEC_' . ($section['n'] + 1), $section['title'])) . '</h3>';
			$html[] = '<table class="table table-striped table-sm">';
			$html[] = '<thead><tr><th scope="col" style="width:22%">' . $col1 . '</th>'
				. '<th scope="col" style="width:48%">' . $col2 . '</th>'
				. '<th scope="col" style="width:30%">' . $col3 . '</th></tr></thead>';
			$html[] = '<tbody>';

			foreach ($section['rows'] as $row) {
				$kind    = $row[0];
				$keyBase = self::key($row[1]);

				// Le nom de la variable est le même dans toutes les langues, sauf l'exemple de syntaxe fichier.gpx:Style
				$shown = $kind === 'syn' ? self::tr($keyBase . '_N', $row[1]) : $row[1];
				$name  = '<code>' . self::e($shown) . '</code>';

				if ($kind === 'plg') {
					$name .= '<br><small>' . $plg . '</small>';
				}

				$vals = self::values($row);

				// Trcolmod : le dégradé de couleurs de la barre latérale de la carte est montré sous les valeurs
				if (strtolower($row[1]) === 'trcolmod') {
					$vals .= self::gradient();
				}

				$html[] = '<tr><td>' . $name . '</td><td>' . self::e(self::tr($keyBase, $row[4])) . '</td><td>' . $vals . '</td></tr>';
			}

			$html[] = '</tbody></table>';
		}

		$html[] = '</div>';

		return implode("\n", $html);
	}
}
