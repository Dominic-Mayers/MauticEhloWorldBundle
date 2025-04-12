<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [];

    $services->load('MauticPlugin\\MauticHelloWorldBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    $services->load('KnpU\\OAuth2ClientBundle\\Client\\', '../../../vendor/knpuniversity/oauth2-client-bundle/src/Client/')
        ->exclude('../../../vendor/knpuniversity/oauth2-client-bundle/src/Client/{OAuth2Client.php,OAuth2ClientInterface.php,OAuth2PKCEClient.php}');

    $services->alias(League\OAuth2\Client\Provider\AbstractProvider::class, League\OAuth2\Client\Provider\Google::class);
    $services->load('League\\OAuth2\\Client\\Provider\\', '../../../vendor/league/oauth2-google/src/Provider/')
       ->exclude('../../../vendor/league/oauth2-google/src/Provider/{GoogleUser.php}');

    $services->get(KnpU\OAuth2ClientBundle\Client\ClientRegistry::class)->arg('$serviceMap', '%mautic.services_knpu%');
    $services->get(League\OAuth2\Client\Provider\Google::class)->arg('$options', '%mautic.google_options%');
};
