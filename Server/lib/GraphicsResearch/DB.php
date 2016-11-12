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

    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
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
            unit_id VARCHAR(32) PRIMARY KEY,
            job_id INTEGER,
            verification_code VARCHAR(32),
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

    public function migrateSchema() {
        $this->dbh->exec(self::initialTableSql);
        $version = (int)$this->fetchOne("SELECT MAX(version) FROM schema_version");
        $schemaVersion = $version;

        // メモ: JSON フォーマットを内部に持つ job テーブルの　question_order_json は、
        //      回答データファイルのメタ情報が増えた時には内部データの変換が必要になるはずなので注意

        // マイグレーション処理
        foreach ([
            function () { $this->extendQuestionOrderJson(); },
            function () { $this->editableQuestionPageMessage(); },
            function () { $this->migrateJudgementDataJsonToTable(); },
            function () { $this->deleteJudgementJsonData(); },
            function () { $this->supportQuizMode(); },
            function () { $this->lodComparable(); },
        ] as $version => $migrateInfo) {
            $version += 1; // version is 1 origin 
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

    /////////////////////////////////////////////////////////////////
    //
    // マイグレーション処理
    //
    /////////////////////////////////////////////////////////////////

    // データの表示順序を保持するカラムを追加 (データ量 > 64KB になりそうなので MEDIUMBLOB)
    private function extendQuestionOrderJson() {
        $migrateSql = "ALTER TABLE job ADD COLUMN question_order_json MEDIUMBLOB";
        $this->dbh->exec($migrateSql);
    }

    // 質問ページのメッセージを管理画面で編集できるようにカラムを追加
    private function editableQuestionPageMessage() {
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

    // 判定データを JSON からテーブルの行に移行
    private function migrateJudgementDataJsonToTable() {
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

    // 判定データ格納用 JSON カラムを削除
    private function deleteJudgementJsonData() {
        $deleteJudgmentDataJsonColumn = "
        ALTER TABLE job_unit DROP judgement_data_json;
        ";
        $this->dbh->exec($deleteJudgmentDataJsonColumn);
    }

    // 足切り用データ格納用テーブル
    private function supportQuizMode() {
        $createQuizTables = "
        CREATE TABLE IF NOT EXISTS job_quiz_unit (
            unit_id VARCHAR(16) PRIMARY KEY NOT NULL,
            job_id INTEGER NOT NULL,
            verification_code VARCHAR(32) NOT NULL,
            question_count INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS job_quiz_unit_golden (
            id INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
            job_id INTEGER NOT NULL,
            model_id MEDIUMINT UNSIGNED NOT NULL,
            rotation_id TINYINT UNSIGNED NOT NULL,
            lod TINYINT UNSIGNED NOT NULL,
            is_same TINYINT NOT NULL, 
            INDEX (job_id)
        );
        CREATE TABLE IF NOT EXISTS job_quiz_unit_judgement (
            id INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
            golden_id INTEGER NOT NULL,
            unit_id VARCHAR(16) NOT NULL,
            is_correct TINYINT NOT NULL,
            worker_id VARCHAR(16) NOT NULL,
            quiz_sid VARCHAR(16) NOT NULL,
            INDEX (golden_id),
            INDEX (quiz_sid)
        );
        ALTER TABLE job ADD COLUMN quiz_accuracy_rate FLOAT;
        ";
        $this->dbh->exec($createQuizTables);
    }

    // LOD個別比較用
    private function lodComparable() {
        $sql = "
        ALTER TABLE job_unit_judgement ADD COLUMN is_better_than_ref TINYINT
        ";
        $this->dbh->exec($sql);
    }
}