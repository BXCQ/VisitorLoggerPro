<?php
/**
 * Umami 风格访客/访问标识（对照 umami-software/umami 源码）
 *
 * Umami /api/send：
 * - sessionId（visitors）= uuid(websiteId, ip, userAgent, monthSalt)
 * - visitId（visits）= 缓存中的 visitId，或 uuid(sessionId, hourSalt)；无活动超过 30 分钟则刷新
 * - 统计：pageviews=事件数，visitors=DISTINCT session_id，visits=DISTINCT visit_id
 *
 * 本插件字段映射：
 * - visitor_id  ← Umami sessionId（独立访客）
 * - session_id  ← Umami visitId（访问次数）
 */

if (!class_exists('VisitorLoggerPro_UmamiIdentity', false)) {
    class VisitorLoggerPro_UmamiIdentity
    {
        const VISIT_TIMEOUT = 1800; // 30 分钟，与 Umami 一致

        /**
         * 派生密钥（不依赖外部配置；稳定即可）
         */
        public static function secret()
        {
            $seed = 'VisitorLoggerPro';
            try {
                if (class_exists('Helper') || class_exists('\\Helper')) {
                    $options = Helper::options();
                    if (!empty($options->secret)) {
                        $seed .= (string)$options->secret;
                    } elseif (!empty($options->siteUrl)) {
                        $seed .= (string)$options->siteUrl;
                    }
                }
            } catch (Exception $e) {
                // ignore
            }
            return hash('sha512', $seed);
        }

        /**
         * 对应 Umami hash(...args)
         */
        public static function hashArgs()
        {
            $args = func_get_args();
            return hash('sha512', implode('', $args));
        }

        /**
         * 确定性 ID（对应 Umami uuid(...args) = uuidv5(hash(...args, secret), DNS)）
         * 输出 UUID 形态字符串，便于入库与去重。
         */
        public static function uuidFrom()
        {
            $args = func_get_args();
            $args[] = self::secret();
            $digest = self::hashArgs(...$args);
            // 取 sha256 前 32 hex，格式化为 UUID
            $h = substr(hash('sha256', $digest), 0, 32);
            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($h, 0, 8),
                substr($h, 8, 4),
                substr($h, 12, 4),
                substr($h, 16, 4),
                substr($h, 20, 12)
            );
        }

        /**
         * 月盐（Umami 默认 SALT_ROTATION=month → startOfMonth(...).toUTCString()）
         */
        public static function monthSalt($timestamp = null)
        {
            $ts = $timestamp === null ? time() : (int)$timestamp;
            // 近似 Date#toUTCString 的月初：用 GMT 固定格式
            $utc = gmdate('D, d M Y H:i:s', gmmktime(0, 0, 0, (int)gmdate('n', $ts), 1, (int)gmdate('Y', $ts))) . ' GMT';
            return self::hashArgs($utc);
        }

        /**
         * 小时盐（Umami visitSalt = hash(startOfHour(...).toUTCString())）
         */
        public static function hourSalt($timestamp = null)
        {
            $ts = $timestamp === null ? time() : (int)$timestamp;
            $utc = gmdate('D, d M Y H:i:s', $ts - ($ts % 3600)) . ' GMT';
            return self::hashArgs($utc);
        }

        /**
         * Umami sessionId → 本插件 visitor_id
         */
        public static function makeVisitorId($websiteKey, $ip, $userAgent, $timestamp = null)
        {
            return self::uuidFrom(
                (string)$websiteKey,
                (string)$ip,
                (string)$userAgent,
                self::monthSalt($timestamp)
            );
        }

        /**
         * Umami visitId（无缓存时）→ 本插件 session_id
         */
        public static function makeVisitId($visitorId, $timestamp = null)
        {
            return self::uuidFrom((string)$visitorId, self::hourSalt($timestamp));
        }

        /**
         * 解析 / 刷新 visit（对照 Umami iat + 1800s）
         *
         * 说明：Umami 源码超时后仍用 uuid(sessionId, hourSalt)，同一小时内可能得到相同 visitId。
         * 这里在超时刷新时额外混入 now，确保 30 分钟超时会落到新的 visit（更符合其文档中的 visit 语义）。
         *
         * @param array|null $cache {visitor_id, session_id, iat}
         * @return array {visitor_id, session_id, iat}
         */
        public static function resolveVisit($visitorId, $cache = null, $now = null)
        {
            $now = $now === null ? time() : (int)$now;
            $visitorId = (string)$visitorId;

            $visitId = null;
            $iat = $now;

            if (is_array($cache)
                && !empty($cache['visitor_id'])
                && $cache['visitor_id'] === $visitorId
                && !empty($cache['session_id'])
                && isset($cache['iat'])
            ) {
                $visitId = (string)$cache['session_id'];
                $iat = (int)$cache['iat'];
                if ($now - $iat > self::VISIT_TIMEOUT) {
                    // 超时：强制新 visit（避免同小时盐导致 visitId 不变）
                    $visitId = self::uuidFrom($visitorId, self::hourSalt($now), (string)$now);
                    $iat = $now;
                }
            } else {
                $visitId = self::makeVisitId($visitorId, $now);
                $iat = $now;
            }

            return array(
                'visitor_id' => $visitorId,
                'session_id' => $visitId,
                'iat' => $iat
            );
        }

        /**
         * 签发缓存 token（对应 Umami x-umami-cache JWT，此处用 HMAC JSON）
         */
        public static function encodeCache(array $payload)
        {
            $body = json_encode(array(
                'visitor_id' => (string)$payload['visitor_id'],
                'session_id' => (string)$payload['session_id'],
                'iat' => (int)$payload['iat']
            ), JSON_UNESCAPED_SLASHES);
            $sig = hash_hmac('sha256', $body, self::secret());
            return rtrim(strtr(base64_encode($body . '.' . $sig), '+/', '-_'), '=');
        }

        /**
         * @return array|null
         */
        public static function decodeCache($token)
        {
            if (!is_string($token) || $token === '') {
                return null;
            }
            $pad = strlen($token) % 4;
            if ($pad) {
                $token .= str_repeat('=', 4 - $pad);
            }
            $raw = base64_decode(strtr($token, '-_', '+/'), true);
            if ($raw === false || strpos($raw, '.') === false) {
                return null;
            }
            list($body, $sig) = explode('.', $raw, 2);
            $expect = hash_hmac('sha256', $body, self::secret());
            if (!hash_equals($expect, $sig)) {
                return null;
            }
            $data = json_decode($body, true);
            if (!is_array($data) || empty($data['visitor_id']) || empty($data['session_id']) || !isset($data['iat'])) {
                return null;
            }
            return array(
                'visitor_id' => (string)$data['visitor_id'],
                'session_id' => (string)$data['session_id'],
                'iat' => (int)$data['iat']
            );
        }

        /**
         * 站点键（对应 Umami websiteId）
         */
        public static function websiteKey()
        {
            try {
                if (class_exists('Helper') || class_exists('\\Helper')) {
                    $options = Helper::options();
                    if (!empty($options->siteUrl)) {
                        return (string)$options->siteUrl;
                    }
                }
            } catch (Exception $e) {
            }
            return 'VisitorLoggerPro';
        }
    }
}
