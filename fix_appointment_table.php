<?php
// 修复appointment_activities表，添加缺失的details字段
require_once 'app/config.php';
require_once 'app/app.php';

$conn = get_db_connection();

// 检查字段是否存在
$check_column_sql = "SHOW COLUMNS FROM appointment_activities LIKE 'details'";
$result = $conn->query($check_column_sql);

if ($result->num_rows === 0) {
    // 字段不存在，添加字段
    $alter_table_sql = "ALTER TABLE appointment_activities ADD COLUMN details TEXT AFTER description";
    if ($conn->query($alter_table_sql)) {
        echo "成功添加details字段到appointment_activities表！";
    } else {
        echo "添加字段失败: " . $conn->error;
    }
} else {
    echo "details字段已经存在，无需添加。";
}

$conn->close();
?>