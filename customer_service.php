<?php
// 客服对话页面

// 引入配置和应用核心文件
require_once 'app/config.php';
require_once 'app/app.php';

// 检查用户是否已登录
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_info = get_user_info($user_id);
$conversations = get_support_conversations($user_id);
$current_conversation = null;
$messages = [];
$new_message = '';
$message_status = '';
$message_success = false;

// 处理创建新对话
if (isset($_POST['create_conversation'])) {
    $content = safe_post('message_content', '', 'string');
    if (!empty($content)) {
        $result = create_support_conversation($user_id, $content);
        if ($result['success']) {
            header("Location: customer_service.php?conversation_id=" . $result['conversation_id']);
            exit;
        } else {
            $message_status = $result['message'];
            $message_success = false;
        }
    } else {
        $message_status = "消息内容不能为空";
        $message_success = false;
    }
}

// 处理发送消息
if (isset($_POST['send_message'])) {
    $conversation_id = safe_post('conversation_id', 0, 'int');
    $content = safe_post('message_content', '', 'string');
    if (!empty($content) && $conversation_id > 0) {
        $result = send_support_message($conversation_id, 'user', $user_id, $content);
        if ($result['success']) {
            // 标记所有消息为已读
            mark_conversation_as_read($conversation_id, 'admin');
            // 刷新页面以显示新消息
            header("Location: customer_service.php?conversation_id=" . $conversation_id);
            exit;
        } else {
            $message_status = $result['message'];
            $message_success = false;
        }
    } else {
        $message_status = "消息内容不能为空或对话ID无效";
        $message_success = false;
    }
}

// 处理选择对话
if (isset($_GET['conversation_id'])) {
    $conversation_id = safe_get('conversation_id', 0, 'int');
    if ($conversation_id > 0) {
        // 获取对话详情
        $conversation_found = false;
        foreach ($conversations as $conv) {
            if ($conv['id'] == $conversation_id) {
                $current_conversation = $conv;
                $conversation_found = true;
                break;
            }
        }
        if ($conversation_found) {
            // 获取对话消息
            $messages = get_conversation_messages($conversation_id);
            // 标记所有管理员消息为已读
            mark_conversation_as_read($conversation_id, 'admin');
        } else {
            $current_conversation = null;
            log_security_event('UNAUTHORIZED_ACCESS_ATTEMPT', 'User ' . $user_id . ' tried to access conversation ' . $conversation_id);
        }
    }
}

// 如果没有选择对话且有对话记录，默认选择最新的一个
if (!$current_conversation && !empty($conversations)) {
    $current_conversation = $conversations[0];
    $messages = get_conversation_messages($current_conversation['id']);
    mark_conversation_as_read($current_conversation['id'], 'admin');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客服中心 - 积分系统</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: #4CAF50;
            color: white;
            padding: 15px 0;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .chat-container {
            display: flex;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .conversation-list {
            width: 300px;
            border-right: 1px solid #eee;
            overflow-y: auto;
        }
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .conversation-item:hover {
            background-color: #f5f5f5;
        }
        .conversation-item.active {
            background-color: #e8f5e9;
        }
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .conversation-title {
            font-weight: bold;
            color: #333;
        }
        .conversation-time {
            font-size: 12px;
            color: #999;
        }
        .conversation-preview {
            color: #666;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .unread-badge {
            background-color: #f44336;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 600px;
        }
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            background-color: #f9f9f9;
        }
        .chat-header h3 {
            margin: 0;
            color: #333;
        }
        .message-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #fafafa;
        }
        .message {
            margin-bottom: 15px;
        }
        .message.user {
            text-align: right;
        }
        .message-content {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 18px;
            max-width: 70%;
            word-wrap: break-word;
            line-height: 1.5;
        }
        .message.user .message-content {
            background-color: #4CAF50;
            color: white;
            border-bottom-right-radius: 5px;
        }
        .message.admin .message-content {
            background-color: white;
            color: #333;
            border: 1px solid #eee;
            border-bottom-left-radius: 5px;
        }
        .message-time {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .message-input-area {
            padding: 20px;
            border-top: 1px solid #eee;
            background-color: white;
        }
        .message-input {
            display: flex;
            gap: 10px;
        }
        .message-input textarea {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: none;
            font-family: inherit;
            font-size: 14px;
            min-height: 80px;
        }
        .message-input button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            align-self: flex-end;
        }
        .message-input button:hover {
            background-color: #45a049;
        }
        .no-conversation {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
        }
        .no-conversation-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .create-conversation-btn {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }
        .status-message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .status-message.success {
            background-color: #e8f5e9;
            color: #4CAF50;
        }
        .status-message.error {
            background-color: #ffebee;
            color: #f44336;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 15px;
            color: #4CAF50;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .close-conversation {
            float: right;
            font-size: 12px;
            color: #666;
            cursor: pointer;
        }
        .close-conversation:hover {
            color: #f44336;
        }
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
        nav ul li a.active {
            color: #4CAF50;
        }
        .container {
            padding-bottom: 80px; /* 为底部导航栏留出空间 */
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>积分系统 - 客服中心</h1>
        </header>
        
        <a href="messages.php" class="back-link"><i class="fas fa-arrow-left"></i> 返回消息中心</a>
        
        <!-- 状态消息 -->
        <?php if (!empty($message_status)): ?>
            <div class="status-message <?php echo $message_success ? 'success' : 'error'; ?>">
                <?php echo $message_status; ?>
            </div>
        <?php endif; ?>
        
        <div class="chat-container">
            <!-- 对话列表 -->
            <div class="conversation-list">
                <div style="padding: 15px; background-color: #f9f9f9; border-bottom: 1px solid #eee;">
                    <h3 style="margin: 0; font-size: 16px;">我的对话</h3>
                </div>
                
                <?php if (empty($conversations)): ?>
                    <div style="padding: 20px; text-align: center; color: #999;">
                        暂无对话记录
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <a href="customer_service.php?conversation_id=<?php echo $conversation['id']; ?>" style="text-decoration: none;">
                            <div class="conversation-item <?php echo $current_conversation && $current_conversation['id'] == $conversation['id'] ? 'active' : ''; ?>">
                                <div class="conversation-header">
                                    <div class="conversation-title">
                                        客服
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversation-time"><?php echo date('H:i', strtotime($conversation['updated_at'])); ?></div>
                                </div>
                                <div class="conversation-preview">
                                    <?php echo !empty($conversation['last_message']) ? $conversation['last_message'] : '暂无消息'; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- 聊天区域 -->
            <div class="chat-area">
                <?php if ($current_conversation): ?>
                    <div class="chat-header">
                        <h3>与客服对话<?php echo $current_conversation['is_closed'] ? ' (已关闭)' : ''; ?></h3>
                    </div>
                    
                    <div class="message-container">
                        <?php if (empty($messages)): ?>
                            <div class="no-conversation">
                                <div class="no-conversation-icon">💬</div>
                                <p>暂无消息记录</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <!-- 确保用户消息正确显示，即使数据库中的sender_type有问题 -->
                                <?php 
                                // 如果sender_id等于当前用户的ID，则强制设置为user类型
                                $display_sender_type = ($message['sender_id'] == $user_id) ? 'user' : 'admin';
                                ?>
                                <div class="message <?php echo $display_sender_type; ?>">
                                    <div class="message-content">
                                        <?php echo $message['content']; ?>
                                    </div>
                                    <div class="message-time">
                                        <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$current_conversation['is_closed']): ?>
                        <div class="message-input-area">
                            <form method="post">
                                <input type="hidden" name="conversation_id" value="<?php echo $current_conversation['id']; ?>">
                                <div class="message-input">
                                    <textarea name="message_content" placeholder="请输入您想咨询的问题..."></textarea>
                                    <button type="submit" name="send_message">发送</button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="message-input-area" style="text-align: center; background-color: #f9f9f9; padding: 30px 20px;">
                            <p style="color: #999; margin-bottom: 20px;">对话已关闭，无法发送新消息</p>
                            <form method="post" style="width: 100%;">
                                <textarea name="message_content" placeholder="请输入您的新问题..." style="width: 100%; min-height: 80px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; margin-bottom: 15px;"></textarea>
                                <button type="submit" name="create_conversation" class="create-conversation-btn">
                                    <i class="fas fa-plus-circle"></i> 开始新对话
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="message-container">
                        <div class="no-conversation">
                            <div class="no-conversation-icon">💬</div>
                            <p>欢迎使用在线客服</p>
                            <p style="margin-bottom: 0;">请创建一个新对话开始咨询</p>
                            <form method="post" style="margin-top: 20px; width: 80%;">
                                <textarea name="message_content" placeholder="请输入您想咨询的问题..." style="width: 100%; min-height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit;"></textarea>
                                <button type="submit" name="create_conversation" class="create-conversation-btn">
                                    开始对话
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-home" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>首页</a></li>
            <li><a href="shop.php"><i class="fas fa-shopping-bag" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>商品</a></li>
            <li><a href="messages.php"><i class="fas fa-comment-dots" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>消息</a></li>
            <li><a href="user_center.php"><i class="fas fa-user" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>我的</a></li>
        </ul>
    </nav>
    
    <script>
        // 自动滚动到最新消息
        window.onload = function() {
            const messageContainer = document.querySelector('.message-container');
            if (messageContainer) {
                messageContainer.scrollTop = messageContainer.scrollHeight;
            }
        };
    </script>
</body>
</html>