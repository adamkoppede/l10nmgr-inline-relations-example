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
 * A single content element with a inline relation to another database record.
 */
class LocalizeContentElementWithSingleInlineRecordTest extends AbstractLocalizationManagerTestCase
{
    /**
     * @throws DBALException
     * @throws Exception
     */
    public function testLocalizeContentElementUsingDataHandler(): void
    {
        [$contentElementId, $inlineRecordId] = $this->prepareDefaultLanguageElements();

        $this->runDataHandler([], ['tt_content' => [$contentElementId => ['localize' => 1]]]);

        $this->checkLocalizationsOfRecord($contentElementId, $inlineRecordId);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function prepareDefaultLanguageElements(): array
    {
        [$virtualWallId, $retrieveWallId] = $this->createPlaceholderRecordId();
        [$virtualContentElementId, $retrieveContentElementId] = $this->createPlaceholderRecordId();

        $this->runDataHandler([
            'tx_example_wall' => [
                $virtualWallId => [
                    'pid' => 1,
                    'title' => 'Inner Wall Title',
                    'tt_content' => $virtualContentElementId
                ]
            ],
            'tt_content' => [
                $virtualContentElementId => [
                    'pid' => 1,
                    'CType' => 'example_wallCollection',
                    'tx_example_relation_wall' => $virtualContentElementId,
                    'header' => 'Wall Collection Content Element'
                ]
            ]
        ], []);

        return [$retrieveContentElementId(), $retrieveWallId()];
    }

    /**
     * Each of the prepared records should have exactly one localization.
     * The parent pointer of the child record localization should point at the localization of the parent record.
     *
     * @param int $parentContentElementId
     * @param int $childInlineId
     * @return void
     * @throws DBALException
     * @throws Exception
     */
    protected function checkLocalizationsOfRecord(int $parentContentElementId, int $childInlineId): void
    {
        $localizationsOfParent = BackendUtility::getRecordLocalization('tt_content', $parentContentElementId, 1);
        self::assertIsArray($localizationsOfParent);
        self::assertCount(1, $localizationsOfParent);
        self::assertIsArray($localizationsOfParent[0]);
        self::assertNotEmpty($localizationsOfParent[0]);
        self::assertIsInt($localizationsOfParent[0]['uid']);
        self::assertEquals(1, $localizationsOfParent[0]['sys_language_uid']);
        self::assertEquals($parentContentElementId, $localizationsOfParent[0]['l18n_parent']);

        $localizationsOfChild = BackendUtility::getRecordLocalization('tx_example_wall', $childInlineId, 1);
        self::assertIsArray($localizationsOfChild);
        self::assertCount(1, $localizationsOfChild);
        self::assertIsArray($localizationsOfChild[0]);
        self::assertNotEmpty($localizationsOfChild[0]);
        self::assertEquals(1, $localizationsOfChild[0]['sys_language_uid']);
        self::assertEquals($parentContentElementId, $localizationsOfChild[0]['l10n_parent']);

        self::assertEquals($localizationsOfParent[0]['uid'], $localizationsOfChild[0]['tt_content']);
    }

    /**
     * @throws Exception
     * @throws DBALException
     * @throws SiteNotFoundException
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function testLocalizeContentElementUsingFullXml(): void
    {
        [$contentElementId, $inlineRecordId] = $this->prepareDefaultLanguageElements();
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

        $this->checkLocalizationsOfRecord($contentElementId, $inlineRecordId);
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
        // TODO: this test failed without the l10nmgr patch, but it shouldn't. So why did it?
        //          Is it a misunderstanding in the configuration or a bug?

        [$contentElementId, $inlineRecordId] = $this->prepareDefaultLanguageElements();
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

        $this->checkLocalizationsOfRecord($contentElementId, $inlineRecordId);
    }
}
