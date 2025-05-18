<?php
if (!defined('__TYPECHO_ADMIN__')) {
    define('__TYPECHO_ADMIN__', true);
}
?>

<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$pageSize = 10;

// 获取记录
$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$totalLogs = 0;
$totalPages = 0;

$ip = isset($_POST['ipQuery']) ? $_POST['ipQuery'] : (isset($_GET['ipQuery']) ? $_GET['ipQuery'] : '');
$totalLogs = $db->fetchObject($db->select(array('COUNT(*)' => 'num'))->from($prefix . 'visitor_log')->where('ip LIKE ?', '%' . $ip . '%'))->num;
$totalPages = ceil($totalLogs / $pageSize);

$logs = VisitorLoggerPro_Plugin::getSearchVisitorLogs($page, $pageSize, $ip);

$formattedStartDate = date('Y-m-d H:i:s', strtotime('today'));
$formattedEndDate = date('Y-m-d H:i:s', strtotime('tomorrow') - 1);

// 获取所有记录用于统计
$allLogsForStats = $db->fetchAll($db->select('country, route')
    ->from($prefix . 'visitor_log')
    ->where('ip LIKE ?', '%' . $ip . '%'));

// 在PHP中进行统计
$countryStats = [];
$routeStats = [];

foreach ($allLogsForStats as $log) {
    // 统计国家访问
    $country = $log['country'];
    if (!isset($countryStats[$country])) {
        $countryStats[$country] = ['country' => $country, 'count' => 0];
    }
    $countryStats[$country]['count']++;

    // 统计路由访问
    $route = $log['route'];
    if (!isset($routeStats[$route])) {
        $routeStats[$route] = ['route' => $route, 'count' => 0];
    }
    $routeStats[$route]['count']++;
}

// 按count降序排序
uasort($countryStats, function ($a, $b) {
    return $b['count'] - $a['count'];
});

uasort($routeStats, function ($a, $b) {
    return $b['count'] - $a['count'];
});

$countryStats = array_values($countryStats);
$routeStats = array_values($routeStats);
?>

<script src="../usr/plugins/VisitorLoggerPro/js/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<script>
    // 分页功能
    document.addEventListener('DOMContentLoaded', function() {
        const paginationContainer = document.getElementById('pagination');
        const currentPage = <?php echo $page; ?>;
        const totalPages = <?php echo $totalPages; ?>;
        const ipQuery = '<?php echo $ip; ?>';

        // 生成分页
        const maxPagesToShow = 5;
        const pagination = [];

        if (totalPages <= maxPagesToShow) {
            for (let i = 1; i <= totalPages; i++) {
                pagination.push(i);
            }
        } else {
            const half = Math.floor(maxPagesToShow / 2);
            let start = currentPage - half + 1 - maxPagesToShow % 2;
            let end = currentPage + half;

            if (start <= 0) {
                start = 1;
                end = maxPagesToShow;
            } else if (end > totalPages) {
                start = totalPages - maxPagesToShow + 1;
                end = totalPages;
            }

            for (let i = start; i <= end; i++) {
                pagination.push(i);
            }

            if (start > 1) {
                pagination.unshift('...');
                pagination.unshift(1);
            }
            if (end < totalPages) {
                pagination.push('...');
                pagination.push(totalPages);
            }
        }

        // 渲染分页
        pagination.forEach(page => {
            const li = document.createElement('li');
            if (page === '...') {
                li.innerHTML = `<span>${page}</span>`;
            } else if (page === currentPage) {
                li.classList.add('current');
                li.innerHTML = `<a href="?panel=VisitorLoggerPro%2Fpanel.php&page=${page}&ipQuery=${ipQuery}">${page}</a>`;
            } else {
                li.innerHTML = `<a href="?panel=VisitorLoggerPro%2Fpanel.php&page=${page}&ipQuery=${ipQuery}">${page}</a>`;
            }
            paginationContainer.appendChild(li);
        });
    });

    // 初始化图表
    document.addEventListener('DOMContentLoaded', function() {
        // 初始化图表
        const countryChart = echarts.init(document.getElementById('countryChartContent'));
        const routeChart = echarts.init(document.getElementById('routeChartContent'));

        // 准备数据
        const countryData = <?php echo json_encode(array_map(function ($stat) {
                                return ['value' => $stat['count'], 'name' => $stat['country']];
                            }, array_slice($countryStats, 0, 30))); ?>;

        const routeData = <?php echo json_encode(array_map(function ($stat) {
                                return ['value' => $stat['count'], 'name' => urldecode($stat['route'])];
                            }, array_slice($routeStats, 0, 15))); ?>;

        // 设置国家访问统计图表
        countryChart.setOption({
            tooltip: {
                trigger: 'item',
                formatter: '{a} <br/>{b}: {c} ({d}%)'
            },
            legend: {
                type: 'scroll',
                orient: 'vertical',
                right: 10,
                top: 20,
                bottom: 20,
            },
            series: [{
                name: '访问次数',
                type: 'pie',
                radius: ['40%', '70%'],
                center: ['40%', '50%'],
                avoidLabelOverlap: true,
                itemStyle: {
                    borderRadius: 10,
                    borderColor: '#fff',
                    borderWidth: 2
                },
                label: {
                    show: true,
                    formatter: '{b}: {c}'
                },
                emphasis: {
                    label: {
                        show: true,
                        fontSize: '14',
                        fontWeight: 'bold'
                    }
                },
                data: countryData
            }]
        });

        // 设置路由访问统计图表
        routeChart.setOption({
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                }
            },
            grid: {
                left: '3%',
                right: '4%',
                bottom: '10%',
                top: '10%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                data: routeData.map(item => item.name),
                axisLabel: {
                    interval: 0,
                    rotate: 30,
                    fontSize: 12,
                    width: 100,
                    overflow: 'break'
                }
            },
            yAxis: {
                type: 'value'
            },
            series: [{
                name: '访问次数',
                type: 'bar',
                data: routeData.map(item => ({
                    value: item.value,
                    itemStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                offset: 0,
                                color: '#83bff6'
                            },
                            {
                                offset: 0.5,
                                color: '#3498db'
                            },
                            {
                                offset: 1,
                                color: '#2980b9'
                            }
                        ])
                    }
                })),
                barWidth: '60%',
                itemStyle: {
                    borderRadius: [4, 4, 0, 0]
                }
            }]
        });

        // 处理图表切换
        const chartContainers = document.querySelectorAll('.chart-container');
        chartContainers.forEach(container => {
            const tabs = container.querySelectorAll('.chart-tab');
            const chartContent = container.querySelector('.chart-content');
            const listContent = container.querySelector('.list-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const view = tab.dataset.view;
                    const chartType = tab.dataset.chart;

                    // 更新标签状态
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    // 更新视图状态
                    if (view === 'chart') {
                        chartContent.style.display = 'block';
                        listContent.style.display = 'none';
                        // 重新渲染图表
                        if (chartType === 'country') {
                            countryChart.resize();
                        } else if (chartType === 'route') {
                            routeChart.resize();
                        }
                    } else {
                        chartContent.style.display = 'none';
                        listContent.style.display = 'block';
                    }
                });
            });
        });

        // 处理窗口大小改变
        window.addEventListener('resize', function() {
            countryChart.resize();
            routeChart.resize();
        });
    });
</script>

<style>
    .main {
        padding: 20px;
        background-color: #f5f7fa;
        min-height: 100vh;
    }

    .body.container {
        max-width: 100%;
        margin: 0 auto;
        padding: 0 20px;
    }

    .page-header {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .page-header h2 {
        color: #2c3e50;
        margin: 0;
        font-size: 24px;
        font-weight: 600;
    }

    .content-wrapper {
        display: grid;
        grid-template-columns: minmax(800px, 1fr) minmax(400px, 1fr);
        gap: 24px;
        align-items: start;
    }

    .left-section {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .action-forms {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin: 0;
    }

    .action-form {
        background: #fff;
        padding: 12px;
        border-radius: 8px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        height: 80%;
        display: flex;
        flex-direction: column;
    }

    .action-form label {
        display: block;
        margin-bottom: 4px;
        color: #2c3e50;
        font-weight: 500;
        font-size: 13px;
    }

    .action-form input {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        margin-bottom: 8px;
        transition: all 0.3s;
        font-size: 13px;
    }

    .action-form button {
        margin-top: auto;
        width: 100%;
        padding: 6px 8px;
        border: none;
        border-radius: 4px;
        background-color: #3498db;
        color: white;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 13px;
    }

    .logs-section {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        /* height: calc(100vh - 200px); */
        overflow: auto;
        min-width: 0;
        border: 1px solid #e2e8f0;
    }

    .typecho-list-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .typecho-list-table th,
    .typecho-list-table td {
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .typecho-list-table th {
        background-color: #f8fafc;
        font-weight: 600;
        color: #4a5568;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .typecho-list-table th:nth-child(1),
    .typecho-list-table td:nth-child(1) {
        width: 15%;
    }

    .typecho-list-table th:nth-child(2),
    .typecho-list-table td:nth-child(2) {
        width: 45%;
    }

    .typecho-list-table th:nth-child(3),
    .typecho-list-table td:nth-child(3) {
        width: 20%;
    }

    .typecho-list-table th:nth-child(4),
    .typecho-list-table td:nth-child(4) {
        width: 20%;
    }

    .typecho-list-table tr:hover {
        background-color: #f8fafc;
    }

    .typecho-list-table tr:last-child td {
        border-bottom: none;
    }

    .stats-section {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        height: calc(100vh - 200px);
        display: flex;
        flex-direction: column;
        gap: 24px;
        border: 1px solid #e2e8f0;
    }

    .chart-container {
        flex: 1;
        min-height: 300px;
        background: #fff;
        border-radius: 8px;
        padding: 16px;
        border: 1px solid #e2e8f0;
        position: relative;
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .chart-title {
        font-size: 14px;
        font-weight: 600;
        color: #2c3e50;
    }

    .chart-tabs {
        display: flex;
        gap: 8px;
    }

    .chart-tab {
        padding: 4px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        background: #fff;
        color: #4a5568;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
    }

    .chart-tab.active {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }

    .chart-content {
        height: calc(100% - 40px);
        width: 100%;
    }

    .list-content {
        display: none;
        height: calc(100% - 40px);
        overflow: auto;
    }

    .list-content.active {
        display: block;
    }

    .stats-list {
        background: #fff;
        border-radius: 8px;
        padding: 12px;
    }

    .stats-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #f0f0f0;
        font-size: 13px;
    }

    .stats-item:last-child {
        border-bottom: none;
    }

    .typecho-pager {
        margin-top: 24px;
        display: flex;
        justify-content: center;
        padding-bottom: 20px;
    }

    .typecho-pager ul {
        display: flex;
        gap: 8px;
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .typecho-pager li {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 4px 12px;
        transition: all 0.3s;
    }

    .typecho-pager li:hover {
        background: rgb(255, 255, 255);
    }

    .typecho-pager li.current {
        background: rgb(255, 255, 255);
        color: white;
        border-color: #3498db;
    }

    @media (max-width: 1600px) {
        .content-wrapper {
            grid-template-columns: 1fr;
        }

        .chart-container {
            min-height: 300px;
        }
    }
</style>

<div class="main">
    <div class="body container">
        <div class="page-header">
            <h2>访客日志</h2>
        </div>

        <div class="content-wrapper">
            <div class="left-section">
                <div class="action-forms">
                    <form class="action-form" method="post" action="?panel=VisitorLoggerPro%2Fpanel.php&page=<?php echo $page; ?>">
                        <label for="days">清理历史记录</label>
                        <input type="number" id="days" name="days" min="0" max="30" value="30" placeholder="天数">
                        <button type="submit" name="clean_up">清理记录</button>
                    </form>

                    <form class="action-form" method="post" action="?panel=VisitorLoggerPro%2Fpanel.php&page=<?php echo $page; ?>">
                        <label for="ipQuery">IP地址查询</label>
                        <input type="text" id="ipQuery" name="ipQuery" value="<?php echo isset($_POST['ipQuery']) ? htmlspecialchars($_POST['ipQuery']) : (isset($_GET['ipQuery']) ? htmlspecialchars($_GET['ipQuery']) : ''); ?>" placeholder="输入IP地址">
                        <input type="hidden" name="totalPages" value="<?php echo $totalPages; ?>">
                        <button type="submit" name="searchLogs">查询</button>
                    </form>
                </div>

                <div class="logs-section">
                    <table class="typecho-list-table">
                        <thead>
                            <tr>
                                <th>IP</th>
                                <th>访问路由</th>
                                <th>访问地点</th>
                                <th>时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['ip']); ?></td>
                                    <td><?php echo htmlspecialchars(urldecode($log['route'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['country']); ?></td>
                                    <td><?php echo htmlspecialchars($log['time']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="typecho-pager">
                        <ul id="pagination">
                            <!-- JavaScript will dynamically generate the pagination here -->
                        </ul>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <div id="countryChart" class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">国家访问统计（Top 30）</div>
                        <div class="chart-tabs">
                            <button class="chart-tab active" data-chart="country" data-view="chart">图表</button>
                            <button class="chart-tab" data-chart="country" data-view="list">列表</button>
                        </div>
                    </div>
                    <div class="chart-content" id="countryChartContent"></div>
                    <div class="list-content" id="countryListContent">
                        <div class="stats-list">
                            <?php foreach ($countryStats as $stat): ?>
                                <div class="stats-item">
                                    <span class="stats-label"><?php echo htmlspecialchars($stat['country']); ?></span>
                                    <span class="stats-value"><?php echo $stat['count']; ?> 次访问</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div id="routeChart" class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">路由访问统计（Top 15）</div>
                        <div class="chart-tabs">
                            <button class="chart-tab active" data-chart="route" data-view="chart">图表</button>
                            <button class="chart-tab" data-chart="route" data-view="list">列表</button>
                        </div>
                    </div>
                    <div class="chart-content" id="routeChartContent"></div>
                    <div class="list-content" id="routeListContent">
                        <div class="stats-list">
                            <?php foreach ($routeStats as $stat): ?>
                                <div class="stats-item">
                                    <span class="stats-label"><?php echo htmlspecialchars(urldecode($stat['route'])); ?></span>
                                    <span class="stats-value"><?php echo $stat['count']; ?> 次访问</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="list-view">
                <!-- ... existing list view content ... -->
            </div>
        </div>
    </div>
</div>
</div>

<?php
include 'footer.php';

if (isset($_POST['clean_up'])) {
    $days = intval($_POST['days']);
    VisitorLoggerPro_Plugin::cleanUpOldRecords($days);
    echo "<script>alert('清理操作已完成');</script>";
    header("Location: ?panel=VisitorLoggerPro%2Fpanel.php&page=$page");
    exit;
}
?>