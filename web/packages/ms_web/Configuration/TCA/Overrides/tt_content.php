<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:ms_web/Configuration/FlexForms/Pricing.xml',
    'mspricing_pricing',
);

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    'pi_flexform',
    'mspricing_pricing',
    'after:pages',
);
