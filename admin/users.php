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
$current_user_id = null;
$current_user_name = '';
$current_user_points = 0;

// 处理积分调整请求
if (isset($_POST['adjust_points'])) {
    $user_id = $_POST['user_id'];
    $points_change = intval($_POST['points_change']);
    $reason = $_POST['reason'];
    
    $result = manage_user_points($user_id, $points_change, $reason);
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// 获取用户信息（用于模态框显示）
if (isset($_GET['edit_points'])) {
    $current_user_id = $_GET['edit_points'];
    
    $conn = get_db_connection();
    $sql = "SELECT username, points FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $current_user_name = $user['username'];
        $current_user_points = $user['points'];
    }
    
    $stmt->close();
    $conn->close();
}

// 获取用户列表
$conn = get_db_connection();

// 分页设置
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page_size = 10;
$offset = ($page - 1) * $page_size;

// 计算总页数
$count_sql = "SELECT COUNT(*) AS count FROM users";
$count_result = $conn->query($count_sql);
$total_count = $count_result->fetch_assoc()['count'];
$total_pages = ceil($total_count / $page_size);

// 查询用户列表
$sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $page_size);
$stmt->execute();
$result = $stmt->get_result();

$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 积分系统</title>
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
        .users-table {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .users-table h2 {
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
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 3px;
            border: none;
            cursor: pointer;
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
        .points-modal {
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
            width: 400px;
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
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
                <h1>用户管理</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="users-table">
                <h2>用户列表</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>手机号</th>
                            <th>积分</th>
                            <th>最近签到</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($user = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo $user['phone']; ?></td>
                                    <td><?php echo $user['points']; ?></td>
                                    <td><?php echo $user['last_signin'] ? $user['last_signin'] : '从未签到'; ?></td>
                                    <td><?php echo $user['created_at']; ?></td>
                                    <td>
                                        <button class="btn btn-edit" onclick="openPointsModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>', <?php echo $user['points']; ?>)">调整积分</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">暂无用户</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="users.php?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- 积分调整模态框 -->
    <div id="pointsModal" class="points-modal">
        <div class="modal-content">
            <span class="close" onclick="closePointsModal()">&times;</span>
            <h2>调整用户积分</h2>
            <form method="POST" action="users.php">
                <input type="hidden" id="modal_user_id" name="user_id" value="">
                <div class="form-group">
                    <label for="modal_user_name">用户名</label>
                    <input type="text" id="modal_user_name" disabled>
                </div>
                <div class="form-group">
                    <label for="modal_current_points">当前积分</label>
                    <input type="number" id="modal_current_points" disabled>
                </div>
                <div class="form-group">
                    <label for="points_change">积分变动（正数增加，负数减少）</label>
                    <input type="number" id="points_change" name="points_change" required>
                </div>
                <div class="form-group">
                    <label for="reason">原因</label>
                    <input type="text" id="reason" name="reason" placeholder="请输入调整原因">
                </div>
                <button type="submit" name="adjust_points" class="btn">确认调整</button>
            </form>
        </div>
    </div>
    
    <script>
        // 获取模态框
        var pointsModal = document.getElementById("pointsModal");
        
        // 打开积分调整模态框
        function openPointsModal(userId, userName, currentPoints) {
            document.getElementById("modal_user_id").value = userId;
            document.getElementById("modal_user_name").value = userName;
            document.getElementById("modal_current_points").value = currentPoints;
            document.getElementById("points_change").value = '';
            document.getElementById("reason").value = '';
            pointsModal.style.display = "block";
        }
        
        // 关闭模态框
        function closePointsModal() {
            pointsModal.style.display = "none";
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            if (event.target == pointsModal) {
                pointsModal.style.display = "none";
            }
        }
        
        // 如果URL中有edit_points参数，打开模态框
        <?php if ($current_user_id): ?>
            openPointsModal(<?php echo $current_user_id; ?>, '<?php echo addslashes($current_user_name); ?>', <?php echo $current_user_points; ?>);
        <?php endif; ?>
    </script>
</body>
</html>