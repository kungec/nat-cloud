-- NAT Cloud 全量数据库结构 (MySQL 5.7+, utf8mb4)
-- 与生产库结构一致，含节点对接/实名/流量/VNC 等全部字段，可重复执行
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `auth_failures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account` varchar(140) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_af` (`account`,`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `coupons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'percent',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quota` int(11) NOT NULL DEFAULT 1,
  `used` int(11) NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` int(10) unsigned NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  `order_no` varchar(32) NOT NULL DEFAULT '',
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cr` (`coupon_id`,`uid`),
  KEY `idx_cr_coupon` (`coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_verifies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `email` varchar(120) NOT NULL DEFAULT '',
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ev_token` (`token`),
  KEY `idx_ev_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `instances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(40) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'running',
  `billing_mode` varchar(10) NOT NULL DEFAULT 'monthly',
  `node_name` varchar(64) NOT NULL DEFAULT '',
  `region` varchar(30) NOT NULL DEFAULT '东京',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `nat_ports` varchar(60) NOT NULL DEFAULT '',
  `ssh_port` int(11) NOT NULL DEFAULT 0,
  `cpu` int(11) NOT NULL DEFAULT 1,
  `ram_mb` int(11) NOT NULL DEFAULT 1024,
  `disk_gb` int(11) NOT NULL DEFAULT 10,
  `traffic_gb` int(11) NOT NULL DEFAULT 1000,
  `bandwidth_mbps` int(11) NOT NULL DEFAULT 100,
  `os` varchar(40) NOT NULL DEFAULT 'Ubuntu 24.04',
  `hourly_price` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `monthly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `forwarded_ports` text,
  `last_billed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_at` datetime DEFAULT NULL,
  `stopped_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `node_id` int(10) unsigned NOT NULL DEFAULT 0,
  `ip_octet` int(10) unsigned NOT NULL DEFAULT 0,
  `root_pw` varchar(64) NOT NULL DEFAULT '',
  `traffic_rx` bigint(20) NOT NULL DEFAULT 0,
  `traffic_tx` bigint(20) NOT NULL DEFAULT 0,
  `node_state` varchar(16) NOT NULL DEFAULT '',
  `cycle` varchar(12) NOT NULL DEFAULT 'monthly',
  `remind_level` tinyint(4) NOT NULL DEFAULT 0,
  `vnc_token` varchar(32) DEFAULT '',
  `vnc_expire_at` datetime DEFAULT NULL,
  `traffic_reset_at` datetime DEFAULT NULL,
  `display_sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_inst_uid` (`uid`,`status`),
  KEY `idx_inst_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `kyc` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `real_name` varbinary(255) NOT NULL,
  `id_card` varbinary(255) NOT NULL,
  `phone` varbinary(255) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  `certify_id` varchar(64) DEFAULT NULL,
  `provider` varchar(20) NOT NULL DEFAULT '',
  `cert_url` varchar(500) DEFAULT NULL,
  `passed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_kyc_uid` (`uid`,`status`),
  KEY `idx_kyc_certify` (`certify_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `login_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `selector` char(24) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `ua` varchar(190) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `is_admin` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `idx_lt_user` (`user_id`,`is_admin`),
  KEY `idx_lt_expire` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `nc_nat_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` int(10) unsigned NOT NULL,
  `port` int(11) NOT NULL,
  `to_port` int(11) NOT NULL,
  `proto` varchar(4) NOT NULL DEFAULT 'both',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rule` (`instance_id`,`port`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `news` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(120) NOT NULL,
  `content` text NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_news_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `node_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `node_id` int(10) unsigned NOT NULL,
  `action` varchar(12) NOT NULL,
  `payload` text NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `result_detail` varchar(190) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nt_node` (`node_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `nodes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `node_key` char(48) NOT NULL,
  `pub_ip` varchar(45) NOT NULL DEFAULT '',
  `net_prefix` varchar(12) NOT NULL DEFAULT '10.0.3',
  `port_start` int(11) NOT NULL DEFAULT 20000,
  `port_end` int(11) NOT NULL DEFAULT 60000,
  `max_instances` int(11) NOT NULL DEFAULT 500,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `remark` varchar(190) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `images` text,
  `sort` int(11) NOT NULL DEFAULT 0,
  `last_seen` datetime DEFAULT NULL,
  `metrics_json` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  `instance_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(12) NOT NULL DEFAULT 'new',
  `title` varchar(160) NOT NULL DEFAULT '',
  `months` int(11) NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` datetime DEFAULT NULL,
  `usdt_amount` decimal(12,4) DEFAULT NULL,
  `usdt_tx` varchar(90) DEFAULT NULL,
  UNIQUE KEY `uq_usdt_tx` (`usdt_tx`),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `idx_ord_uid` (`uid`,`type`,`status`),
  KEY `idx_ord_pending` (`status`,`type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `email` varchar(120) NOT NULL DEFAULT '',
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pr_token` (`token_hash`),
  KEY `idx_pr_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `node_code` varchar(32) NOT NULL DEFAULT 'node1',
  `node_name` varchar(64) NOT NULL DEFAULT '节点 #1',
  `region` varchar(30) NOT NULL DEFAULT '默认',
  `cpu` int(11) NOT NULL DEFAULT 1,
  `ram_mb` int(11) NOT NULL DEFAULT 1024,
  `disk_gb` int(11) NOT NULL DEFAULT 10,
  `monthly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `hourly_price` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `traffic_gb` int(11) NOT NULL DEFAULT 1000,
  `bandwidth_mbps` int(11) NOT NULL DEFAULT 100,
  `stock` int(11) NOT NULL DEFAULT 50,
  `nat_type` varchar(40) NOT NULL DEFAULT '共享IP · 15端口',
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `badge` varchar(20) NOT NULL DEFAULT '',
  `description` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `node_id` int(10) unsigned NOT NULL DEFAULT 0,
  `port_count` int(11) NOT NULL DEFAULT 15,
  `images` text,
  `quarterly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `semiannually_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `annually_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `kyc_required` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `skey` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `svalue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ssh_keys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `name` varchar(40) NOT NULL,
  `public_key` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` int(10) unsigned NOT NULL,
  `uid` int(10) unsigned DEFAULT NULL,
  `is_admin` tinyint(4) NOT NULL DEFAULT 0,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tr_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `category` varchar(20) NOT NULL DEFAULT 'other',
  `status` varchar(10) NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `priority` varchar(10) NOT NULL DEFAULT 'normal',
  `instance_id` int(10) unsigned DEFAULT NULL,
  `images` text,
  PRIMARY KEY (`id`),
  KEY `idx_tk_uid` (`uid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `type` varchar(12) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(14,2) NOT NULL DEFAULT 0.00,
  `remark` varchar(190) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tx_uid` (`uid`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `kyc_status` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `api_token` char(64) DEFAULT NULL,
  `ref_code` varchar(16) DEFAULT NULL,
  `ref_by` int(10) unsigned DEFAULT NULL,
  `tg_chat_id` bigint(20) DEFAULT NULL,
  `tg_bind_code` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_api_token` (`api_token`),
  UNIQUE KEY `uq_ref_code` (`ref_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
