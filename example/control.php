<?php
/**
 * This script can be used to control your workers.
 *
 * Call it like e.g.: php control.php start
 */

if (empty($argv)) {
    exit('Script can only be run in cli mode.' . PHP_EOL);
}
if (empty($argv[1])) {
    exit('No action given. Valid actions are: start|stop|restart|status|keepalive' . PHP_EOL);
}

try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $config = include __DIR__ . '/config.php';
    $manager = new \Nekudo\ShinyGears\Manager($config);
    $action = $argv[1];
    switch ($action) {
        case 'start':
            $manager->start();
            echo 'Worker processes successfully started' . PHP_EOL;
            break;
        case 'stop':
            $manager->stop();
            echo 'Worker processes successfully stopped.' .PHP_EOL;
            break;
        case 'restart':
            $manager->restart();
            echo 'Worker processes successfully restarted.' . PHP_EOL;
            break;
        case 'keepalive':
            $manager->keepalive();
            break;
        case 'status':
            $response = $manager->status();

            echo '+' . str_repeat('-', 85) . '+' . PHP_EOL;
            echo '|' . str_pad('ShinyGears', 85, ' ', STR_PAD_BOTH) . '|' . PHP_EOL;
            echo '|' . str_pad('-= CURRENT WORKER STATUS =-', 85, ' ', STR_PAD_BOTH) . '|' . PHP_EOL;
            echo '+' . str_repeat('-', 85) . '+' . PHP_EOL;

            if (empty($response)) {
                echo '| ' . str_pad('No active workers found.', 84) . '|' . PHP_EOL;
                echo '+' . str_repeat('-', 85) . '+' . PHP_EOL;
                exit;
            }

            echo '| Pool                    | Worker   | S | Jobs Total | ~ Jobs/Min | Uptime | Ping    |' . PHP_EOL;
            echo '+-------------------------+----------+---+------------+------------+--------+---------+' . PHP_EOL;
            foreach ($response as $poolName => $poolInfo) {
                if (empty($poolInfo)) {
                    continue;
                }

                foreach ($poolInfo as $wrokerId => $workerInfo) {
                    echo '| ' . str_pad($poolName, 24);
                    echo '| ' . str_pad($wrokerId, 9);
                    if ($workerInfo['status'] === 'idle') {
                        echo "| \033[0;32mI\033[0m ";
                        echo '| ' . str_pad($workerInfo['jobs_total'], 11);
                        echo '| ' . str_pad($workerInfo['avg_jobs_min'], 11);
                        echo '| ' . str_pad(round($workerInfo['uptime_seconds'] / 3600).'h', 7);
                        echo '| ' . str_pad(round($workerInfo['ping'], 3).'s', 8) . '|' . PHP_EOL;
                    } else {
                        echo "| \033[0;31mB\033[0m ";
                        echo '| ' . str_pad('n/a', 11);
                        echo '| ' . str_pad('n/a', 11);
                        echo '| ' . str_pad('n/a', 7);
                        echo '| ' . str_pad('n/a', 8) . '|' . PHP_EOL;
                    }
                }
            }
            echo '+' . str_repeat('-', 85) . '+' . PHP_EOL;
            echo PHP_EOL;
            break;
        default:
            exit('Invalid action. Valid actions are: start|stop|restart|status|keepalive' . PHP_EOL);
    }
} catch (\Nekudo\ShinyGears\Exceptions\ManagerException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
