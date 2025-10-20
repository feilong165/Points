<?php
// 数据库配置
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', ''); // 默认密码为空，根据实际情况修改
if (!defined('DB_NAME')) define('DB_NAME', 'points_system');

// 应用配置
if (!defined('APP_NAME')) define('APP_NAME', '积分系统');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost/points_system');

// 加密配置
if (!defined('SECRET_KEY')) define('SECRET_KEY', 'your-secret-key-here'); // 建议使用随机字符串
if (!defined('SALT')) define('SALT', 'your-salt-here'); // 建议使用随机字符串

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);