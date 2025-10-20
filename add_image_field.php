<?php
// 引入配置和数据库连接
require_once 'app/config.php';
require_once 'app/app.php';

// 获取数据库连接
$conn = get_db_connection();

try {
    // 检查image_path字段是否存在
    $check_column_sql = "SHOW COLUMNS FROM products LIKE 'image_path'";
    $column_result = $conn->query($check_column_sql);
    
    if ($column_result->num_rows == 0) {
        // 没有image_path字段，添加
        $add_column_sql = "ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER custom_tag";
        if ($conn->query($add_column_sql)) {
            echo "成功添加image_path字段到products表！\n";
        } else {
            echo "添加字段失败: " . $conn->error . "\n";
        }
    } else {
        echo "image_path字段已经存在于products表中！\n";
    }
} catch (Exception $e) {
    echo "发生错误: " . $e->getMessage() . "\n";
}

// 关闭数据库连接
$conn->close();
?>