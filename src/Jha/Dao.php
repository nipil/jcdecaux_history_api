<?php

namespace Jha;

/**
* Data Access Object for jcdecaux_fetch_store and jcdecaux_stats_batch
*/
class Dao
{
    protected $logger;
    protected $dataPath;
    protected $pdo;

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);

        $this->dataPath = $container['settings']['jcd_data_abs_path'];
        $this->checkDataDirectory();

        $this->pdo = $this->getAppPdo();
    }

    private function getPdo($filename, $isErrorFatal)
    {
        try {
            $pdo = new \PDO(
                'sqlite:'
                . $this->dataPath
                . '/'
                . $filename
            );
            $pdo->setAttribute(
                \PDO::ATTR_DEFAULT_FETCH_MODE,
                \PDO::FETCH_ASSOC
            );
            $pdo->setAttribute(
                \PDO::ATTR_ERRMODE,
                \PDO::ERRMODE_EXCEPTION
            );
            return $pdo;
        } catch (\PDOException $e) {
            $this->logger->error($e->getMessage());
            if ($isErrorFatal) {
                throw $e;
            }
            return null;
        }
    }

    public function getAppPdo()
    {
        return $this->getPdo('app.db', true);
    }

    public function checkDataDirectory()
    {
        if (! is_dir($this->dataPath)) {
            throw new \Exception($this->dataPath . " is not a directory");
        }
    }

    public function getDates()
    {
        $dates = [];
        $handle = opendir($this->dataPath);
        if ($handle == false) {
            throw new \Exception("Cannot read directory " . $this->dataPath);
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

    public function getContract($contractId)
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                contract_id,
                contract_name,
                commercial_name,
                country_code,
                cities
            FROM contracts
            WHERE contract_id = :cid"
        );
        $stmt->execute(array(":cid" => $contractId));
        $contract = $stmt->fetch();
        if ($contract === false) {
            return null;
        }
        return $contract;
    }

    public function getStations($contractId)
    {
        if ($this->getContract($contractId) === null) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT
                station_number,
                status,
                bike_stands,
                bonus,
                banking,
                position,
                address,
                station_name
            FROM old_samples
            WHERE contract_id = :cid"
        );
        $stmt->execute(array(":cid" => $contractId));
        return $stmt->fetchAll();
    }

    public function getStation($contractId, $stationId)
    {
        if ($this->getContract($contractId) === null) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT
                station_number,
                status,
                bike_stands,
                bonus,
                banking,
                position,
                address,
                station_name
            FROM old_samples
            WHERE contract_id = :cid
            AND station_number = :sid"
        );
        $stmt->execute(array(
            ":cid" => $contractId,
            ":sid" => $stationId
            ));
        $res = $stmt->fetch();
        if ($res === false) {
            return null;
        }
        return $res;
    }

    public function getSamples($date, $contractId, $stationId)
    {
        // NYI
        return [$date, $contractId, $stationId];
    }
}
