<?php

return [
    // Gearman server related settings:
    'gearman' => [
        // hostname of your Gearman server:
        'host' => '127.0.0.1',

        // Port of your Gearman server:
        'port' => 4730,
    ],

    // Paths to folders and files:
    'paths' => [
        // Path to this file:
        'config' => __FILE__,

        // Path to folder containing log files:
        'log' => __DIR__ . '/logs',

        // Path to folder containing pid files:
        'run' => __DIR__ . '/run',
    ],

    // Process pool configuration. Add as many pools as you like.
    'pools' => [

        // Unique name/identifier for each pool:
        'pool_a' => [

            // Path to the worker file:
            'worker_file' => __DIR__ . '/worker/worker_a.php',

            // Classname of worker:
            'worker_class' => '\Nekudo\ShinyGears\Example\Worker\WorkerA',

            // Number of worker to start;
            'instances' => 3,
        ],

        /* add as many pools as you like ...
        'pool_b' => [
            'worker_file' => __DIR__ . '/worker/worker_b.php',

            'worker_class' => '\Nekudo\ShinyGears\Example\Worker\WorkerB',

            'instances' => 2,
        ],
        */
    ],
];
