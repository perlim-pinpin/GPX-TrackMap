<?php

/**
 * @package      GPX-TrackMap
 * @author       Perlimpinpin - https://www.perlimpinpin.fr
 * @copyright    Copyright (c) 2026 Perlimpin.fr All rights reserved
 * @license      GNU General Public License version 2 or later; see LICENSE.txt
 */

// Empêche l'accès direct au fichier
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Script d'installation : supprime l'ancien serveur de mise à jour (/files/GPX-TrackMap/) qui reste enregistré
 * après une mise à jour depuis une version 1.15.0 ou antérieure. Sans cela, la même mise à jour apparaît deux fois.
 */
class PlgContentGpxtmInstallerScript
{
	public function postflight(string $type, $parent): bool
	{
		if (!\in_array($type, ['install', 'update', 'discover_install'], true)) {
			return true;
		}

		try {
			$db    = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)
				->select($db->quoteName('update_site_id'))
				->from($db->quoteName('#__update_sites'))
				->where($db->quoteName('location') . ' LIKE ' . $db->quote('%/files/GPX-TrackMap/%'));
			$ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

			if ($ids) {
				foreach (['#__updates', '#__update_sites_extensions', '#__update_sites'] as $table) {
					$delete = $db->getQuery(true)
						->delete($db->quoteName($table))
						->whereIn($db->quoteName('update_site_id'), $ids);
					$db->setQuery($delete)->execute();
				}
			}
		} catch (\Throwable $e) {
			// Le nettoyage est facultatif : une erreur ne doit pas bloquer l'installation
		}

		return true;
	}
}
