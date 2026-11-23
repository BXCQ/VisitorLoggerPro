/**
 * VisitorLoggerPro 前端埋点
 * 对照 Umami tracker（src/tracker/index.ts）：
 * - 不写访客/会话 Cookie
 * - 仅上报页面元数据；身份由服务端按 IP+UA+月盐计算
 * - 用内存中的 cache token（对应 x-umami-cache）维持 30 分钟 visit
 * - 尊重 DNT / localStorage 退出（对齐 Umami do-not-track / umami.disabled）
 */
(function () {
  'use strict';

  var TAG = '[VisitorLoggerPro]';
  var cfg = window.__VLP_TRACKER__ || {};
  var endpoint = cfg.endpoint;
  var cache;

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

  function hasDoNotTrack() {
    var dnt = navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack;
    return dnt === 1 || dnt === '1' || dnt === 'yes';
  }

  function trackingDisabled() {
    try {
      if (window.localStorage && localStorage.getItem('vlp.disabled')) {
        return true;
      }
    } catch (e) {
      /* private mode */
    }
    if (cfg.respectDnt !== false && hasDoNotTrack()) {
      return true;
    }
    return false;
  }

  log('埋点脚本已加载（Umami 口径）', {
    mode: cfg.mode || 'unknown',
    endpoint: endpoint,
    page: location.href,
    dnt: hasDoNotTrack()
  });

  function shouldSkipPath() {
    var path = location.pathname || '/';
    return path.indexOf('/admin') === 0 || path.indexOf('/usr/plugins') !== -1;
  }

  function getPayload() {
    if (shouldSkipPath()) {
      return null;
    }
    var origin = location.origin || '';
    var ref = document.referrer || '';
    if (origin && (ref === origin || ref.indexOf(origin + '/') === 0)) {
      ref = ref.slice(origin.length);
    }
    return {
      type: 'event',
      payload: {
        url: location.href,
        route: location.pathname || '/',
        referrer: ref,
        title: document.title || '',
        screen:
          window.screen && window.screen.width
            ? window.screen.width + 'x' + window.screen.height
            : '',
        language: (navigator.language || '').slice(0, 16),
        hostname: location.hostname || ''
      }
    };
  }

  function send(bodyObj) {
    var body = JSON.stringify(bodyObj);
    var headers = { 'Content-Type': 'application/json' };
    if (cache) {
      headers['X-VLP-Cache'] = cache;
    }

    log('正在上报 pageview', {
      route: bodyObj.payload && bodyObj.payload.route,
      hasCache: !!cache
    });

    try {
      fetch(endpoint, {
        method: 'POST',
        headers: headers,
        body: body,
        keepalive: true,
        credentials: 'same-origin'
      })
        .then(function (res) {
          return res
            .json()
            .catch(function () {
              return {};
            })
            .then(function (json) {
              if (res.ok) {
                if (json && json.cache) {
                  cache = json.cache;
                }
                log('上报成功', {
                  visitor_id: json.visitor_id,
                  session_id: json.session_id,
                  result: json.result
                });
              } else {
                warn('上报失败', res.status, json);
              }
            });
        })
        .catch(function (err) {
          warn('上报请求异常', err);
        });
    } catch (e) {
      warn('无法发起上报', e);
    }
  }

  function track() {
    if (trackingDisabled()) {
      log('已跳过：DNT 或 localStorage vlp.disabled');
      return;
    }
    if (cfg.mode === 'hybrid') {
      log('hybrid 模式：由服务端按 Umami 口径记日志，前端不上报');
      return;
    }
    var data = getPayload();
    if (!data) {
      log('当前路径跳过统计', location.pathname);
      return;
    }
    send(data);
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(track, 0);
  } else {
    document.addEventListener('DOMContentLoaded', track);
  }
})();
