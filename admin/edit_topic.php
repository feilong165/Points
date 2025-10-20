<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 检查管理员是否登录
session_start();
if (!is_admin_logged_in()) {
    header("Location: login.php");
    exit;
}

// 获取专题页ID
$topic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($topic_id <= 0) {
    header("Location: topics.php");
    exit;
}

// 获取专题页信息
$conn = get_db_connection();
$sql = "SELECT * FROM topics WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$result = $stmt->get_result();
$topic = $result->fetch_assoc();
$stmt->close();

if (!$topic) {
    $conn->close();
    header("Location: topics.php");
    exit;
}

// 获取专题页项目
$sql = "SELECT * FROM topic_items WHERE topic_id=? ORDER BY sort_order ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $topic_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}
$stmt->close();

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_topic'])) {
        // 更新专题页基本信息
        $title = $_POST['title'];
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title));
        $status = isset($_POST['status']) ? 1 : 0;
        
        // 处理封面图片上传
        $cover_image = $topic['cover_image'];
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
            $target_dir = "../uploads/topics/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES["cover_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = "cover_".time().".".$file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $target_file)) {
                // 删除旧图片
                if (!empty($cover_image) && file_exists("../".$cover_image)) {
                    unlink("../".$cover_image);
                }
                $cover_image = str_replace('../', '', $target_file);
            }
        }
        
        $sql = "UPDATE topics SET title=?, slug=?, cover_image=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $title, $slug, $cover_image, $status, $topic_id);
        
        if ($stmt->execute()) {
            $message = "专题页信息更新成功！";
            $message_type = "success";
            // 刷新专题页数据
            $topic['title'] = $title;
            $topic['slug'] = $slug;
            $topic['cover_image'] = $cover_image;
            $topic['status'] = $status;
        } else {
            $message = "更新失败: " . $conn->error;
            $message_type = "error";
        }
        
        $stmt->close();
    } elseif (isset($_POST['add_item'])) {
        // 添加专题页项目
        $type = $_POST['item_type'];
        $title = $_POST['item_title'];
        $content = $_POST['item_content'];
        $link_url = $_POST['item_link'];
        
        // 处理图片上传
        $image_url = '';
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
            $target_dir = "../uploads/topics/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES["item_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = "item_".time().".".$file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_file)) {
                $image_url = str_replace('../', '', $target_file);
            }
        }
        
        // 获取最大排序号
        $sql = "SELECT MAX(sort_order) as max_order FROM topic_items WHERE topic_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $topic_id);
        $stmt->execute();
        $max_result = $stmt->get_result();
        $max_row = $max_result->fetch_assoc();
        $sort_order = ($max_row['max_order'] ?? 0) + 1;
        $stmt->close();
        
        $sql = "INSERT INTO topic_items (topic_id, type, title, content, image_url, link_url, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssi", $topic_id, $type, $title, $content, $image_url, $link_url, $sort_order);
        
        if ($stmt->execute()) {
            $message = "内容项添加成功！";
            $message_type = "success";
            // 重定向以刷新项目列表
            header("Location: edit_topic.php?id=$topic_id");
            exit;
        } else {
            $message = "添加失败: " . $conn->error;
            $message_type = "error";
        }
        
        $stmt->close();
    } elseif (isset($_POST['update_item'])) {
        // 更新项目
        $item_id = $_POST['item_id'];
        $title = $_POST['item_title'];
        $content = $_POST['item_content'];
        $link_url = $_POST['item_link'];
        
        // 处理图片上传
        $sql = "SELECT image_url FROM topic_items WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $img_result = $stmt->get_result();
        $img_row = $img_result->fetch_assoc();
        $image_url = $img_row['image_url'];
        $stmt->close();
        
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) {
            $target_dir = "../uploads/topics/";
            $file_extension = pathinfo($_FILES["item_image"]["name"], PATHINFO_EXTENSION);
            $new_filename = "item_".time().".".$file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_file)) {
                // 删除旧图片
                if (!empty($image_url) && file_exists("../".$image_url)) {
                    unlink("../".$image_url);
                }
                $image_url = str_replace('../', '', $target_file);
            }
        }
        
        $sql = "UPDATE topic_items SET title=?, content=?, image_url=?, link_url=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $title, $content, $image_url, $link_url, $item_id);
        
        if ($stmt->execute()) {
            $message = "内容项更新成功！";
            $message_type = "success";
            // 重定向以刷新项目列表
            header("Location: edit_topic.php?id=$topic_id");
            exit;
        } else {
            $message = "更新失败: " . $conn->error;
            $message_type = "error";
        }
        
        $stmt->close();
    } elseif (isset($_POST['delete_item'])) {
        // 删除项目
        $item_id = $_POST['item_id'];
        
        // 获取并删除关联的图片
        $sql = "SELECT image_url FROM topic_items WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $img_result = $stmt->get_result();
        $img_row = $img_result->fetch_assoc();
        $stmt->close();
        
        if (!empty($img_row['image_url']) && file_exists("../".$img_row['image_url'])) {
            unlink("../".$img_row['image_url']);
        }
        
        $sql = "DELETE FROM topic_items WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $item_id);
        
        if ($stmt->execute()) {
            $message = "内容项删除成功！";
            $message_type = "success";
            // 重定向以刷新项目列表
            header("Location: edit_topic.php?id=$topic_id");
            exit;
        } else {
            $message = "删除失败: " . $conn->error;
            $message_type = "error";
        }
        
        $stmt->close();
    }
}

$conn->close();

?> 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑专题页 - 运营积木</title>
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
        .header .actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-danger {
            background-color: #e74c3c;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-success {
            background-color: #2ecc71;
        }
        .btn-success:hover {
            background-color: #27ae60;
        }
        .btn-secondary {
            background-color: #95a5a6;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        .card {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        .form-group.checkbox {
            display: flex;
            align-items: center;
        }
        .form-group.checkbox input {
            width: auto;
            margin-right: 10px;
        }
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .items-container {
            margin-top: 20px;
        }
        .item-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .item-title {
            font-weight: bold;
            font-size: 16px;
        }
        .item-actions {
            display: flex;
            gap: 10px;
        }
        .item-content {
            margin-top: 10px;
        }
        .item-image {
            max-width: 100%;
            max-height: 300px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        <main class="content">
            <div class="header">
                <h1>编辑专题页</h1>
                <div class="actions">
                    <a href="topics.php" class="btn btn-secondary">返回列表</a>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>基本信息</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">专题页标题</label>
                        <input type="text" id="title" name="title" value="<?php echo $topic['title']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="cover_image">封面图片</label>
                        <input type="file" id="cover_image" name="cover_image" accept="image/*">
                        <?php if (!empty($topic['cover_image'])): ?>
                            <img src="../<?php echo $topic['cover_image']; ?>" alt="封面图片" class="image-preview">
                        <?php endif; ?>
                    </div>
                    <div class="form-group checkbox">
                        <input type="checkbox" id="status" name="status" <?php echo $topic['status'] ? 'checked' : ''; ?>>
                        <label for="status">启用专题页</label>
                    </div>
                    <button type="submit" name="update_topic" class="btn btn-success">更新信息</button>
                </form>
            </div>
            
            <div class="card">
                <h2>添加内容项</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="item_type">内容类型</label>
                        <select id="item_type" name="item_type" required>
                            <option value="banner">Banner</option>
                            <option value="text">文本内容</option>
                            <option value="image">图片</option>
                            <option value="link">链接</option>
                            <option value="carousel">轮播图</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="item_title">标题</label>
                        <input type="text" id="item_title" name="item_title">
                    </div>
                    <div class="form-group">
                        <label for="item_content">内容</label>
                        <textarea id="item_content" name="item_content"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="item_image">图片</label>
                        <input type="file" id="item_image" name="item_image" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label for="item_link">链接地址</label>
                        <input type="text" id="item_link" name="item_link" placeholder="http://">
                    </div>
                    <button type="submit" name="add_item" class="btn btn-success">添加内容项</button>
                </form>
            </div>
            
            <div class="card">
                <h2>内容项列表</h2>
                
                <?php if (count($items) > 0): ?>
                    <div class="items-container">
                        <?php foreach ($items as $item): ?>
                            <div class="item-card">
                                <div class="item-header">
                                    <div class="item-title">
                                        <?php echo !empty($item['title']) ? $item['title'] : '未命名内容'; ?> (<?php echo $item['type']; ?>)
                                    </div>
                                    <div class="item-actions">
                                        <button type="button" class="btn btn-secondary edit-item" data-id="<?php echo $item['id']; ?>">编辑</button>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="delete_item" class="btn btn-danger" onclick="return confirm('确定要删除这个内容项吗？');">删除</button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="item-content">
                                    <?php if (!empty($item['content'])): ?>
                                        <p><strong>内容：</strong><?php echo $item['content']; ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="../<?php echo $item['image_url']; ?>" alt="内容图片" class="item-image">
                                    <?php endif; ?>
                                    <?php if (!empty($item['link_url'])): ?>
                                        <p><strong>链接：</strong><a href="<?php echo $item['link_url']; ?>" target="_blank"><?php echo $item['link_url']; ?></a></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- 编辑表单 (默认隐藏) -->
                            <div id="edit-form-<?php echo $item['id']; ?>" class="card" style="display: none; margin-top: -10px; margin-bottom: 20px;">
                                <h3>编辑内容项</h3>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <div class="form-group">
                                        <label for="edit-title-<?php echo $item['id']; ?>">标题</label>
                                        <input type="text" id="edit-title-<?php echo $item['id']; ?>" name="item_title" value="<?php echo $item['title']; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-content-<?php echo $item['id']; ?>">内容</label>
                                        <textarea id="edit-content-<?php echo $item['id']; ?>" name="item_content"><?php echo $item['content']; ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-image-<?php echo $item['id']; ?>">图片</label>
                                        <input type="file" id="edit-image-<?php echo $item['id']; ?>" name="item_image" accept="image/*">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="../<?php echo $item['image_url']; ?>" alt="当前图片" class="image-preview">
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-link-<?php echo $item['id']; ?>">链接地址</label>
                                        <input type="text" id="edit-link-<?php echo $item['id']; ?>" name="item_link" value="<?php echo $item['link_url']; ?>" placeholder="http://">
                                    </div>
                                    <button type="submit" name="update_item" class="btn btn-success">保存更改</button>
                                    <button type="button" class="btn btn-secondary cancel-edit" data-id="<?php echo $item['id']; ?>">取消</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">暂无内容项，请添加内容</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // 编辑按钮点击事件
        document.querySelectorAll('.edit-item').forEach(button => {
            button.addEventListener('click', function() {
                const itemId = this.getAttribute('data-id');
                const editForm = document.getElementById(`edit-form-${itemId}`);
                
                // 隐藏所有其他编辑表单
                document.querySelectorAll('[id^="edit-form-"]').forEach(form => {
                    if (form.id !== `edit-form-${itemId}`) {
                        form.style.display = 'none';
                    }
                });
                
                // 切换当前编辑表单显示状态
                if (editForm.style.display === 'none' || editForm.style.display === '') {
                    editForm.style.display = 'block';
                    // 滚动到编辑表单
                    editForm.scrollIntoView({ behavior: 'smooth' });
                } else {
                    editForm.style.display = 'none';
                }
            });
        });
        
        // 取消编辑按钮点击事件
        document.querySelectorAll('.cancel-edit').forEach(button => {
            button.addEventListener('click', function() {
                const itemId = this.getAttribute('data-id');
                const editForm = document.getElementById(`edit-form-${itemId}`);
                editForm.style.display = 'none';
            });
        });
    </script>
</body>
</html>