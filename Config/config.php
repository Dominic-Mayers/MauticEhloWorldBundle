<?php

use Symfony\Component\Dotenv\Dotenv;

$instance_name_dir = isset($_SERVER['MAUTIC_NAME']) ? $_SERVER['MAUTIC_NAME'] . "/": ""; 
(new Dotenv())->loadEnv('config/'.$instance_name_dir.'.env.tokens.local', overrideExistingVars: true);

// Until I figure out how to get the site_url parameter in a config file:
$site_dir_url= $_ENV['SITE_DIR_URL'] ??  'https://'.$_SERVER['SERVER_NAME'].dirname($_SERVER['SCRIPT_NAME']);  

return [
    'name'        => 'Hello World',
    'description' => 'Enables Hello World Integration.',
    'version'     => '1.0',
    'author'      => 'Mautic',
    'services'    => [
        'integrations' => [
            'mautic.integration.helloworld' => [
                'class'     => MauticPlugin\MauticHelloWorldBundle\Integration\HelloworldIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                    'mautic.lead.field.fields_with_unique_identifier',
                ],
            ],
        ],
    ],
    'parameters'  => [
        'services_knpu' => [
            'google' => 'KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient',
        ],
        'google_options' => [
            'clientId'     => '%env(CLIENT_ID)%',
            'clientSecret' => '%env(CLIENT_SECRET)%',
            'accessType'   => 'offline',
            'redirectUri'  => $site_dir_url.'/manage_token',
        ],
    ],
    'routes'      => [
        'public' => [
            'mautic_integration_auth_callback_secure' => [
                'path'         => '/manage_token',
                'controller'   => '\MauticPlugin\MauticHelloWorldBundle\Controller\AuthorizationController::connectCheckAction',
            ],
            'plugin_helloworld_manage_token' => [
                'path'         => '/manage_token',
                'controller'   => '\MauticPlugin\MauticHelloWorldBundle\Controller\AuthorizationController::connectCheckAction',
            ],
            'plugin_helloworld_authorize_client' => [
                'path'         => '/authorize_client',
                'controller'   => '\MauticPlugin\MauticHelloWorldBundle\Controller\AuthorizationController::authorize_client',
            ],
            'plugin_helloworld_manage_token' => [
                'path'         => '/manage_token',
                'controller'   => '\MauticPlugin\MauticHelloWorldBundle\Controller\AuthorizationController::connectCheckAction',
            ],
            'plugin_helloworld_test' => [
                'path'         => '/hello_test',
                'controller'   => 'MauticPlugin\MauticHelloWorldBundle\Controller\TestController::testHelloWorld',
            ],
        ],
        'main' => [
            'mautic_integration_auth_callback_secure' => [
                'path'         => '/manage_token',
                'controller'   => '\MauticPlugin\MauticHelloWorldBundle\Controller\AuthorizationController::connectCheckAction',
            ],
        ],
    ],
];
