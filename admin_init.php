<?php
/**
 * 管理员初始化脚本
 * 用于创建初始管理员账号
 */

// 引入配置文件
require_once 'app/config.php';
// 引入应用核心文件
require_once 'app/app.php';

// 检查是否已初始化
function is_admin_initialized() {
    $conn = get_db_connection();
    $sql = "SELECT COUNT(*) AS count FROM admins";
    $result = $conn->query($sql);
    $count = $result->fetch_assoc()['count'];
    $conn->close();
    return $count > 0;
}

// 初始化管理员
function initialize_admin($username, $password) {
    $conn = get_db_connection();
    
    // 检查用户名是否已存在
    $sql = "SELECT id FROM admins WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => '用户名已存在'];
    }
    
    $stmt->close();
    
    // 生成密码哈希
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // 插入管理员记录
    $sql = "INSERT INTO admins (username, password_hash, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password_hash);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ['success' => true, 'message' => '管理员账号创建成功'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        return ['success' => false, 'message' => '创建失败: ' . $error];
    }
}

// 检查是否已初始化
if (is_admin_initialized()) {
    echo "<h2>管理员账号已初始化</h2>";
    echo "<p>系统已存在管理员账号，如需创建新账号，请联系系统管理员或修改数据库。</p>";
    echo "<p><a href='admin/login.php'>前往管理后台登录</a></p>";
    exit;
}

// 处理表单提交
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // 表单验证
    if (empty($username)) {
        $error = '请输入用户名';
    } else if (strlen($username) < 4 || strlen($username) > 20) {
        $error = '用户名长度必须在4-20个字符之间';
    } else if (empty($password)) {
        $error = '请输入密码';
    } else if (strlen($password) < 6) {
        $error = '密码长度不能少于6个字符';
    } else if ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        // 初始化管理员
        $result = initialize_admin($username, $password);
        
        if ($result['success']) {
            $success = $result['message'];
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
    <title>初始化管理员账号 - 积分系统</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
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
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            width: 100%;
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
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #3498db;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>初始化管理员账号</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo $success; ?><br>
                <a href="admin/login.php">立即登录</a>
            </div>
        <?php else: ?>
            <form method="POST" action="admin_init.php">
                <div class="form-group">
                    <label for="username">管理员用户名</label>
                    <input type="text" id="username" name="username" placeholder="请输入用户名" required>
                </div>
                
                <div class="form-group">
                    <label for="password">管理员密码</label>
                    <input type="password" id="password" name="password" placeholder="请输入密码" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="请再次输入密码" required>
                </div>
                
                <button type="submit" class="btn">创建管理员账号</button>
            </form>
            
            <div class="login-link">
                <a href="index.php">返回首页</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>