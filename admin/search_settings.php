<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 检查管理员是否登录
// 注意：session_start()必须在任何HTML输出之前调用
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 确保搜索相关表存在
ensure_search_tables_exists();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        // 添加推荐搜索
        $keyword = trim($_POST['keyword']);
        $sort_order = intval($_POST['sort_order']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if (!empty($keyword)) {
            add_recommended_search($keyword, $sort_order, $status);
            $success = '推荐搜索添加成功';
        } else {
            $error = '请输入关键词';
        }
    } elseif (isset($_POST['update'])) {
        // 更新推荐搜索
        $id = intval($_POST['id']);
        $keyword = trim($_POST['keyword']);
        $sort_order = intval($_POST['sort_order']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if (!empty($keyword)) {
            update_recommended_search($id, $keyword, $sort_order, $status);
            $success = '推荐搜索更新成功';
        } else {
            $error = '请输入关键词';
        }
    } elseif (isset($_POST['delete'])) {
        // 删除推荐搜索
        $id = intval($_POST['id']);
        delete_recommended_search($id);
        $success = '推荐搜索删除成功';
    }
}

// 获取所有推荐搜索
$recommended_searches = get_all_recommended_searches();

// 初始化编辑模式
$edit_mode = false;
$current_item = null;

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $current_item = get_recommended_search_by_id($edit_id);
    if ($current_item) {
        $edit_mode = true;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜索设置 - 积分系统管理后台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        .header .back-link {
            color: #3498db;
            text-decoration: none;
        }
        .header .back-link:hover {
            text-decoration: underline;
        }
        .card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group input[type="checkbox"] {
            margin-right: 5px;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        .btn-secondary:hover {
            background-color: #e0e0e0;
        }
        .btn-danger {
            background-color: #f44336;
        }
        .btn-danger:hover {
            background-color: #d32f2f;
        }
        .btn-group {
            margin-top: 15px;
        }
        .btn-group .btn {
            margin-right: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
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
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .message-error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
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
        .form-actions {
            display: flex;
            gap: 10px;
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
                        <li><a href="search_settings.php" style="background-color: #34495e;">搜索设置</a></li>
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
            <h1>搜索设置</h1>
            <a href="dashboard.php" class="back-link">返回</a>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="message message-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message message-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h3><?php echo $edit_mode ? '编辑' : '添加'; ?>推荐搜索</h3>
            <form method="post">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id" value="<?php echo $current_item['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="keyword">关键词</label>
                    <input type="text" id="keyword" name="keyword" value="<?php echo $edit_mode ? htmlspecialchars($current_item['keyword']) : ''; ?>" placeholder="请输入推荐搜索关键词" required>
                </div>
                
                <div class="form-group">
                    <label for="sort_order">排序</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?php echo $edit_mode ? $current_item['sort_order'] : 0; ?>" min="0" placeholder="数字越小越靠前">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="status" <?php echo ($edit_mode ? $current_item['status'] : true) ? 'checked' : ''; ?>>
                        启用
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="<?php echo $edit_mode ? 'update' : 'add'; ?>" class="btn">
                        <?php echo $edit_mode ? '更新' : '添加'; ?>
                    </button>
                    
                    <?php if ($edit_mode): ?>
                        <a href="search_settings.php" class="btn btn-secondary">取消</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h3>推荐搜索列表</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>关键词</th>
                        <th>排序</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recommended_searches)): ?>
                        <?php foreach ($recommended_searches as $item): ?>
                            <tr>
                                <td><?php echo $item['id']; ?></td>
                                <td><?php echo htmlspecialchars($item['keyword']); ?></td>
                                <td><?php echo $item['sort_order']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $item['status'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $item['status'] ? '启用' : '禁用'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="search_settings.php?edit=<?php echo $item['id']; ?>" class="btn">编辑</a>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('确定要删除这个推荐搜索吗？');">
                                            删除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">暂无推荐搜索内容</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    </div>
</body>
</html>