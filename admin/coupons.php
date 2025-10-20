<?php
// 检查管理员是否登录
require_once '../app/app.php';

if (!is_admin_logged_in()) {
    header('Location: login.php');
    exit;
}

// 处理表单提交
$success_message = '';
$error_message = '';
$current_coupon = null;

// 获取所有优惠券
$coupons = get_all_coupons();

// 处理添加/编辑优惠券
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_coupon']) || isset($_POST['update_coupon'])) {
                $name = $_POST['name'];
                $description = $_POST['description'];
                $points_discount = $_POST['points_discount'];
                $points_required = $_POST['points_required'];
                $min_points_required = $_POST['min_points_required'];
                $code = !empty($_POST['code']) ? $_POST['code'] : '';
                // 不需要最低消费，设置为0
                $min_order_amount = 0;
                // 不需要现金折扣金额，设置为0
                $discount_value = 0;
                
                // 处理日期时间字段 - 增强版，能处理不完整的日期输入
                $start_time = '';
                $end_time = '';
                $error_message = '';
                
                // 处理开始时间
                if (isset($_POST['start_time']) && !empty($_POST['start_time'])) {
                    $start_time_str = $_POST['start_time'];
                    // 处理HTML5 datetime-local格式（带T分隔符）
                    $start_time_str = str_replace('T', ' ', $start_time_str);
                    // 尝试转换为时间戳
                    $timestamp = strtotime($start_time_str);
                    // 如果转换失败，尝试增强处理
                    if ($timestamp === false) {
                        // 检查是否只输入了年份
                        if (preg_match('/^\d{4}$/', $start_time_str)) {
                            // 补全为该年第一天的开始时间
                            $start_time = $start_time_str . '-01-01 00:00:00';
                        } else {
                            $error_message = '开始时间格式不正确';
                        }
                    } else {
                        // 转换成功，格式化为MySQL DATETIME格式
                        $start_time = date('Y-m-d H:i:s', $timestamp);
                    }
                } else {
                    $error_message = '开始时间不能为空';
                }
                
                // 如果开始时间没有错误，再处理结束时间
                if (empty($error_message)) {
                    if (isset($_POST['end_time']) && !empty($_POST['end_time'])) {
                        $end_time_str = $_POST['end_time'];
                        // 处理HTML5 datetime-local格式（带T分隔符）
                        $end_time_str = str_replace('T', ' ', $end_time_str);
                        // 尝试转换为时间戳
                        $timestamp = strtotime($end_time_str);
                        // 如果转换失败，尝试增强处理
                        if ($timestamp === false) {
                            // 检查是否只输入了年份
                            if (preg_match('/^\d{4}$/', $end_time_str)) {
                                // 补全为该年最后一天的结束时间
                                $end_time = $end_time_str . '-12-31 23:59:59';
                            } else {
                                $error_message = '结束时间格式不正确';
                            }
                        } else {
                            // 转换成功，格式化为MySQL DATETIME格式
                            $end_time = date('Y-m-d H:i:s', $timestamp);
                        }
                    } else {
                        $error_message = '结束时间不能为空';
                    }
                }
                
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // 只有在没有时间格式错误的情况下才尝试添加或更新优惠券
                if (empty($error_message)) {
                    if (isset($_POST['add_coupon'])) {
                        // 添加优惠券
                        $result = add_coupon($name, $description, $discount_value, $points_discount, $min_order_amount, $min_points_required, $points_required, $code, $start_time, $end_time, $is_active);
                        if ($result['success']) {
                            $success_message = '优惠券添加成功';
                            // 刷新优惠券列表
                            $coupons = get_all_coupons();
                        } else {
                            $error_message = '优惠券添加失败';
                        }
                    } else {
                        // 更新优惠券
                        $id = $_POST['coupon_id'];
                        $result = update_coupon($id, $name, $description, $discount_value, $points_discount, $min_order_amount, $min_points_required, $points_required, $code, $start_time, $end_time, $is_active);
                        if ($result['success']) {
                            $success_message = '优惠券更新成功';
                            // 刷新优惠券列表
                            $coupons = get_all_coupons();
                        } else {
                            $error_message = '优惠券更新失败';
                        }
                    }
                }
            } elseif (isset($_POST['delete_coupon'])) {
                // 删除优惠券
                $id = $_POST['coupon_id'];
        $result = delete_coupon($id);
        if ($result['success']) {
            $success_message = '优惠券删除成功';
            // 刷新优惠券列表
            $coupons = get_all_coupons();
        } else {
            $error_message = '优惠券删除失败';
        }
    } elseif (isset($_POST['edit_coupon'])) {
        // 获取要编辑的优惠券
        $id = $_POST['coupon_id'];
        $current_coupon = get_coupon_detail($id);
    }
}

// 生成随机兑换码
function generate_random_code($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>优惠券管理 - 积分系统</title>
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
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 5px 5px 0 0;
            overflow: hidden;
        }
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            color: #666;
            flex: 1;
            text-align: center;
            transition: all 0.3s ease;
        }
        .tab.active {
            background-color: #4CAF50;
            color: white;
        }
        .tab-content {
            display: none;
            background-color: white;
            padding: 20px;
            border-radius: 0 0 5px 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .tab-content.active {
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            margin-right: 5px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-danger {
            background-color: #f44336;
        }
        .btn-danger:hover {
            background-color: #d32f2f;
        }
        .btn-secondary {
            background-color: #757575;
        }
        .btn-secondary:hover {
            background-color: #616161;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="number"], input[type="datetime-local"], textarea {
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
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        .generate-code-btn {
            display: inline-block;
            padding: 8px 15px;
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .generate-code-btn:hover {
            background-color: #0b7dda;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination a {
            color: #4CAF50;
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 2px;
            border-radius: 4px;
        }
        .pagination a.active {
            background-color: #4CAF50;
            color: white;
            border: 1px solid #4CAF50;
        }
        .pagination a:hover:not(.active) {
            background-color: #ddd;
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
                <h1>优惠券管理</h1>
                <a href="logout.php" class="logout">退出登录</a>
            </div>
        
        <?php if ($success_message): ?>
        <div class="message success">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="message error">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="openTab(event, 'couponList')">优惠券列表</button>
            <button class="tab" onclick="openTab(event, 'addCoupon')">添加/编辑优惠券</button>
        </div>
        
        <div id="couponList" class="tab-content active">
            <h2>优惠券列表</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>优惠券名称</th>
                        <th>可抵扣积分</th>
                        <th>所需积分</th>
                        <th>最低使用积分</th>
                        <th>兑换码</th>
                        <th>有效期</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($coupons)): ?>
                        <?php foreach ($coupons as $coupon): ?>
                            <tr>
                                <td><?php echo $coupon['id']; ?></td>
                                <td><?php echo $coupon['name']; ?></td>
                                <td><?php echo $coupon['points_discount']; ?></td>
                                <td><?php echo $coupon['points_required']; ?></td>
                                <td><?php echo $coupon['min_points_required']; ?></td>
                                <td><?php echo $coupon['code'] ? $coupon['code'] : '无'; ?></td>
                                <td><?php echo $coupon['start_time'] . ' 至 ' . $coupon['end_time']; ?></td>
                                <td><?php echo $coupon['is_active'] ? '启用' : '禁用'; ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                        <button type="submit" name="edit_coupon" class="btn">编辑</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这个优惠券吗？');">
                                        <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                        <button type="submit" name="delete_coupon" class="btn btn-danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">暂无优惠券</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div id="addCoupon" class="tab-content">
            <h2><?php echo $current_coupon ? '编辑优惠券' : '添加优惠券'; ?></h2>
            <form method="POST">
                <?php if ($current_coupon): ?>
                    <input type="hidden" name="coupon_id" value="<?php echo $current_coupon['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">优惠券名称</label>
                    <input type="text" id="name" name="name" required value="<?php echo $current_coupon ? $current_coupon['name'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">优惠券描述</label>
                    <textarea id="description" name="description"><?php echo $current_coupon ? $current_coupon['description'] : ''; ?></textarea>
                </div>
                

                
                <div class="form-group">
                    <label for="points_discount">可抵扣积分数量</label>
                    <input type="number" id="points_discount" name="points_discount" min="1" required value="<?php echo $current_coupon && isset($current_coupon['points_discount']) ? $current_coupon['points_discount'] : '100'; ?>">
                    <small>使用此优惠券可抵扣的积分数量</small>
                </div>
                
                <div class="form-group">
                    <label for="min_points_required">最低使用积分</label>
                    <input type="number" id="min_points_required" name="min_points_required" min="0" required value="<?php echo $current_coupon && isset($current_coupon['min_points_required']) ? $current_coupon['min_points_required'] : '0'; ?>">
                    <small>使用此优惠券所需的最低用户积分数量</small>
                </div>
                
                <div class="form-group">
                    <label for="points_required">所需积分</label>
                    <input type="number" id="points_required" name="points_required" min="0" required value="<?php echo $current_coupon ? $current_coupon['points_required'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="code">兑换码</label>
                    <div style="display: flex;">
                        <input type="text" id="code" name="code" value="<?php echo $current_coupon ? $current_coupon['code'] : ''; ?>">
                        <button type="button" class="generate-code-btn" onclick="generateCode()">生成兑换码</button>
                    </div>
                    <small>留空则不设置兑换码</small>
                </div>
                
                <div class="form-group">
                    <label for="start_time">开始时间</label>
                    <input type="datetime-local" id="start_time" name="start_time" required value="<?php echo $current_coupon ? date('Y-m-d\TH:i', strtotime($current_coupon['start_time'])) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_time">结束时间</label>
                    <input type="datetime-local" id="end_time" name="end_time" required value="<?php echo $current_coupon ? date('Y-m-d\TH:i', strtotime($current_coupon['end_time'])) : ''; ?>">
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" <?php echo $current_coupon ? ($current_coupon['is_active'] ? 'checked' : '') : 'checked'; ?>>
                    <label for="is_active">启用优惠券</label>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="<?php echo $current_coupon ? 'update_coupon' : 'add_coupon'; ?>" class="btn">
                        <?php echo $current_coupon ? '更新优惠券' : '添加优惠券'; ?>
                    </button>
                    <?php if ($current_coupon): ?>
                        <button type="button" class="btn btn-secondary" onclick="cancelEdit()">取消编辑</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        </main>
    </div>
    
    <script>
        // 切换标签页
        function openTab(evt, tabName) {
            var i, tabContent, tabLinks;
            
            tabContent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabContent.length; i++) {
                tabContent[i].classList.remove("active");
            }
            
            tabLinks = document.getElementsByClassName("tab");
            for (i = 0; i < tabLinks.length; i++) {
                tabLinks[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
        
        // 生成随机兑换码
        function generateCode() {
            var characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            var code = '';
            var length = 8;
            for (var i = 0; i < length; i++) {
                code += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            document.getElementById('code').value = code;
        }
        
        // 取消编辑
        function cancelEdit() {
            window.location.href = 'coupons.php';
        }
    </script>
</body>
</html>