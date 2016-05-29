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
    protected $lastError;

    const ERR_CONTRACT_NOT_FOUND = "contract not found";
    const ERR_STATION_NOT_FOUND = "station not found";
    const ERR_DATE_NOT_FOUND = "date not found";

    public function __construct($container)
    {
        $this->logger = new \Monolog\Logger(__CLASS__);
        $this->logger->pushHandler($container['log_stream']);

        $this->dataPath = $container['settings']['jcd_data_abs_path'];
        $this->checkDataDirectory();

        $this->pdo = $this->getAppPdo();

        $this->lastError = null;
    }

    public function getLastError()
    {
        return $this->lastError;
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

    public function getSamplesPdo($date)
    {
        $filename = 'samples_' . strtr($date, '-', '_') . '.db';
        return $this->getPdo($filename, false);
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
            $this->lastError = self::ERR_CONTRACT_NOT_FOUND;
            return null;
        }
        $this->lastError = null;
        return $contract;
    }

    public function getStations($contractId)
    {
        if ($this->getContract($contractId) === null) {
            $this->lastError = self::ERR_CONTRACT_NOT_FOUND;
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
        $stations = $stmt->fetchAll();
        $this->lastError = null;
        return $stations;
    }

    public function getStation($contractId, $stationId)
    {
        if ($this->getContract($contractId) === null) {
            $this->lastError = self::ERR_CONTRACT_NOT_FOUND;
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
            $this->lastError = self::ERR_STATION_NOT_FOUND;
            return null;
        }
        $this->lastError = null;
        return $res;
    }

    public function getSamples($date, $contractId, $stationId)
    {
        if ($this->getStation($contractId, $stationId) === null) {
            // lastError was set by getStation
            return null;
        }
        $dataPdo = $this->getSamplesPdo($date);
        if ($dataPdo === null) {
            $this->lastError = self::ERR_DATE_NOT_FOUND;
            return null;
        }
        $stmt = $dataPdo->prepare(
            "SELECT
                timestamp,
                contract_id,
                station_number,
                available_bikes,
                available_bike_stands
            FROM archived_samples
            WHERE contract_id = :cid
            AND station_number = :sid
            ORDER BY timestamp"
        );
        $stmt->execute(array(
            ":cid" => $contractId,
            ":sid" => $stationId
            ));
        $samples = $stmt->fetchAll();
        $this->lastError = null;
        return $samples;
    }
}
