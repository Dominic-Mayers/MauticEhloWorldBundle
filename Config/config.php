<?php

//define("TMP", "/home/dominic/tmp/debug.log");
return [
    'name'        => 'Ehlo World',
    'description' => 'Enables integration with Mautic Ehlo World services.',
    'version'     => '1.0',
    'author'      => 'Mautic',

    'services' => [
        'integrations' => [
            'mautic.integration.gmailsmtp' => [
                'class'     => MauticPlugin\MauticEhloWorldBundle\Integration\GmailSmtpIntegration::class,
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
];
