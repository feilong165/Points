<?php
// 消息中心页面

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

// 获取未读消息数量
$unread_messages = get_user_messages($user_id, 0);
$unread_count = count($unread_messages);

// 处理标记消息为已读
if (isset($_GET['mark_read'])) {
    $message_id = intval($_GET['mark_read']);
    mark_message_as_read($message_id);
    header("Location: messages.php");
    exit;
}

// 获取所有消息
$messages = get_user_messages($user_id);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>消息中心 - 积分系统</title>
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
        .container {
            padding-bottom: 80px; /* 为底部导航栏留出空间 */
        }
        .content {
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .message-container {
            margin-bottom: 16px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 16px;
            background-color: white;
            transition: all 0.3s ease;
        }
        .message-container:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        .message-container:last-child {
            margin-bottom: 0;
        }
        .message-row {
            display: flex;
            align-items: flex-start;
        }
        .message-icon {
            margin-right: 12px;
            margin-top: 2px;
        }
        .notification-bell {
            font-size: 20px;
        }
        .message-content-wrapper {
            flex: 1;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .message-title {
            font-weight: bold;
            font-size: 16px;
            color: #333;
            display: flex;
            align-items: center;
        }
        .message-unread {
            background-color: #f0f9ff;
            border-left: 4px solid #4CAF50;
        }
        .message-time {
            color: #999;
            font-size: 12px;
        }
        .message-content {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }
        .coupon-code {
            background-color: #fff9e6;
            padding: 8px 12px;
            border-radius: 6px;
            margin: 8px 0;
            font-family: monospace;
            font-size: 14px;
            color: #e67e22;
            word-break: break-all;
        }
        .customer-service-btn {
            position: fixed;
            bottom: 80px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: #4CAF50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
            transition: all 0.3s ease;
            z-index: 999;
        }
        .customer-service-btn:hover {
            background-color: #45a049;
            transform: scale(1.1);
        }
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 3px;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-sm {
            padding: 4px 12px;
            font-size: 12px;
        }
        .unread-badge {
            background-color: #f44336;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
        }
        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        .badge-danger {
            color: #fff;
            background-color: #dc3545;
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
        .no-messages {
            text-align: center;
            color: #999;
            padding: 40px 0;
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
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>积分系统 - 消息中心</h1>
        </header>
        
        <!-- 引入 Font Awesome 图标库 -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        
        <nav>
            <ul>
                <li><a href="index.php"><i class="fas fa-home" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>首页</a></li>
                <li><a href="shop.php"><i class="fas fa-shopping-bag" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>商品</a></li>
                <li><a href="messages.php" class="active"><i class="fas fa-comment-dots" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>
                    消息
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-count"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="user_center.php"><i class="fas fa-user" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>我的</a></li>
            </ul>
        </nav>
        
        <div class="content">
            <h2>我的消息</h2>
            
            <div class="tabs">
                <div class="tab active" data-tab="all">全部消息</div>
                <div class="tab" data-tab="unread">
                    未读消息
                    <?php if ($unread_count > 0): ?>
                        <span class="badge badge-danger"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($messages)): ?>
                <div class="no-messages">
                    <p>您暂无消息</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message-container <?php if ($message['is_read'] == 0) echo 'message-unread'; ?>">
                        <div class="message-row">
                            <div class="message-icon">
                                <span class="notification-bell">🔔</span>
                            </div>
                            <div class="message-content-wrapper">
                                <div class="message-header">
                                    <div class="message-title">
                                        <?php echo $message['title']; ?>
                                        <span style="color: #ff6b6b; font-size: 12px; margin-left: 8px;">[官方]</span>
                                        <?php if ($message['is_read'] == 0): ?>
                                            <span class="unread-badge">未读</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="message-time"><?php echo $message['created_at']; ?></span>
                                </div>
                                <div class="message-content">
                                    <?php 
                                        // 检查是否包含兑换码信息
                                        $content = $message['content'];
                                        // 如果内容中包含兑换码格式，进行特殊处理
                                        if (strpos($content, '兑换码：') !== false || strpos($content, '激活码：') !== false) {
                                            // 简单的兑换码提取逻辑，实际应用中可能需要更复杂的正则匹配
                                            $parts = explode('兑换码：', $content);
                                            if (count($parts) > 1) {
                                                echo $parts[0];
                                                echo '<div class="coupon-code">兑换码：' . $parts[1] . '</div>';
                                            } else {
                                                $parts = explode('激活码：', $content);
                                                if (count($parts) > 1) {
                                                    echo $parts[0];
                                                    echo '<div class="coupon-code">激活码：' . $parts[1] . '</div>';
                                                } else {
                                                    echo $content;
                                                }
                                            }
                                        } else {
                                            echo $content;
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($message['is_read'] == 0): ?>
                            <div style="text-align: right; margin-top: 10px;">
                                <a href="messages.php?mark_read=<?php echo $message['id']; ?>">
                                    <button class="btn btn-sm">标记为已读</button>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- 客服入口 -->
        <a href="customer_service.php" class="customer-service-btn" title="联系客服">
            💬
        </a>
        

    </div>
    
    <script>
        // 标签页切换功能
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // 移除所有标签的active类
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                // 添加当前标签的active类
                tab.classList.add('active');
                
                // 这里可以根据需要添加筛选消息的逻辑
                const tabType = tab.getAttribute('data-tab');
                const messages = document.querySelectorAll('.message-container');
                
                messages.forEach(message => {
                    if (tabType === 'all') {
                        message.style.display = 'block';
                    } else if (tabType === 'unread') {
                        if (message.classList.contains('message-unread')) {
                            message.style.display = 'block';
                        } else {
                            message.style.display = 'none';
                        }
                    }
                });
            });
        });
    </script>
    
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-calendar-check" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>签到</a></li>
            <li><a href="shop.php"><i class="fas fa-shopping-bag" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>商品</a></li>
            <li><a href="messages.php" class="active"><i class="fas fa-comment-dots" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>消息</a></li>
            <li><a href="user_center.php"><i class="fas fa-user" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>我的</a></li>
        </ul>
    </nav>
</body>
</html>