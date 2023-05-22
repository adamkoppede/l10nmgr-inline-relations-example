<?php

declare(strict_types=1);

namespace Example\Example\Tests\Functional\LocalizationManager;

use Example\Example\Tests\Functional\DataHandler\AbstractDataHandlerTestCase;

class AbstractLocalizationManagerTestCase extends AbstractDataHandlerTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/l10nmgr',
        'typo3conf/ext/example'
    ];

    protected function createTestLocalizationManagerInstance(): TestLocalizationManager
    {
        return new TestLocalizationManager();
    }
}
