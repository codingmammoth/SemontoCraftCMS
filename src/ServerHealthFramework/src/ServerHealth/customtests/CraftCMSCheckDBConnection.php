<?php

namespace Semonto\ServerHealth;

use Craft;

use Semonto\ServerHealth\{
    ServerStates,
    ServerHealthResult,
    ServerHealthTest
};

require_once __DIR__ . "../../ServerHealthTest.php";

class CraftCMSCheckDBConnection extends ServerHealthTest
{
    protected $name = 'Check DB Connection';

    function checkDbConnection()
    {
        $db = Craft::$app->getDb();
    
        if ($db->getIsActive()) {
            return true;
        } else {
            try {
                $db->open();
                return true;
            } catch (\yii\db\Exception $e) {
                Craft::error('Database connection error: ' . $e->getMessage(), __METHOD__);
                return false;
            }
        }
    }

    protected function performTests()
    {
        if ($this->checkDbConnection()) {
            return new ServerHealthResult($this->name, ServerStates::ok, 'Database connection is successful');
        } else {
            return new ServerHealthResult($this->name, ServerStates::error, 'Database connection is not working.');
        }
    }
}
?>