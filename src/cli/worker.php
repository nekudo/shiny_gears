<?php
/**
 * This script is used to start a Gearman worker.
 * It it called by ShinyGears Manager.
 */
$options = getopt('', [
    'config:',
    'pool:',
    'name:',
]);

if (empty($options['config'])) {
    echo "Path to config file is required.";
    exit(1);
}
if (!file_exists($options['config'])) {
    echo "Config file not found.";
    exit(1);
}
if (empty($options['name'])) {
    echo "Name is required.";
    exit(1);
}
if (empty($options['pool'])) {
    echo "Pool name is required.";
    exit(1);
}

$pathToConfig = $options['config'];
$poolName = $options['pool'];
$workerName = $options['name'];

// startup worker:
require_once __DIR__ . '/../../vendor/autoload.php';
$config = include $pathToConfig;
$workerFile = $config['pools'][$poolName]['worker_file'];
$workerClass = $config['pools'][$poolName]['worker_class'];
require_once  $workerFile;
$worker = new $workerClass($config, $poolName, $workerName);
