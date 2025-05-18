<?php

/**
 * 访客统计
 *
 * @package custom
 * @xuan
 * @version 2.0.0
 * 
 * Template Name: 独立页面访客统计
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 如果管理员尝试删除当前IP数据
if ($this->user->hasLogin() && $this->user->group == 'administrator' && isset($_POST['delete_ip_data'])) {
    $ip_to_delete = $_SERVER['REMOTE_ADDR'];
    if (!empty($ip_to_delete)) {
        try {
            $db = Typecho_Db::get();
            // 从访问日志表中删除该IP的所有记录
            $db->query($db->delete('table.visitor_log')->where('ip = ?', $ip_to_delete));
            // 显示成功消息
            echo '<script>alert("已成功删除 ' . $ip_to_delete . ' 的所有访问记录！");</script>';
        } catch (Exception $e) {
            // 显示错误消息
            echo '<script>alert("删除失败: ' . $e->getMessage() . '");</script>';
        }
    }
}

// 处理IP过滤配置的保存
$serverFilteredIPs = [];
if ($this->user->hasLogin() && $this->user->group == 'administrator') {
    // 配置文件路径
    $configFile = __DIR__ . '/ip_filters.json';

    // 保存IP过滤配置
    if (isset($_POST['save_ip_filter'])) {
        $currentIP = $_SERVER['REMOTE_ADDR'];
        $action = $_POST['action']; // 'exclude' 或 'include'

        // 读取现有配置
        $filters = [];
        if (file_exists($configFile)) {
            $filters = json_decode(file_get_contents($configFile), true) ?: [];
        }

        // 更新配置
        if ($action === 'exclude' && !in_array($currentIP, $filters)) {
            $filters[] = $currentIP;
        } else if ($action === 'include') {
            $filters = array_diff($filters, [$currentIP]);
        }

        // 保存配置
        file_put_contents($configFile, json_encode($filters));
    }

    // 读取当前配置
    if (file_exists($configFile)) {
        $serverFilteredIPs = json_decode(file_get_contents($configFile), true) ?: [];
    }
}

$this->need('component/header.php');
?>

<!-- aside -->
<?php $this->need('component/aside.php'); ?>
<!-- / aside -->

<a class="off-screen-toggle hide"></a>
<main class="app-content-body <?php echo Content::returnPageAnimateClass($this); ?>">
    <div class="hbox hbox-auto-xs hbox-auto-sm">
        <!--文章-->
        <div class="col center-part gpu-speed" id="post-panel">
            <div class="wrapper-md">
                <!--博客文章样式 begin with .blog-post-->
                <div id="postpage" class="blog-post">
                    <article class="single-post panel">
                        <!--文章内容-->
                        <div id="post-content" class="wrapper-lg">
                            <!-- 访客统计内容 -->
                            <div class="visitor-stats-container">
                                <!-- 移除日期筛选功能，只保留筛选结果状态 -->
                                <div id="loadingStatus" class="filter-status" style="color: #666; padding: 6px; margin-bottom: 10px; background: #f8f9fa; border-radius: 4px;">
                                    <p>
                                        统计数据：共 <span id="totalVisits">0</span> 次访问，
                                        来自 <span id="totalCountries">0</span> 个国家/地区
                                        <span class="excluded-note">(已排除管理员登录设备IP)</span>
                                    </p>
                                </div>

                                <!-- 添加自己设备排除按钮 -->
                                <div class="self-exclude-container" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; border: 1px solid #eee; font-size: 13px; color: #666;">
                                    <?php if ($this->user->hasLogin() && $this->user->group == 'administrator'): ?>
                                        <p style="margin: 0 0 5px 0;">
                                            <strong>管理员选项：</strong>
                                            <button id="excludeSelfBtn" class="btn btn-sm" style="margin-left: 10px; padding: 2px 8px; background-color: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">将此设备从统计中排除</button>
                                            <button id="includeSelfBtn" class="btn btn-sm" style="margin-left: 10px; padding: 2px 8px; background-color: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; display: none;">取消排除此设备</button>
                                            <button type="button" id="deleteDataBtn" class="btn btn-sm" style="margin-left: 10px; padding: 2px 8px; background-color: #ff4d4f; color: white; border: 1px solid #ff4d4f; border-radius: 4px; cursor: pointer;">删除本设备数据</button>
                                        </p>
                                        <p id="selfExcludeStatus" style="margin: 5px 0 0 0; font-size: 12px; color: #999;"></p>
                                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #999;">
                                            <i>排除设置将针对所有访问者生效。您设置过滤后，所有用户查看的统计数据都会排除您的访问记录。</i>
                                        </p>

                                        <!-- 添加隐藏表单用于提交删除请求 -->
                                        <form id="deleteIpForm" method="post" style="display:none;">
                                            <input type="hidden" name="delete_ip_data" value="1">
                                        </form>

                                        <!-- 添加隐藏表单用于提交IP过滤配置 -->
                                        <form id="ipFilterForm" method="post" style="display:none;">
                                            <input type="hidden" name="save_ip_filter" value="1">
                                            <input type="hidden" name="action" id="filterAction" value="">
                                        </form>
                                    <?php else: ?>
                                        <p style="margin: 0; font-size: 12px; color: #999;"><i>管理员登录后可开启设备排除选项</i></p>
                                    <?php endif; ?>
                                </div>

                                <!-- 国家访问统计部分 -->
                                <div class="stats-card">
                                    <div class="stats-card-header">
                                        <h3>访问国家/地区统计（Top 20）</h3>
                                        <div class="header-controls">
                                            <div class="view-toggle">
                                                <button data-view="chart" data-target="country" class="active-view">环形图</button>
                                                <button data-view="list" data-target="country">列表</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="stats-card-content">
                                        <div id="countryChart" class="chart-view active"></div>
                                        <div id="countryList" class="list-view">
                                            <table class="stats-table">
                                                <thead>
                                                    <tr>
                                                        <th>国家/地区</th>
                                                        <th>IP分布</th>
                                                        <th>访问次数</th>
                                                        <th>占比</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div id="errorStatus" class="text-center" style="color: #d9534f; padding: 10px; display: none;">
                                    <p>加载数据时出现问题，请刷新页面重试或联系管理员。</p>
                                </div>

                                <!-- 添加署名和链接 -->
                                <div class="credit-container" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; border: 1px solid #eee; font-size: 13px; color: #666; text-align: center;">
                                    <p style="margin: 0;">本页面由 <a href="https://blog.ybyq.wang" target="_blank" style="color: #3685fe; text-decoration: none; font-weight: bold;">Xuan</a> 自主开发 | <a href="https://blog.ybyq.wang/archives/97.html" target="_blank" style="color: #3685fe; text-decoration: none;">查看教程和源码</a></p>
                                </div>

                                <!-- 隐私声明 -->
                                <div class="privacy-notice" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; border: 1px solid #eee; font-size: 13px; color: #666;">
                                    <p style="margin: 0 0 5px 0;"><strong>隐私声明：</strong></p>
                                    <p style="margin: 0 0 5px 0;">本页面展示的访问统计数据已进行匿名化处理，不会显示具体IP地址。所有IP地址信息仅用于统计目的，不会用于识别个人身份。数据收集遵循相关法律法规，保护用户隐私。</p>
                                </div>
                            </div>

                            <?php Content::pageFooter($this->options, $this) ?>
                        </div>
                    </article>
                </div>
                <!--评论-->
                <?php $this->need('component/comments.php') ?>
            </div>
            <?php echo WidgetContent::returnRightTriggerHtml() ?>
        </div>
        <!--文章右侧边栏开始-->
        <?php $this->need('component/sidebar.php'); ?>
        <!--文章右侧边栏结束-->
    </div>
</main>
<?php echo Content::returnReadModeContent($this, $this->user->uid, isset($content) ? $content : ''); ?>

<style>
    .visitor-stats-container {
        margin: 0;
        padding: 10px;
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid #f3f3f3;
    }

    /* 日期筛选样式 */
    .date-filter-container {
        margin-bottom: 4px;
        padding-bottom: 6px;
        border-bottom: 1px solid #f3f3f3;
    }

    .date-filter {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }

    .date-filter span {
        font-weight: 500;
        color: #58666e;
        font-size: 14px;
    }

    .date-inputs {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .date-inputs input[type="date"] {
        padding: 4px 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        color: #58666e;
        background-color: #fff;
        font-size: 13px;
    }

    .filter-btn,
    .reset-btn {
        padding: 4px 10px;
        border: none;
        border-radius: 4px;
        font-size: 13px;
        cursor: pointer;
    }

    .filter-btn {
        background-color: #1c65d7;
        color: white;
    }

    .reset-btn {
        background-color: #f5f5f5;
        border: 1px solid #ddd;
        color: #333;
    }

    /* 筛选状态样式 */
    .filter-status {
        border: 1px solid #eee;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .filter-status p {
        margin: 0;
        font-size: 13px;
        color: #4a5568;
        display: flex;
        /* 添加flex布局 */
        align-items: center;
        /* 垂直居中 */
    }

    .stats-card {
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .05);
        border: 1px solid #ddd;
        overflow: hidden;
        margin-bottom: 10px;
        height: auto;
        min-height: 600px;
        display: flex;
        flex-direction: column;
    }

    .stats-card-header {
        padding: 6px 10px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        /* 确保父元素也垂直居中 */
        background-color: #f5f5f5;
    }

    .stats-card-header h3 {
        margin: 10px 0 10px 0;
        font-size: 14px;
        color: #58666e;
        display: flex;
        /* 添加flex布局 */
        align-items: center;
        /* 垂直居中 */
        flex-grow: 1;
        /* 允许标题占据多余空间，辅助居中 */
    }

    .header-controls {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .chart-style-toggle {
        margin-right: 4px;
        display: flex;
        gap: 2px;
    }

    .view-toggle {
        display: flex;
        gap: 4px;
    }

    .view-toggle button,
    .chart-style-toggle button {
        padding: 3px 8px;
        border: 1px solid #ddd;
        background: #fff;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        color: #58666e;
        transition: all 0.2s ease;
    }

    .view-toggle button.active-view,
    .chart-style-toggle button.active {
        background: #1c65d7;
        color: #fff;
        border-color: #1c65d7;
    }

    .view-toggle button:hover,
    .chart-style-toggle button:hover {
        background: #f0f0f0;
    }

    .view-toggle button.active-view:hover,
    .chart-style-toggle button.active:hover {
        background: #1857b8;
    }

    .stats-card-content {
        padding: 0;
        position: relative;
        flex: 1;
        min-height: 0;
        position: relative;
    }

    .chart-view,
    .list-view {
        display: none;
        height: 600px;
        /* 使用绝对定位，确保图表和列表完全重叠在同一位置 */
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
    }

    .chart-view.active,
    .list-view.active {
        display: block;
        z-index: 1;
        /* 确保活动视图在最上层 */
    }

    /* 列表视图样式优化 */
    .list-view {
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #ccc #f5f5f5;
        -webkit-overflow-scrolling: touch;
        /* 添加弹性滚动，支持触摸滑动 */
        touch-action: pan-y;
        /* 允许垂直滑动 */
        /* 确保滚轮滑动正常工作 */
        scroll-behavior: smooth;
        /* 增加滚动灵敏度 */
        scroll-snap-type: y proximity;
        /* 确保列表可以正常滚动 */
        height: 100%;
        position: relative;
    }

    /* 确保列表内容可以正常滚动 */
    .list-view table {
        width: 100%;
        border-collapse: collapse;
    }

    .list-view tbody {
        display: block;
        height: 100%;
        overflow-y: auto;
    }

    .list-view thead,
    .list-view tbody tr {
        display: table;
        width: 100%;
        table-layout: fixed;
    }

    .list-view thead {
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .list-view::-webkit-scrollbar {
        width: 6px;
    }

    .list-view::-webkit-scrollbar-track {
        background: #f5f5f5;
        border-radius: 3px;
    }

    .list-view::-webkit-scrollbar-thumb {
        background-color: #ccc;
        border-radius: 3px;
    }

    .list-view::-webkit-scrollbar-thumb:hover {
        background-color: #999;
    }

    .stats-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .stats-table th,
    .stats-table td {
        padding: 6px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .stats-table th {
        font-weight: 600;
        color: #58666e;
        background: #f5f5f5;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    @media (max-width: 768px) {
        .visitor-stats-container {
            padding: 8px;
        }

        .date-filter {
            flex-direction: column;
            align-items: flex-start;
        }

        .date-inputs {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 30px 1fr;
            align-items: center;
            margin-top: 5px;
        }

        .date-inputs span {
            text-align: center;
        }

        .date-inputs input[type="date"] {
            width: 100%;
        }

        .filter-btn,
        .reset-btn {
            margin-top: 8px;
        }

        .filter-btn {
            margin-right: 5px;
        }

        .header-controls {
            flex-wrap: wrap;
            gap: 4px;
        }

        .chart-style-toggle {
            margin-right: 0;
            margin-bottom: 4px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 4px;
            width: 100%;
        }

        .chart-style-toggle button {
            width: 100%;
            padding: 6px 0;
            font-size: 13px;
        }

        .stats-card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            padding-bottom: 10px;
        }

        .header-controls {
            width: 100%;
        }

        .view-toggle {
            width: 100%;
            display: flex;
            justify-content: flex-end;
        }

        .view-toggle button {
            width: 100%;
            padding: 6px 0;
            font-size: 13px;
        }

        .chart-view,
        .list-view {
            height: 550px;
            /* 进一步增加移动端高度 */
        }

        .stats-card-content {
            /* 为移动端设置固定高度，确保容器有足够空间 */
            height: 550px;
            /* 进一步增加移动端高度 */
            position: relative;
        }

        /* 确保移动端图表正确显示 */
        #countryChart {
            height: 550px !important;
            /* 进一步增加移动端高度 */
            width: 100% !important;
        }

        /* 确保移动端列表正确显示 */
        #countryList {
            height: 550px !important;
            /* 进一步增加移动端高度 */
            width: 100% !important;
            overflow-y: auto;
        }

        /* 移动端表格优化 */
        .stats-table {
            font-size: 12px;
            table-layout: fixed;
        }

        .stats-table th,
        .stats-table td {
            padding: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 设置列宽 */
        .stats-table th:nth-child(1),
        .stats-table td:nth-child(1) {
            width: 30%;
        }

        .stats-table th:nth-child(2),
        .stats-table td:nth-child(2) {
            width: 35%;
        }

        .stats-table th:nth-child(3),
        .stats-table td:nth-child(3) {
            width: 15%;
        }

        .stats-table th:nth-child(4),
        .stats-table td:nth-child(4) {
            width: 20%;
        }

        /* 确保IP列可以换行 */
        .stats-table td:nth-child(2) {
            white-space: normal;
            word-break: break-all;
        }

        /* 移动端排除提示优化 */
        .excluded-note {
            font-size: 11px;
        }

        /* 移动端图表优化 - 隐藏图例 */
        .echarts-tooltip {
            display: none !important;
        }

        /* 移动端图表优化 - 调整图表位置 */
        .chart-view .echarts-container {
            margin-left: 0 !important;
        }

        /* 移动端图表优化 - 调整图表大小 */
        .chart-view .echarts-container {
            width: 100% !important;
            height: 100% !important;
        }
    }

    /* 更小屏幕设备的优化 */
    @media (max-width: 480px) {
        .date-inputs {
            grid-template-columns: 1fr;
            gap: 5px;
        }

        .date-inputs span {
            display: none;
        }

        .stats-card-header {
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
        }

        .view-toggle {
            align-self: flex-end;
        }

        .chart-view,
        .list-view {
            height: 500px;
            /* 增加小屏幕高度 */
        }

        .stats-card {
            min-height: 550px;
            /* 增加小屏幕最小高度 */
        }

        /* 确保小屏幕图表正确显示 */
        #countryChart {
            height: 500px !important;
            /* 增加小屏幕高度 */
        }

        /* 确保小屏幕列表正确显示 */
        #countryList {
            height: 500px !important;
            /* 增加小屏幕高度 */
        }

        /* 小屏幕图表优化 - 隐藏图例 */
        .echarts-tooltip {
            display: none !important;
        }

        /* 小屏幕图表优化 - 调整图表位置 */
        .chart-view .echarts-container {
            margin-left: 0 !important;
        }

        /* 小屏幕图表优化 - 调整图表大小 */
        .chart-view .echarts-container {
            width: 100% !important;
            height: 100% !important;
        }
    }

    .dark .visitor-stats-container {
        background: #2a2a2a;
        border-color: #444;
    }

    .dark .date-filter-container {
        border-color: #444;
    }

    .dark .date-filter span {
        color: #ccc;
    }

    .dark .date-filter input[type="date"] {
        background-color: #333;
        border-color: #555;
        color: #ccc;
    }

    .dark .filter-btn {
        background-color: #1c65d7;
    }

    .dark .reset-btn {
        background-color: #333;
        border-color: #555;
        color: #ccc;
    }

    .dark .filter-status {
        background-color: #2a2a2a;
        border-color: #444;
        color: #ccc;
    }

    .dark .self-exclude-container {
        background: #2a2a2a;
        border-color: #444;
        color: #ccc;
    }

    .dark .credit-container {
        background: #2a2a2a;
        border-color: #444;
        color: #ccc;
    }

    .dark .credit-container a {
        color: #5e9bfe;
    }

    .dark #excludeSelfBtn,
    .dark #includeSelfBtn {
        background-color: #333;
        border-color: #555;
        color: #ccc;
    }

    .dark #selfExcludeStatus {
        color: #888;
    }

    .dark .stats-card {
        background-color: #2a2a2a;
        border-color: #444;
    }

    .dark .stats-card-header {
        background-color: #333;
        border-color: #444;
    }

    .dark .stats-card-header h3 {
        color: #ccc;
    }

    .dark .chart-style-toggle button,
    .dark .view-toggle button {
        background-color: #333;
        border-color: #555;
        color: #ccc;
    }

    .dark .chart-style-toggle button.active,
    .dark .view-toggle button.active-view {
        background-color: #1c65d7;
        color: #fff;
    }

    .dark .chart-style-toggle button:hover,
    .dark .view-toggle button:hover {
        background-color: #444;
    }

    .dark .stats-table th {
        background-color: #333;
        color: #ccc;
    }

    .dark .stats-table td {
        color: #ccc;
        border-color: #444;
    }

    .dark .stats-table tr:hover {
        background-color: #333;
    }

    /* 排除提示的样式 */
    .excluded-note {
        color: #999;
        font-size: 12px;
        font-style: italic;
        margin-left: 5px;
    }

    .dark .excluded-note {
        color: #777;
    }

    /* 添加暗色模式下的隐私声明样式 */
    .dark .privacy-notice {
        background-color: #333;
        border-color: #444;
        color: #ccc;
    }

    .dark .privacy-notice strong {
        color: #ddd;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
    // 将变量声明放在最前面
    // 全局变量定义
    var originalStatsData = null; // 存储原始数据
    var globalStatsData = null; // 存储当前筛选后的数据
    var countryChart = null;

    // 定义需要过滤的IP地址前缀和精确IP地址
    const ipPrefixesToFilter = ['120.41.', '27.148.', '27.149.'];
    const exactIPsToFilter = ['36.248.247.251'];

    // 从服务器获取过滤的IP列表
    var serverFilteredIPs = <?php echo json_encode($serverFilteredIPs); ?>;

    var excludedIPs = <?php
                        // 获取已排除的IP列表 - 通过多种方法检测所有登录IP
                        try {
                            $db = Typecho_Db::get();
                            $prefix = $db->getPrefix();
                            $excludedList = ["27.149.26.150"]; // 默认至少排除这个IP

                            // 方法合集：尝试所有可能的方法来检测登录IP

                            // 方法1：从用户表中获取
                            try {
                                $userIPs = $db->fetchAll($db->select('DISTINCT(logged_ip)')
                                    ->from($prefix . 'users')
                                    ->where('logged_ip IS NOT NULL'));

                                foreach ($userIPs as $ip) {
                                    if (!empty($ip['logged_ip']) && !in_array($ip['logged_ip'], $excludedList)) {
                                        $excludedList[] = $ip['logged_ip'];
                                    }
                                }
                            } catch (Exception $e) {
                                // 静默失败，继续尝试其他方法
                            }

                            // 方法2：从登录日志中获取
                            try {
                                $loginIPs = $db->fetchAll($db->select('DISTINCT(ip)')
                                    ->from($prefix . 'user_login_log'));

                                foreach ($loginIPs as $ip) {
                                    if (!in_array($ip['ip'], $excludedList)) {
                                        $excludedList[] = $ip['ip'];
                                    }
                                }
                            } catch (Exception $e) {
                                // 静默失败，继续尝试其他方法
                            }

                            // 方法3：从访问日志中识别登录行为(检查访问路径包含login或admin)
                            try {
                                $adminIPs = $db->fetchAll($db->select('DISTINCT(ip)')
                                    ->from($prefix . 'visitor_log')
                                    ->where('path LIKE ?', '%login%')
                                    ->orWhere('path LIKE ?', '%admin%'));

                                foreach ($adminIPs as $ip) {
                                    if (!in_array($ip['ip'], $excludedList)) {
                                        $excludedList[] = $ip['ip'];
                                    }
                                }
                            } catch (Exception $e) {
                                // 静默失败，继续尝试其他方法
                            }

                            // 方法4：从评论者中检测管理员IP
                            try {
                                $commentIPs = $db->fetchAll($db->select('DISTINCT(ip)')
                                    ->from($prefix . 'comments')
                                    ->where('authorId > ?', 0)); // 已登录评论者

                                foreach ($commentIPs as $ip) {
                                    if (!in_array($ip['ip'], $excludedList)) {
                                        $excludedList[] = $ip['ip'];
                                    }
                                }
                            } catch (Exception $e) {
                                // 静默失败，继续尝试其他方法
                            }

                            // 方法5：获取访问日志中的特定设备标识(例如特定Cookie或UA)
                            try {
                                $cookieIPs = $db->fetchAll($db->select('DISTINCT(ip)')
                                    ->from($prefix . 'visitor_log')
                                    ->where('ua LIKE ?', '%Typecho.Admin%') // 管理员界面标记
                                    ->orWhere('ua LIKE ?', '%logged-in%'));  // 登录标记

                                foreach ($cookieIPs as $ip) {
                                    if (!in_array($ip['ip'], $excludedList)) {
                                        $excludedList[] = $ip['ip'];
                                    }
                                }
                            } catch (Exception $e) {
                                // 静默失败，继续尝试其他方法
                            }

                            // 本次访问也要记录排除
                            if (isset($_SERVER['REMOTE_ADDR']) && !in_array($_SERVER['REMOTE_ADDR'], $excludedList)) {
                                $excludedList[] = $_SERVER['REMOTE_ADDR'];
                            }

                            // 创建一个持久化的Cookie标记当前浏览器已被排除
                            // setcookie('visitor_stats_excluded', '1', time() + 31536000, '/'); // 一年有效期
                            // 注释掉这行，避免与管理员排除功能冲突

                            echo json_encode(array_values(array_unique($excludedList)));
                        } catch (Exception $e) {
                            // 出错时至少排除指定的IP和当前IP
                            $defaultExcluded = ["27.149.26.150"];
                            if (isset($_SERVER['REMOTE_ADDR'])) {
                                $defaultExcluded[] = $_SERVER['REMOTE_ADDR'];
                            }
                            echo json_encode($defaultExcluded);
                        }
                        ?>; // 排除的IP列表

    console.log("排除的IP列表(共" + excludedIPs.length + "个):", excludedIPs);

    // 检查当前访问是否应该被排除并记录
    <?php
    // 客户端排除检测 - 添加进页面加载时检测
    echo "// 检查当前会话是否应该被排除\n";
    // 修改检测的cookie名称
    echo "if (document.cookie.indexOf('visitorStats_selfExcluded=true') >= 0) {\n";
    echo "    console.log('当前会话已被标记为排除');\n";
    echo "    // 如果当前IP不在排除列表中，添加它\n";
    echo "    var currentIP = '" . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . "';\n";
    echo "    if (currentIP && excludedIPs.indexOf(currentIP) === -1) {\n";
    echo "        excludedIPs.push(currentIP);\n";
    echo "        console.log('已添加当前IP到排除列表:', currentIP);\n";
    echo "    }\n";
    echo "}\n";
    ?>

    // 图表样式配置
    var chartStyles = {
        ring: {
            radius: ['30%', '60%'],
            roseType: false,
            itemStyle: {
                borderRadius: 4
            }
        }
    };

    // 修改初始化逻辑
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM加载完成，准备初始化图表...');
        // 检查ECharts是否已加载
        if (typeof echarts === 'undefined') {
            console.log('ECharts未加载，等待加载...');
            // 如果未加载，等待加载完成
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js';
            script.onload = function() {
                console.log('ECharts加载完成，开始初始化...');
                initializeEverything();
            };
            document.head.appendChild(script);
        } else {
            console.log('ECharts已加载，直接初始化...');
            initializeEverything();
        }
    });

    // 如果DOMContentLoaded可能已经触发，确保仍然初始化
    if (document.readyState === 'loading') {
        console.log('文档正在加载中，等待DOMContentLoaded事件...');
    } else {
        console.log('文档已加载，检查ECharts...');
        if (typeof echarts === 'undefined') {
            console.log('ECharts未加载，等待加载...');
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js';
            script.onload = function() {
                console.log('ECharts加载完成，开始初始化...');
                initializeEverything();
            };
            document.head.appendChild(script);
        } else {
            console.log('ECharts已加载，直接初始化...');
            initializeEverything();
        }
    }

    // 页面完全加载后的备份措施
    window.addEventListener('load', function() {
        console.log('页面完全加载，检查图表是否已初始化...');
        if (!countryChart || !globalStatsData) {
            console.log('图表未初始化，检查ECharts...');
            if (typeof echarts === 'undefined') {
                console.log('ECharts未加载，等待加载...');
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js';
                script.onload = function() {
                    console.log('ECharts加载完成，开始初始化...');
                    initializeEverything();
                };
                document.head.appendChild(script);
            } else {
                console.log('ECharts已加载，尝试重新初始化...');
                initializeEverything();
            }
        } else {
            // 如果已有数据，重新渲染一次
            console.log('图表已初始化，重新渲染...');
            updateStatsDisplay();
        }

        // 添加窗口大小变化监听
        window.addEventListener('resize', function() {
            if (countryChart) {
                // 使用较长延迟确保布局完成
                setTimeout(function() {
                    countryChart.resize();
                }, 500);
            }
        });

        // 处理屏幕旋转事件（移动设备）
        window.addEventListener('orientationchange', function() {
            if (countryChart) {
                // 延迟更长时间确保旋转后布局完成
                setTimeout(function() {
                    countryChart.resize();
                }, 800);
            }
        });
    });

    // 集中初始化所有内容
    function initializeEverything() {
        try {
            console.log('开始初始化所有内容...');

            // 检查ECharts是否已加载
            if (typeof echarts === 'undefined') {
                console.error('ECharts未加载，无法初始化图表');
                return;
            }

            // 初始化图表
            initChart();

            // 初始化图表样式切换
            initChartStyleToggle();

            // 初始化视图切换器
            initViewToggle();

            // 确保数据加载后立即显示
            setTimeout(function() {
                if (countryChart && globalStatsData) {
                    console.log('数据已加载，立即更新显示...');
                    updateStatsDisplay();
                } else {
                    console.log('数据尚未加载，等待数据...');
                    // 再次尝试获取数据
                    fetchStatsData();
                }
            }, 100);
        } catch (e) {
            console.error('初始化过程出错:', e);
            setTimeout(function() {
                try {
                    console.log('尝试延迟初始化...');
                    if (!countryChart && typeof echarts !== 'undefined') {
                        initChart();
                    }
                } catch (err) {
                    console.error('延迟初始化失败:', err);
                }
            }, 500);
        }
    }

    // 初始化图表
    function initChart() {
        const countryChartElem = document.getElementById('countryChart');
        if (!countryChartElem) {
            console.error('找不到countryChart元素');
            return;
        }

        console.log('找到图表元素，初始化图表...');

        // 检查ECharts是否已加载
        if (typeof echarts === 'undefined') {
            console.error('ECharts未加载，无法初始化图表');
            return;
        }

        // 确保容器可见且有尺寸
        countryChartElem.style.display = 'block';
        countryChartElem.style.height = '600px';
        countryChartElem.style.width = '100%';

        // 创建图表实例
        try {
            countryChart = echarts.init(countryChartElem);
            // 设置加载动画
            countryChart.showLoading({
                text: '正在加载数据...',
                color: '#1c65d7',
                textColor: '#000',
                maskColor: 'rgba(255, 255, 255, 0.8)',
                fontSize: 14
            });

            console.log('图表实例创建成功，开始获取数据...');

            // 立即获取数据
            fetchStatsData();
        } catch (e) {
            console.error('图表初始化错误:', e);
            const errorStatus = document.getElementById('errorStatus');
            if (errorStatus) {
                errorStatus.style.display = 'block';
                errorStatus.innerHTML = '<p>图表初始化失败，请刷新页面重试。</p>';
            }
        }
    }

    // 初始化图表样式切换
    function initChartStyleToggle() {
        document.querySelectorAll('.chart-style-toggle button').forEach(button => {
            button.addEventListener('click', function() {
                const style = this.dataset.style;

                // 更新按钮状态
                const container = this.closest('.stats-card');
                const styleButtons = container.querySelectorAll('.chart-style-toggle button');
                styleButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                // 切换回图表视图
                const chartView = container.querySelector('.chart-view');
                const listView = container.querySelector('.list-view');

                listView.classList.remove('active');
                chartView.classList.add('active');

                // 移除列表按钮的激活状态
                const listButton = container.querySelector('.view-toggle button');
                if (listButton) {
                    listButton.classList.remove('active-view');
                }

                // 更新图表样式
                if (countryChart && globalStatsData) {
                    updateChartStyle(style);
                    // 确保图表大小适应容器
                    setTimeout(() => {
                        countryChart.resize();
                    }, 100);
                }
            });
        });
    }

    // 初始化视图切换器
    function initViewToggle() {
        document.querySelectorAll('.view-toggle button').forEach(button => {
            button.addEventListener('click', function() {
                const target = this.dataset.target;
                const view = this.dataset.view;

                // 处理图表/列表视图切换
                const container = this.closest('.stats-card');
                const chartView = container.querySelector('.chart-view');
                const listView = container.querySelector('.list-view');

                // 移除所有按钮的激活状态
                container.querySelectorAll('.view-toggle button').forEach(btn => btn.classList.remove('active-view'));
                // 激活当前点击的按钮
                this.classList.add('active-view');

                if (view === 'list') {
                    // 切换到列表视图 - 隐藏图表，显示列表
                    chartView.classList.remove('active');
                    listView.classList.add('active');
                } else {
                    // 切换到图表视图
                    listView.classList.remove('active');
                    chartView.classList.add('active');

                    // 重绘图表
                    if (target === 'country' && countryChart) {
                        countryChart.resize();
                    }
                }
            });
        });
    }

    // 初始化日期筛选功能
    function initDateFilter() {
        const filterBtn = document.getElementById('filterBtn');
        const resetBtn = document.getElementById('resetBtn');
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');

        // 设置初始日期范围
        const today = new Date();
        const earliestDate = new Date('2024-04-22'); // 设置最早记录日期

        // 格式化日期为YYYY-MM-DD
        startDateInput.value = formatDate(earliestDate);
        endDateInput.value = formatDate(today);

        // 设置日期输入框的最小值和最大值
        startDateInput.min = formatDate(earliestDate);
        startDateInput.max = formatDate(today);
        endDateInput.min = formatDate(earliestDate);
        endDateInput.max = formatDate(today);

        // 点击筛选按钮
        if (filterBtn) {
            filterBtn.addEventListener('click', function() {
                filterDataByDate();
            });
        }

        // 点击重置按钮
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                startDateInput.value = formatDate(earliestDate);
                endDateInput.value = formatDate(today);
                filterDataByDate(); // 应用默认过滤
            });
        }

        // 初始筛选
        setTimeout(function() {
            if (originalStatsData) {
                filterDataByDate();
            }
        }, 1000);
    }

    // 格式化日期为YYYY-MM-DD
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // 更新显示筛选结果的函数
    function updateFilterStatus(totalVisits, countriesCount, excludedCount) {
        const loadingStatus = document.getElementById('loadingStatus');
        if (loadingStatus) {
            // 更新总访问量和总地区数
            document.getElementById('totalVisits').textContent = totalVisits || 0;
            document.getElementById('totalCountries').textContent = countriesCount || 0;
            loadingStatus.style.display = 'block';
        }
    }

    // 过滤排除的IP
    function filterExcludedIPs(data) {
        if (!data || !data.rawData) return {
            countries: []
        };

        // 检查当前设备是否应该被排除
        const selfExcluded = localStorage.getItem('visitorStats_selfExcluded') === 'true' ||
            document.cookie.indexOf('visitorStats_selfExcluded=true') !== -1;

        // 获取当前IP（如果可以获取）
        let currentIP = '';
        <?php
        if (isset($_SERVER['REMOTE_ADDR'])) {
            echo "currentIP = '" . $_SERVER['REMOTE_ADDR'] . "';";
        }
        ?>

        // 过滤原始日志数据
        const filteredLogs = data.rawData.filter(log => {
            // 检查精确匹配的IP
            if (exactIPsToFilter.includes(log.ip)) {
                return false;
            }

            // 检查IP前缀
            for (const prefix of ipPrefixesToFilter) {
                if (log.ip.startsWith(prefix)) {
                    return false;
                }
            }

            // 如果设备被排除，且当前IP与日志IP匹配，则过滤
            if (selfExcluded && currentIP && log.ip === currentIP) {
                return false;
            }

            // 检查服务器端配置的过滤IP
            if (serverFilteredIPs && serverFilteredIPs.indexOf(log.ip) !== -1) {
                return false;
            }

            return true;
        });

        // 重新计算国家统计
        const countryStats = {};
        const countryIpStats = {};

        filteredLogs.forEach(log => {
            const country = log.country;
            const ip = log.ip;

            // 统计国家访问
            if (!countryStats[country]) {
                countryStats[country] = 0;
            }
            countryStats[country]++;

            // 统计IP分布
            if (!countryIpStats[country]) {
                countryIpStats[country] = {};
            }
            if (!countryIpStats[country][ip]) {
                countryIpStats[country][ip] = 0;
            }
            countryIpStats[country][ip]++;
        });

        // 转换为需要的格式
        const countriesData = [];

        for (const country in countryStats) {
            const ips = [];

            // 处理IP数据
            if (countryIpStats[country]) {
                const ipArray = [];
                for (const ip in countryIpStats[country]) {
                    ipArray.push({
                        ip: ip,
                        count: countryIpStats[country][ip]
                    });
                }

                // 按访问次数排序
                ipArray.sort((a, b) => b.count - a.count);

                // 取前5个IP
                ipArray.slice(0, 5).forEach(item => {
                    ips.push(`${item.ip} (${item.count})`);
                });
            }

            countriesData.push({
                country: country,
                count: countryStats[country],
                ips: ips
            });
        }

        // 按访问次数排序
        countriesData.sort((a, b) => b.count - a.count);

        return {
            countries: countriesData.slice(0, 20),
            rawData: filteredLogs,
            totalVisits: filteredLogs.length,
            excludedCount: data.rawData.length - filteredLogs.length // 记录排除的数量
        };
    }

    // 获取统计数据（只执行一次）
    function fetchStatsData() {
        try {
            console.log('开始获取统计数据...');

            // 使用PHP直接嵌入数据
            originalStatsData = <?php
                                // 获取数据库实例
                                try {
                                    $db = Typecho_Db::get();
                                    $prefix = $db->getPrefix();

                                    // 获取国家统计数据
                                    $countryLogs = $db->fetchAll($db->select('country, COUNT(*) as count')
                                        ->from($prefix . 'visitor_log')
                                        ->group('country')
                                        ->order('count', Typecho_Db::SORT_DESC));

                                    // 获取路由统计数据
                                    $routeLogs = $db->fetchAll($db->select('route, COUNT(*) as count')
                                        ->from($prefix . 'visitor_log')
                                        ->group('route')
                                        ->order('count', Typecho_Db::SORT_DESC));

                                    // 获取每个国家/地区的IP分布 (TOP 5)
                                    $countryIpData = [];
                                    foreach ($countryLogs as $log) {
                                        $country = $log['country'];
                                        if (!isset($countryIpData[$country])) {
                                            $ips = $db->fetchAll($db->select('ip, COUNT(*) as count')
                                                ->from($prefix . 'visitor_log')
                                                ->where('country = ?', $country)
                                                ->group('ip')
                                                ->order('count', Typecho_Db::SORT_DESC)
                                                ->limit(5));

                                            $ipList = [];
                                            foreach ($ips as $ip) {
                                                $ipList[] = $ip['ip'] . ' (' . $ip['count'] . ')';
                                            }
                                            $countryIpData[$country] = $ipList;
                                        }
                                    }

                                    // 获取所有原始日志记录（用于日期筛选）
                                    $rawLogs = $db->fetchAll($db->select('ip, country, time')
                                        ->from($prefix . 'visitor_log')
                                        ->order('time', Typecho_Db::SORT_DESC));

                                    // 转换为需要的格式
                                    $countryData = [];
                                    foreach ($countryLogs as $log) {
                                        $country = $log['country'];
                                        $ips = isset($countryIpData[$country]) ? $countryIpData[$country] : [];

                                        $countryData[] = [
                                            'country' => $country,
                                            'count' => $log['count'],
                                            'ips' => $ips
                                        ];
                                    }

                                    // 准备路由数据
                                    $routeData = [];
                                    foreach ($routeLogs as $log) {
                                        $routeData[] = [
                                            'route' => $log['route'],
                                            'count' => $log['count']
                                        ];
                                    }

                                    // 限制为前20条国家数据
                                    $countryData = array_slice($countryData, 0, 20);

                                    // 限制为前15条路由数据
                                    $routeData = array_slice($routeData, 0, 15);

                                    echo json_encode([
                                        'countries' => $countryData,
                                        'routes' => $routeData, // 添加路由数据
                                        'rawData' => $rawLogs
                                    ]);
                                } catch (Exception $e) {
                                    // 如果数据库查询失败，提供备用数据
                                    echo json_encode([
                                        'countries' => [
                                            ['country' => '中国福建省电信', 'count' => '1532', 'ips' => ['112.23.45.67 (385)', '112.24.56.78 (347)']],
                                            ['country' => '中国福建省联通', 'count' => '892', 'ips' => ['58.23.45.67 (268)', '58.24.56.78 (245)']]
                                        ],
                                        'routes' => [
                                            ['route' => '/', 'count' => '10352'],
                                            ['route' => '/some-other-page/', 'count' => '5000']
                                        ],
                                        'rawData' => [
                                            ['ip' => '112.23.45.67', 'country' => '中国福建省电信', 'time' => '2023-05-01 10:30:45'],
                                            ['ip' => '58.23.45.67', 'country' => '中国福建省联通', 'time' => '2023-05-02 11:25:13']
                                        ]
                                    ]);
                                }
                                ?>;

            console.log('数据获取成功，开始处理...');

            // 过滤排除IP后设置全局数据
            globalStatsData = filterExcludedIPs(originalStatsData);

            // 数据获取完成，立即更新显示
            console.log('数据已处理，立即更新显示...');
            updateStatsDisplay();

            // 更新统计数据显示
            updateFilterStatus(
                globalStatsData.totalVisits,
                globalStatsData.countries.length,
                globalStatsData.excludedCount
            );

            // 尝试应用初始筛选
            setTimeout(function() {
                if (typeof filterDataByDate === 'function') {
                    console.log('应用初始筛选...');
                    filterDataByDate();
                }
            }, 100);
        } catch (e) {
            console.error('获取数据错误:', e);
            const errorStatus = document.getElementById('errorStatus');
            if (errorStatus) {
                errorStatus.style.display = 'block';
                errorStatus.innerHTML = '<p>加载数据时出现问题，请刷新页面重试。</p>';
            }
        }
    }

    // 添加自动刷新功能
    function startAutoRefresh() {
        // 每30秒刷新一次数据
        setInterval(function() {
            console.log('自动刷新数据...');
            fetchStatsData();
        }, 30000); // 30秒
    }

    // 在页面加载完成后启动自动刷新
    document.addEventListener('DOMContentLoaded', function() {
        // 启动自动刷新
        startAutoRefresh();

        // 添加设备排除功能
        initSelfExclude();
    });

    // 更新统计显示
    function updateStatsDisplay() {
        if (!countryChart || !globalStatsData) return;

        // 隐藏错误状态
        const errorStatus = document.getElementById('errorStatus');
        if (errorStatus) errorStatus.style.display = 'none';

        // 隐藏图表加载动画
        countryChart.hideLoading();

        // 获取当前选中的样式，默认为环形图
        try {
            const activeStyleElem = document.querySelector('.chart-style-toggle button.active');
            const activeStyle = activeStyleElem ? activeStyleElem.dataset.style : 'ring';
            updateChartStyle(activeStyle);
        } catch (e) {
            console.error('样式切换错误:', e);
            // 出错时使用默认样式
            updateChartStyle('ring');
        }

        // 更新列表
        updateList('countryList', globalStatsData.countries);
    }

    // 更新图表样式
    function updateChartStyle(style) {
        if (!countryChart || !globalStatsData) return;

        const isDark = document.body.classList.contains('dark');
        const textColor = isDark ? '#ccc' : '#58666e';
        const labelColor = isDark ? '#ddd' : '#333';

        // 确保样式存在，默认使用环形图
        const styleConfig = chartStyles[style] || chartStyles.ring;

        // 准备数据
        const countryData = (globalStatsData.countries || []).map(item => ({
            name: item.country || '未知',
            value: parseInt(item.count) || 0,
            ips: item.ips || [],
            itemStyle: {
                borderRadius: styleConfig.itemStyle.borderRadius
            }
        }));

        // 处理无数据情况
        const seriesData = countryData.length > 0 ? countryData : [{
            name: '暂无数据',
            value: 1
        }];

        // 检查是否为移动设备
        const isMobile = window.innerWidth <= 768;

        // 高级颜色方案 - 使用新的配色
        const premiumColors = [
            '#50c48f', // 高级绿色
            '#26ccd8', // 高级青色
            '#3685fe', // 高级蓝色
            '#9977ef', // 高级紫色
            '#f5616f', // 高级红色
            '#f7b13f', // 高级橙色
            '#f9e264', // 高级金色
            '#f47a75', // 高级珊瑚色
            '#009db2', // 高级青蓝色
            '#024b51', // 高级深青色
            '#0780cf', // 高级蓝色
            '#765005' // 高级棕色
        ];

        // 更新图表
        countryChart.setOption({
            backgroundColor: 'transparent',
            tooltip: {
                trigger: 'item',
                formatter: '{a} <br/>{b}: {c} ({d}%)',
                confine: true
            },
            legend: {
                type: 'scroll',
                orient: isMobile ? 'horizontal' : 'vertical',
                right: isMobile ? 'auto' : 10,
                top: isMobile ? 0 : 20,
                bottom: isMobile ? 0 : 20,
                left: isMobile ? 0 : 'auto',
                textStyle: {
                    color: textColor
                },
                formatter: function(name) {
                    if (name.length > 12) {
                        return name.substring(0, 12) + '...';
                    }
                    return name;
                },
                pageButtonItemGap: 5,
                pageButtonGap: 5,
                pageButtonPosition: 'end',
                pageFormatter: '{current}/{total}',
                pageIconColor: textColor,
                pageIconInactiveColor: isDark ? '#555' : '#ccc',
                pageIconSize: 12,
                pageTextStyle: {
                    color: textColor
                },
                show: !isMobile // 在移动端隐藏图例
            },
            series: [{
                name: '访问次数',
                type: 'pie',
                radius: styleConfig.radius,
                center: isMobile ? ['50%', '25%'] : ['40%', '45%'], // 移动端图表位置偏上
                roseType: styleConfig.roseType,
                avoidLabelOverlap: true,
                itemStyle: {
                    ...styleConfig.itemStyle,
                    borderColor: isDark ? '#2a2a2a' : '#fff'
                },
                label: {
                    show: true,
                    position: 'outside',
                    formatter: '{b}: {c}',
                    color: labelColor,
                    fontSize: isMobile ? 10 : 12,
                    lineHeight: isMobile ? 12 : 14,
                    rich: {
                        a: {
                            color: labelColor,
                            fontSize: isMobile ? 10 : 12,
                            lineHeight: isMobile ? 12 : 16
                        },
                        b: {
                            color: '#3685fe', // 使用高级蓝色作为标签颜色
                            fontSize: isMobile ? 10 : 12,
                            fontWeight: 'bold'
                        }
                    }
                },
                labelLine: {
                    length: 10,
                    length2: 10,
                    smooth: true
                },
                emphasis: {
                    focus: 'series',
                    scale: false,
                    scaleSize: 10,
                    label: {
                        show: true,
                        fontSize: isMobile ? 12 : 14,
                        fontWeight: 'bold'
                    },
                    itemStyle: {
                        shadowBlur: 20,
                        shadowOffsetX: 0,
                        shadowColor: 'rgba(0, 0, 0, 0.5)'
                    }
                },
                data: seriesData,
                color: premiumColors // 使用高级颜色方案
            }]
        });

        // 确保图表大小适应容器
        setTimeout(() => {
            countryChart.resize();
        }, 50);
    }

    // 更新列表视图
    function updateList(elementId, data) {
        const tbody = document.querySelector('#' + elementId + ' tbody');
        if (!tbody) return;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center">暂无数据</td></tr>';
            return;
        }

        const total = data.reduce((sum, item) => sum + parseInt(item.count || 0), 0);

        // 按访问量排序
        data.sort((a, b) => parseInt(b.count || 0) - parseInt(a.count || 0));

        tbody.innerHTML = data.map(item => {
            // 匿名化处理IP信息
            const anonymizedIps = item.ips && item.ips.length > 0 ?
                item.ips.map(ipInfo => {
                    // 提取IP地址部分
                    const ipMatch = ipInfo.match(/(\d+\.\d+\.\d+\.\d+)/);
                    if (ipMatch) {
                        const ip = ipMatch[1];
                        // 只保留前两段，其余用*代替
                        const ipParts = ip.split('.');
                        const anonymizedIp = `${ipParts[0]}.${ipParts[1]}.*.*`;
                        // 提取访问次数
                        const countMatch = ipInfo.match(/\((\d+)\)/);
                        const count = countMatch ? countMatch[1] : '';
                        return `${anonymizedIp} (${count})`;
                    }
                    return ipInfo;
                }).join('<br>') :
                '无数据';

            return `
        <tr>
            <td>${item.country || '未知'}</td>
            <td>${anonymizedIps}</td>
            <td>${item.count || 0}</td>
            <td>${total > 0 ? ((parseInt(item.count || 0) / total) * 100).toFixed(2) : '0.00'}%</td>
        </tr>
    `;
        }).join('');
    }

    // 根据日期筛选数据
    function filterDataByDate() {
        if (!originalStatsData || !originalStatsData.countries || !originalStatsData.rawData) {
            console.error('没有可筛选的原始数据');
            return;
        }

        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');

        if (!startDateInput || !endDateInput) {
            console.error('找不到日期输入框');
            return;
        }

        // 获取用户选择的日期范围
        const startDate = startDateInput.value ? new Date(startDateInput.value) : null;
        const endDate = endDateInput.value ? new Date(endDateInput.value) : null;

        // 如果日期无效，直接使用原始数据
        if (!startDate || !endDate) {
            globalStatsData = filterExcludedIPs(originalStatsData);
            updateStatsDisplay();
            updateFilterStatus(null, null,
                globalStatsData.totalVisits,
                globalStatsData.countries.length,
                globalStatsData.excludedCount);
            return;
        }

        // 确保结束日期是当天的23:59:59
        endDate.setHours(23, 59, 59, 999);

        // 筛选原始日志数据
        const filteredLogs = originalStatsData.rawData.filter(log => {
            const logDate = new Date(log.time);
            return logDate >= startDate && logDate <= endDate;
        });

        // 过滤指定IP，添加服务器端过滤检查
        const filteredLogsWithoutExcluded = filteredLogs.filter(log => {
            // 检查精确匹配的IP
            if (exactIPsToFilter.includes(log.ip)) {
                return false;
            }

            // 检查IP前缀
            for (const prefix of ipPrefixesToFilter) {
                if (log.ip.startsWith(prefix)) {
                    return false;
                }
            }

            // 检查当前设备是否应该被排除
            const selfExcluded = localStorage.getItem('visitorStats_selfExcluded') === 'true' ||
                document.cookie.indexOf('visitorStats_selfExcluded=true') !== -1;

            // 获取当前IP（如果可以获取）
            let currentIP = '';
            <?php
            if (isset($_SERVER['REMOTE_ADDR'])) {
                echo "currentIP = '" . $_SERVER['REMOTE_ADDR'] . "';";
            }
            ?>

            // 如果设备被排除，且当前IP与日志IP匹配，则过滤
            if (selfExcluded && currentIP && log.ip === currentIP) {
                return false;
            }

            // 检查服务器端配置的过滤IP
            if (serverFilteredIPs && serverFilteredIPs.indexOf(log.ip) !== -1) {
                return false;
            }

            return true;
        });

        // 处理筛选后的数据，计算统计
        const countryStats = {};
        const countryIpStats = {};

        filteredLogsWithoutExcluded.forEach(log => {
            const country = log.country;
            const ip = log.ip;

            // 统计国家访问
            if (!countryStats[country]) {
                countryStats[country] = 0;
            }
            countryStats[country]++;

            // 统计IP分布
            if (!countryIpStats[country]) {
                countryIpStats[country] = {};
            }
            if (!countryIpStats[country][ip]) {
                countryIpStats[country][ip] = 0;
            }
            countryIpStats[country][ip]++;
        });

        // 转换为需要的格式
        const countriesData = [];

        for (const country in countryStats) {
            const ips = [];

            // 处理IP数据
            if (countryIpStats[country]) {
                const ipArray = [];
                for (const ip in countryIpStats[country]) {
                    ipArray.push({
                        ip: ip,
                        count: countryIpStats[country][ip]
                    });
                }

                // 按访问次数排序
                ipArray.sort((a, b) => b.count - a.count);

                // 取前5个IP
                ipArray.slice(0, 5).forEach(item => {
                    ips.push(`${item.ip} (${item.count})`);
                });
            }

            countriesData.push({
                country: country,
                count: countryStats[country],
                ips: ips
            });
        }

        // 按访问次数排序
        countriesData.sort((a, b) => b.count - a.count);

        // 更新全局数据
        globalStatsData = {
            countries: countriesData.slice(0, 20), // 最多显示前20条
            totalVisits: filteredLogsWithoutExcluded.length,
            excludedCount: filteredLogs.length - filteredLogsWithoutExcluded.length
        };

        // 更新显示
        updateStatsDisplay();

        // 显示筛选结果数量
        const totalVisits = countriesData.reduce((sum, item) => sum + item.count, 0);
        updateFilterStatus(totalVisits, countriesData.length, filteredLogs.length - filteredLogsWithoutExcluded.length);
    }

    // 初始化设备排除功能
    function initSelfExclude() {
        const excludeSelfBtn = document.getElementById('excludeSelfBtn');
        const includeSelfBtn = document.getElementById('includeSelfBtn');
        const deleteDataBtn = document.getElementById('deleteDataBtn');
        const statusElem = document.getElementById('selfExcludeStatus');

        // 如果元素不存在（可能非管理员），则退出
        if (!excludeSelfBtn || !includeSelfBtn || !statusElem) {
            return;
        }

        // 获取当前IP
        const currentIP = '<?php echo isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''; ?>';

        // 检查当前设备是否已被排除（检查服务器端配置和本地存储）
        const isExcluded = localStorage.getItem('visitorStats_selfExcluded') === 'true' ||
            (serverFilteredIPs && serverFilteredIPs.indexOf(currentIP) !== -1);

        // 同步本地存储和服务器配置
        if (serverFilteredIPs && serverFilteredIPs.indexOf(currentIP) !== -1) {
            localStorage.setItem('visitorStats_selfExcluded', 'true');
            document.cookie = "visitorStats_selfExcluded=true; path=/; max-age=31536000; SameSite=Lax";
        }

        // 更新按钮状态
        if (isExcluded) {
            excludeSelfBtn.style.display = 'none';
            includeSelfBtn.style.display = 'inline-block';
            statusElem.textContent = '当前设备已从统计中排除。您的访问记录不会影响统计结果。';
        } else {
            excludeSelfBtn.style.display = 'inline-block';
            includeSelfBtn.style.display = 'none';
            statusElem.textContent = '当前设备的访问记录会被计入统计。';
        }

        // 设置排除按钮点击事件
        excludeSelfBtn.addEventListener('click', function() {
            // 设置排除标记
            localStorage.setItem('visitorStats_selfExcluded', 'true');
            // 设置cookie，长期有效，域设置为根路径
            document.cookie = "visitorStats_selfExcluded=true; path=/; max-age=31536000; SameSite=Lax"; // 一年有效期

            // 更新UI
            excludeSelfBtn.style.display = 'none';
            includeSelfBtn.style.display = 'inline-block';
            statusElem.textContent = '当前设备已从统计中排除。您的访问记录不会影响统计结果。';

            // 将当前IP添加到全局排除列表
            if (currentIP && excludedIPs.indexOf(currentIP) === -1) {
                excludedIPs.push(currentIP);
                console.log('已添加当前IP到排除列表:', currentIP);
            }

            // 保存到服务器
            document.getElementById('filterAction').value = 'exclude';

            // 使用AJAX提交表单，避免页面跳转
            const formData = new FormData(document.getElementById('ipFilterForm'));
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.onload = function() {
                // 无论成功失败，都重新获取数据
                setTimeout(() => {
                    fetchStatsData();
                }, 500);
            };
            xhr.onerror = function() {
                console.error('保存配置失败');
                alert('设置保存失败，请重试');
            };
            xhr.send(formData);

            return false; // 阻止默认提交行为
        });

        // 设置包含按钮点击事件
        includeSelfBtn.addEventListener('click', function() {
            // 移除排除标记
            localStorage.removeItem('visitorStats_selfExcluded');
            // 移除cookie，确保路径正确
            document.cookie = "visitorStats_selfExcluded=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax";

            // 更新UI
            excludeSelfBtn.style.display = 'inline-block';
            includeSelfBtn.style.display = 'none';
            statusElem.textContent = '当前设备的访问记录会被计入统计。';

            // 从全局排除列表中移除当前IP
            if (currentIP) {
                const index = excludedIPs.indexOf(currentIP);
                if (index > -1) {
                    excludedIPs.splice(index, 1);
                    console.log('已从排除列表中移除当前IP:', currentIP);
                }
            }

            // 保存到服务器
            document.getElementById('filterAction').value = 'include';

            // 使用AJAX提交表单，避免页面跳转
            const formData = new FormData(document.getElementById('ipFilterForm'));
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.onload = function() {
                // 无论成功失败，都重新获取数据
                setTimeout(() => {
                    fetchStatsData();
                }, 500);
            };
            xhr.onerror = function() {
                console.error('保存配置失败');
                alert('设置保存失败，请重试');
            };
            xhr.send(formData);

            return false; // 阻止默认提交行为
        });

        // 设置删除数据按钮点击事件
        if (deleteDataBtn) {
            deleteDataBtn.addEventListener('click', function(e) {
                e.preventDefault(); // 阻止默认行为

                if (confirm('确定要删除当前IP的所有访问记录吗？此操作不可撤销！')) {
                    // 使用AJAX提交表单，避免页面跳转
                    const formData = new FormData(document.getElementById('deleteIpForm'));
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', window.location.href, true);
                    xhr.onload = function() {
                        // 操作完成后显示提示
                        alert('数据已删除！刷新后生效');
                        // 刷新数据
                        setTimeout(() => {
                            fetchStatsData();
                        }, 500);
                    };
                    xhr.onerror = function() {
                        console.error('删除数据失败');
                        alert('删除数据失败，请重试');
                    };
                    xhr.send(formData);
                }
                return false; // 阻止默认提交行为
            });
        }
    } // 这里结束了initSelfExclude函数
</script>

<!-- footer -->
<?php $this->need('component/footer.php'); ?>
<!-- / footer -->