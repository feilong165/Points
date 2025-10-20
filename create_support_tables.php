<?php
// 客服表创建脚本
// 此脚本用于单独创建缺失的客服对话表和客服消息表

// 引入配置文件
require_once 'app/config.php';

// 获取数据库连接
function get_db_connection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // 检查连接是否成功
    if ($conn->connect_error) {
        die("数据库连接失败: " . $conn->connect_error);
    }
    
    return $conn;
}

// 创建客服对话表
echo "开始创建客服对话表 (support_conversations)...<br />";
$conn = get_db_connection();

// 创建客服对话表
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

if ($conn->query($sql)) {
    echo "✓ 成功创建support_conversations表<br />";
} else {
    echo "✗ 创建support_conversations表失败: " . $conn->error . "<br />";
}

// 创建客服消息表
echo "开始创建客服消息表 (support_messages)...<br />";

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

if ($conn->query($sql)) {
    echo "✓ 成功创建support_messages表<br />";
} else {
    echo "✗ 创建support_messages表失败: " . $conn->error . "<br />";
}

$conn->close();
echo "<br />操作完成！请尝试访问客服管理页面。";
?>