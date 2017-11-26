<?php

namespace Nekudo\ShinyGears;

use Nekudo\ShinyGears\Exceptions\WorkerException;

abstract class Worker
{
    /** @var string $poolName Name of worker-pool */
    protected $poolName;

    /** @var string Unique name to identify worker. */
    protected $workerName;

    /** @var \GearmanWorker */
    protected $GearmanWorker;

    /** @var int Jobs handled by worker since start. */
    protected $jobsTotal = 0;

    /** @var int Worker startup time. */
    protected $startupTime = 0;

    /** @var  string $runPath Path path to store pid files. */
    protected $runPath;

    abstract protected function registerCallbacks();

    public function __construct(array $config, string $poolName, string $workerName)
    {
        $this->setPoolName($poolName);
        $this->setWorkerName($workerName);
        $this->setRunPath($config['paths']['run']);
        $this->startupTime = time();

        $this->GearmanWorker = new \GearmanWorker;
        $this->GearmanWorker->addServer($config['gearman']['host'], $config['gearman']['port']);

        // Register methods every worker has:
        $this->GearmanWorker->addFunction('ping_' . $this->workerName, [$this, 'ping']);
        $this->GearmanWorker->addFunction('jobinfo_' . $this->workerName, [$this, 'getJobInfo']);
        $this->GearmanWorker->addFunction('pidupdate_' . $this->workerName, [$this, 'updatePidFile']);

        $this->registerCallbacks();

        // Let's roll...
        $this->startup();
    }

    /**
     * Fetches pool name of a worker.
     *
     * @return string
     */
    public function getPoolName() : string
    {
        return $this->poolName;
    }

    /**
     * Sets the pool a worker belongs to.
     *
     * @param string $poolName
     * @return void
     */
    public function setPoolName(string $poolName) : void
    {
        $this->poolName = $poolName;
    }

    /**
     * Return workers identifier.
     *
     * @return string
     */
    public function getWorkerName() : string
    {
        return $this->workerName;
    }

    /**
     * Sets workers identifier.
     *
     * @param string $workerName
     * @return void
     */
    public function setWorkerName(string $workerName) : void
    {
        $this->workerName = $workerName;
    }

    /**
     * Returns base path containing pid files.
     *
     * @param string $pool If set pool will be attached to basic run path.
     * @return string
     */
    public function getRunPath(string $pool = '') : string
    {
        return $this->runPath . (empty($pool) ? '' : '/' . $pool);
    }

    /**
     * Sets base path to store pid files in.
     *
     * @param string $runPath
     * @return void
     */
    public function setRunPath(string $runPath) : void
    {
        $this->runPath = $runPath;
    }

    /**
     * Startup worker and wait for jobs.
     *
     * @return void
     */
    protected function startup() : void
    {
        $this->updatePidFile();
        while ($this->GearmanWorker->work()) {
            // wait for jobs...
        }
    }

    /**
     * Simple ping method to test if worker is alive.
     *
     * @param \GearmanJob $Job
     * @return void
     */
    public function ping($Job) : void
    {
        $Job->sendData('pong');
    }

    /**
     * Increases job counter.
     *
     * @return void
     */
    public function countJob() : void
    {
        $this->jobsTotal++;
    }

    /**
     * Returns information about jobs handled.
     *
     * @param \GearmanJob $Job
     * @return void
     */
    public function getJobInfo($Job) : void
    {
        $uptimeSeconds = time() - $this->startupTime;
        $uptimeSeconds = ($uptimeSeconds === 0) ? 1 : $uptimeSeconds;
        $avgJobsMin = $this->jobsTotal / ($uptimeSeconds / 60);
        $avgJobsMin = round($avgJobsMin, 2);
        $response = [
            'jobs_total' => $this->jobsTotal,
            'avg_jobs_min' => $avgJobsMin,
            'uptime_seconds' => $uptimeSeconds,
        ];
        $Job->sendData(json_encode($response));
    }

    /**
     * Updates PID file for the worker.
     *
     * @return void
     * @throws WorkerException
     */
    public function updatePidFile() : void
    {
        $pidFolder = $this->getRunPath($this->poolName);
        if (!file_exists($pidFolder)) {
            mkdir($pidFolder, 0755, true);
        }
        $pidFile = $pidFolder . '/' . $this->workerName . '.pid';
        $pid = getmypid();
        if (file_put_contents($pidFile, $pid) === false) {
            throw new WorkerException('Could not create PID file.');
        }
    }
}
