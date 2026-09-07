<?php

declare(strict_types=1);

use Causal\Oidc\LoginProvider\OidcLoginProvider;
use Causal\Oidc\OidcConfiguration;
use Causal\Oidc\Service\AuthenticationService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'tx_oidc[code]';

$settings = GeneralUtility::makeInstance(OidcConfiguration::class);

// Service configuration
$subTypes = array_merge(
    ($settings->enableFrontendAuthentication) ? [
        'getUserFE',
        'authUserFE',
        'getGroupsFE',
    ] : [],
    ($settings->enableBackendAuthentication) ? [
        'getUserBE',
        'authUserBE',
    ] : [],
);

$authenticationClassName = AuthenticationService::class;
ExtensionManagementUtility::addService(
    'oidc',
    'auth' /* sv type */,
    $authenticationClassName /* sv key */,
    [
        'title' => 'Authentication service',
        'description' => 'Authentication service for OpenID Connect.',
        'subtype' => implode(',', $subTypes),
        'available' => true,
        'priority' => $settings->authenticationServicePriority,
        'quality' => $settings->authenticationServiceQuality,
        'os' => '',
        'exec' => '',
        'className' => $authenticationClassName,
    ]
);

// Require 3rd-party libraries, in case TYPO3 does not run in composer mode
$pharFileName = ExtensionManagementUtility::extPath('oidc') . 'Libraries/league-oauth2-client.phar';
if (is_file($pharFileName)) {
    @include 'phar://' . $pharFileName . '/vendor/autoload.php';
}

if ($settings->enableBackendAuthentication) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][OidcLoginProvider::IDENTIFIER] = [
        'provider' => OidcLoginProvider::class,
        'sorting' => 50,
        'iconIdentifier' => 'actions-key',
        'label' => 'OIDC',
    ];

    if ($settings->hideBackendPasswordLogin) {
        // Unregister core's own username/password login provider from the
        // backend login screen. This must happen here (in ext_localconf.php,
        // which loads after core's own backend registration, rather than in
        // config/system/additional.php, which runs too early during the
        // initial configuration export - before any ext_localconf.php has
        // executed, so core would just re-register it afterwards).
        // This only hides the UI option; a direct POST with credentials
        // still authenticates normally, and be_users passwords stay valid.
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][1433416747]);
    }
}
