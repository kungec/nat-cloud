# NAT Cloud 安装说明

支持两种部署环境：**Linux（坤面板 KunPanel）** 与 **Windows Server 2016+（IIS）**，按需选读。

## 〇、安装坤面板（Linux，1 分钟）

[坤面板 KunPanel](https://github.com/kungec/kunpanel) 是开源免费的轻量级服务器运维面板（Go 单二进制、开箱即用），在全新的 Ubuntu / Debian / CentOS 等主流发行版上执行一行命令即可完成安装：

```bash
curl -fsSL https://raw.githubusercontent.com/kungec/kunpanel/main/scripts/install.sh | bash
```

安装完成后会自动输出 **面板访问地址（随机端口 + 随机安全入口）与管理员密码**，浏览器登录即可。更多安装参数（自定义端口 / 账号密码 / Docker 引擎 / 离线安装等）见 [坤面板仓库](https://github.com/kungec/kunpanel)。

## 一、准备（坤面板，3 分钟）

1. **应用商店**：一键安装 **PHP 7.4 及以上**（官方多版本可选）与 **MySQL**；
2. **数据库**：添加一个 MySQL 数据库（字符集 utf8mb4），记下 **数据库名 / 用户名 / 密码**；
3. **网站 → 添加站点**：填你的域名，网站类型选 **PHP** 并选择刚安装的 7.4+ 版本，网站根目录指向站点目录下的 `public` 子目录（例如 `/www/sites/example.com/public`）。

## 二、上传程序（1 分钟）

**文件管理**：把本安装包上传到站点目录并 **解压**，解压后站点目录下应有：`app`、`public`、`cron`、`install` 四个目录（网站根目录即其中的 `public`，已在上一步指定）。

## 三、站点设置（1 处）

**网站 → 设置 → 伪静态**：粘贴以下内容并保存：

```nginx
location / { try_files $uri $uri/ /index.php?$query_string; }
```

## 四、安装（1 分钟）

浏览器访问 `http://你的域名/`，自动进入安装向导：

1. **第 1 步**：环境与权限自检（常见项会自动修复；PHP 扩展缺失按提示在面板应用商店补装后刷新本页；两项 Web 探测通过后「开始安装」才会亮起）。
2. **第 2 步**：填入第一步创建的数据库信息 + 设置管理员账号密码 → 点击「开始安装」。
3. 安装完成页会显示 **随机生成的管理后台地址**（每套系统都不同），请立即保存，该地址只展示这一次。

## 五、装完必做

1. **计划任务** → 添加 2 个 Shell 脚本任务（把「站点根目录」换成实际路径；命令中的 `php` 请换成应用商店所装 PHP 的实际路径，可在应用详情中查看）：

```
任务1：每 1 分钟   php 站点根目录/cron/bill.php
任务2：每日 00:05  php 站点根目录/cron/traffic_reset.php
```

2. 登录管理后台 → 系统设置：站点名称、支付渠道、SMTP 邮件。
3. 节点管理 → 添加节点 → 复制一键安装命令到你的 KVM 节点服务器执行（仅支持 KVM/独立服务器，OpenVZ/LXC 容器机不可用）。
4. 套餐管理：编辑或删除演示套餐，绑定真实节点后即可开售。


## Windows Server 2016+ / IIS 安装说明

**环境准备**

1. IIS 10（Server 2016 自带，需含 CGI 角色：服务器管理器 → 添加角色和功能 → Web 服务器 → 应用程序开发 → CGI）；
2. 安装 [PHP 7.4+](https://windows.php.net/download/)，并用 [PHP Manager for IIS](https://github.com/phpmgr/phpmgr) 注册到 IIS；
3. 安装 [URL Rewrite 模块 2.0](https://www.iis.net/downloads/microsoft/url-rewrite)；
4. 准备 MySQL 5.7+ 数据库（可同机或独立服务器），创建好数据库与账号。

**部署步骤**

1. 解压程序到如 `C:\inetpub\wwwroot\natcloud`；
2. IIS 添加网站：**物理路径直接指向其中的 `public` 文件夹**（推荐，程序自带 `public/web.config`）；若物理路径指向解压根目录，则使用根目录附带的 `web.config` 兼容配置；
3. 给对应应用程序池身份（`IIS AppPool\站点名`）对程序根目录授予**修改**权限（安装时需写入 `.env` 与 `install.lock`）；
4. php.ini 启用 `extension=pdo_mysql / mysqli / curl / gd / openssl`；
5. 浏览器访问域名进入安装向导，向导提示会自动切换为 Windows/IIS 对应指引；
6. 计划任务：编辑 `cron\natcloud-tasks.bat` 里的 PHP 与程序路径后，以管理员身份运行一次即可注册两个计划任务（按量计费每分钟、流量重置每日 00:05）。

> 注意：节点机（出售主机所在服务器）仍需为 Linux（KVM/独立服务器），Windows 仅用于部署本销售站面板。
>
> 坤面板同样提供 Windows 版（Release 下载 `kunpanel_windows_amd64.zip`，管理员运行 `install.bat` 即可），也可作为 IIS 之外的另一种 Windows 部署方式。

## 常见问题

- **「开始安装」按钮灰色**：上方有未通过的自检项，按提示处理后刷新页面。
- **提示数据库不存在**：面板 → 数据库 → 先创建数据库再回来安装。
- **安装后想找回后台地址**：查看站点根目录下 `.env` 文件里的 `ADMIN_PATH` 一行。

## 支持与交流

- 赞助商：[跨境VPS](https://www.kjvps.com)
- 在线演示站：[nat.kjvps.com](https://nat.kjvps.com)
- 官方交流 QQ 群：[307386089](https://qm.qq.com/cgi-bin/qm/qr?group_code=307386089)
