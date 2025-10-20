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

// 获取活动ID
$activity_id = isset($_GET['activity_id']) ? intval($_GET['activity_id']) : 0;

if ($activity_id <= 0) {
    header("Location: appointment.php");
    exit;
}

// 获取活动信息
$conn = get_db_connection();
$sql = "SELECT * FROM appointment_activities WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$result = $stmt->get_result();
$activity = $result->fetch_assoc();
$stmt->close();

if (!$activity) {
    $conn->close();
    header("Location: appointment.php");
    exit;
}

// 获取所有团队数据
$sql = "SELECT g.*, u.username as leader_name 
        FROM appointment_groups g 
        JOIN users u ON g.leader_user_id = u.id 
        WHERE g.activity_id = ? 
        ORDER BY g.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$groups_result = $stmt->get_result();
$groups = [];
while ($group = $groups_result->fetch_assoc()) {
    // 获取团队成员
    $member_sql = "SELECT m.*, u.username 
                  FROM appointment_members m 
                  JOIN users u ON m.user_id = u.id 
                  WHERE m.group_id = ?";
    $member_stmt = $conn->prepare($member_sql);
    $member_stmt->bind_param("i", $group['id']);
    $member_stmt->execute();
    $members_result = $member_stmt->get_result();
    $members = [];
    while ($member = $members_result->fetch_assoc()) {
        $members[] = $member;
    }
    $member_stmt->close();
    
    $group['members'] = $members;
    $group['member_count'] = count($members);
    $groups[] = $group;
}
$stmt->close();
$conn->close();

?> 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>团队数据 - 预约模块</title>
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
        .group-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }
        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .group-info {
            display: flex;
            flex-direction: column;
        }
        .group-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .group-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #666;
        }
        .group-members {
            margin-top: 10px;
        }
        .members-header {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .member-list {
            list-style-type: none;
            padding: 0;
        }
        .member-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .member-item:last-child {
            border-bottom: none;
        }
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #ffc107;
            color: #333;
        }
        .status-completed {
            background-color: #28a745;
            color: white;
        }
        .status-expired {
            background-color: #6c757d;
            color: white;
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
                <h1>团队数据 - <?php echo $activity['name']; ?></h1>
                <div>
                    <a href="appointment.php" class="btn btn-secondary">返回活动列表</a>
                    <a href="logout.php" class="logout">退出登录</a>
                </div>
            </div>
            
            <div class="card">
                <h2>活动信息</h2>
                <div class="activity-info">
                    <p><strong>活动名称：</strong><?php echo $activity['name']; ?></p>
                    <p><strong>开始时间：</strong><?php echo date('Y-m-d H:i', strtotime($activity['start_time'])); ?></p>
                    <p><strong>结束时间：</strong><?php echo date('Y-m-d H:i', strtotime($activity['end_time'])); ?></p>
                    <p><strong>团队人数：</strong><?php echo $activity['group_size']; ?></p>
                    <p><strong>奖励：</strong>
                        <?php if ($activity['reward_type'] === 'points'): ?>
                            <?php echo $activity['reward_value']; ?> 积分
                        <?php else: ?>
                            兑换码: <?php echo $activity['reward_code']; ?>
                        <?php endif; ?>
                    </p>
                    <p><strong>状态：</strong><?php echo $activity['status'] ? '启用' : '禁用'; ?></p>
                </div>
            </div>
            
            <div class="card">
                <h2>团队列表 (共 <?php echo count($groups); ?> 个团队)</h2>
                
                <?php if (count($groups) > 0): ?>
                    <div class="groups-container">
                        <?php foreach ($groups as $group): ?>
                            <div class="group-card">
                                <div class="group-header">
                                    <div class="group-info">
                                        <div class="group-title">团队 #<?php echo $group['id']; ?></div>
                                        <div class="group-stats">
                                            <span>创建者: <?php echo $group['leader_name']; ?></span>
                                            <span>创建时间: <?php echo date('Y-m-d H:i', strtotime($group['created_at'])); ?></span>
                                            <span>成员: <?php echo $group['member_count']; ?>/<?php echo $activity['group_size']; ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo $group['status']; ?>">
                                        <?php 
                                            switch($group['status']) {
                                                case 'pending': echo '招募中'; break;
                                                case 'completed': echo '已完成'; break;
                                                case 'expired': echo '已过期'; break;
                                            }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="group-members">
                                    <div class="members-header">成员列表</div>
                                    <ul class="member-list">
                                        <?php foreach ($group['members'] as $member): ?>
                                            <li class="member-item">
                                                <span><?php echo $member['username']; ?></span>
                                                <span>加入时间: <?php echo date('Y-m-d H:i', strtotime($member['joined_at'])); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">暂无团队数据</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>