<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Filesystem\Filesystem;

return static function (ContainerConfigurator $container) {
    $proxyDirectory = dirname(__DIR__, 2) . '/var/cache/' . $_SERVER['APP_ENV'] . '/orm-proxies';

    $container->parameters()
        ->set('orm_array_proxy.proxy_directory', $proxyDirectory);

    $filesystem = new Filesystem();
    if (!$filesystem->exists($proxyDirectory)) {
        $filesystem->mkdir($proxyDirectory);
    }

    $container->extension('doctrine', [
        'orm' => [
            'mappings' => [
                'ORMArrayProxy' => [
                    'is_bundle' => false,
                    'type' => 'staticphp',
                    'dir' => $proxyDirectory,
                    'prefix' => 'ORMArrayProxy',
                    'alias' => 'ORMArrayProxy',
                ]
            ],
        ],
    ]);
};
