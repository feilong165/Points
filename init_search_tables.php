<?php
// 初始化搜索相关数据表并添加示例数据
require_once 'app/config.php';
require_once 'app/app.php';

// 确保搜索相关表存在
echo "正在创建搜索相关数据表...\n";
ensure_search_tables_exists();
echo "数据表创建成功！\n\n";

// 检查是否已存在推荐搜索数据
$recommended_searches = get_all_recommended_searches();
if (empty($recommended_searches)) {
    echo "正在添加推荐搜索示例数据...\n";
    // 添加一些推荐搜索示例
    add_recommended_search('积分兑换', 1, 1);
    add_recommended_search('新用户专享', 2, 1);
    add_recommended_search('限时活动', 3, 1);
    add_recommended_search('优惠券', 4, 1);
    add_recommended_search('热门商品', 5, 1);
    echo "推荐搜索示例数据添加成功！\n\n";
} else {
    echo "推荐搜索表已有数据，跳过添加示例数据。\n\n";
}

// 检查是否已存在搜索玩法配置数据
$play_configs = get_all_search_play_configs();
if (empty($play_configs)) {
    echo "正在添加搜索玩法配置示例数据...\n";
    // 添加一些搜索玩法配置示例
    add_search_play_config('隐藏活动', 'shop.php?category=hidden', 1);
    add_search_play_config('惊喜奖励', 'user_center.php?section=rewards', 1);
    echo "搜索玩法配置示例数据添加成功！\n\n";
} else {
    echo "搜索玩法配置表已有数据，跳过添加示例数据。\n\n";
}

echo "搜索功能初始化完成！\n";
echo "请访问 search.php 体验搜索功能，或登录后台管理搜索设置。\n";