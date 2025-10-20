<?php
// 优惠券页面

// 引入配置和应用核心文件
require_once 'app/config.php';
require_once 'app/app.php';

// 检查用户是否已登录
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_info = get_user_info($user_id);

// 处理兑换优惠券
$exchange_message = '';
$exchange_success = false;

if (isset($_POST['exchange_coupon'])) {
    $coupon_id = intval($_POST['coupon_id']);
    $result = exchange_coupon($user_id, $coupon_id);
    
    if ($result['success']) {
        $exchange_message = $result['message'];
        $exchange_success = true;
        // 更新会话中的积分
        $_SESSION['points'] = get_user_info($user_id)['points'];
    } else {
        $exchange_message = $result['message'];
    }
}

// 处理通过兑换码兑换优惠券
if (isset($_POST['exchange_code'])) {
    $code = trim($_POST['coupon_code']);
    $result = exchange_coupon_by_code($user_id, $code);
    
    if ($result['success']) {
        $exchange_message = $result['message'];
        $exchange_success = true;
        // 更新会话中的积分
        $_SESSION['points'] = get_user_info($user_id)['points'];
    } else {
        $exchange_message = $result['message'];
    }
}

// 获取优惠券列表
$available_coupons = get_coupons();
$user_available_coupons = get_user_coupons($user_id, 'available');
$user_used_coupons = get_user_coupons($user_id, 'used');
$user_expired_coupons = get_user_coupons($user_id, 'expired');

// 获取未读消息数量
$unread_messages = get_user_messages($user_id, 0);
$unread_count = count($unread_messages);

// 获取当前标签页
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'available';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的优惠券 - 积分系统</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
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
        .coupon-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 20px;
        }
        .coupon-card {
            display: flex;
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .coupon-card:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .coupon-left {
            width: 120px;
            background-color: #ff6b6b;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 20px 10px;
        }
        .coupon-right {
            flex: 1;
            padding: 20px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .coupon-left::after {
            content: '';
            position: absolute;
            top: 0;
            right: -10px;
            width: 20px;
            height: 100%;
            background-color: #ff6b6b;
            border-radius: 0 50% 50% 0;
        }
        .coupon-card.used {
            opacity: 0.6;
        }
        .coupon-card.expired {
            opacity: 0.6;
        }
        .coupon-discount {
            font-size: 36px;
            font-weight: bold;
            color: #f44336;
            margin-bottom: 10px;
        }
        .coupon-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .coupon-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .coupon-conditions {
            color: #999;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .coupon-expiry {
            color: #f44336;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .btn-disabled:hover {
            background-color: #cccccc;
        }
        .btn-primary {
            background-color: #4CAF50;
        }
        .btn-primary:hover {
            background-color: #45a049;
        }
        .btn-outline {
            background-color: transparent;
            border: 1px solid #4CAF50;
            color: #4CAF50;
        }
        .btn-outline:hover {
            background-color: #4CAF50;
            color: white;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        .tab.active {
            border-bottom: 2px solid #4CAF50;
            color: #4CAF50;
            font-weight: bold;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .no-coupons {
            text-align: center;
            color: #999;
            padding: 60px 0;
            width: 100%;
        }
        .coupon-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #ff9800;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        .coupon-tag.used {
            background-color: #999;
        }
        .coupon-tag.expired {
            background-color: #f44336;
        }
        .exchange-form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .exchange-form input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            flex: 1;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s ease;
        }
        .exchange-form input:focus {
            border-color: #ff6b6b;
        }
        .notification-icon {
            position: relative;
            display: inline-block;
        }
        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #f44336;
            color: white;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }
        .footer {
            text-align: center;
            padding: 20px 0;
            color: #999;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .coupon-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>积分系统 - 我的优惠券</h1>
        </header>
        
        <nav>
            <ul>
                <li><a href="index.php">首页</a></li>
                <li><a href="user_center.php">用户中心</a></li>
                <li><a href="shop.php">积分商城</a></li>
                <li><a href="messages.php">
                    消息中心
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-count" style="position: absolute; top: 5px; right: 15px; background-color: #f44336; color: white; font-size: 10px; padding: 1px 4px; border-radius: 8px;"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="logout.php">退出登录</a></li>
            </ul>
        </nav>
        
        <div class="content">
            <h2>我的优惠券</h2>
            
            <!-- 兑换码输入框 -->
            <div class="exchange-form">
                <input type="text" id="coupon_code" placeholder="输入兑换码获取优惠券" required>
                <button id="exchange_code_btn" class="btn btn-primary">兑换优惠券</button>
            </div>
            
            <!-- 兑换优惠券大按钮 -->
            <div style="text-align: center; margin: 25px 0;">
                <button class="btn btn-primary" style="padding: 18px 60px; font-size: 18px; background-color: #ff6b6b; border: none; border-radius: 28px; font-weight: bold; box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3); transition: all 0.3s ease;">
                    兑换优惠券
                </button>
            </div>
            
            <!-- 兑换消息 -->
            <?php if (!empty($exchange_message)): ?>
                <div class="alert <?php echo $exchange_success ? 'alert-success' : 'alert-danger'; ?>">
                    <?php echo $exchange_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- 标签页 -->
            <div class="tabs">
                <div class="tab <?php echo $active_tab == 'available' ? 'active' : ''; ?>" data-tab="available">
                    可用优惠券 (<?php echo count($user_available_coupons); ?>)
                </div>
                <div class="tab <?php echo $active_tab == 'used' ? 'active' : ''; ?>" data-tab="used">
                    已使用优惠券 (<?php echo count($user_used_coupons); ?>)
                </div>
                <div class="tab <?php echo $active_tab == 'expired' ? 'active' : ''; ?>" data-tab="expired">
                    已过期优惠券 (<?php echo count($user_expired_coupons); ?>)
                </div>
                <div class="tab <?php echo $active_tab == 'all' ? 'active' : ''; ?>" data-tab="all">
                    全部优惠券
                </div>
            </div>
            
            <!-- 优惠券列表 -->
            <div class="coupon-container">
                <?php if ($active_tab == 'available' || $active_tab == 'all'): ?>
                    <?php if (empty($user_available_coupons)): ?>
                        <div class="no-coupons">
                            <p>暂无可用优惠券</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($user_available_coupons as $coupon): ?>
                            <div class="coupon-card">
                                <div class="coupon-left">
                                    <?php if (isset($coupon['points_discount']) && $coupon['points_discount'] > 0): ?>
                                        <span style="font-size: 32px; font-weight: bold;">-<?php echo $coupon['points_discount']; ?></span>
                                        <span style="font-size: 12px; margin-top: 4px;">积分券</span>
                                    <?php else: ?>
                                        <span style="font-size: 32px; font-weight: bold;">¥<?php echo $coupon['discount_value']; ?></span>
                                        <span style="font-size: 12px; margin-top: 4px;">优惠券</span>
                                    <?php endif; ?>
                                </div>
                                <div class="coupon-right">
                                    <div style="position: absolute; top: 10px; right: 10px;">
                                        <span class="coupon-tag" style="background-color: #ff6b6b;">可用</span>
                                    </div>
                                    <div>
                                        <h3 class="coupon-name" style="margin: 0 0 8px 0;"><?php echo $coupon['name']; ?></h3>
                                        <div class="coupon-conditions" style="margin-bottom: 8px;">
                                            <?php if (isset($coupon['min_points_required']) && $coupon['min_points_required'] > 0): ?>
                                                积分满<?php echo $coupon['min_points_required']; ?>可用
                                            <?php else: ?>
                                                订单满<?php echo $coupon['min_order_amount']; ?>元可用
                                            <?php endif; ?>
                                        </div>
                                        <div class="coupon-expiry" style="margin-bottom: 12px;">
                                            有效期至: <?php echo $coupon['end_time']; ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <button class="btn btn-outline" style="background-color: #ff6b6b; color: white; border: none;">立即使用</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($active_tab == 'used' || $active_tab == 'all'): ?>
                    <?php if (empty($user_used_coupons)): ?>
                        <div class="no-coupons">
                            <p>暂无已使用优惠券</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($user_used_coupons as $coupon): ?>
                            <div class="coupon-card">
                                <div class="coupon-left" style="background-color: #999;">
                                    <?php if (isset($coupon['points_discount']) && $coupon['points_discount'] > 0): ?>
                                        <span style="font-size: 32px; font-weight: bold;">-<?php echo $coupon['points_discount']; ?></span>
                                        <span style="font-size: 12px; margin-top: 4px;">积分券</span>
                                    <?php else: ?>
                                        <span style="font-size: 32px; font-weight: bold;">¥<?php echo $coupon['discount_value']; ?></span>
                                        <span style="font-size: 12px; margin-top: 4px;">优惠券</span>
                                    <?php endif; ?>
                                </div>
                                <div class="coupon-right">
                                    <div style="position: absolute; top: 10px; right: 10px;">
                                        <span class="coupon-tag used">已使用</span>
                                    </div>
                                    <div>
                                        <h3 class="coupon-name" style="margin: 0 0 8px 0; color: #999;"><?php echo $coupon['name']; ?></h3>
                                        <div class="coupon-conditions" style="margin-bottom: 8px; color: #999;">
                                            <?php if (isset($coupon['min_points_required']) && $coupon['min_points_required'] > 0): ?>
                                                积分满<?php echo $coupon['min_points_required']; ?>可用
                                            <?php else: ?>
                                                订单满<?php echo $coupon['min_order_amount']; ?>元可用
                                            <?php endif; ?>
                                        </div>
                                        <div class="coupon-expiry" style="margin-bottom: 12px; color: #999;">
                                            使用时间: <?php echo $coupon['used_at']; ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <button class="btn btn-disabled">已使用</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($active_tab == 'expired' || $active_tab == 'all'): ?>
                    <?php if (empty($user_expired_coupons)): ?>
                        <div class="no-coupons">
                            <p>暂无已过期优惠券</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($user_expired_coupons as $coupon): ?>
                            <div class="coupon-card">
                                <div class="coupon-left" style="background-color: #f44336;">
                                    <?php if (isset($coupon['points_discount']) && $coupon['points_discount'] > 0): ?>
                                        <span style="font-size: 32px; font-weight: bold;">-<?php echo $coupon['points_discount']; ?></span>
                                        <span style="font-size: 12px; margin-top: 4px;">积分券</span>
                                    <?php else: ?>
                                        <span style="font-size: 32px; font-weight: bold;">¥<?php echo $coupon['discount_value']; ?></span>
                                        <span style="font-size: 12px; margin-top: 4px;">优惠券</span>
                                    <?php endif; ?>
                                </div>
                                <div class="coupon-right">
                                    <div style="position: absolute; top: 10px; right: 10px;">
                                        <span class="coupon-tag expired">已过期</span>
                                    </div>
                                    <div>
                                        <h3 class="coupon-name" style="margin: 0 0 8px 0; color: #999;"><?php echo $coupon['name']; ?></h3>
                                        <div class="coupon-conditions" style="margin-bottom: 8px; color: #999;">
                                            <?php if (isset($coupon['min_points_required']) && $coupon['min_points_required'] > 0): ?>
                                                积分满<?php echo $coupon['min_points_required']; ?>可用
                                            <?php else: ?>
                                                订单满<?php echo $coupon['min_order_amount']; ?>元可用
                                            <?php endif; ?>
                                        </div>
                                        <div class="coupon-expiry" style="margin-bottom: 12px; color: #999;">
                                            已于<?php echo $coupon['end_time']; ?>过期
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <button class="btn btn-disabled">已过期</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 引入 Font Awesome 图标库 -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        
        <nav>
            <ul>
                <li><a href="index.php"><i class="fas fa-calendar-check" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>签到</a></li>
                <li><a href="shop.php"><i class="fas fa-shopping-bag" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>商品</a></li>
                <li><a href="messages.php"><i class="fas fa-comment-dots" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>
                    消息
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-count"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="user_center.php" class="active"><i class="fas fa-user" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>我的</a></li>
            </ul>
        </nav>
    </div>
    
    <!-- 隐藏的表单用于提交兑换码 -->
    <form id="exchange_code_form" method="post" style="display: none;">
        <input type="hidden" id="coupon_code_hidden" name="coupon_code">
        <input type="submit" name="exchange_code">
    </form>
    
    <script>
        // 标签页切换功能
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabType = tab.getAttribute('data-tab');
                window.location.href = 'coupons.php?tab=' + tabType;
            });
        });
        
        // 兑换码提交
        document.getElementById('exchange_code_btn').addEventListener('click', function() {
            const code = document.getElementById('coupon_code').value.trim();
            if (code) {
                document.getElementById('coupon_code_hidden').value = code;
                document.getElementById('exchange_code_form').submit();
            } else {
                alert('请输入兑换码');
            }
        });
        
        // 回车键提交兑换码
        document.getElementById('coupon_code').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('exchange_code_btn').click();
            }
        });
    </script>
</body>
</html>