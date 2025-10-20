<?php
/**
 * 安全函数库 - 提供防SQL注入和输入验证功能
 */

/**
 * 过滤用户输入，防止SQL注入
 * @param mixed $input 用户输入数据
 * @param string $type 输入类型：'string', 'int', 'float', 'bool'
 * @return mixed 过滤后的数据
 */
function sanitize_input($input, $type = 'string') {
    // 如果输入是数组，递归处理每个元素
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize_input($value, $type);
        }
        return $input;
    }
    
    // 根据类型进行过滤
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false ? intval($input) : 0;
            
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? floatval($input) : 0.0;
            
        case 'bool':
            return filter_var($input, FILTER_VALIDATE_BOOLEAN);
            
        case 'string':
        default:
            // 对于字符串，使用htmlspecialchars防止XSS，同时移除可能的SQL注入字符
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            // 额外的SQL注入防护
            $input = preg_replace('/[\\\'"\`]/', '', $input);
            return $input;
    }
}

/**
 * 安全获取GET参数
 * @param string $key 参数名
 * @param mixed $default 默认值
 * @param string $type 类型
 * @return mixed 安全的参数值
 */
function safe_get($key, $default = null, $type = 'string') {
    return isset($_GET[$key]) ? sanitize_input($_GET[$key], $type) : $default;
}

/**
 * 安全获取POST参数
 * @param string $key 参数名
 * @param mixed $default 默认值
 * @param string $type 类型
 * @return mixed 安全的参数值
 */
function safe_post($key, $default = null, $type = 'string') {
    return isset($_POST[$key]) ? sanitize_input($_POST[$key], $type) : $default;
}

/**
 * 安全获取REQUEST参数
 * @param string $key 参数名
 * @param mixed $default 默认值
 * @param string $type 类型
 * @return mixed 安全的参数值
 */
function safe_request($key, $default = null, $type = 'string') {
    return isset($_REQUEST[$key]) ? sanitize_input($_REQUEST[$key], $type) : $default;
}

/**
 * 验证排序字段是否合法
 * @param string $field 排序字段名
 * @param array $allowed_fields 允许的字段列表
 * @param string $default 默认排序字段
 * @return string 安全的排序字段
 */
function validate_sort_field($field, $allowed_fields, $default) {
    // 过滤并验证排序字段
    $field = sanitize_input($field);
    return in_array($field, $allowed_fields) ? $field : $default;
}

/**
 * 验证排序方向是否合法
 * @param string $direction 排序方向
 * @param string $default 默认排序方向
 * @return string 安全的排序方向
 */
function validate_sort_direction($direction, $default = 'ASC') {
    // 只允许ASC或DESC
    $direction = strtoupper(sanitize_input($direction));
    return in_array($direction, ['ASC', 'DESC']) ? $direction : $default;
}

/**
 * 安全的分页参数处理
 * @param int $page 当前页码
 * @param int $limit 每页记录数
 * @return array [offset, limit]
 */
function safe_pagination($page, $limit = 10) {
    $page = max(1, intval($page));
    $limit = max(1, min(100, intval($limit))); // 限制每页最多100条记录
    $offset = ($page - 1) * $limit;
    
    return [$offset, $limit];
}

/**
 * 安全地构建WHERE条件
 * @param mysqli $conn 数据库连接
 * @param array $conditions 条件数组 [字段名 => [值, 操作符, 类型]]
 * @return array [where_sql, params, types]
 */
function build_safe_where($conn, $conditions) {
    $where_conditions = [];
    $params = [];
    $types = '';
    
    foreach ($conditions as $field => $condition) {
        // 确保字段名有效（只允许字母、数字和下划线）
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
            continue;
        }
        
        $value = $condition[0];
        $operator = isset($condition[1]) ? $condition[1] : '=';
        $type = isset($condition[2]) ? $condition[2] : 's';
        
        // 验证操作符
        $valid_operators = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IN', 'NOT IN'];
        $operator = strtoupper($operator);
        if (!in_array($operator, $valid_operators)) {
            continue;
        }
        
        // 处理不同的操作符
        if ($operator === 'IN' || $operator === 'NOT IN') {
            if (is_array($value) && !empty($value)) {
                $placeholders = [];
                foreach ($value as $item) {
                    $placeholders[] = '?';
                    $params[] = $item;
                    $types .= $type;
                }
                $where_conditions[] = "$field $operator (" . implode(', ', $placeholders) . ")";
            }
        } else {
            $where_conditions[] = "$field $operator ?";
            $params[] = $value;
            $types .= $type;
        }
    }
    
    $where_sql = empty($where_conditions) ? '' : ' WHERE ' . implode(' AND ', $where_conditions);
    
    return [$where_sql, $params, $types];
}

/**
 * 为数据库查询添加防注入的LIKE条件
 * @param string $value LIKE值
 * @return string 处理后的LIKE值
 */
function safe_like_value($value) {
    // 转义LIKE中的特殊字符
    $value = str_replace(['%', '_'], ['\%', '\_'], $value);
    return '%' . $value . '%';
}

/**
 * 检查并修复可能存在的SQL注入风险
 * @param mysqli $conn 数据库连接
 * @param string $query SQL查询
 * @return bool 是否安全
 */
function check_sql_injection($conn, $query) {
    // 简单的SQL注入检测
    $injection_patterns = [
        '/\bsleep\s*\(/i', '/\bdelay\s*\(/i', '/\bwaitfor\s+delay\s+\'/i',
        '/\bexec\s*\(/i', '/\bexecute\s*\(/i', '/\bsp_executesql\s*\(/i',
        '/\bunion\s+all\s+select/i', '/\bselect\s+.+\s+from\s+/i',
        '/\binsert\s+into\s+/i', '/\bupdate\s+.+\s+set\s+/i',
        '/\bdelete\s+from\s+/i', '/\bdrop\s+(table|database)\s+/i'
    ];
    
    foreach ($injection_patterns as $pattern) {
        if (preg_match($pattern, $query)) {
            return false;
        }
    }
    
    return true;
}

/**
 * 记录安全事件日志
 * @param string $event 事件类型
 * @param string $details 事件详情
 */
function log_security_event($event, $details) {
    // 可以扩展为写入文件或数据库
    $log_message = date('Y-m-d H:i:s') . " - [$event] $details\n";
    $log_file = dirname(__FILE__) . '/../logs/security.log';
    
    // 确保日志目录存在
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // 写入日志
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * 启用全局输入过滤
 */
function enable_global_input_filter() {
    // 过滤GET变量
    if (isset($_GET) && !empty($_GET)) {
        foreach ($_GET as $key => $value) {
            $_GET[$key] = sanitize_input($value);
        }
    }
    
    // 过滤POST变量
    if (isset($_POST) && !empty($_POST)) {
        foreach ($_POST as $key => $value) {
            $_POST[$key] = sanitize_input($value);
        }
    }
    
    // 过滤COOKIE变量
    if (isset($_COOKIE) && !empty($_COOKIE)) {
        foreach ($_COOKIE as $key => $value) {
            $_COOKIE[$key] = sanitize_input($value);
        }
    }
}