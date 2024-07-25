<?php

namespace Semonto\ServerHealth;

use Semonto\ServerHealth\{
    ServerStates,
    ServerHealthResult,
    ServerHealthTest
};

class MySQLPing extends ServerHealthTest
{
    protected $name = 'MySQL Ping';

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
            } else if (mysqli_ping($this->db)) {
                $totaltime = getRunningTime($starttime);

                return new ServerHealthResult(
                    $this->name,
                    ServerStates::ok,
                    "Total time: $totaltime",
                    $totaltime
                );
            } else {
                return new ServerHealthResult(
                    $this->name,
                    ServerStates::error,
                    "Failed to ping the db server",
                    getRunningTime($starttime)
                );
            }
        } catch (\Throwable $th) {
            $error = $th->getMessage();
            return new ServerHealthResult(
                $this->name,
                ServerStates::error,
                "Failed to ping, connect or login on the db server. Error: $error",
                getRunningTime($starttime)
            );
        }
    }
}
