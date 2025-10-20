<?php
// 引入配置和应用核心文件
require_once '../app/config.php';
require_once '../app/app.php';

// 获取专题页slug
$slug = '';
if (isset($_SERVER['PATH_INFO'])) {
    $path_parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    $slug = isset($path_parts[0]) ? $path_parts[0] : '';
}

// 如果没有slug，重定向到首页
if (empty($slug)) {
    header("Location: " . APP_URL);
    exit;
}

// 查询专题页信息
$conn = get_db_connection();
$sql = "SELECT * FROM topics WHERE slug = ? AND status = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$topic = $result->fetch_assoc();
$stmt->close();

// 如果专题页不存在或未启用，显示404页面
if (!$topic) {
    $conn->close();
    http_response_code(404);
    echo "<h1>页面不存在</h1><p>您访问的专题页不存在或已被删除。</p>";
    exit;
}

// 查询专题页项目
$sql = "SELECT * FROM topic_items WHERE topic_id = ? ORDER BY sort_order ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $topic['id']);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}
$stmt->close();
$conn->close();

// 输出专题页内容
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $topic['title']; ?> - 积分系统</title>
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
        /* 专题页专用样式 */
        body {
            background-color: #f9fafb;
        }
        .topic-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .carousel-slide {
            transition: transform 0.5s ease-in-out;
        }
        .banner-gradient {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.9), rgba(99, 102, 241, 0.9));
        }
        .text-gradient {
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            background-image: linear-gradient(90deg, #3b82f6, #6366f1);
        }
    </style>
</head>
<body>
    <!-- 头部导航 -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="topic-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="<?php echo APP_URL; ?>" class="text-primary font-bold text-xl">
                            <i class="fa fa-diamond mr-2"></i>积分系统
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="<?php echo APP_URL; ?>" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium transition-custom">
                        返回首页
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- 专题页内容 -->
    <main class="py-8">
        <div class="topic-container mx-auto px-4 sm:px-6 lg:px-8">
            <!-- 专题页标题 -->
            <div class="text-center mb-10">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4"><?php echo $topic['title']; ?></h1>
                <?php if (!empty($topic['cover_image'])): ?>
                    <div class="mt-4">
                        <img src="<?php echo $topic['cover_image']; ?>" alt="专题页封面" class="mx-auto rounded-lg shadow-lg max-w-full h-auto max-h-60 object-cover">
                    </div>
                <?php endif; ?>
            </div>

            <!-- 专题页内容区 -->
            <div class="bg-white rounded-xl shadow-sm p-6 md:p-8 mb-8">
                <?php echo $topic['content']; ?>
            </div>

            <!-- 专题页项目 -->
            <div class="grid gap-8">
                <?php foreach ($items as $item): ?>
                    <?php if ($item['type'] === 'banner'): ?>
                        <!-- Banner 模块 -->
                        <div class="relative rounded-xl overflow-hidden shadow-md group">
                            <img src="<?php echo !empty($item['image_url']) ? $item['image_url'] : 'https://via.placeholder.com/1200x400?text=Banner+Image'; ?>" 
                                 alt="<?php echo !empty($item['title']) ? $item['title'] : 'Banner'; ?>" 
                                 class="w-full h-64 md:h-80 object-cover transition-custom group-hover:scale-105">
                            <?php if (!empty($item['link_url'])): ?>
                                <a href="<?php echo $item['link_url']; ?>" target="_blank" 
                                   class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-20 opacity-0 group-hover:opacity-100 transition-custom">
                                    <span class="bg-white text-primary px-6 py-3 rounded-full font-medium transform transition-custom group-hover:scale-105">
                                        查看详情
                                    </span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($item['type'] === 'text'): ?>
                        <!-- 文本模块 -->
                        <div class="bg-white rounded-xl shadow-sm p-6 md:p-8">
                            <?php if (!empty($item['title'])): ?>
                                <h2 class="text-2xl font-bold text-gray-900 mb-4"><?php echo $item['title']; ?></h2>
                            <?php endif; ?>
                            <div class="prose max-w-none text-gray-600">
                                <?php echo !empty($item['content']) ? $item['content'] : '暂无文本内容'; ?>
                            </div>
                        </div>
                    <?php elseif ($item['type'] === 'image'): ?>
                        <!-- 图片模块 -->
                        <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                            <?php if (!empty($item['title'])): ?>
                                <h3 class="text-xl font-semibold text-gray-900 mb-4"><?php echo $item['title']; ?></h3>
                            <?php endif; ?>
                            <div class="flex justify-center">
                                <img src="<?php echo !empty($item['image_url']) ? $item['image_url'] : 'https://via.placeholder.com/800x450?text=Image+Content'; ?>" 
                                     alt="<?php echo !empty($item['title']) ? $item['title'] : '图片'; ?>" 
                                     class="rounded-lg shadow-md max-w-full h-auto">
                            </div>
                            <?php if (!empty($item['content'])): ?>
                                <p class="mt-4 text-gray-600"><?php echo $item['content']; ?></p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($item['type'] === 'link'): ?>
                        <!-- 链接模块 -->
                        <div class="bg-white rounded-xl shadow-sm p-6 flex flex-col items-center justify-center text-center">
                            <?php if (!empty($item['title'])): ?>
                                <h3 class="text-xl font-semibold text-gray-900 mb-2"><?php echo $item['title']; ?></h3>
                            <?php endif; ?>
                            <?php if (!empty($item['content'])): ?>
                                <p class="text-gray-600 mb-4 max-w-lg"><?php echo $item['content']; ?></p>
                            <?php endif; ?>
                            <?php if (!empty($item['link_url'])): ?>
                                <a href="<?php echo $item['link_url']; ?>" target="_blank" 
                                   class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-custom">
                                    查看详情
                                    <i class="fa fa-arrow-right ml-2"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($item['type'] === 'carousel'): ?>
                        <!-- 轮播图模块 -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <?php if (!empty($item['title'])): ?>
                                <h3 class="text-xl font-semibold text-gray-900 mb-4 text-center"><?php echo $item['title']; ?></h3>
                            <?php endif; ?>
                            <div class="relative overflow-hidden rounded-lg">
                                <div class="flex transition-transform duration-500 ease-in-out" id="carousel-container">
                                    <?php 
                                        // 这里简化处理，实际项目中可能需要解析content字段获取多个轮播图片
                                        $carousel_images = [
                                            !empty($item['image_url']) ? $item['image_url'] : 'https://via.placeholder.com/1200x400?text=Carousel+1',
                                            'https://via.placeholder.com/1200x400?text=Carousel+2',
                                            'https://via.placeholder.com/1200x400?text=Carousel+3',
                                        ];
                                        foreach ($carousel_images as $carousel_image):
                                    ?>
                                        <div class="carousel-slide min-w-full">
                                            <img src="<?php echo $carousel_image; ?>" 
                                                 alt="轮播图" 
                                                 class="w-full h-64 md:h-80 object-cover">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <!-- 轮播控制按钮 -->
                                <button type="button" id="carousel-prev" class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-70 rounded-full p-2 text-gray-800 transition-custom">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                                <button type="button" id="carousel-next" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-50 hover:bg-opacity-70 rounded-full p-2 text-gray-800 transition-custom">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                                <!-- 轮播指示器 -->
                                <div class="absolute bottom-2 left-0 right-0 flex justify-center gap-2">
                                    <?php for ($i = 0; $i < count($carousel_images); $i++): ?>
                                        <button type="button" class="carousel-indicator w-3 h-3 rounded-full bg-white bg-opacity-50 hover:bg-opacity-70 transition-custom <?php echo $i === 0 ? 'opacity-100' : 'opacity-50'; ?>" data-index="<?php echo $i; ?>"></button>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- 页脚 -->
    <footer class="bg-gray-100 py-6">
        <div class="topic-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center text-gray-600 text-sm">
                <p>&copy; <?php echo date('Y'); ?> 积分系统 - 专题页</p>
            </div>
        </div>
    </footer>

    <!-- 轮播图JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const carouselContainer = document.getElementById('carousel-container');
            const prevButton = document.getElementById('carousel-prev');
            const nextButton = document.getElementById('carousel-next');
            const indicators = document.querySelectorAll('.carousel-indicator');
            
            if (carouselContainer && prevButton && nextButton && indicators.length > 0) {
                let currentIndex = 0;
                const slideCount = indicators.length;
                
                function updateCarousel() {
                    carouselContainer.style.transform = `translateX(-${currentIndex * 100}%)`;
                    indicators.forEach((indicator, index) => {
                        indicator.classList.toggle('opacity-100', index === currentIndex);
                        indicator.classList.toggle('opacity-50', index !== currentIndex);
                    });
                }
                
                prevButton.addEventListener('click', function() {
                    currentIndex = (currentIndex - 1 + slideCount) % slideCount;
                    updateCarousel();
                });
                
                nextButton.addEventListener('click', function() {
                    currentIndex = (currentIndex + 1) % slideCount;
                    updateCarousel();
                });
                
                indicators.forEach((indicator, index) => {
                    indicator.addEventListener('click', function() {
                        currentIndex = index;
                        updateCarousel();
                    });
                });
                
                // 自动轮播
                setInterval(function() {
                    currentIndex = (currentIndex + 1) % slideCount;
                    updateCarousel();
                }, 5000);
            }
        });
    </script>
</body>
</html>