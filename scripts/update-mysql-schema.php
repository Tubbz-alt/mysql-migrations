<?php

/**
 * Copyright 2014 Shazam Entertainment Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this 
 * file except in compliance with the License.
 *
 * You may obtain a copy of the License at http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed under 
 * the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR 
 * CONDITIONS OF ANY KIND, either express or implied. See the License for the specific 
 * language governing permissions and limitations under the License.
 *
 * @author toni lopez <toni.lopez@shazam.com>
 */

use Common\Config;

date_default_timezone_set('UTC');
require_once __DIR__ . '/../../../autoload.php';

$config = Config::getInstance();
$path = __DIR__ . '/../../../../config';
$config->loadConfig(array("$path/environment.yml"));
$mysqlConfig = $config->fetch('db');
$dsn = "mysql:dbname={$mysqlConfig['schema']};host={$mysqlConfig['host']}";
$mysql = new PDO($dsn, $mysqlConfig['user'], $mysqlConfig['password']);

// is _schema table there? if not, let's create it
if (!$mysql->query('select 1 from _schema')) {
    $schemaQuery = file_get_contents(__DIR__ . '/../../../../db/schema.sql');
    if ($mysql->exec($schemaQuery) === false) {
        $error = $mysql->errorInfo();
        echo "Cannot apply schema.sql: " . $error[2] . "\n";
        exit();
    }

    $query = <<<SQL
create table if not exists _schema (
    version int unsigned not null,
    timestamp timestamp default now(),
    result enum('success', 'error') not null
);
SQL;
    if (!$mysql->exec($query) === false) {
        $error = $mysql->errorInfo();
        echo "Cannot apply schema.sql: " . $error[2];
        exit();
    }
}

// get latest delta applied
$statement = $mysql->query('select version from _schema where result = "success" order by version desc limit 1');
$rows = $statement->fetchAll();
$latestVersion = empty($rows) ? 0 : $rows[0]['version'];

$deltas = array();
foreach (scandir(__DIR__ . '/../../../../db/deltas/') as $delta) {
    if (!preg_match('/^v(?<version>[0-9]+)\.sql$/', $delta, $matches)) {
        continue;
    }

    if ($latestVersion < $matches['version']) {
        $deltas[$matches['version']] = $delta;
    }
}

if (empty($deltas)){
    echo "Your db is already up to date.\n";
}

ksort($deltas);

foreach ($deltas as $key => $delta) {
    if ($mysql->exec(file_get_contents(__DIR__ . '/../../../../db/deltas/' . $delta)) === false) {
        $error = $mysql->errorInfo();
        $mysql->exec("insert into _schema (version, result) values ($key, 'error')");
        echo "Error trying to apply delta $delta: " . $error[2] . "\n";
        exit();
    }
    $mysql->exec("insert into _schema (version, result) values ($key, 'success')");
    echo "Applied delta $delta.\n";
}
