<?php
// 数据库表结构更新脚本
// 此脚本用于更新现有表结构，添加缺失的字段

// 引入配置文件
require_once 'app/config.php';

// 获取数据库连接
function get_update_db_connection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // 检查连接是否成功
    if ($conn->connect_error) {
        die("数据库连接失败: " . $conn->connect_error);
    }
    
    return $conn;
}

// 检查字段是否存在
function check_column_exists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return $result->num_rows > 0;
}

// 更新兑换码表结构
function update_codes_table() {
    $conn = get_update_db_connection();
    
    // 检查expiry_date字段是否存在
    if (!check_column_exists($conn, 'codes', 'expiry_date')) {
        $sql = "ALTER TABLE codes ADD COLUMN expiry_date TIMESTAMP NULL AFTER created_at";
        if ($conn->query($sql)) {
            echo "✓ 成功添加expiry_date字段到codes表<br />
";
        } else {
            echo "✗ 添加expiry_date字段失败: " . $conn->error . "<br />
";
        }
    } else {
        echo "✓ expiry_date字段已存在于codes表<br />
";
    }
    
    // 检查status字段是否存在
    if (!check_column_exists($conn, 'codes', 'status')) {
        $sql = "ALTER TABLE codes ADD COLUMN status VARCHAR(20) DEFAULT 'available' AFTER expiry_date";
        if ($conn->query($sql)) {
            echo "✓ 成功添加status字段到codes表<br />
";
            
            // 更新现有记录的status值
            $update_sql = "UPDATE codes SET status = CASE WHEN is_used = 0 THEN 'available' ELSE 'used' END";
            if ($conn->query($update_sql)) {
                echo "✓ 成功更新现有记录的status值<br />
";
            } else {
                echo "✗ 更新status值失败: " . $conn->error . "<br />
";
            }
        } else {
            echo "✗ 添加status字段失败: " . $conn->error . "<br />
";
        }
    } else {
        echo "✓ status字段已存在于codes表<br />
";
    }
    
    $conn->close();
}

// 更新商品表结构，添加自定义标签和图片路径字段
function update_products_table() {
    $conn = get_update_db_connection();
    
    // 检查custom_tag字段是否存在
    if (!check_column_exists($conn, 'products', 'custom_tag')) {
        $sql = "ALTER TABLE products ADD COLUMN custom_tag VARCHAR(50) NULL AFTER sort_order";
        if ($conn->query($sql)) {
            echo "✓ 成功添加custom_tag字段到products表<br />
";
        } else {
            echo "✗ 添加custom_tag字段失败: " . $conn->error . "<br />
";
        }
    } else {
        echo "✓ custom_tag字段已存在于products表<br />
";
    }
    
    // 检查image_path字段是否存在
    if (!check_column_exists($conn, 'products', 'image_path')) {
        $sql = "ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER custom_tag";
        if ($conn->query($sql)) {
            echo "✓ 成功添加image_path字段到products表<br />
";
        } else {
            echo "✗ 添加image_path字段失败: " . $conn->error . "<br />
";
        }
    } else {
        echo "✓ image_path字段已存在于products表<br />
";
    }
    
    $conn->close();
}

// 创建客服对话表
function create_support_conversations_table() {
    $conn = get_update_db_connection();
    
    // 检查表是否存在
    $result = $conn->query("SHOW TABLES LIKE 'support_conversations'");
    if ($result->num_rows > 0) {
        echo "✓ support_conversations表已存在<br />
";
        $conn->close();
        return;
    }
    
    // 创建表
    $sql = "CREATE TABLE support_conversations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        admin_id INT(11) NULL,
        is_closed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "✓ 成功创建support_conversations表<br />
";
    } else {
        echo "✗ 创建support_conversations表失败: " . $conn->error . "<br />
";
    }
    
    $conn->close();
}

// 创建客服消息表
function create_support_messages_table() {
    $conn = get_update_db_connection();
    
    // 检查表是否存在
    $result = $conn->query("SHOW TABLES LIKE 'support_messages'");
    if ($result->num_rows > 0) {
        echo "✓ support_messages表已存在<br />
";
        $conn->close();
        return;
    }
    
    // 创建表
    $sql = "CREATE TABLE support_messages (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT(11) NOT NULL,
        sender_type ENUM('user', 'admin') NOT NULL,
        sender_id INT(11) NOT NULL,
        content TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES support_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "✓ 成功创建support_messages表<br />
";
    } else {
        echo "✗ 创建support_messages表失败: " . $conn->error . "<br />
";
    }
    
    $conn->close();
}

// 执行表结构更新
function run_table_updates() {
    echo "开始更新数据库表结构...<br />
";
    echo "----------------------------------------<br />
";
    
    // 更新兑换码表
    echo "更新codes表结构:<br />
";
    update_codes_table();
    
    // 更新商品表
    echo "更新products表结构:<br />
";
    update_products_table();
    
    // 创建客服相关表
    echo "创建客服相关表:<br />
";
    create_support_conversations_table();
    create_support_messages_table();
    
    echo "----------------------------------------<br />
";
    echo "表结构更新完成！<br />
";
    echo "<br />
";
    echo "系统已准备就绪，请<a href='admin/codes.php'>点击这里</a>返回兑换码管理页面。";
}

// 运行更新
run_table_updates();
?>