<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 检查管理员是否登录
// 注意：session_start()必须在任何HTML输出之前调用
session_start();
if (!is_admin_logged_in()) {
    header("Location: login.php");
    exit;
}

// 创建必要的表
function create_necessary_tables() {
    $conn = get_db_connection();
    
    // 创建预约活动表
    $sql = "CREATE TABLE IF NOT EXISTS appointment_activities (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        details TEXT,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        group_size INT(11) NOT NULL DEFAULT 3,
        reward_type ENUM('points', 'code') NOT NULL DEFAULT 'points',
        reward_value INT(11) NOT NULL DEFAULT 0,
        reward_code VARCHAR(255) NULL,
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    
    // 创建预约团队表
    $sql = "CREATE TABLE IF NOT EXISTS appointment_groups (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        activity_id INT(11) NOT NULL,
        leader_user_id INT(11) NOT NULL,
        status ENUM('pending', 'completed', 'expired') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (activity_id) REFERENCES appointment_activities(id) ON DELETE CASCADE,
        FOREIGN KEY (leader_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    
    // 创建预约成员表
    $sql = "CREATE TABLE IF NOT EXISTS appointment_members (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        group_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES appointment_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_activity (user_id, group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    
    $conn->close();
}

// 创建表
create_necessary_tables();

// 处理表单提交
$error = '';
$success = '';
$current_activity = null;

// 添加/编辑活动
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_activity'])) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $details = trim($_POST['details']);
        
        // 修复：强制验证和转换日期时间格式
        $start_time = '';
        $end_time = '';
        
        if (!empty($_POST['start_time'])) {
            $start_input = $_POST['start_time'];
            // 检查是否是完整的datetime-local格式 (YYYY-MM-DDTHH:MM)
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $start_input)) {
                $start_time = str_replace('T', ' ', $start_input) . ':00';
            } else {
                $error = '开始时间格式不正确，请使用完整的日期和时间';
            }
        } else {
            $error = '开始时间不能为空';
        }
        
        if (empty($error) && !empty($_POST['end_time'])) {
            $end_input = $_POST['end_time'];
            // 检查是否是完整的datetime-local格式 (YYYY-MM-DDTHH:MM)
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $end_input)) {
                $end_time = str_replace('T', ' ', $end_input) . ':00';
            } else {
                $error = '结束时间格式不正确，请使用完整的日期和时间';
            }
        } else if (empty($error) && empty($_POST['end_time'])) {
            $error = '结束时间不能为空';
        }
        
        $group_size = intval($_POST['group_size']);
        
        // 修复：安全地处理reward_type
        $reward_type = 'points'; // 默认值
        if (isset($_POST['reward_type'])) {
            $reward_type = ($_POST['reward_type'] === 'code') ? 'code' : 'points';
        }
        
        $reward_value = intval($_POST['reward_value']);
        $reward_code = trim($_POST['reward_code']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        // 验证表单 - 只有在日期时间格式正确的情况下才继续
        if (empty($error)) {
            if (empty($name)) {
                $error = '活动名称不能为空';
            } elseif ($group_size <= 0) {
                $error = '团队人数必须大于0';
            } elseif ($reward_type === 'points' && $reward_value <= 0) {
                $error = '积分奖励必须大于0';
            } elseif ($reward_type === 'code' && empty($reward_code)) {
                $error = '请输入兑换码';
            } elseif (strtotime($start_time) >= strtotime($end_time)) {
                $error = '结束时间必须晚于开始时间';
            }
        }
        
        // 只有所有验证都通过后才连接数据库
        if (empty($error)) {
            $conn = get_db_connection();
            
            if ($id > 0) {
                // 更新活动
                $sql = "UPDATE appointment_activities SET name=?, description=?, details=?, start_time=?, end_time=?, group_size=?, reward_type=?, reward_value=?, reward_code=?, status=?, updated_at=NOW() WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssisiiii", $name, $description, $details, $start_time, $end_time, $group_size,
                    $reward_type,
                    $reward_value, $reward_code, $status, $id);
            } else {
                // 添加新活动
                $sql = "INSERT INTO appointment_activities (name, description, details, start_time, end_time, group_size, reward_type, reward_value, reward_code, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssisiii", $name, $description, $details, $start_time, $end_time, $group_size,
                    $reward_type,
                    $reward_value, $reward_code, $status);
            }
            
            if ($stmt->execute()) {
                $success = $id > 0 ? '活动更新成功' : '活动添加成功';
                $stmt->close();
                $conn->close();
                // 重定向以避免表单重复提交
                header("Location: appointment.php?success=" . urlencode($success));
                exit;
            } else {
                $error = '操作失败: ' . $conn->error;
                $stmt->close();
                $conn->close();
            }
        }
    }
    // 删除活动
    elseif (isset($_POST['delete_activity'])) {
        $id = intval($_POST['id']);
        $conn = get_db_connection();
        $sql = "DELETE FROM appointment_activities WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = '活动删除成功';
            $stmt->close();
            $conn->close();
            header("Location: appointment.php?success=" . urlencode($success));
            exit;
        } else {
            $error = '删除失败: ' . $conn->error;
            $stmt->close();
            $conn->close();
        }
    }
}

// 编辑活动
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $conn = get_db_connection();
    $sql = "SELECT * FROM appointment_activities WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_activity = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
}

// 获取活动列表
$conn = get_db_connection();
$sql = "SELECT * FROM appointment_activities ORDER BY created_at DESC";
$result = $conn->query($sql);
$activities = [];
while ($row = $result->fetch_assoc()) {
    $activities[] = $row;
}
$conn->close();

// 获取URL参数中的成功消息
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

?> 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>预约模块 - 运营积木</title>
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
        .btn-secondary {
            background-color: #95a5a6;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        .status-active {
            color: #27ae60;
            font-weight: bold;
        }
        .status-inactive {
            color: #e74c3c;
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
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
                <h1>预约模块</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php echo $current_activity ? '编辑活动' : '添加活动'; ?></h2>
                <form method="post" action="appointment.php">
                    <?php if ($current_activity): ?>
                        <input type="hidden" name="id" value="<?php echo $current_activity['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">活动名称</label>
                        <input type="text" id="name" name="name" required value="<?php echo $current_activity ? $current_activity['name'] : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">活动描述</label>
                        <textarea id="description" name="description"><?php echo $current_activity ? $current_activity['description'] : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="details">活动详情</label>
                        <textarea id="details" name="details" rows="10"><?php echo $current_activity && isset($current_activity['details']) ? $current_activity['details'] : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">开始时间</label>
                        <input type="datetime-local" id="start_time" name="start_time" required value="<?php echo $current_activity ? date('Y-m-d\TH:i', strtotime($current_activity['start_time'])) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">结束时间</label>
                        <input type="datetime-local" id="end_time" name="end_time" required value="<?php echo $current_activity ? date('Y-m-d\TH:i', strtotime($current_activity['end_time'])) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="group_size">团队人数</label>
                        <input type="number" id="group_size" name="group_size" min="1" required value="<?php echo $current_activity ? $current_activity['group_size'] : '3'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="reward_type">奖励类型</label>
                        <select id="reward_type" name="reward_type" onchange="toggleRewardField()">
                            <option value="points" <?php echo ($current_activity && $current_activity['reward_type'] === 'points') ? 'selected' : ''; ?>>积分</option>
                            <option value="code" <?php echo ($current_activity && $current_activity['reward_type'] === 'code') ? 'selected' : ''; ?>>兑换码</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="reward_value_group">
                        <label for="reward_value">积分数量</label>
                        <input type="number" id="reward_value" name="reward_value" min="1" value="<?php echo $current_activity ? $current_activity['reward_value'] : '10'; ?>">
                    </div>
                    
                    <div class="form-group" id="reward_code_group" style="display: none;">
                        <label for="reward_code">兑换码</label>
                        <input type="text" id="reward_code" name="reward_code" value="<?php echo $current_activity ? $current_activity['reward_code'] : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">
                            <input type="checkbox" id="status" name="status" <?php echo ($current_activity && $current_activity['status']) || !$current_activity ? 'checked' : ''; ?>>
                            启用活动
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="save_activity" class="btn">保存</button>
                        <?php if ($current_activity): ?>
                            <button type="button" class="btn btn-danger" onclick="confirmDelete(<?php echo $current_activity['id']; ?>)">删除</button>
                        <?php endif; ?>
                        <a href="appointment.php" class="btn btn-secondary">取消</a>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h2>活动列表</h2>
                <table>
                    <thead>
                        <tr>
                            <th>活动名称</th>
                            <th>开始时间</th>
                            <th>结束时间</th>
                            <th>团队人数</th>
                            <th>奖励</th>
                            <th>状态</th>
                            <th>访问地址</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($activities) > 0): ?>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?php echo $activity['name']; ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($activity['start_time'])); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($activity['end_time'])); ?></td>
                                    <td><?php echo $activity['group_size']; ?></td>
                                    <td>
                                        <?php if ($activity['reward_type'] === 'points'): ?>
                                            <?php echo $activity['reward_value']; ?> 积分
                                        <?php else: ?>
                                            兑换码: <?php echo $activity['reward_code']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?php echo $activity['status'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $activity['status'] ? '启用' : '禁用'; ?>
                                    </td>
                                    <td>
                                        <a href="../appointment/index.php?id=<?php echo $activity['id']; ?>" target="_blank">
                                            查看活动
                                        </a>
                                    </td>
                                    <td class="actions">
                                        <a href="appointment.php?edit=<?php echo $activity['id']; ?>">编辑</a> |
                                        <a href="#" onclick="confirmDelete(<?php echo $activity['id']; ?>)">删除</a> |
                                        <a href="appointment_groups.php?activity_id=<?php echo $activity['id']; ?>">查看团队</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">暂无活动数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- 确认删除模态框 -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <h3>确认删除</h3>
            <p>确定要删除这个活动吗？这将同时删除相关的团队数据。</p>
            <form id="deleteForm" method="post" action="appointment.php">
                <input type="hidden" name="id" id="deleteId">
                <input type="hidden" name="delete_activity">
                <button type="submit" class="btn btn-danger">删除</button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">取消</button>
            </form>
        </div>
    </div>
    
    <script>
        // 切换奖励字段显示
        function toggleRewardField() {
            const rewardType = document.getElementById('reward_type').value;
            document.getElementById('reward_value_group').style.display = rewardType === 'points' ? 'block' : 'none';
            document.getElementById('reward_code_group').style.display = rewardType === 'code' ? 'block' : 'none';
        }
        
        // 页面加载时检查初始奖励类型
        window.onload = function() {
            toggleRewardField();
        };
        
        // 删除确认相关函数
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            if (event.target === document.getElementById('deleteModal')) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>