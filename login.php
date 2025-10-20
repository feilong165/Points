<?php
// 检查是否已安装
$installed = false;
if (file_exists('app/config.php')) {
    require_once 'app/config.php';
    $installed = defined('INSTALLED') && INSTALLED === true;
}

// 如果未安装，跳转到安装页面
if (!$installed) {
    header('Location: install.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 积分系统</title>
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
        <h2>用户登录</h2>
        
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
        
        // 处理登录表单提交
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $identifier = trim($_POST['identifier']);
            $password = $_POST['password'];
            
            // 表单验证
            if (empty($identifier) || empty($password)) {
                $error = '请填写所有必填字段';
            } else {
                // 调用登录函数
                $result = login_user($identifier, $password);
                
                if ($result['success']) {
                    // 登录成功，检查是否有return_url参数
                    if (isset($_GET['return_url'])) {
                        header("Location: " . urldecode($_GET['return_url']));
                    } else {
                        header("Location: user_center.php");
                    }
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
        }
        ?>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <div class="form-group">
                <label for="identifier">用户名或手机号</label>
                <input type="text" id="identifier" name="identifier" placeholder="请输入用户名或手机号" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required>
            </div>
            
            <button type="submit" class="btn">登录</button>
            
            <div class="link">
                <p>还没有账号？<a href="register.php">立即注册</a></p>
            </div>
        </form>
    </div>
</body>
</html>