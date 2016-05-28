<?php

namespace Jha;

/**
* Data Access Object for jcdecaux_fetch_store and jcdecaux_stats_batch
*/
class Dao
{
    protected $logger;
    protected $data_path;

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);
        $this->logger->debug(__METHOD__, func_get_args());

        $this->data_path = $container['settings']['jcd_data'];
        $this->checkDataDirectory();
    }

    public function checkDataDirectory()
    {
        $this->logger->debug(__METHOD__, func_get_args());
        if (! is_dir($this->data_path)) {
            throw new \Exception("Data directory is not a directory");
        }
    }

    public function getDates()
    {
        $dates = [];
        $handle = opendir($this->data_path);
        if ($handle == false) {
            throw new \Exception("Cannot read data directory");
        }
        while (false !== ($entry = readdir($handle))) {
            $matches = null;
            $res = preg_match(
                "/^samples_(\d{4})_(\d{2})_(\d{2}).db$/",
                $entry,
                $matches
            );
            if ($res === false) {
                throw new \Exception("Cannot match dates from files");
            }
            if ($res > 0) {
                array_shift($matches);
                $dates[] = join('-', $matches);
            }
        }
        closedir($handle);
        return $dates;
    }
}
