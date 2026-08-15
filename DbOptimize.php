<?php
/**
 * 访客日志表查询优化：索引保障与慢查询规避
 *
 * @package VisitorLoggerPro
 */

if (!defined('__TYPECHO_ROOT_DIR__') && !defined('__VISITORLOGGERPRO_DBOPT__')) {
    define('__VISITORLOGGERPRO_DBOPT__', true);
}

class VisitorLoggerPro_DbOptimize
{
    /** @var bool 当前请求内是否已检查过索引 */
    private static $indexesChecked = false;

    /**
     * 确保 visitor_log 表具备常用查询索引（幂等，可安全重复调用）
     *
     * @param mixed  $db     Typecho Db 实例
     * @param string $prefix 表前缀
     * @return void
     */
    public static function ensureIndexes($db, $prefix)
    {
        if (self::$indexesChecked) {
            return;
        }
        self::$indexesChecked = true;

        $table = $prefix . 'visitor_log';

        try {
            $existing = array();
            $rows = $db->fetchAll("SHOW INDEX FROM `{$table}`");
            foreach ($rows as $row) {
                if (!empty($row['Key_name'])) {
                    $existing[$row['Key_name']] = true;
                }
            }

            // 时间范围筛选（统计/趋势最关键）
            if (empty($existing['idx_time'])) {
                $db->query("ALTER TABLE `{$table}` ADD INDEX `idx_time` (`time`)");
            }

            // 按时间范围聚合国家/地区
            if (empty($existing['idx_time_country'])) {
                $db->query("ALTER TABLE `{$table}` ADD INDEX `idx_time_country` (`time`, `country`)");
            }

            // IP 精确匹配、删除、会话统计
            if (empty($existing['idx_ip'])) {
                $db->query("ALTER TABLE `{$table}` ADD INDEX `idx_ip` (`ip`)");
            }

            // 时间 + IP，利于独立 IP / 会话类查询
            if (empty($existing['idx_time_ip'])) {
                $db->query("ALTER TABLE `{$table}` ADD INDEX `idx_time_ip` (`time`, `ip`)");
            }

            // Cookie 访客 / 会话（前端埋点口径）
            if (empty($existing['idx_time_visitor'])) {
                $db->query("ALTER TABLE `{$table}` ADD INDEX `idx_time_visitor` (`time`, `visitor_id`)");
            }
            if (empty($existing['idx_time_session'])) {
                $db->query("ALTER TABLE `{$table}` ADD INDEX `idx_time_session` (`time`, `session_id`)");
            }
        } catch (Exception $e) {
            // SQLite 或不支持 ALTER 的环境：忽略，避免影响正常统计
            error_log('VisitorLoggerPro ensureIndexes: ' . $e->getMessage());
        }
    }

    /**
     * 检测 visitor_id / session_id 字段是否可用
     */
    public static function hasIdentityColumns($db, $prefix)
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $columns = $db->fetchAll("SHOW COLUMNS FROM `{$prefix}visitor_log`");
            $fields = array();
            foreach ($columns as $column) {
                $fields[$column['Field']] = true;
            }
            $cached = !empty($fields['visitor_id']) && !empty($fields['session_id']);
        } catch (Exception $e) {
            $cached = false;
        }
        return $cached;
    }
}
