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

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - 积分系统</title>
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            color: #7f8c8d;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .stat-card .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #3498db;
        }
        .recent-activities {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .recent-activities h2 {
            margin-top: 0;
            color: #333;
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
                <h1>控制面板</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
            
            <?php
            
            // 获取系统统计数据
            $conn = get_db_connection();
            
            // 用户总数
            $user_count_sql = "SELECT COUNT(*) AS count FROM users";
            $user_count_result = $conn->query($user_count_sql);
            $user_count = $user_count_result->fetch_assoc()['count'];
            
            // 商品总数
            $product_count_sql = "SELECT COUNT(*) AS count FROM products";
            $product_count_result = $conn->query($product_count_sql);
            $product_count = $product_count_result->fetch_assoc()['count'];
            
            // 订单总数
            $order_count_sql = "SELECT COUNT(*) AS count FROM orders";
            $order_count_result = $conn->query($order_count_sql);
            $order_count = $order_count_result->fetch_assoc()['count'];
            
            // 总积分发放量
            $total_points_sql = "SELECT SUM(points) AS total FROM users";
            $total_points_result = $conn->query($total_points_sql);
            $total_points = $total_points_result->fetch_assoc()['total'] ?: 0;
            
            // 获取最近活动记录
            $recent_activities_sql = "(
                SELECT 
                    '签到' AS type,
                    u.username,
                    l.created_at AS action_time,
                    '获得1积分' AS description
                FROM signin_logs l
                JOIN users u ON l.user_id = u.id
                ORDER BY l.created_at DESC
                LIMIT 10
            )
            UNION ALL
            (
                SELECT 
                    '兑换' AS type,
                    u.username,
                    o.created_at AS action_time,
                    CONCAT('兑换商品: ', o.product_name)
                FROM orders o
                JOIN users u ON o.user_id = u.id
                ORDER BY o.created_at DESC
                LIMIT 10
            )
            ORDER BY action_time DESC
            LIMIT 10";
            
            $recent_activities_result = $conn->query($recent_activities_sql);
            
            $conn->close();
            ?>
            
            <div class="stats">
                <div class="stat-card">
                    <h3>用户总数</h3>
                    <div class="stat-value"><?php echo $user_count; ?></div>
                </div>
                <div class="stat-card">
                    <h3>商品总数</h3>
                    <div class="stat-value"><?php echo $product_count; ?></div>
                </div>
                <div class="stat-card">
                    <h3>订单总数</h3>
                    <div class="stat-value"><?php echo $order_count; ?></div>
                </div>
                <div class="stat-card">
                    <h3>总积分</h3>
                    <div class="stat-value"><?php echo $total_points; ?></div>
                </div>
            </div>
            
            <div class="recent-activities">
                <h2>最近活动</h2>
                <table>
                    <thead>
                        <tr>
                            <th>类型</th>
                            <th>用户</th>
                            <th>描述</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_activities_result->num_rows > 0): ?>
                            <?php while ($activity = $recent_activities_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $activity['type']; ?></td>
                                    <td><?php echo $activity['username']; ?></td>
                                    <td><?php echo $activity['description']; ?></td>
                                    <td><?php echo $activity['action_time']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">暂无活动记录</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>