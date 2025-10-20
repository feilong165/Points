<!DOCTYPE html>
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

// 启动会话
session_start();

// 检查用户是否登录，如果未登录则跳转到登录页面
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 引入配置和应用核心文件
require_once 'app/config.php';
require_once 'app/app.php';

// 获取用户信息
$user_id = $_SESSION['user_id'];
$user_info = get_user_info($user_id);

// 检查今天是否已签到
$has_checked_in = has_user_checked_in_today($user_id);

// 处理签到请求
$sign_in_message = '';
$sign_in_success = false;

if (isset($_POST['check_in']) && !$has_checked_in) {
    $result = user_check_in($user_id);
    if ($result['success']) {
        $sign_in_success = true;
        $sign_in_message = $result['message'];
        $has_checked_in = true;
        // 刷新用户信息以显示最新积分
        $user_info = get_user_info($user_id);
    } else {
        $sign_in_message = $result['message'];
        $sign_in_success = false;
    }
}

// 获取签到统计信息
$check_in_stats = get_user_check_in_stats($user_id);
?>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>积分系统 - 签到中心</title>
    <!-- 引入 Font Awesome 图标库 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 80px; /* 为底部导航栏留出空间 */
        }
        header {
            background-color: #4CAF50;
            color: white;
            padding: 15px 0;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            border-top: 1px solid #eee;
            z-index: 1000;
            box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
        }
        nav ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: space-around;
        }
        nav ul li {
            flex: 1;
            text-align: center;
        }
        nav ul li a {
            display: block;
            padding: 15px 0;
            padding-top: 10px;
            text-decoration: none;
            color: #666;
            font-size: 14px;
            position: relative;
        }
        nav ul li a:hover {
            color: #4CAF50;
        }
        nav ul li a.active {
            color: #4CAF50;
        }
        .check-in-container {
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .user-info {
            margin-bottom: 30px;
        }
        .user-info h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .user-points {
            font-size: 24px;
            color: #FF9800;
            font-weight: bold;
        }
        .check-in-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .check-in-btn:hover {
            background-color: #45a049;
        }
        .check-in-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            display: none;
        }
        .message.show {
            display: block;
        }
        .success-message {
            background-color: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .check-in-stats {
            margin-top: 30px;
            display: flex;
            justify-content: space-around;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .mall-entry {
            margin-top: 30px;
            padding: 20px;
            background-color: #fff3e0;
            border-radius: 5px;
            border: 1px solid #ffe0b2;
        }
        .mall-entry h3 {
            color: #f57c00;
            margin-bottom: 10px;
        }
        .mall-btn {
            display: inline-block;
            background-color: #f57c00;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        .mall-btn:hover {
            background-color: #ef6c00;
        }
        .check-in-icon {
            font-size: 48px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>每日签到</h1>
        </header>
        
        <div class="check-in-container">
            <div class="user-info">
                <h2>欢迎，<?php echo $user_info['username']; ?></h2>
                <p>当前积分: <span class="user-points"><?php echo $user_info['points']; ?></span></p>
            </div>
            
            <div class="check-in-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            
            <?php if (!$has_checked_in): ?>
                <form method="post">
                    <button type="submit" name="check_in" class="check-in-btn">
                        <i class="fas fa-gift"></i> 立即签到 (+1积分)
                    </button>
                </form>
            <?php else: ?>
                <button class="check-in-btn" disabled>
                    <i class="fas fa-check-circle"></i> 今日已签到
                </button>
            <?php endif; ?>
            
            <?php if (!empty($sign_in_message)): ?>
                <div class="message <?php echo $sign_in_success ? 'success-message' : 'error-message'; ?> show">
                    <?php echo $sign_in_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="check-in-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $check_in_stats['total_days']; ?></div>
                    <div class="stat-label">总签到天数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $check_in_stats['consecutive_days']; ?></div>
                    <div class="stat-label">连续签到天数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $check_in_stats['total_points']; ?></div>
                    <div class="stat-label">累计获得积分</div>
                </div>
            </div>
            
            <?php if ($sign_in_success || $has_checked_in): ?>
                <div class="mall-entry">
                    <h3><i class="fas fa-shopping-bag"></i> 积分商城</h3>
                    <p>使用您的积分兑换心仪的商品吧！</p>
                    <a href="shop.php" class="mall-btn">
                        <i class="fas fa-store"></i> 前往积分商城
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <nav>
            <ul>
                <li><a href="index.php" class="active"><i class="fas fa-calendar-check" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>签到</a></li>
                <li><a href="shop.php"><i class="fas fa-shopping-bag" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>商品</a></li>
                <li><a href="messages.php"><i class="fas fa-comment-dots" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>消息</a></li>
                <li><a href="user_center.php"><i class="fas fa-user" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>我的</a></li>
            </ul>
        </nav>
    </div>
</body>
</html>