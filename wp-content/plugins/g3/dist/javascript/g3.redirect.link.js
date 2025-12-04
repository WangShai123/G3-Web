document.addEventListener('DOMContentLoaded', function () {

    const links = document.querySelectorAll('a');

    links.forEach(link => {
        link.addEventListener('click', event => {

            // 始终获取 <a> 元素（防止点击子元素）
            const a = event.currentTarget;

            // 当前站点 origin，包括协议，例如 https://example.com
            const origin = location.origin;

            // 判断是否为外链
            if (!a.href.startsWith(origin)) {
                event.preventDefault();

                const redirectUrl = encodeURIComponent(a.href);

                // 正确拼接跳转地址
                location.href = `${origin}/redirect/go/${redirectUrl}`;
            }
        });
    });
});
