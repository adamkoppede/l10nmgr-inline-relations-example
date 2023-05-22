<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

call_user_func(static function (): void {
    ExtensionManagementUtility::allowTableOnStandardPages('tx_example_wall');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_example_brick');
});
