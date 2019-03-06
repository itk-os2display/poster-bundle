<?php

namespace Os2Display\PosterBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Os2Display\CoreBundle\DependencyInjection\Os2DisplayBaseExtension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\FileLocator;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class Os2DisplayPosterExtension extends Os2DisplayBaseExtension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->dir = __DIR__;

        parent::load($configs, $container);
    }
}
