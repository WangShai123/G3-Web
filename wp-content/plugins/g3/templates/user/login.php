<?php get_header(); ?>
login
<div id="app"></div>

<script>
    (function () {
        const app = document.querySelector('#app');

        // ✅ 安全生成 UUID v4（兼容旧版 Safari）
        function generateLoginHash() {
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

        let loginHash;

        // 工具函数：发送 JSON POST 请求
        async function postJson(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            return res.json();
        }

        // 初始化登录流程
        async function initLogin() {
            // 1. 生成唯一登录凭证（UUID v4）
            // if (!window.crypto || !crypto.randomUUID) {
            //     app.innerHTML = '<p style="color:red;">浏览器不支持安全随机数生成，请升级浏览器。</p>';
            //     return;
            // }
            // loginHash = crypto.randomUUID();
            try {
                loginHash = generateLoginHash();
            } catch (err) {
                app.innerHTML = `<p style="color:red;text-align:center;">${err.message}</p>`;
                return;
            }

            // 2. 显示加载状态
            app.innerHTML = '<p>正在生成登录二维码...</p>';

            try {
                // 3. 请求后端创建临时二维码
                const qrRes = await postJson('/wp-json/api/v1/auth/wechat/login/subscribe/qrcode', {
                    hash: loginHash
                });

                if (qrRes.code !== 200 || !qrRes.data?.url) {
                    throw new Error(qrRes.message || '获取二维码失败');
                }

                // 4. 渲染二维码图片 + 状态提示
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

                // 5. 开始轮询验证
                // startPolling();
                setTimeout(() => {
                    startPolling(loginHash);
                }, 1000); // 👈 关键：避免立即轮询
            } catch (err) {
                console.error('初始化失败:', err);
                app.innerHTML = `<p style="color:red; text-align:center;">❌ ${err.message}</p>`;
            }
        }

        // 轮询验证登录状态
        function startPolling() {
            const statusEl = document.querySelector('#status');
            const pollInterval = 2000; // 2秒轮询一次

            const poll = async () => {
                try {
                    const res = await postJson('/wp-json/api/v1/auth/wechat/login/subscribe/validate', {
                        hash: loginHash
                    });

                    if (res.success === true) {
                        // 登录成功（后端已设置 Cookie）
                        statusEl.textContent = '✅ 登录成功！';
                        statusEl.style.color = 'green';
                        setTimeout(() => {
                            window.location.href = '/dashboard'; // ← 修改为你自己的跳转地址
                        }, 500);
                    } else {
                        console.log(res);
                        console.log(res.message);
                        // if (res.message && res.message.includes('expired')) {
                        //     statusEl.textContent = '⚠️ 二维码已过期';
                        //     statusEl.style.color = '#d97706';
                        //     return; // 停止轮询
                        // }
                        // 继续等待（Quest pending）
                        setTimeout(poll, pollInterval);
                    }
                } catch (err) {
                    console.warn('轮询出错，重试中...', err);
                    setTimeout(poll, pollInterval * 2); // 稍长重试
                }
            };

            poll();
        }

        // 启动整个流程
        initLogin();
    })();
</script>

<?php get_footer(); ?>