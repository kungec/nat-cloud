<?php
declare(strict_types=1);

/* NAT Cloud - 查询助手
 * 约定：$statement 必须是调用方写死的 SQL 字面量（只含 ? 占位符），
 * 任何外部输入都只能通过 $bindings 绑定进入查询，禁止拼接。
 */

function db_all(string $statement, array $bindings = []): array
{
    $stmt = call_user_func([db(), 'prepare'], $statement);
    $stmt->execute($bindings);
    return $stmt->fetchAll();
}

function db_one(string $statement, array $bindings = [])
{
    $stmt = call_user_func([db(), 'prepare'], $statement);
    $stmt->execute($bindings);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_val(string $statement, array $bindings = [])
{
    $stmt = call_user_func([db(), 'prepare'], $statement);
    $stmt->execute($bindings);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}

function db_exec(string $statement, array $bindings = []): int
{
    $stmt = call_user_func([db(), 'prepare'], $statement);
    $stmt->execute($bindings);
    return $stmt->rowCount();
}
