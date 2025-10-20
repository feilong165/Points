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

// 创建分类表和分类商品关联表（如果不存在）
create_categories_table();
create_product_categories_table();

$error = '';
$success = '';
$editing_category = null;

// 处理编辑分类
if (isset($_POST['edit_category'])) {
    $id = intval($_POST['category_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $parent_id = isset($_POST['parent_id']) && intval($_POST['parent_id']) > 0 ? intval($_POST['parent_id']) : null;
    $sort_order = intval($_POST['sort_order']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // 表单验证
    if (empty($name)) {
        $error = '请输入分类名称';
    } else {
        $result = update_category($id, $name, $description, $parent_id, $sort_order, $is_active);

        if ($result['success']) {
            $success = $result['message'];
            $editing_category = null;
        } else {
            $error = $result['message'];

            // 获取要编辑的分类信息
            $editing_category = get_category($id);
        }
    }
}

// 处理添加分类
if (isset($_POST['add_category']) && !isset($_POST['category_id'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $parent_id = isset($_POST['parent_id']) && intval($_POST['parent_id']) > 0 ? intval($_POST['parent_id']) : null;
    $sort_order = intval($_POST['sort_order']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // 表单验证
    if (empty($name)) {
        $error = '请输入分类名称';
    } else {
        $result = add_category($name, $description, $parent_id, $sort_order, $is_active);

        if ($result['success']) {
            $success = $result['message'];
            // 清空表单
            $_POST = array();
        } else {
            $error = $result['message'];
        }
    }
}

// 处理编辑请求
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $editing_category = get_category($id);
}

// 处理上下架请求
if (isset($_GET['toggle_active'])) {
    $id = intval($_GET['toggle_active']);

    $result = toggle_category_status($id);

    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// 处理删除分类请求
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $result = delete_category($id);

    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// 获取分类列表
$conn = get_db_connection();

// 分页设置
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page_size = 10;
$offset = ($page - 1) * $page_size;

// 计算总页数
$count_sql = "SELECT COUNT(*) AS count FROM categories";
$count_result = $conn->query($count_sql);
$total_count = $count_result->fetch_assoc()['count'];
$total_pages = ceil($total_count / $page_size);

// 查询分类列表
$sql = "SELECT c.*, p.name AS parent_name FROM categories c LEFT JOIN categories p ON c.parent_id = p.id ORDER BY sort_order ASC, created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $page_size);
$stmt->execute();
$result = $stmt->get_result();

// 获取所有分类（用于选择父分类）
$all_categories_sql = "SELECT id, name, parent_id FROM categories ORDER BY sort_order ASC";
$all_categories_result = $conn->query($all_categories_sql);

$all_categories = array();
while ($category = $all_categories_result->fetch_assoc()) {
    $all_categories[$category['id']] = $category;
}

$stmt->close();
$conn->close();

// 递归获取分类树
function build_category_tree($categories, $parent_id = null, $level = 0) {
    $tree = array();
    foreach ($categories as $id => $category) {
        if ($category['parent_id'] == $parent_id) {
            $category['level'] = $level;
            $tree[] = $category;
            $children = build_category_tree($categories, $id, $level + 1);
            $tree = array_merge($tree, $children);
        }
    }
    return $tree;
}

$category_tree = build_category_tree($all_categories);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分类管理 - 积分系统</title>
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
        .add-category {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .add-category h2 {
            margin-top: 0;
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
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
        .btn-toggle {
            background-color: #ff9800;
        }
        .btn-toggle:hover {
            background-color: #f57c00;
        }
        .categories-table {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .categories-table h2 {
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
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        .checkbox-group input {
            width: auto;
            margin-right: 5px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-active {
            background-color: #4CAF50;
            color: white;
        }
        .badge-inactive {
            background-color: #f44336;
            color: white;
        }
        .category-level {
            padding-left: 20px;
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
                <h1>分类管理</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($editing_category): ?>
                <div class="add-category">
                    <h2>编辑分类</h2>
                    <form method="POST" action="categories.php">
                        <input type="hidden" name="category_id" value="<?php echo $editing_category['id']; ?>">
                        
                        <div class="form-group">
                            <label for="name">分类名称</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editing_category['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">分类描述</label>
                            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($editing_category['description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_id">父分类</label>
                            <select id="parent_id" name="parent_id">
                                <option value="0">无父分类（顶级分类）</option>
                                <?php foreach ($category_tree as $category): ?>
                                    <?php if ($category['id'] != $editing_category['id']): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo ($category['id'] == $editing_category['parent_id']) ? 'selected' : ''; ?>>
                                            <?php echo str_repeat('— ', $category['level']) . htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sort_order">排序号</label>
                                <input type="number" id="sort_order" name="sort_order" value="<?php echo $editing_category['sort_order']; ?>" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="is_active" <?php echo $editing_category['is_active'] ? 'checked' : ''; ?>>
                                    启用分类
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" name="edit_category" class="btn btn-edit">保存修改</button>
                        <a href="categories.php" class="btn">取消</a>
                    </form>
                </div>
            <?php else: ?>
                <div class="add-category">
                    <h2>添加分类</h2>
                    <form method="POST" action="categories.php">
                        <div class="form-group">
                            <label for="name">分类名称</label>
                            <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">分类描述</label>
                            <textarea id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_id">父分类</label>
                            <select id="parent_id" name="parent_id">
                                <option value="0">无父分类（顶级分类）</option>
                                <?php foreach ($category_tree as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['parent_id']) && intval($_POST['parent_id']) == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo str_repeat('— ', $category['level']) . htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sort_order">排序号</label>
                                <input type="number" id="sort_order" name="sort_order" value="<?php echo isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0; ?>" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="is_active" <?php echo !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : ''; ?>>
                                    启用分类
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_category" class="btn">添加分类</button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div class="categories-table">
                <h2>分类列表</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>分类名称</th>
                            <th>父分类</th>
                            <th>描述</th>
                            <th>排序</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($category = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td>
                                        <?php 
                                        // 获取分类级别
                                        $level = 0;
                                        $current_parent = $category['parent_id'];
                                        while ($current_parent && isset($all_categories[$current_parent])) {
                                            $level++;
                                            $current_parent = $all_categories[$current_parent]['parent_id'];
                                        }
                                        echo str_repeat('<span class="category-level"></span>', $level) . htmlspecialchars($category['name']);
                                        ?>
                                    </td>
                                    <td><?php echo $category['parent_name'] ? htmlspecialchars($category['parent_name']) : '无'; ?></td>
                                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                                    <td><?php echo $category['sort_order']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $category['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                            <?php echo $category['is_active'] ? '启用' : '禁用'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $category['created_at']; ?></td>
                                    <td>
                                        <a href="categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-edit">编辑</a>
                                        <a href="categories.php?toggle_active=<?php echo $category['id']; ?>" class="btn btn-toggle">
                                            <?php echo $category['is_active'] ? '禁用' : '启用'; ?>
                                        </a>
                                        <a href="categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-delete" onclick="return confirm('确定要删除该分类吗？删除后，分类下的商品不会被删除，但将不再属于该分类。');">删除</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">暂无分类</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="categories.php?page=1">首页</a>
                            <a href="categories.php?page=<?php echo $page - 1; ?>">上一页</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="categories.php?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="categories.php?page=<?php echo $page + 1; ?>">下一页</a>
                            <a href="categories.php?page=<?php echo $total_pages; ?>">末页</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>