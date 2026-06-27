<?php
if (is_user_logged_in()) return;
get_header();
?>

<div id="app"></div>

<script>
    (function () {
        const app = document.querySelector('#app');
        const subscribeCookie = 'g3_wechat_login_hash';

        // init login
        async function initLogin() {
            let hash = getCookie(subscribeCookie);
            // generate UUID
            if (!hash) {
                try {
                    hash = UUID();
                    // sync to cache expiration
                    setCookie(subscribeCookie, hash, 1800);
                } catch (err) {
                    app.innerHTML = `<p style="color:red;text-align:center;">${err.message}</p>`;
                    return;
                }
            }

            // display loading status
            app.innerHTML = '<p>正在生成登录二维码...</p>';

            try {
                // generate qrcode
                const qrRes = await postJson('/wp-json/api/v1/auth/wechat/subscribe/qrcode', {
                    hash: 'login:' + hash
                });

                if (qrRes.code !== 200 || !qrRes.data?.url) {
                    throw new Error(qrRes.message || '获取二维码失败');
                }

                // render qrcode + status
                app.innerHTML = `
        <h3 style="text-align:center; margin-bottom:16px;">请使用微信扫码登录</h3>
        <div style="text-align:center;">
          <img 
            src="${qrRes.data.url}" 
            alt="微信登录二维码"
            style="width:220px; height:auto; border:1px solid #eee; border-radius:8px;"
          />
        </div>
        <p id="status" style="text-align:center; color:#666; margin-top:12px;">扫码后自动登录...</p>
      `;

                // delay polling validate
                setTimeout(() => {
                    startPolling(hash);
                }, 2000);
            } catch (err) {
                console.error('init failed:', err);
                app.innerHTML = `<p style="color:red; text-align:center;">❌ ${err.message}</p>`;
            }
        }

        function startPolling(hash) {
            const statusEl = document.querySelector('#status');
            // 3 seconds interval
            const pollInterval = 3000;

            const poll = async () => {
                try {
                    const res = await postJson('/wp-json/api/v1/auth/wechat/subscribe/validate', {
                        hash: 'login:' + hash
                    });

                    if (res.success === true) {
                        statusEl.textContent = res.message;
                        statusEl.style.color = 'green';
                        deleteCookie(subscribeCookie);
                        setTimeout(() => {
                            window.location.href = '/dashboard';
                        }, 500);
                    } else {
                        if (res.status && res.status === 'expired') {
                            statusEl.textContent = res.message;
                            statusEl.style.color = '#d97706';
                            deleteCookie(subscribeCookie);
                            // stop polling
                            return;
                        }
                        // pending
                        setTimeout(poll, pollInterval);
                    }
                } catch (err) {
                    console.warn('Polling error, try later...', err);
                    setTimeout(poll, pollInterval * 2);
                }
            };

            poll();
        }

        // init
        initLogin();

        /**************************************************
         * utility function
         **************************************************/
        // send JSON POST request
        async function postJson(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            return res.json();
        }
        // generate UUID v4, Compatible with Safari
        function UUID() {
            if (typeof crypto.randomUUID === 'function') {
                return crypto.randomUUID();
            }

            // 回退实现
            const bytes = new Uint8Array(16);
            if (typeof crypto.getRandomValues !== 'function') {
                throw new Error('Your browser is too old to support secure login.');
            }
            crypto.getRandomValues(bytes);
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            const hex = Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
            return [
                hex.slice(0, 8),
                hex.slice(8, 12),
                hex.slice(12, 16),
                hex.slice(16, 20),
                hex.slice(20, 32)
            ].join('-');
        }
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        }
        function setCookie(name, value, seconds) {
            const expires = new Date(Date.now() + seconds * 1000);
            document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Strict`;
        }
        function deleteCookie(name) {
            document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;`;
        }
    })();
</script>

<?php get_footer(); ?>