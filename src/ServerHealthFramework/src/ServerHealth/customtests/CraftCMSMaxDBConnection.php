<?php

namespace Semonto\ServerHealth;

use Craft;

use Semonto\ServerHealth\{
    ServerStates,
    ServerHealthResult,
    ServerHealthTest
};

require_once __DIR__ . "../../ServerHealthTest.php";

class CraftCMSMaxDBConnection extends ServerHealthTest
{
    protected $name = 'Max DB Connections Check';

    function checkDbConnection()
    {
        $db = Craft::$app->getDb();
        $driverName = $db->getDriverName();

        if ($driverName !== 'mysql') {
            return [ false, "Unsupported type of database: $driverName" ];
        }

        if ($db->getIsActive()) {
            return [ true, '' ];
        } else {
            try {
                $db->open();
                return [ true, '' ];
            } catch (\yii\db\Exception $e) {
                return [ false, 'Failed to connect to the database: ' . $e->getMessage() ];
            }
        }
    }

    function getCurrentNumberOfDBConnections()
    {
        $db = Craft::$app->getDb();
        $driverName = $db->getDriverName();
        $currentNumberOfDBConnections = false;

        if ($driverName === 'mysql') {
            $command = $db->createCommand("SHOW STATUS LIKE 'Threads_connected';");
            $result = $command->queryOne();

            if ($result !== false) { $currentNumberOfDBConnections = $result['Value']; }
        } elseif ($driverName === 'pgsql') {
            $command = $db->createCommand("SELECT COUNT(*) FROM pg_stat_activity;");
            $currentNumberOfDBConnections = $command->queryScalar();
        }

        return $currentNumberOfDBConnections;
    }

    function getMaxNumberOfConnections()
    {
        $db = Craft::$app->getDb();
        $driverName = $db->getDriverName();
        $maxConnections = false;

        if ($driverName === 'mysql') {
            $command = $db->createCommand("SHOW VARIABLES LIKE 'max_connections';");
            $result = $command->queryOne();
        
            if ($result !== false) {
                $maxConnections = (int) $result['Value'];
            }
        } else if ($driverName === 'pgsql') {
            $command = $db->createCommand("SHOW max_connections;");
            $result = $command->queryScalar();

            if($result !== false){
                $maxConnections = (int) $result;
            }
        }

        return $maxConnections;
    }

    protected function performTests()
    {
        $warning_percentage_threshold = isset($this->config['warning_percentage_threshold']) ? $this->config['warning_percentage_threshold'] :70;
        $error_percentage_threshold = isset($this->config['error_percentage_threshold']) ? $this->config['error_percentage_threshold'] : 90;

        if ($warning_percentage_threshold>=$error_percentage_threshold) {
            return new ServerHealthResult($this->name, ServerStates::error, 'Error percentage threshold should be higher than warning percentage threshold.');
        }

        [ $db_connection, $db_error ] = $this->checkDbConnection();
        
        if ($db_connection) {
            $maxNumberOfConnections = $this->getMaxNumberOfConnections();
            $currentNumberOfDBConnections = $this->getCurrentNumberOfDBConnections();
            $description = '';
            $value = null;
            $status = ServerStates::ok;

            if ($maxNumberOfConnections === false || $currentNumberOfDBConnections === false) {
                $status = ServerStates::error;
                $description = "Failed to get the number of connections.";
            } else {
                $percentage = ($currentNumberOfDBConnections / $maxNumberOfConnections) * 100;
                $value = number_format((float)$percentage/100,4,".","");
                $description = 'Number of connections: ' . $currentNumberOfDBConnections . ' (' . $percentage . '%)';

                if ($percentage >= $error_percentage_threshold) {
                    $status = ServerStates::error;
                } else if ($percentage >= $warning_percentage_threshold){
                    $status = ServerStates::warning;
                }
            }

            return new ServerHealthResult($this->name, $status, $description, $value);
        } else {
            return new ServerHealthResult($this->name, ServerStates::error, $db_error);
        }
    }
}
