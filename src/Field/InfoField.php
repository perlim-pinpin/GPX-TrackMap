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
use Joomla\CMS\Uri\Uri;

/**
 * Champ d'information des onglets « Waypoint » et « Photos » (explications, exemples, liste des icônes).
 * L'attribut « part » choisit le contenu : waypoints_doc, waypoints_icons ou photos_doc.
 * Tous les textes viennent des fichiers de langue du plugin.
 */
class InfoField extends FormField
{
	protected $type = 'Info';

	// Échappe un texte pour l'affichage HTML
	private static function e(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}

	// Paragraphes d'explication puis exemples (les textes contiennent du HTML simple écrit dans les fichiers de langue)
	private static function doc(string $prefix, int $examples): string
	{
		$html   = [];
		if (Text::_($prefix . '_TITLE') !== $prefix . '_TITLE') {
			$html[] = '<h4 class="mt-2">' . self::e(Text::_($prefix . '_TITLE')) . '</h4>';
		}
		$html[] = '<p>' . Text::_($prefix . '_P1') . '</p>';
		$html[] = '<p>' . Text::_($prefix . '_P2') . '</p>';
		$html[] = '<h4 class="mt-3">' . self::e(Text::_('GPXTM_EXAMPLES')) . '</h4>';
		$html[] = '<ol>';
		for ($i = 1; $i <= $examples; $i++) {
			$html[] = '<li>' . Text::_($prefix . '_EX' . $i) . '</li>';
		}
		$html[] = '</ol>';

		return implode("\n", $html);
	}

	// Liste de toutes les icônes disponibles avec leur dénomination (dossier icons du plugin)
	private static function icons(): string
	{
		$base   = Uri::root(true) . '/plugins/content/gpxtm/icons/';
		$html   = [];
		$html[] = '<p>' . Text::_('GPXTM_WP_ICONS_INTRO') . '</p>';
		$html[] = '<div style="display:flex;flex-wrap:wrap;gap:.4em 0">';

		foreach ((array) glob(JPATH_PLUGINS . '/content/gpxtm/icons/*.png') as $file) {
			$name   = basename($file, '.png');
			$html[] = '<div style="width:16em;display:flex;align-items:center;gap:.5em;padding:.15em .4em">'
				. '<img src="' . self::e($base . $name . '.png') . '" alt="" width="24" height="24" loading="lazy" style="object-fit:contain">'
				. '<code>' . self::e($name) . '</code></div>';
		}

		$html[] = '</div>';

		return implode("\n", $html);
	}

	protected function getInput(): string
	{
		// Chargement des fichiers de langue du plugin dans la langue de l'administration
		Factory::getApplication()->getLanguage()->load('plg_content_gpxtm', JPATH_ADMINISTRATOR);

		switch ((string) $this->element['part']) {
			case 'waypoints_doc':
				return '<div class="gpxtm-info">' . self::doc('GPXTM_WP', 3) . '</div>';

			case 'waypoints_icons':
				return '<div class="gpxtm-info">' . self::icons() . '</div>';

			case 'tmpl_cote_doc':
				return '<div class="gpxtm-info">' . self::doc('GPXTM_TC', 5) . '</div>';

			case 'tmpl_dossier_doc':
				return '<div class="gpxtm-info">' . self::doc('GPXTM_TD', 3) . '</div>';

			case 'photos_doc':
				return '<div class="gpxtm-info">' . self::doc('GPXTM_PH', 3) . '</div>';
		}

		return '';
	}
}
