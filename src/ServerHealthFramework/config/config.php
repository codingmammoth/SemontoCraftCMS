<?php

namespace Semonto\ServerHealth;

use Craft;


function getConfig()
{
    $plugin = Craft::$app->plugins->getPlugin('semonto-health-monitor');

    if ($plugin === null) {
        Craft::error('Could not find the plugin', __METHOD__);
        return null;
    }

    $settings = $plugin->getSettings();
    $config = $settings->generateConfig();
    return $config;
}
