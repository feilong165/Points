<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 检查管理员是否登录
// 使用统一的登录验证函数
session_start();
if (!is_admin_logged_in()) {
    header("Location: login.php");
    exit;
}

// 创建特别活动表
function create_special_events_table() {
    $conn = get_db_connection();
    $sql = "CREATE TABLE IF NOT EXISTS special_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        reward_type ENUM('points', 'code') NOT NULL,
        reward_value INT DEFAULT 0,
        reward_code VARCHAR(255) DEFAULT NULL,
        status BOOLEAN DEFAULT TRUE,
        max_participants INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === FALSE) {
        error_log("创建special_events表失败: " . $conn->error);
        return false;
    }
    
    // 创建特别活动参与记录表
    $sql = "CREATE TABLE IF NOT EXISTS special_event_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        participation_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES special_events(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_event_user (event_id, user_id)
    )";
    
    if ($conn->query($sql) === FALSE) {
        error_log("创建special_event_participants表失败: " . $conn->error);
        $conn->close();
        return false;
    }
    
    $conn->close();
    return true;
}

// 调用函数创建表
create_special_events_table();

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_event'])) {
        // 创建特别活动
        $name = $_POST['name'];
        $description = $_POST['description'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $reward_type = $_POST['reward_type'];
        $reward_value = $reward_type === 'points' ? $_POST['reward_value'] : 0;
        $reward_code = $reward_type === 'code' ? $_POST['reward_code'] : null;
        $status = isset($_POST['status']) ? 1 : 0;
        $max_participants = $_POST['max_participants'];
        
        $conn = get_db_connection();
        $sql = "INSERT INTO special_events (name, description, start_time, end_time, reward_type, reward_value, reward_code, status, max_participants) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssiiii", $name, $description, $start_time, $end_time, $reward_type, $reward_value, $reward_code, $status, $max_participants);
        
        if ($stmt->execute()) {
            $message = "特别活动创建成功！";
            $message_type = "success";
        } else {
            $message = "创建失败: " . $conn->error;
            $message_type = "error";
        }
        
        $stmt->close();
        $conn->close();
    } elseif (isset($_POST['update_status'])) {
        // 更新活动状态
        $id = $_POST['id'];
        $status = $_POST['status'];
        
        $conn = get_db_connection();
        $sql = "UPDATE special_events SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $status, $id);
        
        if ($stmt->execute()) {
            $message = "状态更新成功！";
            $message_type = "success";
        } else {
            $message = "更新失败: " . $conn->error;
            $message_type = "error";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// 获取所有特别活动
$conn = get_db_connection();
$sql = "SELECT se.*, COUNT(sep.id) as participant_count 
        FROM special_events se 
        LEFT JOIN special_event_participants sep ON se.id = sep.event_id 
        GROUP BY se.id 
        ORDER BY se.created_at DESC";
$result = $conn->query($sql);
$events = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}
$conn->close();

?> 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>特别活动管理 - 运营积木</title>
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
        .card {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .btn {
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-danger {
            background-color: #e74c3c;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-success {
            background-color: #2ecc71;
        }
        .btn-success:hover {
            background-color: #27ae60;
        }
        .btn-secondary {
            background-color: #95a5a6;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
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
        .actions {
            white-space: nowrap;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background-color: #28a745;
            color: white;
        }
        .status-inactive {
            background-color: #6c757d;
            color: white;
        }
        .status-upcoming {
            background-color: #17a2b8;
            color: white;
        }
        .status-ended {
            background-color: #dc3545;
            color: white;
        }
        .reward-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .form-half {
            flex: 1;
            min-width: 300px;
        }
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
        
        <main class="content">
            <div class="header">
                <h1>特别活动管理</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>创建新特别活动</h2>
                <form method="POST">
                    <div class="form-container">
                        <div class="form-half">
                            <div class="form-group">
                                <label for="name">活动名称</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="description">活动描述</label>
                                <textarea id="description" name="description"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="start_time">开始时间</label>
                                <input type="datetime-local" id="start_time" name="start_time" required>
                            </div>
                            <div class="form-group">
                                <label for="end_time">结束时间</label>
                                <input type="datetime-local" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        <div class="form-half">
                            <div class="form-group">
                                <label for="reward_type">奖励类型</label>
                                <select id="reward_type" name="reward_type" required>
                                    <option value="points">积分</option>
                                    <option value="code">兑换码</option>
                                </select>
                            </div>
                            <div class="form-group" id="points-field">
                                <label for="reward_value">积分数量</label>
                                <input type="number" id="reward_value" name="reward_value" min="1" value="100">
                            </div>
                            <div class="form-group" id="code-field" style="display: none;">
                                <label for="reward_code">兑换码</label>
                                <input type="text" id="reward_code" name="reward_code">
                            </div>
                            <div class="form-group">
                                <label for="max_participants">最大参与人数 (0表示无限制)</label>
                                <input type="number" id="max_participants" name="max_participants" min="0" value="0">
                            </div>
                            <div class="form-group checkbox" style="display: flex; align-items: center;">
                                <input type="checkbox" id="status" name="status" checked>
                                <label for="status" style="margin: 0 0 0 10px;">立即启用</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="create_event" class="btn btn-success">创建活动</button>
                </form>
            </div>
            
            <div class="card">
                <h2>特别活动列表</h2>
                <?php if (count($events) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                    <th>ID</th>
                                    <th>活动名称</th>
                                    <th>奖励</th>
                                    <th>开始时间</th>
                                    <th>结束时间</th>
                                    <th>参与人数</th>
                                    <th>状态</th>
                                    <th>访问地址</th>
                                    <th>操作</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <?php
                                    // 计算活动状态
                                    $current_time = date('Y-m-d H:i:s');
                                    if (!$event['status']) {
                                        $status_class = 'inactive';
                                        $status_text = '已禁用';
                                    } elseif ($current_time < $event['start_time']) {
                                        $status_class = 'upcoming';
                                        $status_text = '即将开始';
                                    } elseif ($current_time > $event['end_time']) {
                                        $status_class = 'ended';
                                        $status_text = '已结束';
                                    } else {
                                        $status_class = 'active';
                                        $status_text = '进行中';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $event['id']; ?></td>
                                    <td><?php echo $event['name']; ?></td>
                                    <td>
                                        <div class="reward-info">
                                            <?php if ($event['reward_type'] === 'points'): ?>
                                                <span>积分: <?php echo $event['reward_value']; ?></span>
                                            <?php else: ?>
                                                <span>兑换码: <?php echo $event['reward_code']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($event['start_time'])); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($event['end_time'])); ?></td>
                                    <td>
                                        <?php echo $event['participant_count']; ?>
                                        <?php if ($event['max_participants'] > 0): ?>
                                            / <?php echo $event['max_participants']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../special_event/index.php?id=<?php echo $event['id']; ?>" target="_blank">
                                            查看活动
                                        </a>
                                    </td>
                                    <td class="actions">
                                        <a href="edit_special_event.php?id=<?php echo $event['id']; ?>" class="btn">编辑</a>
                                        <a href="event_participants.php?id=<?php echo $event['id']; ?>" class="btn btn-secondary">查看参与记录</a>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $event['status'] ? '0' : '1'; ?>">
                                            <button type="submit" name="update_status" class="btn btn-<?php echo $event['status'] ? 'danger' : 'success'; ?>">
                                                <?php echo $event['status'] ? '禁用' : '启用'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">暂无特别活动数据</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // 奖励类型切换
        document.getElementById('reward_type').addEventListener('change', function() {
            const rewardType = this.value;
            const pointsField = document.getElementById('points-field');
            const codeField = document.getElementById('code-field');
            
            if (rewardType === 'points') {
                pointsField.style.display = 'block';
                codeField.style.display = 'none';
            } else {
                pointsField.style.display = 'none';
                codeField.style.display = 'block';
            }
        });
    </script>
</body>
</html>