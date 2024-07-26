<?php

namespace codingmammoth\craftsemontowebsitemonitor\models;

use Craft;
use craft\base\Model;
use Exception;

use function codingmammoth\craftsemontowebsitemonitor\functions\{
    check_available_features
};

require_once __DIR__ . './../functions/functions.php';

class Settings extends Model
{
    private $default_test_config =  [
        'ServerLoadNow' => [
            'test' => 'ServerLoad',
            'enabled' => true,
            'config' => ['type' => 'current', 'warning_threshold' => 5, 'error_threshold' => 15],
            'error' => false
        ],
        'ServerLoadAverage5min' => [
            'test' => 'ServerLoad',
            'enabled' => true,
            'config' => ['type' => 'average_5_min', 'warning_threshold' => 5, 'error_threshold' => 15],
            'error' => false
        ],
        'ServerLoadAverage15min' => [
            'test' => 'ServerLoad',
            'enabled' => true,
            'config' => ['type' => 'average_15_min', 'warning_threshold' => 5, 'error_threshold' => 15],
            'error' => false
        ],
        'MemoryUsage' => [
            'test' => 'MemoryUsage',
            'enabled' => true,
            'config' => ['warning_threshold' => 75, 'error_threshold' => 90],
            'error' => false
        ],
        'DiskSpace' => [
            'test' => 'DiskSpace',
            'enabled' => true,
            'config' => ['disks' => []],
            'error' => false
        ],
        'DiskSpaceInode' => [
            'test' => 'DiskSpaceInode',
            'enabled' => true,
            'config' => ['disks' => []],
            'error' => false
        ],
        'CraftCMSCheckDBConnection' => [
            'test' => 'CraftCMSCheckDBConnection',
            'enabled' => true,
            'config' => [],
            'error' => false
        ],
        'CraftCMSMaxDBConnection' => [
            'test' => 'CraftCMSMaxDBConnection',
            'enabled' => true,
            'config' => ['warning_percentage_threshold' => 80, 'error_percentage_threshold' => 90],
            'error' => false
        ],
        'CraftCMSUpdates' => [
            'test' => 'CraftCMSUpdates',
            'enabled' => true,
            'config' => [ 'alert_non_critical_updates' => false ],
            'error' => false
        ]
    ];

    public $secretKey = '';

    public $test_config = [];

    public $caching_lifespan = 45;

    public $caching_enabled = true;

    public function getTestConfig()
    {
        // Compares to the default_test_config, if default_test_config has a test that is not in test_config, it adds it
        $this->updateTestConfig();
        return $this->test_config;
    }

    public function updateTestConfig()
    {
        $features = check_available_features();

        $db = Craft::$app->getDb();
        $driverName = $db->getDriverName();
        if ($driverName !== 'mysql') {
            unset($this->default_test_config['CraftCMSMaxDBConnection']);
        }

        $defaultDiskConfig = [];
        if ($features['df_command']) {
            $defaultDiskConfig = $this->getDefaultDiskConfig();
        }
        $this->default_test_config['DiskSpace']['config']['disks'] = $defaultDiskConfig;
        $this->default_test_config['DiskSpaceInode']['config']['disks'] = $defaultDiskConfig;

        foreach ($this->default_test_config as $test => $config) {
            if (!array_key_exists($test, $this->test_config)) {
                $this->test_config[$test] = $config;
            }
        }

        foreach ($this->test_config as $test => $config) {
            if (!array_key_exists($test, $this->default_test_config)) {
                unset($this->test_config[$test]);
            }
        }

        $existing_disks = array_keys($defaultDiskConfig);
        $configured_disks_diskspace = array_keys($this->test_config['DiskSpace']['config']['disks']);
        $configured_disks_diskspace_inode = array_keys($this->test_config['DiskSpaceInode']['config']['disks']);

        // Add all missing disks.
        foreach ($existing_disks as $existing_disk) {
            if (!in_array($existing_disk, $configured_disks_diskspace)) {
                $this->test_config['DiskSpace']['config']['disks'][$existing_disk] = $defaultDiskConfig[$existing_disk];
            }

            if (!in_array($existing_disk, $configured_disks_diskspace_inode)) {
                $this->test_config['DiskSpaceInode']['config']['disks'][$existing_disk] = $defaultDiskConfig[$existing_disk];
            }
        }

        // Remove non-exising disks.
        foreach ($configured_disks_diskspace as $configured_disk) {
            if (!in_array($configured_disk, $existing_disks)) {
                unset($this->test_config['DiskSpace']['config']['disks'][$configured_disk]);
            }
        }

        foreach ($configured_disks_diskspace_inode as $configured_disk) {
            if (!in_array($configured_disk, $existing_disks)) {
                unset($this->test_config['DiskSpaceInode']['config']['disks'][$configured_disk]);
            }
        }
    }

    public function setTestConfig($test_config)
    {
        $this->test_config = $test_config;
    }

    public function setDefault()
    {
        $this->updateTestConfig();
        $this->setTestConfig($this->default_test_config);
        $this->setCachingSettings(45, true);
    }

    public function setTest($test_id, $enabled, $config = null)
    {
        if (!isset($this->test_config[$test_id])) {
            throw new Exception("The test id {$test_id} does not exist.");
        }

        $this->test_config[$test_id]['enabled'] = $enabled;

        if ($test_id == 'DiskSpace' || $test_id == 'DiskSpaceInode') {
            // The enabled key can be missing for a disk.
            $disks = array_keys($config['disks']);
            foreach ($disks as $disk) {
                if (!isset($config['disks'][$disk]['enabled'])) {
                    $config['disks'][$disk]['enabled'] = 0;
                }
            }
            $this->test_config[$test_id]['config'] = array_merge($this->test_config[$test_id]['config'], $config);
        } else if ($test_id == 'CraftCMSUpdates') {
            if ($config) {
                $this->test_config['CraftCMSUpdates']['config'] = array_merge($this->test_config['CraftCMSUpdates']['config'], $config);
            } else {
                $this->test_config['CraftCMSUpdates']['config'] = $this->default_test_config['CraftCMSUpdates']['config'];
            }
        } else {
            if ($config) {
                $this->test_config[$test_id]['config'] = array_merge($this->test_config[$test_id]['config'], $config);
            }
        }
    }

    public function getDefaultDiskConfig()
    {
        $disks = [];

        try {
            $output = shell_exec('df -h');
            $lines = explode("\n", $output);

            for ($i = 1; $i < count($lines); $i++) {
                if (trim($lines[$i]) != '') {
                    $cols = preg_split('/\s+/', $lines[$i]);
                    if (preg_match('/^\/dev\//', $cols[0])) {
                        $disks[$cols[0]] = [
                            'enabled' => true,
                            'warning_percentage_threshold' => 80,
                            'error_percentage_threshold' => 90,
                            'error' => false
                        ];
                    }
                }
            }
        } catch (\Throwable $th) {
            return [];
        }

        return $disks;
    }

    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function setCachingSettings($lifespan, $enabled)
    {
        $this->caching_lifespan = $lifespan;
        $this->caching_enabled = $enabled;
    }

    public function generateConfig()
    {
        $features = check_available_features();

        $this->updateTestConfig();
        $enabledTests = array_filter($this->test_config, function($test) use ($features) {
            if ($test['test'] === 'ServerLoad' && !$features['sys_getloadavg_function']) {
                return false;
            }

            if (($test['test'] === 'DiskSpace' || $test['test'] === 'DiskSpaceInode') && !$features['df_command']) {
                return false;
            }

            if ($test['test'] === 'MemoryUsage' && !$features['vmstat_command']) {
                return false;
            }

            return $test['enabled'] === true;
        });

        $enabledTests = array_map(function($test) {
            unset($test['enabled']);
            unset($test['error']);
            if (!is_array($test['config'])) { $test['config'] = []; }
            return $test;
        }, $enabledTests);

        foreach ($enabledTests as $test_id => $test) {
            if ($test_id == 'DiskSpace' || $test_id == 'DiskSpaceInode') {
                $enabledDisks = array_filter($test['config']['disks'], function($disk) {
                    return $disk['enabled'] == true;
                });

                $enabledTests[$test_id]['config']['disks'] = [];

                foreach ($enabledDisks as $disk => $disk_config) {
                    $enabledTests[$test_id]['config']['disks'][] = [
                        'name' => $disk,
                        'warning_percentage_threshold' => $disk_config['warning_percentage_threshold'],
                        'error_percentage_threshold' => $disk_config['error_percentage_threshold']
                    ];
                }
            }
        }

        $config = [
            'secret_key' => $this->secretKey,
            'cache' => [
                'location' => sys_get_temp_dir(),
                'life_span' => $this->caching_lifespan,
                'enabled' => $this->caching_enabled,
            ],
            'db' => [
                'initialise_type' => 'credentials',
                'function_name' => null,
                'connect' => false,
                'db_host' => '',
                'db_user' => '',
                'db_pass' => '',
                'db_port' => 3306,
            ],
            'tests' => $enabledTests
        ];
        return $config;
    }
}
