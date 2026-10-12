# NAT Cloud

轻量 NAT VPS 主机销售与自动化开通系统（PHP / MySQL，原生无框架）。

面向“共享 IP + 独立端口段”的 NAT VPS 业务：用户下单付款后，系统自动在已对接的 LXC 节点上创建实例、分配公网端口段并交付 SSH；支持包年包月与按小时计费、流量配额、工单、实名认证（对接第三方人脸核身）、码支付 / 支付宝当面付 / 微信 Native 收款，以及一键安装向导与节点一键接入脚本。

> 系统为作者业务真实在用代码整理开源，功能均经生产环境长期验证。

## 功能特性

**销售与计费**
- 套餐 / 自定义配置两种购买方式，包年包月（月/季/半年/年价）与按量计费（小时价、余额门槛、欠费自动停机与释放）
- 流量双向统计、超额自动停机、按订购日锚定的每月流量重置
- 余额充值：码支付（易支付协议）、支付宝当面付（RSA2 公钥模式）、微信官方 Native（APIv3 + 公钥模式验签 + AES-GCM 回调解密）、USDT（链上识别可选）
- 演示套餐与全部业务参数均可在后台修改

**自动化开通**
- 节点一键接入：后台生成含专属密钥的安装命令，在全新 Ubuntu 20+ / Debian 11+（KVM/独立服务器）上执行即完成 LXC 环境部署与对接
- 实例全生命周期自动执行：创建 / 开关机 / 重启 / 重装 / 释放 / 密码重置 / NAT 端口映射，节点零常驻进程、零入站端口（拉取式对账 + 每节点独立密钥）
- 实例隔离加固：lxcfs 视图遮蔽、独立 loop 磁盘、machine-id 与 SSH host key 每实例唯一
- 自助 NAT 端口转发规则、Web VNC 控制台（ttyd 一次性凭据 + TTL）

**用户与合规**
- 邮箱验证注册、找回密码、图形验证码（GD 不可用自动降级）
- 实名认证：姓名 + 身份证提交，对接第三方支付宝人脸核身自动通过；全局开关 + 套餐三态覆盖；数据加密存储、管理后台可见
- 工单系统（分类/优先级/图片附件）、公告、多语言（中/英）骨架

**管理与安全**
- 安装向导：环境自检 + 权限自动设置 + 数据库初始化 + 随机管理后台路径（每套部署不同）
- 后台：仪表盘、用户/实例/套餐/节点/工单/公告/财务管理、系统镜像图标、卡片拖拽排序
- 安全设计：CSRF 全表单、Cookie httponly+SameSite、安全响应头、密文存储（APP_KEY 派生）、支付回调验签+幂等、登录限速、SQL 全参数化、WAF 友好

## 环境要求

- PHP 7.4+（pdo_mysql、mysqli、curl、gd、openssl）
- MySQL 5.7+（utf8mb4）
- Web 服务任选其一：
  - **Linux**：Nginx + 宝塔面板（或任意可配 try_files 伪静态的环境）
  - **Windows**：Windows Server 2016+ / IIS 10 + [URL Rewrite 模块 2.0](https://www.iis.net/downloads/microsoft/url-rewrite) + PHP（FastCGI，推荐 PHP Manager 注册）
- 节点机（出售的主机所在服务器）：Ubuntu 20+ / Debian 11+，**KVM 虚拟化或独立服务器（x86_64）**——OpenVZ / LXC 等共享内核容器机不可用（节点程序为 Linux LXC；网站面板本身在 Windows 上运行不受影响）

## 快速开始（宝塔面板 / Linux）

1. 宝塔新建站点（PHP 7.4+）并创建数据库；
2. 下载 [最新 Release](../../releases) 的压缩包上传到站点根目录解压（或 `git clone` 本仓库到站点根目录）；
3. 网站设置：运行目录选 **/public**；伪静态添加：
   ```nginx
   location / { try_files $uri $uri/ /index.php?$query_string; }
   ```
4. 浏览器访问你的域名，进入安装向导，按提示填写数据库与管理员信息完成安装；
5. 保存安装完成页展示的**随机管理后台地址**（也可在站点根目录 `.env` 的 `ADMIN_PATH` 中找到）；
6. 宝塔计划任务添加：
   ```cron
   * * * * * php 站点根目录/cron/bill.php
   5 0 * * * php 站点根目录/cron/traffic_reset.php
   ```
7. 登录管理后台 → 节点管理 → 添加节点 → 复制一键安装命令到节点服务器 root 执行；
8. 套餐管理绑定节点后即可开售。

详细说明见 [README_INSTALL.md](README_INSTALL.md)。

## 快速开始（Windows Server 2016+ / IIS）

1. **环境准备**：IIS 10（Server 2016 自带）；安装 [PHP 7.4+](https://windows.php.net/download/) 非 Thread Safe 或 Thread Safe 均可（用 PHP Manager 注册到 IIS）；安装 [URL Rewrite 模块 2.0](https://www.iis.net/downloads/microsoft/url-rewrite)；准备 MySQL 5.7+ 数据库；
2. **部署**：解压程序到如 `C:\inetpub\wwwroot\natcloud`；IIS 站点**物理路径直接指向其中的 `public` 文件夹**（推荐，程序已自带 `public/web.config`）——若物理路径指向解压根目录，则使用根目录附带的 `web.config` 兼容配置；
3. **权限**：给对应应用程序池身份（`IIS AppPool\站点名`）对程序根目录授予**修改**权限（安装时需写入 `.env` 与 `install.lock`）；
4. **php.ini**：启用 `extension=pdo_mysql / mysqli / curl / gd / openssl`；
5. 浏览器访问域名进入安装向导，向导提示会自动切换为 Windows/IIS 对应指引；
6. **计划任务**：编辑 `cron\natcloud-tasks.bat` 里的 PHP 与程序路径后，以管理员身份运行一次即可注册两个计划任务（按量计费每分钟、流量重置每日 00:05）。

## 赞助与支持

- **赞助商**：[跨境VPS](https://www.kjvps.com) —— 感谢对本项目的大力支持 ❤
- **在线演示站**：[nat.kjvps.com](https://nat.kjvps.com)
- **官方交流 QQ 群**：[307386089](https://qm.qq.com/cgi-bin/qm/qr?group_code=307386089)

## 目录结构

```
app/            应用代码（路由分发、控制器、视图、支付/实名/节点对接）
  node_install/ 节点对账程序 natnode.sh（节点端自动拉取）
public/         网站根目录（入口 index.php、静态资源、节点 API shim）
cron/           计划任务（按量计费、流量重置）
install/        数据库结构 + 种子数据
```

## 安全说明

- 安装完成后请在站点根目录确认生成 `install.lock`（向导永久关闭）；
- `.env` 含数据库凭据与 APP_KEY，权限应为 600 且不可被 Web 访问；
- 管理后台地址为随机生成，泄露可在 `.env` 修改 `ADMIN_PATH` 更换；
- 建议生产环境关闭 phpMyAdmin 对公网的暴露，并定期备份。

## License

[MIT](LICENSE)
