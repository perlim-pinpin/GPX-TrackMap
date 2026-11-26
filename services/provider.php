<?php

/**
 * @package      GPX-TrackMap
 * @copyright    Copyright (c) 2026 Perlimpin.fr All rights reserved
 * @license      GNU/GPL license: https://www.gnu.org/licenses/gpl.html
 */

// Empêche l'accès direct au fichier
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Perlimpinpin\Plugin\Content\Gpxtm\Extension\Gpxtm;

// Fournisseur de services : Joomla l'utilise pour instancier le plugin
return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Gpxtm(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('content', 'gpxtm')
                );
                // Injecte l'application dans le plugin (getApplication())
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
