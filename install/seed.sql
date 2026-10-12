-- NAT Cloud 初始数据（默认设置 / 演示套餐 / 欢迎公告）
-- 管理员账号由安装向导创建，不在此文件内
SET NAMES utf8mb4;

INSERT INTO settings (skey, svalue) VALUES
  ('site_name', 'NAT Cloud'),
  ('nat_public_ip', ''),
  ('nat_port_start', '20000'),
  ('ports_per_instance', '15'),
  ('nat_port_blocks', '3000'),
  ('register_enabled', '1'),
  ('announcement', ''),
  ('contact', '')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

INSERT INTO plans (name, node_code, node_name, region, cpu, ram_mb, disk_gb, monthly_price, hourly_price, traffic_gb, bandwidth_mbps, stock, nat_type, sort, status, badge, description, node_id, port_count, images)
VALUES
  ('轻量 · 1C1G', 'node1', '节点 #1', '默认', 1, 1024, 10, 15.00, 0.0300, 500, 100, 50, '共享IP · 15端口', 1, 1, '入门', '演示套餐：请在后台编辑为你的节点与价格', 0, 15, NULL),
  ('标准 · 1C2G', 'node1', '节点 #1', '默认', 1, 2048, 15, 25.00, 0.0500, 800, 200, 50, '共享IP · 15端口', 2, 1, '热门', '演示套餐：请在后台编辑为你的节点与价格', 0, 15, NULL),
  ('进阶 · 2C4G', 'node1', '节点 #1', '默认', 2, 4096, 30, 49.00, 0.0900, 1000, 300, 40, '共享IP · 15端口', 3, 1, '', '演示套餐：请在后台编辑为你的节点与价格', 0, 15, NULL);

INSERT INTO news (title, content, status) VALUES
  ('NAT Cloud 部署完成', "站点安装向导已执行完毕，系统可正常使用。\n\n请管理员：\n1. 登录管理后台，修改站点名称与基础设置；\n2. 在「节点管理」添加节点并执行一键安装命令，完成节点对接；\n3. 在「套餐管理」将演示套餐绑定到真实节点，或删除后自建。\n\n如有问题请提交工单联系管理员。", 1);
