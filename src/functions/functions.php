<?php

namespace codingmammoth\craftsemontohealthmonitor\functions;

function check_available_features ()
{
    $features = [
        'exec_function' => true,
        'shell_exec_function' => true,
        'sys_getloadavg_function' => true,
        'df_command' => true,
        'vmstat_command' => true
    ];

    $disabled = explode(',', ini_get('disable_functions'));

    if (in_array('exec', $disabled)) {
        $features['exec_function'] = false;
    }

    if (!function_exists('exec')) {
        $features['exec_function'] = false;
    }

    if (in_array('shell_exec', $disabled)) {
        $features['shell_exec_function'] = false;
    }

    if (!function_exists('shell_exec')) {
        $features['shell_exec_function'] = false;
    }

    if (in_array('sys_getloadavg', $disabled)) {
        $features['sys_getloadavg_function'] = false;
    }

    if (!function_exists('sys_getloadavg')) {
        $features['sys_getloadavg_function'] = false;
    }

    if ($features['exec_function'] && $features['shell_exec_function']) {
        try {
            $df_command = exec('which df');
            if (!$df_command) {
                $features['df_command'] = false;
            }
        } catch (\Throwable $th) {
            $features['df_command'] = false;
        }

        try {
            $vmstat_command = exec('which df');
            if (!$vmstat_command) {
                $features['vmstat_command'] = false;
            }
        } catch (\Throwable $th) {
            $features['vmstat_command'] = false;
        }
    } else {
        $features['df_command'] = false;
        $features['vmstat_command'] = false;
    }

    return $features;
}
