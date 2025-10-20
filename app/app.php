<?php
// 引入配置文件
require_once 'config.php';

// 引入安全函数库
require_once 'security.php';

// 启用全局输入过滤
enable_global_input_filter();

// 数据库连接函数
function get_db_connection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // 检查连接是否成功
    if ($conn->connect_error) {
        die("数据库连接失败: " . $conn->connect_error);
    }
    
    return $conn;
}

// 创建用户表
function create_user_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        phone VARCHAR(20) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        points INT(11) DEFAULT 0,
        last_signin DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建用户表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 创建签到记录表
function create_signin_log_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS signin_logs (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        signin_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建签到记录表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 创建商品表
function create_products_table() {
    $conn = get_db_connection();
    
    // 检查表是否存在
    $check_table_sql = "SHOW TABLES LIKE 'products'";
    $result = $conn->query($check_table_sql);
    
    if ($result->num_rows == 0) {
        // 表不存在，创建新表
        $sql = "CREATE TABLE IF NOT EXISTS products (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            points_required INT(11) NOT NULL,
            stock INT(11) NOT NULL,
            is_virtual TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            is_top TINYINT(1) DEFAULT 0,
            sort_order INT(11) DEFAULT 0,
            restock_time DATETIME NULL,
            restock_quantity INT(11) DEFAULT 0,
            custom_tag VARCHAR(50) NULL,
            image_path VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($sql)) {
            die("创建商品表失败: " . $conn->error);
        }
    } else {
        // 表已存在，检查是否有image_path字段
        $check_column_sql = "SHOW COLUMNS FROM products LIKE 'image_path'";
        $column_result = $conn->query($check_column_sql);
        
        if ($column_result->num_rows == 0) {
            // 没有image_path字段，添加
            $add_column_sql = "ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER custom_tag";
            if (!$conn->query($add_column_sql)) {
                die("添加image_path字段失败: " . $conn->error);
            }
        }
    }
    
    $conn->close();
}

// 创建兑换码表
function create_codes_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS codes (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        product_id INT(11) NOT NULL,
        code VARCHAR(100) NOT NULL,
        is_used TINYINT(1) DEFAULT 0,
        used_by INT(11) NULL,
        used_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expiry_date TIMESTAMP NULL,
        status VARCHAR(20) DEFAULT 'available',
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建兑换码表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 创建订单表
function create_orders_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        product_id INT(11) NOT NULL,
        product_name VARCHAR(100) NOT NULL,
        points_used INT(11) NOT NULL,
        status ENUM('pending', 'completed', 'canceled') DEFAULT 'pending',
        code_id INT(11) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (code_id) REFERENCES codes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建订单表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 创建管理员表
function create_admins_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS admins (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建管理员表失败: " . $conn->error);
    }
    
    // 创建默认管理员账户
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $check_sql = "SELECT id FROM admins WHERE username = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $insert_sql = "INSERT INTO admins (username, password) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
    }
    
    $stmt->close();
    $conn->close();
}

// 创建分类表
function create_categories_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS categories (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT NULL,
        parent_id INT(11) NULL DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建分类表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 创建分类商品关联表
function create_product_categories_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS product_categories (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        product_id INT(11) NOT NULL,
        category_id INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        UNIQUE KEY unique_product_category (product_id, category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建分类商品关联表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 创建Banner表
function create_banners_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS banners (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT NULL,
        image_url VARCHAR(255) NOT NULL,
        link_url VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT(11) DEFAULT 0,
        start_time DATETIME NULL,
        end_time DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建Banner表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 用户注册函数
function register_user($username, $phone, $password) {
    $conn = get_db_connection();
    
    // 检查用户名是否已存在
    $check_username_sql = "SELECT id FROM users WHERE username = ?";
    $stmt = $conn->prepare($check_username_sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "用户名已存在"];
    }
    
    // 检查手机号是否已存在
    $check_phone_sql = "SELECT id FROM users WHERE phone = ?";
    $stmt = $conn->prepare($check_phone_sql);
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "手机号已被注册"];
    }
    
    // 加密密码
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // 插入用户数据
    $insert_sql = "INSERT INTO users (username, phone, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sss", $username, $phone, $hashed_password);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "注册成功"];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "注册失败，请稍后再试"];
    }
}

// 用户登录函数
function login_user($identifier, $password) {
    // 输入验证和过滤
    $identifier = sanitize_input($identifier);
    $password = sanitize_input($password);
    
    $conn = get_db_connection();
    
    // 准备SQL语句，通过用户名或手机号查询
    $sql = "SELECT id, username, password, points FROM users WHERE username = ? OR phone = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // 验证密码
        if (password_verify($password, $user['password'])) {
            // 设置会话
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['points'] = $user['points'];
            
            $stmt->close();
            $conn->close();
            return ["success" => true, "message" => "登录成功"];
        } else {
            $stmt->close();
            $conn->close();
            return ["success" => false, "message" => "密码错误"];
        }
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "用户不存在"];
    }
}

// 签到函数
function signin_user($user_id) {
    $conn = get_db_connection();
    
    // 检查今天是否已经签到
    $today = date('Y-m-d');
    $check_sql = "SELECT id FROM signin_logs WHERE user_id = ? AND signin_date = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "今天已经签到过了"];
    }
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 记录签到
        $insert_log_sql = "INSERT INTO signin_logs (user_id, signin_date) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_log_sql);
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        
        // 增加积分
        $update_points_sql = "UPDATE users SET points = points + 1, last_signin = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_points_sql);
        $stmt->bind_param("si", $today, $user_id);
        $stmt->execute();
        
        // 提交事务
        $conn->commit();
        
        // 更新会话中的积分
        if (isset($_SESSION)) {
            $_SESSION['points'] += 1;
        }
        
        $stmt->close();
        $conn->close();
        
        // 记录积分变动
        log_points_change($user_id, 1, "每日签到获得积分", 'sign_in');
        
        return ["success" => true, "message" => "签到成功，获得1积分"];
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "签到失败，请稍后再试"];
    }
}

// 获取用户信息
function get_user_info($user_id) {
    $conn = get_db_connection();
    
    $sql = "SELECT id, username, phone, points, last_signin, created_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $user = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $user;
}

// 创建消息表
function create_messages_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        title VARCHAR(100) NOT NULL,
        content TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建消息表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 创建客服对话表
function create_support_conversations_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS support_conversations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        admin_id INT(11) NULL,
        is_closed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建客服对话表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 创建客服消息表
function create_support_messages_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS support_messages (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT(11) NOT NULL,
        sender_type ENUM('user', 'admin') NOT NULL,
        sender_id INT(11) NOT NULL,
        content TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES support_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建客服消息表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 创建优惠券表
function create_coupons_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS coupons (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        discount_value DECIMAL(10,2) NOT NULL,
        min_order_amount DECIMAL(10,2) DEFAULT 0,
        points_required INT(11) DEFAULT 0,
        code VARCHAR(50) NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建优惠券表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 初始化优惠券表结构，添加积分满减相关字段
function init_coupon_table_structure() {
    $conn = get_db_connection();
    
    // 检查points_discount字段是否存在
    $result = $conn->query("SHOW COLUMNS FROM coupons LIKE 'points_discount'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE coupons ADD COLUMN points_discount INT(11) DEFAULT 0 AFTER discount_value");
    }
    
    // 检查min_points_required字段是否存在
    $result = $conn->query("SHOW COLUMNS FROM coupons LIKE 'min_points_required'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE coupons ADD COLUMN min_points_required INT(11) DEFAULT 0 AFTER min_order_amount");
    }
    
    $conn->close();
}

// 初始化订单表结构，添加折扣相关字段
function init_order_table_structure() {
    $conn = get_db_connection();
    
    // 检查discount_points字段是否存在
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'discount_points'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN discount_points INT(11) DEFAULT 0 AFTER points_used");
    }
    
    // 检查coupon_id字段是否存在
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'coupon_id'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN coupon_id INT(11) DEFAULT NULL AFTER discount_points");
    }
    
    $conn->close();
}

// 调用函数初始化表结构（首次加载时执行）
// init_coupon_table_structure();
// init_order_table_structure();

// 添加优惠券
function add_coupon($name, $description, $discount_value, $points_discount, $min_order_amount, $min_points_required, $points_required, $code, $start_time, $end_time, $is_active) {
    // 创建优惠券数据数组
    $couponData = array(
        'name' => $name,
        'description' => $description,
        'discount_value' => $discount_value,
        'points_discount' => $points_discount,
        'min_order_amount' => $min_order_amount,
        'min_points_required' => $min_points_required,
        'points_required' => $points_required,
        'code' => $code,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'is_active' => $is_active
    );
    
    // 使用新的验证函数进行严格的数据验证
    list($isValid, $message) = validateCouponData($couponData);
    
    if (!$isValid) {
        error_log('优惠券数据验证失败: ' . $message);
        return array(
            'success' => false,
            'error' => $message
        );
    }
    
    // 格式化日期时间确保数据库兼容
    $start_time = formatDateTimeForDB($start_time);
    $end_time = formatDateTimeForDB($end_time);
    
    try {
        $conn = get_db_connection();
        
        // 确保表结构已更新
        init_coupon_table_structure();
        
        // 检查参数值是否有效
        error_log('添加优惠券参数: ' . json_encode(array(
            'name' => $name,
            'description' => $description,
            'discount_value' => $discount_value,
            'points_discount' => $points_discount,
            'min_order_amount' => $min_order_amount,
            'min_points_required' => $min_points_required,
            'points_required' => $points_required,
            'code' => $code,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'is_active' => $is_active
        )));
        
        // 记录标准化后的日期时间值
        error_log('标准化后 - end_time: ' . $end_time);
        error_log('标准化后 - start_time: ' . $start_time);
        
        // 详细记录优惠券参数
        error_log('添加优惠券参数: ' . json_encode(array(
            'name' => $name,
            'description' => $description,
            'discount_value' => $discount_value,
            'points_discount' => $points_discount,
            'min_order_amount' => $min_order_amount,
            'min_points_required' => $min_points_required,
            'points_required' => $points_required,
            'code' => $code,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'is_active' => $is_active
        )));
        
        // 根据coupons表结构确定正确的参数绑定类型
        $stmt = $conn->prepare("INSERT INTO coupons (name, description, discount_value, points_discount, min_order_amount, min_points_required, points_required, code, start_time, end_time, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // 参数绑定类型：s=string, d=double, i=integer
        // 确保参数绑定顺序与SQL语句中的字段顺序完全匹配
        // 11个参数需要11个类型字符
        $stmt->bind_param("ssdiidsssii", $name, $description, $discount_value, $points_discount, $min_order_amount, $min_points_required, $points_required, $code, $start_time, $end_time, $is_active);
        
        $result = $stmt->execute();
        
        if (!$result) {
            $error = $stmt->error;
            error_log('优惠券添加失败: ' . $error);
        }
        
        $coupon_id = $stmt->insert_id;
        
        $stmt->close();
        $conn->close();
        
        return array(
            'success' => $result,
            'coupon_id' => $result ? $coupon_id : null,
            'error' => !$result ? $error : null
        );
    } catch (Exception $e) {
        error_log('添加优惠券异常: ' . $e->getMessage());
        return array(
            'success' => false,
            'error' => '数据库操作失败: ' . $e->getMessage()
        );
    }
}

// 更新优惠券
function update_coupon($id, $name, $description, $discount_value, $points_discount, $min_order_amount, $min_points_required, $points_required, $code, $start_time, $end_time, $is_active) {
    // 参数验证
    if (empty($id) || empty($name) || empty($start_time) || empty($end_time)) {
        return array(
            'success' => false,
            'error' => 'ID、名称、开始时间和结束时间不能为空'
        );
    }
    
    try {
        $conn = get_db_connection();
        
        // 确保表结构已更新
        init_coupon_table_structure();
        
        // 检查参数值是否有效
        error_log('更新优惠券参数: ' . json_encode(array(
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'discount_value' => $discount_value,
            'points_discount' => $points_discount,
            'min_order_amount' => $min_order_amount,
            'min_points_required' => $min_points_required,
            'points_required' => $points_required,
            'code' => $code,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'is_active' => $is_active
        )));
        
        // 根据coupons表结构确定正确的参数绑定类型
        $stmt = $conn->prepare("UPDATE coupons SET name = ?, description = ?, discount_value = ?, points_discount = ?, min_order_amount = ?, min_points_required = ?, points_required = ?, code = ?, start_time = ?, end_time = ?, is_active = ? WHERE id = ?");
        // 参数绑定类型：s=string, d=double, i=integer，最后多一个id参数
        // 12个参数需要12个类型字符
        $stmt->bind_param("ssdiidsssiii", $name, $description, $discount_value, $points_discount, $min_order_amount, $min_points_required, $points_required, $code, $start_time, $end_time, $is_active, $id);
        
        $result = $stmt->execute();
        
        if (!$result) {
            $error = $stmt->error;
            error_log('优惠券更新失败: ' . $error);
        }
        
        $stmt->close();
        $conn->close();
        
        return array(
            'success' => $result,
            'error' => !$result ? $error : null
        );
    } catch (Exception $e) {
        error_log('更新优惠券异常: ' . $e->getMessage());
        return array(
            'success' => false,
            'error' => '数据库操作失败: ' . $e->getMessage()
        );
    }
}



// 删除优惠券
function delete_coupon($id) {
    // 参数验证
    if (empty($id)) {
        return array(
            'success' => false,
            'error' => 'ID不能为空'
        );
    }
    
    try {
        $conn = get_db_connection();
        
        $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        return array(
            'success' => $result
        );
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => '数据库操作失败: ' . $e->getMessage()
        );
    }
}

// 获取优惠券详情
function get_coupon_detail($id) {
    $conn = get_db_connection();
    
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $coupon = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $coupon;
}

// 获取所有优惠券（包括失效的）
function get_all_coupons() {
    try {
        $conn = get_db_connection();
        
        // 使用created_at字段进行排序（数据库中确实存在此字段）
        $stmt = $conn->prepare("SELECT * FROM coupons ORDER BY created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        $coupons = $result->fetch_all(MYSQLI_ASSOC);
        
        $stmt->close();
        $conn->close();
        
        return $coupons;
    } catch (Exception $e) {
        // 记录错误信息，便于调试
        error_log('获取优惠券列表失败: ' . $e->getMessage());
        return array(); // 返回空数组，避免页面显示错误
    }
}

// 创建用户优惠券表
function create_user_coupons_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS user_coupons (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        coupon_id INT(11) NOT NULL,
        is_used TINYINT(1) DEFAULT 0,
        used_at TIMESTAMP NULL,
        received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建用户优惠券表失败: " . $conn->error);
    }
    
    $conn->close();
}

// 获取用户消息
function get_user_messages($user_id, $is_read = null) {
    $conn = get_db_connection();
    
    if ($is_read === null) {
        $stmt = $conn->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM messages WHERE user_id = ? AND is_read = ? ORDER BY created_at DESC");
        $stmt->bind_param("ii", $user_id, $is_read);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $messages;
}

// 标记消息为已读
function mark_message_as_read($message_id) {
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

// 获取所有优惠券
function get_coupons($is_active = 1) {
    $conn = get_db_connection();
    $now = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE is_active = ? AND start_time <= ? AND end_time >= ? ORDER BY created_at DESC");
    $stmt->bind_param("iss", $is_active, $now, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $coupons = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $coupons;
}

// 获取商品详情
function get_product_detail($id) {
    $conn = get_db_connection();
    
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $product;
}

// 通过兑换码获取优惠券
function get_coupon_by_code($code) {
    $conn = get_db_connection();
    $now = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND start_time <= ? AND end_time >= ?");
    $stmt->bind_param("iss", $code, $now, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $coupon = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $coupon;
}

// 获取用户优惠券
function get_user_coupons($user_id, $status = 'available') {
    $conn = get_db_connection();
    $now = date('Y-m-d H:i:s');
    
    if ($status === 'available') {
        // 未使用且在有效期内的优惠券
        $stmt = $conn->prepare("SELECT uc.*, c.name, c.description, c.discount_value, c.min_order_amount, c.end_time 
                               FROM user_coupons uc 
                               JOIN coupons c ON uc.coupon_id = c.id 
                               WHERE uc.user_id = ? AND uc.is_used = 0 AND c.end_time >= ?");
        $stmt->bind_param("is", $user_id, $now);
    } elseif ($status === 'used') {
        // 已使用的优惠券
        $stmt = $conn->prepare("SELECT uc.*, c.name, c.description, c.discount_value, c.min_order_amount, c.end_time 
                               FROM user_coupons uc 
                               JOIN coupons c ON uc.coupon_id = c.id 
                               WHERE uc.user_id = ? AND uc.is_used = 1");
        $stmt->bind_param("i", $user_id);
    } else {
        // 已过期的优惠券
        $stmt = $conn->prepare("SELECT uc.*, c.name, c.description, c.discount_value, c.min_order_amount, c.end_time 
                               FROM user_coupons uc 
                               JOIN coupons c ON uc.coupon_id = c.id 
                               WHERE uc.user_id = ? AND uc.is_used = 0 AND c.end_time < ?");
        $stmt->bind_param("is", $user_id, $now);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $coupons = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $coupons;
}

// 用户兑换优惠券
function exchange_coupon($user_id, $coupon_id) {
    $conn = get_db_connection();
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 获取优惠券信息
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $coupon_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $coupon = $result->fetch_assoc();
        
        if (!$coupon) {
            throw new Exception("优惠券不存在或已失效");
        }
        
        // 检查用户积分是否足够
        $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user['points'] < $coupon['points_required']) {
            throw new Exception("积分不足，无法兑换");
        }
        
        // 扣减用户积分
        $stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $stmt->bind_param("ii", $coupon['points_required'], $user_id);
        if (!$stmt->execute()) {
            throw new Exception("积分扣减失败");
        }
        
        // 添加用户优惠券
        $stmt = $conn->prepare("INSERT INTO user_coupons (user_id, coupon_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $coupon_id);
        if (!$stmt->execute()) {
            throw new Exception("优惠券兑换失败");
        }
        
        // 提交事务
        $conn->commit();
        
        $stmt->close();
        $conn->close();
        
        return array(
            'success' => true,
            'message' => '优惠券兑换成功'
        );
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        
        $stmt->close();
        $conn->close();
        
        return array(
            'success' => false,
            'message' => $e->getMessage()
        );
    }
}

// 通过兑换码兑换优惠券
function exchange_coupon_by_code($user_id, $code) {
    $coupon = get_coupon_by_code($code);
    
    if (!$coupon) {
        return array(
            'success' => false,
            'message' => '无效的兑换码'
        );
    }
    
    // 检查用户是否已经兑换过该优惠券
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT * FROM user_coupons WHERE user_id = ? AND coupon_id = ?");
    $stmt->bind_param("ii", $user_id, $coupon['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return array(
            'success' => false,
            'message' => '您已经兑换过该优惠券'
        );
    }
    
    $stmt->close();
    $conn->close();
    
    // 执行兑换
    return exchange_coupon($user_id, $coupon['id']);
}

// 管理员登录函数
function admin_login($username, $password) {
    // 输入验证和过滤
    $username = sanitize_input($username);
    $password = sanitize_input($password);
    
    $conn = get_db_connection();
    
    $sql = "SELECT id, username, password FROM admins WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();
        
        // 验证密码
        if (password_verify($password, $admin['password'])) {
            // 设置会话
            session_start();
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            
            $stmt->close();
            $conn->close();
            return ["success" => true, "message" => "管理员登录成功"];
        } else {
            $stmt->close();
            $conn->close();
            return ["success" => false, "message" => "密码错误"];
        }
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "管理员不存在"];
    }
}

// 验证用户是否已登录
function is_user_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

// 验证管理员是否已登录
function is_admin_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_id']);
}

// 用户登出
function logout_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
}

// 管理员登出
function logout_admin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
}

// 获取商品列表
function get_products($active_only = true, $category_id = null) {
    // 确保product_categories表存在
    create_product_categories_table();
    
    $conn = get_db_connection();
    
    if ($category_id) {
        // 如果指定了分类ID，先获取该分类下的所有商品ID
        $sql = "SELECT DISTINCT p.* FROM products p
                JOIN product_categories pc ON p.id = pc.product_id
                WHERE pc.category_id = ?";
        
        if ($active_only) {
            $sql .= " AND p.is_active = ?";
        }
        
        $sql .= " ORDER BY p.is_top DESC, p.sort_order ASC";
        
        $stmt = $conn->prepare($sql);
        if ($active_only) {
            $active_value = 1;
            $stmt->bind_param("ii", $category_id, $active_value);
        } else {
            $stmt->bind_param("i", $category_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // 未指定分类ID，获取所有商品
        if ($active_only) {
            $sql = "SELECT * FROM products WHERE is_active = ? ORDER BY is_top DESC, sort_order ASC";
            $stmt = $conn->prepare($sql);
            $active_value = 1;
            $stmt->bind_param("i", $active_value);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $sql = "SELECT * FROM products ORDER BY is_top DESC, sort_order ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
        }
    }
    
    $products = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
    
    return $products;
}

// 添加分类
function add_category($name, $description, $parent_id = 0, $sort_order = 0, $is_active = 1) {
    $conn = get_db_connection();
    
    // 检查分类名称是否已存在
    $check_stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $check_stmt->close();
        $conn->close();
        return array(
            'success' => false,
            'message' => '分类名称已存在'
        );
    }
    
    $check_stmt->close();
    
    try {
        $stmt = $conn->prepare("INSERT INTO categories (name, description, parent_id, is_active, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiii", $name, $description, $parent_id, $is_active, $sort_order);
        $result = $stmt->execute();
        $category_id = $stmt->insert_id;
        
        $stmt->close();
        $conn->close();
        
        return array(
            'success' => true,
            'message' => '分类添加成功',
            'category_id' => $category_id
        );
    } catch (Exception $e) {
        $stmt->close();
        $conn->close();
        return array(
            'success' => false,
            'message' => '添加分类失败: ' . $e->getMessage()
        );
    }
}

// 更新分类
function update_category($id, $name, $description, $parent_id = 0, $is_active = 1, $sort_order = 0) {
    $conn = get_db_connection();
    
    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, parent_id = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssiiii", $name, $description, $parent_id, $is_active, $sort_order, $id);
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return array(
        'success' => $result
    );
}

// 删除分类
function delete_category($id) {
    $conn = get_db_connection();
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 删除分类商品关联
        $delete_relation_sql = "DELETE FROM product_categories WHERE category_id = ?";
        $stmt = $conn->prepare($delete_relation_sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // 删除分类
        $delete_category_sql = "DELETE FROM categories WHERE id = ?";
        $stmt = $conn->prepare($delete_category_sql);
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();
        
        // 提交事务
        $conn->commit();
        $conn->close();
        
        return array(
            'success' => $result
        );
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $conn->close();
        
        return array(
            'success' => false,
            'message' => $e->getMessage()
        );
    }
}

// 获取分类详情
function get_category_detail($id) {
    $conn = get_db_connection();
    
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $category;
}

// 获取所有分类
function get_all_categories($is_active = null, $parent_id = null) {
    $conn = get_db_connection();
    
    $sql = "SELECT * FROM categories";
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($is_active !== null) {
        $where_conditions[] = "is_active = ?";
        $params[] = $is_active;
        $types .= 'i';
    }
    
    if ($parent_id !== null) {
        $where_conditions[] = "parent_id = ?";
        $params[] = $parent_id;
        $types .= 'i';
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY parent_id ASC, sort_order ASC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    $conn->close();
    
    return $categories;
}

// 切换分类上下架状态
function toggle_category_status($id) {
    $conn = get_db_connection();
    
    try {
        // 首先获取当前状态
        $get_stmt = $conn->prepare("SELECT is_active FROM categories WHERE id = ?");
        $get_stmt->bind_param("i", $id);
        $get_stmt->execute();
        $result = $get_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $get_stmt->close();
            $conn->close();
            return array(
                'success' => false,
                'message' => '分类不存在'
            );
        }
        
        $category = $result->fetch_assoc();
        $current_status = $category['is_active'];
        $new_status = $current_status == 1 ? 0 : 1;
        
        $get_stmt->close();
        
        // 更新状态
        $update_stmt = $conn->prepare("UPDATE categories SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("ii", $new_status, $id);
        $update_result = $update_stmt->execute();
        
        $update_stmt->close();
        $conn->close();
        
        return array(
            'success' => $update_result,
            'message' => $update_result ? ($new_status == 1 ? '分类已上架' : '分类已下架') : '状态更新失败'
        );
    } catch (Exception $e) {
        $conn->close();
        return array(
            'success' => false,
            'message' => '操作失败: ' . $e->getMessage()
        );
    }
}

// 添加商品到分类
function add_product_to_category($product_id, $category_ids) {
    $conn = get_db_connection();
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 先删除该商品的所有分类关联
        $delete_sql = "DELETE FROM product_categories WHERE product_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->close();
        
        // 添加新的分类关联
        if (!empty($category_ids)) {
            $insert_sql = "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_sql);
            
            foreach ($category_ids as $category_id) {
                $stmt->bind_param("ii", $product_id, $category_id);
                $stmt->execute();
            }
            
            $stmt->close();
        }
        
        // 提交事务
        $conn->commit();
        $conn->close();
        
        return array(
            'success' => true
        );
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $conn->close();
        
        return array(
            'success' => false,
            'message' => $e->getMessage()
        );
    }
}

// 获取商品的分类
function get_product_categories($product_id) {
    $conn = get_db_connection();
    
    $stmt = $conn->prepare("SELECT c.* FROM categories c
                           JOIN product_categories pc ON c.id = pc.category_id
                           WHERE pc.product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    $conn->close();
    
    return $categories;
}

// 添加商品
function add_product($name, $description, $points_required, $stock, $is_virtual, $is_active = 1, $is_top = 0, $sort_order = 0, $restock_time = null, $restock_quantity = 0, $custom_tag = null, $image_path = null, $category_ids = []) {
    $conn = get_db_connection();
    
    $sql = "INSERT INTO products (name, description, points_required, stock, is_virtual, is_active, is_top, sort_order, restock_time, restock_quantity, custom_tag, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiiiisssiss", $name, $description, $points_required, $stock, $is_virtual, $is_active, $is_top, $sort_order, $restock_time, $restock_quantity, $custom_tag, $image_path);
    
    if ($stmt->execute()) {
        $product_id = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        
        // 如果提供了分类ID，添加商品到分类
        if (!empty($category_ids)) {
            $category_result = add_product_to_category($product_id, $category_ids);
            if (!$category_result['success']) {
                return ["success" => false, "message" => "商品添加成功，但分类关联失败: " . $category_result['message']];
            }
        }
        
        return ["success" => true, "message" => "商品添加成功", "product_id" => $product_id];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "商品添加失败"];
    }
}

// 更新商品
function update_product($id, $name, $description, $points_required, $stock, $is_virtual, $is_active, $is_top, $sort_order, $restock_time = null, $restock_quantity = 0, $custom_tag = null, $image_path = null, $category_ids = null) {
    $conn = get_db_connection();
    
    $sql = "UPDATE products SET name = ?, description = ?, points_required = ?, stock = ?, is_virtual = ?, is_active = ?, is_top = ?, sort_order = ?, restock_time = ?, restock_quantity = ?, custom_tag = ?, image_path = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiiiisssissi", $name, $description, $points_required, $stock, $is_virtual, $is_active, $is_top, $sort_order, $restock_time, $restock_quantity, $custom_tag, $image_path, $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        
        // 如果提供了分类ID，更新商品的分类
        if ($category_ids !== null) {
            $category_result = add_product_to_category($id, $category_ids);
            if (!$category_result['success']) {
                return ["success" => false, "message" => "商品更新成功，但分类关联失败: " . $category_result['message']];
            }
        }
        
        return ["success" => true, "message" => "商品更新成功"];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "商品更新失败"];
    }
}

// 删除商品
function delete_product($id) {
    $conn = get_db_connection();
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 获取商品图片路径
        $image_sql = "SELECT image_path FROM products WHERE id = ?";
        $stmt = $conn->prepare($image_sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $image_path = $product['image_path'] ?? null;
        $stmt->close();
        
        // 删除相关的兑换码
        $code_sql = "DELETE FROM codes WHERE product_id = ?";
        $stmt = $conn->prepare($code_sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // 删除商品
        $product_sql = "DELETE FROM products WHERE id = ?";
        $stmt = $conn->prepare($product_sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // 提交事务
        $conn->commit();
        
        // 事务成功后删除图片文件（如果有）
        if (!empty($image_path) && file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "商品删除成功"];
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => $e->getMessage()];
    }
}

// 添加兑换码
function add_code($product_id, $code) {
    $conn = get_db_connection();
    
    $sql = "INSERT INTO codes (product_id, code) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $product_id, $code);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "兑换码添加成功"];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "兑换码添加失败"];
    }
}

// 应用优惠券到订单
function apply_coupon($user_id, $product_id, $points_required) {
    $conn = get_db_connection();
    
    // 确保表结构已更新
    init_coupon_table_structure();
    init_order_table_structure();
    
    // 获取用户可用的优惠券
    $now = date('Y-m-d H:i:s');
    $sql = "SELECT uc.*, c.* FROM user_coupons uc 
            JOIN coupons c ON uc.coupon_id = c.id 
            WHERE uc.user_id = ? AND uc.is_used = 0 
            AND c.is_active = 1 AND c.start_time <= ? AND c.end_time >= ?
            AND c.min_points_required <= ?
            ORDER BY c.points_discount DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $user_id, $now, $now, $points_required);
    $stmt->execute();
    $result = $stmt->get_result();
    $coupon = $result->fetch_assoc();
    
    $discount_points = 0;
    $used_coupon_id = null;
    
    if ($coupon && $coupon['points_discount'] > 0) {
        // 计算实际折扣积分（不超过商品所需积分）
        $discount_points = min($coupon['points_discount'], $points_required);
        $used_coupon_id = $coupon['id'];
        
        // 标记优惠券为已使用
        $update_sql = "UPDATE user_coupons SET is_used = 1, used_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $coupon['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    $stmt->close();
    $conn->close();
    
    return array(
        'discount_points' => $discount_points,
        'used_coupon_id' => $used_coupon_id
    );
}

// 兑换商品
function exchange_product($user_id, $product_id) {
    $conn = get_db_connection();
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 获取商品信息
        $product_sql = "SELECT * FROM products WHERE id = ? AND is_active = 1 AND stock > 0 FOR UPDATE";
        $stmt = $conn->prepare($product_sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product_result = $stmt->get_result();
        
        if ($product_result->num_rows == 0) {
            throw new Exception("商品不存在或已下架或库存不足");
        }
        
        $product = $product_result->fetch_assoc();
        
        // 检查用户积分是否足够
        $user_sql = "SELECT points FROM users WHERE id = ? FOR UPDATE";
        $stmt = $conn->prepare($user_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user = $user_result->fetch_assoc();
        
        // 应用优惠券
        $coupon_result = apply_coupon($user_id, $product_id, $product['points_required']);
        $discount_points = $coupon_result['discount_points'];
        $used_coupon_id = $coupon_result['used_coupon_id'];
        
        // 计算实际需要扣除的积分
        $actual_points_used = $product['points_required'] - $discount_points;
        
        if ($user['points'] < $actual_points_used) {
            throw new Exception("积分不足");
        }
        
        // 创建订单
        $order_sql = "INSERT INTO orders (user_id, product_id, product_name, points_used, discount_points, coupon_id, status) VALUES (?, ?, ?, ?, ?, ?, 'completed')";
        $stmt = $conn->prepare($order_sql);
        $stmt->bind_param("iisiii", $user_id, $product_id, $product['name'], $actual_points_used, $discount_points, $used_coupon_id);
        $stmt->execute();
        $order_id = $stmt->insert_id;
        
        // 如果是虚拟商品，分配兑换码
        $code_id = null;
        $code_value = null;
        if ($product['is_virtual']) {
            $code_sql = "SELECT id, code FROM codes WHERE product_id = ? AND is_used = 0 LIMIT 1 FOR UPDATE";
            $stmt = $conn->prepare($code_sql);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $code_result = $stmt->get_result();
            
            if ($code_result->num_rows == 0) {
                throw new Exception("兑换码库存不足");
            }
            
            $code = $code_result->fetch_assoc();
            $code_id = $code['id'];
            $code_value = $code['code'];
            
            // 更新兑换码状态
            $update_code_sql = "UPDATE codes SET is_used = 1, status = 'used', used_by = ?, used_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_code_sql);
            $stmt->bind_param("ii", $user_id, $code_id);
            $stmt->execute();
            
            // 更新订单，添加兑换码信息
            $update_order_sql = "UPDATE orders SET code_id = ? WHERE id = ?";
            $stmt = $conn->prepare($update_order_sql);
            $stmt->bind_param("ii", $code_id, $order_id);
            $stmt->execute();
        }
        
        // 减少商品库存
        $update_product_sql = "UPDATE products SET stock = stock - 1, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_product_sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        
        // 扣除用户积分（使用实际扣除的积分）
        $update_user_sql = "UPDATE users SET points = points - ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_user_sql);
        $stmt->bind_param("ii", $actual_points_used, $user_id);
        $stmt->execute();
        
        // 更新会话中的积分
        if (isset($_SESSION)) {
            $_SESSION['points'] -= $actual_points_used;
        }
        
        // 记录积分使用日志
        if (function_exists('log_points_change')) {
            if ($discount_points > 0) {
                log_points_change($user_id, -$actual_points_used, "兑换商品：{$product['name']}（使用优惠券折扣{$discount_points}积分）", $order_id);
            } else {
                log_points_change($user_id, -$actual_points_used, "兑换商品：{$product['name']}", $order_id);
            }
        }
        
        // 提交事务
        $conn->commit();
        
        $stmt->close();
        $conn->close();
        
        // 构建返回结果，包含折扣信息
        $result = [
            "success" => true,
            "message" => "兑换成功",
            "points_used" => $actual_points_used
        ];
        
        // 如果有积分折扣，添加折扣信息
        if ($discount_points > 0) {
            $result["discount_points"] = $discount_points;
            $result["coupon_id"] = $used_coupon_id;
            $result["original_points"] = $product['points_required'];
        }
        
        // 如果是虚拟商品，添加兑换码信息
        if ($product['is_virtual'] && $code_value) {
            $result["code"] = $code_value;
        }
        
        return $result;
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => $e->getMessage()];
    }
}

// 管理用户积分
function manage_user_points($user_id, $points_change, $reason) {
    $conn = get_db_connection();
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 更新用户积分
        $update_sql = "UPDATE users SET points = points + ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $points_change, $user_id);
        $stmt->execute();
        
        // 提交事务
        $conn->commit();
        
        $stmt->close();
        $conn->close();
        
        // 记录积分变动
        log_points_change($user_id, $points_change, $reason, 'admin_adjust');
        
        return ["success" => true, "message" => "积分调整成功"];
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "积分调整失败"];
    }
}

// 检查用户今天是否已签到
function check_user_signed_today($user_id) {
    $conn = get_db_connection();
    
    // 获取今天的日期
    $today = date('Y-m-d');
    
    // 检查用户今天是否已签到
    $check_sql = "SELECT id FROM signin_logs WHERE user_id = ? AND signin_date = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $has_signed = $result->num_rows > 0;
    
    $stmt->close();
    $conn->close();
    
    return $has_signed;
}

// 函数别名，与index.php中的调用保持一致
function has_user_checked_in_today($user_id) { return check_user_signed_today($user_id); }
function user_check_in($user_id) { return signin_user($user_id); }

// 记录积分变动
function log_points_change($user_id, $points_change, $description, $type = 'system_reward') {
    try {
        $conn = get_db_connection();
        
        // 获取用户当前积分
        $user_sql = "SELECT points FROM users WHERE id = ?";
        $stmt = $conn->prepare($user_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            return false;
        }
        
        // 计算变动后的积分余额
        $points_balance = $user['points'] + $points_change;
        
        // 插入积分变动记录
        $insert_sql = "INSERT INTO points_history (user_id, points_change, points_balance, type, description) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iiiss", $user_id, $points_change, $points_balance, $type, $description);
        $stmt->execute();
        
        $stmt->close();
        $conn->close();
        return true;
    } catch (Exception $e) {
        // 出错时记录日志但不中断主流程
        error_log('记录积分变动失败: ' . $e->getMessage());
        return false;
    }
}

// 获取用户签到统计信息
function get_user_check_in_stats($user_id) {
    $conn = get_db_connection();
    
    // 计算总签到天数
    $total_days_sql = "SELECT COUNT(*) AS total FROM signin_logs WHERE user_id = ?";
    $stmt = $conn->prepare($total_days_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_days_result = $stmt->get_result();
    $total_days = $total_days_result->fetch_assoc()['total'] ?: 0;
    $stmt->close();
    
    // 计算连续签到天数
    $consecutive_days = 0;
    $today = date('Y-m-d');
    $current_date = new DateTime($today);
    
    while (true) {
        $date_str = $current_date->format('Y-m-d');
        
        // 检查该日期是否签到
        $check_sql = "SELECT id FROM signin_logs WHERE user_id = ? AND signin_date = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("is", $user_id, $date_str);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows > 0) {
            $consecutive_days++;
            // 往前推一天
            $current_date->sub(new DateInterval('P1D'));
        } else {
            break;
        }
    }
    
    // 计算累计获得的积分
    $total_points_sql = "SELECT points FROM users WHERE id = ?";
    $stmt = $conn->prepare($total_points_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_points_result = $stmt->get_result();
    $user = $total_points_result->fetch_assoc();
    $total_points = $user['points'] ?: 0;
    $stmt->close();
    
    $conn->close();
    
    return [
        'total_days' => $total_days,
        'consecutive_days' => $consecutive_days,
        'total_points' => $total_points
    ];
}

// 生成兑换码
function generate_codes($product_id, $quantity, $expiry_date, $code_length) {
    $conn = get_db_connection();
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 确保code_length在有效范围内
    $code_length = max(1, min(18, $code_length));
        
        // 生成指定数量的兑换码
        $generated_count = 0;
        
        // 字符集用于生成随机兑换码
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $chars_length = strlen($chars);
        
        for ($i = 0; $i < $quantity; $i++) {
            // 生成随机兑换码
            $code = '';
            for ($j = 0; $j < $code_length; $j++) {
                $code .= $chars[rand(0, $chars_length - 1)];
            }
            
            // 检查兑换码是否已存在
            $check_sql = "SELECT id FROM codes WHERE code = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // 如果兑换码已存在，则跳过当前循环，重新生成
            if ($result->num_rows > 0) {
                $stmt->close();
                $i--; // 重试当前循环
                continue;
            }
            
            $stmt->close();
            
            // 插入兑换码
            $insert_sql = "INSERT INTO codes (product_id, code, is_used, used_by, used_at, created_at, expiry_date, status) VALUES (?, ?, 0, NULL, NULL, NOW(), ?, 'available')";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iss", $product_id, $code, $expiry_date);
            
            if (!$stmt->execute()) {
                throw new Exception("兑换码生成失败");
            }
            
            $stmt->close();
            $generated_count++;
        }
        
        // 提交事务
        $conn->commit();
        $conn->close();
        
        return ["success" => true, "message" => "成功生成{$generated_count}个兑换码"];
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $conn->close();
        
        return ["success" => false, "message" => $e->getMessage()];
    }
}

// 添加自定义兑换码
function add_custom_codes($product_id, $custom_codes, $expiry_date) {
    $conn = get_db_connection();
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        $added_count = 0;
        $existing_codes = [];
        
        foreach ($custom_codes as $code) {
            $code = trim($code);
            
            // 验证兑换码长度
            if (strlen($code) < 1 || strlen($code) > 18) {
                throw new Exception("兑换码 '$code' 长度应在1-18个字符之间");
            }
            
            // 检查兑换码是否已存在
            $check_sql = "SELECT id FROM codes WHERE code = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // 如果兑换码已存在，记录下来但继续处理其他码
            if ($result->num_rows > 0) {
                $existing_codes[] = $code;
                $stmt->close();
                continue;
            }
            
            $stmt->close();
            
            // 插入兑换码
            $insert_sql = "INSERT INTO codes (product_id, code, is_used, used_by, used_at, created_at, expiry_date, status) VALUES (?, ?, 0, NULL, NULL, NOW(), ?, 'available')";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("iss", $product_id, $code, $expiry_date);
            
            if (!$stmt->execute()) {
                throw new Exception("兑换码 '$code' 添加失败");
            }
            
            $stmt->close();
            $added_count++;
        }
        
        // 提交事务
        $conn->commit();
        $conn->close();
        
        $message = "成功添加{$added_count}个兑换码";
        if (!empty($existing_codes)) {
            $existing_count = count($existing_codes);
            $message .= "，{$existing_count}个兑换码已存在（" . implode(", ", array_slice($existing_codes, 0, 5)) . (count($existing_codes) > 5 ? "..." : "") . "）";
        }
        
        return ["success" => true, "message" => $message];
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $conn->close();
        
        return ["success" => false, "message" => $e->getMessage()];
    }
}

// 删除兑换码
function delete_code($code_id) {
    $conn = get_db_connection();
    
    try {
        // 检查兑换码是否已使用
        $check_sql = "SELECT is_used FROM codes WHERE id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $code_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $stmt->close();
            $conn->close();
            return ["success" => false, "message" => "兑换码不存在"];
        }
        
        $code = $result->fetch_assoc();
        
        if ($code['is_used']) {
            $stmt->close();
            $conn->close();
            return ["success" => false, "message" => "已使用的兑换码无法删除"];
        }
        
        $stmt->close();
        
        // 删除兑换码
        $delete_sql = "DELETE FROM codes WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $code_id);
        
        if (!$stmt->execute()) {
            $stmt->close();
            $conn->close();
            return ["success" => false, "message" => "兑换码删除失败"];
        }
        
        $stmt->close();
        $conn->close();
        
        return ["success" => true, "message" => "兑换码删除成功"];
    } catch (Exception $e) {
        $conn->close();
        return ["success" => false, "message" => $e->getMessage()];
    }
}

// 发送消息给用户
function send_message_to_user($user_id, $title, $content) {
    $conn = get_db_connection();
    
    $sql = "INSERT INTO messages (user_id, title, content) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $title, $content);
    
    if ($stmt->execute()) {
        $message_id = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "消息发送成功", "message_id" => $message_id];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "消息发送失败"];
    }
}

// 批量发送消息给多个用户
function send_message_to_multiple_users($user_ids, $title, $content) {
    $conn = get_db_connection();
    $conn->begin_transaction();
    
    try {
        $sent_count = 0;
        $sql = "INSERT INTO messages (user_id, title, content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        foreach ($user_ids as $user_id) {
            $stmt->bind_param("iss", $user_id, $title, $content);
            if ($stmt->execute()) {
                $sent_count++;
            }
        }
        
        $stmt->close();
        $conn->commit();
        $conn->close();
        
        return ["success" => true, "message" => "成功发送 $sent_count 条消息"];
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        return ["success" => false, "message" => $e->getMessage()];
    }
}

// 创建新的客服对话
function create_support_conversation($user_id, $content) {
    $conn = get_db_connection();
    $conn->begin_transaction();
    
    try {
        // 创建对话
        $sql = "INSERT INTO support_conversations (user_id) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $conversation_id = $stmt->insert_id;
        $stmt->close();
        
        // 发送第一条消息
        $sql = "INSERT INTO support_messages (conversation_id, sender_type, sender_id, content) VALUES (?, 'user', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $conversation_id, $user_id, $content);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        $conn->close();
        
        return ["success" => true, "message" => "对话创建成功", "conversation_id" => $conversation_id];
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        return ["success" => false, "message" => $e->getMessage()];
    }
}

// 发送客服消息
function send_support_message($conversation_id, $sender_type, $sender_id, $content) {
    $conn = get_db_connection();
    
    // 1. 确保sender_type是字符串并去除所有可能导致问题的字符
    $sender_type = trim(strval($sender_type));
    // 确保没有不可见字符
    $sender_type = preg_replace('/[^a-zA-Z0-9]/', '', $sender_type);
    
    // 2. 严格验证sender_type参数
    $valid_types = ['user', 'admin'];
    if (!in_array($sender_type, $valid_types)) {
        $conn->close();
        return ["success" => false, "message" => "无效的发送者类型: '" . $sender_type . "' - 长度: " . strlen($sender_type) . ""];
    }
    
    // 3. 确保值长度不超过定义的最大长度
    if (strlen($sender_type) > 10) {
        $conn->close();
        return ["success" => false, "message" => "发送者类型长度超过限制"];
    }
    
    // 检查对话是否存在且未关闭
    $check_sql = "SELECT is_closed FROM support_conversations WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $conversation_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows == 0) {
        $check_stmt->close();
        $conn->close();
        return ["success" => false, "message" => "对话不存在"];
    }
    
    $conversation = $check_result->fetch_assoc();
    if ($conversation['is_closed']) {
        $check_stmt->close();
        $conn->close();
        return ["success" => false, "message" => "对话已关闭"];
    }
    
    $check_stmt->close();
    
    // 发送消息
    $sql = "INSERT INTO support_messages (conversation_id, sender_type, sender_id, content) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $conn->close();
        return ["success" => false, "message" => "准备SQL语句失败: " . $conn->error];
    }
    
    // 使用严格类型绑定确保MySQL正确接收值
    $bind_result = $stmt->bind_param("iiss", $conversation_id, $sender_type, $sender_id, $content);
    
    if (!$bind_result) {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "绑定参数失败"];
    }
    
    // 执行SQL语句，捕获可能的错误
    try {
        $execute_result = $stmt->execute();
        if ($execute_result) {
        // 更新对话的更新时间
        $update_sql = "UPDATE support_conversations SET updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $conversation_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "消息发送成功"];
        } else {
            throw new Exception("执行SQL失败: " . $stmt->error);
        }
    } catch (Exception $e) {
        $stmt->close();
        $conn->close();
        // 记录详细错误信息
        $error_msg = "消息发送失败: " . $e->getMessage();
        $error_msg .= "\n- conversation_id: " . $conversation_id;
        $error_msg .= "\n- sender_type: '" . $sender_type . "' (长度: " . strlen($sender_type) . ")";
        $error_msg .= "\n- sender_id: " . $sender_id;
        
        return ["success" => false, "message" => $error_msg];
    }
}

// 获取客服对话列表
function get_support_conversations($user_id = null, $is_closed = null) {
    $conn = get_db_connection();
    
    $sql = "SELECT sc.*, u.username, u.phone, 
            (SELECT content FROM support_messages WHERE conversation_id = sc.id ORDER BY created_at DESC LIMIT 1) AS last_message,
            (SELECT COUNT(*) FROM support_messages WHERE conversation_id = sc.id AND is_read = 0 AND sender_type != 'user') AS unread_count
            FROM support_conversations sc
            JOIN users u ON sc.user_id = u.id";
    
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($user_id !== null) {
        $where_conditions[] = "sc.user_id = ?";
        $params[] = $user_id;
        $types .= 'i';
    }
    
    if ($is_closed !== null) {
        $where_conditions[] = "sc.is_closed = ?";
        $params[] = $is_closed;
        $types .= 'i';
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY sc.updated_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $conversations = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    $conn->close();
    
    return $conversations;
}

// 获取对话详情
function get_conversation_messages($conversation_id) {
    $conn = get_db_connection();
    
    $sql = "SELECT sm.*, 
            CASE 
                WHEN sm.sender_type = 'user' THEN u.username 
                ELSE '管理员' 
            END AS sender_name
            FROM support_messages sm
            LEFT JOIN users u ON sm.sender_type = 'user' AND sm.sender_id = u.id
            WHERE sm.conversation_id = ?
            ORDER BY sm.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    $conn->close();
    
    return $messages;
}

// 标记对话为已关闭
function close_support_conversation($conversation_id) {
    $conn = get_db_connection();
    
    $sql = "UPDATE support_conversations SET is_closed = 1, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $conversation_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "对话已关闭"];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "关闭对话失败"];
    }
}

// 标记消息为已读
function mark_support_message_as_read($message_id) {
    $conn = get_db_connection();
    
    $sql = "UPDATE support_messages SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $message_id);
    
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $result;
}

// 标记对话中所有消息为已读
function mark_conversation_as_read($conversation_id, $sender_type = null) {
    $conn = get_db_connection();
    
    $sql = "UPDATE support_messages SET is_read = 1 WHERE conversation_id = ?";
    $params = [$conversation_id];
    $types = 'i';
    
    if ($sender_type !== null) {
        $sql .= " AND sender_type = ?";
        $params[] = $sender_type;
        $types .= 's';
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    $result = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $result;
}

// 获取所有Banner
function get_banners($is_active = null) {
    $conn = get_db_connection();
    
    $sql = "SELECT * FROM banners";
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($is_active !== null) {
        $where_conditions[] = "is_active = ?";
        $params[] = $is_active;
        $types .= 'i';
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY sort_order ASC, created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $banners = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    $conn->close();
    
    return $banners;
}

// 获取单个Banner
function get_banner_by_id($banner_id) {
    $conn = get_db_connection();
    
    $sql = "SELECT * FROM banners WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $banner_id);
    
    $stmt->execute();
    $result = $stmt->get_result();
    $banner = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $banner;
}

// 添加Banner
function add_banner($title, $description, $image_url, $link_url, $is_active, $sort_order, $start_time, $end_time) {
    $conn = get_db_connection();
    
    $sql = "INSERT INTO banners (title, description, image_url, link_url, is_active, sort_order, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssiiss", $title, $description, $image_url, $link_url, $is_active, $sort_order, $start_time, $end_time);
    
    if ($stmt->execute()) {
        $banner_id = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "Banner添加成功", "banner_id" => $banner_id];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "Banner添加失败"];
    }
}

// 更新Banner
function update_banner($banner_id, $title, $description, $image_url, $link_url, $is_active, $sort_order, $start_time, $end_time) {
    $conn = get_db_connection();
    
    $sql = "UPDATE banners SET title = ?, description = ?, image_url = ?, link_url = ?, is_active = ?, sort_order = ?, start_time = ?, end_time = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssiissi", $title, $description, $image_url, $link_url, $is_active, $sort_order, $start_time, $end_time, $banner_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "Banner更新成功"];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "Banner更新失败"];
    }
}

// 删除Banner
function delete_banner($banner_id) {
    $conn = get_db_connection();
    
    $sql = "DELETE FROM banners WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $banner_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "Banner删除成功"];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "Banner删除失败"];
    }
}

// 更新Banner状态
function update_banner_status($banner_id, $is_active) {
    $conn = get_db_connection();
    
    $sql = "UPDATE banners SET is_active = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $is_active, $banner_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return ["success" => true, "message" => "Banner状态更新成功"];
    } else {
        $stmt->close();
        $conn->close();
        return ["success" => false, "message" => "Banner状态更新失败"];
    }
}

// 导出兑换码
function export_codes($product_id, $status) {
    $conn = get_db_connection();
    
    // 构建查询条件
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if ($product_id > 0) {
        $where_conditions[] = "product_id = ?";
        $params[] = $product_id;
        $types .= 'i';
    }
    
    if (!empty($status)) {
        $status_value = $status == 'available' ? 0 : 1;
        $where_conditions[] = "is_used = ?";
        $params[] = $status_value;
        $types .= 'i';
    }
    
    // 构建SQL查询
    $sql = "SELECT codes.*, products.name AS product_name 
            FROM codes 
            LEFT JOIN products ON codes.product_id = products.id";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY codes.created_at DESC";
    
    // 执行查询
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 设置HTTP头以强制下载CSV文件
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="兑换码_"' . date('YmdHis') . '.csv');
    
    // 打开输出流
    $output = fopen('php://output', 'w');
    
    // 添加BOM以支持UTF-8编码
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // 写入CSV标题行
    fputcsv($output, ['ID', '兑换码', '关联商品', '状态', '使用用户', '有效期至', '创建时间']);
    
    // 写入数据行
    while ($code = $result->fetch_assoc()) {
        // 格式化状态
        $status_text = $code['is_used'] ? '已使用' : '可用';
        
        // 获取使用用户信息
        $used_by_text = '';
        if ($code['used_by']) {
            $user_sql = "SELECT username FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("i", $code['used_by']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user = $user_result->fetch_assoc();
            $used_by_text = $user ? $user['username'] : '未知用户';
            $user_stmt->close();
        } else {
            $used_by_text = '未使用';
        }
        
        // 格式化有效期
        $expiry_text = $code['expiry_date'] ? $code['expiry_date'] : '永久有效';
        
        // 写入CSV行
        fputcsv($output, [
            $code['id'],
            $code['code'],
            $code['product_name'],
            $status_text,
            $used_by_text,
            $expiry_text,
            $code['created_at']
        ]);
    }
    
    // 关闭资源
    fclose($output);
    $stmt->close();
    $conn->close();
    
    // 结束脚本执行
exit;
}

/**
 * 优惠券数据验证函数
 * @param array $couponData 优惠券数据数组
 * @return array 返回验证结果 [success, message]
 */
function validateCouponData($couponData) {
    // 1. 验证必填字段
    $requiredFields = ['name', 'discount_value', 'start_time', 'end_time'];
    foreach ($requiredFields as $field) {
        if (!isset($couponData[$field]) || (is_string($couponData[$field]) && empty(trim($couponData[$field])))) {
            return [false, "字段 '$field' 不能为空"];
        }
    }
    
    // 2. 特别验证code字段 - 允许空字符串但如果有值必须符合格式要求
    if (isset($couponData['code']) && !empty(trim($couponData['code']))) {
        if (!preg_match('/^[A-Za-z0-9_\-]{4,20}$/', $couponData['code'])) {
            return [false, "优惠码只能是4-20位的字母、数字、下划线或短横线"];
        }
    } else {
        // 如果code为空字符串，设置为默认值
        $couponData['code'] = '';
    }
    
    // 3. 验证优惠券名称
    if (strlen($couponData['name']) > 100) {
        return [false, "优惠券名称不能超过100个字符"];
    }
    
    // 4. 验证折扣值 - 允许为0，因为积分系统中可能只使用积分折扣
    if (!is_numeric($couponData['discount_value']) || $couponData['discount_value'] < 0) {
        return [false, "折扣值必须是非负数字"];
    }
    
    // 5. 严格验证日期时间格式
    $dateTimeFormat = 'Y-m-d H:i:s';
    
    // 验证开始时间
    $startTime = validateDateTime($couponData['start_time'], $dateTimeFormat);
    if (!$startTime) {
        return [false, "开始时间格式不正确，请使用 'YYYY-MM-DD HH:MM:SS' 格式"];
    }
    
    // 验证结束时间
    $endTime = validateDateTime($couponData['end_time'], $dateTimeFormat);
    if (!$endTime) {
        return [false, "结束时间格式不正确，请使用 'YYYY-MM-DD HH:MM:SS' 格式"];
    }
    
    // 6. 验证时间逻辑
    if ($endTime <= $startTime) {
        return [false, "结束时间必须晚于开始时间"];
    }
    
    // 7. 验证时间不能早于当前时间（根据业务需求可选）
    $currentTime = new DateTime();
    if ($endTime <= $currentTime) {
        return [false, "结束时间必须晚于当前时间"];
    }
    
    // 8. 验证使用次数（如果存在）
    if (isset($couponData['usage_limit']) && !empty($couponData['usage_limit'])) {
        if (!is_numeric($couponData['usage_limit']) || $couponData['usage_limit'] < 0) {
            return [false, "使用次数限制必须是非负整数"];
        }
    }
    
    // 9. 验证最小订单金额（如果存在）
    if (isset($couponData['min_order_amount']) && !empty($couponData['min_order_amount'])) {
        if (!is_numeric($couponData['min_order_amount']) || $couponData['min_order_amount'] < 0) {
            return [false, "最小订单金额必须是非负数"];
        }
    }
    
    return [true, "验证通过"];
}

/**
 * 严格验证日期时间格式
 * @param string $dateString 日期时间字符串
 * @param string $format 期望的格式
 * @return DateTime|false 返回DateTime对象或false
 */
function validateDateTime($dateString, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $dateString);
    return $d && $d->format($format) === $dateString ? $d : false;
}

/**
 * 格式化日期时间为数据库兼容格式
 * @param mixed $dateTime 日期时间字符串或DateTime对象
 * @return string 格式化后的日期时间字符串
 */
function formatDateTimeForDB($dateTime) {
    if (!$dateTime instanceof DateTime) {
        $dateTime = new DateTime($dateTime);
    }
    return $dateTime->format('Y-m-d H:i:s');
}

/**
 * 前端输入后即时验证
 * @param string $input 用户输入的日期时间字符串
 * @return array 验证结果
 */
function validateDateTimeInput($input) {
    $patterns = [
        '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/' => 'YYYY-MM-DD HH:MM:SS',
        '/^\d{4}-\d{2}-\d{2}$/' => 'YYYY-MM-DD',
        '/^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}$/' => 'YYYY/MM/DD HH:MM:SS'
    ];
    
    foreach ($patterns as $pattern => $format) {
        if (preg_match($pattern, $input)) {
            return [true, $format];
        }
    }
    
    return [false, "请使用正确的日期时间格式，如：2023-12-01 00:00:00"];
}

/**
 * 确保搜索相关表存在
 */
function ensure_search_tables_exists() {
    // 确保搜索历史表存在
    $conn = get_db_connection();
    
    // 创建搜索历史表
    $sql = "CREATE TABLE IF NOT EXISTS search_history (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        query VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建搜索历史表失败: " . $conn->error);
    }
    
    // 创建推荐搜索表
    $sql = "CREATE TABLE IF NOT EXISTS recommended_searches (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(255) NOT NULL,
        sort_order INT(11) DEFAULT 0,
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建推荐搜索表失败: " . $conn->error);
    }
    
    // 创建搜索玩法配置表
    $sql = "CREATE TABLE IF NOT EXISTS search_play_configs (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(255) NOT NULL,
        target_url VARCHAR(255) NOT NULL,
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (keyword)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("创建搜索玩法配置表失败: " . $conn->error);
    }
    
    $conn->close();
}

/**
 * 记录搜索历史
 * @param int $user_id 用户ID
 * @param string $query 搜索关键词
 */
function record_search_history($user_id, $query) {
    $conn = get_db_connection();
    
    // 先检查是否已存在相同的搜索历史，避免重复
    $stmt = $conn->prepare("SELECT id FROM search_history WHERE user_id = ? AND query = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->bind_param("is", $user_id, $query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // 不存在重复的搜索历史，插入新记录
        $stmt = $conn->prepare("INSERT INTO search_history (user_id, query) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $query);
        $stmt->execute();
        
        // 限制每个用户的搜索历史记录数量
        $stmt = $conn->prepare("DELETE FROM search_history WHERE id NOT IN (SELECT id FROM (SELECT id FROM search_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 20) as temp)");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    $stmt->close();
    $conn->close();
}

/**
 * 获取搜索历史
 * @param int $user_id 用户ID
 * @return array 搜索历史记录
 */
function get_search_history($user_id) {
    $conn = get_db_connection();
    $history = [];
    
    $stmt = $conn->prepare("SELECT query, MAX(created_at) as created_at FROM search_history WHERE user_id = ? GROUP BY query ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $history;
}

/**
 * 清空搜索历史
 * @param int $user_id 用户ID
 */
function clear_search_history($user_id) {
    $conn = get_db_connection();
    
    $stmt = $conn->prepare("DELETE FROM search_history WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $stmt->close();
    $conn->close();
}

/**
 * 获取推荐搜索内容
 * @return array 推荐搜索列表
 */
function get_recommended_searches() {
    $conn = get_db_connection();
    $recommendations = [];
    
    $stmt = $conn->prepare("SELECT keyword FROM recommended_searches WHERE status = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recommendations[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $recommendations;
}

/**
 * 获取搜索玩法配置
 * @param string $keyword 搜索关键词
 * @return array|null 玩法配置
 */
function get_search_play_config($keyword) {
    $conn = get_db_connection();
    $config = null;
    
    // 精确匹配
    $stmt = $conn->prepare("SELECT target_url FROM search_play_configs WHERE keyword = ? AND status = 1");
    $stmt->bind_param("s", $keyword);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $config = $result->fetch_assoc();
    }
    
    $stmt->close();
    $conn->close();
    
    return $config;
}

/**
 * 搜索商品和优惠券
 * @param string $query 搜索关键词
 * @return array 搜索结果
 */
function search_products_and_coupons($query) {
    $conn = get_db_connection();
    $results = [];
    
    // 搜索商品
    $stmt = $conn->prepare("SELECT id, name as title, description, points, image_url, 'product' as type FROM products WHERE (name LIKE ? OR description LIKE ?) AND status = 1 ORDER BY created_at DESC LIMIT 50");
    $search_param = "%" . $query . "%";
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $product_result = $stmt->get_result();
    
    while ($row = $product_result->fetch_assoc()) {
        $results[] = $row;
    }
    
    // 搜索优惠券（如果有优惠券表）
    $check_table = $conn->query("SHOW TABLES LIKE 'coupons'");
    if ($check_table->num_rows > 0) {
        $stmt = $conn->prepare("SELECT id, name as title, description, 'coupon' as type FROM coupons WHERE (name LIKE ? OR description LIKE ?) AND status = 1 AND end_date > NOW() ORDER BY created_at DESC LIMIT 50");
        $stmt->bind_param("ss", $search_param, $search_param);
        $stmt->execute();
        $coupon_result = $stmt->get_result();
        
        while ($row = $coupon_result->fetch_assoc()) {
            $results[] = $row;
        }
    }
    
    $stmt->close();
    $conn->close();
    
    return $results;
  }
  
  /**
   * 获取所有推荐搜索
   * @return array 推荐搜索列表
   */
  function get_all_recommended_searches() {
      $conn = get_db_connection();
      $searches = [];
      
      $sql = "SELECT id, keyword, sort_order, status, created_at, updated_at FROM recommended_searches ORDER BY sort_order ASC";
      $result = $conn->query($sql);
      
      while ($row = $result->fetch_assoc()) {
          $searches[] = $row;
      }
      
      $conn->close();
      
      return $searches;
  }
  
  /**
   * 根据ID获取推荐搜索
   * @param int $id 推荐搜索ID
   * @return array|null 推荐搜索详情
   */
  function get_recommended_search_by_id($id) {
      $conn = get_db_connection();
      $search = null;
      
      $stmt = $conn->prepare("SELECT id, keyword, sort_order, status, created_at, updated_at FROM recommended_searches WHERE id = ?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
          $search = $result->fetch_assoc();
      }
      
      $stmt->close();
      $conn->close();
      
      return $search;
  }
  
  /**
   * 添加推荐搜索
   * @param string $keyword 关键词
   * @param int $sort_order 排序
   * @param int $status 状态
   * @return int 插入的ID
   */
  function add_recommended_search($keyword, $sort_order = 0, $status = 1) {
      $conn = get_db_connection();
      
      $stmt = $conn->prepare("INSERT INTO recommended_searches (keyword, sort_order, status) VALUES (?, ?, ?)");
      $stmt->bind_param("sii", $keyword, $sort_order, $status);
      $stmt->execute();
      
      $insert_id = $stmt->insert_id;
      $stmt->close();
      $conn->close();
      
      return $insert_id;
  }
  
  /**
   * 更新推荐搜索
   * @param int $id 推荐搜索ID
   * @param string $keyword 关键词
   * @param int $sort_order 排序
   * @param int $status 状态
   * @return bool 是否更新成功
   */
  function update_recommended_search($id, $keyword, $sort_order = 0, $status = 1) {
      $conn = get_db_connection();
      
      $stmt = $conn->prepare("UPDATE recommended_searches SET keyword = ?, sort_order = ?, status = ? WHERE id = ?");
      $stmt->bind_param("siii", $keyword, $sort_order, $status, $id);
      $result = $stmt->execute();
      
      $stmt->close();
      $conn->close();
      
      return $result;
  }
  
  /**
   * 删除推荐搜索
   * @param int $id 推荐搜索ID
   * @return bool 是否删除成功
   */
  function delete_recommended_search($id) {
      $conn = get_db_connection();
      
      $stmt = $conn->prepare("DELETE FROM recommended_searches WHERE id = ?");
      $stmt->bind_param("i", $id);
      $result = $stmt->execute();
      
      $stmt->close();
      $conn->close();
      
      return $result;
  }
  
  /**
   * 获取所有搜索玩法配置
   * @return array 搜索玩法配置列表
   */
  function get_all_search_play_configs() {
      $conn = get_db_connection();
      $configs = [];
      
      $sql = "SELECT id, keyword, target_url, status, created_at, updated_at FROM search_play_configs ORDER BY keyword ASC";
      $result = $conn->query($sql);
      
      while ($row = $result->fetch_assoc()) {
          $configs[] = $row;
      }
      
      $conn->close();
      
      return $configs;
  }
  
  /**
   * 根据ID获取搜索玩法配置
   * @param int $id 配置ID
   * @return array|null 配置详情
   */
  function get_search_play_config_by_id($id) {
      $conn = get_db_connection();
      $config = null;
      
      $stmt = $conn->prepare("SELECT id, keyword, target_url, status, created_at, updated_at FROM search_play_configs WHERE id = ?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
          $config = $result->fetch_assoc();
      }
      
      $stmt->close();
      $conn->close();
      
      return $config;
  }
  
  /**
   * 添加搜索玩法配置
   * @param string $keyword 关键词
   * @param string $target_url 目标URL
   * @param int $status 状态
   * @return int 插入的ID
   */
  function add_search_play_config($keyword, $target_url, $status = 1) {
      $conn = get_db_connection();
      
      $stmt = $conn->prepare("INSERT INTO search_play_configs (keyword, target_url, status) VALUES (?, ?, ?)");
      $stmt->bind_param("ssi", $keyword, $target_url, $status);
      $stmt->execute();
      
      $insert_id = $stmt->insert_id;
      $stmt->close();
      $conn->close();
      
      return $insert_id;
  }
  
  /**
   * 更新搜索玩法配置
   * @param int $id 配置ID
   * @param string $keyword 关键词
   * @param string $target_url 目标URL
   * @param int $status 状态
   * @return bool 是否更新成功
   */
  function update_search_play_config($id, $keyword, $target_url, $status = 1) {
      $conn = get_db_connection();
      
      $stmt = $conn->prepare("UPDATE search_play_configs SET keyword = ?, target_url = ?, status = ? WHERE id = ?");
      $stmt->bind_param("ssii", $keyword, $target_url, $status, $id);
      $result = $stmt->execute();
      
      $stmt->close();
      $conn->close();
      
      return $result;
  }
  
  /**
   * 删除搜索玩法配置
   * @param int $id 配置ID
   * @return bool 是否删除成功
   */
  function delete_search_play_config($id) {
      $conn = get_db_connection();
      
      $stmt = $conn->prepare("DELETE FROM search_play_configs WHERE id = ?");
      $stmt->bind_param("i", $id);
      $result = $stmt->execute();
      
      $stmt->close();
      $conn->close();
      
      return $result;
  }