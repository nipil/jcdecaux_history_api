<?php

namespace Jha;

/**
* Data Access Object for jcd_fetch_store and jcd_stats_batch
*/
class Dao
{
    protected $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
        $this->logger->debug(__METHOD__, func_get_args());
    }

    public function noop()
    {
        $this->logger->debug(__METHOD__, func_get_args());
    }
}
