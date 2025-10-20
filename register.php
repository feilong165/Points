<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - 积分系统</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 500px;
            margin: 50px auto;
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
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .error-message {
            color: #f44336;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 3px;
        }
        .success-message {
            color: #4CAF50;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 3px;
        }
        .link {
            text-align: center;
            margin-top: 15px;
        }
        .link a {
            color: #4CAF50;
            text-decoration: none;
        }
        .link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>用户注册</h2>
        
        <?php
        // 引入配置和应用核心文件
        require_once 'app/config.php';
        require_once 'app/app.php';
        
        // 检查是否已登录
        session_start();
        if (isset($_SESSION['user_id'])) {
            header("Location: user_center.php");
            exit;
        }
        
        $error = '';
        $success = '';
        
        // 处理注册表单提交
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $username = trim($_POST['username']);
            $phone = trim($_POST['phone']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            
            // 表单验证
            if (empty($username) || empty($phone) || empty($password) || empty($confirm_password)) {
                $error = '请填写所有必填字段';
            } else if (strlen($username) < 3 || strlen($username) > 20) {
                $error = '用户名长度应在3-20个字符之间';
            } else if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                $error = '请输入有效的手机号码';
            } else if (strlen($password) < 6) {
                $error = '密码长度至少为6个字符';
            } else if ($password !== $confirm_password) {
                $error = '两次输入的密码不一致';
            } else {
                // 调用注册函数
                $result = register_user($username, $phone, $password);
                
                if ($result['success']) {
                    $success = $result['message'];
                    // 清空表单
                    $username = $phone = '';
                } else {
                    $error = $result['message'];
                }
            }
        }
        ?>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" placeholder="请输入用户名" required>
            </div>
            
            <div class="form-group">
                <label for="phone">手机号</label>
                <input type="tel" id="phone" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>" placeholder="请输入手机号" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认密码</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="请再次输入密码" required>
            </div>
            
            <button type="submit" class="btn">注册</button>
            
            <div class="link">
                <p>已有账号？<a href="login.php">立即登录</a></p>
            </div>
        </form>
    </div>
</body>
</html>