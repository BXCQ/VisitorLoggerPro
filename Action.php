<?php

/**
 * 访客统计操作类
 *
 * @package custom
 * @xuan
 * @version 2.0.4
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 加载兼容适配器
require_once dirname(__FILE__) . '/adapter.php';

class VisitorLogger_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $db;
    private $options;

    /**
     * 初始化
     */
    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->db = Typecho_Db::get();
        $this->options = Helper::options();
    }

    /**
     * 获取当前用户组件（兼容 Typecho 1.2 / 1.3）
     */
    private function currentUser()
    {
        if (class_exists('\\Widget\\User')) {
            return \Widget\User::alloc();
        }

        if (method_exists('Typecho_Widget', 'widget')) {
            return Typecho_Widget::widget('Widget_User');
        }

        // 极旧写法兜底
        return $this->widget('Widget_User');
    }

    /**
     * 校验管理员权限
     */
    private function requireAdministrator()
    {
        if (!$this->currentUser()->pass('administrator', true)) {
            throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
        }
    }

    /**
     * 渲染访客统计页面
     */
    public function render()
    {
        $options = Helper::options();
        if (!$options->plugin('VisitorLoggerPro')->enableStats) {
            throw new Typecho_Widget_Exception(_t('访客统计功能未启用'));
        }

        $this->requireAdministrator();

        // 渲染模板
        require_once dirname(__FILE__) . '/visitor-stats.php';
    }

    /**
     * 获取统计数据
     */
    public function stats()
    {
        $this->requireAdministrator();

        $db = Typecho_Db::get();

        // 获取访客统计数据
        $stats = array(
            'total' => $db->fetchObject($db->select('COUNT(*) AS total')->from('table.visitor_log'))->total,
            'today' => $db->fetchObject($db->select('COUNT(*) AS total')
                ->from('table.visitor_log')
                ->where('time >= ?', strtotime('today')))->total,
            'yesterday' => $db->fetchObject($db->select('COUNT(*) AS total')
                ->from('table.visitor_log')
                ->where(
                    'time >= ? AND time < ?',
                    strtotime('yesterday'),
                    strtotime('today')
                ))->total,
            'countries' => $db->fetchAll($db->select('country, COUNT(*) AS count')
                ->from('table.visitor_log')
                ->group('country')
                ->order('count', Typecho_Db::SORT_DESC)
                ->limit(20)),
            'routes' => $db->fetchAll($db->select('route, COUNT(*) AS count')
                ->from('table.visitor_log')
                ->group('route')
                ->order('count', Typecho_Db::SORT_DESC)
                ->limit(15))
        );

        // 输出 JSON 数据
        $this->response->setContentType('application/json');
        echo json_encode($stats, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 执行函数
     */
    public function execute()
    {
        // 验证权限
        if (!$this->currentUser()->pass('administrator', true)) {
            $this->response->setStatus(403);
            $this->response->throwJson(array('code' => 0, 'message' => '无权限操作'));
        }
    }

    /**
     * 获取访问统计数据
     */
    public function getStats()
    {
        $response = array();
        $prefix = $this->db->getPrefix();

        // 获取国家统计
        $countryStats = $this->db->fetchAll($this->db->select('country, COUNT(*) as count')
            ->from($prefix . 'visitor_log')
            ->group('country')
            ->order('count', Typecho_Db::SORT_DESC));

        // 处理数据
        $response['countries'] = $countryStats;

        // 返回JSON格式
        $this->response->throwJson(array(
            'code' => 1,
            'data' => $response
        ));
    }

    /**
     * 绑定动作
     */
    public function action()
    {
        $this->on($this->request->is('do=stats'))->getStats();
        $this->on($this->request->is('render'))->render();
    }
}
