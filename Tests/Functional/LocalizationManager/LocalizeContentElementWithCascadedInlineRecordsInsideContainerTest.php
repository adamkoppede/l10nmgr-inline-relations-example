<?php

declare(strict_types=1);

namespace Example\Example\Tests\Functional\LocalizationManager;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Localizationteam\L10nmgr\Model\L10nConfiguration;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Our most complicated use-case:
 *
 * ```
 *  Container
 *      => Container
 *          => Content Element
 *              => inline relation (brick)
 *              => inline relation (wall)
 *                  => inline relation (brick)
 * ```
 */
class LocalizeContentElementWithCascadedInlineRecordsInsideContainerTest extends AbstractLocalizationManagerTestCase
{
    /**
     * @throws Exception
     * @throws DBALException
     */
    public function testLocalizationOfWallLocalizesBricks(): void
    {
        [$virtualBrickId, $getBrickId] = $this->createPlaceholderRecordId();
        [$virtualWallId, $getWallId] = $this->createPlaceholderRecordId();

        $this->runDataHandler([
            'tx_example_brick' => [
                $virtualBrickId => [
                    'pid' => 1,
                    'title' => 'Brick',
                    'tx_example_wall' => $virtualWallId
                ]
            ],
            'tx_example_wall' => [
                $virtualWallId => [
                    'pid' => 1,
                    'title' => 'Brick',
                    'relation_brick' => $virtualBrickId
                ]
            ]
        ], []);

        $wallId = $getWallId();
        $brickId = $getBrickId();

        $this->runDataHandler([], [
            'tx_example_brick' => [$brickId => ['localize' => 1]],
            'tx_example_wall' => [$wallId => ['localize' => 1]],
        ]);

        $translatedWall = $this->fetchLocalizationOfRecord('tx_example_wall', $wallId);
        $translatedBrick = $this->fetchLocalizationOfRecord('tx_example_brick', $brickId);
        self::assertEquals($translatedWall['uid'], $translatedBrick['tx_example_wall']);
    }

    /**
     * @throws DBALException
     * @throws Exception
     * @psalm-suppress MixedArrayAccess
     */
    protected function fetchLocalizationOfRecord(string $tableName, int $recordId): array
    {
        $localizations = BackendUtility::getRecordLocalization($tableName, $recordId, 1);

        self::assertIsArray($localizations);
        self::assertCount(1, $localizations);
        self::assertIsArray($localizations[0]);
        self::assertNotEmpty($localizations[0]);
        self::assertIsInt($localizations[0]['uid']);
        self::assertEquals(1, $localizations[0]['sys_language_uid']);

        $translationPointerField = isset($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'])
            ? (string)$GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField']
            : 'l10n_parent';

        self::assertEquals(
            $recordId,
            $localizations[0][$translationPointerField]
        );

        return $localizations[0];
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    public function testLocalizeContentElementUsingDataHandler(): void
    {
        [$contentElementId, $inlineRecordId, $parentContainerId, $childContainerId, $upperBrickId, $lowerBrickId] = $this->prepareDefaultLanguageELementsWithNoElementLocalized();

        $this->runDataHandler([], [
            'pages' => [1 => ['localize' => 1]],
            'tt_content' => [$parentContainerId => ['localize' => 1]],
        ]);

        $this->checkLocalizationsOfRecords(
            $contentElementId,
            $inlineRecordId,
            $parentContainerId,
            $childContainerId,
            $upperBrickId,
            $lowerBrickId
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int}
     */
    protected function prepareDefaultLanguageELementsWithNoElementLocalized(): array
    {
        [$virtualWallId, $retrieveWallId] = $this->createPlaceholderRecordId();
        [$virtualPluginElementId, $retrievePluginElementId] = $this->createPlaceholderRecordId();
        [$virtualContainerParentId, $retrieveContainerParentId] = $this->createPlaceholderRecordId();
        [$virtualContainerChildId, $retrieveContainerChildId] = $this->createPlaceholderRecordId();
        [$virtualUpperBrickId, $retrieveUpperBrickId] = $this->createPlaceholderRecordId();
        [$virtualLowerBrickId, $retrieveLowerBrickId] = $this->createPlaceholderRecordId();

        $this->runDataHandler([
            'tx_example_brick' => [
                $virtualUpperBrickId => [
                    'pid' => 1,
                    'title' => 'Upper Brick',
                    'tt_content' => $virtualPluginElementId
                ],
                $virtualLowerBrickId => [
                    'pid' => 1,
                    'title' => 'Lower Brick',
                    'tx_example_wall' => $virtualWallId
                ]
            ],
            'tx_example_wall' => [
                $virtualWallId => [
                    'pid' => 1,
                    'title' => 'Inner Wall Title',
                    'tt_content' => $virtualPluginElementId,
                    'relation_brick' => $virtualLowerBrickId
                ]
            ],
            'tt_content' => [
                $virtualContainerParentId => [
                    'pid' => 1,
                    'CType' => 'example_containerParent',
                    'header' => 'Container Parent Element',
                ],
                $virtualContainerChildId => [
                    'pid' => 1,
                    'CType' => 'example_containerChild',
                    'header' => 'Container Child Element',
                    'tx_container_parent' => $virtualContainerParentId
                ],
                $virtualPluginElementId => [
                    'pid' => 1,
                    'CType' => 'example_wallCollection',
                    'tx_example_relation_wall' => $virtualWallId,
                    'tx_example_relation_brick' => $virtualUpperBrickId,
                    'header' => 'Wall Collection Content Element',
                    'tx_container_parent' => $virtualContainerChildId
                ]
            ]
        ], []);

        return [
            $retrievePluginElementId(),
            $retrieveWallId(),
            $retrieveContainerParentId(),
            $retrieveContainerChildId(),
            $retrieveUpperBrickId(),
            $retrieveLowerBrickId()
        ];
    }

    /**
     * Each of the prepared records should have exactly one localization.
     * The parent pointer of the child record localization should point at the localization of the parent record.
     *
     * @param int $parentContentElementId
     * @param int $childInlineId
     * @param int $containerContentElementParentId
     * @param int $containerContentElementChildId
     * @param int $upperBrickId
     * @param int $lowerBrickId
     * @return void
     * @throws DBALException
     * @throws Exception
     */
    protected function checkLocalizationsOfRecords(
        int $parentContentElementId,
        int $childInlineId,
        int $containerContentElementParentId,
        int $containerContentElementChildId,
        int $upperBrickId,
        int $lowerBrickId
    ): void {
        $this->fetchLocalizationOfRecord('tt_content', $containerContentElementParentId);
        $this->fetchLocalizationOfRecord('tt_content', $containerContentElementChildId);
        $parentPluginLocalization = $this->fetchLocalizationOfRecord('tt_content', $parentContentElementId);
        $childInlineLocalization = $this->fetchLocalizationOfRecord('tx_example_wall', $childInlineId);
        $upperBrickLocalization = $this->fetchLocalizationOfRecord('tx_example_brick', $upperBrickId);
        $lowerBrickLocalization = $this->fetchLocalizationOfRecord('tx_example_brick', $lowerBrickId);

        // the parent field pointer should point at the localization
        self::assertEquals($parentPluginLocalization['uid'], $childInlineLocalization['tt_content']);
        self::assertEquals($parentPluginLocalization['uid'], $upperBrickLocalization['tt_content']);
        self::assertEquals($childInlineLocalization['uid'], $lowerBrickLocalization['tx_example_wall']);
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws SiteNotFoundException
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function testLocalizeContentElementUsingFullXml(): void
    {
        [$contentElementId, $inlineRecordId, $parentContainerId, $childContainerId, $upperBrickId, $lowerBrickId] = $this->prepareDefaultLanguageELementsWithNoElementLocalized();
        $configurationId = $this->insertRowUsingDataHandler('tx_l10nmgr_cfg', [
            'pid' => 1,
            'depth' => 0,
            'tablelist' => 'pages,tt_content,tx_example_wall,tx_example_brick',
        ]);

        $configuration = GeneralUtility::makeInstance(L10nConfiguration::class);
        $configuration->load($configurationId);
        $localizationManager = $this->createTestLocalizationManagerInstance();

        $xmlString = $localizationManager->testExport(
            $configuration,
            1
        );

        $localizationManager->testImport(
            $configuration,
            $xmlString,
            1
        );
        $this->checkDataHandlerForErrors();

        $this->checkLocalizationsOfRecords(
            $contentElementId,
            $inlineRecordId,
            $parentContainerId,
            $childContainerId,
            $upperBrickId,
            $lowerBrickId
        );
    }

    /**
     * @throws \TYPO3\CMS\Core\Exception
     * @throws Exception
     * @throws DBALException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function testLocalizeContentElementUsingOnlyDeepestChildInXml(): void
    {
        [$contentElementId, $inlineRecordId, $parentContainerId, $childContainerId, $upperBrickId, $lowerBrickId] = $this->prepareDefaultLanguageELementsWithNoElementLocalized();
        $configurationId = $this->insertRowUsingDataHandler('tx_l10nmgr_cfg', [
            'pid' => 1,
            'depth' => 0,
            'tablelist' => 'pages,tt_content,tx_example_wall,tx_example_brick',
        ]);

        $configuration = GeneralUtility::makeInstance(L10nConfiguration::class);
        $configuration->load($configurationId);
        $localizationManager = $this->createTestLocalizationManagerInstance();

        $xmlLines = explode("\n", $localizationManager->testExport(
            $configuration,
            1
        ));

        // unset all except lower brick lines, to simulate only changed content / no hidden situations.
        foreach ($xmlLines as $index => $xmlLine) {
            if (str_contains($xmlLine, 'table="tt_content"')) {
                $xmlLines[$index] = '';
            }

            if (str_contains($xmlLine, 'table="tx_example_wall"')) {
                $xmlLines[$index] = '';
            }

            if (str_contains($xmlLine, 'table="tx_example_brick"') && str_contains($xmlLine, "elementUid=\"$upperBrickId\"")) {
                $xmlLines[$index] = '';
            }
        }

        $localizationManager->testImport(
            $configuration,
            implode("\n", $xmlLines),
            1
        );
        $this->checkDataHandlerForErrors();

        $this->checkLocalizationsOfRecords(
            $contentElementId,
            $inlineRecordId,
            $parentContainerId,
            $childContainerId,
            $upperBrickId,
            $lowerBrickId
        );
    }

    /**
     * @throws DBALException
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function testLocalizeUsingOnlyDeepestChildInXmlWithAlreadyLocalizedPluginElement(): void
    {
        [$virtualPluginElementId, $retrievePluginElementId] = $this->createPlaceholderRecordId();
        [$virtualContainerParentId, $retrieveContainerParentId] = $this->createPlaceholderRecordId();
        [$virtualContainerChildId, $retrieveContainerChildId] = $this->createPlaceholderRecordId();

        $this->runDataHandler([
            'tt_content' => [
                $virtualContainerParentId => [
                    'pid' => 1,
                    'CType' => 'example_containerParent',
                    'header' => 'Container Parent Element',
                ],
                $virtualContainerChildId => [
                    'pid' => 1,
                    'CType' => 'example_containerChild',
                    'header' => 'Container Child Element',
                    'tx_container_parent' => $virtualContainerParentId
                ],
                $virtualPluginElementId => [
                    'pid' => 1,
                    'CType' => 'example_wallCollection',
                    'tx_example_relation_wall' => '',
                    'tx_example_relation_brick' => '',
                    'header' => 'Wall Collection Content Element',
                    'tx_container_parent' => $virtualContainerChildId
                ]
            ]
        ], []);

        $pluginElementId = $retrievePluginElementId();
        $parentContainerId = $retrieveContainerParentId();
        $childContainerId = $retrieveContainerChildId();

        $this->runDataHandler([], [
            'pages' => [1 => ['localize' => 1]],
            'tt_content' => [$parentContainerId => ['localize' => 1]],
        ]);

        [$virtualWallId, $retrieveWallId] = $this->createPlaceholderRecordId();
        [$virtualUpperBrickId, $retrieveUpperBrickId] = $this->createPlaceholderRecordId();
        [$virtualLowerBrickId, $retrieveLowerBrickId] = $this->createPlaceholderRecordId();

        $this->runDataHandler([
            'tt_content' => [
                $pluginElementId => [
                    'tx_example_relation_wall' => $virtualWallId,
                    'tx_example_relation_brick' => $virtualUpperBrickId
                ]
            ],
            'tx_example_brick' => [
                $virtualUpperBrickId => [
                    'pid' => 1,
                    'title' => 'Upper Brick',
                    'tt_content' => $virtualPluginElementId
                ],
                $virtualLowerBrickId => [
                    'pid' => 1,
                    'title' => 'Lower Brick',
                    'tx_example_wall' => $virtualWallId
                ]
            ],
            'tx_example_wall' => [
                $virtualWallId => [
                    'pid' => 1,
                    'title' => 'Inner Wall Title',
                    'tt_content' => $virtualPluginElementId,
                    'relation_brick' => $virtualLowerBrickId
                ]
            ],
        ], []);

        $wallId = $retrieveWallId();
        $upperBrickId = $retrieveUpperBrickId();
        $lowerBrickId = $retrieveLowerBrickId();

        $configurationId = $this->insertRowUsingDataHandler('tx_l10nmgr_cfg', [
            'pid' => 1,
            'depth' => 0,
            'tablelist' => 'pages,tt_content,tx_example_wall,tx_example_brick',
        ]);

        $configuration = GeneralUtility::makeInstance(L10nConfiguration::class);
        $configuration->load($configurationId);
        $localizationManager = $this->createTestLocalizationManagerInstance();

        $xmlLines = explode("\n", $localizationManager->testExport(
            $configuration,
            1
        ));

        // unset all except both brick lines, to simulate missing parent records due to
        // only changed content / no hidden.
        foreach ($xmlLines as $index => $xmlLine) {
            if (str_contains($xmlLine, 'table="tt_content"')) {
                $xmlLines[$index] = '';
            }

            if (str_contains($xmlLine, 'table="tx_example_wall"')) {
                $xmlLines[$index] = '';
            }
        }

        $localizationManager->testImport(
            $configuration,
            implode("\n", $xmlLines),
            1
        );
        $this->checkDataHandlerForErrors();

        $this->checkLocalizationsOfRecords(
            $pluginElementId,
            $wallId,
            $parentContainerId,
            $childContainerId,
            $upperBrickId,
            $lowerBrickId
        );
    }

    protected function setUp(): void
    {
        $this->testExtensionsToLoad[] = 'typo3conf/container';
        parent::setUp();
    }
}
