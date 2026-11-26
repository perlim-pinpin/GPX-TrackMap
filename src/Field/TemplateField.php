<?php

/**
 * @package      GPX-TrackMap
 * @author       Perlimpinpin - https://www.perlimpinpin.fr
 * @copyright    Copyright (c) 2026 Perlimpin.fr All rights reserved
 * @license      GNU/GPL license: https://www.gnu.org/licenses/gpl.html
 */

namespace Perlimpinpin\Plugin\Content\Gpxtm\Field;

// Empêche l'accès direct au fichier
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Perlimpinpin\Plugin\Content\Gpxtm\Extension\Gpxtm;

/**
 * Liste des modèles (templates) : ceux du plugin et les modèles personnalisés du dossier /media/gpxtm/tmpl/,
 * qui ne sont jamais écrasés par une mise à jour ou une réinstallation.
 */
class TemplateField extends ListField
{
	protected $type = 'Template';

	protected function getOptions(): array
	{
		Factory::getApplication()->getLanguage()->load('plg_content_gpxtm', JPATH_ADMINISTRATOR);

		$options = parent::getOptions();
		$files   = [];

		// Modèles du plugin, puis modèles personnalisés (un fichier de même nom remplace celui du plugin)
		foreach ([JPATH_PLUGINS . '/content/gpxtm/tmpl' => false, JPATH_ROOT . Gpxtm::CUSTOM_TMPL => true] as $dir => $custom) {
			if (!is_dir($dir)) {
				continue;
			}

			foreach ((array) glob($dir . '/*.html') as $file) {
				$files[basename($file)] = $custom || ($files[basename($file)] ?? false);
			}
		}

		ksort($files, SORT_NATURAL | SORT_FLAG_CASE);

		foreach ($files as $name => $custom) {
			$options[] = HTMLHelper::_('select.option', $name, $name . ($custom ? ' ' . Text::_('GPXTM_CUSTOM_MARK') : ''));
		}

		return $options;
	}
}
