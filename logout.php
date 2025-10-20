<?php
// 引入配置和应用核心文件
require_once 'app/config.php';
require_once 'app/app.php';

// 调用登出函数
logout_user();

// 重定向到登录页面
header("Location: login.php");
exit;