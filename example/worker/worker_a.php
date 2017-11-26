<?php

namespace Nekudo\ShinyGears\Example\Worker;

use Nekudo\ShinyGears\Worker;

class WorkerA extends Worker
{
    /**
     * This method is required by every worker. It is used to registers the actual worker
     * methods which handle your jobs.
     */
    protected function registerCallbacks()
    {
        $this->GearmanWorker->addFunction('sayHello', [$this, 'sayHello']);
    }

    /**
     * A dummy methods.
     * Says hello...
     *
     * @param \GearmanJob $Job
     * @return bool
     */
    public function sayHello(\GearmanJob $Job)
    {
        /**
         * You should call this method to collect information about your workers.
         * This data can be shown using the status method.
         */
        $this->countJob();

        // Get the arguments passed to your worker.
        $params = json_decode($Job->workload(), true);

        // Do some actual work here...
        return "Hello World!";
    }
}
