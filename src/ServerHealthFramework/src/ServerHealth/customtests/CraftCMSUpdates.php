<?php

namespace Semonto\ServerHealth;

use Semonto\ServerHealth\{
    ServerStates,
    ServerHealthResult,
    ServerHealthTest
};

use Craft;

require_once __DIR__ . "../../ServerHealthTest.php";

class CraftCMSUpdates extends ServerHealthTest
{
    protected $name = 'CraftCMS Updates';

    protected function performTests()
    {
        $updates = Craft::$app->getUpdates();
        $isCriticalUpdateAvailable = $updates->getIsCriticalUpdateAvailable();
        $totalAvailableUpdates = $updates->getTotalAvailableUpdates();

        $description = "";
        if ($isCriticalUpdateAvailable) {
            $status = ServerStates::error;
            $description .= "Critical update available. ";
        } else if ($this->config['alert_non_critical_updates'] && $totalAvailableUpdates > 0) {
            $status = ServerStates::warning;
        } else {
            $status = ServerStates::ok;
        }

        if ($totalAvailableUpdates > 0) {
            $updateOrUpdates = $totalAvailableUpdates > 1 ? 'updates': 'update';
            if ($isCriticalUpdateAvailable) { $description .= "In total "; }
            $description .= "$totalAvailableUpdates $updateOrUpdates available.";
        } else {
            $description .= 'No updates available.';
        }

        return new ServerHealthResult($this->name, $status, $description, $totalAvailableUpdates);
    }
}
