<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 检查用户是否登录
session_start();
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
$username = $is_logged_in ? $_SESSION['username'] : '';
$user_points = $is_logged_in ? (isset($_SESSION['points']) ? $_SESSION['points'] : 0) : 0;

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
$sql = "SELECT * FROM special_events WHERE id = ? AND status = 1";
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
    echo "<h1>活动不存在</h1><p>您访问的特别活动不存在或已被删除。</p>";
    exit;
}

// 检查活动时间
$current_time = date('Y-m-d H:i:s');
$is_started = isset($event['start_time']) && strtotime($event['start_time']) <= strtotime($current_time);
$is_ended = isset($event['end_time']) && strtotime($event['end_time']) < strtotime($current_time);

// 检查用户是否已经参与此活动
$has_participated = false;
$participation_time = '';
if ($is_logged_in) {
    $sql = "SELECT participation_time FROM special_event_participants WHERE event_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $has_participated = true;
        $participation_time = $row['participation_time'];
    }
    $stmt->close();
}

// 查询活动参与人数
$sql = "SELECT COUNT(*) as total_participants FROM special_event_participants WHERE event_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$count_result = $stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_participants = $count_row['total_participants'];
$stmt->close();

// 检查是否达到参与上限
$has_reached_limit = isset($event['max_participants']) && $event['max_participants'] > 0 && $total_participants >= $event['max_participants'];

// 检查用户是否满足参与条件
$meets_requirements = true;
$requirement_message = '';
if ($is_logged_in && !empty($event['requirements'])) {
    // 这里简化处理，实际项目中可能需要根据具体需求检查用户是否满足条件
    // 例如：检查用户积分、等级、注册时间等
    $requirements = json_decode($event['requirements'], true);
    if (is_array($requirements)) {
        // 检查积分要求
        if (isset($requirements['min_points']) && $requirements['min_points'] > $user_points) {
            $meets_requirements = false;
            $requirement_message = "需要至少" . $requirements['min_points'] . "积分才能参与活动";
        }
    }
}

$conn->close();

// 处理领取福利请求
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in && !$has_participated && $is_started && !$is_ended && !$has_reached_limit && $meets_requirements) {
    // 领取福利
    $conn = get_db_connection();
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 记录参与信息
        $sql = "INSERT INTO special_event_participants (event_id, user_id, participation_time) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $event_id, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("记录参与信息失败");
        }
        
        // 根据奖励类型发放福利
        if (isset($event['reward_type']) && $event['reward_type'] === 'points' && isset($event['reward_value']) && $event['reward_value'] > 0) {
            // 发放积分
            $sql = "UPDATE users SET points = points + ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $event['reward_value'], $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("发放积分失败");
            }
            
            // 更新session中的积分
            $_SESSION['points'] = $user_points + $event['reward_value'];
            
        } else if (isset($event['reward_type']) && $event['reward_type'] === 'code' && !empty($event['reward_code'])) {
            // 发放兑换码（这里简化处理，实际项目中可能需要从兑换码表中获取未使用的兑换码）
            // 这里直接使用活动配置的兑换码
            $reward_code = $event['reward_code'];
        }
        
        // 提交事务
        $conn->commit();
        
        $message = "福利领取成功！";
        $message_type = "success";
        
        // 重新加载页面以更新状态
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $message = "领取失败：" . $e->getMessage() . "，请稍后重试。";
        $message_type = "error";
    }
    
    $stmt->close();
    $conn->close();
}

?> 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($event['name']) ? $event['name'] : '特别活动'; ?> - 特别活动</title>
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
            .reward-glow {
                animation: glow 2s infinite alternate;
            }
            @keyframes glow {
                from {
                    box-shadow: 0 0 5px rgba(59, 130, 246, 0.5);
                }
                to {
                    box-shadow: 0 0 20px rgba(59, 130, 246, 0.8);
                }
            }
        }
    </style>
    
    <style>
        /* 特别活动页面专用样式 */
        body {
            background-color: #f9fafb;
            background-image: url('data:image/svg+xml,%3Csvg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"%3E%3Cpath d="M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z" fill="%23e5e7eb" fill-opacity="0.4" fill-rule="evenodd"/%3E%3C/svg%3E');
        }
        .event-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .badge-ribbon {
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            overflow: hidden;
        }
        .badge-ribbon::before,
        .badge-ribbon::after {
            position: absolute;
            z-index: -1;
            content: '';
            display: block;
            border: 5px solid #f43f5e;
            border-top-color: transparent;
            border-right-color: transparent;
        }
        .badge-ribbon::before {
            top: 0;
            left: 0;
        }
        .badge-ribbon::after {
            bottom: 0;
            right: 0;
        }
        .badge-ribbon-content {
            position: absolute;
            top: 15px;
            right: -35px;
            transform: rotate(45deg);
            width: 120px;
            background-color: #f43f5e;
            color: white;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            padding: 5px 0;
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="event-container mx-auto px-4 sm:px-6 lg:px-8">
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
                                <i class="fa fa-coins"></i> <?php echo $user_points; ?>
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

    <!-- 主要内容 -->
    <main class="py-8">
        <div class="event-container mx-auto px-4 sm:px-6 lg:px-8">
            <!-- 消息提示 -->
            <?php if (!empty($message)): ?>
                <div class="mb-8 px-6 py-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <div class="flex items-center">
                        <i class="fa fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> text-xl mr-3"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 活动名称 -->
            <div class="mb-6">
                <h1 class="text-3xl md:text-5xl font-bold text-gray-900 mb-2">
                    <?php echo isset($event['name']) ? $event['name'] : '特别活动'; ?>
                </h1>
                <p class="text-lg text-gray-600">
                    <?php echo isset($event['description']) ? $event['description'] : ''; ?>
                </p>
            </div>
            
            <!-- 活动奖励（移到最上方） -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-8 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-primary/10 rounded-full -mr-12 -mt-12"></div>
                <div class="absolute bottom-0 left-0 w-16 h-16 bg-primary/10 rounded-full -ml-8 -mb-8"></div>
                
                <!-- 活动奖励标题和剩余时间 -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 relative z-10">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center">
                        <i class="fa fa-gift text-primary mr-2"></i>活动奖励
                    </h2>
                    
                    <!-- 剩余时间显示在活动奖励文字右边 -->
                    <?php if ($is_started && !$is_ended && isset($event['end_time'])): ?>
                        <div class="mt-3 md:mt-0 bg-primary/10 rounded-lg px-4 py-2">
                            <i class="fa fa-clock-o text-primary mr-2"></i>
                            <span class="text-gray-700 font-medium" id="countdown-text">剩余时间：加载中...</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="space-y-6 relative z-10">
                    <!-- 奖励内容 -->
                    <div class="text-center p-6 bg-gradient-to-br from-primary/5 to-secondary/5 rounded-xl reward-glow">
                        <?php if (isset($event['reward_type']) && $event['reward_type'] === 'points' && isset($event['reward_value'])): ?>
                            <div class="text-5xl font-bold text-primary mb-2">
                                <i class="fa fa-coins"></i> <?php echo $event['reward_value']; ?>
                            </div>
                            <p class="text-gray-700 font-medium">积分奖励</p>
                        <?php else: ?>
                            <div class="text-2xl font-bold text-primary mb-2 truncate">
                                <?php echo !empty($event['reward_code']) ? substr($event['reward_code'], 0, 10) . '...' : '兑换码'; ?>
                            </div>
                            <p class="text-gray-700 font-medium">兑换码奖励</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 奖励说明 -->
                    <?php if (!empty($event['reward_description'])): ?>
                        <div class="text-sm text-gray-600">
                            <h3 class="font-medium text-gray-800 mb-2">奖励说明：</h3>
                            <p><?php echo $event['reward_description']; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- 领取提示 -->
                    <div class="pt-4 border-t border-gray-100">
                        <?php if (!$is_logged_in): ?>
                            <p class="text-sm text-gray-500 mb-4">
                                <i class="fa fa-info-circle text-primary mr-1"></i> 登录后即可领取奖励
                            </p>
                            <a href="../../login.php?return_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="block w-full py-2 bg-primary text-white text-center rounded-lg hover:bg-primary/90 transition-custom">
                                立即登录
                            </a>
                        <?php elseif (!$has_participated && $is_started && !$is_ended && !$has_reached_limit && $meets_requirements): ?>
                            <p class="text-sm text-gray-500 mb-4">
                                <i class="fa fa-bell text-primary mr-1"></i> 点击下方按钮立即领取奖励
                            </p>
                            <form method="POST">
                                <button type="submit" class="w-full py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-custom transform hover:scale-105">
                                    立即领取
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 活动Banner（移除渐变层） -->
            <div class="relative mb-8 rounded-xl overflow-hidden shadow-lg">
                <?php if (!empty($event['banner_image'])): ?>
                    <img src="<?php echo $event['banner_image']; ?>" alt="活动Banner" class="w-full h-64 md:h-80 object-cover">
                <?php else: ?>
                    <div class="w-full h-64 md:h-80 bg-gradient-to-r from-primary to-secondary flex items-center justify-center">
                        <i class="fa fa-gift text-white text-6xl opacity-30"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 领取按钮 -->
            <div class="flex flex-col sm:flex-row gap-4 mb-8 justify-center">
                <?php if ($is_ended): ?>
                    <span class="inline-block px-6 py-3 bg-gray-500 rounded-lg text-white font-medium cursor-not-allowed">
                        活动已结束
                    </span>
                <?php elseif (!$is_started): ?>
                    <span class="inline-block px-6 py-3 bg-gray-500 rounded-lg text-white font-medium cursor-not-allowed">
                        活动未开始
                    </span>
                <?php elseif (!$is_logged_in): ?>
                    <a href="../../login.php?return_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="inline-block px-6 py-3 bg-primary hover:bg-primary/90 rounded-lg text-white font-medium transition-custom">
                        登录领取
                    </a>
                <?php elseif ($has_participated): ?>
                    <span class="inline-block px-6 py-3 bg-green-500 rounded-lg text-white font-medium">
                        已成功领取
                    </span>
                    <span class="inline-block px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium">
                        领取时间：<?php echo date('Y-m-d H:i', strtotime($participation_time)); ?>
                    </span>
                <?php elseif ($has_reached_limit): ?>
                    <span class="inline-block px-6 py-3 bg-gray-500 rounded-lg text-white font-medium cursor-not-allowed">
                        参与人数已满
                    </span>
                <?php elseif (!$meets_requirements): ?>
                    <span class="inline-block px-6 py-3 bg-red-500 rounded-lg text-white font-medium cursor-not-allowed">
                        <?php echo $requirement_message; ?>
                    </span>
                <?php else: ?>
                    <form method="POST">
                        <button type="submit" class="inline-block px-8 py-3 bg-primary hover:bg-primary/90 rounded-lg text-white font-medium transition-custom transform hover:scale-105">
                            立即领取
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- 活动详情和规则 -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- 左侧：活动详情 -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm p-6 md:p-8 mb-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                            <i class="fa fa-info-circle text-primary mr-3"></i>活动详情
                        </h2>
                        
                        <div class="prose max-w-none text-gray-600">
                            <?php echo !empty($event['details']) ? $event['details'] : '暂无活动详细介绍'; ?>
                        </div>
                    </div>
                    
                    <!-- 活动规则 -->
                    <div class="bg-white rounded-xl shadow-sm p-6 md:p-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                            <i class="fa fa-gavel text-primary mr-3"></i>活动规则
                        </h2>
                        
                        <ul class="space-y-4 text-gray-600">
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary/10 flex items-center justify-center text-primary mr-3 mt-0.5">
                                    <i class="fa fa-calendar-check-o text-sm"></i>
                                </div>
                                <div>
                                    <strong class="text-gray-900">活动时间</strong>
                                    <p class="mt-1">
                                        开始：<?php echo isset($event['start_time']) ? date('Y年m月d日 H:i', strtotime($event['start_time'])) : '未设置'; ?><br>
                                        结束：<?php echo isset($event['end_time']) ? date('Y年m月d日 H:i', strtotime($event['end_time'])) : '未设置'; ?>
                                    </p>
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary/10 flex items-center justify-center text-primary mr-3 mt-0.5">
                                    <i class="fa fa-user-o text-sm"></i>
                                </div>
                                <div>
                                    <strong class="text-gray-900">参与对象</strong>
                                    <p class="mt-1">
                                        <?php echo !empty($event['target_audience']) ? $event['target_audience'] : '所有用户'; ?>
                                    </p>
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary/10 flex items-center justify-center text-primary mr-3 mt-0.5">
                                    <i class="fa fa-tasks text-sm"></i>
                                </div>
                                <div>
                                    <strong class="text-gray-900">参与条件</strong>
                                    <p class="mt-1">
                                        <?php echo !empty($event['requirements']) ? 
                                            (is_array(json_decode($event['requirements'], true)) ? 
                                                implode('<br>', array_map(function($key, $value) {
                                                    if ($key === 'min_points') return "至少" . $value . "积分"; 
                                                    return ucfirst($key) . ": " . $value;
                                                }, array_keys(json_decode($event['requirements'], true)), array_values(json_decode($event['requirements'], true)))) : 
                                                $event['requirements']
                                            ) : '无特殊要求'; 
                                        ?>
                                    </p>
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary/10 flex items-center justify-center text-primary mr-3 mt-0.5">
                                    <i class="fa fa-refresh text-sm"></i>
                                </div>
                                <div>
                                    <strong class="text-gray-900">参与次数</strong>
                                    <p class="mt-1">
                                        <?php echo isset($event['participation_limit']) && $event['participation_limit'] > 0 ? '限' . $event['participation_limit'] . '次' : '无限制'; ?>
                                    </p>
                                </div>
                            </li>
                            
                            <li class="flex items-start">
                                <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary/10 flex items-center justify-center text-primary mr-3 mt-0.5">
                                    <i class="fa fa-users text-sm"></i>
                                </div>
                                <div>
                                    <strong class="text-gray-900">参与上限</strong>
                                    <p class="mt-1">
                                        <?php echo isset($event['max_participants']) && $event['max_participants'] > 0 ? '限' . $event['max_participants'] . '人' : '无限制'; ?>
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- 右侧：分享 -->
                <div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fa fa-share-alt text-primary mr-2"></i>分享活动
                        </h2>
                        <div class="flex justify-center space-x-4">
                            <button class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center text-white hover:bg-green-600 transition-custom">
                                <i class="fa fa-weixin"></i>
                            </button>
                            <button class="w-10 h-10 rounded-full bg-red-500 flex items-center justify-center text-white hover:bg-red-600 transition-custom">
                                <i class="fa fa-weibo"></i>
                            </button>
                            <button class="w-10 h-10 rounded-full bg-blue-400 flex items-center justify-center text-white hover:bg-blue-500 transition-custom">
                                <i class="fa fa-qq"></i>
                            </button>
                            <button class="w-10 h-10 rounded-full bg-gray-500 flex items-center justify-center text-white hover:bg-gray-600 transition-custom" onclick="copyShareLink()">
                                <i class="fa fa-link"></i>
                            </button>
                        </div>
                        <div class="mt-4">
                            <input type="text" value="<?php echo APP_URL; ?>/special_event/index.php?id=<?php echo $event_id; ?>" 
                                   readonly class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50" id="share-link">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- 页脚 -->
    <footer class="bg-gray-100 py-6">
        <div class="event-container mx-auto px-4 sm:px-6 lg:px-8">
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // 倒计时功能
        function updateCountdown() {
            const endTime = new Date('<?php echo isset($event['end_time']) ? $event['end_time'] : ''; ?>').getTime();
            const now = new Date().getTime();
            const distance = endTime - now;
            
            if (distance <= 0) {
                document.getElementById('countdown-text').innerText = '活动已结束';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            let countdownString = '剩余时间：';
            if (days > 0) {
                countdownString += days + '天';
            }
            countdownString += hours.toString().padStart(2, '0') + ':';
            countdownString += minutes.toString().padStart(2, '0') + ':';
            countdownString += seconds.toString().padStart(2, '0');
            
            document.getElementById('countdown-text').innerText = countdownString;
        }
        
        // 初始化倒计时
        if (document.getElementById('countdown-text')) {
            updateCountdown();
            setInterval(updateCountdown, 1000);
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
    </script>
</body>
</html>