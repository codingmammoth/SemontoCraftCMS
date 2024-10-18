<?php

namespace codingmammoth\craftsemontowebsitemonitor\controllers;

use Craft;
use craft\web\Controller;

use function codingmammoth\craftsemontowebsitemonitor\functions\{
    check_available_features
};

require_once __DIR__ . './../functions/functions.php';

class PluginController extends Controller
{
    private function getSemontoLogoUrl()
    {
        return \Craft::$app->assetManager->getPublishedUrl(
            '@codingmammoth/craftsemontowebsitemonitor/resources',
            true,
            'semonto-logo.png'
        );
    }

    public function actionWebsiteMonitoring()
    {
        $semontoLogoUrl = $this->getSemontoLogoUrl();
        $imageUrl = \Craft::$app->assetManager->getPublishedUrl(
            '@codingmammoth/craftsemontowebsitemonitor/resources',
            true,
            'semonto-website-dashboard.png'
        );
        
        return $this->renderTemplate(
            'semonto-website-monitor/semonto-website-monitor-website-monitoring',
            [
                'semontoLogoUrl' => $semontoLogoUrl,
                'semontoDashboard' => $imageUrl
            ]
        );
    }

    public function actionServerMonitoring()
    {
        $plugin = Craft::$app->plugins->getPlugin('semonto-website-monitor');

        if ($plugin === null) {
            Craft::error('Could not find the plugin', __METHOD__);
            return null;
        }

        $routeParams = Craft::$app->getUrlManager()->getRouteParams();
        $settings = $plugin->getSettings();
        $features = check_available_features();

        // Data without any validation.
        $endpoint = Craft::$app->getSites()->getCurrentSite()->baseUrl . 'health';
        $semontoLogoUrl = $this->getSemontoLogoUrl();
        $imageUrl = \Craft::$app->assetManager->getPublishedUrl(
            '@codingmammoth/craftsemontowebsitemonitor/resources',
            true,
            'semonto-website-monitor-server-monitoring.png'
        );

        // Data with validation or that could have been changed by the user.
        if (isset($routeParams['settings'])) {
            // There are settings that didn't validate, use these.
            $tests = $routeParams['settings']['tests']['value'];
            $secretKey = $routeParams['settings']['secretKey']['value'];
            $cachingEnabled = $routeParams['settings']['cachingEnabled']['value'];
            $cachingLifespan = $routeParams['settings']['cachingLifespan']['value'];
            $cachingLifespanError = $routeParams['settings']['cachingLifespan']['error'];
        } else {
            // Use saved settings.
            $tests = $settings->getTestConfig();
            $secretKey = $settings->secretKey;
            $cachingEnabled = $settings->caching_enabled;
            $cachingLifespan = $settings->caching_lifespan;
            $cachingLifespanError = false;
        }

        return $this->renderTemplate('semonto-website-monitor/semonto-website-monitor-server-monitoring', [
            'features' => $features,
            'tests' => $tests,
            'endpoint' => $endpoint,
            'secretKey' => $secretKey,
            'semontoLogoUrl' => $semontoLogoUrl,
            'semontoDashboard' => $imageUrl,
            'cachingEnabled' => $cachingEnabled,
            'cachingLifespan' => $cachingLifespan,
            'cachingLifespanError' => $cachingLifespanError
        ]);
    }

    private function validateThresholds ($warningThreshold, $errorThreshold, $validations = [])
    {
        $errors = false;
        $message = false;

        if (in_array('required', $validations) && ($warningThreshold === null || $warningThreshold === '' || $errorThreshold === null || $errorThreshold === '' )) {
            $errors = true;
            $message = 'The thresholds are required';
        } else if (in_array('numbers', $validations) && (!is_numeric($warningThreshold) || !is_numeric($errorThreshold))) {
            $errors = true;
            $message = 'The thresholds should be numbers';
        } else if (in_array('notNegative', $validations) && ((int) $warningThreshold < 0 || (int) $errorThreshold < 0)) {
            $errors = true;
            $message = 'The thresholds should not be negative';
        } else if (in_array('max100', $validations) && ((int) $warningThreshold > 100 || (int) $errorThreshold > 100)) {
            $errors = true;
            $message = 'The thresholds should be less than 100';
        } else if (in_array('warningLessThenError', $validations) && ((int) $warningThreshold >= (int) $errorThreshold)) {
            $errors = true;
            $message = 'The warning threshold should be lower than the error threshold';
        }

        return [ $errors, $message ];
    }

    public function actionSaveServerSettings()
    {
        $this->requirePostRequest();

        $plugin = Craft::$app->plugins->getPlugin('semonto-website-monitor');
    
        if ($plugin === null) {
            Craft::error('Could not find the plugin', __METHOD__);
            return null;
        }
    
        $settings = $plugin->getSettings();
        $currrentTests = $settings->getTestConfig(); // The current settings.

        // Get the POST body data we need to validate.
        $newSecretKeySettings = Craft::$app->getRequest()->getBodyParam('secretKey');
        $newTestSettings = Craft::$app->getRequest()->getBodyParam('tests');
        $newCacheLifeSpanSettings = Craft::$app->getRequest()->getBodyParam('cachingLifespan');
        $newCacheEnabledSpanSettings = Craft::$app->getRequest()->getBodyParam('cachingEnabled');

        $errors = false;

        /**
         * Create data to be passed as a router parameter.
         *
         * This should be the data that could have been changed or
         * can contain errors.
         */
        $newSettings = [
            'tests' => [
                'value' => $currrentTests, // Save the current settings.
                'error' => false
            ],
            'secretKey' => [
                'value' => $newSecretKeySettings,
                'error' => false
            ],
            'cachingLifespan' => [
                'value' => $newCacheLifeSpanSettings,
                'error' => false
            ],
            'cachingEnabled' => [
                'value' => $newCacheEnabledSpanSettings,
                'error' => false
            ]
        ];

        /**
         * Loop over the tests in the current test config.
         * 
         * If there are any new settings, update these.
         */
        foreach ($currrentTests as $test_id => $test) {

            // The new settings for this tests.
            $newTestSetting = false;
            if (isset($newTestSettings[$test_id])) {
                $newTestSetting = $newTestSettings[$test_id];
            }

            if ($newTestSetting === false) {
                // There isn't any data, mark this test as disbled.
                $newSettings['tests']['value'][$test_id]['enabled'] = false;
            }

            if ($newTestSetting !== false && in_array($test_id, ['ServerLoadNow', 'ServerLoadAverage5min', 'ServerLoadAverage15min'])) {
                $newSettings['tests']['value'][$test_id] = array_merge($test, $newTestSetting);
                $newSettings['tests']['value'][$test_id]['enabled'] = $newTestSetting['enabled'] ?? '0';

                if ($newSettings['tests']['value'][$test_id]['enabled']) {
                    [ $error, $message ] = $this->validateThresholds(
                        $newSettings['tests']['value'][$test_id]['config']['warning_threshold'],
                        $newSettings['tests']['value'][$test_id]['config']['error_threshold'],
                        ['required', 'numbers', 'notNegative', 'warningLessThenError']
                    );

                    if ($error) {
                        $errors = true;
                        $newSettings['tests']['value'][$test_id]['error'] = $message;
                    }
                }
            } else if ($newTestSetting !== false && $test_id === 'MemoryUsage') {
                $newSettings['tests']['value'][$test_id] = array_merge($test, $test, $newTestSetting);
                $newSettings['tests']['value'][$test_id]['enabled'] = $newTestSetting['enabled'] ?? '0';

                if ($newSettings['tests']['value'][$test_id]['enabled']) {
                    [ $error, $message ] = $this->validateThresholds(
                        $newSettings['tests']['value'][$test_id]['config']['warning_percentage_threshold'],
                        $newSettings['tests']['value'][$test_id]['config']['error_percentage_threshold'],
                        ['required', 'numbers', 'notNegative', 'max100', 'warningLessThenError']
                    );

                    if ($error) {
                        $errors = true;
                        $newSettings['tests']['value'][$test_id]['error'] = $message;
                    }
                }
            } else if ($newTestSetting !== false && ($test_id === 'DiskSpace' || $test_id === 'DiskSpaceInode')) {
                $newSettings['tests']['value'][$test_id]['enabled'] = $newTestSetting['enabled'] ?? '0';

                // Make sure to use all available disk.
                foreach ($currrentTests[$test_id]['config']['disks'] as $disk_name => $current_disk_config) {
                    // Find new disk config.
                    $newDiskConfig = $newTestSetting['config']['disks'][$disk_name];

                    // Mark the disk as disabled when this field is not set.
                    $current_disk_config['enabled'] = $newDiskConfig['enabled'] ?? '0';
                    // Set warning threshold, use new or keep existing.
                    $current_disk_config['warning_percentage_threshold'] = $newDiskConfig['warning_percentage_threshold'] ?? $current_disk_config['warning_percentage_threshold'];
                    // Set error threshold, use new or keep existing.
                    $current_disk_config['error_percentage_threshold'] = $newDiskConfig['error_percentage_threshold'] ?? $current_disk_config['error_percentage_threshold'];

                    // Validate and set error
                    if ($current_disk_config['enabled']) {
                        [ $error, $message ] = $this->validateThresholds(
                            $current_disk_config['warning_percentage_threshold'],
                            $current_disk_config['error_percentage_threshold'],
                            ['required', 'numbers', 'notNegative', 'max100', 'warningLessThenError']
                        );

                        if ($error) {
                            $errors = true;
                            $newSettings['tests']['value'][$test_id]['error'] = $message;
                            $current_disk_config['error'] = $message;
                        }
                    }

                    // Update the disk config in newSettings.
                    $newSettings['tests']['value'][$test_id]['config']['disks'][$disk_name] = $current_disk_config;
                }
            } else if ($newTestSetting !== false && $test_id == 'CraftCMSCheckDBConnection') {
                $newSettings['tests']['value'][$test_id] = array_merge($test, $newTestSetting);
                $newSettings['tests']['value'][$test_id]['enabled'] = $newTestSetting['enabled'] ?? '0';
            } else if ($newTestSetting !== false && $test_id === 'CraftCMSMaxDBConnection') {
                $newSettings['tests']['value'][$test_id] = array_merge($test, $newTestSetting);
                $newSettings['tests']['value'][$test_id]['enabled'] = $newTestSetting['enabled'] ?? '0';

                [ $error, $message ] = $this->validateThresholds(
                    $newSettings['tests']['value'][$test_id]['config']['warning_percentage_threshold'],
                    $newSettings['tests']['value'][$test_id]['config']['error_percentage_threshold'],
                    ['required', 'numbers', 'notNegative', 'max100', 'warningLessThenError']
                );

                if ($error) {
                    $errors = true;
                    $newSettings['tests']['value'][$test_id]['error'] = $message;
                }
            } else if ($newTestSetting !== false && $test_id === 'CraftCMSUpdates') {
                $newSettings['tests']['value'][$test_id] = array_merge($test, $newTestSetting);
                $newSettings['tests']['value'][$test_id]['enabled'] = $newTestSetting['enabled'] ?? '0';
            }
        }

        if ((int) $newCacheLifeSpanSettings < 0) {
            $errors = true;
            $newSettings['cachingLifespan']['error'] = 'The life span should not be negative';
        }

        if (!$errors) {
            $settings->setSecretKey($newSecretKeySettings);
            $settings->setCachingSettings($newCacheLifeSpanSettings, $newCacheEnabledSpanSettings === "1");

            foreach ($newTestSettings as $test_id => $test) {
                $test_enabled = isset($test['enabled']) && $test['enabled'] == "1";
                $test_config = $test['config'] ?? [];
                $settings->setTest($test_id, $test_enabled, $test_config);
            }

            if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
                Craft::error('Could not save plugin settings', __METHOD__);
                return null;
            }

            Craft::$app->getSession()->setSuccess('Successfully saved your settings.');
            return $this->redirectToPostedUrl();
        } else {

            /**
             * See this post:
             * https://craftcms.stackexchange.com/questions/38511/how-to-access-setrouteparams-model-in-form-validation
             */
            Craft::$app->getUrlManager()->setRouteParams([ 'settings' => $newSettings ]);
            Craft::$app->getSession()->setError('Could not save your settings. Please check the errors.');
            return null;
        }
    }

    public function actionResetToDefaults()
    {
        $this->requirePostRequest();

        $plugin = Craft::$app->plugins->getPlugin('semonto-website-monitor');

        if ($plugin === null) {
            Craft::error('Could not find the plugin', __METHOD__);
            return null;
        }

        $settings = $plugin->getSettings();
        $settings->setDefault();

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            Craft::error('Could not save plugin settings', __METHOD__);
            return null;
        }

        return $this->redirectToPostedUrl();
    }
}
