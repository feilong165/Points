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
        // 添加搜索玩法配置
        $keyword = trim($_POST['keyword']);
        $target_url = trim($_POST['target_url']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if (!empty($keyword) && !empty($target_url)) {
            add_search_play_config($keyword, $target_url, $status);
            $success = '搜索玩法配置添加成功';
        } else {
            $error = '请输入关键词和目标URL';
        }
    } elseif (isset($_POST['update'])) {
        // 更新搜索玩法配置
        $id = intval($_POST['id']);
        $keyword = trim($_POST['keyword']);
        $target_url = trim($_POST['target_url']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if (!empty($keyword) && !empty($target_url)) {
            update_search_play_config($id, $keyword, $target_url, $status);
            $success = '搜索玩法配置更新成功';
        } else {
            $error = '请输入关键词和目标URL';
        }
    } elseif (isset($_POST['delete'])) {
        // 删除搜索玩法配置
        $id = intval($_POST['id']);
        delete_search_play_config($id);
        $success = '搜索玩法配置删除成功';
    }
}

// 获取所有搜索玩法配置
$play_configs = get_all_search_play_configs();

// 初始化编辑模式
$edit_mode = false;
$current_item = null;

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $current_item = get_search_play_config_by_id($edit_id);
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
    <title>搜索玩法设置 - 积分系统管理后台</title>
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
        .form-group input[type="text"] {
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
        .target-url-preview {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
            word-break: break-all;
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
                        <li><a href="search_play_settings.php" style="background-color: #34495e;">玩法设置</a></li>
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
            <h1>搜索玩法设置</h1>
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
            <h3><?php echo $edit_mode ? '编辑' : '添加'; ?>搜索玩法配置</h3>
            <form method="post">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id" value="<?php echo $current_item['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="keyword">搜索关键词</label>
                    <input type="text" id="keyword" name="keyword" value="<?php echo $edit_mode ? htmlspecialchars($current_item['keyword']) : ''; ?>" placeholder="用户搜索的关键词" required>
                    <small style="color: #666; display: block; margin-top: 5px;">注意：关键词必须唯一，用户搜索此关键词将直接跳转到指定页面</small>
                </div>
                
                <div class="form-group">
                    <label for="target_url">目标URL</label>
                    <input type="text" id="target_url" name="target_url" value="<?php echo $edit_mode ? htmlspecialchars($current_item['target_url']) : ''; ?>" placeholder="跳转到的页面URL" required>
                    <small style="color: #666; display: block; margin-top: 5px;">示例：product_detail.php?id=123 或 https://example.com</small>
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
                        <a href="search_play_settings.php" class="btn btn-secondary">取消</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h3>搜索玩法配置列表</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>搜索关键词</th>
                        <th>目标URL</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($play_configs)): ?>
                        <?php foreach ($play_configs as $item): ?>
                            <tr>
                                <td><?php echo $item['id']; ?></td>
                                <td><?php echo htmlspecialchars($item['keyword']); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars(substr($item['target_url'], 0, 50)); ?><?php echo strlen($item['target_url']) > 50 ? '...' : ''; ?></div>
                                    <div class="target-url-preview">
                                        <i class="fas fa-link" style="margin-right: 5px;"></i>
                                        <?php echo htmlspecialchars($item['target_url']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $item['status'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $item['status'] ? '启用' : '禁用'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="search_play_settings.php?edit=<?php echo $item['id']; ?>" class="btn">编辑</a>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('确定要删除这个搜索玩法配置吗？');">
                                            删除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">暂无搜索玩法配置</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
                <h4 style="margin-top: 0;">使用说明</h4>
                <ol style="margin-bottom: 0;">
                    <li>添加配置后，用户搜索指定关键词将直接跳转到配置的目标URL。</li>
                    <li>可以配置跳转到特定商品详情页、活动页面或外部链接。</li>
                    <li>关键词必须唯一，系统会优先匹配完全相同的关键词。</li>
                    <li>可以通过启用/禁用状态来控制配置是否生效。</li>
                </ol>
            </div>
        </div>
    </main>
    </div>
</body>
</html>