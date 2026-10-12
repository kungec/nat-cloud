@echo off
chcp 65001 >nul
rem ============================================================
rem  NAT Cloud - Windows 计划任务一键注册（按量计费 + 流量重置）
rem  用法：先修改下面两个变量，然后右键“以管理员身份运行”
rem ============================================================
set "PHP_EXE=C:\Program Files\PHP\v7.4\php.exe"
set "APP_ROOT=C:\inetpub\wwwroot\natcloud"

if not exist "%PHP_EXE%" (
  echo [错误] 未找到 PHP：%PHP_EXE%
  echo 请把 PHP_EXE 修改为本机 php.exe 的实际完整路径。
  pause
  exit /b 1
)
if not exist "%APP_ROOT%\cron\bill.php" (
  echo [错误] 未找到程序：%APP_ROOT%\cron\bill.php
  echo 请把 APP_ROOT 修改为程序解压根目录的实际完整路径。
  pause
  exit /b 1
)

schtasks /Create /F /TN "NATCloud-bill" /SC MINUTE /MO 1 /TR "\"%PHP_EXE%\" \"%APP_ROOT%\cron\bill.php\""
schtasks /Create /F /TN "NATCloud-traffic-reset" /SC DAILY /ST 00:05 /TR "\"%PHP_EXE%\" \"%APP_ROOT%\cron\traffic_reset.php\""

echo.
echo 已注册两个计划任务：NATCloud-bill（每分钟）、NATCloud-traffic-reset（每日 00:05）。
pause
