<?php

namespace Jha;

/**
* Data Access Object for jcd_fetch_store and jcd_stats_batch
*/
class Dao
{
    protected $logger;
    protected $data_path;

    public function __construct($logger, $data_path)
    {
        $this->logger = $logger;
        $this->data_path = $data_path;
    }

    public function noop()
    {
        $this->logger->debug(__METHOD__, func_get_args());
    }

    public function getDates()
    {
        $files = scandir($this->data_path);

        return print_r($files, true);
    }
}
