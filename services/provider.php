<?php

/**
 * Finder.PhocaGalleryTags 
 * copyright 		: Copyright (C) 2023 ConseilGouz. All rights reserved.
 * license    		: http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Phoca\Plugin\Finder\PhocaGalleryTag\Extension\PhocaGalleryTag;
// Make sure that Joomla has registered the namespace for the plugin

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.3.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin     = new PhocaGalleryTag(
                    $dispatcher,
                    (array) PluginHelper::getPlugin('finder', 'phocagallerytag')
                );
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));
                return $plugin;
            }
        );
    }
};
