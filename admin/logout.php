<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 调用管理员登出函数
logout_admin();

// 重定向到管理员登录页面
header("Location: login.php");
exit;