<?php

namespace codingmammoth\craftsemontohealthmonitor;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class PluginBundle extends AssetBundle
{
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@codingmammoth/craftsemontohealthmonitor/resources';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'semonto-health-monitor.js',
        ];

        $this->css = [
            'semonto-health-monitor.css',
        ];

        parent::init();
    }
}
