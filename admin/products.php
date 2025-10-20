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
$editing_product = null;

// 处理编辑商品
    if (isset($_POST['edit_product'])) {
        $id = intval($_POST['product_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $points_required = intval($_POST['points_required']);
        $stock = intval($_POST['stock']);
        $is_virtual = isset($_POST['is_virtual']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_top = isset($_POST['is_top']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order']);
        $restock_time = !empty($_POST['restock_time']) ? $_POST['restock_time'] : null;
        $restock_quantity = intval($_POST['restock_quantity']);
        $custom_tag = trim($_POST['custom_tag']);
        
        // 获取当前商品信息，用于处理图片
        $conn = get_db_connection();
        $sql = "SELECT image_path FROM products WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $current_product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        $image_path = $current_product['image_path'];
        
        // 检查是否删除图片
        if (isset($_POST['delete_image']) && $_POST['delete_image'] == 1 && !empty($image_path)) {
            // 删除旧图片文件
            if (file_exists('../' . $image_path)) {
                unlink('../' . $image_path);
            }
            $image_path = null;
        }
        
        // 检查是否上传了新图片
        if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] == UPLOAD_ERR_OK) {
            // 如果有旧图片，先删除
            if (!empty($image_path) && file_exists('../' . $image_path)) {
                unlink('../' . $image_path);
            }
            
            // 处理新图片上传
            $image_path = handle_image_upload($_FILES['image_path']);
            if (!$image_path) {
                $error = '图片上传失败';
            }
        }
    
    // 表单验证
    if (empty($name)) {
        $error = '请输入商品名称';
    } else if ($points_required <= 0) {
        $error = '所需积分必须大于0';
    } else if ($stock < 0) {
        $error = '库存不能为负数';
    } else if (!empty($error)) {
        // 已经有错误信息了
    } else {
            $result = update_product($id, $name, $description, $points_required, $stock, $is_virtual, $is_active, $is_top, $sort_order, $restock_time, $restock_quantity, $custom_tag, $image_path);
        
        if ($result['success']) {
            // 保存商品分类关联
            $category_ids = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
            add_product_to_category($id, $category_ids);
            
            $success = $result['message'];
            $editing_product = null;
        } else {
            $error = $result['message'];
        }
    }
}

// 处理图片上传
function handle_image_upload($file) {
    // 定义允许的图片类型
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // 检查文件类型
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    // 检查文件大小
    if ($file['size'] > $max_size) {
        return false;
    }
    
    // 创建uploads目录（如果不存在）
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // 生成唯一的文件名
    $filename = uniqid('product_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $target_path = $upload_dir . $filename;
    
    // 移动上传的文件
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // 返回相对路径
        return 'uploads/' . $filename;
    } else {
        return false;
    }
}

// 处理添加商品
    // 只有在明确是添加请求且没有product_id的情况下才执行添加逻辑
    if (isset($_POST['add_product']) && !isset($_POST['product_id'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $points_required = intval($_POST['points_required']);
        $stock = intval($_POST['stock']);
        $is_virtual = isset($_POST['is_virtual']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_top = isset($_POST['is_top']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order']);
        $restock_time = !empty($_POST['restock_time']) ? $_POST['restock_time'] : null;
        $restock_quantity = intval($_POST['restock_quantity']);
        $custom_tag = trim($_POST['custom_tag']);
        
        $image_path = null;
        
        // 检查是否上传了图片
        if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] == UPLOAD_ERR_OK) {
            // 处理图片上传
            $image_path = handle_image_upload($_FILES['image_path']);
            if (!$image_path) {
                $error = '图片上传失败';
            }
        }
    
    // 表单验证
    if (empty($name)) {
        $error = '请输入商品名称';
    } else if ($points_required <= 0) {
        $error = '所需积分必须大于0';
    } else if ($stock < 0) {
        $error = '库存不能为负数';
    } else if (!empty($error)) {
        // 已经有错误信息了
    } else {
            $result = add_product($name, $description, $points_required, $stock, $is_virtual, $is_active, $is_top, $sort_order, $restock_time, $restock_quantity, $custom_tag, $image_path);
        
        if ($result['success']) {
            // 保存商品分类关联
            if (!empty($result['product_id'])) {
                $category_ids = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
                add_product_to_category($result['product_id'], $category_ids);
            }
            
            $success = $result['message'];
            // 清空表单
            $_POST = array();
            $custom_tag = '';
        } else {
            $error = $result['message'];
        }
    }
}

// 处理编辑请求
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    
    $conn = get_db_connection();
    $sql = "SELECT * FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editing_product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
}

// 处理上下架请求
if (isset($_GET['toggle_active'])) {
    $id = intval($_GET['toggle_active']);
    
    $conn = get_db_connection();
    $sql = "UPDATE products SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    $success = '商品状态已更新';
}

// 处理置顶请求
if (isset($_GET['toggle_top'])) {
    $id = intval($_GET['toggle_top']);
    
    $conn = get_db_connection();
    $sql = "UPDATE products SET is_top = NOT is_top, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    $success = '置顶状态已更新';
}

// 处理删除商品请求
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $result = delete_product($id);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// 获取商品列表
$conn = get_db_connection();

// 分页设置
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page_size = 10;
$offset = ($page - 1) * $page_size;

// 计算总页数
$count_sql = "SELECT COUNT(*) AS count FROM products";
$count_result = $conn->query($count_sql);
$total_count = $count_result->fetch_assoc()['count'];
$total_pages = ceil($total_count / $page_size);

// 查询商品列表
$sql = "SELECT * FROM products ORDER BY is_top DESC, sort_order ASC, created_at DESC LIMIT ?, ?";
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
    <title>商品管理 - 积分系统</title>
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
        .add-product {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .add-product h2 {
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
        .products-table {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .products-table h2 {
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
        .badge-virtual {
            background-color: #9c27b0;
            color: white;
        }
        .badge-top {
            background-color: #ff9800;
            color: white;
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
                <h1>商品管理</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($editing_product): ?>
                <div class="add-product">
                    <h2>编辑商品</h2>
                    <form method="POST" enctype="multipart/form-data" action="products.php">
                        <input type="hidden" name="product_id" value="<?php echo $editing_product['id']; ?>">
                        
                        <div class="form-group">
                            <label for="name">商品名称</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editing_product['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">商品描述</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($editing_product['description']); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="points_required">所需积分</label>
                                <input type="number" id="points_required" name="points_required" value="<?php echo $editing_product['points_required']; ?>" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock">库存</label>
                                <input type="number" id="stock" name="stock" value="<?php echo $editing_product['stock']; ?>" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="is_virtual" <?php echo $editing_product['is_virtual'] ? 'checked' : ''; ?>>
                                    虚拟商品
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="is_active" <?php echo $editing_product['is_active'] ? 'checked' : ''; ?>>
                                    上架商品
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="is_top" <?php echo $editing_product['is_top'] ? 'checked' : ''; ?>>
                                    置顶商品
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sort_order">排序号</label>
                                <input type="number" id="sort_order" name="sort_order" value="<?php echo $editing_product['sort_order']; ?>" min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <h3>定时补货设置</h3>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="restock_time">补货时间</label>
                                <input type="datetime-local" id="restock_time" name="restock_time" value="<?php echo $editing_product['restock_time'] ? date('Y-m-d\TH:i', strtotime($editing_product['restock_time'])) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="restock_quantity">补货数量</label>
                                <input type="number" id="restock_quantity" name="restock_quantity" value="<?php echo $editing_product['restock_quantity']; ?>" min="0">
                            </div>
                        </div>
                         
                        <div class="form-group">
                            <label for="custom_tag">自定义标签（最多1个，50字符以内）</label>
                            <input type="text" id="custom_tag" name="custom_tag" value="<?php echo isset($editing_product['custom_tag']) ? htmlspecialchars($editing_product['custom_tag']) : ''; ?>" maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="image_path">商品图片</label>
                            <input type="file" id="image_path" name="image_path" accept="image/*">
                            <?php if (!empty($editing_product['image_path'] ?? '')): ?>
                            <div class="mt-2">
                                <img src="<?php echo htmlspecialchars($editing_product['image_path']); ?>" alt="<?php echo htmlspecialchars($editing_product['name'] ?? '商品图片'); ?>" style="max-width: 200px; max-height: 200px;">
                                <div class="mt-1">
                                    <label>
                                        <input type="checkbox" name="delete_image" value="1"> 删除图片
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 商品分类选择 -->
                        <div class="form-group">
                            <label>商品分类</label>
                            <?php 
                                // 获取所有分类
                                $categories = get_all_categories(1);
                                // 获取当前商品已选择的分类
                                $selected_categories = [];
                                if ($editing_product) {
                                    $conn = get_db_connection();
                                    $sql = "SELECT category_id FROM product_categories WHERE product_id = ?";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bind_param("i", $editing_product['id']);
                                    $stmt->execute();
                                    $result_cat = $stmt->get_result();
                                    while ($row = $result_cat->fetch_assoc()) {
                                        $selected_categories[] = $row['category_id'];
                                    }
                                    $stmt->close();
                                    $conn->close();
                                }
                            ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                <?php foreach ($categories as $category): ?>
                                    <label style="display: flex; align-items: center;">
                                        <input 
                                            type="checkbox" 
                                            name="category_ids[]" 
                                            value="<?php echo $category['id']; ?>" 
                                            <?php echo in_array($category['id'], $selected_categories) ? 'checked' : ''; ?>
                                            style="width: auto; margin-right: 5px;">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit" name="edit_product" class="btn btn-edit">保存修改</button>
                        <a href="products.php" class="btn">取消</a>
                    </form>
                </div>
            <?php else: ?>
                <div class="add-product">
                    <h2>添加商品</h2>
                    <form method="POST" enctype="multipart/form-data" action="products.php">
                        <div class="form-group">
                            <label for="name">商品名称</label>
                            <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">商品描述</label>
                            <textarea id="description" name="description" rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="points_required">所需积分</label>
                                <input type="number" id="points_required" name="points_required" value="<?php echo isset($_POST['points_required']) ? intval($_POST['points_required']) : 10; ?>" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock">库存</label>
                                <input type="number" id="stock" name="stock" value="<?php echo isset($_POST['stock']) ? intval($_POST['stock']) : 10; ?>" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="is_virtual" <?php echo isset($_POST['is_virtual']) ? 'checked' : ''; ?>>
                                    虚拟商品
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="is_active" <?php echo !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : ''; ?>>
                                    上架商品
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="is_top" <?php echo isset($_POST['is_top']) ? 'checked' : ''; ?>>
                                    置顶商品
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sort_order">排序号</label>
                                <input type="number" id="sort_order" name="sort_order" value="<?php echo isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0; ?>" min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <h3>定时补货设置</h3>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="restock_time">补货时间</label>
                                <input type="datetime-local" id="restock_time" name="restock_time" value="<?php echo isset($_POST['restock_time']) ? $_POST['restock_time'] : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="restock_quantity">补货数量</label>
                                <input type="number" id="restock_quantity" name="restock_quantity" value="<?php echo isset($_POST['restock_quantity']) ? intval($_POST['restock_quantity']) : 0; ?>" min="0">
                            </div>
                        </div>
                         
                        <div class="form-group">
                            <label for="custom_tag">自定义标签（最多1个，50字符以内）</label>
                            <input type="text" id="custom_tag" name="custom_tag" value="<?php echo isset($_POST['custom_tag']) ? htmlspecialchars($_POST['custom_tag']) : ''; ?>" maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="image_path">商品图片</label>
                            <input type="file" id="image_path" name="image_path" accept="image/*">
                        </div>
                        
                        <!-- 商品分类选择 -->
                        <div class="form-group">
                            <label>商品分类</label>
                            <?php 
                                // 获取所有分类
                                $categories = get_all_categories(1);
                            ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                <?php foreach ($categories as $category): ?>
                                    <label style="display: flex; align-items: center;">
                                        <input 
                                            type="checkbox" 
                                            name="category_ids[]" 
                                            value="<?php echo $category['id']; ?>"
                                            style="width: auto; margin-right: 5px;">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_product" class="btn">添加商品</button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div class="products-table">
                <h2>商品列表</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>商品名称</th>
                            <th>所需积分</th>
                            <th>库存</th>
                            <th>状态</th>
                            <th>属性</th>
                            <th>排序</th>
                            <th>补货设置</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($product = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['points_required']; ?></td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $product['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                            <?php echo $product['is_active'] ? '上架' : '下架'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($product['is_virtual']): ?>
                                            <span class="badge badge-virtual">虚拟</span>
                                        <?php endif; ?>
                                        <?php if ($product['is_top']): ?>
                                            <span class="badge badge-top">置顶</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product['sort_order']; ?></td>
                                    <td>
                                        <?php if ($product['restock_time']): ?>
                                            <?php echo $product['restock_time']; ?><br>
                                            +<?php echo $product['restock_quantity']; ?>
                                        <?php else: ?>
                                            无
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product['created_at']; ?></td>
                                    <td>
                                        <a href="products.php?edit=<?php echo $product['id']; ?>", class="btn btn-edit">编辑</a>
                                        <a href="products.php?toggle_active=<?php echo $product['id']; ?>", class="btn btn-toggle">
                                            <?php echo $product['is_active'] ? '下架' : '上架'; ?>
                                        </a>
                                        <a href="products.php?toggle_top=<?php echo $product['id']; ?>", class="btn btn-toggle">
                                            <?php echo $product['is_top'] ? '取消置顶' : '置顶'; ?>
                                        </a>
                                        <a href="products.php?delete=<?php echo $product['id']; ?>", class="btn btn-delete" onclick="return confirm('确定要删除该商品吗？删除后无法恢复，相关的兑换码也将被删除。');">删除</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center;">暂无商品</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="products.php?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>