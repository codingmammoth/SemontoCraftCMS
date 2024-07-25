<?php

namespace Semonto\ServerHealth;

use Semonto\ServerHealth\{
    ServerStates,
    ServerHealthResult,
    ServerHealthTest
};

class MySQLFetchOperation extends ServerHealthTest
{
    protected $name = 'MySQL Fetch Operation';

    public function performTests()
    {
        $starttime = getStartTime();

        try {
            if ($this->db === false) {
                return new ServerHealthResult(
                    $this->name,
                    ServerStates::error,
                    "Failed to connect or login on the db server.",
                    getRunningTime($starttime)
                );
            }

            if (!isset($this->config['database']) || $this->config['database'] === '') {
                return new ServerHealthResult(
                    $this->name,
                    ServerStates::warning,
                    "No database to select.",
                    getRunningTime($starttime)
                );
            } else {
                $sql_result = mysqli_select_db($this->db, $this->config['database']);
                if (!$sql_result) {
                    return new ServerHealthResult(
                        $this->name,
                        ServerStates::error,
                        "Failed to select database " . $this->config['database'],
                        getRunningTime($starttime)
                    );
                }
            }

            if (isset($this->config['database_table'])) {
                $sql  = "SELECT * FROM " . $this->config['database_table'] . " LIMIT 50";
                $sql_result = mysqli_query($this->db, $sql);
                if (!$sql_result) {
                    return new ServerHealthResult(
                        $this->name,
                        ServerStates::error,
                        "Failed to select table " . $this->config['database_table'],
                        getRunningTime($starttime)
                    );
                } else {
                    $sql_result->close();
                }
            }

            $totaltime = getRunningTime($starttime);
            return new ServerHealthResult(
                $this->name,
                ServerStates::ok,
                "Total time: $totaltime",
                $totaltime
            );
        } catch (\Throwable $th) {
            $error = $th->getMessage();
            return new ServerHealthResult(
                $this->name,
                ServerStates::error,
                "Error: $error",
                getRunningTime($starttime)
            );
        }
    }
}
