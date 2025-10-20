<?php
// 引入配置文件
require_once '../app/config.php';
require_once '../app/app.php';

// 启动会话
session_start();

// 检查管理员是否登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// 处理生成兑换码请求
$error = '';
$success = '';

// 处理生成兑换码
if (isset($_POST['generate_codes'])) {
    $product_id = intval($_POST['product_id']);
    $expiry_days = intval($_POST['expiry_days']);
    $code_type = isset($_POST['code_type']) ? $_POST['code_type'] : 'auto';
    
    // 验证输入
    if ($product_id <= 0) {
        $error = '请选择商品';
    } else {
        // 检查是否是虚拟商品
        $conn = get_db_connection();
        $sql = "SELECT * FROM products WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        if (!$product || !$product['is_virtual']) {
            $error = '请选择有效的虚拟商品';
        } else {
            // 计算过期时间
            $expiry_date = $expiry_days > 0 ? date('Y-m-d H:i:s', strtotime("+$expiry_days days")) : null;
            
            if ($code_type === 'auto') {
                // 自动生成模式
                $quantity = intval($_POST['quantity']);
                $code_length = intval($_POST['code_length']);
                
                // 验证自动生成的参数
                if ($quantity < 1 || $quantity > 1000) {
                    $error = '生成数量应在1-1000之间';
                } elseif ($code_length < 1 || $code_length > 18) {
                    $error = '兑换码长度应在1-18之间';
                } else {
                    // 生成兑换码
                    $result = generate_codes($product_id, $quantity, $expiry_date, $code_length);
                    
                    if ($result['success']) {
                        $success = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                }
            } else {
                // 自定义输入模式
                $custom_codes_text = trim($_POST['custom_codes']);
                
                if (empty($custom_codes_text)) {
                    $error = '请输入自定义兑换码';
                } else {
                    // 将输入的文本按行分割为兑换码数组
                    $custom_codes = preg_split('/\r\n|\r|\n/', $custom_codes_text);
                    // 过滤空行
                    $custom_codes = array_filter($custom_codes, function($code) {
                        return !empty(trim($code));
                    });
                    
                    if (count($custom_codes) < 1) {
                        $error = '请输入有效的兑换码';
                    } elseif (count($custom_codes) > 1000) {
                        $error = '兑换码数量不能超过1000个';
                    } else {
                        // 验证每个兑换码的长度
                        foreach ($custom_codes as $code) {
                            $code = trim($code);
                            if (strlen($code) < 1 || strlen($code) > 18) {
                                $error = '兑换码长度应在1-18个字符之间';
                                break;
                            }
                        }
                        
                        if (empty($error)) {
                            // 添加自定义兑换码
                            $result = add_custom_codes($product_id, $custom_codes, $expiry_date);
                            
                            if ($result['success']) {
                                $success = $result['message'];
                            } else {
                                $error = $result['message'];
                            }
                        }
                    }
                }
            }
        }
    }
}

// 处理删除兑换码
if (isset($_GET['delete'])) {
    $code_id = intval($_GET['delete']);
    
    $result = delete_code($code_id);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// 处理导出兑换码
if (isset($_GET['export'])) {
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    export_codes($product_id, $status);
    exit;
}

// 获取过滤条件
$product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_code = isset($_GET['code']) ? trim($_GET['code']) : '';

// 获取虚拟商品列表
$conn = get_db_connection();
$sql = "SELECT id, name FROM products WHERE is_virtual = 1 AND is_active = 1 ORDER BY name";
$products_result = $conn->query($sql);

// 构建查询条件
$where_conditions = [];
$params = [];
$types = '';

if ($product_filter > 0) {
    $where_conditions[] = "product_id = ?";
    $params[] = $product_filter;
    $types .= 'i';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search_code)) {
    $where_conditions[] = "code LIKE ?";
    $params[] = "%$search_code%";
    $types .= 's';
}

// 分页设置
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page_size = 20;
$offset = ($page - 1) * $page_size;

// 计算总页数
$count_sql = "SELECT COUNT(*) AS count FROM codes";

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

// 查询兑换码列表
$sql = "SELECT codes.*, products.name AS product_name 
        FROM codes 
        LEFT JOIN products ON codes.product_id = products.id";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY codes.created_at DESC LIMIT ?, ?";
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
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>兑换码管理 - 积分系统管理后台</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
            color: #333;
            display: flex;
        }
        
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 220px;
            background-color: #2c3e50;
            color: white;
            padding: 20px;
        }
        
        .sidebar h2 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .sidebar ul {
            list-style: none;
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
        
        main {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .error-message {
            background-color: #fee;
            color: #c00;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
            border-left: 4px solid #c00;
        }
        
        .success-message {
            background-color: #efe;
            color: #080;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
            border-left: 4px solid #080;
        }
        
        h2 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
        }
        
        .generate-codes {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e1e1e1;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        input[type="number"],
        input[type="text"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-edit {
            background-color: #3498db;
        }
        
        .btn-edit:hover {
            background-color: #2980b9;
        }
        
        .btn-delete {
            background-color: #e74c3c;
        }
        
        .btn-delete:hover {
            background-color: #c0392b;
        }
        
        .btn-export {
            background-color: #27ae60;
            margin-left: 10px;
        }
        
        .btn-export:hover {
            background-color: #219a52;
        }
        
        .btn-toggle {
            background-color: #f39c12;
        }
        
        .btn-toggle:hover {
            background-color: #e67e22;
        }
        
        .codes-table {
            margin-top: 30px;
        }
        
        .filter {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e1e1e1;
        }
        
        .filter form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        table tr:hover {
            background-color: #f9f9f9;
        }
        
        .code-display {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #3498db;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-available {
            background-color: #4CAF50;
            color: white;
        }
        
        .badge-used {
            background-color: #2196F3;
            color: white;
        }
        
        .badge-expired {
            background-color: #f44336;
            color: white;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #3498db;
        }
        
        .pagination a:hover {
            background-color: #f8f9fa;
        }
        
        .pagination .current {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        /* 模态框样式 */
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
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            border-radius: 5px;
            max-height: 80vh;
            overflow-y: auto;
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
        
        <div class="content">
            <div class="header">
                <h1>兑换码管理</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
            
            <main>
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- 生成兑换码表单 -->
            <div class="generate-codes">
                <h2>生成兑换码</h2>
                <form method="POST" action="codes.php">
                    <div class="form-group">
                        <label for="product_id">虚拟商品</label>
                        <select id="product_id" name="product_id" required>
                            <option value="">请选择商品</option>
                            <?php while ($product = $products_result->fetch_assoc()): ?>
                                <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>兑换码生成方式</label>
                        <div style="display: flex; gap: 20px;">
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="code_type" value="auto" checked> 自动生成
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="code_type" value="custom"> 自定义输入
                            </label>
                        </div>
                    </div>
                    
                    <div id="auto_generate_fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="quantity">生成数量</label>
                                <input type="number" id="quantity" name="quantity" value="10" min="1" max="1000" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="code_length">兑换码长度</label>
                                <input type="number" id="code_length" name="code_length" value="16" min="1" max="18" required>
                            </div>
                        </div>
                    </div>
                    
                    <div id="custom_codes_field" style="display: none;">
                        <div class="form-group">
                            <label for="custom_codes">自定义兑换码（每行一个，最多1000个）</label>
                            <textarea id="custom_codes" name="custom_codes" rows="10" placeholder="请输入自定义兑换码，每行一个"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="expiry_days">有效期（天）</label>
                        <input type="number" id="expiry_days" name="expiry_days" value="30" min="0" placeholder="0表示永久有效">
                    </div>
                    
                    <button type="submit" name="generate_codes" class="btn">生成兑换码</button>
                    
                    <script>
                        // 切换兑换码生成方式
                        document.querySelectorAll('input[name="code_type"]').forEach(radio => {
                            radio.addEventListener('change', function() {
                                document.getElementById('auto_generate_fields').style.display = this.value === 'auto' ? 'block' : 'none';
                                document.getElementById('custom_codes_field').style.display = this.value === 'custom' ? 'block' : 'none';
                                document.getElementById('quantity').required = this.value === 'auto';
                                document.getElementById('code_length').required = this.value === 'auto';
                                document.getElementById('custom_codes').required = this.value === 'custom';
                            });
                        });
                    </script>
                </form>
            </div>
            
            <!-- 兑换码列表 -->
            <div class="codes-table">
                <h2>兑换码列表</h2>
                
                <!-- 筛选表单 -->
                <div class="filter">
                    <form method="GET" action="codes.php">
                        <div class="filter-group">
                            <label for="product_filter">商品:</label>
                            <select id="product_filter" name="product_id">
                                <option value="">全部</option>
                                <?php 
                                    // 重新获取商品列表用于筛选
                                    $conn = get_db_connection();
                                    $sql = "SELECT id, name FROM products WHERE is_virtual = 1 ORDER BY name";
                                    $products_filter_result = $conn->query($sql);
                                    while ($product = $products_filter_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $product['id']; ?>" <?php echo $product_filter == $product['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <?php $conn->close(); ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status_filter">状态:</label>
                            <select id="status_filter" name="status">
                                <option value="">全部</option>
                                <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>可用</option>
                                <option value="used" <?php echo $status_filter === 'used' ? 'selected' : ''; ?>>已使用</option>
                                <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>已过期</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search_code">搜索码:</label>
                            <input type="text" id="search_code" name="code" placeholder="输入兑换码" value="<?php echo htmlspecialchars($search_code); ?>">
                        </div>
                        
                        <button type="submit" class="btn">筛选</button>
                        
                        <!-- 导出按钮 -->
                        <a href="codes.php?export=1&product_id=<?php echo $product_filter; ?>&status=<?php echo $status_filter; ?>&code=<?php echo urlencode($search_code); ?>" class="btn btn-export" onclick="return confirm('确定要导出当前筛选条件下的所有兑换码吗？');">
                            导出兑换码
                        </a>
                    </form>
                </div>
                
                <!-- 兑换码列表表格 -->
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>兑换码</th>
                            <th>关联商品</th>
                            <th>状态</th>
                            <th>使用用户</th>
                            <th>有效期至</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($code = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $code['id']; ?></td>
                                    <td class="code-display"><?php echo $code['code']; ?></td>
                                    <td><?php echo htmlspecialchars($code['product_name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $code['status']; ?>">
                                            <?php 
                                                switch ($code['status']) {
                                                    case 'available':
                                                        echo '可用';
                                                        break;
                                                    case 'used':
                                                        echo '已使用';
                                                        break;
                                                    case 'expired':
                                                        echo '已过期';
                                                        break;
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($code['used_user_id']): ?>
                                            <?php 
                                                // 获取使用用户信息
                                                $conn = get_db_connection();
                                                $sql = "SELECT username FROM users WHERE id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->bind_param("i", $code['used_user_id']);
                                                $stmt->execute();
                                                $user = $stmt->get_result()->fetch_assoc();
                                                $stmt->close();
                                                $conn->close();
                                                echo $user ? htmlspecialchars($user['username']) : '未知用户';
                                            ?>
                                        <?php else: ?>
                                            未使用
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $code['expiry_date'] ? $code['expiry_date'] : '永久有效'; ?></td>
                                    <td><?php echo $code['created_at']; ?></td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($code['status'] === 'available'): ?>
                                                <a href="codes.php?delete=<?php echo $code['id']; ?>" class="btn btn-delete" onclick="return confirm('确定要删除此兑换码吗？');">删除</a>
                                            <?php else: ?>
                                                <span style="color: #999;">已使用/过期</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">暂无兑换码</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="codes.php?page=<?php echo $i; ?>&product_id=<?php echo $product_filter; ?>&status=<?php echo $status_filter; ?>&code=<?php echo urlencode($search_code); ?>" class="<?php echo $i == $page ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        </main>
    </div>
</body>
</html>