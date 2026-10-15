-- NAT Cloud 初始数据（默认设置 / 演示套餐 / 欢迎公告）
-- 管理员账号由安装向导创建，不在此文件内
-- 全部 INSERT 均为幂等（NOT EXISTS 守卫），安装流程异常中断后重试不会产生重复数据
SET NAMES utf8mb4;

INSERT INTO settings (skey, svalue) VALUES
  ('site_name', 'NAT Cloud'),
  ('site_url', ''),
  ('nat_public_ip', ''),
  ('nat_port_start', '20000'),
  ('ports_per_instance', '15'),
  ('nat_port_blocks', '3000'),
  ('register_enabled', '1'),
  ('announcement', ''),
  ('contact', '')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- 演示套餐：默认停用（status=0）——绑定真实节点或明确启用本机模拟模式前不可购买，
-- 防止新装站点出现“可下单但无真实容器”的幻影实例
INSERT INTO plans (name, node_code, node_name, region, cpu, ram_mb, disk_gb, monthly_price, hourly_price, traffic_gb, bandwidth_mbps, stock, nat_type, sort, status, badge, description, node_id, port_count, images)
SELECT * FROM (SELECT '轻量 · 1C1G' AS a, 'node1' AS b, '节点 #1' AS c, '默认' AS d, 1 AS e, 1024 AS f, 10 AS g, 15.00 AS h, 0.0300 AS i, 500 AS j, 100 AS k, 50 AS l, '共享IP · 15端口' AS m, 1 AS n, 0 AS o, '入门' AS p, '演示套餐：请在后台编辑为你的节点与价格' AS q, 0 AS r, 15 AS s, NULL AS t) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = '轻量 · 1C1G');

INSERT INTO plans (name, node_code, node_name, region, cpu, ram_mb, disk_gb, monthly_price, hourly_price, traffic_gb, bandwidth_mbps, stock, nat_type, sort, status, badge, description, node_id, port_count, images)
SELECT * FROM (SELECT '标准 · 1C2G' AS a, 'node1' AS b, '节点 #1' AS c, '默认' AS d, 1 AS e, 2048 AS f, 15 AS g, 25.00 AS h, 0.0500 AS i, 800 AS j, 200 AS k, 50 AS l, '共享IP · 15端口' AS m, 2 AS n, 0 AS o, '热门' AS p, '演示套餐：请在后台编辑为你的节点与价格' AS q, 0 AS r, 15 AS s, NULL AS t) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = '标准 · 1C2G');

INSERT INTO plans (name, node_code, node_name, region, cpu, ram_mb, disk_gb, monthly_price, hourly_price, traffic_gb, bandwidth_mbps, stock, nat_type, sort, status, badge, description, node_id, port_count, images)
SELECT * FROM (SELECT '进阶 · 2C4G' AS a, 'node1' AS b, '节点 #1' AS c, '默认' AS d, 2 AS e, 4096 AS f, 30 AS g, 49.00 AS h, 0.0900 AS i, 1000 AS j, 300 AS k, 40 AS l, '共享IP · 15端口' AS m, 3 AS n, 0 AS o, '' AS p, '演示套餐：请在后台编辑为你的节点与价格' AS q, 0 AS r, 15 AS s, NULL AS t) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = '进阶 · 2C4G');

INSERT INTO news (title, content, status)
SELECT 'NAT Cloud 部署完成', "站点安装向导已执行完毕，系统可正常使用。\n\n请管理员：\n1. 登录管理后台，修改站点名称与基础设置；\n2. 在「节点管理」添加节点并执行一键安装命令，完成节点对接；\n3. 在「套餐管理」将演示套餐绑定到真实节点后启用开售。\n\n如有问题请提交工单联系管理员。", 1
WHERE NOT EXISTS (SELECT 1 FROM news WHERE title = 'NAT Cloud 部署完成');
