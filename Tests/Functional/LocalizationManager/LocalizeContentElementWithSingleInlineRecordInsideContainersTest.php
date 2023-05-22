<?php

declare(strict_types=1);

namespace Example\Example\Tests\Functional\LocalizationManager;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use Localizationteam\L10nmgr\Model\L10nConfiguration;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LocalizeContentElementWithSingleInlineRecordInsideContainersTest extends AbstractLocalizationManagerTestCase
{
    protected function setUp(): void
    {
        $this->testExtensionsToLoad[] = 'typo3conf/container';
        parent::setUp();
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    public function testLocalizeContentElementUsingDataHandler(): void
    {
        [$contentElementId, $inlineRecordId, $parentContainerId, $childContainerId] = $this->prepareDefaultLanguageElements();

        $this->runDataHandler([], [
            'pages' => [1 => ['localize' => 1]],
            'tt_content' => [$parentContainerId => ['localize' => 1]],
        ]);

        $this->checkLocalizationsOfRecords($contentElementId, $inlineRecordId, $parentContainerId, $childContainerId);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    protected function prepareDefaultLanguageElements(): array
    {
        [$virtualWallId, $retrieveWallId] = $this->createPlaceholderRecordId();
        [$virtualPluginElementId, $retrievePluginElementId] = $this->createPlaceholderRecordId();
        [$virtualContainerParentId, $retrieveContainerParentId] = $this->createPlaceholderRecordId();
        [$virtualContainerChildId, $retrieveContainerChildId] = $this->createPlaceholderRecordId();

        $this->runDataHandler([
            'tx_example_wall' => [
                $virtualWallId => [
                    'pid' => 1,
                    'title' => 'Inner Wall Title',
                    'tt_content' => $virtualPluginElementId
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
                    'tx_example_relation_wall' => $virtualPluginElementId,
                    'header' => 'Wall Collection Content Element',
                    'tx_container_parent' => $virtualContainerChildId
                ]
            ]
        ], []);

        return [$retrievePluginElementId(), $retrieveWallId(), $retrieveContainerParentId(), $retrieveContainerChildId()];
    }

    /**
     * Each of the prepared records should have exactly one localization.
     * The parent pointer of the child record localization should point at the localization of the parent record.
     *
     * @param int $parentContentElementId
     * @param int $childInlineId
     * @param int $containerContentElementParentId
     * @param int $containerContentElementChildId
     * @return void
     * @throws DBALException
     * @throws Exception
     */
    protected function checkLocalizationsOfRecords(int $parentContentElementId, int $childInlineId, int $containerContentElementParentId, int $containerContentElementChildId): void
    {
        $this->fetchLocalizationOfRecord('tt_content', $containerContentElementParentId);
        $this->fetchLocalizationOfRecord('tt_content', $containerContentElementChildId);
        $parentPluginLocalization = $this->fetchLocalizationOfRecord('tt_content', $parentContentElementId);
        $childInlineLocalization = $this->fetchLocalizationOfRecord('tx_example_wall', $childInlineId);

        // the parent field pointer should point at the localization
        self::assertEquals($parentPluginLocalization['uid'], $childInlineLocalization['tt_content']);
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
     * @throws Exception
     * @throws DBALException
     * @throws SiteNotFoundException
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function testLocalizeContentElementUsingFullXml(): void
    {
        [$contentElementId, $inlineRecordId, $parentContainerId, $childContainerId] = $this->prepareDefaultLanguageElements();
        $configurationId = $this->insertRowUsingDataHandler('tx_l10nmgr_cfg', [
            'pid' => 1,
            'depth' => 0,
            'tablelist' => 'pages,tt_content,tx_example_wall',
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

        $this->checkLocalizationsOfRecords($contentElementId, $inlineRecordId, $parentContainerId, $childContainerId);
    }

    /**
     * @throws \TYPO3\CMS\Core\Exception
     * @throws Exception
     * @throws DBALException
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function testLocalizeContentElementUsingOnlyInlineChildInXml(): void
    {
        [$contentElementId, $inlineRecordId, $parentContainerId, $childContainerId] = $this->prepareDefaultLanguageElements();
        $configurationId = $this->insertRowUsingDataHandler('tx_l10nmgr_cfg', [
            'pid' => 1,
            'depth' => 0,
            'tablelist' => 'pages,tt_content,tx_example_wall',
        ]);

        $configuration = GeneralUtility::makeInstance(L10nConfiguration::class);
        $configuration->load($configurationId);
        $localizationManager = $this->createTestLocalizationManagerInstance();

        $xmlLines = explode("\n", $localizationManager->testExport(
            $configuration,
            1
        ));

        // unset all tt_content lines, to simulate only changed content / no hidden situations.
        foreach ($xmlLines as $index => $xmlLine) {
            if (str_contains($xmlLine, 'table="tt_content"')) {
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
            $childContainerId
        );
    }
}
