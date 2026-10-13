#!/bin/bash
# ============================================================
# natnode —— NAT VPS 节点对账程序（原创）
#
# 设计原则：
#   * 零常驻进程、零监听端口：由 cron 每分钟触发一次 sync，
#     主动向网站拉取待执行任务 → 执行 → 回报结果与容器状态/流量。
#   * 仅占用磁盘空间；空闲时 CPU/内存开销趋近于零。
#   * 节点不开任何入站管理端口，一切管理经由网站管理后台
#     （管理员登录）+ 每节点独立密钥（X-Node-Key）完成。
#
# 支持动作：create / start / stop / restart / rebuild / delete
# ============================================================
set -u
umask 077
BASE=/opt/natnode
CONF=$BASE/natnode.conf
STATE=$BASE/state
LOGFILE=$BASE/natnode.log
GOLDEN=$BASE/golden
MNT=$BASE/mnt
LXC=/var/lib/lxc

[ -f "$CONF" ] || { echo "natnode: 缺少 $CONF" >&2; exit 1; }
# CONF 内容: SITE_URL=  NODE_KEY=  PUB_IP=  NET_PREFIX=  PORT_GRACE_DAYS=
. "$CONF"
: "${NET_PREFIX:=10.0.3}"
: "${PORT_GRACE_DAYS:=3}"

mkdir -p "$STATE" "$MNT" "$LXC"
: > "$BASE/empty"
exec >> "$LOGFILE" 2>&1
ts()  { date "+%F %T"; }
log() { echo "[$(ts)] $*"; }

api_get()  { curl -sS -m 40 -H "X-Node-Key: $NODE_KEY" "$SITE_URL/api/api.php?f=$1"; }
api_post() { curl -sS -m 40 -H "X-Node-Key: $NODE_KEY" -H "Content-Type: application/json" --data-binary "$2" "$SITE_URL/api/api.php?f=$1"; }

# ---------- 校验（一切输入均为白名单/纯数字，杜绝注入） ----------
valid_name()   { case "$1" in ''|*[!a-z0-9-]*) return 1;; esac; echo "$1" | grep -Eq '^[a-z0-9][a-z0-9-]{2,20}$'; }
# 镜像动态白名单：只要 golden 目录里实际存在该模板即可（名字限小写字母/数字/连字符，杜绝路径穿越）
valid_img()    { case "$1" in ''|*[!a-z0-9-]*) return 1;; esac; [ -d "$GOLDEN/$1" ]; }
valid_num()    { case "$2" in ''|*[!0-9]*) return 1;; esac; [ "$2" -ge "$1" ] 2>/dev/null; }
veth_of()      { echo "nv$(echo -n "$1" | md5sum | cut -c1-10)"; }
cip_of()       { echo "$NET_PREFIX.$1"; }

# ---------- NAT 端口映射（幂等增删，按容器 IP 定位） ----------
add_nat_rules() { # $1=octet $2=port_base $3=port_count
  local ip cip p
  ip=$(cip_of "$1")
  iptables -t nat -C PREROUTING -p tcp --dport "$2" -j DNAT --to-destination "$ip:22" 2>/dev/null || \
    iptables -t nat -A PREROUTING -p tcp --dport "$2" -j DNAT --to-destination "$ip:22"
  local last=$(( $2 + $3 - 1 ))
  for (( p = $2 + 1; p <= last; p++ )); do
    iptables -t nat -C PREROUTING -p tcp --dport "$p" -j DNAT --to-destination "$ip:$p" 2>/dev/null || \
      iptables -t nat -A PREROUTING -p tcp --dport "$p" -j DNAT --to-destination "$ip:$p"
    iptables -t nat -C PREROUTING -p udp --dport "$p" -j DNAT --to-destination "$ip:$p" 2>/dev/null || \
      iptables -t nat -A PREROUTING -p udp --dport "$p" -j DNAT --to-destination "$ip:$p"
  done
}

t_password() { # $1=name $2=rootpw（运行中用 lxc-attach，停止时挂载 loop 盘 chroot 修改）
  local name="$1" pw="$2" M
  valid_name "$name" || return 1
  case "$pw" in ''|*[!a-zA-Z0-9]*) return 1;; esac
  if [ "$(lxc-info -n "$name" -sH 2>/dev/null)" = "RUNNING" ]; then
    lxc-attach -n "$name" -- /bin/bash -c "echo root:$pw | chpasswd" >/dev/null 2>&1
  else
    M="$MNT/$name.pw"
    mkdir -p "$M"
    if mount -o loop "$LXC/$name/disk.img" "$M" 2>/dev/null; then
      chroot "$M" /bin/bash -c "echo root:$pw | chpasswd" >/dev/null 2>&1
      umount "$M"
    fi
    rmdir "$M" 2>/dev/null
  fi
  return 0
}

t_nat() { # $1=name $2=octet $3=port_base $4=port_count $5=rulesJSON（全量重建该实例 DNAT）
  local name="$1" octet="$2" pbase="$3" pcount="$4" rules="$5"
  valid_name "$name" || return 1
  valid_num 2 "$octet" && [ "$octet" -le 254 ] || return 1
  valid_num 1024 "$pbase" || return 1
  [ "$pcount" -ge 1 ] && [ "$pcount" -le 64 ] || return 1
  del_nat_rules "$octet"
  local ip
  ip=$(cip_of "$octet")
  iptables -t nat -A PREROUTING -p tcp --dport "$pbase" -j DNAT --to-destination "$ip:22"
  local port to proto
  while read -r port to proto; do
    [ -n "$port" ] || continue
    case "$port" in ''|*[!0-9]*) continue;; esac
    case "$to" in ''|*[!0-9]*) continue;; esac
    [ "$port" -ge $((pbase + 1)) ] && [ "$port" -le $((pbase + pcount - 1)) ] || continue
    [ "$to" -ge 1 ] && [ "$to" -le 65535 ] || continue
    case "$proto" in tcp|udp|both) ;; *) proto=both;; esac
    if [ "$proto" = tcp ] || [ "$proto" = both ]; then
      iptables -t nat -A PREROUTING -p tcp --dport "$port" -j DNAT --to-destination "$ip:$to"
    fi
    if [ "$proto" = udp ] || [ "$proto" = both ]; then
      iptables -t nat -A PREROUTING -p udp --dport "$port" -j DNAT --to-destination "$ip:$to"
    fi
  done < <(printf '%s' "$rules" | jq -r '.[] | "\(.port) \(.to) \(.proto // "both")"' 2>/dev/null)
  return 0
}

del_nat_rules() { # $1=octet
  local ip
  ip=$(cip_of "$1")
  iptables-save -t nat | grep -- "--to-destination $ip:" | sed 's/^-A /-D /' | while read -r r; do
    iptables -t nat $r 2>/dev/null || true
  done
}

# ---------- 根文件系统准备（loop 镜像 = 硬性磁盘限额） ----------
build_rootfs() { # $1=name $2=img $3=disk_gb $4=rootpw
  local name="$1" img="$2" dgb="$3" pw="$4"
  case "$pw" in ''|*[!a-zA-Z0-9]*) echo "root 密码需为字母数字"; return 1;; esac
  local D="$LXC/$name" M="$MNT/$name"
  rm -rf "$D"; mkdir -p "$D"
  truncate -s "${dgb}G" "$D/disk.img"
  mkfs.ext4 -q -F -E lazy_itable_init=1,lazy_journal_init=1 "$D/disk.img" || return 1
  mkdir -p "$M"
  mount -o loop "$D/disk.img" "$M" || return 1
  tar -C "$GOLDEN/$img" --exclude=./proc --exclude=./sys --exclude=./dev --exclude=./run -cf - . | tar -C "$M" -xf - || { umount "$M"; return 1; }
  mkdir -p "$M/etc/systemd/network" "$M/proc" "$M/sys" "$M/dev" "$M/run"
  # 每台实例全新 SSH host key：先删模板自带 keys，chroot 内 ssh-keygen -A 重新生成
  rm -f "$M"/etc/ssh/ssh_host_* 2>/dev/null
  printf 'nameserver %s.1\nnameserver 8.8.8.8\n' "$NET_PREFIX" > "$M/etc/resolv.conf"
  echo "$name" > "$M/etc/hostname"
  printf '127.0.0.1 localhost\n%s.%s %s\n' "$NET_PREFIX" "${ip_octet:-0}" "$name" > "$M/etc/hosts"
  mount -t proc proc "$M/proc"
  chroot "$M" /bin/bash -c "ssh-keygen -A >/dev/null 2>&1; echo root:$pw | chpasswd; systemctl enable ssh >/dev/null 2>&1 || true"
  # machine-id 置空：systemd 首次启动自动生成每台实例唯一的机器标识（golden 克隆不共享身份）
  : > "$M/etc/machine-id"
  umount "$M/proc" 2>/dev/null
  umount "$M" || return 1
  rmdir "$M" 2>/dev/null
  return 0
}

write_config() { # $1=name $2=octet $3=cpu_pct $4=mem_mb
  local name="$1" octet="$2" cpupct="$3" memmb="$4"
  local D="$LXC/$name"
  local mac="fe:$(echo -n "$name" | md5sum | cut -c1-2 | sed 's/../&:/;s/:$//')$(echo -n "$name" | md5sum | cut -c3-10 | sed 's/../&:/g;s/:$//')"
  # CPU 绑核：按内网尾号轮转分配物理核，使容器内 nproc=套餐核数
  local cores=$(( cpupct / 100 )); [ "$cores" -lt 1 ] && cores=1
  local hc; hc=$(nproc); [ "$cores" -gt "$hc" ] && cores=$hc
  local cstart=$(( (octet - 2) % hc ))
  cpuset=""
  local ci
  for (( ci = 0; ci < cores; ci++ )); do
    cpuset="$cpuset$(( (cstart + ci) % hc ))"
    [ $(( ci + 1 )) -lt "$cores" ] && cpuset="$cpuset,"
  done
  cat > "$D/config" <<EOC
lxc.uts.name = $name
lxc.arch = amd64
lxc.rootfs.path = loop:$D/disk.img
lxc.net.0.type = veth
lxc.net.0.link = lxcbr0
lxc.net.0.flags = up
lxc.net.0.veth.pair = $(veth_of "$name")
lxc.net.0.hwaddr = $mac
lxc.net.0.ipv4.address = $(cip_of "$octet")/24
lxc.net.0.ipv4.gateway = $NET_PREFIX.1
lxc.cgroup2.memory.max = ${memmb}M
lxc.cgroup2.memory.swap.max = 0
lxc.cgroup2.cpu.max = $(( cpupct * 1000 )) 100000
lxc.tty.max = 1
lxc.pty.max = 32
lxc.autodev = 1
lxc.apparmor.profile = generated
lxc.mount.auto = cgroup:mixed proc:mixed sys:mixed
lxc.cap.drop = sys_module sys_time mac_admin mac_override
lxc.cgroup2.cpuset.cpus = $cpuset
lxc.mount.entry = /var/lib/lxcfs/proc/cpuinfo proc/cpuinfo none bind,optional 0 0
lxc.mount.entry = /var/lib/lxcfs/proc/meminfo proc/meminfo none bind,optional 0 0
lxc.mount.entry = /var/lib/lxcfs/proc/uptime proc/uptime none bind,optional 0 0
lxc.mount.entry = /var/lib/lxcfs/proc/diskstats proc/diskstats none bind,optional 0 0
lxc.mount.entry = /var/lib/lxcfs/proc/loadavg proc/loadavg none bind,optional 0 0
lxc.mount.entry = /var/lib/lxcfs/proc/swaps proc/swaps none bind,optional 0 0
lxc.mount.entry = /opt/natnode/empty proc/partitions none bind,optional 0 0
lxc.mount.entry = tmpfs sys/class/block tmpfs ro,nosuid,nodev,noexec,optional 0 0
lxc.mount.entry = tmpfs sys/block tmpfs ro,nosuid,nodev,noexec,optional 0 0
EOC
}

container_up() { # 等待容器进入 RUNNING，$1=name $2=最多秒数
  local i=0
  while [ $i -lt "$2" ]; do
    [ "$(lxc-info -n "$1" -sH 2>/dev/null)" = "RUNNING" ] && return 0
    sleep 1; i=$((i+1))
  done
  return 1
}

# ---------- 任务动作 ----------
t_create() { # create|rebuild 共用；$1..= name img cpu_pct mem_mb disk_gb octet pbase pcount rootpw
  local name="$1" img="$2" cpupct="$3" memmb="$4" dgb="$5" octet="$6" pbase="$7" pcount="$8" pw="$9"
  valid_name "$name" || { echo "容器名不合法"; return 1; }
  valid_img "$img" || { echo "镜像不在白名单"; return 1; }
  valid_num 1 "$cpupct" && [ "$cpupct" -le 400 ] || { echo "CPU 配置不合法"; return 1; }
  valid_num 64 "$memmb" || { echo "内存配置不合法"; return 1; }
  valid_num 1 "$dgb" || { echo "磁盘配置不合法"; return 1; }
  valid_num 2 "$octet" && [ "$octet" -le 254 ] || { echo "内网地址不合法"; return 1; }
  valid_num 1024 "$pbase" || { echo "端口段不合法"; return 1; }
  valid_num 1 "$pcount" && [ "$pcount" -le 64 ] || { echo "端口数量不合法"; return 1; }
  ip_octet=$octet
  build_rootfs "$name" "$img" "$dgb" "$pw" || { echo "根文件系统构建失败"; return 1; }
  write_config "$name" "$octet" "$cpupct" "$memmb"
  lxc-start -n "$name" -d 2>/dev/null || { echo "容器启动失败"; return 1; }
  container_up "$name" 30 || { echo "容器未进入运行状态"; return 1; }
  add_nat_rules "$octet" "$pbase" "$pcount"
  printf '0 0 0 0' > "$STATE/$name.traffic"
  return 0
}

t_start() {
  valid_name "$1" || return 1
  lxc-start -n "$1" -d 2>/dev/null; container_up "$1" 30
}
t_stop() {
  valid_name "$1" || return 1
  lxc-stop -n "$1" -t 12 2>/dev/null || lxc-stop -n "$1" -k 2>/dev/null
  [ "$(lxc-info -n "$1" -sH 2>/dev/null)" != "RUNNING" ]
}
t_restart() { t_stop "$1" && t_start "$1"; }
t_rebuild() { # 参数同 create
  local name="$1"; shift
  valid_name "$name" || return 1
  lxc-stop -n "$name" -k 2>/dev/null
  t_create "$name" "$@" && container_up "$name" 20
}
t_delete() {
  local name="$1" octet="$2"
  valid_name "$name" || return 1
  [ -f "$STATE/$name.console" ] && { pkill -f "lxc-attach -n $name --" 2>/dev/null; rm -f "$STATE/$name.console"; }
  lxc-stop -n "$name" -k 2>/dev/null
  sleep 1
  umount "$MNT/$name" 2>/dev/null; umount "$LXC/$name/rootfs" 2>/dev/null
  losetup -D 2>/dev/null || true
  rm -rf "$LXC/$name" && rm -f "$STATE/$name.traffic" && rm -rf "$MNT/$name"
  [ -n "$octet" ] && valid_num 2 "$octet" && del_nat_rules "$octet"
  return 0
}

# ---------- 双向流量计量（宿主 veth 计数，容器重启累计不丢） ----------
# state 文件格式: 累计rx 累计tx 快照rx 快照tx （字节）
traffic_json() {
  local f name v snaprx snaptx rx tx drx dtx first=1
  printf '{'
  for f in "$STATE"/*.traffic; do
    [ -f "$f" ] || continue
    name=$(basename "$f" .traffic)
    v=$(veth_of "$name")
    read -r treg ttx snaprx snaptx < "$f"
    rx=0; tx=0
    [ -r "/sys/class/net/$v/statistics/rx_bytes" ] && rx=$(cat "/sys/class/net/$v/statistics/rx_bytes")
    [ -r "/sys/class/net/$v/statistics/tx_bytes" ] && tx=$(cat "/sys/class/net/$v/statistics/tx_bytes")
    if [ "$rx" -ge "$snaprx" ]; then drx=$((rx - snaprx)); else drx=$rx; fi
    if [ "$tx" -ge "$snaptx" ]; then dtx=$((tx - snaptx)); else dtx=$tx; fi
    treg=$((treg + drx)); ttx=$((ttx + dtx))
    printf '%s"%s":{"rx":%d,"tx":%d}' "$([ $first = 1 ] || echo ,)" "$name" "$treg" "$ttx"
    first=0
    printf '%d %d %d %d' "$treg" "$ttx" "$rx" "$tx" > "$f"
  done
  printf '}'
}

# ---------- 容器状态 ----------
states_json() {
  local f name st first=1
  printf '{'
  for f in "$STATE"/*.traffic; do
    [ -f "$f" ] || continue
    name=$(basename "$f" .traffic)
    st=$(lxc-info -n "$name" -sH 2>/dev/null || echo ABSENT)
    printf '%s"%s":"%s"' "$([ $first = 1 ] || echo ,)" "$name" "$st"
    first=0
  done
  printf '}'
}

# ---------- 可用镜像（golden 目录实际存在的模板，供网站后台/前台展示） ----------
images_json() {
  local arr="[]" d
  for d in "$GOLDEN"/*/; do
    [ -d "$d" ] || continue
    arr=$(printf '%s' "$arr" | jq -c --arg n "$(basename "$d")" '. + [$n]')
  done
  printf '%s' "$arr"
}

# ---------- 节点资源指标（负载/内存/磁盘/运行容器数，随对账上报） ----------
metrics_json() {
  local load1 mem_total mem_avail disk_total disk_used running
  read -r load1 _ < /proc/loadavg 2>/dev/null || load1=0
  mem_total=$(awk '/^MemTotal/ {print int($2/1024)}' /proc/meminfo 2>/dev/null)
  mem_avail=$(awk '/^MemAvailable/ {print int($2/1024)}' /proc/meminfo 2>/dev/null)
  disk_total=$(df -BG "$LXC" 2>/dev/null | awk 'NR==2 {print int($2)}')
  disk_used=$(df -BG "$LXC" 2>/dev/null | awk 'NR==2 {print int($3)}')
  running=$(lxc-ls --running 2>/dev/null | wc -w)
  case "$load1" in ''|*[!0-9.]*) load1=0;; esac
  case "$mem_total" in ''|*[!0-9]*) mem_total=0;; esac
  case "$mem_avail" in ''|*[!0-9]*) mem_avail=0;; esac
  case "$disk_total" in ''|*[!0-9]*) disk_total=0;; esac
  case "$disk_used" in ''|*[!0-9]*) disk_used=0;; esac
  case "$running" in ''|*[!0-9]*) running=0;; esac
  jq -cn --argjson l1 "$load1" --argjson mt "$mem_total" --argjson ma "$mem_avail" \
    --argjson dt "$disk_total" --argjson du "$disk_used" --argjson run "$running" \
    '{load1:$l1, mem_total_mb:$mt, mem_avail_mb:$ma, disk_total_gb:$dt, disk_used_gb:$du, containers_running:$run}'
}


# ---------- Web 控制台（ttyd + 容器 root shell，一次性凭据 + TTL 自毁） ----------
t_console() { # $1=name $2=port $3=token $4=ttl_sec
  local name="$1" port="$2" token="$3" ttl="${4:-900}"
  valid_name "$name" || return 1
  valid_num 1024 "$port" && [ "$port" -le 65535 ] || return 1
  valid_num 30 "$ttl" && [ "$ttl" -le 86400 ] || return 1
  case "$token" in ''|*[!a-zA-Z0-9]*) return 1;; esac
  command -v ttyd >/dev/null 2>&1 || return 1
  # 杀掉该端口上的旧控制台（timeout 包装进程与 ttyd 进程命令行都含此串），确保新凭据生效。
  # 不能依赖 PID 文件：setsid fork 后记录的 PID 并非 ttyd 实际进程，旧进程会一直占用端口。
  pkill -f "ttyd -W -p $port " 2>/dev/null || true
  sleep 0.5
  local pf="$STATE/$name.console"
  setsid nohup timeout "$ttl" ttyd -W -p "$port" -c "nc:$token" lxc-attach -n "$name" -- /bin/bash --login >/dev/null 2>&1 < /dev/null 9>&- &
  echo $! > "$pf"
  return 0
}

# ---------- 主流程：sync ----------
cmd_sync() {
  # 防并发：上一轮 sync 未结束时本轮 cron 直接跳过（避免 iptables/loop 设备竞争与任务重复执行）
  exec 9>/var/lock/natnode.sync.lock
  flock -n 9 || { log "上一轮 sync 仍在进行，本轮跳过"; exit 0; }
  # 日志上限：超过 5MB 时保留尾部 1MB（防无限增长）
  if [ -f "$LOGFILE" ] && [ "$(stat -c%s "$LOGFILE" 2>/dev/null || echo 0)" -gt $((5 * 1024 * 1024)) ]; then
    tail -c 1048576 "$LOGFILE" > "$LOGFILE.tmp" && mv "$LOGFILE.tmp" "$LOGFILE"
  fi
  log "sync 开始"
  local resp ok
  resp=$(api_get node_sync)
  ok=$(echo "$resp" | jq -r '.ok // false' 2>/dev/null)
  if [ "$ok" != "true" ]; then log "sync 拉取失败: $(echo "$resp" | head -c 200)"; return 1; fi

  local results="[]"
  local tid action p
  while read -r tid; do
    [ -n "$tid" ] || continue
    p=$(echo "$resp" | jq -c --argjson id "$tid" '.data.tasks[] | select(.id == $id)')
    action=$(echo "$p" | jq -r '.action')
    lname=$(echo "$p" | jq -r '.payload.name // empty')
    case "$lname" in ''|*[!a-zA-Z0-9-]*) lname="?";; esac
    log "执行任务 #$tid $action $lname"
    local okk=0 detail=""
    local name img cpupct memmb dgb octet pbase pcount pw
    name=$(echo "$p" | jq -r '.payload.name // empty')
    case "$action" in
      create|rebuild)
        img=$(echo "$p" | jq -r '.payload.img');        cpupct=$(echo "$p" | jq -r '.payload.cpu_pct')
        memmb=$(echo "$p" | jq -r '.payload.mem_mb');   dgb=$(echo "$p" | jq -r '.payload.disk_gb')
        octet=$(echo "$p" | jq -r '.payload.ip_octet'); pbase=$(echo "$p" | jq -r '.payload.port_base')
        pcount=$(echo "$p" | jq -r '.payload.port_count'); pw=$(echo "$p" | jq -r '.payload.rootpw')
        if t_create "$name" "$img" "$cpupct" "$memmb" "$dgb" "$octet" "$pbase" "$pcount" "$pw"; then
          okk=1; detail="$PUB_IP:$pbase"
        else
          detail="开通失败，请查看节点日志"; t_delete "$name" "$octet" 2>/dev/null || true
        fi
        ;;
      start)    t_start "$name"   && okk=1 || detail="启动失败" ;;
      stop)     t_stop "$name"    && okk=1 || detail="停止失败" ;;
      restart)  t_restart "$name" && okk=1 || detail="重启失败" ;;
      delete)   octet=$(echo "$p" | jq -r '.payload.ip_octet // empty'); t_delete "$name" "$octet" && okk=1 || detail="删除失败" ;;
      password) t_password "$name" "$(echo "$p" | jq -r '.payload.rootpw')" && okk=1 || detail="重置密码失败" ;;
      console)
        cport=$(echo "$p" | jq -r '.payload.port')
        ctoken=$(echo "$p" | jq -r '.payload.token')
        cttl=$(echo "$p" | jq -r '.payload.ttl // 900')
        if t_console "$name" "$cport" "$ctoken" "$cttl"; then
          okk=1
        else
          detail="控制台启动失败"
        fi ;;
      nat)
        octet=$(echo "$p" | jq -r '.payload.ip_octet')
        pbase=$(echo "$p" | jq -r '.payload.port_base')
        pcount=$(echo "$p" | jq -r '.payload.port_count')
        if t_nat "$name" "$octet" "$pbase" "$pcount" "$(echo "$p" | jq -c '.payload.rules // []')"; then
          okk=1
        else
          detail="端口转发更新失败"
        fi ;;
      *) detail="未知动作" ;;
    esac
    results=$(jq -cn --argjson arr "$results" --argjson id "$tid" --argjson ok "$okk" --arg d "$detail" '$arr + [{id:$id, ok:(if $ok==1 then true else false end), detail:$d}]')
    log "任务 #$tid $action => ok=$okk $detail"
  done < <(echo "$resp" | jq -r '.data.tasks[]?.id')

  local body
  body=$(jq -cn --argjson tasks "$results" --argjson stats "$(traffic_json)" --argjson states "$(states_json)" --argjson images "$(images_json)" --argjson metrics "$(metrics_json)" '{tasks:$tasks, traffic:$stats, states:$states, images:$images, metrics:$metrics}')
  local rp
  rp=$(api_post node_report "$body")
  log "sync 完成：任务 $(echo "$results" | jq 'length') 个，回报 $(echo "$rp" | head -c 80)"
}

case "${1:-help}" in
  sync)   cmd_sync ;;
  password) shift; t_password "$1" "$2" ;;
  console) shift; t_console "$1" "$2" "$3" "$4" ;;
  nat) shift; t_nat "$1" "$2" "$3" "$4" "$5" ;;
  create) shift; t_create "$@" ;;
  start)  t_start "$2" ;;
  stop)   t_stop "$2" ;;
  delete) t_delete "$2" "${3:-}" ;;
  *) echo "用法: natnode.sh sync|create|start|stop|delete" ;;
esac
