<?php

declare(strict_types=1);

namespace Example\Example\Tests\Functional\DataHandler;

use Closure;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Mfa\MfaRequiredException;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Abstract test case that automatically sets up a data handler ready environment.
 *
 * @link https://github.com/b13/container initial implementation
 */
class AbstractDataHandlerTestCase extends FunctionalTestCase
{
    protected BackendUserAuthentication $backendUser;
    protected DataHandler $dataHandler;

    /**
     * @throws SessionNotCreatedException
     * @throws MfaRequiredException
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\TestingFramework\Core\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->linkSiteConfigurationIntoTestInstance();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DataHandlerBasics.csv');
        $this->backendUser = $this->setUpBackendUser(1);
        Bootstrap::initializeLanguageObject();
        $this->dataHandler = GeneralUtility::makeInstance(
            DataHandler::class
        );
    }

    protected function linkSiteConfigurationIntoTestInstance(): void
    {
        $siteConfigurationFixture = __DIR__ . '/Fixtures/SiteConfiguration.yaml';
        $testSiteConfigurationDirectory = self::getInstancePath() . '/typo3conf/sites/default';
        mkdir($testSiteConfigurationDirectory, recursive: true);

        $symlinkTarget = realpath($siteConfigurationFixture);
        $symlinkLink = $testSiteConfigurationDirectory . '/config.yaml';

        if (file_exists($symlinkLink)) {
            unlink($symlinkLink);
        }

        $symbolSuccessfullyCreated = symlink(
            $symlinkTarget,
            $symlinkLink
        );

        if (!$symbolSuccessfullyCreated) {
            throw new RuntimeException(
                "Could not create symlink to $symlinkTarget at $symlinkLink.",
                1684714178
            );
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->backendUser, $this->dataHandler);
    }

    /**
     * Create a new record of the specified table using the TYPO3 Core Data Handler.
     *
     * @link https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Typo3CoreEngine/Database/Index.html TYPO3 Explained | Data Handler basics
     *
     * @param string $table
     * @param array $newRow
     * @return int the actual uid of the created record.
     */
    protected function insertRowUsingDataHandler(string $table, array $newRow): int
    {
        [$virtualId, $resolveVirtualId] = $this->createPlaceholderRecordId();
        $dataMap = [$table => [$virtualId => $newRow]];
        $this->runDataHandler($dataMap, []);
        return $resolveVirtualId();
    }

    /**
     * @param string $prefix
     * @return array{0: string, 1: Closure(): int}
     */
    protected function createPlaceholderRecordId(string $prefix = 'NEW'): array
    {
        $virtualId = StringUtility::getUniqueId($prefix);

        return [$virtualId, function () use ($virtualId) {
            $actualDatabaseId = $this->dataHandler->substNEWwithIDs[$virtualId];
            self::assertIsInt($actualDatabaseId);
            return $actualDatabaseId;
        }];
    }

    protected function runDataHandler(array $data, array $command): void
    {
        $this->dataHandler->start($data, $command);

        if (count($data) > 0) {
            try {
                $this->dataHandler->process_datamap();
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'Error during data map processing inside typo3 core data handler. '
                    . 'The data handler should handle errors more softly by adding them to the error list.',
                    1684714502,
                    $exception
                );
            }
        }

        if (count($command) > 0) {
            try {
                $this->dataHandler->process_cmdmap();
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'Error during command map processing inside typo3 core data handler. '
                    . 'The data handler should handle errors more softly by adding them to the error list.',
                    1684714503,
                    $exception
                );
            }
        }

        $this->checkDataHandlerForErrors();
    }

    /**
     *Check the data handler for any errors. Throws if at least one error is found.
     *
     * TYPO3 Data Handler Documentation:
     * {@link https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Typo3CoreEngine/UsingDataHandler/Index.html#error-handling}
     *
     * @return void
     */
    protected function checkDataHandlerForErrors(): void
    {
        $errors = $this->dataHandler->errorLog;

        if (count($errors) === 0) {
            return;
        }

        $concatenatedErrors = '';

        foreach ($errors as $error) {
            if (!is_string($error)) {
                throw new InvalidArgumentException(
                    'Given error was not a string but of type ' . gettype($error),
                    1684714504
                );
            }
            $concatenatedErrors .= "\n\t$error" . PHP_EOL;
        }

        throw new RuntimeException(
            'Found errors in data handler: ' . $concatenatedErrors,
            1684714505
        );
    }
}
