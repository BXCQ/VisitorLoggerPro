# Typecho 访客统计插件-VisitorLogger-Pro
这是一个为 Typecho 博客系统开发的访客统计插件，基于原版的VistorLogger修改版本。该插件提供了详细的访问统计功能，包括访问国家/地区统计、IP分布等信息，并进行了隐私保护处理。


## 功能特点

- 访问国家/地区统计（Top 20）
- IP分布统计（已匿名化处理）
- 日期筛选功能
- 多种图表展示方式（环形图、玫瑰图、3D图）
- 列表视图支持
- 移动端适配
- 隐私保护（IP地址匿名化）
- 管理员访问自动排除
![后台统计](https://github.com/user-attachments/assets/abf6e988-8541-4f6d-9fef-dceb1a27ec8e)
![网站页面](https://github.com/user-attachments/assets/7572ba77-88ff-44e8-9b20-0b148ee73ea8)
![列表](https://github.com/user-attachments/assets/f3b4aaea-4b2b-4e75-becf-a4eae04e5f71)
![移动端显示](https://github.com/user-attachments/assets/39bfdcdd-012c-48ef-b8df-e25566f93454)

  

## 安装方法

1. 下载插件文件
2. 在本地解压后把VisitorLogger改为VisitorLoggerPro
3. 将改后的文件上传到/usr/plugins目录下
4. 插件名为VisitorLoggerPro
5. 在 Typecho 后台启用插件
6. 要把该文件`visitor-stats.php`移动到handsome主题根目录

## 使用说明

### 基本使用

1. 在 Typecho 后台创建新页面
2. 在页面模板中选择"访客统计"
3. 发布页面即可看到统计效果
4. 创建新页面，选择"访客统计"模板

### 功能说明

#### 日期筛选
- 可以选择特定时间范围查看统计数据
- 支持重置到全部时间范围

#### 图表展示
- 支持三种图表样式：环形图、玫瑰图、3D图
- 可以切换列表视图查看详细数据

#### 数据说明
- 统计数据已进行匿名化处理，保护访客隐私
- 自动排除管理员访问记录
- 显示访问次数和占比信息

## 隐私保护

本插件已实现以下隐私保护措施：
- IP地址匿名化处理（只显示前两段）
- 明确的隐私声明
- 符合相关法律法规要求

## 技术实现

- 前端：ECharts 图表库
- 后端：PHP + MySQL
- 数据存储：Typecho 数据库

## 作者信息

- 作者：BXCQ
- 博客：https://blog.ybyq.wang/
- GitHub：https://github.com/BXCQ

## 许可证

MIT License

## 更新日志

### v1.0.0 (2024-04-23)
- 初始版本发布
- 实现基本统计功能
- 添加隐私保护措施
- 优化移动端显示
