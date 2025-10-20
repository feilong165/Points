<?php
// 引入配置和应用核心文件
require_once 'app/config.php';
require_once 'app/app.php';

// 检查用户是否登录
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';

// 如果points_history表不存在，创建该表
function ensure_points_history_table_exists() {
    $conn = get_db_connection();
    
    // 检查表是否存在
    $check_table_sql = "SHOW TABLES LIKE 'points_history'";
    $result = $conn->query($check_table_sql);
    
    if ($result->num_rows == 0) {
        // 表不存在，创建表
        $create_table_sql = "CREATE TABLE points_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            points_change INT NOT NULL,
            points_balance INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        if (!$conn->query($create_table_sql)) {
            return false;
        }
    }
    
    $conn->close();
    return true;
}

// 确保表存在
$table_created = ensure_points_history_table_exists();
// 添加调试信息
echo "<div style='display:none;' id='debug-info'>";
echo "表创建状态: " . ($table_created ? '成功' : '失败') . "<br>";

// 获取用户信息
$user = get_user_info($user_id);
echo "用户ID: " . $user_id . "<br>";
echo "用户信息: " . (is_array($user) ? print_r($user, true) : '未获取到') . "<br>";

// 获取积分历史记录
function get_points_history($user_id) {
    try {
        $conn = get_db_connection();
        
        // 添加调试信息
        echo "数据库连接状态: 已连接<br>";
        
        $sql = "SELECT * FROM points_history WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo "预处理语句错误: " . $conn->error . "<br>";
            return [];
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        if ($stmt->errno) {
            echo "执行语句错误: " . $stmt->error . "<br>";
            $stmt->close();
            $conn->close();
            return [];
        }
        
        $result = $stmt->get_result();
        
        if (!$result) {
            echo "获取结果集错误: " . $stmt->error . "<br>";
            $stmt->close();
            $conn->close();
            return [];
        }
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        return $history;
    } catch (Exception $e) {
        echo "异常错误: " . $e->getMessage() . "<br>";
        return [];
    }
}

$points_history = get_points_history($user_id);
echo "积分记录数量: " . count($points_history) . "<br>";
echo "积分记录: " . (is_array($points_history) ? print_r($points_history, true) : '未获取到') . "<br>";
echo "</div>";

// 获取积分类型对应的中文名称
function get_points_type_name($type) {
    $types = [
        'sign_in' => '每日签到',
        'admin_adjust' => '后台调整',
        'product_exchange' => '兑换商品',
        'system_reward' => '系统奖励',
        'activity_reward' => '活动奖励'
    ];
    
    return isset($types[$type]) ? $types[$type] : '其他';
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>积分明细 - 积分系统</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 80px; /* 为底部导航栏留出空间 */
        }
        header {
            background-color: #4CAF50;
            color: white;
            padding: 15px 0;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 30px;
            position: relative;
        }
        .back-btn {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            text-decoration: none;
            font-size: 20px;
        }
        .content {
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .current-points {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .current-points .points-label {
            font-size: 16px;
            color: #666;
        }
        .current-points .points-value {
            font-size: 28px;
            font-weight: bold;
            color: #1976d2;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        table tr:hover {
            background-color: #f5f5f5;
        }
        .points-in {
            color: #4CAF50;
        }
        .points-out {
            color: #f44336;
        }
        .type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            background-color: #e0e0e0;
            color: #333;
        }
        .empty-message {
            text-align: center;
            color: #999;
            padding: 40px;
        }
        nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            border-top: 1px solid #eee;
            z-index: 1000;
            box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
        }
        nav ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: space-around;
        }
        nav ul li {
            flex: 1;
            text-align: center;
        }
        nav ul li a {
            display: block;
            padding: 15px 0;
            text-decoration: none;
            color: #666;
            font-size: 14px;
        }
        nav ul li a.active {
            color: #4CAF50;
        }
        nav ul li a i {
            display: block;
            font-size: 20px;
            margin-bottom: 5px;
        }
        nav ul li a span {
            display: block;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="user_center.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <h1>积分明细</h1>
        </header>
        
        <div class="content">
            <div class="current-points">
                <div class="points-label">当前积分余额</div>
                <div class="points-value"><?php echo $user['points']; ?></div>
            </div>
            
            <table>
                <tr>
                    <th>时间</th>
                    <th>类型</th>
                    <th>积分变动</th>
                    <th>积分余额</th>
                    <th>备注</th>
                </tr>
                
                <?php if (count($points_history) > 0): ?>
                    <?php foreach ($points_history as $record): ?>
                        <tr>
                            <td><?php echo $record['created_at']; ?></td>
                            <td><span class="type-badge"><?php echo get_points_type_name($record['type']); ?></span></td>
                            <td class="<?php echo $record['points_change'] > 0 ? 'points-in' : 'points-out'; ?>">
                                <?php echo $record['points_change'] > 0 ? '+' : ''; ?><?php echo $record['points_change']; ?>
                            </td>
                            <td><?php echo $record['points_balance']; ?></td>
                            <td><?php echo !empty($record['description']) ? $record['description'] : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-message">暂无积分记录</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <nav>
            <ul>
                <li><a href="index.php"><i class="fas fa-home"></i><span>首页</span></a></li>
                <li><a href="shop.php"><i class="fas fa-shopping-bag"></i><span>商品</span></a></li>
                <li><a href="messages.php"><i class="fas fa-comment-dots"></i><span>消息</span></a></li>
                <li><a href="user_center.php" class="active"><i class="fas fa-user"></i><span>我的</span></a></li>
            </ul>
        </nav>
    </div>
</body>
</html>