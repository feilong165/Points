<?php
// 引入配置和应用核心文件
require_once 'app/config.php';
require_once 'app/app.php';

// 检查用户是否登录
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 获取用户信息
$user = get_user_info($user_id);

// 获取订单记录（包含兑换码信息）
$conn = get_db_connection();
$orders_sql = "SELECT orders.*, products.name AS product_name, codes.code 
               FROM orders 
               LEFT JOIN products ON orders.product_id = products.id 
               LEFT JOIN codes ON orders.code_id = codes.id 
               WHERE orders.user_id = ? 
               ORDER BY orders.created_at DESC LIMIT 30";
$stmt = $conn->prepare($orders_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户中心 - 积分系统</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
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
        .container {
            padding-bottom: 80px; /* 为底部导航栏留出空间 */
        }
        .content {
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .user-info {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        .section h3 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background-color: #f2f2f2;
        }
        table tr:hover {
            background-color: #f5f5f5;
        }
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .shop-link {
            text-align: center;
            margin-top: 30px;
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
        
        /* 优惠券样式 */
        .coupons-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .coupon-tab {
            flex: 1;
            padding: 10px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            color: #666;
            border-bottom: 2px solid transparent;
        }
        .coupon-tab.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
        }
        .coupons-container {
            margin-top: 10px;
        }
        .coupons-content {
            display: block;
        }
        .coupons-content.hidden {
            display: none;
        }
        .coupons-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .coupon-card {
            display: flex;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            transition: all 0.3s ease;
        }
        .coupon-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .coupon-left {
            flex: 0 0 120px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px 0;
        }
        .discount-value {
            font-size: 24px;
            font-weight: bold;
        }
        .min-order {
            font-size: 12px;
            margin-top: 5px;
        }
        .coupon-right {
            flex: 1;
            padding: 15px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .coupon-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
            color: #333;
        }
        .coupon-desc {
            font-size: 14px;
            color: #666;
        .logout-btn {
            background-color: #f44336;
            padding: 12px 40px;
        }
        .logout-btn:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>积分系统 - 用户中心</h1>
        </header>
        
        <!-- 引入 Font Awesome 图标库 -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        
        <style>
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
            nav ul li a {
                padding-top: 10px;
            }
        </style>
        
        <div class="content">
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="user-info">
                <h2>欢迎回来，<?php echo $user['username']; ?></h2>
                <p>注册时间：<?php echo $user['created_at']; ?></p>
            </div>
            
            <!-- 积分明细入口 -->
            <div class="section">
                <a href="points_history.php" style="display: block; text-decoration: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center;">
                            <div style="margin-right: 15px; font-size: 20px; color: #1976d2;">
                                <i class="fas fa-diamond"></i>
                            </div>
                            <div>
                                <div style="font-size: 16px; font-weight: bold; color: #333;">积分余额</div>
                                <div style="font-size: 14px; color: #999;">当前积分: <?php echo $user['points']; ?></div>
                            </div>
                        </div>
                        <div style="font-size: 16px; color: #ccc;">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="section">
                <h3>兑换记录</h3>
                <table>
                    <tr>
                        <th>订单号</th>
                        <th>商品名称</th>
                        <th>消耗积分</th>
                        <th>兑换时间</th>
                        <th>订单状态</th>
                        <th>兑换码</th>
                    </tr>
                    <?php if ($orders_result->num_rows > 0): ?>
                        <?php while ($row = $orders_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo $row['product_name']; ?></td>
                                <td>-<?php echo $row['points_used']; ?></td>
                                <td><?php echo $row['created_at']; ?></td>
                                <td>
                                    <?php 
                                        switch ($row['status']) {
                                            case 0: echo '待处理'; break;
                                            case 1: echo '已完成'; break;
                                            case 2: echo '已取消'; break;
                                            default: echo '未知状态';
                                        }
                                    ?>
                                </td>
                                <td><?php echo !empty($row['code']) ? '<strong>' . $row['code'] . '</strong>' : '-'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">暂无兑换记录</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- 优惠券入口 -->
            <div class="section">
                <a href="coupons.php" style="display: block; text-decoration: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background-color: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center;">
                            <div style="margin-right: 15px; font-size: 20px;">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div>
                                <div style="font-size: 16px; font-weight: bold; color: #333;">我的优惠券</div>
                                <?php $available_coupons = get_user_coupons($user_id, 'available'); ?>
                                <div style="font-size: 14px; color: #999;">可用优惠券: <?php echo count($available_coupons); ?></div>
                            </div>
                        </div>
                        <div style="font-size: 16px; color: #ccc;">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="logout-section" style="margin-top: 30px; text-align: center;">
                <a href="logout.php" class="btn logout-btn">退出登录</a>
            </div>
            
            <div class="shop-link">
                <a href="shop.php" class="btn">前往积分商城</a>
            </div>
        </div>
        
        <nav>
            <ul>
                <li><a href="index.php"><i class="fas fa-calendar-check" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>签到</a></li>
                <li><a href="shop.php"><i class="fas fa-shopping-bag" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>商品</a></li>
                <li><a href="messages.php"><i class="fas fa-comment-dots" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>
                    消息
                    <?php
                    // 获取未读消息数量
                    $unread_messages = get_user_messages($user_id, 0);
                    $unread_count = count($unread_messages);
                    if ($unread_count > 0): 
                    ?>
                        <span class="notification-count" style="position: absolute; top: 5px; right: 15px; background-color: #f44336; color: white; font-size: 10px; padding: 1px 4px; border-radius: 8px;"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="user_center.php" class="active"><i class="fas fa-user" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>我的</a></li>
            </ul>
        </nav>
        

    </div>
</body>
</html>