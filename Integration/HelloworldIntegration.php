<?php

namespace MauticPlugin\MauticHelloWorldBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

class HelloworldIntegration extends AbstractIntegration
{
    public function getName(): string
    {
        return 'Helloworld';
    }

    public function getDisplayName(): string
    {
        return 'Hello World';
    }

    public function getAuthenticationType(): string
    {
        return 'oauth2';
    }

    public function getAuthenticationUrl(): string
    {    
        $instance = isset($_SERVER['MAUTIC_NAME'])? "/mautic/{$_SERVER['MAUTIC_NAME']}" : ''; 
        return 'https://loc.tmorg.ca'.$instance.'/authorize_client';
    }

    public function getRequiredKeyFields(): array
    {
        return [
            'client_id'     => 'mautic.integration.keyfield.clientid',
            'client_secret' => 'mautic.integration.keyfield.clientsecret',
        ];
    }
}
