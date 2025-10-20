<?php
// 这个脚本用于更新优惠券表结构，添加积分满减相关字段

// 引入配置文件
require_once 'app/config.php';
require_once 'app/app.php';

// 修改优惠券表，添加积分满减相关字段
function add_points_discount_fields() {
    $conn = get_db_connection();
    
    // 检查points_discount字段是否存在
    $result = $conn->query("SHOW COLUMNS FROM coupons LIKE 'points_discount'");
    if ($result->num_rows == 0) {
        if ($conn->query("ALTER TABLE coupons ADD COLUMN points_discount INT(11) DEFAULT 0 AFTER discount_value")) {
            echo "成功添加points_discount字段\n";
        } else {
            echo "添加points_discount字段失败: " . $conn->error . "\n";
        }
    } else {
        echo "points_discount字段已存在\n";
    }
    
    // 检查min_points_required字段是否存在
    $result = $conn->query("SHOW COLUMNS FROM coupons LIKE 'min_points_required'");
    if ($result->num_rows == 0) {
        if ($conn->query("ALTER TABLE coupons ADD COLUMN min_points_required INT(11) DEFAULT 0 AFTER min_order_amount")) {
            echo "成功添加min_points_required字段\n";
        } else {
            echo "添加min_points_required字段失败: " . $conn->error . "\n";
        }
    } else {
        echo "min_points_required字段已存在\n";
    }
    
    $conn->close();
    return true;
}

// 执行函数
add_points_discount_fields();

// 在app.php中添加这个函数的定义，以便后续使用
$app_php_path = 'app/app.php';
$function_code = "\n// 修改优惠券表，添加积分满减相关字段\nfunction add_points_discount_fields() {\n    $conn = get_db_connection();\n    \n    // 检查points_discount字段是否存在\n    $result = $conn->query(\"SHOW COLUMNS FROM coupons LIKE 'points_discount'\");\n    if ($result->num_rows == 0) {\n        $conn->query(\"ALTER TABLE coupons ADD COLUMN points_discount INT(11) DEFAULT 0 AFTER discount_value\");\n    }\n    \n    // 检查min_points_required字段是否存在\n    $result = $conn->query(\"SHOW COLUMNS FROM coupons LIKE 'min_points_required'\");\n    if ($result->num_rows == 0) {\n        $conn->query(\"ALTER TABLE coupons ADD COLUMN min_points_required INT(11) DEFAULT 0 AFTER min_order_amount\");\n    }\n    \n    $conn->close();\n    return true;\n}\n"; // 注意这里我们不调用函数，只添加定义

// 读取app.php内容
$app_content = file_get_contents($app_php_path);

// 检查函数是否已存在
if (strpos($app_content, 'function add_points_discount_fields()') === false) {
    // 添加函数到app.php
    file_put_contents($app_php_path, $function_code, FILE_APPEND);
    echo "函数已添加到app.php\n";
} else {
    echo "函数已存在于app.php中\n";
}

?>