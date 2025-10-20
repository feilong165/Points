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

// 获取参与记录
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 计算总记录数
$sql = "SELECT COUNT(*) as total FROM special_event_participants WHERE event_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$count_result = $stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $per_page);
$stmt->close();

// 获取当前页数据
$sql = "SELECT sep.*, u.username, u.email, u.phone 
        FROM special_event_participants sep 
        JOIN users u ON sep.user_id = u.id 
        WHERE sep.event_id = ? 
        ORDER BY sep.participation_time DESC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $event_id, $offset, $per_page);
$stmt->execute();
$participants_result = $stmt->get_result();
$participants = [];
while ($participant = $participants_result->fetch_assoc()) {
    $participants[] = $participant;
}
$stmt->close();
$conn->close();

?> 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>活动参与记录 - 特别活动</title>
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
            text-align: center;
            margin-top: 20px;
        }
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            background-color: #f2f2f2;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination a:hover {
            background-color: #e2e2e2;
        }
        .pagination .active {
            background-color: #3498db;
            color: white;
        }
        .reward-info {
            display: flex;
            align-items: center;
            gap: 10px;
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
                <h1>活动参与记录 - <?php echo $event['name']; ?></h1>
                <div class="actions">
                    <a href="edit_special_event.php?id=<?php echo $event['id']; ?>" class="btn">编辑活动</a>
                    <a href="special_events.php" class="btn btn-secondary">返回活动列表</a>
                </div>
            </div>
            
            <div class="card">
                <h2>活动信息</h2>
                <div class="activity-info">
                    <p><strong>活动名称：</strong><?php echo $event['name']; ?></p>
                    <p><strong>活动描述：</strong><?php echo $event['description']; ?></p>
                    <p><strong>开始时间：</strong><?php echo date('Y-m-d H:i', strtotime($event['start_time'])); ?></p>
                    <p><strong>结束时间：</strong><?php echo date('Y-m-d H:i', strtotime($event['end_time'])); ?></p>
                    <p><strong>奖励：</strong>
                        <?php if ($event['reward_type'] === 'points'): ?>
                            <?php echo $event['reward_value']; ?> 积分
                        <?php else: ?>
                            兑换码: <?php echo $event['reward_code']; ?>
                        <?php endif; ?>
                    </p>
                    <p><strong>参与人数：</strong><?php echo $total_records; ?><?php if ($event['max_participants'] > 0): ?> / <?php echo $event['max_participants']; ?><?php endif; ?></p>
                </div>
            </div>
            
            <div class="card">
                <h2>参与记录列表</h2>
                
                <?php if (count($participants) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户名称</th>
                                <th>用户邮箱</th>
                                <th>用户电话</th>
                                <th>参与时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $participant): ?>
                                <tr>
                                    <td><?php echo $participant['id']; ?></td>
                                    <td><?php echo $participant['username']; ?></td>
                                    <td><?php echo $participant['email']; ?></td>
                                    <td><?php echo $participant['phone']; ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($participant['participation_time'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- 分页 -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?id=<?php echo $event_id; ?>&page=<?php echo $page - 1; ?>">上一页</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?id=<?php echo $event_id; ?>&page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?id=<?php echo $event_id; ?>&page=<?php echo $page + 1; ?>">下一页</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">暂无参与记录</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>