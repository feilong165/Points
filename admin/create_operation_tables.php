<?php
// 创建运营积木相关表
require_once '../app/config.php';
require_once '../app/app.php';

// 创建预约活动表
function create_appointment_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS appointment_activities (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        details TEXT,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        group_size INT(11) NOT NULL DEFAULT 3,
        reward_type ENUM('points', 'code') NOT NULL DEFAULT 'points',
        reward_value INT(11) NOT NULL DEFAULT 0,
        reward_code VARCHAR(255) NULL,
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        echo "创建预约活动表失败: " . $conn->error . "<br>";
    } else {
        echo "预约活动表创建成功<br>";
    }
    
    $conn->close();
}

// 创建预约团队表
function create_appointment_group_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS appointment_groups (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        activity_id INT(11) NOT NULL,
        leader_user_id INT(11) NOT NULL,
        status ENUM('pending', 'completed', 'expired') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (activity_id) REFERENCES appointment_activities(id) ON DELETE CASCADE,
        FOREIGN KEY (leader_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        echo "创建预约团队表失败: " . $conn->error . "<br>";
    } else {
        echo "预约团队表创建成功<br>";
    }
    
    $conn->close();
}

// 创建预约成员表
function create_appointment_member_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS appointment_members (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        group_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES appointment_groups(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_activity (user_id, group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        echo "创建预约成员表失败: " . $conn->error . "<br>";
    } else {
        echo "预约成员表创建成功<br>";
    }
    
    $conn->close();
}

// 创建专题页表
function create_topics_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS topics (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        echo "创建专题页表失败: " . $conn->error . "<br>";
    } else {
        echo "专题页表创建成功<br>";
    }
    
    $conn->close();
}

// 创建特别活动表
function create_special_events_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS special_events (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        reward_type ENUM('points', 'code') NOT NULL DEFAULT 'points',
        reward_value INT(11) NOT NULL DEFAULT 0,
        reward_code VARCHAR(255) NULL,
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        echo "创建特别活动表失败: " . $conn->error . "<br>";
    } else {
        echo "特别活动表创建成功<br>";
    }
    
    $conn->close();
}

// 创建特别活动参与记录表
function create_special_event_participants_table() {
    $conn = get_db_connection();
    
    $sql = "CREATE TABLE IF NOT EXISTS special_event_participants (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        event_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        participated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES special_events(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_event (user_id, event_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        echo "创建特别活动参与记录表失败: " . $conn->error . "<br>";
    } else {
        echo "特别活动参与记录表创建成功<br>";
    }
    
    $conn->close();
}

// 运行所有表的创建函数
create_appointment_table();
create_appointment_group_table();
create_appointment_member_table();
create_topics_table();
create_special_events_table();
create_special_event_participants_table();

echo "所有运营积木相关表创建完成！";
?>