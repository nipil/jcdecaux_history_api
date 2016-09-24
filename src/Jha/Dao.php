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
    const ERR_CANNOT_READ_DIRECTORY = "cannot read directory";

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

    public function getStatsPdo()
    {
        return $this->getPdo('stats.db', true);
    }

    public function getSamplesPdo($date)
    {
        $filename = 'samples_' . strtr($date, '-', '_') . '.db';
        $pdo = $this->getPdo($filename, false);
        if ($pdo === null) {
            $this->lastError = self::ERR_DATE_NOT_FOUND;
            return null;
        }
        $this->lastError = null;
        return $pdo;
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
            throw new \Exception(self::ERR_CANNOT_READ_DIRECTORY . " " . $this->dataPath);
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
        $dataPdo = $this->getSamplesPdo($date);
        if ($dataPdo === null) {
            // lastError was set by getSamplesPdo
            return null;
        }
        if ($this->getStation($contractId, $stationId) === null) {
            // lastError was set by getStation
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

    protected function getTableName($stat, $scope, $period) {
        return $stat . "_" . $scope . "_" . $period;
    }

    protected function getPeriodKey($period) {
        return "start_of_" . $period;
    }

    public function getActivityGlobal($period)
    {
        $statsPdo = $this->getStatsPdo();
        $timeKey = $this->getPeriodKey($period);
        $tableName = $this->getTableName("activity", "global", $period);
        $stmt = $statsPdo->prepare(
            "SELECT
                " . $timeKey . ",
                num_changes
            FROM " . $tableName . "
            ORDER BY " . $timeKey
        );
        $stmt->execute();
        $samples = $stmt->fetchAll();
        $this->lastError = null;
        return $samples;
    }

    public function getActivityContract($period, $contractId)
    {
        $statsPdo = $this->getStatsPdo();
        if ($this->getContract($contractId) === null) {
            // lastError was set by getContract
            return null;
        }
        $timeKey = $this->getPeriodKey($period);
        $tableName = $this->getTableName("activity", "contracts", $period);
        $stmt = $statsPdo->prepare(
            "SELECT
                " . $timeKey . ",
                contract_id,
                num_changes,
                rank_global
            FROM " . $tableName . "
            WHERE contract_id = :cid
            ORDER BY " . $timeKey
        );
        $stmt->execute(array(
            ":cid" => $contractId,
            ));
        $samples = $stmt->fetchAll();
        $this->lastError = null;
        return $samples;
    }

    public function getActivityStation($period, $contractId, $stationId)
    {
        $statsPdo = $this->getStatsPdo();
        if ($this->getStation($contractId, $stationId) === null) {
            // lastError was set by getStation
            return null;
        }
        $timeKey = $this->getPeriodKey($period);
        $tableName = $this->getTableName("activity", "stations", $period);
        $stmt = $statsPdo->prepare(
            "SELECT
                " . $timeKey . ",
                contract_id,
                station_number,
                num_changes,
                rank_contract,
                rank_global
            FROM " . $tableName . "
            WHERE contract_id = :cid
            AND station_number = :sid
            ORDER BY " . $timeKey
        );
        $stmt->execute(array(
            ":cid" => $contractId,
            ":sid" => $stationId
            ));
        $samples = $stmt->fetchAll();
        $this->lastError = null;
        return $samples;
    }

    public function getMinMaxGlobal($period)
    {
        $statsPdo = $this->getStatsPdo();
        $timeKey = $this->getPeriodKey($period);
        $tableName = $this->getTableName("minmax", "global", $period);
        $stmt = $statsPdo->prepare(
            "SELECT
                " . $timeKey . ",
                min_bikes,
                max_bikes
            FROM " . $tableName . "
            ORDER BY " . $timeKey
        );
        $stmt->execute();
        $samples = $stmt->fetchAll();
        $this->lastError = null;
        return $samples;
    }

    public function getMinMaxContract($period, $contractId)
    {
        $statsPdo = $this->getStatsPdo();
        if ($this->getContract($contractId) === null) {
            // lastError was set by getContract
            return null;
        }
        $timeKey = $this->getPeriodKey($period);
        $tableName = $this->getTableName("minmax", "contracts", $period);
        $stmt = $statsPdo->prepare(
            "SELECT
                " . $timeKey . ",
                contract_id,
                min_bikes,
                max_bikes
            FROM " . $tableName . "
            WHERE contract_id = :cid
            ORDER BY " . $timeKey
        );
        $stmt->execute(array(
            ":cid" => $contractId,
            ));
        $samples = $stmt->fetchAll();
        $this->lastError = null;
        return $samples;
    }

    public function getMinMaxStation($period, $contractId, $stationId)
    {
        $statsPdo = $this->getStatsPdo();
        if ($this->getStation($contractId, $stationId) === null) {
            // lastError was set by getStation
            return null;
        }
        $timeKey = $this->getPeriodKey($period);
        $tableName = $this->getTableName("minmax", "stations", $period);
        $stmt = $statsPdo->prepare(
            "SELECT
                " . $timeKey . ",
                contract_id,
                station_number,
                min_bikes,
                max_bikes,
                min_slots,
                max_slots,
                num_changes
            FROM " . $tableName . "
            WHERE contract_id = :cid
            AND station_number = :sid
            ORDER BY " . $timeKey
        );
        $stmt->execute(array(
            ":cid" => $contractId,
            ":sid" => $stationId
            ));
        $samples = $stmt->fetchAll();
        $this->lastError = null;
        return $samples;
    }

    public function getDatabaseSize() {
        $entries = glob(
            $this->dataPath . '/{app,stats,samples_*_*_*}.db',
            GLOB_BRACE | GLOB_ERR
        );
        if ($entries === false) {
            throw new \Exception(self::ERR_CANNOT_READ_DIRECTORY . " " . $this->dataPath);
        }
        $this->logger->debug(__METHOD__, array(
            "$entries" => $entries
        ));
        $totalSize = 0;
        foreach ($entries as $entry) {
            $size = filesize($entry);
            $totalSize += $size;
        }
        return $totalSize;
    }

    public function getStationCount()
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM (
                SELECT DISTINCT
                    contract_id,
                    station_number
                FROM old_samples
            )"
        );
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_NUM);
        return (int) $result[0];
    }

    public function getSampleCount() {
        $statsPdo = $this->getStatsPdo();
        $stmt = $statsPdo->prepare(
            "SELECT SUM(num_changes)
            FROM activity_global_year"
        );
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_NUM);
        return (int) $result[0];
    }

    public function getInfos() {
        $infos = [];
        $infos["dates_count"] = count($this->getDates());
        $infos["contract_count"] = count($this->getContracts());
        $infos["station_count"] = $this->getStationCount();
        $infos["sample_count"] = $this->getSampleCount();
        $infos["database_size"] = $this->getDatabaseSize();
        $this->logger->debug(__METHOD__, array("infos" => $infos));
        return $infos;
    }

    public function getContractsRepartition($max_age_days = 7) {
        $statsPdo = $this->getStatsPdo();
        $tableName = $this->getTableName("minmax", "global", "day");
        $stmt = $statsPdo->prepare(
            "SELECT
                contract_id,
                MAX(max_bikes) AS max_bikes
            FROM minmax_contracts_day
            WHERE start_of_day >= strftime(
                '%s',
                date(
                    (SELECT MAX(start_of_day) FROM minmax_contracts_day),
                    'unixepoch',
                    '-" . $max_age_days . " days'
                )
            )
            GROUP BY contract_id
            ORDER BY contract_id"
        );
        $stmt->execute();
        $samples = $stmt->fetchAll();
        $this->lastError = null;
        return $samples;
    }
}
