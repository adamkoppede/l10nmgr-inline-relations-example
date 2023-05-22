<?php

declare(strict_types=1);

namespace Example\Example\Tests\Functional\DataHandler;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;

class PlainDataHandlerTest extends AbstractDataHandlerTestCase
{
    /**
     * Just to check if the function tests are properly set up.
     *
     * @throws DBALException
     * @throws Exception
     */
    public function testCanLocalizeRootPage(): void
    {
        $this->runDataHandler([], ['pages' => [1 => ['localize' => 1]]]);

        $localizationsOfPage = BackendUtility::getRecordLocalization('pages', 1, 1);
        self::assertIsArray($localizationsOfPage);
        self::assertCount(1, $localizationsOfPage);
        self::assertNotEmpty($localizationsOfPage[0]);
        self::assertIsArray($localizationsOfPage[0]);
        self::assertEquals(1, $localizationsOfPage[0]['sys_language_uid']);
        self::assertEquals(1, $localizationsOfPage[0]['l10n_parent']);
    }
}
