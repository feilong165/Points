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
$order_details = null;

// 处理订单状态更新
if (isset($_GET['update_status'])) {
    $order_id = intval($_GET['update_status']);
    $status = $_GET['status'];
    
    // 验证状态值
    if (!in_array($status, ['pending', 'completed', 'canceled'])) {
        $error = '无效的订单状态';
    } else {
        $result = update_order_status($order_id, $status);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// 获取订单详情
if (isset($_GET['view'])) {
    $order_id = intval($_GET['view']);
    
    $conn = get_db_connection();
    $sql = "SELECT orders.*, users.username, users.phone, products.name AS product_name, products.is_virtual 
            FROM orders 
            LEFT JOIN users ON orders.user_id = users.id 
            LEFT JOIN products ON orders.product_id = products.id 
            WHERE orders.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // 如果是虚拟商品，获取对应的兑换码
    if ($order_details && $order_details['is_virtual']) {
        $sql = "SELECT * FROM order_codes WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $codes_result = $stmt->get_result();
        $order_details['codes'] = [];
        while ($code = $codes_result->fetch_assoc()) {
            $order_details['codes'][] = $code;
        }
        $stmt->close();
    }
    
    $conn->close();
}

// 获取过滤条件
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$user_filter = isset($_GET['user']) ? trim($_GET['user']) : '';
$product_filter = isset($_GET['product']) ? trim($_GET['product']) : '';

// 构建查询条件
$where_conditions = [];
$params = [];
$types = '';

if (!empty($status_filter)) {
    $where_conditions[] = "orders.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($user_filter)) {
    $where_conditions[] = "(users.username LIKE ? OR users.phone LIKE ?)";
    $params[] = "%$user_filter%";
    $params[] = "%$user_filter%";
    $types .= 'ss';
}

if (!empty($product_filter)) {
    $where_conditions[] = "products.name LIKE ?";
    $params[] = "%$product_filter%";
    $types .= 's';
}

// 获取订单列表
$conn = get_db_connection();

// 分页设置
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page_size = 10;
$offset = ($page - 1) * $page_size;

// 计算总页数
$count_sql = "SELECT COUNT(*) AS count FROM orders 
             LEFT JOIN users ON orders.user_id = users.id 
             LEFT JOIN products ON orders.product_id = products.id";

if (!empty($where_conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_count = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_count / $page_size);
$count_stmt->close();

// 查询订单列表
$sql = "SELECT orders.*, users.username, users.phone, products.name AS product_name 
        FROM orders 
        LEFT JOIN users ON orders.user_id = users.id 
        LEFT JOIN products ON orders.product_id = products.id";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY orders.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $page_size;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$stmt->close();
$conn->close();
?>

<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单管理 - 积分系统</title>
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
        .orders-table {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .orders-table h2 {
            margin-top: 0;
            color: #333;
            margin-bottom: 20px;
        }
        .filter {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            align-items: center;
        }
        .filter-group label {
            margin-right: 5px;
            color: #555;
        }
        .filter-group select,
        .filter-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-edit {
            background-color: #2196F3;
        }
        .btn-edit:hover {
            background-color: #1976D2;
        }
        .btn-delete {
            background-color: #f44336;
        }
        .btn-delete:hover {
            background-color: #d32f2f;
        }
        .btn-process {
            background-color: #ff9800;
        }
        .btn-process:hover {
            background-color: #f57c00;
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
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-pending {
            background-color: #ff9800;
            color: white;
        }
        .badge-completed {
            background-color: #4CAF50;
            color: white;
        }
        .badge-canceled {
            background-color: #f44336;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: white;
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
            cursor: pointer;
        }
        .close:hover, .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .order-details {
            margin-bottom: 20px;
        }
        .order-details p {
            margin: 10px 0;
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
                <h1>订单管理</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="orders-table">
                <h2>订单列表</h2>
                
                <!-- 过滤表单 -->
                <div class="filter">
                    <form method="GET" action="orders.php">
                        <div class="filter-group">
                            <label for="status">订单状态:</label>
                            <select id="status" name="status">
                                <option value="">全部</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>待处理</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>已完成</option>
                                <option value="canceled" <?php echo $status_filter === 'canceled' ? 'selected' : ''; ?>>已取消</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="user">用户信息:</label>
                            <input type="text" id="user" name="user" placeholder="用户名/手机号" value="<?php echo htmlspecialchars($user_filter); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="product">商品名称:</label>
                            <input type="text" id="product" name="product" placeholder="商品名称" value="<?php echo htmlspecialchars($product_filter); ?>">
                        </div>
                        
                        <button type="submit" class="btn">筛选</button>
                    </form>
                </div>
                
                <!-- 订单列表表格 -->
                <table>
                    <thead>
                        <tr>
                            <th>订单ID</th>
                            <th>用户信息</th>
                            <th>商品名称</th>
                            <th>积分消耗</th>
                            <th>订单状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($order = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <td>
                                        用户名: <?php echo htmlspecialchars($order['username']); ?><br>
                                        手机号: <?php echo htmlspecialchars($order['phone']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    <td><?php echo $order['points_used']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $order['status']; ?>">
                                            <?php 
                                                switch ($order['status']) {
                                                    case 'pending':
                                                        echo '待处理';
                                                        break;
                                                    case 'completed':
                                                        echo '已完成';
                                                        break;
                                                    case 'canceled':
                                                        echo '已取消';
                                                        break;
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $order['created_at']; ?></td>
                                    <td>
                                        <a href="orders.php?view=<?php echo $order['id']; ?>" class="btn btn-edit">查看详情</a>
                                        
                                        <?php if ($order['status'] !== 'completed'): ?>
                                            <a href="orders.php?update_status=<?php echo $order['id']; ?>&status=completed" class="btn btn-process" onclick="return confirm('确定要标记为已完成吗？');">
                                                标记完成
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] !== 'canceled'): ?>
                                            <a href="orders.php?update_status=<?php echo $order['id']; ?>&status=canceled" class="btn btn-delete" onclick="return confirm('确定要取消此订单吗？');">
                                                取消订单
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">暂无订单</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="orders.php?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&user=<?php echo urlencode($user_filter); ?>&product=<?php echo urlencode($product_filter); ?>" class="<?php echo $i == $page ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- 订单详情模态框 -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <?php if ($order_details): ?>
                <h2>订单详情</h2>
                <div class="order-details">
                    <p><strong>订单ID:</strong> <?php echo $order_details['id']; ?></p>
                    <p><strong>用户信息:</strong> <?php echo htmlspecialchars($order_details['username']); ?> (<?php echo htmlspecialchars($order_details['phone']); ?>)</p>
                    <p><strong>兑换商品:</strong> <?php echo htmlspecialchars($order_details['product_name']); ?></p>
                    <p><strong>积分消耗:</strong> <?php echo $order_details['points_used']; ?></p>
                    <p><strong>订单状态:</strong> 
                        <span class="badge badge-<?php echo $order_details['status']; ?>">
                            <?php 
                                switch ($order_details['status']) {
                                    case 'pending':
                                        echo '待处理';
                                        break;
                                    case 'completed':
                                        echo '已完成';
                                        break;
                                    case 'canceled':
                                        echo '已取消';
                                        break;
                                }
                            ?>
                        </span>
                    </p>
                    <p><strong>创建时间:</strong> <?php echo $order_details['created_at']; ?></p>
                    <p><strong>完成时间:</strong> <?php echo $order_details['completed_at'] ? $order_details['completed_at'] : '未完成'; ?></p>
                    
                    <?php if ($order_details['is_virtual'] && !empty($order_details['codes'])): ?>
                        <p><strong>兑换码:</strong></p>
                        <ul>
                            <?php foreach ($order_details['codes'] as $code): ?>
                                <li><?php echo $code['code']; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // 订单详情模态框控制
        var modal = document.getElementById('orderModal');
        var span = document.getElementsByClassName('close')[0];
        
        // 如果有订单详情，显示模态框
        <?php if ($order_details): ?>
            modal.style.display = "block";
        <?php endif; ?>
        
        // 点击关闭按钮
        span.onclick = function() {
            window.location.href = 'orders.php';
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            if (event.target == modal) {
                window.location.href = 'orders.php';
            }
        }
    </script>
</body>
</html>