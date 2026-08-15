/**
 * VisitorLoggerPro 前端埋点（接近 Umami / Matomo / 百度统计口径）
 * - visitor_id: 一年期第一方 Cookie
 * - session_id: 30 分钟滚动会话 Cookie
 */
(function () {
  'use strict';

  var TAG = '[VisitorLoggerPro]';
  var cfg = window.__VLP_TRACKER__ || {};
  var endpoint = cfg.endpoint;

  function log() {
    if (typeof console === 'undefined' || !console.log) {
      return;
    }
    var args = Array.prototype.slice.call(arguments);
    args.unshift(TAG);
    console.log.apply(console, args);
  }

  function warn() {
    if (typeof console === 'undefined' || !console.warn) {
      return;
    }
    var args = Array.prototype.slice.call(arguments);
    args.unshift(TAG);
    console.warn.apply(console, args);
  }

  if (!endpoint) {
    warn('未找到 window.__VLP_TRACKER__.endpoint，埋点未启动');
    return;
  }

  log('埋点脚本已加载', {
    mode: cfg.mode || 'unknown',
    endpoint: endpoint,
    page: location.href
  });

  var UID_KEY = '_vlp_uid';
  var SID_KEY = '_vlp_sid';
  var UID_DAYS = 365;
  var SID_MINUTES = 30;

  function uuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
      return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      var v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  function readCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  function writeCookie(name, value, maxAgeSeconds) {
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie =
      name +
      '=' +
      encodeURIComponent(value) +
      '; path=/' +
      '; max-age=' +
      maxAgeSeconds +
      '; SameSite=Lax' +
      secure;
  }

  function getVisitorId() {
    var id = readCookie(UID_KEY);
    if (!id) {
      id = uuid();
    }
    writeCookie(UID_KEY, id, UID_DAYS * 86400);
    return id;
  }

  function getSessionId() {
    var id = readCookie(SID_KEY);
    if (!id) {
      id = uuid();
    }
    // 每次访问刷新 30 分钟超时（滚动会话，与 Umami/Matomo 常见口径一致）
    writeCookie(SID_KEY, id, SID_MINUTES * 60);
    return id;
  }

  function payload() {
    var path = location.pathname + location.search;
    if (path.indexOf('/admin') === 0 || path.indexOf('/usr/plugins') !== -1) {
      return null;
    }

    return {
      visitor_id: getVisitorId(),
      session_id: getSessionId(),
      route: location.pathname || '/',
      referrer: document.referrer || '',
      screen: (window.screen && window.screen.width ? window.screen.width + 'x' + window.screen.height : ''),
      language: (navigator.language || '').slice(0, 16),
      url: location.href
    };
  }

  function send(data) {
    var body = JSON.stringify(data);
    log('正在上报 pageview', {
      route: data.route,
      visitor_id: data.visitor_id,
      session_id: data.session_id,
      referrer: data.referrer || '(空)'
    });

    try {
      if (navigator.sendBeacon) {
        var blob = new Blob([body], { type: 'application/json' });
        if (navigator.sendBeacon(endpoint, blob)) {
          log('上报已发送（sendBeacon）');
          return;
        }
      }
    } catch (e) {
      warn('sendBeacon 失败，改用 fetch', e);
    }

    try {
      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body,
        keepalive: true,
        credentials: 'same-origin'
      })
        .then(function (res) {
          return res.json().catch(function () {
            return {};
          }).then(function (json) {
            if (res.ok) {
              log('上报成功（fetch）', json);
            } else {
              warn('上报失败（fetch）', res.status, json);
            }
          });
        })
        .catch(function (err) {
          warn('上报请求异常', err);
        });
    } catch (e2) {
      warn('无法发起上报', e2);
    }
  }

  function track() {
    // hybrid：只维护 Cookie，由服务端 header 钩子写入，避免双计
    if (cfg.mode === 'hybrid') {
      var vid = getVisitorId();
      var sid = getSessionId();
      log('hybrid 模式：仅刷新 Cookie，不上报 beacon', {
        visitor_id: vid,
        session_id: sid
      });
      return;
    }
    var data = payload();
    if (data) {
      send(data);
    } else {
      log('当前路径跳过统计', location.pathname);
    }
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(track, 0);
  } else {
    document.addEventListener('DOMContentLoaded', track);
  }
})();
