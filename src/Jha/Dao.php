<?php

namespace Jha;

/**
* Data Access Object for jcdecaux_fetch_store and jcdecaux_stats_batch
*/
class Dao
{
    protected $logger;
    protected $data_path;
    protected $pdo;

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);

        $this->data_path = $container['settings']['jcd_data_abs_path'];
        $this->checkDataDirectory();

        try {
            $this->pdo = new \PDO('sqlite:' . $this->data_path . '/app.db');
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        }
    }

    public function checkDataDirectory()
    {
        if (! is_dir($this->data_path)) {
            throw new \Exception($this->data_path . " is not a directory");
        }
    }

    public function getDates()
    {
        $dates = [];
        $handle = opendir($this->data_path);
        if ($handle == false) {
            throw new \Exception("Cannot read directory " . $this->data_path);
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

    public function getContracts()
    {
        $stmt = $this->pdo->query(
            "SELECT
                contract_id,
                contract_name,
                commercial_name,
                country_code,
                cities
            FROM contracts"
        );
        return $stmt->fetchAll();
    }

    public function getContract($id)
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                contract_id,
                contract_name,
                commercial_name,
                country_code,
                cities
            FROM contracts
            WHERE contract_id = :id"
        );
        $stmt->execute(array(":id" => $id));
        $contract = $stmt->fetch();
        if ($contract === false) {
            return null;
        }
        return $contract;
    }
}
