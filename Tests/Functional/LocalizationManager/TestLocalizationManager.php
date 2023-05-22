<?php

declare(strict_types=1);

namespace Example\Example\Tests\Functional\LocalizationManager;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use Localizationteam\L10nmgr\Controller\LocalizationManager;
use Localizationteam\L10nmgr\Model\CatXmlImportManager;
use Localizationteam\L10nmgr\Model\Dto\EmConfiguration;
use Localizationteam\L10nmgr\Model\L10nBaseService;
use Localizationteam\L10nmgr\Model\L10nConfiguration;
use Localizationteam\L10nmgr\Model\TranslationDataFactory;
use Localizationteam\L10nmgr\View\CatXmlView;
use RuntimeException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TestLocalizationManager extends LocalizationManager
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
        $this->emConfiguration = GeneralUtility::makeInstance(EmConfiguration::class);
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
//        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->getLanguageService()
            ->includeLLFile('EXT:l10nmgr/Resources/Private/Language/Modules/LocalizationManager/locallang.xlf');
        $this->MCONF = [
            'name' => $this->moduleName,
        ];
    }

    /**
     * Test version for {@link LocalizationManager::catXMLExportImportAction},
     * since we cannot get around is_uploaded_file.
     *
     * @param L10nConfiguration $l10ncfgObj
     * @param string $xmlContent
     * @param int $targetLanguage
     * @return void
     * @throws DBALException
     * @throws SiteNotFoundException
     */
    public function testImport(
        L10nConfiguration $l10ncfgObj,
        string            $xmlContent,
        int               $targetLanguage
    ): void {
        $this->sysLanguage = $targetLanguage;
        $service = GeneralUtility::makeInstance(L10nBaseService::class);
        $factory = GeneralUtility::makeInstance(TranslationDataFactory::class);
        $importManager = GeneralUtility::makeInstance(
            CatXmlImportManager::class,
            '',
            $this->sysLanguage,
            $xmlContent
        );

        if ($importManager->parseAndCheckXMLString() === false) {
            throw new RuntimeException('Invalid Localization-XML file provided. ' . PHP_EOL . $importManager->getErrorMessages());
        }

        if ($importManager->headerData['t3_sourceLang'] === $importManager->headerData['t3_targetLang']) {
            $this->previewLanguage = $this->sysLanguage;
        }
        $translationData = $factory->getTranslationDataFromCATXMLNodes($importManager->getXMLNodes());
        $translationData->setLanguage($this->sysLanguage);
        $translationData->setPreviewLanguage($this->previewLanguage);
        unset($importManager);
        $service->saveTranslation($l10ncfgObj, $translationData);
    }

    /**
     * Test version for {@link LocalizationManager::catXMLExportImportAction},
     * to get directly the xml's content.
     *
     * @param L10nConfiguration $l10nConfiguration
     * @param int $targetLanguage
     * @return string
     * @throws SiteNotFoundException
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function testExport(L10nConfiguration $l10nConfiguration, int $targetLanguage): string
    {
        $this->sysLanguage = $targetLanguage;
        $view = GeneralUtility::makeInstance(CatXmlView::class, $l10nConfiguration, $targetLanguage);
        $exportedFileName = Environment::getPublicPath() . '/' . $view->render();
        $view->saveExportInformation();
        $fileContent = file_get_contents($exportedFileName);

        if (!$fileContent) {
            throw new RuntimeException('Could not read content of exported file ' . $exportedFileName);
        }

        /**
         * @var string[] $messages
         */
        $messages = $view->getMessages();
        if (count($messages) !== 0) {
            $strings = '';
            foreach ($messages as $message) {
                $strings .= "\t$message" . PHP_EOL;
            }
            throw new RuntimeException(
                'Error during export: ' . PHP_EOL . $strings
            );
        }

        return $fileContent;
    }
}
