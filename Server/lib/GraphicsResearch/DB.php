<?php

namespace GraphicsResearch;

use PDO;

class DB {
    private $dbh;
    private static $defaultConnection;

    private function __construct($dsn, $username, $password) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true,
        ];
        $this->dbh = new PDO($dsn, $username, $password, $options);
    }

    public function transaction($handler) {
        if (!is_callable($handler)) {
            throw new \Exception("Transaction handler is not callable");
        }
        $ok = false;
        $result = null;
        try {
            $this->dbh->beginTransaction();
            $result = $handler($this);
            $ok = true;
        } catch (\Exception $e) {
            $this->dbh->rollBack();
            throw $e;
        } finally {
            if ($ok) {
                $this->dbh->commit();
            }
        }
        return $result;
    }

    public function execute($sql, $params = []) {
        $stmt = $this->dbh->prepare($sql);
        if (!is_array($params)) {
            $params = [$params];
        }
        $stmt->execute($params);
        return $stmt;
    }

    public function each($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch(PDO::FETCH_NUM)[0];
    }

    public function fetchRow($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function insert($table, $params = []) {
        $this->metaExecute("INSERT INTO", $table, $params);
        return $this->dbh->lastInsertId();
    }

    public function insertMulti($table, $rows = []) {
        if (empty($rows)) {
            return;
        }
        $columns = [];
        $values = [];
        list($row) = $rows;
        foreach ($row as $key => $value) {
            $columns[] = $key;
            $values[] = ":$key";
        }
        $sql = "INSERT INTO $table (".implode(",", $columns).") VALUES (".implode(", ", $values).")";
        $stmt = $this->dbh->prepare($sql);
        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }

    public function replace($table, $params = []) {
        return $this->metaExecute("REPLACE INTO", $table, $params);
    }

    public function update($table, $where, $params) {
        $columns = [];
        $row = [];
        foreach ($params as $key => $value) {
            $columns[] = "$key=?";
            $row[] = $value;
        }
        $stmt = $this->dbh->prepare("UPDATE $table SET ".implode(", ", $columns)." WHERE $where");
        $stmt->execute($row);
        return $stmt;
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    const initialTableSql = "
        CREATE TABLE IF NOT EXISTS schema_version (
            version INTEGER PRIMARY KEY NOT NULL
        );
        CREATE TABLE IF NOT EXISTS job (
            job_id INTEGER PRIMARY KEY AUTO_INCREMENT,
            title TEXT NOT NULL,
            instructions TEXT NOT NULL,
            questions INTEGER NOT NULL,
            max_assignments INTEGER NOT NULL,
            reward_amount_usd REAL NOT NULL,
            created_on DATETIME NOT NULL,
            crowdflower_job_id INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS job_unit (
            unit_id CHAR(32) PRIMARY KEY,
            job_id INTEGER,
            verification_code CHAR(32),
            created_on DATETIME NOT NULL,
            updated_on DATETIME,
            answered_questions INTEGER NOT NULL,
            judgement_data_json MEDIUMBLOB NOT NULL,
            INDEX (job_id)
        );
        CREATE TABLE IF NOT EXISTS http_audit_log (
            created_on DATETIME NOT NULL,
            handshake TEXT,
            request_header TEXT,
            request_body TEXT,
            response_header TEXT,
            response_body TEXT
        );
    ";
    
    public function migrateSchema() {
        $this->dbh->exec(self::initialTableSql);
        $version = (int)$this->fetchOne("SELECT MAX(version) FROM schema_version");
        $schemaVersion = $version;

        // メモ: JSON フォーマットを内部に持つ job テーブルの　question_order_json は、
        //      回答データファイルのメタ情報が増えた時には内部データの変換が必要になるはずなので注意

        // マイグレーション処理
        foreach ([
            // <SchemaVersion>, <Migrater>
            [1, function () { $this->migrateSchemaVersion_1(); }],
            [2, function () { $this->migrateSchemaVersion_2(); }],
            [3, function () { $this->migrateSchemaVersion_3(); }],
        ] as $migrateInfo) {
            list ($migrateVersion, $migrater) = $migrateInfo;
            if ($version < $migrateVersion) {
                $migrater();
                $version = $migrateVersion;
            }
        }

        if ($schemaVersion < $version) {
            $this->insert("schema_version", ["version" => $version]);
        }
    }

    private function metaExecute($operation, $table, $params) {
        $columns = [];
        $row = [];
        foreach ($params as $key => $value) {
            $columns[] = $key;
            $row[] = $value;
        }
        $sql = "$operation $table (".implode(", ", $columns).") VALUES (".implode(", ", array_fill(0, count($columns), "?")).")";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($row);
        return $stmt;
    }

    public static function instance() {
        if (self::$defaultConnection === null) {
            self::$defaultConnection = new DB(
                "mysql:host=".MYSQL_HOSTNAME.";dbname=".MYSQL_DATABASE.";charset=utf8",
                MYSQL_USERNAME,
                MYSQL_PASSWORD);
        }
        return self::$defaultConnection;
    }

    private function migrateSchemaVersion_1() {
        // データの表示順序を保持するカラムを追加 (データ量 > 64KB になりそうなので MEDIUMBLOB)
        $migrateSql = "ALTER TABLE job ADD COLUMN question_order_json MEDIUMBLOB";
        $this->dbh->exec($migrateSql);
    }

    private function migrateSchemaVersion_2() {
        $migrateSql = "
        CREATE TABLE IF NOT EXISTS question_page (
            page_key VARCHAR(32) PRIMARY KEY NOT NULL,
            instructions TEXT NOT NULL
        );
        ";
        $this->dbh->exec($migrateSql);
        $this->insert("question_page", [
            "page_key" => "default",
            "instructions" => "Could you see ANY visible differences between the left and right images for the centrally located character?",
        ]);
    }

    private function migrateSchemaVersion_3() {
        $migrateSql = "
        CREATE TABLE IF NOT EXISTS job_unit_judgement (
            id INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
            unit_id CHAR(16) NOT NULL,
            model_id MEDIUMINT UNSIGNED NOT NULL,
            rotation_id TINYINT UNSIGNED NOT NULL,
            lod TINYINT UNSIGNED NOT NULL,
            is_same TINYINT NOT NULL, 
            worker_id CHAR(16) NOT NULL,
            INDEX (unit_id)
        );
        ";
        $this->dbh->exec($migrateSql);

        // JSON に格納されたデータを DB に再インポート
        $units = $this->each("SELECT unit_id, judgement_data_json FROM job_unit");
        foreach ($units as $unit) {
            // $answers = [ ModelID => [
            //   "id" => ModelID,
            //   "lod" => judgeLOD,
            //   "rotation" => RotationID,
            //   "judge" => judge(yes/no),
            //   "worker_id" => Contributor ID (Woker ID),
            // ], ...]
            $answers = json_decode($unit["judgement_data_json"], true);
            $rows = [];
            $unitId = $unit["unit_id"];
            foreach ($answers as $answer) {
                $workerId = $unitId;
                if (isset($answer["worker_id"])) {
                    $workerId = $answer["worker_id"];
                }
                $rows[] = [
                    "unit_id" => $unitId,
                    "model_id" => $answer["id"],
                    "rotation_id" => $answer["rotation"],
                    "lod" => $answer["lod"],
                    "is_same" => $answer["judge"] === "Yes" ? 0 : 1,
                    "worker_id" => substr($workerId, 0, 16),
                ];
            }
            $this->insertMulti("job_unit_judgement", $rows);
        }
    }
}