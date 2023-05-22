<?php

defined('TYPO3') || die();

call_user_func(static function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['l10nmgr']['inlineTablesConfig'] ??= [];
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['l10nmgr']['inlineTablesConfig']['tx_example_wall'] = [
        'parentField' => 'tt_content',
        'childrenField' => 'tx_example_relation_wall'
    ];
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['l10nmgr']['inlineTablesConfig']['tx_example_brick'] = [
        'parentField' => 'tt_content',
        'childrenField' => 'tx_example_relation_brick'
    ];
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['l10nmgr']['additionalInlineTablesConfig']['tx_example_brick'] ??= [];
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['l10nmgr']['additionalInlineTablesConfig']['tx_example_brick'][] = [
        'parentTable' => 'tx_example_wall',
        'parentField' => 'tx_example_wall',
        'childrenField' => 'relation_brick'
    ];
});
