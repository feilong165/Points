<?php
// 检查是否已安装
$installed = false;
if (file_exists('../app/config.php')) {
    require_once '../app/config.php';
    $installed = defined('INSTALLED') && INSTALLED === true;
}

// 如果未安装，跳转到安装页面
if (!$installed) {
    header('Location: ../install.php');
    exit;
}

// 引入应用核心文件
require_once '../app/app.php';

// 注意：session_start()必须在任何HTML输出之前调用
// 检查是否已登录
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// 处理登录表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // 表单验证
    if (empty($username) || empty($password)) {
        $error = '请填写所有必填字段';
    } else {
        // 调用管理员登录函数
        $result = admin_login($username, $password);
        
        if ($result['success']) {
            // 登录成功，跳转到管理后台首页
            header("Location: dashboard.php");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - 积分系统</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        .btn {
            width: 100%;
            background-color: #2196F3;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #1976D2;
        }
        .error-message {
            color: #f44336;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 3px;
        }
        .back-link {
            text-align: center;
            margin-top: 15px;
        }
        .back-link a {
            color: #666;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>管理员登录</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" placeholder="请输入管理员用户名" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入管理员密码" required>
            </div>
            
            <button type="submit" class="btn">登录</button>
            
            <div class="back-link">
                <p><a href="../index.php">返回首页</a></p>
            </div>
        </form>
    </div>
</body>
</html>