<?php

/**
 * Migration 014: rename settings.key/value -> setting_key/setting_value for sqlite/postgresql
 *
 * Some environments (MySQL) required using different column names because
 * `key` is a reserved word and TEXT columns can't have defaults. To keep
 * sqlite and postgresql consistent, rename columns when necessary.
 */

return [
    'name' => 'rename_settings_columns',

    'up' => function (PDO $db) {
        $helper = \App\Database\DatabaseHelper::class;
        $driver = $helper::getDriver($db);

        // If settings table does not exist, nothing to do
        try {
            if ($driver === 'sqlite') {
                $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
                if ($stmt === false || $stmt->fetch() === false) {
                    return;
                }

                $pr = $db->query("PRAGMA table_info(settings)");
                $cols = $pr ? $pr->fetchAll(PDO::FETCH_ASSOC) : [];
                $names = array_map(function ($c) { return $c['name']; }, $cols);

                if (in_array('setting_key', $names) && in_array('setting_value', $names)) {
                    return; // already migrated
                }

                // Try RENAME COLUMN (supported in newer SQLite). If fails, fallback to recreate table.
                try {
                    if (in_array('key', $names)) {
                        $db->exec('ALTER TABLE settings RENAME COLUMN "key" TO setting_key');
                    }
                    if (in_array('value', $names)) {
                        $db->exec('ALTER TABLE settings RENAME COLUMN "value" TO setting_value');
                    }
                    return;
                } catch (\Exception $e) {
                    // fallback: recreate table with new schema and copy data
                }

                // recreate path
                $autoInc = $helper::getAutoIncrement($db);
                $shortText = $helper::getTextType($db, 191);
                $textType = $helper::getTextType($db);
                $timestampType = $helper::getTimestampType($db);
                $currentTimestamp = $helper::getCurrentTimestamp($db);

                $db->exec('BEGIN TRANSACTION');
                // copy existing data
                $db->exec('CREATE TABLE settings_temp AS SELECT * FROM settings');

                // drop old and create new with desired schema
                $db->exec('DROP TABLE settings');
                $db->exec("
                    CREATE TABLE settings (
                        id {$autoInc},
                        setting_key {$shortText} NOT NULL UNIQUE,
                        setting_value {$textType} NOT NULL,
                        updated_at {$timestampType} DEFAULT {$currentTimestamp}
                    )
                ");

                // copy values mapping old names if present
                $selectKey = in_array('key', $names) ? '"key"' : 'NULL';
                $selectValue = in_array('value', $names) ? '"value"' : 'NULL';
                $db->exec("INSERT INTO settings (setting_key, setting_value, updated_at) SELECT COALESCE({$selectKey}, ''), COALESCE({$selectValue}, ''), updated_at FROM settings_temp");

                $db->exec('DROP TABLE settings_temp');
                $db->exec('COMMIT');

                return;
            }

            if ($driver === 'postgresql') {
                // Check existence of table/columns
                $colsStmt = $db->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = 'settings'");
                $colsStmt->execute();
                $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
                if ($cols === false || count($cols) === 0) {
                    return; // no settings table
                }

                if (in_array('setting_key', $cols) && in_array('setting_value', $cols)) {
                    return; // already migrated
                }

                // rename columns if present
                try {
                    if (in_array('key', $cols)) {
                        $db->exec('ALTER TABLE settings RENAME COLUMN "key" TO setting_key');
                    }
                    if (in_array('value', $cols)) {
                        $db->exec('ALTER TABLE settings RENAME COLUMN "value" TO setting_value');
                    }
                } catch (\Exception $e) {
                    // if rename fails, log and rethrow to surface the issue
                    throw $e;
                }
            }

            // For mysql nothing to do (already uses setting_key/setting_value)
        } catch (\Exception $e) {
            // Migration should fail loudly if something unexpected happens
            throw $e;
        }
    }
];
