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
$success = '';

// 检查是否查看商品详情
$is_product_detail = isset($_GET['product_id']);
$product_detail = null;

// 处理商品详情页
if ($is_product_detail) {
    $product_id = intval($_GET['product_id']);
    $product_detail = get_product_detail($product_id);
    
    if (!$product_detail || !$product_detail['is_active']) {
        $error = '商品不存在或已下架';
        $is_product_detail = false;
    }
}

// 处理兑换请求
if (isset($_POST['exchange'])) {
    $product_id = $_POST['product_id'];
    
    $result = exchange_product($user_id, $product_id);
    if ($result['success']) {
        if (isset($result['code'])) {
            $success = $result['message'] . '，兑换码：<strong>' . $result['code'] . '</strong>（请妥善保存）';
        } else {
            $success = $result['message'];
        }
    } else {
        $error = $result['message'];
    }
}

// 获取用户信息和积分
$user = get_user_info($user_id);

// 检查是否有分类筛选参数
$category_id = isset($_GET['category']) ? intval($_GET['category']) : null;

// 获取商品列表
$products = get_products(true, $category_id);

// 检查是否有定时补货的商品
$conn = get_db_connection();
$now = date('Y-m-d H:i:s');
$check_restock_sql = "SELECT * FROM products WHERE restock_time <= ? AND restock_quantity > 0 AND is_active = 1";
$stmt = $conn->prepare($check_restock_sql);
$stmt->bind_param("s", $now);
$stmt->execute();
$restock_result = $stmt->get_result();

// 执行补货
if ($restock_result->num_rows > 0) {
    $conn->begin_transaction();
    
    try {
        while ($restock_item = $restock_result->fetch_assoc()) {
            // 更新库存
            $update_stock_sql = "UPDATE products SET stock = stock + ?, restock_time = NULL, restock_quantity = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_stock_sql);
            $stmt->bind_param("ii", $restock_item['restock_quantity'], $restock_item['id']);
            $stmt->execute();
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
}

$stmt->close();
$conn->close();

// 重新获取商品列表，确保显示最新的库存信息
if (!empty($success)) {
    $products = get_products();
}

// 获取所有分类
$categories = get_all_categories(1); // 获取所有已上架的分类

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>积分商城 - 积分系统</title>
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
        .content {
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* 移动端响应式调整 */
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
        }

        /* 商品列表样式 */
        .product-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease;
        }

        .product-card-link:hover {
            transform: translateY(-2px);
        }

        .product-card-minimal {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            padding: 10px;
            align-self: start; /* 让商品卡片根据自身内容高度显示，不强制与同行最高卡片等高 */
            position: relative;
        }

        /* 商品卡片标签 */
        .product-card-tag {
            position: absolute;
            top: 8px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(255, 107, 0, 0.9);
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            z-index: 1;
        }

        .product-card-minimal .product-image {
            margin: 0 0 8px 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .product-card-minimal .product-image img {
            max-width: 100%;
            height: auto;
            object-fit: contain;
            border-radius: 4px;
        }

        .product-card-minimal .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            color: #6c757d;
            font-size: 32px;
        }

        .product-card-minimal h3 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #333;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        /* 商品积分样式 */
        .product-card-points {
            font-size: 16px;
            font-weight: bold;
            color: #ff6b00;
            margin-top: 5px;
        }

        .product-card-points .points-value {
            font-size: 18px;
        }

        /* 商品详情页样式 */
        .product-detail {
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
        }

        .back-button {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            font-size: 18px;
            color: #333;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .product-banner {
            width: 100%;
            height: 200px;
            overflow: hidden;
            position: relative;
            background-color: #f8f9fa;
        }
        
        .product-banner img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        /* 搜索框样式 */
        .search-container {
            margin-bottom: 20px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 25px;
            padding: 10px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        
        .search-box i {
            color: #999;
            font-size: 18px;
            margin-right: 10px;
        }
        
        .search-box input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 16px;
            color: #333;
            background-color: transparent;
        }
        
        .search-box input::placeholder {
            color: #999;
        }
        
        .search-box:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: box-shadow 0.3s ease;
        }

        .product-info {
            padding: 20px;
        }

        .product-info h2 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 22px;
            color: #333;
        }

        .product-points-detail {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
        }

        .points-value {
            font-size: 24px;
            color: #ff6b00;
        }

        .product-stock-detail {
            text-align: center;
            margin-bottom: 20px;
            font-size: 16px;
            color: #666;
        }

        .stock-value {
            color: #28a745;
            font-weight: bold;
        }

        .detail-section {
            margin-bottom: 20px;
        }

        .detail-section h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 18px;
            color: #333;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 5px;
        }

        .product-detail-text,
        .product-brief {
            color: #666;
            line-height: 1.6;
            margin: 0;
        }

        .exchange-form-detail {
            margin-top: 20px;
        }

        .btn-exchange-detail {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
        }
        .points-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 10px;
            background-color: #e3f2fd;
            border-radius: 5px;
        }
        .points-info span {
            font-size: 24px;
            font-weight: bold;
            color: #1976d2;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px 16px; /* 第一个值是行间距，第二个值是列间距 */
        }
        .product-card {
            background-color: white;
            border-radius: 12px;
            padding: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
            align-self: start; /* 让商品卡片根据自身内容高度显示，不强制与同行最高卡片等高 */
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        
        /* 商品自定义标签样式 */
        .product-tag {
            display: inline-block;
            background-color: #f0f0f0;
            color: #333;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            margin-top: 8px;
            margin-right: 8px;
        }
        
        /* 底部库存信息样式 */
        .product-stock-bottom {
            text-align: right;
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        /* 轮播图样式 */
        .banner-carousel {
            position: relative;
            width: 100%;
            margin-bottom: 20px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .carousel-slides {
            position: relative;
            width: 100%;
            /* 使用padding-bottom设置2:1的宽高比 */
            padding-bottom: 50%;
            height: 0;
        }
        
        .carousel-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        
        .carousel-slide.active {
            opacity: 1;
        }
        
        .carousel-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }
        
        /* 轮播指示器样式 */
        .carousel-indicators {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        
        .indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: none;
            background-color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .indicator.active {
            background-color: #fff;
        }
        

        
        /* 商品分类导航样式优化 */
        .category-nav {
            margin-bottom: 25px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
        }
        .category-nav::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Edge */
        }
        .category-item {
            display: inline-block;
            padding: 8px 16px;
            margin-right: 15px;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            border-radius: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .category-item.active {
            color: #ff6b6b;
            font-weight: bold;
            background-color: #fff0f0;
        }
        .category-item:hover {
            color: #ff6b6b;
        }
        .product-card h3 {
            margin-top: 0;
            color: #333;
        }
        .product-card .points {
            font-size: 20px;
            font-weight: bold;
            color: #ff9800;
            margin: 10px 0;
        }
        .product-card .stock {
            color: #666;
            margin-bottom: 15px;
        }
        .product-card .description {
            margin-bottom: 20px;
            color: #555;
            height: 60px;
            overflow: hidden;
        }
        .btn {
            display: block;
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .virtual-tag {
            display: inline-block;
            background-color: #9c27b0;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        .top-tag {
            display: inline-block;
            background-color: #f44336;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 5px;
        }
        .error-message {
            color: #f44336;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 3px;
        }
        .success-message {
            color: #4CAF50;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 3px;
        }
        
        /* 商品图片样式 */
        .product-image {
            margin: 0 auto 16px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            max-height: 700px;
            width: 100%;
            max-width: 100%;
            padding: 0;
        }
        
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 4px;
            transition: transform 0.3s ease;
        }
        
        .product-image:hover img {
            transform: scale(1.05);
        }
        
        .no-image {
            text-align: center;
            color: #999;
            padding: 20px;
        }
        
        .no-image i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        /* 库存徽章样式 */
        .stock-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: bold;
        }
        
        /* 定时补货样式 */
        .restock-info {
            background-color: #fff3cd;
            color: #856404;
            padding: 8px 12px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
            text-align: center;
        }
        
        /* 小字体样式 */
        .small-text {
            font-size: 12px;
        }
        
        .countdown-timer {
            background-color: #f8d7da;
            color: #721c24;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 5px;
            margin-top: 5px;
        }
        
        /* 调整商品名称的边距 */
        .product-card h3 {
            margin-top: 15px !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 引入 Font Awesome 图标库 -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!$is_product_detail): ?>
            <!-- 搜索框 -->
            <div class="search-container">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-input" placeholder="搜索商品、活动..." readonly onclick="window.location.href='search.php'">
                </div>
            </div>
            
            <!-- 轮播图 - 仅在商品列表页显示 -->
            <?php
            // 获取所有启用的Banner
            $banners = get_banners(1); // 1表示只获取启用状态的Banner
            if (!empty($banners)): 
            ?>
            <div class="banner-carousel">
                <div class="carousel-slides">
                    <?php foreach ($banners as $index => $banner): ?>
                        <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>">
                            <a href="<?php echo !empty($banner['link_url']) ? htmlspecialchars($banner['link_url']) : '#'; ?>">
                                <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- 轮播指示器 -->
                <div class="carousel-indicators">
                    <?php foreach ($banners as $index => $banner): ?>
                        <button class="indicator <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 商品分类导航 - 仅在商品列表页显示 -->
            <div class="category-nav">
                <div class="category-item <?php echo !isset($_GET['category']) ? 'active' : ''; ?>" data-category-id="">全部商品</div>
                <?php foreach ($categories as $category): ?>
                    <div class="category-item <?php echo isset($_GET['category']) && intval($_GET['category']) === $category['id'] ? 'active' : ''; ?>" data-category-id="<?php echo $category['id']; ?>">
                        <?php echo $category['name']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 当前积分信息 - 仅在商品列表页显示 -->
            <div class="points-info">
                您当前拥有的积分: <span><?php echo $user['points']; ?></span>
            </div>
            <?php endif; ?>
            
            <script>
                // 分类筛选功能
                document.addEventListener('DOMContentLoaded', function() {
                    const categoryItems = document.querySelectorAll('.category-item');
                    
                    categoryItems.forEach(item => {
                        item.addEventListener('click', function() {
                            // 移除所有活动状态
                            categoryItems.forEach(cat => cat.classList.remove('active'));
                            // 添加当前活动状态
                            this.classList.add('active');
                            
                            // 获取分类ID
                            const categoryId = this.getAttribute('data-category-id');
                            
                            // 重新加载页面并传递分类参数
                            if (categoryId) {
                                window.location.href = 'shop.php?category=' + categoryId;
                            } else {
                                // 如果是"全部"分类，移除分类参数
                                window.location.href = 'shop.php';
                            }
                        });
                    });
                });
                
                // Banner轮播功能
                document.addEventListener('DOMContentLoaded', function() {
                    const carousel = document.querySelector('.banner-carousel');
                    if (!carousel) return; // 如果没有轮播图，不执行后续代码
                    
                    const slides = document.querySelectorAll('.carousel-slide');
                    const indicators = document.querySelectorAll('.indicator');
                    
                    let currentSlide = 0;
                    let slideInterval;
                    const slideDuration = 5000; // 5秒切换一次
                    
                    // 显示指定幻灯片
                    function showSlide(index) {
                        // 隐藏所有幻灯片
                        slides.forEach(slide => slide.classList.remove('active'));
                        indicators.forEach(indicator => indicator.classList.remove('active'));
                        
                        // 确保索引在有效范围内
                        if (index >= slides.length) currentSlide = 0;
                        else if (index < 0) currentSlide = slides.length - 1;
                        else currentSlide = index;
                        
                        // 显示当前幻灯片和指示器
                        slides[currentSlide].classList.add('active');
                        indicators[currentSlide].classList.add('active');
                    }
                    
                    // 下一张幻灯片
                    function nextSlide() {
                        showSlide(currentSlide + 1);
                        resetInterval();
                    }
                    
                    // 上一张幻灯片
                    function prevSlide() {
                        showSlide(currentSlide - 1);
                        resetInterval();
                    }
                    
                    // 设置自动轮播
                    function startInterval() {
                        slideInterval = setInterval(nextSlide, slideDuration);
                    }
                    
                    // 重置自动轮播
                    function resetInterval() {
                        clearInterval(slideInterval);
                        startInterval();
                    }
                    
                    // 点击指示器切换幻灯片
                    indicators.forEach((indicator, index) => {
                        indicator.addEventListener('click', () => {
                            showSlide(index);
                            resetInterval();
                        });
                    });
                    
                    // 鼠标悬停时暂停轮播
                    carousel.addEventListener('mouseenter', () => {
                        clearInterval(slideInterval);
                    });
                    
                    // 鼠标离开时继续轮播
                    carousel.addEventListener('mouseleave', startInterval);
                    
                    // 触屏滑动切换功能
                    let touchStartX = 0;
                    let touchEndX = 0;
                    let touchStartTime = 0;
                    let isClick = true; // 默认为点击操作
                    
                    // 触摸开始
                    carousel.addEventListener('touchstart', function(e) {
                        touchStartX = e.changedTouches[0].screenX;
                        touchStartTime = new Date().getTime();
                        isClick = true;
                    });
                    
                    // 触摸移动 - 检测是否为滑动操作
                    carousel.addEventListener('touchmove', function(e) {
                        // 当手指移动超过一定距离时，判定为滑动而不是点击
                        const touchX = e.changedTouches[0].screenX;
                        if (Math.abs(touchX - touchStartX) > 10) {
                            isClick = false;
                        }
                    });
                    
                    // 触摸结束
                    carousel.addEventListener('touchend', function(e) {
                        touchEndX = e.changedTouches[0].screenX;
                        const touchEndTime = new Date().getTime();
                        const touchDuration = touchEndTime - touchStartTime;
                        
                        // 判断是点击还是滑动
                        if (isClick && touchDuration < 200) {
                            // 如果是点击操作且持续时间短于200ms，则不处理滑动
                            // 让链接的点击事件正常触发
                            return;
                        } else {
                            // 否则处理滑动
                            handleSwipe();
                        }
                    });
                    
                    // 处理滑动
                    function handleSwipe() {
                        const swipeThreshold = 50; // 滑动阈值
                        
                        if (touchEndX < touchStartX - swipeThreshold) {
                            // 向左滑动，下一张
                            nextSlide();
                        } else if (touchEndX > touchStartX + swipeThreshold) {
                            // 向右滑动，上一张
                            prevSlide();
                        }
                    }
                    
                    // 开始自动轮播
                    startInterval();
                });
                
                // 倒计时功能
                document.addEventListener('DOMContentLoaded', function() {
                    function updateCountdowns() {
                        const countdownElements = document.querySelectorAll('.countdown-timer');
                        countdownElements.forEach(element => {
                            const restockTimeStr = element.getAttribute('data-restock-time');
                            const restockTime = new Date(restockTimeStr);
                            const now = new Date();
                            const diff = restockTime - now;
                            
                            if (diff <= 0) {
                                // 补货时间已到，刷新页面
                                location.reload();
                                return;
                            }
                            
                            // 计算剩余时分秒
                            const hours = Math.floor(diff / (1000 * 60 * 60));
                            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                            
                            // 显示倒计时
                            const countdownDisplay = element.querySelector('.countdown');
                            if (countdownDisplay) {
                                countdownDisplay.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                            }
                        });
                    }
                    
                    // 立即更新一次倒计时
                    updateCountdowns();
                    
                    // 每秒更新一次倒计时
                    setInterval(updateCountdowns, 1000);
                });
            </script>
            
            
            <?php if ($is_product_detail): ?>
                <!-- 商品详情页 -->
                <div class="product-detail">
                    <button onclick="window.location.href='shop.php'" class="back-button">
                        <i class="fas fa-arrow-left"></i> 
                    </button>
                    
                    <div class="product-info">
                        <!-- 商品图片 -->
                        <div class="product-image">
                            <?php if (!empty($product_detail['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($product_detail['image_path']); ?>" alt="<?php echo htmlspecialchars($product_detail['name']); ?>">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-gift"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 商品名称 -->
                        <h2><?php echo $product_detail['name']; ?></h2>
                        
                        <!-- 所需积分 -->
                        <div class="product-points-detail">
                            <span class="points-value"><?php echo $product_detail['points_required']; ?></span> 积分
                        </div>
                        
                        <!-- 库存信息 -->
                        <div class="product-stock-detail">
                            剩余: <span class="stock-value"><?php echo $product_detail['stock']; ?></span>件
                        </div>
                        
                        <!-- 商品详情 -->
                        <div class="detail-section">
                            <h3>商品详情</h3>
                            <p class="product-detail-text"><?php echo $product_detail['description']; ?></p>
                        </div>
                        
                        <!-- 商品简介 -->
                        <div class="detail-section">
                            <h3>商品简介</h3>
                            <p class="product-brief"><?php echo substr($product_detail['description'], 0, 100) . (strlen($product_detail['description']) > 100 ? '...' : ''); ?></p>
                        </div>
                        
                        <!-- 定时补货信息 -->
                        <?php if (!empty($product_detail['restock_time']) && $product_detail['restock_time'] > date('Y-m-d H:i:s')): ?>
                            <div class="restock-info" style="margin-top: 20px;">
                                <i class="fas fa-clock"></i> 将于 <?php echo date('Y-m-d H:i', strtotime($product_detail['restock_time'])); ?> 开放兑换
                                
                                <!-- 计算剩余时间，小于1小时时显示倒计时 -->
                                <?php 
                                    $now = new DateTime();
                                    $restock_time = new DateTime($product_detail['restock_time']);
                                    $interval = $now->diff($restock_time);
                                    $total_seconds = $interval->s + ($interval->i * 60) + ($interval->h * 3600) + ($interval->days * 86400);
                                ?>
                                
                                <?php if ($total_seconds <= 3600): ?>
                                    <div class="countdown-timer" data-restock-time="<?php echo $product_detail['restock_time']; ?>">
                                        倒计时: <span class="countdown"></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 兑换按钮 -->
                        <?php if ($product_detail['stock'] > 0): ?>
                            <form action="shop.php" method="POST" class="exchange-form-detail">
                                <input type="hidden" name="product_id" value="<?php echo $product_detail['id']; ?>">
                                <button type="submit" name="exchange" class="btn btn-primary btn-exchange-detail">立即兑换</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-disabled btn-exchange-detail">库存不足</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- 商品列表页 -->
                <h2>商品列表</h2>
                <?php if (!empty($products)): ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <a href="shop.php?product_id=<?php echo $product['id']; ?>" class="product-card-link">
                                <div class="product-card-minimal">
                                    <!-- 商品标签 -->
                                    <?php if (!empty($product['custom_tag'])): ?>
                                        <div class="product-card-tag"><?php echo htmlspecialchars($product['custom_tag']); ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="product-image">
                                        <?php if (!empty($product['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fas fa-gift"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="product-card-points">
                                        <span class="points-value"><?php echo $product['points_required']; ?></span> 积分
                                    </div>
                                    
                                    <!-- 显示定时补货信息 -->
                                    <?php if (!empty($product['restock_time']) && $product['restock_time'] > date('Y-m-d H:i:s')): ?>
                                        <div class="restock-info small-text">
                                            <?php echo date('Y-m-d H:i', strtotime($product['restock_time'])); ?> 开放兑换
                                             
                                            <!-- 计算剩余时间，小于1小时时显示倒计时 -->
                                            <?php 
                                                $now = new DateTime();
                                                $restock_time = new DateTime($product['restock_time']);
                                                $interval = $now->diff($restock_time);
                                                $total_seconds = $interval->s + ($interval->i * 60) + ($interval->h * 3600) + ($interval->days * 86400);
                                            ?>
                                             
                                            <?php if ($total_seconds <= 3600): ?>
                                                <div class="countdown-timer" data-restock-time="<?php echo $product['restock_time']; ?>">
                                                    倒计时: <span class="countdown"></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-products">
                        <p>暂无可用商品</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

    </div>
    
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
        nav ul li a.active {
            color: #4CAF50;
        }
        .container {
            padding-bottom: 80px; /* 为底部导航栏留出空间 */
        }
    </style>
    
    <nav>
        <ul>
            <li><a href="index.php"><i class="fas fa-calendar-check" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>签到</a></li>
            <li><a href="shop.php" class="active"><i class="fas fa-shopping-bag" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>商品</a></li>
            <li><a href="messages.php"><i class="fas fa-comment-dots" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>消息</a></li>
            <li><a href="user_center.php"><i class="fas fa-user" style="display: block; font-size: 20px; margin-bottom: 5px;"></i>我的</a></li>
        </ul>
    </nav>
</body>
</html>