<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 检查管理员是否登录
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';
$user_list = [];

// 获取用户列表
function get_all_users() {
    $conn = get_db_connection();
    $sql = "SELECT id, username, phone FROM users ORDER BY created_at DESC";
    $result = $conn->query($sql);
    $users = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    $conn->close();
    return $users;
}

$user_list = get_all_users();

// 处理发送通知请求
if (isset($_POST['send_notification'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $target_type = $_POST['target_type'];
    
    if (empty($title) || empty($content)) {
        $error = "标题和内容不能为空";
    } else {
        if ($target_type == 'all') {
            // 发送给所有用户
            $user_ids = array_column($user_list, 'id');
            $result = send_message_to_multiple_users($user_ids, $title, $content);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } else if ($target_type == 'selected') {
            // 发送给选中的用户
            if (isset($_POST['selected_users']) && !empty($_POST['selected_users'])) {
                $user_ids = $_POST['selected_users'];
                $result = send_message_to_multiple_users($user_ids, $title, $content);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = "请至少选择一个用户";
            }
        }
    }
}

// 处理查看通知记录请求
$notifications = [];
if (isset($_GET['view_records'])) {
    $conn = get_db_connection();
    $sql = "SELECT m.*, u.username, u.phone 
            FROM messages m 
            JOIN users u ON m.user_id = u.id 
            ORDER BY m.created_at DESC 
            LIMIT 100";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发送通知 - 积分系统</title>
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
        .notification-form {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .notification-form h2 {
            margin-top: 0;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-view {
            background-color: #2196F3;
        }
        .btn-view:hover {
            background-color: #1976D2;
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
        .user-selection {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 10px;
        }
        .user-selection label {
            display: block;
            margin-bottom: 8px;
            cursor: pointer;
        }
        .user-selection input[type="checkbox"] {
            margin-right: 8px;
        }
        .notifications-table {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .notifications-table h2 {
            margin-top: 0;
            color: #333;
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
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            color: #3498db;
            text-decoration: none;
            padding: 8px 16px;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .pagination a:hover {
            background-color: #f1f1f1;
        }
        .pagination .current {
            background-color: #3498db;
            color: white;
            border: 1px solid #3498db;
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
                <li><a href="notifications.php" style="background-color: #34495e;">通知管理</a></li>
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
                <h1>发送通知</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="notification-form">
                <h2>发送新通知</h2>
                <form method="POST" action="notifications.php">
                    <div class="form-group">
                        <label for="title">通知标题</label>
                        <input type="text" id="title" name="title" placeholder="请输入通知标题" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="content">通知内容</label>
                        <textarea id="content" name="content" placeholder="请输入通知内容" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="target_type">发送对象</label>
                        <select id="target_type" name="target_type" required onchange="toggleUserSelection()">
                            <option value="all">所有用户</option>
                            <option value="selected">选中用户</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="userSelectionContainer" style="display: none;">
                        <label>选择用户</label>
                        <div class="user-selection">
                            <?php foreach ($user_list as $user): ?>
                                <label>
                                    <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>">
                                    <?php echo $user['username']; ?> (<?php echo $user['phone']; ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="send_notification" class="btn">发送通知</button>
                    <a href="notifications.php?view_records=1" class="btn btn-view">查看发送记录</a>
                </form>
            </div>
            
            <?php if (isset($_GET['view_records'])): ?>
                <div class="notifications-table">
                    <h2>通知发送记录</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>接收用户</th>
                                <th>标题</th>
                                <th>内容</th>
                                <th>是否已读</th>
                                <th>发送时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td><?php echo $notification['id']; ?></td>
                                        <td><?php echo $notification['username']; ?> (<?php echo $notification['phone']; ?>)</td>
                                        <td><?php echo $notification['title']; ?></td>
                                        <td><?php echo $notification['content']; ?></td>
                                        <td><?php echo $notification['is_read'] ? '已读' : '未读'; ?></td>
                                        <td><?php echo $notification['created_at']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">暂无通知记录</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // 切换用户选择框的显示状态
        function toggleUserSelection() {
            const targetType = document.getElementById('target_type').value;
            const userSelectionContainer = document.getElementById('userSelectionContainer');
            
            if (targetType === 'selected') {
                userSelectionContainer.style.display = 'block';
            } else {
                userSelectionContainer.style.display = 'none';
            }
        }
    </script>
</body>
</html>