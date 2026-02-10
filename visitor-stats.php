<?php

/**
 * 访客统计
 *
 * @package custom
 * @xuan
 * @version 2.0.4
 * 
 * Template Name: 独立页面访客统计
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 如果管理员尝试删除当前IP数据
if ($this->user->hasLogin() && $this->user->group == 'administrator' && isset($_POST['delete_ip_data'])) {
    // 获取真实IP，优先使用CDN转发的IP
    $ip_to_delete = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? 
        explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : 
        (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    
    $response = ['success' => false, 'message' => '未知错误'];
    if (!empty($ip_to_delete)) {
        try {
            // 根据Typecho版本选择正确的方式获取Db实例
            if (class_exists('\\Typecho\\Db')) {
                $db = \Typecho\Db::get();
            } else if (class_exists('Typecho_Db')) {
                $db = Typecho_Db::get();
                $prefix = $db->getPrefix();
            } else {
                throw new Exception('无法获取数据库连接');
            }
            
            // 从访问日志表中删除该IP的所有记录
            $db->query($db->delete('table.visitor_log')->where('ip = ?', $ip_to_delete));
            $response = ['success' => true, 'message' => '已成功删除您当前IP（' . htmlspecialchars($ip_to_delete) . '）的所有访问记录！'];
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => '删除失败: ' . $e->getMessage()];
        }
    } else {
        $response = ['success' => false, 'message' => '无法获取当前IP地址'];
    }
    // 返回JSON响应
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 处理IP过滤配置的保存
$serverFilteredIPs = [];
if ($this->user->hasLogin() && $this->user->group == 'administrator') {
    // 配置文件路径
    $configFile = __DIR__ . '/ip_filters.json';

    // 保存IP过滤配置
    if (isset($_POST['save_ip_filter'])) {
        // 获取真实IP，优先使用CDN转发的IP
        $currentIP = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? 
            explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : 
            (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
        
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

<!-- 智能加载ECharts：优先CDN，失败时自动回退到本地 -->
<script>
// 添加超时机制的脚本加载函数
function loadScriptWithTimeout(src, timeout = 2000) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            reject(new Error('Timeout'));
        }, timeout);

        const script = document.createElement('script');
        script.src = src;
        script.onload = () => {
            clearTimeout(timer);
            resolve();
        };
        script.onerror = () => {
            clearTimeout(timer);
            reject(new Error('Failed to load'));
        };
        document.head.appendChild(script);
    });
}

// 优化后的ECharts加载函数
function loadEChartsWithFallback() {
    return new Promise((resolve, reject) => {
        // 优先尝试CDN，2秒超时
        loadScriptWithTimeout('https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js', 2000)
        .then(() => {
            console.log('✅ ECharts CDN加载成功');
            resolve('cdn');
        })
        .catch(() => {
            console.warn('⚠️ ECharts CDN加载超时或失败，立即尝试本地文件');
            // CDN失败，立即加载本地文件
            const localScript = document.createElement('script');
            localScript.src = '../usr/plugins/VisitorLoggerPro/js/echarts.min.js';
        localScript.onload = () => {
            console.log('✅ ECharts 本地文件加载成功');
            resolve('local');
        };
        localScript.onerror = () => {
            console.error('❌ ECharts 本地文件加载失败');
            reject('both_failed');
        };
        document.head.appendChild(localScript);
        });
    });
}

// 立即开始加载
loadEChartsWithFallback().then(result => {
    console.log('📊 ECharts加载完成:', result);
    // 设置全局标记，表示ECharts已准备就绪
    window.echartsReady = true;
    // 触发应用初始化（如果需要）
    if (typeof window.startTrendInitialization === 'function') {
        window.startTrendInitialization();
    }
}).catch(error => {
    console.error('❌ ECharts加载完全失败:', error);
    window.echartsReady = false;
    // 可选：显示错误提示
    const errorDiv = document.createElement('div');
    errorDiv.innerHTML = '<div style="color: red; padding: 10px; background: #ffebee; border: 1px solid #ff8a8a;">ECharts加载失败，请检查网络或联系管理员</div>';
document.body.appendChild(errorDiv);
});
</script>

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
                                <!-- 日期筛选 -->
                                <div class="date-filter-container">
                                    <div class="date-filter">
                                        <span>日期范围：</span>
                                        <div class="date-inputs">
                                            <input type="date" id="startDate">
                                            <span>-</span>
                                            <input type="date" id="endDate">
                                        </div>
                                        <div class="date-buttons">
                                            <button id="filterBtn" class="filter-btn">查询</button>
                                            <button id="resetBtn" class="date-btn">今天</button>
                                            <button id="last7DaysBtn" class="date-btn">最近7天</button>
                                            <button id="last30DaysBtn" class="date-btn">最近30天</button>
                                            <button id="allTimeBtn" class="date-btn">全部</button>
                                        </div>
                                    </div>
                                </div>
                                <div id="loadingStatus" class="filter-status" style="color: #666; padding: 6px; margin-bottom: 10px; background: #f8f9fa; border-radius: 4px;">
                                    <p>
                                        统计数据：共 <span id="totalVisits">0</span> 次访问，
                                        来自 <span id="totalCountries">0</span> 个国家/地区
                                        <span class="excluded-note">(已排除管理员登录设备IP)</span>
                                    </p>
                                    <!-- <p style="margin: 0; color: #ff4d4f; font-size: 13px;">注：此版本需要手动刷新页面才有数据显示</p> -->
                                </div>

                                <!-- 调试信息区域 -->
                                <div id="debugInfo" style="margin-bottom: 10px; padding: 6px; background: #f8f9fa; border-radius: 4px; border: 1px dashed #ddd; color: #666; font-size: 12px; display: none;">
                                    <p style="margin: 0;"><strong>图表加载状态：</strong> <span id="chartLoadStatus">未初始化</span></p>
                                    <p style="margin: 0;"><strong>数据加载状态：</strong> <span id="dataLoadStatus">未加载</span></p>
                                    <p style="margin: 0;"><strong>加载尝试次数：</strong> <span id="loadAttempts">0</span></p>
                                    <button id="toggleDebug" style="margin-top: 5px; padding: 2px 5px; font-size: 11px; background: #eee; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">隐藏调试信息</button>
                                    <button id="forceReload" style="margin-top: 5px; margin-left: 5px; padding: 2px 5px; font-size: 11px; background: #1c65d7; color: white; border: 1px solid #1c65d7; border-radius: 3px; cursor: pointer;">强制重新加载</button>
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
                                        <p style="margin: 0; font-size: 12px; color: #999;"><i>管理员登录后可开启本设备排除或删除选项</i></p>
                                    <?php endif; ?>
                                </div>

                                <!-- 添加标签页切换控件 -->
                                <div class="stats-tabs">
                                    <button class="tab-btn active" data-tab="country">国家/地区统计</button>
                                    <button class="tab-btn" data-tab="province">省份统计（中国）</button>
                                </div>

                                <!-- 国家访问统计部分 -->
                                <div id="countryTab" class="stats-tab-content active">
                                    <div class="stats-card">
                                        <div class="stats-card-header">
                                            <h3>访问国家/地区统计（Top 30）</h3>
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
                                                            <th>访问次数</th>
                                                            <th>占比</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 省份访问统计部分 -->
                                <div id="provinceTab" class="stats-tab-content">
                                    <div class="stats-card">
                                        <div class="stats-card-header">
                                            <h3>省份访问统计（中国）</h3>
                                            <div class="header-controls">
                                                <div class="view-toggle">
                                                    <button data-view="chart" data-target="province" class="active-view">环形图</button>
                                                    <button data-view="list" data-target="province">列表</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="stats-card-content">
                                            <div id="provinceChart" class="chart-view active"></div>
                                            <div id="provinceList" class="list-view">
                                                <table class="stats-table">
                                                    <thead>
                                                        <tr>
                                                            <th>省份/地区</th>
                                                            <th>访问次数</th>
                                                            <th>占比</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
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
        min-height: 800px;
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

    #post-content h1,
    #post-content h2,
    #post-content h3,
    #post-content h4,
    #post-content h5,
    #post-content h6 {
        margin: 10px 0 10px 0;
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
        height: 920px;
        /* 进一步增加图表高度 */
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

    .stats-card {
        height: 100% !important;
    }

    .date-buttons button.active {
        background-color: #1c65d7;
        color: white;
        border-color: #1c65d7;
    }

    .dark .date-buttons button.active {
        background-color: #1c65d7;
        border-color: #1c65d7;
    }

    .date-buttons {
        display: flex;
        gap: 6px;
        margin-left: 10px;
    }

    .date-btn,
    .filter-btn {
        padding: 4px 10px;
        border: 1px solid #ddd;
        background: #f5f5f5;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        color: #333;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .date-btn:hover,
    .filter-btn:hover {
        border-color: #1c65d7;
        color: #1c65d7;
        background-color: #f0f7ff;
    }

    .date-btn.active {
        background-color: #1c65d7;
        color: white;
        border-color: #1c65d7;
        box-shadow: 0 1px 3px rgba(28, 101, 215, 0.3);
        font-weight: 500;
    }

    .dark .date-btn,
    .dark .filter-btn {
        background-color: #333;
        border-color: #555;
        color: #ccc;
    }

    .dark .date-btn.active {
        background-color: #1c65d7;
        border-color: #1c65d7;
    }

    /* 分页样式 */
    .list-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 10px;
        background: #f8f9fa;
        border-bottom: 1px solid #eee;
    }

    .pagination-btn {
        padding: 4px 10px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        color: #333;
        transition: all 0.2s ease;
    }

    .pagination-btn:hover:not([disabled]) {
        border-color: #1c65d7;
        color: #1c65d7;
    }

    .pagination-btn[disabled] {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-info {
        margin: 0 10px;
        font-size: 12px;
        color: #666;
    }

    .dark .list-pagination {
        background: #333;
        border-color: #444;
    }

    .dark .pagination-btn {
        background: #444;
        border-color: #555;
        color: #ccc;
    }

    .dark .pagination-btn:hover:not([disabled]) {
        border-color: #1c65d7;
        color: #fff;
    }

    .dark .pagination-info {
        color: #ccc;
    }

    /* 标签页样式 */
    .stats-tabs {
        display: flex;
        margin-bottom: 10px;
        border-bottom: 1px solid #eee;
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
    }

    .tab-btn {
        padding: 8px 16px;
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        cursor: pointer;
        font-size: 14px;
        color: #666;
        transition: all 0.2s ease;
    }

    .tab-btn:hover {
        color: #1c65d7;
    }

    .tab-btn.active {
        color: #1c65d7;
        border-bottom-color: #1c65d7;
        font-weight: 500;
    }

    .stats-tab-content {
        display: none;
    }

    .stats-tab-content.active {
        display: block;
    }

    .dark .stats-tabs {
        border-bottom-color: #444;
    }

    .dark .tab-btn {
        color: #aaa;
    }

    .dark .tab-btn:hover {
        color: #5e9bfe;
    }

    .dark .tab-btn.active {
        color: #5e9bfe;
        border-bottom-color: #5e9bfe;
    }
</style>

<script>
    // 将变量声明放在最前面
    // 全局变量定义
    var globalStatsData = null; // 存储当前筛选后的数据
    var countryChart = null;
    var provinceChart = null;
    var dataLoadAttempts = 0; // 记录加载尝试次数
    var maxLoadAttempts = 2; // 最大加载尝试次数

    var serverFilteredIPs = <?php echo json_encode($serverFilteredIPs); ?>;

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

    // 页面加载完成后立即执行
    window.addEventListener('load', function() {
        console.log("页面完全加载，开始初始化...");

        // 初始化调试信息区域
        initDebugPanel();

        // 检查echarts是否加载
        if (typeof echarts === 'undefined') {
            console.log("echarts库未加载，尝试加载...");
            var scriptLoaded = false;
            
            // 检查页面上是否已有echarts脚本标签
            document.querySelectorAll('script').forEach(function(script) {
                if (script.src.indexOf('echarts') > -1) {
                    scriptLoaded = true;
                }
            });
            
            // 检查ECharts是否已通过智能加载机制加载
            if (typeof echarts !== 'undefined') {
                console.log("echarts已加载，直接初始化图表");
                setTimeout(function() {
                    initializeEverything();
                }, 200);
            } else if (window.echartsReady === false) {
                // 智能加载已失败
                console.error("ECharts加载失败，无法初始化图表");
                alert('图表库加载失败，请刷新页面重试');
            } else {
                // 等待智能加载完成
                var checkInterval = setInterval(function() {
                    if (typeof echarts !== 'undefined') {
                        clearInterval(checkInterval);
                        console.log("echarts库加载完成，开始初始化图表");
                        setTimeout(function() {
                            initializeEverything();
                        }, 200);
                    } else if (window.echartsReady === false) {
                        clearInterval(checkInterval);
                        console.error("ECharts最终加载失败");
                        alert('图表库加载失败，请刷新页面重试');
                    }
                }, 100);
            }
            
            // 保留原有的脚本检查逻辑作为备用
            if (false) { // 禁用原逻辑
                // 脚本标签存在但可能尚未加载完成，等待并轮询检查
                var checkInterval = setInterval(function() {
                    if (typeof echarts !== 'undefined') {
                        clearInterval(checkInterval);
                        console.log("echarts库已加载完成，开始初始化图表");
                        initializeEverything();
                    } else {
                        console.log("等待echarts库加载...");
                    }
                }, 300);
                
                // 设置超时，避免无限等待
                setTimeout(function() {
                    clearInterval(checkInterval);
                    console.log("echarts库加载超时，请刷新页面");
                    
                    // 显示错误提示
                    var errorStatus = document.getElementById('errorStatus');
                    if (errorStatus) {
                        errorStatus.style.display = 'block';
                        errorStatus.innerHTML = '<p>图表库加载失败，请尝试刷新页面或检查网络连接。</p>';
                    }
                }, 10000); // 10秒超时
            }
        } else {
            // echarts已加载，直接初始化
            console.log("echarts库已存在，直接初始化");
            initializeEverything();
        }

        // 添加窗口大小变化监听
        window.addEventListener('resize', handleResize);

        // 处理屏幕旋转事件（移动设备）
        window.addEventListener('orientationchange', handleOrientationChange);
    });

    // 初始化调试面板
    function initDebugPanel() {
        const debugPanel = document.getElementById('debugInfo');
        const toggleBtn = document.getElementById('toggleDebug');
        const forceReloadBtn = document.getElementById('forceReload');

        // 检查是否需要显示调试面板
        const showDebug = localStorage.getItem('visitorStats_showDebug') === 'true';
        debugPanel.style.display = showDebug ? 'block' : 'none';
        toggleBtn.textContent = showDebug ? '隐藏调试信息' : '显示调试信息';

        // 切换调试面板显示状态
        toggleBtn.addEventListener('click', function() {
            const isVisible = debugPanel.style.display === 'block';
            debugPanel.style.display = isVisible ? 'none' : 'block';
            toggleBtn.textContent = isVisible ? '显示调试信息' : '隐藏调试信息';
            localStorage.setItem('visitorStats_showDebug', !isVisible);
        });

        // 强制重新加载数据
        forceReloadBtn.addEventListener('click', function() {
            updateDebugInfo('数据加载状态', '强制重新加载中...');
            dataLoadAttempts = 0;

            const today = new Date();
            const last7 = new Date();
            last7.setDate(today.getDate() - 6);
            const startDate = formatDate(last7) + " 00:00:00";
            const endDate = formatDate(today) + " 23:59:59";

            loadDataWithRetry(startDate, endDate);
        });

        // 按下Ctrl+Shift+D显示调试面板
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                debugPanel.style.display = 'block';
                toggleBtn.textContent = '隐藏调试信息';
                localStorage.setItem('visitorStats_showDebug', true);
            }
        });
    }

    // 更新调试信息
    function updateDebugInfo(field, value) {
        switch (field) {
            case '图表加载状态':
                document.getElementById('chartLoadStatus').textContent = value;
                break;
            case '数据加载状态':
                document.getElementById('dataLoadStatus').textContent = value;
                break;
            case '加载尝试次数':
                document.getElementById('loadAttempts').textContent = value;
                break;
        }
    }

    // 集中初始化所有内容
    function initializeEverything() {
        try {
            console.log("开始初始化所有内容...");
            updateDebugInfo('图表加载状态', '初始化中...');
            
            // 清理先前的实例，避免内存泄漏
            if (countryChart) {
                countryChart.dispose();
                countryChart = null;
            }
            
            if (provinceChart) {
                provinceChart.dispose();
                provinceChart = null;
            }
            
            // 移除先前绑定的事件监听器，防止重复绑定
            removeEventListeners();
            
            // 初始化图表
            initChart();

            // 初始化日期筛选
            initDateFilter();
            
            // 初始化图表样式切换
            initChartStyleToggle();
            
            // 初始化视图切换器
            initViewToggle();
            
            // 初始化标签页切换
            initTabToggle();
            
            // 初始化设备排除功能
            initSelfExclude();
            
            // 首次加载默认选择最近7天的数据
            const today = new Date();
            const last7 = new Date();
            last7.setDate(today.getDate() - 6);
            const startDate = formatDate(last7) + " 00:00:00";
            const endDate = formatDate(today) + " 23:59:59";
            
            // 更新日期输入框
            document.getElementById('startDate').value = formatDate(last7);
            document.getElementById('endDate').value = formatDate(today);
            
            // 设置"最近7天"按钮为活跃状态
            document.querySelectorAll('.date-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('last7DaysBtn').classList.add('active');
            
            // 直接获取数据
            console.log("首次加载数据...");
            updateDebugInfo('图表加载状态', '已初始化');
            updateDebugInfo('数据加载状态', '开始加载数据...');
            loadDataWithRetry(startDate, endDate);
        } catch (e) {
            console.error('初始化过程出错:', e);
            updateDebugInfo('图表加载状态', '初始化失败: ' + e.message);

            setTimeout(function() {
                try {
                    console.log("尝试延迟初始化...");
                    if (!countryChart && typeof echarts !== 'undefined') {
                        initChart();
                        updateDebugInfo('图表加载状态', '延迟初始化成功');

                        // 再次尝试加载数据
                        const today = new Date();
                        const last7 = new Date();
                        last7.setDate(today.getDate() - 6);
                        loadDataWithRetry(formatDate(last7) + " 00:00:00", formatDate(today) + " 23:59:59");
                    }
                } catch (err) {
                    console.error('延迟初始化失败:', err);
                    updateDebugInfo('图表加载状态', '延迟初始化失败: ' + err.message);
                }
            }, 500);
        }
    }

    // 移除事件监听器，防止重复绑定
    function removeEventListeners() {
        // 移除窗口大小变化监听器
        window.removeEventListener('resize', handleResize);
        window.removeEventListener('orientationchange', handleOrientationChange);
        
        // 移除日期按钮的监听器
        const filterBtn = document.getElementById('filterBtn');
        const resetBtn = document.getElementById('resetBtn');
        const last7DaysBtn = document.getElementById('last7DaysBtn');
        const last30DaysBtn = document.getElementById('last30DaysBtn');
        const allTimeBtn = document.getElementById('allTimeBtn');
        
        if (filterBtn) filterBtn.replaceWith(filterBtn.cloneNode(true));
        if (resetBtn) resetBtn.replaceWith(resetBtn.cloneNode(true));
        if (last7DaysBtn) last7DaysBtn.replaceWith(last7DaysBtn.cloneNode(true));
        if (last30DaysBtn) last30DaysBtn.replaceWith(last30DaysBtn.cloneNode(true));
        if (allTimeBtn) allTimeBtn.replaceWith(allTimeBtn.cloneNode(true));
        
        // 移除视图切换按钮的监听器
        document.querySelectorAll('.view-toggle button').forEach(button => {
            button.replaceWith(button.cloneNode(true));
        });
        
        // 移除图表样式切换按钮的监听器
        document.querySelectorAll('.chart-style-toggle button').forEach(button => {
            button.replaceWith(button.cloneNode(true));
        });
        
        // 移除标签页切换按钮的监听器
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.replaceWith(button.cloneNode(true));
        });
        
        // 移除设备排除按钮的监听器
        const excludeSelfBtn = document.getElementById('excludeSelfBtn');
        const includeSelfBtn = document.getElementById('includeSelfBtn');
        const deleteDataBtn = document.getElementById('deleteDataBtn');
        
        if (excludeSelfBtn) excludeSelfBtn.replaceWith(excludeSelfBtn.cloneNode(true));
        if (includeSelfBtn) includeSelfBtn.replaceWith(includeSelfBtn.cloneNode(true));
        if (deleteDataBtn) deleteDataBtn.replaceWith(deleteDataBtn.cloneNode(true));
        
        // 移除调试面板按钮的监听器
        const toggleBtn = document.getElementById('toggleDebug');
        const forceReloadBtn = document.getElementById('forceReload');
        
        if (toggleBtn) toggleBtn.replaceWith(toggleBtn.cloneNode(true));
        if (forceReloadBtn) forceReloadBtn.replaceWith(forceReloadBtn.cloneNode(true));
    }

    // 处理窗口大小变化
    function handleResize() {
        if (countryChart) {
            countryChart.resize();
        }
        if (provinceChart) {
            provinceChart.resize();
        }
    }

    // 处理屏幕旋转事件
    function handleOrientationChange() {
        setTimeout(function() {
            if (countryChart) countryChart.resize();
            if (provinceChart) provinceChart.resize();
        }, 300);
    }

    // 添加带重试的数据加载函数
    function loadDataWithRetry(startDate, endDate) {
        dataLoadAttempts++;
        console.log(`加载数据尝试 ${dataLoadAttempts}/${maxLoadAttempts}...`);
        updateDebugInfo('加载尝试次数', dataLoadAttempts);
        updateDebugInfo('数据加载状态', `正在尝试第${dataLoadAttempts}次加载...`);

        fetchStatsData(startDate, endDate)
            .then(data => {
                console.log("数据加载成功:", data ? "有数据" : "无数据");
                updateDebugInfo('数据加载状态', data ? '加载成功' : '数据为空');

                if ((!data || !data.countries || data.countries.length === 0) && dataLoadAttempts < maxLoadAttempts) {
                    // 如果没有数据且未达到最大尝试次数，再次尝试
                    console.log("数据加载不完整，再次尝试...");
                    updateDebugInfo('数据加载状态', '数据不完整，准备重试...');

                    setTimeout(() => {
                        loadDataWithRetry(startDate, endDate);
                    }, 1000);
                } else {
                    dataLoadAttempts = 0; // 重置尝试计数
                    
                    // 如果仍然没有数据，显示错误提示
                    if (!data || !data.countries || data.countries.length === 0) {
                        console.error("多次尝试后仍无法加载数据");
                        const errorStatus = document.getElementById('errorStatus');
                        if (errorStatus) {
                            errorStatus.style.display = 'block';
                            errorStatus.innerHTML = '<p>无法加载统计数据，请检查网络连接或刷新页面重试。<button id="manualRetry" style="margin-left:10px;padding:2px 8px;">重试</button></p>';
                            
                            // 添加手动重试按钮点击事件
                            const manualRetryBtn = document.getElementById('manualRetry');
                            if (manualRetryBtn) {
                                manualRetryBtn.addEventListener('click', function() {
                                    errorStatus.style.display = 'none';
                                    const today = new Date();
                                    const last7 = new Date();
                                    last7.setDate(today.getDate() - 6);
                                    loadDataWithRetry(formatDate(last7) + " 00:00:00", formatDate(today) + " 23:59:59");
                                });
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error("数据加载失败:", error);
                updateDebugInfo('数据加载状态', '加载失败: ' + error.message);

                if (dataLoadAttempts < maxLoadAttempts) {
                    // 如果加载失败且未达到最大尝试次数，再次尝试
                    console.log("加载失败，再次尝试...");
                    updateDebugInfo('数据加载状态', '准备重试...');

                    setTimeout(() => {
                        loadDataWithRetry(startDate, endDate);
                    }, 1500 * dataLoadAttempts); // 逐渐增加重试间隔
                } else {
                    dataLoadAttempts = 0; // 重置尝试计数
                    
                    // 显示重试按钮
                    const errorStatus = document.getElementById('errorStatus');
                    if (errorStatus) {
                        errorStatus.style.display = 'block';
                        errorStatus.innerHTML = '<p>加载数据时出现问题，请检查网络连接或点击重试。<button id="manualRetry" style="margin-left:10px;padding:2px 8px;">重试</button></p>';
                        
                        // 添加手动重试按钮点击事件
                        const manualRetryBtn = document.getElementById('manualRetry');
                        if (manualRetryBtn) {
                            manualRetryBtn.addEventListener('click', function() {
                                errorStatus.style.display = 'none';
                                const today = new Date();
                                const last7 = new Date();
                                last7.setDate(today.getDate() - 6);
                                loadDataWithRetry(formatDate(last7) + " 00:00:00", formatDate(today) + " 23:59:59");
                            });
                        }
                    }
                }
            });
    }

    // 初始化图表
    function initChart() {
        const countryChartElem = document.getElementById('countryChart');
        const provinceChartElem = document.getElementById('provinceChart');

        if (!countryChartElem || !provinceChartElem) {
            console.error('找不到图表元素');
            updateDebugInfo('图表加载状态', '找不到图表容器元素');
            return;
        }
        
        // 确保容器可见且有尺寸
        countryChartElem.style.display = 'block';
        countryChartElem.style.height = '850px';
        countryChartElem.style.width = '100%';

        provinceChartElem.style.display = 'block';
        provinceChartElem.style.height = '850px';
        provinceChartElem.style.width = '100%';
        
        // 创建图表实例
        try {
            // 使用主题和适当选项初始化
            const initOptions = {
                renderer: 'canvas',
                devicePixelRatio: window.devicePixelRatio
            };
            
            // 创建图表实例
            countryChart = echarts.init(countryChartElem, null, initOptions);
            provinceChart = echarts.init(provinceChartElem, null, initOptions);
            updateDebugInfo('图表加载状态', '图表实例创建成功');

            // 设置加载动画
            countryChart.showLoading({
                text: '正在加载数据...',
                color: '#1c65d7',
                textColor: '#000',
                maskColor: 'rgba(255, 255, 255, 0.8)',
                fontSize: 14
            });

            provinceChart.showLoading({
                text: '正在加载数据...',
                color: '#1c65d7',
                textColor: '#000',
                maskColor: 'rgba(255, 255, 255, 0.8)',
                fontSize: 14
            });
        } catch (e) {
            console.error('图表初始化错误:', e);
            updateDebugInfo('图表加载状态', '初始化错误: ' + e.message);
            
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
                    
                    // 确保列表数据已加载
                    if (target === 'country' && globalStatsData && globalStatsData.countries) {
                        updateList('countryList', globalStatsData.countries);
                    } else if (target === 'province' && globalStatsData && globalStatsData.provinces) {
                        updateList('provinceList', globalStatsData.provinces);
                    }
                } else {
                    // 切换到图表视图
                    listView.classList.remove('active');
                    chartView.classList.add('active');
                    
                    // 重绘图表
                    if (target === 'country' && countryChart) {
                        countryChart.resize();
                    } else if (target === 'province' && provinceChart) {
                        provinceChart.resize();
                    }
                }
            });
        });
    }

    // 初始化日期筛选功能
    function initDateFilter() {
        const dateButtons = document.querySelectorAll('.date-btn');
        const filterBtn = document.getElementById('filterBtn');
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');

        const setActiveButton = (activeBtn) => {
            dateButtons.forEach(btn => btn.classList.remove('active'));
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        };

        // 查询按钮
        if (filterBtn) {
            filterBtn.addEventListener('click', function() {
                const startDate = startDateInput.value ? startDateInput.value + " 00:00:00" : null;
                const endDate = endDateInput.value ? endDateInput.value + " 23:59:59" : null;
                if (startDate && endDate) {
                    setActiveButton(null); // Custom query deselects quick buttons
                    fetchStatsData(startDate, endDate);
                }
            });
        }

        // 今天（重置）按钮
        const resetBtn = document.getElementById('resetBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                const today = new Date();
                startDateInput.value = formatDate(today);
                endDateInput.value = formatDate(today);
                setActiveButton(this);
                fetchStatsData(formatDate(today) + " 00:00:00", formatDate(today) + " 23:59:59");
            });
        }

        // 最近7天按钮
        const last7DaysBtn = document.getElementById('last7DaysBtn');
        if (last7DaysBtn) {
            last7DaysBtn.addEventListener('click', function() {
                const today = new Date();
                const last7 = new Date();
                last7.setDate(today.getDate() - 6);
                startDateInput.value = formatDate(last7);
                endDateInput.value = formatDate(today);
                setActiveButton(this);
                fetchStatsData(formatDate(last7) + " 00:00:00", formatDate(today) + " 23:59:59");
            });
        }

        // 最近30天按钮
        const last30DaysBtn = document.getElementById('last30DaysBtn');
        if (last30DaysBtn) {
            last30DaysBtn.addEventListener('click', function() {
                const today = new Date();
                const last30 = new Date();
                last30.setDate(today.getDate() - 29);
                startDateInput.value = formatDate(last30);
                endDateInput.value = formatDate(today);
                setActiveButton(this);
                fetchStatsData(formatDate(last30) + " 00:00:00", formatDate(today) + " 23:59:59");
            });
        }

        // 全部按钮
        const allTimeBtn = document.getElementById('allTimeBtn');
        if (allTimeBtn) {
            allTimeBtn.addEventListener('click', function() {
                const today = new Date();
                const allTimeStart = new Date('2020-01-01'); // A very early date
                startDateInput.value = formatDate(allTimeStart);
                endDateInput.value = formatDate(today);
                setActiveButton(this);
                fetchStatsData(formatDate(allTimeStart) + " 00:00:00", formatDate(today) + " 23:59:59");
            });
        }
    }

    // 格式化日期为YYYY-MM-DD
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // 添加新函数用于格式化日期时间
    function formatDateTime(date, isStart = true) {
        const dateStr = formatDate(date);
        return dateStr + (isStart ? " 00:00:00" : " 23:59:59");
    }

    // 更新显示筛选结果的函数
    function updateFilterStatus(totalVisits, countriesCount) {
        const loadingStatus = document.getElementById('loadingStatus');
        if (loadingStatus) {
            // 更新总访问量和总地区数
            document.getElementById('totalVisits').textContent = totalVisits || 0;
            document.getElementById('totalCountries').textContent = countriesCount || 0;
            loadingStatus.style.display = 'block';
        }
    }

    // 获取统计数据
    function fetchStatsData(startDate, endDate) {
        if (!startDate || !endDate) {
            const today = new Date();
            const defaultStartDate = new Date();
            defaultStartDate.setDate(today.getDate() - 6);
            startDate = formatDate(defaultStartDate) + " 00:00:00";
            endDate = formatDate(today) + " 23:59:59";
        }

        try {
            console.log(`获取统计数据，范围: ${startDate} 至 ${endDate}`);
            if (countryChart) {
                countryChart.showLoading({
                    text: '正在加载数据...',
                    color: '#1c65d7',
                    textColor: '#000',
                    maskColor: 'rgba(255, 255, 255, 0.8)',
                    fontSize: 14
                });
            }

            if (provinceChart) {
                provinceChart.showLoading({
                    text: '正在加载数据...',
                    color: '#1c65d7',
                    textColor: '#000',
                    maskColor: 'rgba(255, 255, 255, 0.8)',
                    fontSize: 14
                });
            }

            // 获取当前站点根URL
            let baseUrl = window.location.protocol + '//' + window.location.host;
            
            // 构建API完整URL
            let apiUrl = '<?php echo $this->options->pluginUrl; ?>/VisitorLoggerPro/getVisitStatistic.php';
            
            // 如果是相对路径，则添加基础URL
            if (apiUrl.indexOf('http') !== 0) {
                apiUrl = baseUrl + apiUrl;
            }
            
            console.log("API请求URL:", apiUrl);
            
            // 使用传统的XMLHttpRequest，避免fetch可能导致的问题
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                
                // 设置30秒超时
                xhr.timeout = 30000;
                
                xhr.open('POST', apiUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('Cache-Control', 'no-cache');
                xhr.setRequestHeader('Pragma', 'no-cache');
                
                // 处理加载完成
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            console.log("数据加载成功:", data);
                            resolve(data);
                        } catch (e) {
                            console.error("解析响应数据失败:", e);
                            reject(new Error("解析数据失败: " + e.message));
                        }
                    } else {
                        console.error("服务器错误:", xhr.status, xhr.statusText);
                        reject(new Error("服务器错误: " + xhr.status));
                    }
                };
                
                // 处理错误
                xhr.onerror = function() {
                    console.error("网络请求失败");
                    reject(new Error("网络请求失败"));
                };
                
                // 处理超时
                xhr.ontimeout = function() {
                    console.error("请求超时");
                    reject(new Error("请求超时，请检查网络连接"));
                };
                
                // 发送请求
                xhr.send(JSON.stringify({
                    startDate,
                    endDate
                }));
            })
            .then(data => {
                console.log("API返回数据:", data);

                const transformedData = {
                    countries: Object.entries(data.countryData || {}).map(([name, value]) => ({
                        country: name,
                        count: value,
                        ips: []
                    })),
                    provinces: Object.entries(data.provinceData || {}).map(([name, value]) => ({
                        province: name,
                        count: value
                    })),
                    routes: Object.entries(data.routeData || {}).map(([name, value]) => ({
                        route: name,
                        count: value
                    })),
                    totalVisits: data.totalVisits || 0,
                    totalCountries: data.totalCountries || 0
                };

                globalStatsData = {
                    countries: transformedData.countries,
                    provinces: transformedData.provinces,
                    totalVisits: transformedData.totalVisits,
                    totalCountries: transformedData.totalCountries
                };

                updateStatsDisplay();
                updateFilterStatus(
                    globalStatsData.totalVisits,
                    globalStatsData.totalCountries
                );

                return globalStatsData;
            })
            .catch(error => {
                console.error('获取数据错误:', error);
                const errorStatus = document.getElementById('errorStatus');
                if (errorStatus) {
                    errorStatus.style.display = 'block';
                    errorStatus.innerHTML = '<p>获取数据失败，请检查网络连接或刷新页面重试。</p>';
                }
                // 确保隐藏加载动画
                if (countryChart) countryChart.hideLoading();
                if (provinceChart) provinceChart.hideLoading();
                throw error; // 重新抛出错误以便调用者处理
            });
        } catch (e) {
            console.error('获取数据函数执行出错:', e);
            const errorStatus = document.getElementById('errorStatus');
            if (errorStatus) {
                errorStatus.style.display = 'block';
                errorStatus.innerHTML = '<p>加载过程中出错，请刷新页面重试。</p><p style="font-size:12px;color:#999;">错误信息: ' + e.message + '</p>';
            }
            // 确保隐藏加载动画
            if (countryChart) countryChart.hideLoading();
            if (provinceChart) provinceChart.hideLoading();
            return Promise.reject(e);
        }
    }

    // 更新统计显示
    function updateStatsDisplay() {
        if (!countryChart || !globalStatsData) return;
        
        // 隐藏错误状态
        const errorStatus = document.getElementById('errorStatus');
        if (errorStatus) errorStatus.style.display = 'none';
        
        // 隐藏图表加载动画
        countryChart.hideLoading();
        if (provinceChart) provinceChart.hideLoading();
        
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

        // 更新省份列表
        if (globalStatsData.provinces && globalStatsData.provinces.length > 0) {
            updateList('provinceList', globalStatsData.provinces);

            // 更新省份图表
            if (provinceChart) {
                updateProvinceChart('ring');
            }
        }
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
            '#765005', // 高级棕色
            '#a5673f', // 棕色
            '#6435c9', // 紫色
            '#e03997', // 粉色
            '#00b5ad', // 水鸭色
            '#2185d0', // 蓝色
            '#21ba45', // 绿色
            '#db2828', // 红色
            '#fbbd08', // 黄色
            '#f2711c', // 橙色
            '#b5cc18', // 橄榄绿
            '#00b5ad', // 青色
            '#6435c9', // 紫罗兰
            '#a333c8', // 紫色
            '#e03997', // 粉色
            '#a5673f', // 棕色
            '#767676', // 灰色
            '#1b1c1d', // 黑色
            '#fbbd08'  // 黄色
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
                center: isMobile ? ['50%', '25%'] : ['40%', '40%'], // 移动端图表位置偏上
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
                    scale: true, // 启用缩放效果
                    scaleSize: 10,
                    label: {
                        show: true,
                        fontSize: isMobile ? 12 : 14,
                        fontWeight: 'bold',
                        color: '#fff',
                        backgroundColor: 'rgba(28,101,215,0.7)',
                        padding: [4, 8],
                        borderRadius: 4
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
            tbody.innerHTML = '<tr><td colspan="3" class="text-center">暂无数据</td></tr>';
            return;
        }
        
        const total = data.reduce((sum, item) => sum + parseInt(item.count || 0), 0);
        
        // 按访问量排序
        data.sort((a, b) => parseInt(b.count || 0) - parseInt(a.count || 0));
        
        // 更新表格内容
        tbody.innerHTML = data.map((item, index) => {
            const country = item.country || item.province || '未知';
            const count = item.count || 0;
            const percentage = total > 0 ? ((parseInt(count) / total) * 100).toFixed(2) : '0.00';
            
            return `
            <tr>
                <td>${country}</td>
                <td>${count}</td>
                <td>${percentage}%</td>
            </tr>
            `;
        }).join('');
    }

    // 更新分页控件
    function updatePagination(container, currentPage, totalPages, totalItems) {
        // 更新容器的当前页属性
        container.setAttribute('data-current-page', currentPage);
        
        // 更新分页信息文本
        const currentPageElem = container.querySelector('.current-page');
        const totalPagesElem = container.querySelector('.total-pages');
        
        if (currentPageElem) currentPageElem.textContent = currentPage;
        if (totalPagesElem) totalPagesElem.textContent = totalPages;
        
        // 更新按钮状态
        const prevBtn = container.querySelector('.prev-btn');
        const nextBtn = container.querySelector('.next-btn');
        
        if (prevBtn) prevBtn.disabled = currentPage <= 1;
        if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
        
        // 添加按钮点击事件
        if (prevBtn) {
            prevBtn.onclick = function() {
                if (currentPage > 1) {
                    container.setAttribute('data-current-page', currentPage - 1);
                    // 重新加载当前数据
                    const targetType = container.id === 'countryList' ? 'countries' : 'provinces';
                    updateList(container.id, globalStatsData[targetType]);
                }
            };
        }
        
        if (nextBtn) {
            nextBtn.onclick = function() {
                if (currentPage < totalPages) {
                    container.setAttribute('data-current-page', currentPage + 1);
                    // 重新加载当前数据
                    const targetType = container.id === 'countryList' ? 'countries' : 'provinces';
                    updateList(container.id, globalStatsData[targetType]);
                }
            };
        }
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

        // 获取当前IP (优先使用X-Forwarded-For以支持CDN)
        const currentIP = '<?php echo isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? 
            explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0] : 
            (isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : ""); ?>';

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

            // 将当前IP添加到全局排除列表（如果存在这个变量）
            if (currentIP && window.excludedIPs && window.excludedIPs.indexOf(currentIP) === -1) {
                window.excludedIPs.push(currentIP);
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

            // 从全局排除列表中移除当前IP（如果存在这个变量）
            if (currentIP && window.excludedIPs) {
                const index = window.excludedIPs.indexOf(currentIP);
                if (index > -1) {
                    window.excludedIPs.splice(index, 1);
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
                e.preventDefault();

                if (confirm('确定要删除当前IP的所有访问记录吗？此操作不可撤销！')) {
                    const form = document.getElementById('deleteIpForm');
                    const formData = new FormData(form);

                    fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                // 刷新数据
                                document.getElementById('resetBtn').click();
                            } else {
                                alert('删除失败: ' + data.message);
                            }
                        })
                        .catch(error => {
                            alert('删除请求失败，请检查网络或联系管理员。');
                            console.error('删除数据失败:', error);
                        });
                }
            });
        }
    }

    // 更新省份图表
    function updateProvinceChart(style) {
        if (!provinceChart || !globalStatsData || !globalStatsData.provinces) return;

        const isDark = document.body.classList.contains('dark');
        const textColor = isDark ? '#ccc' : '#58666e';
        const labelColor = isDark ? '#ddd' : '#333';

        // 确保样式存在，默认使用环形图
        const styleConfig = chartStyles[style] || chartStyles.ring;

        // 准备数据
        const provinceData = (globalStatsData.provinces || []).map(item => ({
            name: item.province || '未知',
            value: parseInt(item.count) || 0
        }));

        // 处理无数据情况
        const seriesData = provinceData.length > 0 ? provinceData : [{
            name: '暂无数据',
            value: 1
        }];

        // 检查是否为移动设备
        const isMobile = window.innerWidth <= 768;

        // 高级颜色方案 - 使用不同的配色
        const provinceColors = [
            '#26ccd8', // 高级青色
            '#3685fe', // 高级蓝色
            '#9977ef', // 高级紫色
            '#f5616f', // 高级红色
            '#f7b13f', // 高级橙色
            '#f9e264', // 高级金色
            '#50c48f', // 高级绿色
            '#f47a75', // 高级珊瑚色
            '#009db2', // 高级青蓝色
            '#024b51', // 高级深青色
            '#0780cf', // 高级蓝色
            '#765005', // 高级棕色
            '#a5673f', // 棕色
            '#6435c9', // 紫色
            '#e03997', // 粉色
            '#00b5ad', // 水鸭色
            '#2185d0', // 蓝色
            '#21ba45', // 绿色
            '#db2828', // 红色
            '#fbbd08', // 黄色
            '#f2711c', // 橙色
            '#b5cc18', // 橄榄绿
            '#00b5ad', // 青色
            '#6435c9', // 紫罗兰
            '#a333c8', // 紫色
            '#e03997', // 粉色
            '#a5673f', // 棕色
            '#767676', // 灰色
            '#1b1c1d', // 黑色
            '#fbbd08'  // 黄色
        ];

        // 更新图表
        provinceChart.setOption({
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
                name: '省份访问次数',
                type: 'pie',
                radius: styleConfig.radius,
                center: isMobile ? ['50%', '25%'] : ['40%', '40%'], // 移动端图表位置偏上
                roseType: styleConfig.roseType,
                avoidLabelOverlap: true,
                itemStyle: {
                    borderRadius: 4,
                    borderColor: isDark ? '#2a2a2a' : '#fff'
                },
                label: {
                    show: true,
                    position: 'outside',
                    formatter: '{b}: {c}',
                    color: labelColor,
                    fontSize: isMobile ? 10 : 12,
                    lineHeight: isMobile ? 12 : 14
                },
                labelLine: {
                    length: 10,
                    length2: 10,
                    smooth: true
                },
                emphasis: {
                    focus: 'series',
                    scale: true,
                    scaleSize: 10,
                    label: {
                        show: true,
                        fontSize: isMobile ? 12 : 14,
                        fontWeight: 'bold',
                        color: '#fff',
                        backgroundColor: 'rgba(28,101,215,0.7)',
                        padding: [4, 8],
                        borderRadius: 4
                    },
                    itemStyle: {
                        shadowBlur: 20,
                        shadowOffsetX: 0,
                        shadowColor: 'rgba(0, 0, 0, 0.5)'
                    }
                },
                data: seriesData,
                color: provinceColors
            }]
        });

        // 确保图表大小适应容器
        setTimeout(() => {
            provinceChart.resize();
        }, 50);
    }

    // 初始化标签页切换
    function initTabToggle() {
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                
                // 更新按钮状态
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // 更新内容区域
                document.querySelectorAll('.stats-tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tabId + 'Tab').classList.add('active');
                
                // 重新调整图表大小
                setTimeout(() => {
                    if (tabId === 'country' && countryChart) {
                        countryChart.resize();
                    } else if (tabId === 'province' && provinceChart) {
                        provinceChart.resize();
                    }
                }, 10);
            });
        });
    }
</script>

<!-- footer -->
<?php $this->need('component/footer.php'); ?>
<!-- / footer -->
