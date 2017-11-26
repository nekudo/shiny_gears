<?php

namespace Nekudo\ShinyGears;

use Nekudo\ShinyGears\Exceptions\ManagerException;

class Manager
{
    /**
     * @var string $gearmanHost Hostname or IP of gearman server.
     */
    protected $gearmanHost = '127.0.0.1';

    /**
     * @var int $gearmanPort Port of gearman server.
     */
    protected $gearmanPort = 4730;

    /**
     * @var string $pidPath Path to store worker pid files.
     */
    protected $runPath;

    /**
     * @var string $logPath Path to store worker log files.
     */
    protected $logPath;

    /**
     * @var string $configPath Path to config file.
     */
    protected $configPath;

    /**
     * @var array $poolsConfig The worker-pool configuration.
     */
    protected $poolsConfig = [];

    /**
     * @var array $pids Holds worker process ids.
     */
    protected $pids = [];

    public function __construct(array $config)
    {
        $this->setLogPath($config['paths']['log']);
        $this->setRunPath($config['paths']['run']);
        $this->setConfigPath($config['paths']['config']);
        $this->setPoolsConfig($config['pools']);
        $this->setGearmanHost($config['gearman']['host']);
        $this->setGearmanPort($config['gearman']['port']);
    }

    /**
     * Gets Gearman server hostname.
     *
     * @return string
     */
    public function getGearmanHost() : string
    {
        return $this->gearmanHost;
    }

    /**
     * Sets the Gearman hostname.
     *
     * @param string $hostname
     * @return void
     * @throws ManagerException
     */
    public function setGearmanHost(string $hostname) : void
    {
        if (empty($hostname)) {
            throw new ManagerException('Hostname can not be empty.');
        }
        $this->gearmanHost = $hostname;
    }

    /**
     * Gets Gearman server port.
     *
     * @return int
     */
    public function getGearmanPort() : int
    {
        return $this->gearmanPort;
    }

    /**
     * Sets Gearman server port.
     *
     * @param int $port
     * @return void
     * @throws ManagerException
     */
    public function setGearmanPort(int $port) : void
    {
        if ($port < 1 || $port > 65535) {
            throw new ManagerException('Invalid port number.');
        }
        $this->gearmanPort = $port;
    }

    /**
     * Returns path to log folder.
     *
     * @return string
     */
    public function getLogPath() : string
    {
        return $this->logPath;
    }

    /**
     * Sets path to log folder.
     *
     * @param string $logPath
     * @return void
     * @throws ManagerException
     */
    public function setLogPath(string $logPath) : void
    {
        if (!file_exists($logPath)) {
            throw new ManagerException('Invalid log path. Folder not found.');
        }
        $this->logPath = $logPath;
    }

    /**
     * Gets path to "run" folder.
     *
     * @return string
     */
    public function getRunPath() : string
    {
        return $this->runPath;
    }

    /**
     * Sets path to "run" folder.
     *
     * @param string $runPath
     * @return void
     * @throws ManagerException
     */
    public function setRunPath(string $runPath) : void
    {
        if (!file_exists($runPath)) {
            throw new ManagerException('Invalid run path. Folder not found.');
        }
        $this->runPath = $runPath;
    }

    /**
     * Gets path to config file.
     *
     * @return string
     */
    public function getConfigPath() : string
    {
        return $this->configPath;
    }

    /**
     * Sets path to config file.
     *
     * @param string $configPath
     * @return void
     * @throws ManagerException
     */
    public function setConfigPath(string $configPath) : void
    {
        if (!file_exists($configPath)) {
            throw new ManagerException('Invalid config path. File not found.');
        }
        $this->configPath = $configPath;
    }

    /**
     * Gets worker-pool configuration.
     *
     * @return array
     */
    public function getPoolsConfig() : array
    {
        return $this->poolsConfig;
    }

    /**
     * Sets worker-pool configuration.
     *
     * @param array $poolsConfig
     * @return void
     */
    public function setPoolsConfig(array $poolsConfig) : void
    {
        $this->poolsConfig = $poolsConfig;
    }

    /**
     * Startup workers.
     *
     * @param string $poolOnly If set only workers of given pool will be started.
     * @return void
     */
    public function start(string $poolOnly = '') : void
    {
        $this->reloadPids();

        foreach ($this->poolsConfig as $pool => $workerConfig) {
            // don't start workers of different type if filter is set
            if (!empty($poolOnly) && $poolOnly !== $pool) {
                continue;
            }

            // don't start new workers if already running:
            if (!empty($this->pids[$pool])) {
                continue;
            }

            // startup the workers:
            for ($i = 0; $i < $workerConfig['instances']; $i++) {
                $this->startupWorker($pool);
            }
        }
    }

    /**
     * Stop workers.
     *
     * @param string $poolOnly If given only workers of this type will be stopped.
     * @return void
     */
    public function stop($poolOnly = '') : void
    {
        $this->reloadPids();
        foreach ($this->poolsConfig as $pool => $workerConfig) {
            // don't stop workers of different type if filter is set
            if (!empty($poolOnly) && $poolOnly !== $pool) {
                continue;
            }

            // skip if no worker running:
            if (empty($this->pids[$pool])) {
                continue;
            }

            // stop the workers:
            foreach ($this->pids[$pool] as $workerId => $pid) {
                exec(escapeshellcmd('kill ' . $pid));
            }
        }
        $this->reloadPids();
    }

    /**
     * Restart workers.
     *
     * @param string $workerId If given only workers of this type will be started.
     * @return void
     */
    public function restart($workerId = '') : void
    {
        $this->stop($workerId);
        sleep(2);
        $this->start($workerId);
    }

    /**
     * Pings every worker and displays result.
     *
     * @return array Status information.
     */
    public function status() : array
    {
        $this->reloadPids();

        $workerStatus = [];
        if (empty($this->pids)) {
            return $workerStatus;
        }

        $Client = new \GearmanClient();
        $Client->addServer($this->gearmanHost, $this->gearmanPort);
        $Client->setTimeout(1000);
        foreach ($this->pids as $pool => $workerPids) {
            $workerStatus[$pool] = [];
            foreach ($workerPids as $workerName => $workerPid) {
                // raises php warning on timeout so we need the "evil @" here...
                $workerStatus[$pool][$workerName] = false;
                $start = microtime(true);
                $pong = @$Client->doHigh('ping_'.$workerName, 'ping');
                if ($pong === 'pong') {
                    $jobinfo = @$Client->doHigh('jobinfo_'.$workerName, 'foo');
                    $jobinfo = json_decode($jobinfo, true);
                    $workerStatus[$pool][$workerName] = $jobinfo;
                    $pingtime = microtime(true) - $start;
                    $workerStatus[$pool][$workerName]['ping'] = $pingtime;
                    $workerStatus[$pool][$workerName]['status'] = 'idle';
                } else {
                    $workerStatus[$pool][$workerName]['jobs_total'] = 'n/a';
                    $workerStatus[$pool][$workerName]['avg_jobs_min'] = 'n/a';
                    $workerStatus[$pool][$workerName]['uptime_seconds'] = 0;
                    $workerStatus[$pool][$workerName]['ping'] = 0;
                    $workerStatus[$pool][$workerName]['status'] = 'busy';
                }
            }
        }

        return $workerStatus;
    }

    /**
     * Pings every worker and does a "restart" if worker is not responding.
     *
     * @return bool
     */
    public function keepalive() : bool
    {
        $this->reloadPids();

        // if already running don't do anything:
        if ($this->managerIsRunning() === true) {
            return false;
        }

        // if there are no workers at all do a fresh start:
        if (empty($this->pids)) {
            $this->start();
            return true;
        }

        // startup new workers if necessary:
        $this->adjustRunningWorkers();

        // delete old pid files:
        $this->reloadPids();

        return true;
    }

    /**
     * Starts a new worker.
     *
     * @param $pool
     * @return void
     */
    protected function startupWorker($pool) : void
    {
        $workerId = $this->getId();
        $baseCmd = 'php %s --config %s --pool %s --name %s';
        $launcherPath = __DIR__ . '/cli/worker.php';
        $startupCmd = sprintf(
            $baseCmd,
            $launcherPath,
            $this->getConfigPath(),
            $pool,
            $workerId
        );

        exec(escapeshellcmd($startupCmd) . ' >> ' . $this->logPath.'/'.$pool.'.log 2>&1 &');
        $this->reloadPids();
    }

    /**
     * Starts up new workers if there are currently running less workers than required.
     *
     * @return void
     */
    protected function adjustRunningWorkers() : void
    {
        foreach ($this->poolsConfig as $pool => $workerConfig) {
            $workersActive = count($this->pids[$pool]);
            $workersTarget = (int)$workerConfig['instances'];
            if ($workersActive >= $workersTarget) {
                continue;
            }

            $workerDiff = $workersTarget - $workersActive;
            for ($i = 0; $i < $workerDiff; $i++) {
                $this->startupWorker($pool);
            }
        }
    }

    /**
     * Gets the process-ids for all workers.
     *
     * @return void
     */
    protected function loadPids() : void
    {
        foreach ($this->poolsConfig as $pool => $poolConfig) {
            $pidFiles = glob($this->runPath . '/' . $pool . '/*.pid');
            foreach ($pidFiles as $pidFile) {
                $workerId = basename($pidFile, '.pid');
                $pid = file_get_contents($pidFile);
                if ($this->pidIsValid($pid, $workerId)) {
                    $this->pids[$pool][$workerId] = $pid;
                } else {
                    $this->removePidFile($pidFile);
                }
            }
        }
    }

    /**
     * Checks if given process is active and valid (by checking worker name).
     *
     * @param int $pid
     * @param string $workerName
     * @return bool
     */
    protected function pidIsValid(int $pid, string $workerName) : bool
    {
        $processInfo = exec(sprintf('ps -p %d -o args=', $pid));
        if (empty($processInfo)) {
            return false;
        }
        if (strpos($processInfo, $workerName) === false) {
            return false;
        }
        return true;
    }

    /**
     * Deletes a pid file.
     *
     * @param string $pidFile
     * @return bool
     */
    protected function removePidFile(string $pidFile) : bool
    {
        if (!file_exists($pidFile)) {
            return true;
        }
        return unlink($pidFile);
    }

    /**
     * Generates path to file for given pid and removes that file.
     *
     * @param int $pid
     * @return bool
     */
    protected function removePidFileByPid(int $pid) : bool
    {
        foreach ($this->pids as $pool => $poolPids) {
            foreach ($poolPids as $workerId => $workerPid) {
                if ($pid !== $workerPid) {
                    continue;
                }
                $pidFile = $this->runPath .'/'.$pool.'/'.$pid.'.pid';
                return $this->removePidFile($pidFile);
            }
        }
        return false;
    }

    /**
     * Reloads the process ids (e.g. during restart)
     *
     * @return void
     */
    protected function reloadPids() : void
    {
        $this->pids = [];
        $this->loadPids();
    }

    /**
     * Checks if an instance of worker manager is already running.
     *
     * @return bool
     */
    protected function managerIsRunning() : bool
    {
        global $argv;
        $cliOutput = [];
        exec('ps x | grep ' . $argv[0], $cliOutput);
        $processCount = 0;
        if (empty($cliOutput)) {
            return false;
        }
        foreach ($cliOutput as $line) {
            if (strpos($line, 'grep') !== false) {
                continue;
            }
            if (strpos($line, '/bin/sh') !== false) {
                continue;
            }
            $processCount++;
        }
        return ($processCount > 1) ? true : false;
    }

    /**
     * Generates a random string of given length.
     *
     * @param int $length
     * @return string
     */
    protected function getId($length = 6) : string
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
    }
}
