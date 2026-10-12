<?php
declare(strict_types=1);

/* NAT Cloud - installer SQL file importer (mysqli multi_query)
 * This file intentionally contains no request input: the caller passes
 * already-validated connection values and packaged .sql file paths. */

function install_import_sql(string $host, string $port, string $user, string $pass, string $name, array $files): void
{
    $db = new mysqli($host, $user, $pass, $name, (int) $port);
    if ($db->connect_error) {
        throw new RuntimeException('database connect failed');
    }
    $db->set_charset('utf8mb4');
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('sql file missing: ' . basename($file));
        }
        $db->multi_query($sql);
        do {
            $res = $db->store_result();
            if ($res instanceof mysqli_result) {
                $res->free();
            }
        } while ($db->more_results() && $db->next_result());
        if ($db->errno) {
            $msg = $db->error;
            $db->close();
            throw new RuntimeException('sql error: ' . $msg);
        }
    }
    $db->close();
}
