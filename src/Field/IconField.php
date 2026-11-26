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

/**
 * Liste des icônes de waypoints : tous les fichiers PNG du dossier icons du plugin (SJJB Map Icons, domaine public).
 */
class IconField extends ListField
{
	protected $type = 'Icon';

	protected function getOptions(): array
	{
		Factory::getApplication()->getLanguage()->load('plg_content_gpxtm', JPATH_ADMINISTRATOR);

		$options   = parent::getOptions();
		$options[] = HTMLHelper::_('select.option', '', Text::_('GPXTM_ICON_DEFAULT'));

		foreach ((array) glob(JPATH_PLUGINS . '/content/gpxtm/icons/*.png') as $file) {
			$name      = basename($file, '.png');
			$options[] = HTMLHelper::_('select.option', $name, $name);
		}

		return $options;
	}
}
