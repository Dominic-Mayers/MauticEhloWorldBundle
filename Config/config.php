<?php
//define("TMP", "/home/dominic/tmp/debug.log");
return [
    'name'        => 'Email Marketing',
    'description' => 'Enables integration with Mautic supported email marketing services.',
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
