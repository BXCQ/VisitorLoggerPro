<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 引入 Typecho 后台模板
if (!defined('__TYPECHO_ADMIN__')) {
    include 'common.php';
}
include 'header.php';
include 'menu.php';
?>

<!-- 智能加载ECharts：优先CDN，失败时自动回退到本地 -->
<script>
// 为每个资源添加超时加载机制
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

// 加载ECharts的智能回退机制
function loadECharts() {
    return new Promise((resolve, reject) => {
        // 首先尝试CDN，2秒超时
        loadScriptWithTimeout('https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js', 2000)
        .then(() => {
            console.log('✅ ECharts CDN加载成功');
            resolve('cdn');
        })
        .catch(() => {
            console.warn('⚠️ ECharts CDN加载失败，尝试本地文件');
            // CDN失败，立即尝试本地文件
            const localScript = document.createElement('script');
            localScript.src = '../usr/plugins/VisitorLoggerPro/js/echarts.min.js';
        localScript.onload = () => {
            console.log('✅ ECharts 本地文件加载成功');
            resolve('local');
        };
        localScript.onerror = () => {
            console.error('❌ ECharts 本地文件也加载失败');
            reject('both_failed');
        };
        document.head.appendChild(localScript);
        });
    });
}

// 加载Flatpickr的智能回退机制
function loadFlatpickr() {
    return new Promise((resolve, reject) => {
        // 首先尝试CDN，2秒超时
        loadScriptWithTimeout('https://cdn.jsdelivr.net/npm/flatpickr', 2000)
        .then(() => {
            console.log('✅ Flatpickr CDN加载成功');
            // 加载CDN的CSS
            const link = document.createElement('link');
            link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css';
        document.head.appendChild(link);
        resolve('cdn');
        })
        .catch(() => {
            console.warn('⚠️ Flatpickr CDN加载失败，尝试本地文件');
            // CDN失败，立即尝试本地文件
            const localScript = document.createElement('script');
            localScript.src = '../usr/plugins/VisitorLoggerPro/js/flatpickr.js';
        localScript.onload = () => {
            console.log('✅ Flatpickr 本地文件加载成功');
            // 加载本地CSS
            const cssLink = document.createElement('link');
            cssLink.rel = 'stylesheet';
        cssLink.href = '../usr/plugins/VisitorLoggerPro/css/flatpickr.min.css';
        document.head.appendChild(cssLink);
        resolve('local');
        };
        localScript.onerror = () => {
            console.error('❌ Flatpickr 本地文件也加载失败');
            reject('both_failed');
        };
        document.head.appendChild(localScript);
        });
    });
}

// 并行加载所有资源
Promise.allSettled([loadECharts(), loadFlatpickr()]).then(results => {
    console.log('📊 资源加载结果:', results);
    // 确保DOM加载完成
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeApp);
    } else {
        initializeApp();
    }
});

function initializeApp() {
    if (typeof window.startTrendInitialization === 'function') {
        window.startTrendInitialization();
    }
}
</script>

<script>
    // 调试函数
    const DEBUG = false; // 设置为false，禁用调试输出
    function debugLog(message, data = null) {
        if (!DEBUG) return;
        console.log(`[${new Date().toTimeString().split(' ')[0]}] ${message}`, data || '');
    }

    // 错误处理函数
    window.addEventListener('error', function(event) {
        if (DEBUG) {
            console.error(`错误: ${event.message} (${event.filename}:${event.lineno})`);
        }
    });

    // 定义全局初始化函数，供智能加载机制调用
    window.startTrendInitialization = function() {
        debugLog('🟢 开始趋势图表初始化...');

        try {
            // 检查图表容器是否存在
            const trendChartElement = document.getElementById('trendChartContent');

            debugLog('检查图表容器', {
                trend: Boolean(trendChartElement)
            });

            // 检查 ECharts 是否加载
            if (typeof echarts === 'undefined') {
                debugLog('❌ ECharts 仍未加载，等待重试...');
                setTimeout(() => {
                    if (typeof echarts !== 'undefined') {
                        debugLog('✅ ECharts 延迟加载成功');
                        initializeTrendCharts();
                    } else {
                        debugLog('❌ ECharts 最终加载失败');
                        alert('图表库加载失败，请刷新页面重试');
                    }
                }, 1000);
                return;
            } else {
                debugLog('✅ ECharts 已加载');
            }

            function initializeTrendCharts() {
                try {
                    // 为图表容器设置明确的尺寸
                    const element = document.getElementById('trendChartContent');
                    if (element) {
                        element.style.width = '100%';
                        element.style.height = '600px';
                        debugLog('设置趋势图表尺寸为 width: 100%, height: 600px');
                    }

                    // 强制延迟初始化以确保容器已经渲染
                    setTimeout(function() {
                        try {
                            // --- 1. 初始化 ECharts 实例 ---
                            debugLog('正在初始化趋势图表 ECharts 实例...');

                            const initOptions = {
                                renderer: 'canvas',
                                devicePixelRatio: window.devicePixelRatio
                            };

                            let trendChart;

                            try {
                                trendChart = echarts.init(document.getElementById('trendChartContent'), null, initOptions);
                                debugLog('✅ 趋势图表初始化成功');
                            } catch (e) {
                                debugLog('❌ 趋势图表初始化失败', e.message);
                            }

                            // 显示加载中动画
                            if (trendChart) trendChart.showLoading();

                            // --- 2. 定义趋势图表功能函数 ---
                            function fetchTrendData(startDate, endDate) {
                                debugLog('📊 获取趋势数据', {
                                    startDate,
                                    endDate
                                });

                                fetch('../usr/plugins/VisitorLoggerPro/getTrendData.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json'
                                        },
                                        body: JSON.stringify({
                                            startDate,
                                            endDate
                                        })
                                    })
                                    .then(response => {
                                        debugLog('📊 趋势API响应状态', response.status);
                                        return response.json();
                                    })
                                    .then(data => {
                                        debugLog('📊 趋势API返回数据', {
                                            dataLength: data.data ? data.data.length : 0
                                        });

                                        if (data.error) {
                                            debugLog('❌ 趋势API错误', data.error);
                                            return;
                                        }

                                        if (trendChart) {
                                            updateTrendChart(trendChart, data);
                                        }
                                    })
                                    .catch(error => {
                                        debugLog('❌ 趋势数据获取错误', error.message);
                                        if (trendChart) trendChart.hideLoading();
                                    });
                            }

                            function updateTrendChart(chartInstance, responseData) {
                                try {
                                    const data = responseData.data;
                                    const range = responseData.range;
                                    const totals = responseData.totals;

                                    debugLog('更新趋势图表', {
                                        dataCount: data.length,
                                        isSingleDay: range.is_single_day
                                    });

                                    // 隐藏加载动画
                                    chartInstance.hideLoading();

                                    // 更新统计数据显示
                                    if (totals) {
                                        document.getElementById('totalPv').textContent = totals.total_pv.toLocaleString();
                                        document.getElementById('totalUniqueIps').textContent = totals.total_unique_ip.toLocaleString();
                                        document.getElementById('totalUniqueVisitors').textContent = totals.total_unique_visitor.toLocaleString();
                                        document.getElementById('totalSessions').textContent = totals.total_session.toLocaleString();
                                        document.getElementById('statsSummary').style.display = 'flex';
                                    }

                                    if (data.length === 0) {
                                        debugLog('⚠️ 趋势图表没有数据可显示');
                                        chartInstance.setOption({
                                            title: {
                                                text: '暂无数据',
                                                left: 'center',
                                                top: 'center',
                                                textStyle: {
                                                    color: '#999',
                                                    fontSize: 16
                                                }
                                            },
                                            series: []
                                        });
                                        return;
                                    }

                                    const dates = data.map(item => item.date);
                                    const pvCounts = data.map(item => item.pv_count);
                                    const uniqueIpCounts = data.map(item => item.unique_ip_count);
                                    const uniqueVisitorCounts = data.map(item => item.unique_visitor_count);
                                    const sessionCounts = data.map(item => item.session_count);

                                    // 分别计算PV和其他指标的数据范围
                                    const pvMax = Math.max(...pvCounts);
                                    const otherValues = [...uniqueIpCounts, ...uniqueVisitorCounts, ...sessionCounts];
                                    const otherMax = Math.max(...otherValues);
                                    
                                    // 左轴（IP、UV、SV）配置 - 刚好比最大值大的整数
                                    let leftAxisConfig = {};
                                    
                                    // 计算刚好比最大值大的整数作为最大值
                                    const leftMaxValue = Math.ceil(otherMax) + 1;
                                    
                                    // 根据最大值动态计算合适的间隔
                                    let interval;
                                    if (leftMaxValue <= 10) {
                                        interval = 1;
                                    } else if (leftMaxValue <= 50) {
                                        interval = 5;
                                    } else if (leftMaxValue <= 100) {
                                        interval = 10;
                                    } else if (leftMaxValue <= 200) {
                                        interval = 20;
                                    } else if (leftMaxValue <= 500) {
                                        interval = 50;
                                    } else {
                                        interval = 100;
                                    }
                                    
                                    leftAxisConfig = {
                                        min: 0,
                                        max: leftMaxValue,
                                        interval: interval
                                    };
                                    
                                    // 右轴（PV）配置
                                    let rightAxisConfig = {};
                                    
                                    if (pvMax <= 100) {
                                        rightAxisConfig = {
                                            min: 0,
                                            max: Math.max(120, Math.ceil(pvMax * 1.2)),
                                            interval: 10
                                        };
                                    } else if (pvMax <= 500) {
                                        rightAxisConfig = {
                                            min: 0,
                                            max: Math.max(600, Math.ceil(pvMax * 1.2)),
                                            interval: 50
                                        };
                                    } else if (pvMax <= 1000) {
                                        rightAxisConfig = {
                                            min: 0,
                                            max: Math.ceil(pvMax * 1.15),
                                            interval: 100
                                        };
                                    } else if (pvMax <= 5000) {
                                        rightAxisConfig = {
                                            min: 0,
                                            max: Math.ceil(pvMax * 1.1),
                                            interval: 500
                                        };
                                    } else {
                                        rightAxisConfig = {
                                            min: 0,
                                            max: Math.ceil(pvMax * 1.1),
                                            interval: 1000
                                        };
                                    }

                                    const option = {
                                        title: {
                                            text: '访客统计趋势分析',
                                            left: 'center',
                                            top: 10,
                                            textStyle: {
                                                color: '#333',
                                                fontSize: 18
                                            }
                                        },
                                        tooltip: {
                                            trigger: 'axis',
                                            axisPointer: {
                                                type: 'cross'
                                            },
                                            formatter: function(params) {
                                                let html = `<div style="margin: 0px 0 0; line-height: 1.5;">${params[0].axisValue}</div>`;
                                                params.forEach(function(item) {
                                                    html += `<div style="margin: 2px 0 0; line-height: 1.5;">
                                                        <span style="display:inline-block;margin-right:5px;border-radius:10px;width:10px;height:10px;background-color:${item.color};"></span>
                                                        ${item.seriesName}: <strong>${item.value}</strong>
                                                    </div>`;
                                                });
                                                return html;
                                            }
                                        },
                                        legend: {
                                            top: 45,
                                            left: 'center',
                                            data: ['浏览量(PV)', 'IP数', '访客数(UV)', '访问数(SV)']
                                        },
                                        grid: {
                                            left: '8%',
                                            right: '8%',
                                            bottom: '15%',
                                            top: '18%',
                                            containLabel: true
                                        },
                                        xAxis: {
                                            type: 'category',
                                            data: dates,
                                            axisLabel: {
                                                rotate: range.is_single_day ? 0 : 45,
                                                formatter: function(value) {
                                                    return value; // 直接显示日期或时间
                                                }
                                            }
                                        },
                                        yAxis: [{
                                            type: 'value',
                                            name: 'IP/访客/访问数',
                                            position: 'left',
                                            min: leftAxisConfig.min,
                                            max: leftAxisConfig.max,
                                            interval: leftAxisConfig.interval,
                                            axisLabel: {
                                                formatter: function(value) {
                                                    if (value >= 1000) {
                                                        return (value / 1000).toFixed(1) + 'K';
                                                    }
                                                    return value;
                                                },
                                                color: '#3498db'
                                            },
                                            splitLine: {
                                                show: true,
                                                lineStyle: {
                                                    type: 'dashed',
                                                    color: '#e0e6ed',
                                                    width: 1
                                                }
                                            },
                                            axisTick: {
                                                show: true,
                                                inside: false,
                                                length: 4
                                            },
                                            axisLine: {
                                                show: true,
                                                lineStyle: {
                                                    color: '#3498db'
                                                }
                                            }
                                        }, {
                                            type: 'value',
                                            name: '浏览量(PV)',
                                            position: 'right',
                                            min: rightAxisConfig.min,
                                            max: rightAxisConfig.max,
                                            interval: rightAxisConfig.interval,
                                            axisLabel: {
                                                formatter: function(value) {
                                                    if (value >= 1000) {
                                                        return (value / 1000).toFixed(1) + 'K';
                                                    }
                                                    return value;
                                                },
                                                color: '#e74c3c'
                                            },
                                            splitLine: {
                                                show: false
                                            },
                                            axisTick: {
                                                show: true,
                                                inside: false,
                                                length: 4
                                            },
                                            axisLine: {
                                                show: true,
                                                lineStyle: {
                                                    color: '#e74c3c'
                                                }
                                            }
                                        }],
                                        series: [{
                                                name: 'IP数',
                                                type: 'line',
                                                yAxisIndex: 0, // 使用左侧Y轴
                                                data: uniqueIpCounts,
                                                smooth: true,
                                                symbol: 'diamond',
                                                symbolSize: 6,
                                                lineStyle: {
                                                    width: 2
                                                },
                                                itemStyle: {
                                                    color: '#3498db'
                                                },
                                                areaStyle: {
                                                    opacity: 0.15,
                                                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                                            offset: 0,
                                                            color: '#3498db'
                                                        },
                                                        {
                                                            offset: 1,
                                                            color: '#ecf0f1'
                                                        }
                                                    ])
                                                }
                                            },
                                            {
                                                name: '访客数(UV)',
                                                type: 'line',
                                                yAxisIndex: 0, // 使用左侧Y轴
                                                data: uniqueVisitorCounts,
                                                smooth: true,
                                                symbol: 'triangle',
                                                symbolSize: 6,
                                                lineStyle: {
                                                    width: 2
                                                },
                                                itemStyle: {
                                                    color: '#27ae60'
                                                },
                                                areaStyle: {
                                                    opacity: 0.15,
                                                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                                            offset: 0,
                                                            color: '#27ae60'
                                                        },
                                                        {
                                                            offset: 1,
                                                            color: '#ecf0f1'
                                                        }
                                                    ])
                                                }
                                            },
                                            {
                                                name: '访问数(SV)',
                                                type: 'line',
                                                yAxisIndex: 0, // 使用左侧Y轴
                                                data: sessionCounts,
                                                smooth: true,
                                                symbol: 'rect',
                                                symbolSize: 6,
                                                lineStyle: {
                                                    width: 2
                                                },
                                                itemStyle: {
                                                    color: '#f39c12'
                                                },
                                                areaStyle: {
                                                    opacity: 0.15,
                                                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                                            offset: 0,
                                                            color: '#f39c12'
                                                        },
                                                        {
                                                            offset: 1,
                                                            color: '#ecf0f1'
                                                        }
                                                    ])
                                                }
                                            },
                                            {
                                                name: '浏览量(PV)',
                                                type: 'line',
                                                yAxisIndex: 1, // 使用右侧Y轴
                                                data: pvCounts,
                                                smooth: true,
                                                symbol: 'circle',
                                                symbolSize: 6,
                                                lineStyle: {
                                                    width: 2
                                                },
                                                itemStyle: {
                                                    color: '#e74c3c'
                                                },
                                                areaStyle: {
                                                    opacity: 0.1,
                                                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                                            offset: 0,
                                                            color: '#e74c3c'
                                                        },
                                                        {
                                                            offset: 1,
                                                            color: '#ecf0f1'
                                                        }
                                                    ])
                                                }
                                            }
                                        ]
                                    };

                                    chartInstance.setOption(option, true);

                                    // 确保图表大小适应容器
                                    setTimeout(() => chartInstance.resize(), 100);

                                    debugLog('✅ 趋势图表已更新');
                                } catch (e) {
                                    debugLog('❌ 更新趋势图表出错', e.message);
                                }
                            }

                            const dateButtons = document.querySelectorAll('.date-btn');
                            const setActiveButton = (activeBtn) => {
                                dateButtons.forEach(btn => btn.classList.remove('active'));
                                if (activeBtn) activeBtn.classList.add('active');
                                debugLog('设置活跃按钮', activeBtn ? activeBtn.id : 'none');
                            };

                            // --- 3. 初始化 Flatpickr ---
                            debugLog('初始化日期选择器');
                            const flatpickrInstance = flatpickr("#dateRange", {
                                mode: "range",
                                dateFormat: "Y-m-d",
                                onChange: function(selectedDates) {
                                    if (selectedDates.length === 2) {
                                        const start = flatpickr.formatDate(selectedDates[0], "Y-m-d 00:00:00");
                                        const end = flatpickr.formatDate(selectedDates[1], "Y-m-d 23:59:59");
                                        setActiveButton(null);
                                        fetchTrendData(start, end);
                                    }
                                }
                            });
                            debugLog('✅ 日期选择器初始化成功');

                            // --- 4. 绑定事件监听器 ---
                            debugLog('绑定事件监听器');

                            document.getElementById('todayBtn').addEventListener('click', function() {
                                debugLog('点击今天按钮');
                                const today = new Date();
                                const start = flatpickr.formatDate(today, "Y-m-d 00:00:00");
                                const end = flatpickr.formatDate(today, "Y-m-d 23:59:59");
                                flatpickrInstance.setDate([start, end], false);
                                setActiveButton(this);
                                fetchTrendData(start, end);
                            });

                            document.getElementById('last7DaysBtn').addEventListener('click', function() {
                                debugLog('点击最近7天按钮');
                                const today = new Date();
                                const last7 = new Date();
                                last7.setDate(today.getDate() - 6);
                                const start = flatpickr.formatDate(last7, "Y-m-d 00:00:00");
                                const end = flatpickr.formatDate(today, "Y-m-d 23:59:59");
                                flatpickrInstance.setDate([start, end], false);
                                setActiveButton(this);
                                fetchTrendData(start, end);
                            });

                            document.getElementById('last30DaysBtn').addEventListener('click', function() {
                                debugLog('点击最近30天按钮');
                                const today = new Date();
                                const last30 = new Date();
                                last30.setDate(today.getDate() - 29);
                                const start = flatpickr.formatDate(last30, "Y-m-d 00:00:00");
                                const end = flatpickr.formatDate(today, "Y-m-d 23:59:59");
                                flatpickrInstance.setDate([start, end], false);
                                setActiveButton(this);
                                fetchTrendData(start, end);
                            });

                            document.getElementById('allTimeBtn').addEventListener('click', function() {
                                debugLog('点击全部按钮');
                                setActiveButton(this);
                                fetchTrendData('all', 'all');
                            });

                            // 新增快捷按钮事件
                            document.getElementById('yesterdayBtn').addEventListener('click', function() {
                                debugLog('点击昨日按钮');
                                const yesterday = new Date();
                                yesterday.setDate(yesterday.getDate() - 1);
                                const start = flatpickr.formatDate(yesterday, "Y-m-d 00:00:00");
                                const end = flatpickr.formatDate(yesterday, "Y-m-d 23:59:59");
                                flatpickrInstance.setDate([start, end], false);
                                setActiveButton(this);
                                fetchTrendData(start, end);
                            });

                            document.getElementById('thisWeekBtn').addEventListener('click', function() {
                                debugLog('点击本周按钮');
                                const today = new Date();
                                const dayOfWeek = today.getDay();
                                const monday = new Date(today);
                                monday.setDate(today.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
                                const start = flatpickr.formatDate(monday, "Y-m-d 00:00:00");
                                const end = flatpickr.formatDate(today, "Y-m-d 23:59:59");
                                flatpickrInstance.setDate([start, end], false);
                                setActiveButton(this);
                                fetchTrendData(start, end);
                            });

                            document.getElementById('lastWeekBtn').addEventListener('click', function() {
                                debugLog('点击上周按钮');
                                const today = new Date();
                                const dayOfWeek = today.getDay();
                                const lastSunday = new Date(today);
                                lastSunday.setDate(today.getDate() - (dayOfWeek === 0 ? 0 : dayOfWeek));
                                const lastMonday = new Date(lastSunday);
                                lastMonday.setDate(lastSunday.getDate() - 6);
                                const start = flatpickr.formatDate(lastMonday, "Y-m-d 00:00:00");
                                const end = flatpickr.formatDate(lastSunday, "Y-m-d 23:59:59");
                                flatpickrInstance.setDate([start, end], false);
                                setActiveButton(this);
                                fetchTrendData(start, end);
                            });

                            document.getElementById('thisMonthBtn').addEventListener('click', function() {
                                debugLog('点击本月按钮');
                                const today = new Date();
                                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                                const start = flatpickr.formatDate(firstDay, "Y-m-d 00:00:00");
                                const end = flatpickr.formatDate(today, "Y-m-d 23:59:59");
                                flatpickrInstance.setDate([start, end], false);
                                setActiveButton(this);
                                fetchTrendData(start, end);
                            });

                            document.getElementById('lastMonthBtn').addEventListener('click', function() {
                                debugLog('点击上月按钮');
                                const today = new Date();
                                const firstDayLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                                const lastDayLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
                                const start = flatpickr.formatDate(firstDayLastMonth, "Y-m-d 00:00:00");
                                const end = flatpickr.formatDate(lastDayLastMonth, "Y-m-d 23:59:59");
                                flatpickrInstance.setDate([start, end], false);
                                setActiveButton(this);
                                fetchTrendData(start, end);
                            });

                            debugLog('✅ 事件监听器绑定完成');

                            // --- 5. 初始加载数据 ---
                            debugLog('🔄 初始化加载数据 - 点击今天按钮');
                            const todayBtn = document.getElementById('todayBtn');
                            if (todayBtn) {
                                todayBtn.click();
                            } else {
                                debugLog('❌ 找不到今天按钮');
                            }

                            // --- 6. 窗口大小调整 ---
                            window.addEventListener('resize', () => {
                                debugLog('窗口大小改变，调整图表大小');
                                if (trendChart) trendChart.resize();
                            });

                            debugLog('✅ 趋势图表所有初始化步骤完成');

                        } catch (e) {
                            debugLog('❌ 初始化趋势图表时发生错误', e.message);
                        }
                    }, 500); // 延迟500毫秒确保DOM已完全渲染

                } catch (e) {
                    debugLog('❌ initializeTrendCharts函数执行出错', e.message);
                }
            }

            // 开始初始化
            initializeTrendCharts();

        } catch (e) {
            debugLog('❌ 趋势图表主逻辑执行出错', e.message);
        }
    };
</script>

<style>
    .main {
        padding: 20px;
        background-color: #f5f7fa;
        min-height: 100vh;
        box-sizing: border-box;
        width: 100%;
    }

    /* Typecho 1.3 grid.css 将 .container 设为 flex，导致子块宽度随内容收缩 */
    .body.container {
        max-width: 100% !important;
        width: 100%;
        margin: 0 auto;
        padding: 0 20px;
        display: block;
        box-sizing: border-box;
        flex-wrap: nowrap;
    }

    .page-header {
        width: 100%;
        box-sizing: border-box;
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header h2 {
        color: #2c3e50;
        margin: 0;
        font-size: 24px;
        font-weight: 600;
    }

    .nav-links {
        display: flex;
        gap: 12px;
    }

    .nav-link {
        padding: 8px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        text-decoration: none;
        color: #4a5568;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        background: #f8fafc;
    }

    .nav-link:hover {
        background: #e2e8f0;
        color: #2c3e50;
    }

    .nav-link.active {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }

    .trend-section {
        width: 100%;
        box-sizing: border-box;
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .controls-section {
        width: 100%;
        box-sizing: border-box;
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .control-group {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .control-group label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
        min-width: 80px;
    }

    .control-group input {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 14px;
        min-width: 200px;
    }

    .control-group input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    .date-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .date-btn {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
        color: #4a5568;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
    }

    .date-btn:hover {
        background: #e2e8f0;
        color: #2c3e50;
    }

    .date-btn.active {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }

    .chart-container {
        height: 650px;
        width: 100%;
    }

    .chart-container canvas {
        border-radius: 8px;
    }

    .stats-summary {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 20px;
        padding: 16px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }

    .stats-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        min-width: 120px;
    }

    .stats-label {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .stats-value {
        font-size: 20px;
        font-weight: bold;
        color: #3498db;
    }

    /* 指标说明区域样式 */
    .metrics-explanation {
        width: 100%;
        box-sizing: border-box;
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        margin-top: 24px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    .explanation-header {
        text-align: center;
        margin-bottom: 32px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f1f5f9;
    }

    .explanation-header h3 {
        color: #2c3e50;
        margin: 0 0 8px 0;
        font-size: 22px;
        font-weight: 600;
    }

    .explanation-header p {
        color: #64748b;
        margin: 0;
        font-size: 14px;
    }

    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 32px;
    }

    .metric-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 20px;
        transition: all 0.3s ease;
    }

    .metric-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
        border-color: #cbd5e1;
    }

    .metric-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        color: white;
        font-weight: bold;
        font-size: 14px;
    }

    .metric-content h4 {
        color: #2c3e50;
        margin: 0 0 12px 0;
        font-size: 18px;
        font-weight: 600;
    }

    .metric-description p {
        margin: 8px 0;
        line-height: 1.6;
        color: #4a5568;
        font-size: 14px;
    }

    .metric-description code {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 2px 6px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        color: #1e293b;
        display: inline-block;
        margin-top: 4px;
        word-break: break-all;
    }

    .technical-notes {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 20px;
        border-left: 4px solid #3498db;
    }

    .technical-notes h4 {
        color: #2c3e50;
        margin: 0 0 16px 0;
        font-size: 16px;
        font-weight: 600;
    }

    .technical-notes ul {
        margin: 0;
        padding-left: 20px;
    }

    .technical-notes li {
        margin: 8px 0;
        line-height: 1.6;
        color: #4a5568;
        font-size: 14px;
    }

    @media (max-width: 768px) {
        .control-group {
            flex-direction: column;
            align-items: flex-start;
        }

        .control-group input {
            min-width: 100%;
        }

        .date-buttons {
            justify-content: center;
            width: 100%;
        }

        .stats-summary {
            gap: 10px;
        }

        .stats-item {
            min-width: 100px;
        }

        .stats-value {
            font-size: 18px;
        }

        .stats-label {
            font-size: 12px;
        }

        /* 移动端指标说明样式调整 */
        .metrics-explanation {
            padding: 16px;
            margin-top: 16px;
        }

        .explanation-header {
            margin-bottom: 24px;
        }

        .explanation-header h3 {
            font-size: 20px;
        }

        .metrics-grid {
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            padding: 16px;
        }

        .metric-content h4 {
            font-size: 16px;
        }

        .metric-description p {
            font-size: 13px;
        }

        .metric-description code {
            font-size: 11px;
            padding: 1px 4px;
        }

        .technical-notes {
            padding: 16px;
        }

        .technical-notes h4 {
            font-size: 15px;
        }

        .technical-notes li {
            font-size: 13px;
        }
    }
</style>

<div class="main">
    <div class="body container">
        <div class="page-header">
            <h2>趋势分析</h2>
            <div class="nav-links">
                <a href="?panel=VisitorLoggerPro%2Fpanel.php" class="nav-link">访客日志</a>
                <a href="?panel=VisitorLoggerPro%2Ftrend.php" class="nav-link active">趋势分析</a>
            </div>
        </div>

        <div class="controls-section">
            <div class="control-group">
                <label for="dateRange">日期范围:</label>
                <input type="text" id="dateRange" name="dateRange" placeholder="选择日期范围">
                <div class="date-buttons">
                    <button type="button" id="todayBtn" class="date-btn">今天</button>
                    <button type="button" id="yesterdayBtn" class="date-btn">昨日</button>
                    <button type="button" id="last7DaysBtn" class="date-btn">最近7天</button>
                    <button type="button" id="thisWeekBtn" class="date-btn">本周</button>
                    <button type="button" id="lastWeekBtn" class="date-btn">上周</button>
                    <button type="button" id="thisMonthBtn" class="date-btn">本月</button>
                    <button type="button" id="lastMonthBtn" class="date-btn">上月</button>
                    <button type="button" id="last30DaysBtn" class="date-btn">最近30天</button>
                    <button type="button" id="allTimeBtn" class="date-btn">全部</button>
                </div>
            </div>
        </div>

        <div class="trend-section">
            <div class="stats-summary" id="statsSummary" style="display: none;">
                <div class="stats-item">
                    <span class="stats-label">浏览量(PV):</span>
                    <span class="stats-value" id="totalPv">-</span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">IP数:</span>
                    <span class="stats-value" id="totalUniqueIps">-</span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">访客数(UV):</span>
                    <span class="stats-value" id="totalUniqueVisitors">-</span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">访问数(SV):</span>
                    <span class="stats-value" id="totalSessions">-</span>
                </div>
            </div>
            <div class="chart-container" id="trendChartContent"></div>
        </div>

        <!-- 指标说明区域 -->
        <div class="metrics-explanation">
            <div class="explanation-header">
                <h3>📊 统计指标说明</h3>
                <p>以下是四项核心统计指标的详细解释和数据获取方法</p>
            </div>

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon" style="background-color: #e74c3c;">
                        <span>PV</span>
                    </div>
                    <div class="metric-content">
                        <h4>PV (页面浏览量)</h4>
                        <div class="metric-description">
                            <p><strong>概念：</strong>对应 Umami 的 <code>pageviews</code>，每次成功记入的一次页面浏览。</p>
                            <p><strong>统计方法：</strong><code>COUNT(*)</code>。启用「前端埋点」后由浏览器上报，不执行 JS 的爬虫通常不计（对齐 Umami tracker）。</p>
                            <p><strong>获取数据：</strong><code>SELECT COUNT(*) FROM visitor_log WHERE time BETWEEN ? AND ?</code></p>
                        </div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background-color: #3498db;">
                        <span>IP</span>
                    </div>
                    <div class="metric-content">
                        <h4>独立IP数</h4>
                        <div class="metric-description">
                            <p><strong>概念：</strong>指定时间范围内不同 IP 地址数量（本插件附加指标，Umami 面板无直接对应项）。</p>
                            <p><strong>统计方法：</strong>按 IP 去重。同一公网 NAT 下的多人会合并；同一人换网络会拆分，故<strong>不等于</strong>访客数(UV)。</p>
                            <p><strong>获取数据：</strong><code>SELECT COUNT(DISTINCT ip) FROM visitor_log WHERE time BETWEEN ? AND ?</code></p>
                        </div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background-color: #27ae60;">
                        <span>UV</span>
                    </div>
                    <div class="metric-content">
                        <h4>独立访客数 (UV)</h4>
                        <div class="metric-description">
                            <p><strong>概念：</strong>对应 Umami 的 <code>visitors</code>（<code>COUNT DISTINCT session_id</code>）。</p>
                            <p><strong>统计方法：</strong>服务端计算 <code>visitor_id = hash(站点, IP, User-Agent, 月盐)</code>（对照 Umami <code>/api/send</code>）；盐按月轮换。旧数据无该字段时回退 IP+UA。</p>
                            <p><strong>获取数据：</strong><code>COUNT(DISTINCT visitor_id)</code>（本字段即 Umami sessionId；legacy 回退见 API）</p>
                        </div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background-color: #f39c12;">
                        <span>访问</span>
                    </div>
                    <div class="metric-content">
                        <h4>访问次数 (会话数)</h4>
                        <div class="metric-description">
                            <p><strong>概念：</strong>对应 Umami 的 <code>visits</code>（<code>COUNT DISTINCT visit_id</code>）。</p>
                            <p><strong>统计方法：</strong>本插件 <code>session_id</code> 即 Umami visitId；同一 visitor 超过 30 分钟无活动后开启新 visit（对照 <code>iat &gt; 1800</code>）。旧数据无该字段时按同一 IP 间隔 &gt;30 分钟切分。</p>
                            <p><strong>获取数据：</strong><code>COUNT(DISTINCT session_id)</code> + 旧数据窗口函数回退</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="technical-notes">
                <h4>🔧 技术实现要点</h4>
                <ul>
                    <li><strong>推荐模式：</strong>插件设置选择「前端埋点」。算法对照开源 <a href="https://github.com/umami-software/umami" target="_blank" rel="noopener">Umami</a>（<code>/api/send</code> + tracker），<strong>不使用追踪 Cookie</strong></li>
                    <li><strong>真实访客筛查：</strong>① 前端埋点要求执行 JS；② 服务端内置 <a href="https://github.com/omrilotan/isbot" target="_blank" rel="noopener">isbot</a> 规则（与 Umami 相同思路）过滤爬虫/自动化 UA；③ 可配置 UA 黑名单与忽略 IP；④ 已登录管理员不计入；⑤ 尊重浏览器 DNT / <code>localStorage.vlp.disabled</code></li>
                    <li><strong>字段映射：</strong><code>visitor_id</code> ← Umami sessionId（访客）；<code>session_id</code> ← Umami visitId（访问）；前端用 <code>X-VLP-Cache</code> 维持 visit（对齐 <code>x-umami-cache</code>）</li>
                    <li><strong>与第三方差异：</strong>自托管全量日志、不做抽样；广告拦截可能导致略低于百度统计等第三方脚本</li>
                    <li><strong>兼容旧数据：</strong>升级前无身份字段的记录仍按 IP+UA / IP 间隔回退统计</li>
                    <li><strong>隐私：</strong>身份由服务端哈希生成，不写访客 Cookie、不跨站追踪；列表中的 IP 仍做匿名化展示</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
