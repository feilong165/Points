<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 检查管理员是否登录
session_start();
if (!is_admin_logged_in()) {
    header("Location: login.php");
    exit;
}

// 获取活动ID
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($event_id <= 0) {
    header("Location: special_events.php");
    exit;
}

// 获取活动信息
$conn = get_db_connection();
$sql = "SELECT * FROM special_events WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    $conn->close();
    header("Location: special_events.php");
    exit;
}

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 更新特别活动
    $name = $_POST['name'];
    $description = $_POST['description'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $reward_type = $_POST['reward_type'];
    $reward_value = $reward_type === 'points' ? $_POST['reward_value'] : 0;
    $reward_code = $reward_type === 'code' ? $_POST['reward_code'] : null;
    $status = isset($_POST['status']) ? 1 : 0;
    $max_participants = $_POST['max_participants'];
    
    $sql = "UPDATE special_events SET name=?, description=?, start_time=?, end_time=?, reward_type=?, reward_value=?, reward_code=?, status=?, max_participants=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssiisii", $name, $description, $start_time, $end_time, $reward_type, $reward_value, $reward_code, $status, $max_participants, $event_id);
    
    if ($stmt->execute()) {
        $message = "特别活动更新成功！";
        $message_type = "success";
        // 刷新活动数据
        $event['name'] = $name;
        $event['description'] = $description;
        $event['start_time'] = $start_time;
        $event['end_time'] = $end_time;
        $event['reward_type'] = $reward_type;
        $event['reward_value'] = $reward_value;
        $event['reward_code'] = $reward_code;
        $event['status'] = $status;
        $event['max_participants'] = $max_participants;
    } else {
        $message = "更新失败: " . $conn->error;
        $message_type = "error";
    }
    
    $stmt->close();
}

$conn->close();

?> 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑特别活动 - 运营积木</title>
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
        .header .actions {
            display: flex;
            gap: 10px;
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
                <h1>编辑特别活动</h1>
                <div class="actions">
                    <a href="special_events.php" class="btn btn-secondary">返回列表</a>
                    <a href="event_participants.php?id=<?php echo $event['id']; ?>" class="btn">查看参与记录</a>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>活动信息</h2>
                <form method="POST">
                    <div class="form-container">
                        <div class="form-half">
                            <div class="form-group">
                                <label for="name">活动名称</label>
                                <input type="text" id="name" name="name" value="<?php echo $event['name']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="description">活动描述</label>
                                <textarea id="description" name="description"><?php echo $event['description']; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="start_time">开始时间</label>
                                <input type="datetime-local" id="start_time" name="start_time" value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_time'])); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="end_time">结束时间</label>
                                <input type="datetime-local" id="end_time" name="end_time" value="<?php echo date('Y-m-d\TH:i', strtotime($event['end_time'])); ?>" required>
                            </div>
                        </div>
                        <div class="form-half">
                            <div class="form-group">
                                <label for="reward_type">奖励类型</label>
                                <select id="reward_type" name="reward_type" required>
                                    <option value="points" <?php echo $event['reward_type'] === 'points' ? 'selected' : ''; ?>>积分</option>
                                    <option value="code" <?php echo $event['reward_type'] === 'code' ? 'selected' : ''; ?>>兑换码</option>
                                </select>
                            </div>
                            <div class="form-group" id="points-field" style="display: <?php echo $event['reward_type'] === 'points' ? 'block' : 'none'; ?>">
                                <label for="reward_value">积分数量</label>
                                <input type="number" id="reward_value" name="reward_value" min="1" value="<?php echo $event['reward_value']; ?>">
                            </div>
                            <div class="form-group" id="code-field" style="display: <?php echo $event['reward_type'] === 'code' ? 'block' : 'none'; ?>">
                                <label for="reward_code">兑换码</label>
                                <input type="text" id="reward_code" name="reward_code" value="<?php echo $event['reward_code']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="max_participants">最大参与人数 (0表示无限制)</label>
                                <input type="number" id="max_participants" name="max_participants" min="0" value="<?php echo $event['max_participants']; ?>">
                            </div>
                            <div class="form-group checkbox" style="display: flex; align-items: center;">
                                <input type="checkbox" id="status" name="status" <?php echo $event['status'] ? 'checked' : ''; ?>>
                                <label for="status" style="margin: 0 0 0 10px;">启用活动</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">更新活动</button>
                </form>
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