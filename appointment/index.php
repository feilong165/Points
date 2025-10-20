<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 检查用户是否登录
session_start();
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
$username = $is_logged_in ? $_SESSION['username'] : '';

// 发放团队奖励
function distribute_team_rewards($team_id, $event_id, $conn) {
    // 获取活动信息
    $sql = "SELECT * FROM appointment_activities WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event_result = $stmt->get_result();
    $event = $event_result->fetch_assoc();
    $stmt->close();
    
    if (!$event) {
        return false;
    }
    
    // 获取团队成员
    $sql = "SELECT m.user_id, u.username 
            FROM appointment_members m 
            JOIN users u ON m.user_id = u.id 
            WHERE m.group_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $members_result = $stmt->get_result();
    $stmt->close();
    
    // 为每个成员发放奖励
    while ($member = $members_result->fetch_assoc()) {
        if ($event['reward_type'] === 'points' && $event['reward_value'] > 0) {
            // 发放积分奖励
            $points_change = $event['reward_value'];
            $reason = "参与活动'{$event['name']}'团队达到人数要求获得积分奖励";
            
            // 更新用户积分
            $update_sql = "UPDATE users SET points = points + ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $points_change, $member['user_id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // 记录积分变动
            log_points_change($member['user_id'], $points_change, $reason, 'activity_reward');
        } else if ($event['reward_type'] === 'code' && !empty($event['reward_code'])) {
            // 发放兑换码奖励（这里可以根据实际需求实现兑换码的发放逻辑）
            // 例如：记录兑换码的发放历史或通知用户
            $reason = "参与活动'{$event['name']}'团队达到人数要求获得兑换码";
            log_points_change($member['user_id'], 0, $reason . ': ' . $event['reward_code'], 'activity_reward');
        }
    }
    
    return true;
}

// 获取活动ID
$event_id = 0;
if (isset($_GET['id'])) {
    $event_id = intval($_GET['id']);
}

// 如果没有活动ID，重定向到首页
if ($event_id <= 0) {
    header("Location: " . APP_URL);
    exit;
}

// 查询活动信息
$conn = get_db_connection();
$sql = "SELECT * FROM appointment_activities WHERE id = ? AND status = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();
$stmt->close();

// 如果活动不存在或未启用，显示404页面
if (!$event) {
    $conn->close();
    http_response_code(404);
    echo "<h1>活动不存在</h1><p>您访问的预约活动不存在或已被删除。</p>";
    exit;
}

// 检查活动时间是否已结束，是否已开始
$current_time = date('Y-m-d H:i:s');
$is_ended = strtotime($event['end_time']) < strtotime($current_time);
$is_started = strtotime($event['start_time']) <= strtotime($current_time);

// 检查用户是否已经参与此活动
$has_participated = false;
$user_team_id = 0;
if ($is_logged_in) {
    $sql = "SELECT g.id as team_id FROM appointment_members m JOIN appointment_groups g ON m.group_id = g.id WHERE g.activity_id = ? AND m.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $has_participated = true;
        $user_team_id = $row['team_id'];
    }
    $stmt->close();
}

// 查询活动参与人数
$sql = "SELECT COUNT(*) as total_participants FROM appointment_members m JOIN appointment_groups g ON m.group_id = g.id WHERE g.activity_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$count_result = $stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_participants = $count_row['total_participants'];
$stmt->close();

// 查询热门团队（只显示招募中的团队）
$sql = "SELECT ag.*, COUNT(am.id) as member_count 
        FROM appointment_groups ag 
        LEFT JOIN appointment_members am ON ag.id = am.group_id 
        WHERE ag.activity_id = ? 
        GROUP BY ag.id 
        ORDER BY member_count DESC 
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$teams_result = $stmt->get_result();
$hot_teams = [];
while ($team = $teams_result->fetch_assoc()) {
    // 只显示招募中的团队
    if ($team['status'] === 'pending') {
        $hot_teams[] = $team;
    }
}
$stmt->close();
$conn->close();

// 处理参与/预约表单提交
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in && !$is_ended && !$has_participated && $is_started) {
    if (isset($_POST['join_team'])) {
        // 加入团队
        $team_id = intval($_POST['team_id']);
        
        $conn = get_db_connection();
        // 检查团队是否存在且与当前活动匹配
        $sql = "SELECT ag.*, COUNT(am.id) as current_members 
                FROM appointment_groups ag 
                LEFT JOIN appointment_members am ON ag.id = am.group_id 
                WHERE ag.id = ? AND ag.activity_id = ?
                GROUP BY ag.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $team_id, $event_id);
        $stmt->execute();
        $team_result = $stmt->get_result();
        
        if ($team_result->num_rows > 0) {
            $team_data = $team_result->fetch_assoc();
            // 从活动表获取团队人数限制
            $sql = "SELECT group_size FROM appointment_activities WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $activity_result = $stmt->get_result();
            $activity_data = $activity_result->fetch_assoc();
            $max_members = $activity_data['group_size'];
            
            if ($team_data['current_members'] < $max_members) {
                // 可以加入团队
                $sql = "INSERT INTO appointment_members (group_id, user_id) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $team_id, $user_id);
                
                if ($stmt->execute()) {
                    $message = "成功加入团队！";
                    $message_type = "success";
                    
                    // 检查团队人数是否达到上限
                    $new_member_count = $team_data['current_members'] + 1;
                    if ($new_member_count >= $max_members) {
                        // 发放奖励
                        distribute_team_rewards($team_id, $event_id, $conn);
                        // 更新团队状态为已完成
                        $update_sql = "UPDATE appointment_groups SET status = 'completed' WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("i", $team_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    
                    // 重新加载页面以更新状态
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                } else {
                    $message = "加入团队失败，请稍后重试。";
                    $message_type = "error";
                }
            } else {
                $message = "该团队已满员，请选择其他团队。";
                $message_type = "error";
            }
        } else {
            $message = "团队不存在。";
            $message_type = "error";
        }
        
        $stmt->close();
        $conn->close();
    } elseif (isset($_POST['create_team'])) {
        // 创建新团队
        // 验证团队人数（从活动表获取）
        $conn = get_db_connection();
        $sql = "SELECT group_size FROM appointment_activities WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $activity_result = $stmt->get_result();
        $activity_data = $activity_result->fetch_assoc();
        $max_members = $activity_data['group_size'];
        
        // 创建团队
        $sql = "INSERT INTO appointment_groups (activity_id, leader_user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $event_id, $user_id);
        
        if ($stmt->execute()) {
            $team_id = $conn->insert_id;
            
            // 将创建者添加到团队
            $sql = "INSERT INTO appointment_members (group_id, user_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $team_id, $user_id);
                
                if ($stmt->execute()) {
                    $message = "团队创建成功！";
                    $message_type = "success";
                    
                    // 检查团队人数是否达到上限（如果上限为1）
                    if ($max_members <= 1) {
                        // 发放奖励
                        distribute_team_rewards($team_id, $event_id, $conn);
                        // 更新团队状态为已完成
                        $update_sql = "UPDATE appointment_groups SET status = 'completed' WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("i", $team_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    
                    // 重新加载页面以更新状态
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                } else {
                    // 如果添加成员失败，删除创建的团队
                    $sql = "DELETE FROM appointment_groups WHERE id = ?";
                    $delete_stmt = $conn->prepare($sql);
                    $delete_stmt->bind_param("i", $team_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    
                    $message = "创建团队失败，请稍后重试。";
                    $message_type = "error";
                }
            } else {
                $message = "创建团队失败，请稍后重试。";
                $message_type = "error";
            }
            
            $stmt->close();
            $conn->close();
        }
    }
    
    // 处理获取团队信息的AJAX请求
    if (isset($_GET['action']) && $_GET['action'] === 'get_team_info' && isset($_GET['team_id'])) {
        $team_id = intval($_GET['team_id']);
        
        $conn = get_db_connection();
        
        // 查询团队信息和成员数
        $sql = "SELECT ag.*, COUNT(am.id) as member_count, u.username as creator_name 
                FROM appointment_groups ag 
                LEFT JOIN appointment_members am ON ag.id = am.group_id 
                LEFT JOIN users u ON ag.leader_user_id = u.id 
                WHERE ag.id = ? 
                GROUP BY ag.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $team_result = $stmt->get_result();
        
        if ($team_result->num_rows > 0) {
            $team_data = $team_result->fetch_assoc();
            
            // 获取活动的团队人数限制
            $sql = "SELECT group_size FROM appointment_activities WHERE id = ?";
            $activity_stmt = $conn->prepare($sql);
            $activity_stmt->bind_param("i", $team_data['activity_id']);
            $activity_stmt->execute();
            $activity_result = $activity_stmt->get_result();
            $activity_data = $activity_result->fetch_assoc();
            
            $response = array(
                'success' => true,
                'team_id' => $team_data['id'],
                'member_count' => $team_data['member_count'],
                'max_members' => !empty($activity_data['group_size']) ? $activity_data['group_size'] : 5,
                'creator_name' => $team_data['creator_name']
            );
            
            $activity_stmt->close();
        } else {
            $response = array(
                'success' => false,
                'message' => '团队不存在'
            );
        }
        
        $stmt->close();
        $conn->close();
        
        // 设置响应头并输出JSON
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

?> 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $event['name']; ?> - 组队活动</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- 配置Tailwind CSS -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#6366f1',
                        accent: '#f43f5e',
                        neutral: '#1f2937',
                        'base-100': '#ffffff',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                },
            }
        }
    </script>
    
    <!-- 自定义工具类 -->
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .text-shadow {
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .transition-custom {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
        }
    </style>
    
    <style>
        /* 预约页面专用样式 */
        body {
            background-color: #f9fafb;
        }
        .appointment-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        /* 倒计时样式已移至奖励区块内 */
        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background-color: #e5e7eb;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            background-color: #3b82f6;
            transition: width 0.3s ease;
        }
        .share-btn {
            transition: all 0.3s ease;
        }
        .share-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="appointment-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="<?php echo APP_URL; ?>" class="text-primary font-bold text-xl">
                            <i class="fa fa-diamond mr-2"></i>积分系统
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <?php if ($is_logged_in): ?>
                        <div class="mr-4 text-gray-700">
                            <span class="mr-2"><i class="fa fa-user"></i> <?php echo $username; ?></span>
                            <span class="bg-primary/10 text-primary px-2 py-1 rounded text-xs">
                                <i class="fa fa-coins"></i> <?php echo isset($_SESSION['points']) ? $_SESSION['points'] : 0; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <a href="<?php echo APP_URL; ?>" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium transition-custom">
                        返回首页
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- 活动标题 -->
    <section class="bg-gradient-to-r from-primary to-secondary py-12">
        <div class="appointment-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center text-white">
                <h1 class="text-3xl md:text-5xl font-bold mb-4">
                    <?php echo $event['name']; ?>
                </h1>
                <p class="text-lg md:text-xl mb-6 max-w-2xl mx-auto">
                    <?php echo $event['description']; ?>
                </p>
                
                <!-- 参与按钮 -->
                <?php if ($is_ended): ?>
                    <span class="inline-block px-6 py-3 bg-gray-500 rounded-lg text-white font-medium cursor-not-allowed">
                        活动已结束
                    </span>
                <?php elseif (!$is_logged_in): ?>
                    <a href="../../login.php?return_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="inline-block px-6 py-3 bg-white text-primary hover:bg-gray-100 rounded-lg font-medium transition-custom">
                    登录参与
                </a>
                <?php elseif ($has_participated): ?>
                    <span class="inline-block px-6 py-3 bg-green-500 rounded-lg text-white font-medium">
                        已成功参与
                    </span>
                <?php elseif (!$is_started): ?>
                    <span class="inline-block px-6 py-3 bg-gray-300 rounded-lg text-gray-800 font-medium cursor-not-allowed">
                        活动未开始
                    </span>
                <?php else: ?>
                    <button id="join-event-btn" class="inline-block px-6 py-3 bg-white text-primary hover:bg-gray-100 rounded-lg font-medium transition-custom">
                        立即参与
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 活动奖励区域 - 移至最上方 -->
    <section class="py-8">
        <div class="appointment-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-r from-primary to-secondary rounded-xl shadow-md p-6 md:p-8 text-white">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold flex items-center">
                        <i class="fa fa-gift mr-3"></i>活动奖励
                    </h2>
                    <div class="mt-4 md:mt-0 bg-white/20 backdrop-blur-sm px-4 py-2 rounded-lg">
                        <span id="countdown-label" class="text-sm md:text-base font-medium">
                            <?php echo !$is_started ? '活动开始剩余：' : '活动结束剩余：'; ?>
                        </span>
                        <div class="flex items-center space-x-1 text-sm md:text-base">
                            <span id="days" class="font-bold">00</span>
                            <span>天</span>
                            <span>:</span>
                            <span id="hours" class="font-bold">00</span>
                            <span>时</span>
                            <span>:</span>
                            <span id="minutes" class="font-bold">00</span>
                            <span>分</span>
                            <span>:</span>
                            <span id="seconds" class="font-bold">00</span>
                            <span>秒</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white/10 backdrop-blur-sm p-5 rounded-lg">
                    <?php 
                        // 根据实际的数据库字段显示奖励信息
                        if (!empty($event['reward_type'])) {
                            if ($event['reward_type'] === 'points') {
                                echo $event['reward_value'] . ' 积分';
                            } else if ($event['reward_type'] === 'code') {
                                echo '兑换码: ' . $event['reward_code'];
                            }
                        } else {
                            echo '暂无奖励信息';
                        }
                    ?>
                </div>
            </div>
        </div>
    </section>

    <!-- 主要内容 -->
    <main class="py-10">
        <div class="appointment-container mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- 我的团队 -->
            <?php if (isset($user_team_id) && $user_team_id > 0): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <?php 
                        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                        if ($conn->connect_error) {
                            die("数据库连接失败: " . $conn->connect_error);
                        }
                        
                        // 查询团队信息和成员数量
                        $sql = "SELECT ag.*, COUNT(am.id) as member_count 
                                FROM appointment_groups ag 
                                LEFT JOIN appointment_members am ON ag.id = am.group_id 
                                WHERE ag.id = ? AND ag.activity_id = ? 
                                GROUP BY ag.id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $user_team_id, $event_id);
                        $stmt->execute();
                        $team_result = $stmt->get_result();
                        
                        if ($team_result->num_rows > 0) {
                            $user_team = $team_result->fetch_assoc();
                            $stmt->close();
                            
                            // 查询团队成员
                            $sql = "SELECT u.username 
                                    FROM appointment_members am 
                                    JOIN users u ON am.user_id = u.id 
                                    WHERE am.group_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $user_team_id);
                            $stmt->execute();
                            $members_result = $stmt->get_result();
                            $members = [];
                            while ($member = $members_result->fetch_assoc()) {
                                $members[] = $member;
                            }
                            $stmt->close();
                            $conn->close();
                        }
                    ?>
                    
                    <div class="mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-800">我的团队</h3>
                                <p class="text-gray-500 mt-1">
                                    成员：<?php echo $user_team['member_count']; ?>/<?php echo !empty($event['group_size']) ? $event['group_size'] : 5; ?>
                                </p>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <?php if ($user_team['leader_user_id'] == $user_id): ?>
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">                                        团队领袖
                                    </span>
                                <?php endif; ?>
                                <button class="ml-2 px-3 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-custom" onclick="copyTeamInviteLink()">
                                    <i class="fa fa-share-alt mr-1"></i>邀请成员
                                </button>
                            </div>
                        </div>
                        
                        <!-- 团队成员列表 -->
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h4 class="font-medium text-gray-700">团队成员</h4>
                            </div>
                            <ul class="divide-y divide-gray-200">
                                <?php foreach ($members as $member): ?>
                                    <li class="px-4 py-3 flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 mr-3">
                                                <i class="fa fa-user"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900"><?php echo $member['username']; ?></p>
                                            </div>
                                        </div>
                                        <?php if ($member['username'] == $username): ?>
                                            <span class="text-xs text-gray-500">(我)</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 活动信息 -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
                <!-- 左侧：活动详情 -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm p-6 md:p-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                            <i class="fa fa-info-circle text-primary mr-3"></i>活动详情
                        </h2>
                        
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-3">活动介绍</h3>
                                <div class="prose max-w-none text-gray-600">
                                    <?php echo !empty($event['details']) ? $event['details'] : '暂无活动详细介绍'; ?>
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-3">活动规则</h3>
                                <ul class="space-y-2 text-gray-600">
                                    <li class="flex items-start">
                                        <i class="fa fa-check-circle text-primary mt-1 mr-2"></i>
                                        <span>活动时间：<?php echo date('Y年m月d日 H:i', strtotime($event['start_time'])); ?> - <?php echo date('Y年m月d日 H:i', strtotime($event['end_time'])); ?></span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fa fa-check-circle text-primary mt-1 mr-2"></i>
                                        <span>参与条件：<?php echo !empty($event['requirements']) ? $event['requirements'] : '无特殊要求'; ?></span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fa fa-check-circle text-primary mt-1 mr-2"></i>
                                        <span>组队规则：每队最多<?php echo !empty($event['group_size']) ? $event['group_size'] : 5; ?>人，可邀请好友加入</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 右侧：活动统计和分享 -->
                <div>
                    <!-- 活动统计 -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fa fa-bar-chart text-primary mr-2"></i>活动统计
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm text-gray-600">参与人数</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo $total_participants; ?><?php if (!empty($event['max_participants']) && $event['max_participants'] > 0): ?>/<?php echo $event['max_participants']; ?><?php endif; ?>
                                    </span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo (!empty($event['max_participants']) && $event['max_participants'] > 0) ? min(100, ($total_participants / $event['max_participants']) * 100) : 0; ?>%" ></div>
                                </div>
                            </div>
                            <div class="pt-4 border-t border-gray-100">
                                <p class="text-sm text-gray-500 mb-1">活动状态</p>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <?php echo $is_ended ? '已结束' : '进行中'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 分享 -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fa fa-share-alt text-primary mr-2"></i>分享活动
                        </h2>
                        <div class="flex justify-center space-x-4">
                            <button class="share-btn w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white">
                                <i class="fa fa-weixin"></i>
                            </button>
                            <button class="share-btn w-10 h-10 rounded-full bg-red-500 flex items-center justify-center text-white">
                                <i class="fa fa-weibo"></i>
                            </button>
                            <button class="share-btn w-10 h-10 rounded-full bg-blue-400 flex items-center justify-center text-white">
                                <i class="fa fa-qq"></i>
                            </button>
                            <button class="share-btn w-10 h-10 rounded-full bg-gray-500 flex items-center justify-center text-white" onclick="copyShareLink()">
                                <i class="fa fa-link"></i>
                            </button>
                        </div>
                        <div class="mt-4">
                            <input type="text" value="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?id=' . $event_id; ?>" 
                                   readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50" id="share-link">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 参与表单（模态框） -->
            <div id="join-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
                <div class="bg-white rounded-xl shadow-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xl font-bold text-gray-900">参与活动</h3>
                            <button id="close-modal" class="text-gray-500 hover:text-gray-700">
                                <i class="fa fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <!-- 加入现有团队 -->
                        <div class="mb-8" id="invited-team-section" style="display: none;">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4">加入邀请团队</h4>
                            <div class="space-y-3">
                                <form method="POST">
                                    <input type="hidden" name="join_team" value="1">
                                    <input type="hidden" name="team_id" id="invited-team-id" value="">
                                    <div class="p-4 border border-gray-200 rounded-lg border-primary" id="invited-team-card">
                                        <div class="flex items-center">
                                            <div class="ml-4">
                                                <h5 class="font-medium text-gray-900" id="invited-team-name">团队 #</h5>
                                                <p class="text-sm text-gray-500" id="invited-team-info">成员：/ | 创建者：用户
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="mt-4 w-full py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-custom" id="join-invited-team-btn">                                            加入该团队
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- 创建新团队 -->
                        <div>
                            <h4 class="text-lg font-semibold text-gray-800 mb-4">创建新团队</h4>
                            <form method="POST">
                                <input type="hidden" name="create_team" value="1">
                                <div class="space-y-4">
    
                                    <button type="submit" class="w-full py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-custom">                                        创建团队
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            

            
            <!-- 热门团队 -->
            <?php 
                // 检查URL中是否有team_id参数
                $has_team_id_param = isset($_GET['team_id']) && !empty($_GET['team_id']);
            ?>
            <?php if (count($hot_teams) > 0 && !$has_participated && !$has_team_id_param && $is_started): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 md:p-8">                    <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">                        <i class="fa fa-trophy text-primary mr-3"></i>热门团队
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">                        <?php foreach ($hot_teams as $team): ?>                            <div class="border border-gray-200 rounded-lg p-4 team-card transition-custom">                                <div class="flex justify-between items-start mb-2">                                    <h3 class="font-semibold text-gray-900">团队 #<?php echo $team['id']; ?></h3>                                    <!-- 团队状态标识 -->                                    <span class="inline-block px-2 py-1 text-xs font-medium rounded-full bg-<?php echo ($team['status'] === 'completed') ? 'green' : (($team['status'] === 'expired') ? 'gray' : 'yellow'); ?>-100 text-<?php echo ($team['status'] === 'completed') ? 'green' : (($team['status'] === 'expired') ? 'gray' : 'yellow'); ?>-800">                                        <?php echo ($team['status'] === 'completed') ? '已完成' : (($team['status'] === 'expired') ? '已过期' : '招募中'); ?>                                    </span>                                </div>
                                <p class="text-sm text-gray-500 mb-3">
                                    成员：<?php echo $team['member_count']; ?>/<?php echo !empty($event['group_size']) ? $event['group_size'] : 5; ?> | 
                                    创建者：用户<?php echo $team['leader_user_id']; ?>
                                </p>                                <div class="progress-bar mb-3">                                    <div class="progress-fill" style="width: <?php echo !empty($event['group_size']) ? (($team['member_count'] / $event['group_size']) * 100) : 0; ?>%" ></div>                                </div>                                <?php if (!$is_ended && $is_logged_in && !$has_participated && $team['status'] === 'pending' && $is_started): ?>                                    <form method="POST" class="inline">                                        <input type="hidden" name="join_team" value="1">                                        <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">                                        <button type="submit" class="w-full py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-custom text-sm">                                            加入团队
                                        </button>                                    </form>                                <?php endif; ?>                            </div>                        <?php endforeach; ?>                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>


    <!-- JavaScript -->
    <script>
        // 倒计时功能 - 支持显示距离开始或结束的时间
        function updateCountdown() {
            const startTime = new Date('<?php echo $event['start_time']; ?>').getTime();
            const endTime = new Date('<?php echo $event['end_time']; ?>').getTime();
            const now = new Date().getTime();
            
            let targetTime, distance, label;
            let isEventStarted = now >= startTime;
            let isEventEnded = now >= endTime;
            
            // 如果活动已结束
            if (isEventEnded) {
                document.getElementById('days').innerText = '00';
                document.getElementById('hours').innerText = '00';
                document.getElementById('minutes').innerText = '00';
                document.getElementById('seconds').innerText = '00';
                document.getElementById('countdown-label').textContent = '活动已结束';
                return;
            }
            
            // 如果活动尚未开始
            if (!isEventStarted) {
                targetTime = startTime;
                label = '活动开始剩余：';
            } 
            // 如果活动正在进行中
            else {
                targetTime = endTime;
                label = '活动结束剩余：';
            }
            
            distance = targetTime - now;
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('days').innerText = days.toString().padStart(2, '0');
            document.getElementById('hours').innerText = hours.toString().padStart(2, '0');
            document.getElementById('minutes').innerText = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').innerText = seconds.toString().padStart(2, '0');
            document.getElementById('countdown-label').textContent = label;
            
            // 如果活动从未开始变为已开始，刷新页面更新状态
            if (!window.previousEventStatus && isEventStarted) {
                window.location.reload();
            }
            
            window.previousEventStatus = isEventStarted;
        }
        
        // 初始化倒计时和状态
        window.previousEventStatus = new Date().getTime() >= new Date('<?php echo $event['start_time']; ?>').getTime();
        updateCountdown();
        setInterval(updateCountdown, 1000);
        
        // 模态框控制
        document.getElementById('join-event-btn').addEventListener('click', function() {
            document.getElementById('join-modal').classList.remove('hidden');
        });
        
        document.getElementById('close-modal').addEventListener('click', function() {
            document.getElementById('join-modal').classList.add('hidden');
        });
        
        // 点击模态框外部关闭
        document.getElementById('join-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
        
        // 团队选择
        function selectTeam(teamId, element) {
            // 移除其他选择
            document.querySelectorAll('[onclick^="selectTeam"]').forEach(el => {
                el.classList.remove('border-primary', 'bg-primary/5');
                const radio = el.querySelector('input[type="radio"]');
                radio.checked = false;
            });
            
            // 添加当前选择
            element.classList.add('border-primary', 'bg-primary/5');
            const radio = element.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // 显示加入按钮
            document.getElementById('join-team-btn').classList.remove('hidden');
        }
        
        // 复制分享链接
        function copyShareLink() {
            const shareLink = document.getElementById('share-link');
            shareLink.select();
            document.execCommand('copy');
            
            // 显示复制成功提示
            const originalValue = shareLink.value;
            shareLink.value = '链接已复制到剪贴板！';
            shareLink.classList.add('bg-green-50', 'text-green-700', 'border-green-300');
            
            setTimeout(() => {
                shareLink.value = originalValue;
                shareLink.classList.remove('bg-green-50', 'text-green-700', 'border-green-300');
            }, 2000);
        }
        
        // 复制团队邀请链接
        function copyTeamInviteLink() {
            // 获取当前页面的完整URL（不包含URL参数）
            const baseUrl = window.location.origin + window.location.pathname;
            const inviteLink = baseUrl + '?id=<?php echo $event_id; ?>&team_id=<?php echo $user_team_id; ?>';
            
            // 创建临时输入框
            const tempInput = document.createElement('input');
            tempInput.value = inviteLink;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            
            // 显示复制成功提示
            alert('邀请链接已复制到剪贴板！');
        }
        
        // 检查是否有团队ID参数，如果有则自动选择团队
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const teamId = urlParams.get('team_id');
            
            if (teamId) {
                // 显示邀请团队区域
                const invitedTeamSection = document.getElementById('invited-team-section');
                const invitedTeamId = document.getElementById('invited-team-id');
                const invitedTeamName = document.getElementById('invited-team-name');
                const invitedTeamInfo = document.getElementById('invited-team-info');
                
                if (invitedTeamSection && invitedTeamId && invitedTeamName && invitedTeamInfo) {
                    invitedTeamSection.style.display = 'block';
                    invitedTeamId.value = teamId;
                    invitedTeamName.textContent = '团队 #' + teamId;
                    
                    // 从后端获取团队的真实信息
                    // 构建正确的URL，保留当前URL中的所有参数
                    const currentUrl = new URL(window.location);
                    currentUrl.searchParams.set('action', 'get_team_info');
                    
                    fetch(currentUrl)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('网络响应错误: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                invitedTeamInfo.textContent = '成员：' + data.member_count + '/' + data.max_members + ' | 创建者：' + data.creator_name;
                            } else {
                                invitedTeamInfo.textContent = '无法获取团队信息';
                            }
                        })
                        .catch(error => {
                            console.error('获取团队信息失败:', error);
                            invitedTeamInfo.textContent = '成员：?/? | 创建者：用户?';
                        });
                }
                
                if (document.getElementById('join-event-btn')) {
                    document.getElementById('join-event-btn').click();
                }
            }
        });
    </script>
    
    <style>
        /* 底部导航栏样式 */
        nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            border-top: 1px solid #eee;
            z-index: 1000;
            box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
        }
        nav ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: space-around;
        }
        nav ul li {
            flex: 1;
            text-align: center;
        }
        nav ul li a {
            display: block;
            padding: 15px 0;
            padding-top: 10px;
            text-decoration: none;
            color: #666;
            font-size: 14px;
            position: relative;
        }
        nav ul li a:hover {
            color: #4CAF50;
        }
        .appointment-container {
            padding-bottom: 80px; /* 为底部导航栏留出空间 */
        }
    </style>
    
    <nav>
        <ul>
            <li><a href="../index.php"><i class="fa fa-calendar-check" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>签到</a></li>
            <li><a href="../shop.php"><i class="fa fa-shopping-bag" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>商品</a></li>
            <li><a href="../messages.php"><i class="fa fa-comment" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>消息</a></li>
            <li><a href="../user_center.php"><i class="fa fa-user" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>我的</a></li>
        </ul>
    </nav>
</body>
</html>