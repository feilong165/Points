<?php
// 引入配置和应用核心文件
require_once 'app/config.php';
require_once 'app/app.php';

// 检查用户是否登录
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$search_query = '';
$search_results = [];

// 确保搜索相关表存在
ensure_search_tables_exists();

// 处理搜索请求
if (isset($_POST['search'])) {
    $search_query = trim($_POST['search_query']);
    
    if (!empty($search_query)) {
        // 记录搜索历史
        record_search_history($user_id, $search_query);
        
        // 检查是否有搜索玩法配置
        $play_config = get_search_play_config($search_query);
        
        if ($play_config && !empty($play_config['target_url'])) {
            // 有玩法配置，跳转到指定页面
            header("Location: " . $play_config['target_url']);
            exit;
        } else {
            // 没有玩法配置，执行正常搜索
            $search_results = search_products_and_coupons($search_query);
        }
    } else {
        $error = '请输入搜索内容';
    }
}

// 获取搜索历史
$search_history = get_search_history($user_id);

// 获取推荐搜索内容
$recommended_searches = get_recommended_searches();

// 获取用户信息
$user = get_user_info($user_id);

// 清空搜索历史
if (isset($_POST['clear_history'])) {
    clear_search_history($user_id);
    $search_history = [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜索 - 积分系统</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 80px; /* 为底部导航栏留出空间 */
        }
        header {
            background-color: #4CAF50;
            color: white;
            padding: 15px 0;
            text-align: center;
            border-radius: 5px;
            margin-bottom: 30px;
            position: relative;
        }
        .back-btn {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            text-decoration: none;
            font-size: 20px;
        }
        .search-header {
            position: relative;
            margin-bottom: 20px;
        }
        .search-bar {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 25px;
            padding: 10px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .search-bar i {
            color: #999;
            font-size: 18px;
            margin-right: 10px;
        }
        .search-bar input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 16px;
            color: #333;
        }
        .search-bar button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 16px;
        }
        .search-bar button:hover {
            background-color: #45a049;
        }
        .search-section {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .clear-btn {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 14px;
        }
        .clear-btn:hover {
            color: #4CAF50;
        }
        .search-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .search-tag {
            background-color: #f0f0f0;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            color: #666;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .search-tag:hover {
            background-color: #e0e0e0;
            color: #333;
        }
        .search-tag.active {
            background-color: #4CAF50;
            color: white;
        }
        .recommended-tag {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .recommended-tag:hover {
            background-color: #bbdefb;
        }
        .search-results {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .result-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .result-item:last-child {
            border-bottom: none;
        }
        .result-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
        }
        .result-info {
            flex: 1;
        }
        .result-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        .result-desc {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        .result-meta {
            font-size: 14px;
            color: #999;
        }
        .points {
            color: #f44336;
            font-weight: bold;
        }
        .no-results {
            text-align: center;
            padding: 40px;
            color: #999;
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
            text-decoration: none;
            color: #666;
            font-size: 14px;
        }
        nav ul li a.active {
            color: #4CAF50;
        }
        nav ul li a i {
            display: block;
            font-size: 20px;
            margin-bottom: 5px;
        }
        nav ul li a span {
            display: block;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="shop.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <h1>搜索</h1>
        </header>
        
        <!-- 搜索表单 -->
        <div class="search-header">
            <form method="post" class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" name="search_query" placeholder="搜索商品、活动..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" name="search">搜索</button>
            </form>
        </div>
        
        <!-- 搜索结果 -->
        <?php if (!empty($search_query)): ?>
            <div class="search-results">
                <div class="section-title">
                    <span>搜索结果："<?php echo htmlspecialchars($search_query); ?>"</span>
                </div>
                
                <?php if (!empty($search_results)): ?>
                    <?php foreach ($search_results as $result): ?>
                        <div class="result-item">
                            <img src="<?php echo !empty($result['image_url']) ? htmlspecialchars($result['image_url']) : 'https://via.placeholder.com/80x80'; ?>" alt="<?php echo htmlspecialchars($result['title']); ?>" class="result-image">
                            <div class="result-info">
                                <div class="result-title">
                                    <a href="<?php echo $result['type'] === 'product' ? 'shop.php?product_id=' . $result['id'] : '#'; ?>" style="color: inherit; text-decoration: none;">
                                        <?php echo htmlspecialchars($result['title']); ?>
                                    </a>
                                </div>
                                <div class="result-desc"><?php echo htmlspecialchars($result['description']); ?></div>
                                <div class="result-meta">
                                    <?php if ($result['type'] === 'product'): ?>
                                        <span class="points"><?php echo $result['points']; ?> 积分</span>
                                    <?php else: ?>
                                        <span>优惠券</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-search fa-5x" style="margin-bottom: 20px; color: #ddd;"></i>
                        <p>未找到相关内容</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- 搜索历史 -->
            <?php if (!empty($search_history)): ?>
                <div class="search-section">
                    <div class="section-title">
                        <span>搜索历史</span>
                        <form method="post" style="display: inline;">
                            <button type="submit" name="clear_history" class="clear-btn">清空</button>
                        </form>
                    </div>
                    <div class="search-tags">
                        <?php foreach ($search_history as $history): ?>
                            <div class="search-tag" onclick="document.getElementsByName('search_query')[0].value='<?php echo htmlspecialchars($history['query']); ?>'; document.getElementsByName('search')[0].click();">
                                <?php echo htmlspecialchars($history['query']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 推荐搜索 -->
            <?php if (!empty($recommended_searches)): ?>
                <div class="search-section">
                    <div class="section-title">
                        <span>推荐搜索</span>
                    </div>
                    <div class="search-tags">
                        <?php foreach ($recommended_searches as $item): ?>
                            <div class="search-tag recommended-tag" onclick="document.getElementsByName('search_query')[0].value='<?php echo htmlspecialchars($item['keyword']); ?>'; document.getElementsByName('search')[0].click();">
                                <?php echo htmlspecialchars($item['keyword']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <nav>
            <ul>
                <li><a href="index.php"><i class="fas fa-home"></i><span>首页</span></a></li>
                <li><a href="shop.php"><i class="fas fa-shopping-bag"></i><span>商品</span></a></li>
                <li><a href="messages.php"><i class="fas fa-comment-dots"></i><span>消息</span></a></li>
                <li><a href="user_center.php"><i class="fas fa-user"></i><span>我的</span></a></li>
            </ul>
        </nav>
    </div>
</body>
</html>