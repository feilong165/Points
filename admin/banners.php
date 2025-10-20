<?php
// 检查管理员是否登录
require_once '../app/app.php';
if (!is_admin_logged_in()) {
    header('Location: login.php');
    exit;
}

// 初始化Banner表（如果不存在）
create_banners_table();

// 处理Banner图片上传
function handle_banner_image_upload($file) {
    // 定义允许的图片类型
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // 检查文件类型
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => '不支持的图片格式，请上传JPG、PNG或GIF格式'];
    }
    
    // 检查文件大小
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => '图片大小不能超过5MB'];
    }
    
    // 创建uploads目录（如果不存在）
    $upload_dir = '../uploads/banners/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // 生成唯一的文件名
    $filename = uniqid('banner_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $target_path = $upload_dir . $filename;
    
    // 移动上传的文件
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // 返回相对路径
        return ['success' => true, 'image_path' => 'uploads/banners/' . $filename];
    } else {
        return ['success' => false, 'message' => '图片上传失败，请稍后重试'];
    }
}

// 处理裁剪后的图片数据
function handle_cropped_image($cropped_data) {
    // 检查是否有裁剪后的图片数据
    if (!empty($cropped_data) && strpos($cropped_data, 'data:image/') === 0) {
        // 提取图片数据
        $data_parts = explode(',', $cropped_data);
        $image_data = base64_decode($data_parts[1]);
        
        // 创建uploads目录（如果不存在）
        $upload_dir = '../uploads/banners/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // 生成唯一的文件名
        $filename = uniqid('banner_', true) . '.jpg';
        $target_path = $upload_dir . $filename;
        
        // 保存裁剪后的图片
        if (file_put_contents($target_path, $image_data)) {
            return 'uploads/banners/' . $filename;
        }
    }
    return false;
}

// 删除Banner图片
function delete_banner_image($image_path) {
    if (!empty($image_path) && file_exists('../' . $image_path)) {
        unlink('../' . $image_path);
        return true;
    }
    return false;
}

// 处理操作
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add':
            // 添加Banner
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $image_url = trim($_POST['image_url']);
            $link_url = trim($_POST['link_url']);
            // 确保链接URL格式正确
            if (!empty($link_url) && !preg_match('/^https?:\/\//i', $link_url)) {
                $link_url = 'http://' . $link_url;
            }
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $sort_order = intval($_POST['sort_order']);
            $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
            $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
            
            // 处理图片上传或裁剪后的图片
            // 优先检查裁剪后的图片数据
            if (!empty($_POST['cropped_image_data'])) {
                $cropped_image_path = handle_cropped_image($_POST['cropped_image_data']);
                if ($cropped_image_path) {
                    $image_url = $cropped_image_path;
                } else {
                    header('Location: banners.php?msg=' . urlencode('裁剪图片保存失败'));
                    exit;
                }
            }
            // 其次检查传统文件上传
            else if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] == UPLOAD_ERR_OK) {
                // 处理图片上传
                $upload_result = handle_banner_image_upload($_FILES['image_upload']);
                if ($upload_result['success']) {
                    $image_url = $upload_result['image_path'];
                } else {
                    header('Location: banners.php?msg=' . urlencode($upload_result['message']));
                    exit;
                }
            }
            
            $result = add_banner($title, $description, $image_url, $link_url, $is_active, $sort_order, $start_time, $end_time);
            header('Location: banners.php?msg=' . ($result['success'] ? urlencode($result['message']) : urlencode($result['message'])));
            exit;
            break;
            
        case 'update':
            // 更新Banner
            $banner_id = intval($_POST['banner_id']);
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $image_url = trim($_POST['image_url']);
            $link_url = trim($_POST['link_url']);
            // 确保链接URL格式正确
            if (!empty($link_url) && !preg_match('/^https?:\/\//i', $link_url)) {
                $link_url = 'http://' . $link_url;
            }
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $sort_order = intval($_POST['sort_order']);
            $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
            $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
            
            // 获取原图片路径
            $original_banner = get_banner_by_id($banner_id);
            $original_image_url = $original_banner['image_url'] ?? '';
            
            // 处理图片上传或裁剪后的图片
            // 优先检查裁剪后的图片数据
            if (!empty($_POST['cropped_image_data'])) {
                $cropped_image_path = handle_cropped_image($_POST['cropped_image_data']);
                if ($cropped_image_path) {
                    // 删除原图片
                    if (!empty($original_image_url)) {
                        delete_banner_image($original_image_url);
                    }
                    $image_url = $cropped_image_path;
                } else {
                    header('Location: banners.php?msg=' . urlencode('裁剪图片保存失败'));
                    exit;
                }
            }
            // 其次检查传统文件上传
            else if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] == UPLOAD_ERR_OK) {
                // 处理图片上传
                $upload_result = handle_banner_image_upload($_FILES['image_upload']);
                if ($upload_result['success']) {
                    // 删除原图片
                    if (!empty($original_image_url)) {
                        delete_banner_image($original_image_url);
                    }
                    $image_url = $upload_result['image_path'];
                } else {
                    header('Location: banners.php?msg=' . urlencode($upload_result['message']));
                    exit;
                }
            }
            
            $result = update_banner($banner_id, $title, $description, $image_url, $link_url, $is_active, $sort_order, $start_time, $end_time);
            header('Location: banners.php?msg=' . ($result['success'] ? urlencode($result['message']) : urlencode($result['message'])));
            exit;
            break;
            
        case 'delete':
            // 删除Banner
            $banner_id = intval($_POST['banner_id']);
            
            // 获取要删除的Banner信息，以便删除对应的图片
            $banner = get_banner_by_id($banner_id);
            if ($banner) {
                // 删除图片文件
                delete_banner_image($banner['image_url']);
            }
            
            $result = delete_banner($banner_id);
            header('Location: banners.php?msg=' . ($result['success'] ? urlencode($result['message']) : urlencode($result['message'])));
            exit;
            break;
            
        case 'update_status':
            // 更新Banner状态
            $banner_id = intval($_POST['banner_id']);
            $is_active = intval($_POST['is_active']);
            $result = update_banner_status($banner_id, $is_active);
            echo json_encode($result);
            exit;
            break;
    }
}

// 获取编辑的Banner
$edit_banner = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $banner_id = intval($_GET['id']);
    $edit_banner = get_banner_by_id($banner_id);
    if (!$edit_banner) {
        header('Location: banners.php?msg=Banner不存在');
        exit;
    }
}

// 获取Banner列表
$banners = get_banners();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banner管理 - 积分商城管理系统</title>
    <link rel="stylesheet" href="css/common.css">
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
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            margin: 5px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-primary {
            background-color: #2196F3;
        }
        .btn-primary:hover {
            background-color: #0b7dda;
        }
        .btn-danger {
            background-color: #f44336;
        }
        .btn-danger:hover {
            background-color: #da190b;
        }
        .btn-secondary {
            background-color: #9e9e9e;
        }
        .btn-secondary:hover {
            background-color: #757575;
        }
        .add-btn {
            margin-bottom: 20px;
            display: inline-block;
        }
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background-color: #4CAF50;
            color: white;
        }
        .status-inactive {
            background-color: #9e9e9e;
            color: white;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"], textarea, input[type="url"], input[type="number"], input[type="datetime-local"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        input[type="checkbox"] {
            margin-right: 10px;
        }
        .form-actions {
            text-align: right;
            margin-top: 30px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2196F3;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .image-preview {
            max-width: 200px;
            max-height: 100px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 4px;
        }
        .modal-header {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        .modal-footer {
            padding: 10px 0;
            border-top: 1px solid #eee;
            margin-top: 20px;
            text-align: right;
        }
        .modal-title {
            font-size: 18px;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            .btn {
                display: block;
                width: 100%;
                margin: 5px 0;
                text-align: center;
            }
            .table-container {
                font-size: 14px;
            }
            th, td {
                padding: 8px;
            }
            .form-container {
                padding: 20px 15px;
            }
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
                <h1>Banner管理</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
        
        <!-- 消息提示 -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="success-message"><?php echo urldecode($_GET['msg']); ?></div>
        <?php endif; ?>
        
        <!-- 添加或编辑Banner页面 -->
        <?php if ($edit_banner): ?>
            <a href="banners.php" class="back-link">← 返回Banner列表</a>
            
            <div class="form-container">
                <h2>编辑Banner</h2>
                <form method="post" action="banners.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="banner_id" value="<?php echo $edit_banner['id']; ?>">
                    
                    <div class="form-group">
                        <label for="title">标题 <span style="color: red;">*</span></label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($edit_banner['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">描述</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($edit_banner['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                            <label for="image_upload">上传图片 <span style="color: red;">*</span></label>
                            <input type="file" id="image_upload" name="image_upload" accept="image/jpeg, image/png">
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">支持JPG、PNG格式，上传后可裁剪为2:1比例显示</p>
                            <input type="hidden" id="image_url" name="image_url" value="<?php echo htmlspecialchars($edit_banner['image_url']); ?>" required>
                            <input type="hidden" id="cropped_image_data" name="cropped_image_data">
                            <img src="<?php echo htmlspecialchars($edit_banner['image_url']); ?>" alt="Banner预览" class="image-preview" style="display: block;">
                            <div id="crop_container" style="display: none; margin-top: 10px;">
                                <canvas id="crop_canvas" style="max-width: 100%; border: 1px solid #ddd;"></canvas>
                                <div style="margin-top: 10px;">
                                    <button type="button" id="crop_image" class="btn btn-primary">确认裁剪</button>
                                    <button type="button" id="cancel_crop" class="btn btn-secondary">取消裁剪</button>
                                </div>
                            </div>
                        </div>
                    
                    <div class="form-group">
                        <label for="link_url">链接URL</label>
                        <input type="url" id="link_url" name="link_url" value="<?php echo htmlspecialchars($edit_banner['link_url']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="sort_order">排序</label>
                        <input type="number" id="sort_order" name="sort_order" value="<?php echo $edit_banner['sort_order']; ?>" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">开始时间</label>
                        <input type="datetime-local" id="start_time" name="start_time" value="<?php echo !empty($edit_banner['start_time']) ? date('Y-m-d\TH:i', strtotime($edit_banner['start_time'])) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">结束时间</label>
                        <input type="datetime-local" id="end_time" name="end_time" value="<?php echo !empty($edit_banner['end_time']) ? date('Y-m-d\TH:i', strtotime($edit_banner['end_time'])) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" <?php echo $edit_banner['is_active'] ? 'checked' : ''; ?>>
                            启用Banner
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">保存修改</button>
                        <a href="banners.php" class="btn btn-secondary">取消</a>
                    </div>
                </form>
            </div>
        
        <!-- 添加Banner按钮 -->
        <?php else: ?>
            <a href="banners.php?action=add" class="btn btn-primary add-btn">添加新Banner</a>
            
            <!-- Banner列表 -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>标题</th>
                            <th>图片</th>
                            <th>链接</th>
                            <th>状态</th>
                            <th>排序</th>
                            <th>开始时间</th>
                            <th>结束时间</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($banners)): ?>
                            <tr>
                                <td colspan="10" class="no-data">暂无Banner数据</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($banners as $banner): ?>
                                <tr>
                                    <td><?php echo $banner['id']; ?></td>
                                    <td><?php echo htmlspecialchars($banner['title']); ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" alt="Banner" style="max-width: 100px; max-height: 50px; border-radius: 4px;">
                                    </td>
                                    <td style="max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php if (!empty($banner['link_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($banner['link_url']); ?>" target="_blank">查看链接</a>
                                        <?php else: ?>
                                            -  
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $banner['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $banner['is_active'] ? '启用' : '禁用'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $banner['sort_order']; ?></td>
                                    <td><?php echo !empty($banner['start_time']) ? date('Y-m-d H:i', strtotime($banner['start_time'])) : '-'; ?></td>
                                    <td><?php echo !empty($banner['end_time']) ? date('Y-m-d H:i', strtotime($banner['end_time'])) : '-'; ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($banner['created_at'])); ?></td>
                                    <td>
                                        <a href="banners.php?action=edit&id=<?php echo $banner['id']; ?>" class="btn btn-primary">编辑</a>
                                        <button class="btn btn-secondary" onclick="toggleBannerStatus(<?php echo $banner['id']; ?>, <?php echo $banner['is_active'] ? '0' : '1'; ?>, '<?php echo $banner['is_active'] ? '禁用' : '启用'; ?>')">
                                            <?php echo $banner['is_active'] ? '禁用' : '启用'; ?>
                                        </button>
                                        <button class="btn btn-danger" onclick="deleteBanner(<?php echo $banner['id']; ?>, '<?php echo addslashes(htmlspecialchars($banner['title'])); ?>')">删除</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 添加Banner表单 -->
            <?php if (isset($_GET['action']) && $_GET['action'] == 'add'): ?>
                <div class="form-container">
                    <h2>添加新Banner</h2>
                    <form method="post" action="banners.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group">
                            <label for="title">标题 <span style="color: red;">*</span></label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">描述</label>
                            <textarea id="description" name="description"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="image_upload">上传图片 <span style="color: red;">*</span></label>
                            <input type="file" id="image_upload" name="image_upload" accept="image/jpeg, image/png">
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">支持JPG、PNG格式，上传后可裁剪为2:1比例显示</p>
                            <input type="hidden" id="image_url" name="image_url" required>
                            <input type="hidden" id="cropped_image_data" name="cropped_image_data">
                            <img id="new_image_preview" class="image-preview" style="display: none;">
                            <div id="crop_container" style="display: none; margin-top: 10px;">
                                <canvas id="crop_canvas" style="max-width: 100%; border: 1px solid #ddd;"></canvas>
                                <div style="margin-top: 10px;">
                                    <button type="button" id="crop_image" class="btn btn-primary">确认裁剪</button>
                                    <button type="button" id="cancel_crop" class="btn btn-secondary">取消裁剪</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="link_url">链接URL</label>
                            <input type="url" id="link_url" name="link_url">
                        </div>
                        
                        <div class="form-group">
                            <label for="sort_order">排序</label>
                            <input type="number" id="sort_order" name="sort_order" value="0" min="0">
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">数字越小，排序越靠前</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_time">开始时间</label>
                            <input type="datetime-local" id="start_time" name="start_time">
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">留空表示立即生效</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_time">结束时间</label>
                            <input type="datetime-local" id="end_time" name="end_time">
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">留空表示永久有效</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" checked>
                                启用Banner
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">添加Banner</button>
                            <a href="banners.php" class="btn btn-secondary">取消</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </main>
    </div>
    
    <!-- 删除确认模态框 -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">确认删除</h3>
            </div>
            <div class="modal-body">
                <p>确定要删除Banner "<span id="deleteBannerTitle"></span>" 吗？此操作不可恢复。</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="post" action="banners.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteBannerId" name="banner_id" value="">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">取消</button>
                    <button type="submit" class="btn btn-danger">确认删除</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // 打开模态框
        function openModal(modalId) {
            document.getElementById(modalId).style.display = "block";
        }
        
        // 关闭模态框
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }
        
        // 删除Banner
        function deleteBanner(bannerId, bannerTitle) {
            document.getElementById('deleteBannerId').value = bannerId;
            document.getElementById('deleteBannerTitle').textContent = bannerTitle;
            openModal('deleteModal');
        }
        
        // 切换Banner状态
        function toggleBannerStatus(bannerId, newStatus, actionText) {
            if (confirm(`确定要${actionText}此Banner吗？`)) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'banners.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    // 刷新页面
                                    window.location.reload();
                                } else {
                                    alert(response.message);
                                }
                            } catch (e) {
                                alert('操作失败，请刷新页面重试');
                            }
                        } else {
                            alert('网络异常，请重试');
                        }
                    }
                };
                xhr.send(`action=update_status&banner_id=${bannerId}&is_active=${newStatus}`);
            }
        }
        
        // 图片上传预览和裁剪
        function setupImageUpload() {
            const imageUpload = document.getElementById('image_upload');
            const imageUrlInput = document.getElementById('image_url');
            const newImagePreview = document.getElementById('new_image_preview');
            const existingPreview = document.querySelector('.image-preview[src]');
            const cropContainer = document.getElementById('crop_container');
            const cropCanvas = document.getElementById('crop_canvas');
            const cropButton = document.getElementById('crop_image');
            const cancelCropButton = document.getElementById('cancel_crop');
            const croppedImageDataInput = document.getElementById('cropped_image_data');
            
            // 用于裁剪的数据
            let currentImage = null;
            let isDragging = false;
            let startX = 0;
            let startY = 0;
            let offsetX = 0;
            let offsetY = 0;
            let scale = 1;
            let canvasContext = null;
            
            if (imageUpload && cropCanvas) {
                canvasContext = cropCanvas.getContext('2d');
                
                // 设置canvas尺寸，保持2:1比例
                function setupCanvas() {
                    const containerWidth = cropContainer.parentElement.clientWidth - 20; // 减去边距
                    const canvasWidth = Math.min(containerWidth, 800); // 最大宽度限制
                    const canvasHeight = canvasWidth / 2; // 2:1比例
                    cropCanvas.width = canvasWidth;
                    cropCanvas.height = canvasHeight;
                }
                
                // 绘制裁剪区域
                function drawCropArea() {
                    if (!currentImage) return;
                    
                    // 清除画布
                    canvasContext.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
                    
                    // 绘制背景网格（可选）
                    canvasContext.fillStyle = '#f0f0f0';
                    for (let y = 0; y < cropCanvas.height; y += 20) {
                        for (let x = 0; x < cropCanvas.width; x += 20) {
                            if ((x / 20 + y / 20) % 2 === 0) {
                                canvasContext.fillRect(x, y, 20, 20);
                            }
                        }
                    }
                    
                    // 计算图片绘制位置
                    const imageRatio = currentImage.width / currentImage.height;
                    let drawWidth, drawHeight;
                    
                    if (imageRatio > 2) {
                        // 宽图
                        drawWidth = currentImage.width * scale;
                        drawHeight = currentImage.height * scale;
                    } else {
                        // 高图或正方形
                        drawHeight = cropCanvas.height;
                        drawWidth = currentImage.width * (drawHeight / currentImage.height);
                        scale = drawHeight / currentImage.height;
                    }
                    
                    // 确保图片不会太小
                    if (drawWidth < cropCanvas.width) {
                        drawWidth = cropCanvas.width;
                        drawHeight = currentImage.height * (drawWidth / currentImage.width);
                        scale = drawWidth / currentImage.width;
                    }
                    
                    // 绘制图片
                    canvasContext.drawImage(
                        currentImage, 
                        (cropCanvas.width - drawWidth) / 2 + offsetX, 
                        (cropCanvas.height - drawHeight) / 2 + offsetY, 
                        drawWidth, 
                        drawHeight
                    );
                    
                    // 绘制裁剪框
                    canvasContext.strokeStyle = 'rgba(255, 0, 0, 0.5)';
                    canvasContext.lineWidth = 2;
                    canvasContext.strokeRect(0, 0, cropCanvas.width, cropCanvas.height);
                }
                
                // 绑定鼠标事件进行拖动
                cropCanvas.addEventListener('mousedown', function(e) {
                    isDragging = true;
                    startX = e.offsetX;
                    startY = e.offsetY;
                });
                
                window.addEventListener('mousemove', function(e) {
                    if (!isDragging) return;
                    
                    const rect = cropCanvas.getBoundingClientRect();
                    const mouseX = e.clientX - rect.left;
                    const mouseY = e.clientY - rect.top;
                    
                    offsetX += mouseX - startX;
                    offsetY += mouseY - startY;
                    
                    startX = mouseX;
                    startY = mouseY;
                    
                    drawCropArea();
                });
                
                window.addEventListener('mouseup', function() {
                    isDragging = false;
                });
                
                // 绑定滚轮事件进行缩放
                cropCanvas.addEventListener('wheel', function(e) {
                    e.preventDefault();
                    const delta = e.deltaY > 0 ? 0.9 : 1.1;
                    scale *= delta;
                    // 限制缩放范围
                    scale = Math.max(0.5, Math.min(3, scale));
                    drawCropArea();
                });
                
                // 确认裁剪
                cropButton.addEventListener('click', function() {
                    // 创建2:1比例的临时canvas用于最终裁剪
                    const tempCanvas = document.createElement('canvas');
                    const tempContext = tempCanvas.getContext('2d');
                    tempCanvas.width = 1200; // 最终宽度
                    tempCanvas.height = 600; // 最终高度（2:1比例）
                    
                    // 直接绘制原始图片到临时canvas，不包含红色边框和白色背景
                    if (currentImage) {
                        // 计算图片在临时canvas上的绘制参数
                        const imageRatio = currentImage.width / currentImage.height;
                        const targetRatio = tempCanvas.width / tempCanvas.height; // 2:1
                        let sourceX, sourceY, sourceWidth, sourceHeight;
                        
                        // 根据图片比例确定裁剪策略
                        if (imageRatio > targetRatio) {
                            // 宽图 - 按高度裁剪
                            sourceHeight = currentImage.height;
                            sourceWidth = currentImage.height * targetRatio;
                            sourceX = (currentImage.width - sourceWidth) / 2;
                            sourceY = 0;
                        } else {
                            // 高图或正方形 - 按宽度裁剪
                            sourceWidth = currentImage.width;
                            sourceHeight = currentImage.width / targetRatio;
                            sourceX = 0;
                            sourceY = (currentImage.height - sourceHeight) / 2;
                        }
                        
                        // 直接绘制图片到临时canvas，确保填充整个区域
                        tempContext.drawImage(
                            currentImage, 
                            sourceX, 
                            sourceY, 
                            sourceWidth, 
                            sourceHeight, 
                            0, 
                            0, 
                            tempCanvas.width, 
                            tempCanvas.height
                        );
                    }
                    
                    // 将裁剪后的图片转换为DataURL
                    const croppedDataUrl = tempCanvas.toDataURL('image/jpeg');
                    
                    // 更新预览图片
                    if (newImagePreview) {
                        newImagePreview.src = croppedDataUrl;
                        newImagePreview.style.display = 'block';
                    } else if (existingPreview) {
                        existingPreview.src = croppedDataUrl;
                        existingPreview.style.display = 'block';
                    }
                    
                    // 保存裁剪后的数据
                    croppedImageDataInput.value = croppedDataUrl;
                    
                    // 隐藏裁剪容器
                    cropContainer.style.display = 'none';
                });
                
                // 取消裁剪
                cancelCropButton.addEventListener('click', function() {
                    cropContainer.style.display = 'none';
                    imageUpload.value = '';
                    if (newImagePreview && !newImagePreview.src.includes('data:image')) {
                        newImagePreview.style.display = 'none';
                    }
                });
                
                // 监听文件上传
                imageUpload.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // 创建文件预览
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            // 加载图片用于裁剪
                            currentImage = new Image();
                            currentImage.onload = function() {
                                // 重置裁剪参数
                                offsetX = 0;
                                offsetY = 0;
                                scale = 1;
                                
                                // 设置canvas尺寸
                                setupCanvas();
                                
                                // 绘制裁剪区域
                                drawCropArea();
                                
                                // 显示裁剪容器
                                cropContainer.style.display = 'block';
                            };
                            currentImage.src = event.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        }
        
        // 页面加载完成后设置图片上传功能
        document.addEventListener('DOMContentLoaded', setupImageUpload);
    </script>
</body>
</html>