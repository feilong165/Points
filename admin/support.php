<?php
// 检查管理员是否登录
require_once '../app/app.php';
if (!is_admin_logged_in()) {
    header('Location: login.php');
    exit;
}

// 处理操作
if (isset($_GET['action'])) {
    $action = safe_get('action', '', 'string');
    switch ($action) {
        case 'reply':
            // 回复消息
            if (isset($_POST['conversation_id']) && isset($_POST['content'])) {
                $conversation_id = safe_post('conversation_id', 0, 'int');
                $content = safe_post('content', '', 'string');
                $admin_id = $_SESSION['admin_id'];
                
                if ($conversation_id > 0 && !empty($content)) {
                    $result = send_support_message($conversation_id, 'admin', $admin_id, $content);
                    
                    // 标记所有用户消息为已读
                    mark_conversation_as_read($conversation_id, 'user');
                    
                    // 重定向回对话详情页
                    header('Location: support.php?action=view&id=' . $conversation_id . '&msg=' . ($result['success'] ? '回复成功' : $result['message']));
                    exit;
                } else {
                    header('Location: support.php?msg=参数错误');
                    exit;
                }
            }
            break;
        case 'close':
            // 关闭对话
            if (isset($_GET['id'])) {
                $conversation_id = safe_get('id', 0, 'int');
                if ($conversation_id > 0) {
                    $result = close_support_conversation($conversation_id);
                    header('Location: support.php?msg=' . ($result['success'] ? '对话已关闭' : $result['message']));
                    exit;
                }
            }
            header('Location: support.php?msg=参数错误');
            exit;
            break;
        case 'mark_read':
            // 标记对话为已读
            if (isset($_GET['id'])) {
                $conversation_id = safe_get('id', 0, 'int');
                if ($conversation_id > 0) {
                    mark_conversation_as_read($conversation_id, 'user');
                    header('Location: support.php?action=view&id=' . $conversation_id);
                    exit;
                }
            }
            header('Location: support.php?msg=参数错误');
            exit;
            break;
    }
}

// 获取对话列表或详情
if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])) {
    // 获取对话详情
    $conversation_id = intval($_GET['id']);
    $conversation = null;
    $conversations = get_support_conversations(null, null);
    foreach ($conversations as $conv) {
        if ($conv['id'] == $conversation_id) {
            $conversation = $conv;
            break;
        }
    }
    
    if (!$conversation) {
        header('Location: support.php?msg=对话不存在');
        exit;
    }
    
    // 标记对话为已读
    mark_conversation_as_read($conversation_id, 'user');
    
    // 获取对话消息
    $messages = get_conversation_messages($conversation_id);
} else {
    // 获取对话列表 - 安全地处理筛选参数
$filter_status = safe_get('status', 'all', 'string');

// 验证筛选状态值
$valid_statuses = ['all', 'open', 'closed'];
if (!in_array($filter_status, $valid_statuses)) {
    $filter_status = 'all';
}

if ($filter_status == 'open') {
    $conversations = get_support_conversations(null, 0);
} elseif ($filter_status == 'closed') {
    $conversations = get_support_conversations(null, 1);
} else {
    $conversations = get_support_conversations();
}
}

// 计算未读消息总数
$unread_total = 0;
foreach ($conversations as $conv) {
    $unread_total += intval($conv['unread_count']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客服管理 - 积分商城管理系统</title>
    <link rel="stylesheet" href="css/common.css">
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 220px;
            background-color: #2c3e50;
            color: white;
            padding: 20px;
        }
        .sidebar h2 {
            margin-top: 0;
            margin-bottom: 30px;
            text-align: center;
        }
        .sidebar ul {
            list-style-type: none;
            padding: 0;
        }
        .sidebar ul li {
            margin-bottom: 10px;
        }
        .sidebar ul li a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .sidebar ul li a:hover {
            background-color: #34495e;
        }
        .content {
            flex: 1;
            padding: 20px;
        }
        .header {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .header .logout {
            color: #f44336;
            text-decoration: none;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            margin: 5px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-primary {
            background-color: #2196F3;
        }
        .btn-primary:hover {
            background-color: #0b7dda;
        }
        .btn-danger {
            background-color: #f44336;
        }
        .btn-danger:hover {
            background-color: #da190b;
        }
        .btn-secondary {
            background-color: #9e9e9e;
        }
        .btn-secondary:hover {
            background-color: #757575;
        }
        .filter-buttons {
            margin-bottom: 20px;
            text-align: center;
        }
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-open {
            background-color: #4CAF50;
            color: white;
        }
        .status-closed {
            background-color: #9e9e9e;
            color: white;
        }
        .message-unread {
            background-color: #e3f2fd;
            font-weight: bold;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .message-form {
            margin-top: 20px;
            padding: 20px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
        }
        textarea {
            width: 100%;
            height: 100px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            margin-bottom: 10px;
        }
        .message-thread {
            margin-top: 20px;
            padding: 20px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            max-height: 500px;
            overflow-y: auto;
        }
        .message-item {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
            position: relative;
        }
        .message-user {
            background-color: #f1f1f1;
            margin-right: 100px;
        }
        .message-admin {
            background-color: #e3f2fd;
            margin-left: 100px;
        }
        .message-header {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .message-content {
            word-wrap: break-word;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 16px;
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .back-btn:hover {
            background-color: #0b7dda;
        }
        .conversation-header {
            margin-bottom: 20px;
            padding: 15px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
        }
        .conversation-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .user-info {
            font-size: 16px;
            font-weight: bold;
        }
        .unread-badge {
            display: inline-block;
            background-color: #f44336;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            font-size: 12px;
            margin-left: 5px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .form-actions {
            text-align: right;
        }
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            .conversation-info {
                flex-direction: column;
                align-items: flex-start;
            }
            .btn {
                display: block;
                width: 100%;
                margin: 5px 0;
                text-align: center;
            }
            .message-user, .message-admin {
                margin: 0;
            }
            .table-container {
                font-size: 14px;
            }
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <h2>管理后台</h2>
            <ul>
                <li><a href="dashboard.php">控制面板</a></li>
                <li><a href="users.php">用户管理</a></li>
                <li><a href="products.php">商品管理</a></li>
                <li><a href="categories.php">分类管理</a></li>
                <li><a href="orders.php">订单管理</a></li>
                <li><a href="coupons.php">优惠券管理</a></li>
                <li><a href="codes.php">兑换码管理</a></li>
                <li><a href="notifications.php">通知管理</a></li>
                <li><a href="support.php">客服管理</a></li>
                <li><a href="banners.php">Banner管理</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">搜索框管理</a>
                    <ul class="dropdown-content">
                        <li><a href="search_settings.php">搜索设置</a></li>
                        <li><a href="search_play_settings.php">玩法设置</a></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">运营积木</a>
                    <ul class="dropdown-content">
                        <li><a href="appointment.php">预约模块</a></li>
                        <li><a href="topics.php">专题页模块</a></li>
                        <li><a href="special_events.php">特别活动模块</a></li>
                    </ul>
                </li>
                <li><a href="logout.php">退出登录</a></li>
            </ul>
        </aside>
        
        <style>
            .dropdown {
                position: relative;
                margin-bottom: 10px;
            }
            .dropdown-toggle {
                display: block;
                padding: 10px;
                color: white;
                text-decoration: none;
                background-color: transparent;
                border: none;
                cursor: pointer;
                width: 100%;
                text-align: left;
                border-radius: 5px;
            }
            .dropdown-toggle:hover {
                background-color: #34495e;
            }
            .dropdown-content {
                display: none;
                position: static;
                background-color: #34495e;
                width: 100%;
                box-shadow: none;
                z-index: 1;
                list-style-type: none;
                padding: 0;
                margin: 5px 0 5px 20px;
            }
            .dropdown:hover .dropdown-content {
                display: block;
            }
            .dropdown-content li {
                margin-bottom: 5px;
                border-bottom: none;
            }
            .dropdown-content a {
                display: block;
                padding: 8px 10px;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-size: 14px;
            }
            .dropdown-content a:hover {
                background-color: #2c3e50;
            }
        </style>
        
        <main class="content">
            <div class="header">
                <h1>客服管理</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
        
        <!-- 消息提示 -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="success-message"><?php echo htmlspecialchars(safe_get('msg', '')); ?></div>
        <?php endif; ?>
        
        <!-- 对话详情页面 -->
        <?php if (isset($conversation)): ?>
            <a href="support.php" class="back-btn">← 返回对话列表</a>
            
            <div class="conversation-header">
                <div class="conversation-info">
                    <div class="user-info">
                        用户：<?php echo $conversation['username']; ?>
                        <span style="font-weight: normal; margin-left: 10px;">电话：<?php echo $conversation['phone']; ?></span>
                    </div>
                    <div>
                        <span class="status-badge status-<?php echo $conversation['is_closed'] ? 'closed' : 'open'; ?>">
                            <?php echo $conversation['is_closed'] ? '已关闭' : '进行中'; ?>
                        </span>
                        <?php if (!$conversation['is_closed']): ?>
                            <a href="support.php?action=close&id=<?php echo $conversation['id']; ?>&confirm=1" class="btn btn-danger" onclick="return confirm('确定要关闭此对话吗？');">关闭对话</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 对话消息列表 -->
            <div class="message-thread">
                <?php foreach ($messages as $message): ?>
                    <?php 
                    // 使用 sender_id 和 sender_type 双重验证来确保正确显示发送者身份
                    $display_sender_type = 'admin';
                    $display_sender_name = $message['sender_name'];
                    
                    // 如果 sender_type 是 'user' 或者 sender_id 不为管理员ID，显示为用户消息
                    if ($message['sender_type'] === 'user' || $message['sender_id'] != $_SESSION['admin_id']) {
                        $display_sender_type = 'user';
                        // 确保用户名称正确显示
                        if (!isset($conversation['username']) || $conversation['username'] === '管理员') {
                            $display_sender_name = $message['sender_name'] === '管理员' ? '用户' : $message['sender_name'];
                        }
                    }
                    ?>
                    <div class="message-item message-<?php echo $display_sender_type; ?>">
                        <div class="message-header">
                            <?php echo $display_sender_name; ?> · <?php echo date('Y-m-d H:i:s', strtotime($message['created_at'])); ?>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 回复表单 -->
            <?php if (!$conversation['is_closed']): ?>
                <form class="message-form" method="post" action="support.php?action=reply">
                    <input type="hidden" name="conversation_id" value="<?php echo $conversation['id']; ?>">
                    <textarea name="content" placeholder="请输入回复内容..." required></textarea>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">发送回复</button>
                    </div>
                </form>
            <?php endif; ?>
        
        <!-- 对话列表页面 -->
        <?php else: ?>
            <!-- 筛选按钮 -->
            <div class="filter-buttons">
                <a href="support.php" class="btn <?php echo $filter_status == 'all' ? 'btn-primary' : ''; ?>">
                    全部对话 (<?php echo count($conversations); ?>)
                </a>
                <a href="support.php?status=open" class="btn <?php echo $filter_status == 'open' ? 'btn-primary' : ''; ?>">
                    进行中 (<?php 
                        $open_count = 0;
                        foreach ($conversations as $conv) {
                            if (!$conv['is_closed']) $open_count++;
                        }
                        echo $open_count;
                    ?>)
                </a>
                <a href="support.php?status=closed" class="btn <?php echo $filter_status == 'closed' ? 'btn-primary' : ''; ?>">
                    已关闭 (<?php 
                        $closed_count = 0;
                        foreach ($conversations as $conv) {
                            if ($conv['is_closed']) $closed_count++;
                        }
                        echo $closed_count;
                    ?>)
                </a>
                <span style="margin-left: 20px; color: #f44336; font-weight: bold;">
                    未读消息：<?php echo $unread_total; ?>
                </span>
            </div>
            
            <!-- 对话列表 -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户</th>
                            <th>电话</th>
                            <th>最后消息</th>
                            <th>状态</th>
                            <th>未读</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conversations)): ?>
                            <tr>
                                <td colspan="8" class="no-data">暂无对话记录</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($conversations as $conversation): ?>
                                <tr class="<?php echo $conversation['unread_count'] > 0 ? 'message-unread' : ''; ?>">
                                    <td><?php echo $conversation['id']; ?></td>
                                    <td><?php echo $conversation['username']; ?></td>
                                    <td><?php echo $conversation['phone']; ?></td>
                                    <td style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($conversation['last_message']); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $conversation['is_closed'] ? 'closed' : 'open'; ?>">
                                            <?php echo $conversation['is_closed'] ? '已关闭' : '进行中'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                        <?php else: ?>
                                            -  
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($conversation['created_at'])); ?></td>
                                    <td>
                                        <a href="support.php?action=view&id=<?php echo $conversation['id']; ?>" class="btn btn-primary">查看</a>
                                        <?php if (!$conversation['is_closed']): ?>
                                            <a href="support.php?action=close&id=<?php echo $conversation['id']; ?>" class="btn btn-danger" onclick="return confirm('确定要关闭此对话吗？');">关闭</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </main>
    </div>
</body>
</html>