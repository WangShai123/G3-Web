document.addEventListener('DOMContentLoaded', function () {
    if (location.pathname.startsWith('/redirect/go/')) {
        return;
    }

    document.addEventListener('click', function (event) {
        // 确保点击的是 <a> 或其子元素
        const a = event.target.closest('a');
        if (!a || !a.href) return;

        // 跳过站内链接、锚点、电话/邮件等
        if (a.hostname === location.hostname ||
            a.href.startsWith('#') ||
            a.protocol === 'tel:' ||
            a.protocol === 'mailto:') {
            return;
        }

        // 拦截外链
        event.preventDefault();
        const safeUrl = encodeURIComponent(a.href);
        window.location.href = '/redirect/go/' + safeUrl;
    });
});