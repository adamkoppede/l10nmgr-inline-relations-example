<?php

use B13\Container\Tca\ContainerConfiguration;
use B13\Container\Tca\Registry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die();

(static function (): void {
    $containerRegistry = GeneralUtility::makeInstance(
        Registry::class
    );
    $containerRegistry->configureContainer(
        new ContainerConfiguration(
            'example_containerParent',
            'Container Parent Content Element',
            'Container Parent Content Element',
            [
                [
                    [
                        'name' => 'Children',
                        'colPos' => 999,
                        'allowed' => [
                            'CType' => 'example_containerChild'
                        ]
                    ]
                ]
            ]
        )
    );
    $containerRegistry->configureContainer(
        new ContainerConfiguration(
            'example_containerChild',
            'Container Child Content Element',
            'Container Child Content Element',
            [
                [
                    [
                        'name' => 'Content',
                        'colPos' => 1000,
                        'allowed' => [
                            'CType' => 'example_wallCollection'
                        ]
                    ]
                ]
            ]
        )
    );
})();

(static function (): void {
    ExtensionManagementUtility::addPlugin(
        [
            'Wall Collection Content Element',
            'example_wallCollection',
        ],
        'CType',
        'example'
    );
    ExtensionManagementUtility::addTCAcolumns(
        'tt_content',
        [
            'tx_example_relation_wall' => [
                'label' => 'Walls',
                'config' => [
                    'type' => 'inline',
                    'foreign_table' => 'tx_example_wall',
                    'foreign_field' => 'tt_content',
                    'maxitems' => 10,
                    'appearance' => [
                        'useSortable' => true,
                        'showSynchronizationLink' => true,
                        'showAllLocalizationLink' => true,
                        'showPossibleLocalizationRecords' => true,
                        'expandSingle' => true,
                        'enabledControls' => [
                            'localize' => true,
                        ],
                    ],
                ]
            ],
            'tx_example_relation_brick' => [
                'label' => 'Bricks',
                'config' => [
                    'type' => 'inline',
                    'foreign_table' => 'tx_example_brick',
                    'foreign_field' => 'tt_content',
                    'maxitems' => 10,
                    'appearance' => [
                        'useSortable' => true,
                        'showSynchronizationLink' => true,
                        'showAllLocalizationLink' => true,
                        'showPossibleLocalizationRecords' => true,
                        'expandSingle' => true,
                        'enabledControls' => [
                            'localize' => true,
                        ],
                    ],
                ]
            ],
        ]
    );
})();
