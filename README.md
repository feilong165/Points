# 积分系统

一个基于PHP和MySQL的积分管理系统，支持用户注册登录、每日签到、积分兑换、商品管理等功能。

## 功能特性

### 用户功能
- 用户注册（用户名、手机号、密码、确认密码）
- 用户登录（用户名或手机号、密码）
- 每日签到（签到成功获得1积分）
- 积分商城（浏览和兑换商品）
- 用户中心（查看积分、签到记录、订单记录）

### 商品管理
- 添加、编辑、删除商品
- 商品上下架管理
- 商品置顶功能
- 商品排序功能
- 定时补货功能
- 虚拟商品支持（兑换码发货）

### 管理功能
- 管理员登录/退出
- 用户管理（查看用户列表、调整积分）
- 商品管理（添加、编辑、上下架、置顶、排序、补货）
- 订单管理（查看订单、更新订单状态、取消订单）
- 兑换码管理（生成、导出、删除兑换码）
- 数据统计（用户总数、商品总数、订单总数、总积分）

## 系统要求

- PHP 7.0 或更高版本
- MySQL 5.6 或更高版本
- Web服务器（Apache、Nginx等）

## 安装指南

### 1. 安装PHP环境

**Windows系统:**
1. 访问 [PHP官网](https://www.php.net/downloads) 下载Windows版本的PHP安装包
2. 解压到本地文件夹（例如 `C:\php`）
3. 将PHP目录添加到系统环境变量 `PATH`
4. 配置 `php.ini` 文件（复制 `php.ini-development` 为 `php.ini` 并启用必要的扩展）

**Linux系统:**
```bash
# Ubuntu/Debian
apt-get update
apt-get install php php-mysql php-mbstring php-gd php-xml

# CentOS/RHEL
yum install php php-mysql php-mbstring php-gd php-xml
```

### 2. 安装MySQL

**Windows系统:**
1. 访问 [MySQL官网](https://dev.mysql.com/downloads/installer/) 下载MySQL安装包
2. 按照安装向导完成安装
3. 记住设置的root密码

**Linux系统:**
```bash
# Ubuntu/Debian
apt-get install mysql-server

service mysql start
mysql_secure_installation

# CentOS/RHEL
yum install mysql-server

service mysqld start
mysql_secure_installation
```
### 2. 安装导向
访问部署文件后的网站按照指导填写信息，完成安装

## 系统使用说明

### 用户端
- **首页**: 显示系统介绍和登录/注册入口
- **注册**: 填写用户名、手机号、密码完成注册
- **登录**: 使用用户名/手机号和密码登录
- **用户中心**: 查看个人信息、积分、签到记录、订单记录
- **积分商城**: 浏览和兑换商品

### 管理后台
- **登录**: 访问 `admin/login.php` 使用管理员账号登录
- **控制面板**: 查看系统统计数据和最近活动
- **用户管理**: 管理用户信息和积分
- **商品管理**: 管理积分商城的商品
- **订单管理**: 查看和处理用户的兑换订单
- **兑换码管理**: 生成和管理虚拟商品的兑换码

## 系统安全建议

1. 不要在生产环境中使用默认的数据库密码
2. 定期备份数据库
3. 确保 `app/config.php` 文件的权限设置正确，防止未授权访问
4. 建议在生产环境中使用HTTPS
5. 定期更新PHP和MySQL到最新版本

## 常见问题

### Q: 为什么无法连接数据库？
A: 请检查 `app/config.php` 中的数据库连接信息是否正确，确保MySQL服务正在运行。

### Q: 如何重置管理员密码？
A: 可以通过数据库管理工具（如phpMyAdmin）直接修改 `admins` 表中的 `password_hash` 字段。

### Q: 如何添加新的管理员？
A: 首次安装后，系统需要通过 `admin_init.php` 创建初始管理员。后续可以通过修改数据库添加更多管理员。

### Q: 商品的定时补货功能如何工作？
A: 在添加/编辑商品时设置补货时间和补货数量，系统会在指定时间自动补充库存。

## 版权信息

© 2024-2025 Smallshark - 保留所有权利

请在使用前阅读并遵守相关使用条款。