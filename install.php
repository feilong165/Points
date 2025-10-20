<?php
// 积分系统安装向导

// 安装状态常量
const INSTALL_STATUS_NOT_INSTALLED = 0;
const INSTALL_STATUS_DB_CONFIGURED = 1;
const INSTALL_STATUS_COMPLETED = 2;

// 初始化会话
if (!session_id()) {
    session_start();
}

// 设置默认安装状态
if (!isset($_SESSION['install_status'])) {
    $_SESSION['install_status'] = INSTALL_STATUS_NOT_INSTALLED;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['db_config'])) {
        // 数据库配置表单提交
        $_SESSION['db_host'] = $_POST['db_host'];
        $_SESSION['db_user'] = $_POST['db_user'];
        $_SESSION['db_pass'] = $_POST['db_pass'];
        $_SESSION['db_name'] = $_POST['db_name'];
        $_SESSION['app_name'] = $_POST['app_name'];
        $_SESSION['app_url'] = $_POST['app_url'];
        
        // 测试数据库连接
        $test_conn = @new mysqli($_SESSION['db_host'], $_SESSION['db_user'], $_SESSION['db_pass']);
        if ($test_conn->connect_error) {
            $error = "数据库服务器连接失败: " . $test_conn->connect_error;
        } else {
            // 连接成功，创建数据库
            $sql = "CREATE DATABASE IF NOT EXISTS " . $_SESSION['db_name'] . " DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if (!$test_conn->query($sql)) {
                $error = "创建数据库失败: " . $test_conn->error;
            } else {
                $test_conn->close();
                $_SESSION['install_status'] = INSTALL_STATUS_DB_CONFIGURED;
                // 创建临时配置文件用于后续步骤
                create_temp_config();
            }
        }
    } elseif (isset($_POST['admin_config'])) {
        // 管理员配置表单提交
        $_SESSION['admin_username'] = $_POST['admin_username'];
        $_SESSION['admin_password'] = $_POST['admin_password'];
        
        // 加载临时配置
        require_once 'temp_config.php';
        
        // 创建所有数据表
        require_once 'app/app.php';
        
        // 创建管理表并插入管理员账号
        if (create_all_tables($_SESSION['admin_username'], $_SESSION['admin_password'])) {
            // 更新最终配置文件
            update_final_config();
            // 清理临时文件
            @unlink('temp_config.php');
            $_SESSION['install_status'] = INSTALL_STATUS_COMPLETED;
        } else {
            $error = "数据库表创建失败，请检查错误信息";
        }
    } elseif (isset($_POST['reinstall'])) {
        // 重新安装
        session_destroy();
        header('Location: install.php');
        exit;
    }
}

/**
 * 创建临时配置文件
 */
function create_temp_config() {
    $config_content = "<?php\n";
    $config_content .= "// 数据库配置\n";
    $config_content .= "if (!defined('DB_HOST')) define('DB_HOST', '{$_SESSION['db_host']}');\n";
    $config_content .= "if (!defined('DB_USER')) define('DB_USER', '{$_SESSION['db_user']}');\n";
    $config_content .= "if (!defined('DB_PASS')) define('DB_PASS', '{$_SESSION['db_pass']}');\n";
    $config_content .= "if (!defined('DB_NAME')) define('DB_NAME', '{$_SESSION['db_name']}');\n\n";
    $config_content .= "// 应用配置\n";
    $config_content .= "if (!defined('APP_NAME')) define('APP_NAME', '{$_SESSION['app_name']}');\n";
    $config_content .= "if (!defined('APP_URL')) define('APP_URL', '{$_SESSION['app_url']}');\n\n";
    $config_content .= "// 加密配置\n";
    $config_content .= "if (!defined('SECRET_KEY')) define('SECRET_KEY', '" . generateRandomString(32) . "');\n";
    $config_content .= "if (!defined('SALT')) define('SALT', '" . generateRandomString(32) . "');\n\n";
    $config_content .= "// 时区设置\n";
    $config_content .= "date_default_timezone_set('Asia/Shanghai');\n\n";
    $config_content .= "// 错误报告设置\n";
    $config_content .= "error_reporting(E_ALL);\n";
    $config_content .= "ini_set('display_errors', 1);\n";
    
    file_put_contents('temp_config.php', $config_content);
}

/**
 * 更新最终配置文件
 */
function update_final_config() {
    $config_content = "<?php\n";
    $config_content .= "// 数据库配置\n";
    $config_content .= "if (!defined('DB_HOST')) define('DB_HOST', '{$_SESSION['db_host']}');\n";
    $config_content .= "if (!defined('DB_USER')) define('DB_USER', '{$_SESSION['db_user']}');\n";
    $config_content .= "if (!defined('DB_PASS')) define('DB_PASS', '{$_SESSION['db_pass']}');\n";
    $config_content .= "if (!defined('DB_NAME')) define('DB_NAME', '{$_SESSION['db_name']}');\n\n";
    $config_content .= "// 应用配置\n";
    $config_content .= "if (!defined('APP_NAME')) define('APP_NAME', '{$_SESSION['app_name']}');\n";
    $config_content .= "if (!defined('APP_URL')) define('APP_URL', '{$_SESSION['app_url']}');\n\n";
    $config_content .= "// 加密配置\n";
    $config_content .= "if (!defined('SECRET_KEY')) define('SECRET_KEY', '" . generateRandomString(32) . "');\n";
    $config_content .= "if (!defined('SALT')) define('SALT', '" . generateRandomString(32) . "');\n\n";
    $config_content .= "// 时区设置\n";
    $config_content .= "date_default_timezone_set('Asia/Shanghai');\n\n";
    $config_content .= "// 错误报告设置\n";
    $config_content .= "error_reporting(E_ALL);\n";
    $config_content .= "ini_set('display_errors', 1);\n";
    $config_content .= "// 安装完成标记\n";
    $config_content .= "define('INSTALLED', true);\n";
    
    // 备份原配置文件
    if (file_exists('app/config.php')) {
        rename('app/config.php', 'app/config.php.bak');
    }
    
    file_put_contents('app/config.php', $config_content);
}

/**
 * 创建所有数据表
 */
function create_all_tables($admin_username, $admin_password) {
    try {
        // 创建核心表（确保用户表先创建）
        create_user_table();
        
        // 先创建管理员表，因为客服对话表依赖它
        $conn = get_db_connection();
        
        $sql = "CREATE TABLE IF NOT EXISTS admins (\n";
        $sql .= "    id INT(11) AUTO_INCREMENT PRIMARY KEY,\n";
        $sql .= "    username VARCHAR(50) NOT NULL UNIQUE,\n";
        $sql .= "    password VARCHAR(255) NOT NULL,\n";
        $sql .= "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        $sql .= "    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($sql)) {
            throw new Exception("创建管理员表失败: " . $conn->error);
        }
        
        // 插入管理员账户
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        $insert_sql = "INSERT INTO admins (username, password) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ss", $admin_username, $hashed_password);
        
        if (!$stmt->execute()) {
            throw new Exception("插入管理员账户失败: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        
        // 继续创建其他核心表
        create_signin_log_table();
        create_products_table();
        create_codes_table();
        create_orders_table();
        create_categories_table();
        create_product_categories_table();
        create_banners_table();
        create_messages_table();
        create_coupons_table();
        create_user_coupons_table();
        
        // 创建积分历史表
        create_points_history_table();
        
        // 创建搜索相关表
        ensure_search_tables_exists();
        
        // 现在可以创建客服相关表（依赖于users和admins表）
        create_support_conversations_table();
        create_support_messages_table();
        
        return true;
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage();
        return false;
    }
}

/**
 * 创建积分历史表
 */
function create_points_history_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS points_history (\n";
    $sql .= "    id INT(11) AUTO_INCREMENT PRIMARY KEY,\n";
    $sql .= "    user_id INT(11) NOT NULL,\n";
    $sql .= "    points_change INT(11) NOT NULL,\n";
    $sql .= "    points_balance INT(11) NOT NULL,\n";
    $sql .= "    type VARCHAR(50) NOT NULL,\n";
    $sql .= "    description VARCHAR(255) NOT NULL,\n";
    $sql .= "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
    $sql .= "    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        throw new Exception("创建积分历史表失败: " . $conn->error);
    }
    
    $conn->close();
}

/**
 * 生成随机字符串
 */
function generateRandomString($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * 检查是否已安装
 */
function check_installed() {
    if (file_exists('app/config.php')) {
        require_once 'app/config.php';
        return defined('INSTALLED') && INSTALLED === true;
    }
    return false;
}

// 检查是否已安装
if (check_installed() && $_SESSION['install_status'] !== INSTALL_STATUS_COMPLETED) {
    $_SESSION['install_status'] = INSTALL_STATUS_COMPLETED;
}

// 显示安装页面
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>积分系统 - 安装向导</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .step-indicator {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px 0;
            position: relative;
            color: #999;
        }
        .step.active {
            color: #4CAF50;
            font-weight: bold;
        }
        .step.completed {
            color: #4CAF50;
        }
        .step.completed::after {
            content: '✓';
            position: absolute;
            right: -10px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #4CAF50;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .info-box {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        .install-complete {
            text-align: center;
            padding: 40px 20px;
        }
        .install-complete h2 {
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .install-complete p {
            margin-bottom: 30px;
            font-size: 18px;
        }
        .btn-secondary {
            background-color: #f44336;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background-color: #da190b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>积分系统安装向导</h1>
        </div>
        <div class="content">
            <!-- 步骤指示器 -->
            <div class="step-indicator">
                <div class="step <?php echo $_SESSION['install_status'] >= INSTALL_STATUS_DB_CONFIGURED ? 'completed' : ($_SESSION['install_status'] == INSTALL_STATUS_NOT_INSTALLED ? 'active' : ''); ?>">
                    数据库配置
                </div>
                <div class="step <?php echo $_SESSION['install_status'] >= INSTALL_STATUS_COMPLETED ? 'completed' : ($_SESSION['install_status'] == INSTALL_STATUS_DB_CONFIGURED ? 'active' : ''); ?>">
                    管理员设置
                </div>
                <div class="step <?php echo $_SESSION['install_status'] == INSTALL_STATUS_COMPLETED ? 'active' : ''; ?>">
                    安装完成
                </div>
            </div>
            
            <!-- 错误信息 -->
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- 第一步：数据库配置 -->
            <?php if ($_SESSION['install_status'] == INSTALL_STATUS_NOT_INSTALLED): ?>
                <div class="info-box">
                    <p>请填写您的数据库信息，系统将自动创建数据库和所有必要的数据表。</p>
                </div>
                <form method="post" action="install.php">
                    <div class="form-group">
                        <label for="db_host">数据库主机</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label for="db_user">数据库用户名</label>
                        <input type="text" id="db_user" name="db_user" value="root" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">数据库密码</label>
                        <input type="password" id="db_pass" name="db_pass">
                    </div>
                    <div class="form-group">
                        <label for="db_name">数据库名称</label>
                        <input type="text" id="db_name" name="db_name" value="points_system" required>
                    </div>
                    <div class="form-group">
                        <label for="app_name">应用名称</label>
                        <input type="text" id="app_name" name="app_name" value="积分系统" required>
                    </div>
                    <div class="form-group">
                        <label for="app_url">应用URL</label>
                        <input type="text" id="app_url" name="app_url" value="http://localhost/points_system" required>
                    </div>
                    <input type="hidden" name="db_config" value="1">
                    <button type="submit" class="btn">下一步：设置管理员账号</button>
                </form>
            <?php endif; ?>
            
            <!-- 第二步：管理员设置 -->
            <?php if ($_SESSION['install_status'] == INSTALL_STATUS_DB_CONFIGURED): ?>
                <div class="info-box">
                    <p>数据库连接成功，请设置系统管理员账号信息。</p>
                </div>
                <form method="post" action="install.php">
                    <div class="form-group">
                        <label for="admin_username">管理员用户名</label>
                        <input type="text" id="admin_username" name="admin_username" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_password">管理员密码</label>
                        <input type="password" id="admin_password" name="admin_password" required>
                    </div>
                    <input type="hidden" name="admin_config" value="1">
                    <button type="submit" class="btn">开始安装</button>
                </form>
            <?php endif; ?>
            
            <!-- 第三步：安装完成 -->
            <?php if ($_SESSION['install_status'] == INSTALL_STATUS_COMPLETED): ?>
                <div class="install-complete">
                    <h2>🎉 安装完成！</h2>
                    <p>积分系统已成功安装并配置完成。</p>
                    <div class="success-message">
                        <p><strong>管理员账号：</strong><?php echo $_SESSION['admin_username']; ?></p>
                        <p><strong>管理员密码：</strong>您设置的密码（出于安全考虑已不再显示）</p>
                    </div>
                    <div style="margin-top: 30px;">
                        <a href="admin/login.php" class="btn">进入管理后台</a>
                        <a href="index.php" class="btn" style="margin-left: 10px;">访问前台</a>
                        <form method="post" action="install.php" style="display: inline-block; margin-left: 10px;">
                            <input type="hidden" name="reinstall" value="1">
                            <button type="submit" class="btn btn-secondary" onclick="return confirm('确定要重新安装吗？这将清除所有数据！');">重新安装</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>