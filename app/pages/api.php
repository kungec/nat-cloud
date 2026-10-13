<?php
declare(strict_types=1);

/* NAT Cloud - API：前端取价 + 节点对账接口（X-Node-Key 认证，机器对机器） */

function api_price(): void
{
    $stmt = db()->prepare('SELECT id, name, node_name, region, cpu, ram_mb, disk_gb, monthly_price, hourly_price, traffic_gb, bandwidth_mbps, stock, nat_type, badge FROM plans WHERE status = 1 ORDER BY sort ASC, id ASC');
    $stmt->execute();
    $plans = $stmt->fetchAll();
    json_out(['ok' => true, 'plans' => $plans]);
}

/* ---------- 节点对账 ---------- */

function api_node_auth(): ?array
{
    $key = $_SERVER['HTTP_X_NODE_KEY'] ?? '';
    if (!is_string($key) || !preg_match('~^[a-f0-9]{32,64}$~', $key)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM nodes WHERE node_key = ? AND status = 1 LIMIT 1');
    $stmt->execute([$key]);
    return $stmt->fetch() ?: null;
}

function api_node(): void
{
    $f = $_GET['f'] ?? '';
    if ($f === 'node_install') {
        api_node_install();
    }
    if ($f === 'node_agent') {
        api_node_agent();
    }
    if ($f === 'node_sync') {
        api_node_sync();
    }
    if ($f === 'node_report') {
        api_node_report();
    }
    json_out(['ok' => false, 'msg' => 'unknown function']);
}

/* 按 key 查节点（含停用节点——安装阶段可能尚未启用） */
function api_node_by_key(string $key): ?array
{
    if (!preg_match('~^[a-f0-9]{32,64}$~', $key)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM nodes WHERE node_key = ? LIMIT 1');
    $stmt->execute([$key]);
    return $stmt->fetch() ?: null;
}

/* 安装器程序本体（natnode.sh） */
function api_node_agent(): void
{
    $node = api_node_by_key((string) ($_GET['key'] ?? ''));
    if (!$node) {
        json_out(['ok' => false, 'msg' => 'auth failed'], 403);
    }
    $file = NC_ROOT . '/app/node_install/natnode.sh';
    if (!is_file($file)) {
        json_out(['ok' => false, 'msg' => 'agent missing'], 404);
    }
    header('Content-Type: text/x-shellscript; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo file_get_contents($file);
    exit;
}

/* 一键安装器：按节点配置动态生成（依赖安装 + conf + 程序 + cron + 自检） */
function api_node_install(): void
{
    $node = api_node_by_key((string) ($_GET['key'] ?? ''));
    if (!$node) {
        json_out(['ok' => false, 'msg' => 'auth failed'], 403);
    }
    $site = site_base_url();
    $key = (string) ($_GET['key'] ?? '');
    $pubIp = (string) $node['pub_ip'];
    $netPrefix = (string) $node['net_prefix'];
    $script = <<<'INSTALLER'
#!/bin/bash
# ============================================================
# natnode 节点一键安装（由管理后台按节点配置生成）
# 用法：在全新 Ubuntu 20+ / Debian 11+ 服务器上以 root 执行
# 完成：依赖安装 → 节点配置 → 程序部署 → 分钟级对账 → golden 模板
# ============================================================
set -e
SITE_URL="__SITE_URL__"
NODE_KEY="__NODE_KEY__"
PUB_IP="__PUB_IP__"
NET_PREFIX="__NET_PREFIX__"

[ "$(id -u)" = "0" ] || { echo "请以 root 身份执行本脚本"; exit 1; }
echo "== [1/6] 系统检测 =="
. /etc/os-release 2>/dev/null || { echo "无法识别系统"; exit 1; }
case "${ID:-}" in ubuntu|debian) ;; *) echo "仅支持 Ubuntu / Debian（apt 系）"; exit 1;; esac
echo "系统：${PRETTY_NAME:-$ID}"

echo "== [2/6] 安装依赖（lxc / lxcfs / jq / curl / iptables / cron）=="
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq curl jq lxc lxcfs iptables cron debootstrap >/dev/null
command -v lxc-start >/dev/null || { echo "lxc 安装失败"; exit 1; }
command -v jq >/dev/null || { echo "jq 安装失败"; exit 1; }
systemctl enable --now lxcfs >/dev/null 2>&1 || true

echo "== [3/6] 写入节点配置 =="
mkdir -p /opt/natnode
cat > /opt/natnode/natnode.conf <<NODECONF
SITE_URL="$SITE_URL"
NODE_KEY="$NODE_KEY"
PUB_IP="$PUB_IP"
NET_PREFIX="$NET_PREFIX"
NODECONF
chmod 600 /opt/natnode/natnode.conf

echo "== [4/6] 部署节点程序 =="
curl -fsS -m 60 -H "X-Node-Key: $NODE_KEY" "$SITE_URL/api/api.php?f=node_agent&key=$NODE_KEY" -o /opt/natnode/natnode.sh
chmod 700 /opt/natnode/natnode.sh
bash -n /opt/natnode/natnode.sh || { echo "程序完整性校验失败"; exit 1; }

echo "== [5/6] 注册分钟级对账任务 =="
( crontab -l 2>/dev/null | grep -v 'natnode.sh sync'; echo '* * * * * /opt/natnode/natnode.sh sync >/dev/null 2>&1' ) | crontab -

echo "== [6/6] golden 模板（debian12，首次约 3-10 分钟）=="
golden_build() { # $1=目标镜像名（默认 debian12）。任一环节失败只返回非零，不中断安装
  local img="${1:-debian12}"
  local G="/opt/natnode/golden/$img"
  if [ -x "$G/bin/sh" ]; then echo "golden $img 已存在，跳过"; return 0; fi
  if [ -d "$G" ]; then echo "清除残缺的旧模板目录..."; rm -rf "$G"; fi
  mkdir -p /opt/natnode/golden
  lxc-destroy -n nc-gbuild >/dev/null 2>&1 || true

  # 方法一：官方模板源（lxc-download，自带签名校验关闭的快速通道）
  echo "尝试源 1：images.linuxcontainers.org ..."
  if lxc-create -n nc-gbuild -t download -- --no-validate -d debian -r bookworm -a amd64 >/dev/null 2>&1; then
    mkdir -p "$G"
    cp -a /var/lib/lxc/nc-gbuild/rootfs/. "$G"/ || { lxc-destroy -n nc-gbuild >/dev/null 2>&1 || true; return 1; }
    lxc-destroy -n nc-gbuild >/dev/null 2>&1 || true
  else
    # 方法二：debootstrap 自动换 Debian 镜像源（http 即可，debootstrap 用 GPG 验证仓库签名）
    command -v debootstrap >/dev/null || apt-get install -y -qq debootstrap >/dev/null
    local R="/opt/natnode/.gbuild-root" mir ok=""
    for mir in deb.debian.org/debian mirrors.aliyun.com/debian mirrors.tencent.com/debian mirrors.ustc.edu.cn/debian; do
      echo "尝试源：http://$mir ..."
      rm -rf "$R"
      if debootstrap --arch=amd64 bookworm "$R" "http://$mir" >/dev/null 2>&1 && [ -x "$R/bin/bash" ]; then
        ok=1
        echo "基础系统就绪（源：$mir），安装 openssh-server 等基础包..."
        mount --bind /proc "$R/proc" 2>/dev/null || true
        if chroot "$R" /bin/bash -c "echo 'deb http://$mir bookworm main' > /etc/apt/sources.list && env DEBIAN_FRONTEND=noninteractive apt-get update -qq && env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq openssh-server systemd-sysv sudo curl wget ca-certificates vim-tiny"; then
          :
        else
          ok=""
        fi
        umount "$R/proc" 2>/dev/null || true
        [ -n "$ok" ] && break
      fi
    done
    if [ -z "$ok" ]; then rm -rf "$R"; return 1; fi
    mkdir -p "$G"
    cp -a "$R"/. "$G"/ || { rm -rf "$R"; return 1; }
    rm -rf "$R"
  fi
  # 模板卫生：不留机器身份与网络配置（实例身份由 natnode 首启重建，IP 由 LXC 配置层注入）
  : > "$G/etc/machine-id"
  rm -f "$G"/etc/ssh/ssh_host_* 2>/dev/null || true
  rm -f "$G"/etc/network/interfaces.d/* 2>/dev/null || true
  rm -f "$G"/etc/systemd/network/* 2>/dev/null || true
  rm -rf "$G"/var/cache/apt/archives/*.deb "$G"/var/lib/apt/lists/* 2>/dev/null || true
  return 0
}
if [ "${AUTO_GOLDEN:-1}" = "1" ]; then
  golden_build debian12 && echo "golden debian12 就绪" || echo "警告：golden 自动生成失败（官方源与镜像源均已尝试），安装继续；稍后把模板 rootfs 内容放到 /opt/natnode/golden/debian12/ 即自动生效"
fi

echo "== 首次对账自检 =="
/opt/natnode/natnode.sh sync >/dev/null 2>&1 || true
echo "安装完成！约 1 分钟内节点会出现在管理后台并自动上报可用镜像。"
echo "如需更换系统模板：把 rootfs 内容放到 /opt/natnode/golden/<镜像名>/（bin/etc/usr 直接位于该目录下）即可被自动识别。"
INSTALLER;

    $script = str_replace(
        ['__SITE_URL__', '__NODE_KEY__', '__PUB_IP__', '__NET_PREFIX__', '__SITE__'],
        [$site, $key, $pubIp, $netPrefix, $site],
        $script
    );
    header('Content-Type: text/x-shellscript; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo $script;
    exit;
}

function api_node_sync(): void
{
    $node = api_node_auth();
    if (!$node) {
        json_out(['ok' => false, 'msg' => 'auth failed'], 403);
    }
    /* 超过10分钟仍未回报的 sent 任务重新入队（节点执行中崩溃的自愈） */
    db_exec("UPDATE node_tasks SET status = 'pending' WHERE node_id = ? AND status = 'sent' AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)", [$node['id']]);

    $stmt = db()->prepare("SELECT id, action, payload FROM node_tasks WHERE node_id = ? AND status = 'pending' ORDER BY id ASC LIMIT 20");
    $stmt->execute([$node['id']]);
    $rows = $stmt->fetchAll();
    $tasks = [];
    $ids = [];
    foreach ($rows as $r) {
        $payload = json_decode($r['payload'], true);
        if (!is_array($payload)) {
            $payload = new stdClass();
        }
        $tasks[] = ['id' => (int) $r['id'], 'action' => $r['action'], 'payload' => $payload];
        $ids[] = (int) $r['id'];
    }
    if ($ids) {
        /* 标记为已下发，防止拉取与回报之间被重复取走 */
        $place = implode(',', array_fill(0, count($ids), '?'));
        db_exec("UPDATE node_tasks SET status = 'sent' WHERE id IN ($place) AND status = 'pending'", $ids);
    }
    json_out(['ok' => true, 'data' => ['tasks' => $tasks]]);
}

function api_node_report(): void
{
    $node = api_node_auth();
    if (!$node) {
        json_out(['ok' => false, 'msg' => 'auth failed'], 403);
    }
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        json_out(['ok' => false, 'msg' => 'bad json'], 400);
    }
    $nid = (int) $node['id'];

    /* 任务结果 */
    foreach (($body['tasks'] ?? []) as $t) {
        if (!is_array($t) || !isset($t['id'])) {
            continue;
        }
        $ok = !empty($t['ok']) ? 'done' : 'failed';
        $detail = mb_substr((string) ($t['detail'] ?? ''), 0, 180);
        $stmt = db()->prepare("UPDATE node_tasks SET status = ?, result_detail = ?, completed_at = NOW() WHERE id = ? AND node_id = ? AND status = 'sent'");
        $stmt->execute([$ok, $detail, (int) $t['id'], $nid]);

        if ($ok !== 'done') {
            continue;
        }
        /* create/rebuild 成功：实例进入运行状态 */
        $stmt = db()->prepare('SELECT action, payload FROM node_tasks WHERE id = ? AND node_id = ? LIMIT 1');
        $stmt->execute([(int) $t['id'], $nid]);
        $task = $stmt->fetch();
        if ($task && in_array($task['action'], ['create', 'rebuild'], true)) {
            $p = json_decode($task['payload'], true);
            if (is_array($p) && !empty($p['name'])) {
                db_exec("UPDATE instances SET status = 'running', node_state = 'RUNNING' WHERE name = ? AND node_id = ? AND status = 'creating'", [$p['name'], $nid]);
            }
        }
    }

    /* 流量（累计字节，上下行均计费） */
    foreach (($body['traffic'] ?? []) as $name => $t) {
        if (!is_array($t) || !preg_match('~^[a-z0-9][a-z0-9-]{2,20}$~', (string) $name)) {
            continue;
        }
        $stmt = db()->prepare('UPDATE instances SET traffic_rx = ?, traffic_tx = ? WHERE name = ? AND node_id = ?');
        $stmt->execute([(int) ($t['rx'] ?? 0), (int) ($t['tx'] ?? 0), $name, $nid]);
    }

    /* 容器状态 */
    foreach (($body['states'] ?? []) as $name => $st) {
        if (!preg_match('~^[a-z0-9][a-z0-9-]{2,20}$~', (string) $name) || !is_string($st)) {
            continue;
        }
        $stmt = db()->prepare("UPDATE instances SET node_state = ? WHERE name = ? AND node_id = ?");
        $stmt->execute([mb_substr($st, 0, 15), $name, $nid]);
    }

    /* 节点实际可用镜像（natnode 上报 golden 目录），存入 nodes.images */
    if (isset($body['images']) && is_array($body['images'])) {
        $imgs = [];
        foreach ($body['images'] as $im) {
            if (is_string($im) && preg_match('~^[a-z0-9][a-z0-9-]{0,31}$~', $im)) {
                $imgs[] = $im;
            }
            if (count($imgs) >= 24) {
                break;
            }
        }
        $imgs = array_values(array_unique($imgs));
        $imgsJson = json_encode($imgs) ?: '[]';
        try {
            $stmt = db()->prepare('UPDATE nodes SET images = ? WHERE id = ?');
            $stmt->execute([$imgsJson, $nid]);
        } catch (Throwable $e) {
            /* 首次部署自愈：补齐 nodes.images 列后重试 */
            db_exec('ALTER TABLE nodes ADD COLUMN images TEXT NULL');
            $stmt = db()->prepare('UPDATE nodes SET images = ? WHERE id = ?');
            $stmt->execute([$imgsJson, $nid]);
        }
    }

    /* 心跳时间与资源指标（状态页/管理后台在线判定与展示用） */
    $metrics = is_array($body['metrics'] ?? null) ? $body['metrics'] : null;
    $metricsJson = $metrics ? json_encode($metrics, JSON_UNESCAPED_UNICODE) : null;
    try {
        $stmt = db()->prepare('UPDATE nodes SET last_seen = NOW(), metrics_json = ? WHERE id = ?');
        $stmt->execute([$metricsJson, $nid]);
    } catch (Throwable $e) {
        /* 首次部署自愈：补齐 nodes.last_seen / nodes.metrics_json 列后重试 */
        try {
            db_exec('ALTER TABLE nodes ADD COLUMN last_seen DATETIME NULL');
            db_exec('ALTER TABLE nodes ADD COLUMN metrics_json TEXT NULL');
            $stmt = db()->prepare('UPDATE nodes SET last_seen = NOW(), metrics_json = ? WHERE id = ?');
            $stmt->execute([$metricsJson, $nid]);
        } catch (Throwable $e2) {
            /* ignore */
        }
    }

    json_out(['ok' => true]);
}
