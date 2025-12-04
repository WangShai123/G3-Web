/**
 * @class JUI
 * @classdesc JUI
 */
class JUI {
    constructor() {
        this.DarkModeCookie = 'jl:darkMode';
        this.DarkModeClass = 'is-dark';
        this.ThemeSwitchSelector = '[data-selector="theme-switch"]';
        this.init();
    }

    init() {
        this.isES6Supported();
        this.userAgent();
        this.isMobile();
        this.initDarkMode();
        this.a();
        this.color();
        this.backgroundColor();
        this.backgroundImage();
        this.badge();
        this.input();
        this.textarea();
        this.radio();
        this.checkbox();
        this.counter();
        this.tab();
        this.logo();
        this.sizing();
        this.mobileMenu();
        this.dropDown();
    }

    /**
     * 获取元素
     * @param {string} selector 选择器
     * @returns {object | array | null} 元素 | 元素数组 | null
     */
    $(selector) {
        const elements = document.querySelectorAll(selector);
        return elements.length === 1 ? elements[0] : Array.from(elements);
    }

    /**
     * 淡入淡出
     * @param {object} el 元素
     * @param {boolean} isFadeIn 是否淡入 true 淡入 false 淡出
     * @param {number} duration 持续时间 默认300ms
     */
    fade(el, isFadeIn = true, duration = 300) {
        if (!el || !el.style) {
            return;
        }

        if (isFadeIn) {
            el.style.opacity = 0;
            el.style.display = 'block';
        }

        const start = performance.now();

        function updateOpacity(currentTime) {
            const progress = isFadeIn ? (currentTime - start) / duration : 1 - (currentTime - start) / duration;

            if ((isFadeIn && progress >= 1) || (!isFadeIn && progress <= 0)) {
                el.style.opacity = isFadeIn ? 1 : 0;
                if (!isFadeIn) {
                    el.style.display = 'none';
                }
            } else {
                el.style.opacity = progress;
                //使用requestAnimationFrame减少重绘次数
                requestAnimationFrame(updateOpacity);
            }
        }

        requestAnimationFrame(updateOpacity);
    };

    /**
     * 生成时间戳字符串
     * @return {string} 时间戳字符串
     */
    timeString() {
        return new Date().getTime().toString();
    }

    /**
     * 生成随机字符串，用于生成唯一的ID
     * @param {number} length 字符串长度 默认8
     * @return {string} 随机字符串
     */
    hashString(length = 8) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        const maxPos = chars.length;
        const stringArray = new Array(length);

        for (let i = 0; i < length; i++) {
            stringArray[i] = chars.charAt(Math.floor(Math.random() * maxPos));
        }

        return stringArray.join('');
    }

    /**
     * 创建元素
     * @param {string} tagElement 元素标签
     * @param {object} attributes 属性
     * @returns {object} element 元素
     * @example
     * attributes = {
     *   'class': ['class1', 'class2'], or 'class', // 多个class用数组，单个class用字符串
     *   'id': 'id', // 多个id用数组，单个id用字符串
     *   'data': {
     *    'name': 'value',
     *   },
     *   'custom-attribute': 'value',
     * }
     */
    createElement(tagElement, attributes) {
        const element = document.createElement(tagElement);

        for (const key in attributes) {
            if (!Object.hasOwnProperty.call(attributes, key)) continue;
            const value = attributes[key];

            switch (key) {
                case 'class':
                    if (Array.isArray(value)) {
                        element.classList.add(...value);
                    } else {
                        element.classList.add(value);
                    }
                    break;

                case 'id':
                    if (Array.isArray(value)) {
                        element.id = value.join(' ');
                    } else {
                        element.id = value;
                    }
                    break;

                case 'data':
                    for (const dataKey in value) {
                        if (Object.hasOwnProperty.call(value, dataKey)) {
                            element.setAttribute('data-' + dataKey, value[dataKey]);
                        }
                    }
                    break;

                default:
                    element.setAttribute(key, value);
            }
        }

        return element;
    }

    /**
     * 简单创建元素，仅支持class
     * @param {string} tagElement 元素标签
     * @param {array | string} className class名称
     * @return {object} element 元素
     */
    easyElement(tagElement, className) {
        const element = document.createElement(tagElement);
        if (Array.isArray(className)) {
            className.forEach((item) => {
                element.classList.add(item);
            });
        } else {
            element.classList.add(className);
        }
        return element;
    }

    /**
     * 检查参数类型
     * @param {string} param 参数
     * @param {string} type 类型
     * @param {string} msg 错误信息
     * @return {boolean}
     */
    validateParam(param, type, msg) {
        if (typeof param !== type) {
            console.error(msg);
            return false;
        }
        return true;
    }

    /**
     * fetch封装
     * 默认json请求和响应
     * @param {Object} options 配置
     * @param {Boolean} options.isInternal 是否是站内接口, 默认true
     * @param {Boolean} options.ajax 是否是 WordPress AJAX 地址, 默认false
     * @param {String} options.url 接口完整地址 或 站内接口相对地址 或 WordPress AJAX action
     * @param {String} options.method 请求方法, 默认GET
     * @param {Object} options.data 请求数据
     * @param {Function} options.prepare 发送请求前回调函数
     * @param {Function} options.success 成功回调函数
     * @param {Function} options.error 失败回调函数
     * @param {Function} options.complete 完成回调函数
     * @returns {Promise} Promise 对象
     */
    fetch(options) {
        // 默认配置 默认是站内接口
        const defaultOptions = {
            isInternal: true, // 是否是站内接口
            ajax: false, // 是否是 WordPress AJAX 地址
            url: '', // 接口完整地址 或 站内接口相对地址 或 WordPress AJAX action
            method: 'GET', // 请求方法
            type: 'application/x-www-form-urlencoded', // content-type
            data: null, // 请求数据
            prepare: function () { }, // 发送请求前回调函数
            success: function (data) { }, // 成功回调函数
            error: function (error) { }, // 失败回调函数
            complete: function () { }, // 完成回调函数
        };

        options = { ...defaultOptions, ...options };

        // 判断是否是站内接口
        if (options.isInternal) {
            // 判断是否是 WordPress AJAX 地址
            options.url = options.ajax
                ? `${window.location.origin}/wp-admin/admin-ajax.php?action=${options.url}`
                : `${window.location.origin}${options.url}`;
        }

        // 判断请求方法
        options.method = options.method.toUpperCase();

        // 判断请求数据
        let fetchBody = null;
        if (options.data) {
            // if (options.method === 'GET') {
            //     options.url += `?${Object.keys(options.data)
            //         .map((key) => `${key}=${options.data[key]}`)
            //         .join('&')}`;
            // } else {
            //     options.data = JSON.stringify(options.data);
            // }
            fetchBody = `${Object.keys(options.data)
                .map((key) => `${key}=${options.data[key]}`)
                .join('&')}`;
        }

        // 发送请求前回调函数
        options.prepare();

        // 发送请求
        fetch(options.url, {
            method: options.method,
            headers: {
                'Content-Type': options.type,
            },
            body: fetchBody,
        })
            .then((response) => {
                // if (response.ok) {
                //     return response.json();
                // } else {
                //     throw new Error(response.statusText);
                // }
                return response.json();
            })
            .then((data) => {
                options.success(data);
            })
            .catch((error) => {
                options.error(error);
            })
            .finally(() => {
                options.complete();
            });
    }

    /**
     * 解析颜色字符串 j-color
     * 字符串格式 colorName_lightness[:opacity]_colorName_lightness[:opacity]
     * @param {string} str
     * @return {object} {colorName1, lightness1, opacity1, colorName2, lightness2, opacity2}
     */
    parseColorString(str) {
        let parts = str.split('_');
        if (parts.length > 2) {
            throw new Error('Invalid color string');
        }
        let temp1 = parts[0];
        let colorName1 = temp1.match(/[a-z]+/i)[0];
        let lightness1 = temp1.match(/[0-9]+/i)[0];
        // let colorName1 = temp1.match(/[a-z]+/i) ?? temp1.match(/[a-z]+/i)[0];
        // let lightness1 = temp1.match(/[0-9]+/i) ?? temp1.match(/[0-9]+/i)[0];
        let opacity1 = temp1.match(/:[0-9.]+/i);
        opacity1 = opacity1 ? opacity1[0].replace(':', '') : null;
        temp1 = {
            colorName1,
            lightness1,
            opacity1,
        };
        let temp2;
        if (parts.length === 2) {
            temp2 = parts[1];
            let colorName2 = temp2.match(/[a-z]+/i)[0];
            let lightness2 = temp2.match(/[0-9]+/i)[0];
            // let colorName2 = temp2.match(/[a-z]+/i) ?? temp2.match(/[a-z]+/i)[0];
            // let lightness2 = temp2.match(/[0-9]+/i) ?? temp2.match(/[0-9]+/i)[0];
            let opacity2 = temp2.match(/:[0-9.]+/i);
            opacity2 = opacity2 ? opacity2[0].replace(':', '') : null;
            temp2 = {
                colorName2,
                lightness2,
                opacity2,
            };
        } else {
            temp2 = null;
        }
        return Object.assign(temp1, temp2);
    }

    /**
     * HSL转换为HEX或RGBA
     * @param {int} h
     * @param {int} s
     * @param {int} l
     * @param {int} a
     * @return {string} HEX | RGBA
     */
    hslConvert(h, s, l, a) {
        h /= 360; // 将色调转换到 0 - 1
        s /= 100; // 将饱和度除以 100
        l /= 100; // 将亮度除以 100

        // 转换为 RGB 值
        let r, g, b;

        // 当饱和度为0，转换为灰色
        if (s === 0) {
            r = g = b = Math.round(l * 255);
        } else {
            const hue2rgb = (p, q, t) => {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1 / 6) return p + (q - p) * 6 * t;
                if (t < 1 / 2) return q;
                if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
                return p;
            };
            // const q = l + s - l * s;
            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            const p = 2 * l - q;
            r = (Math.round(hue2rgb(p, q, h + 1 / 3) * 1000) * 255) / 1000;
            g = (Math.round(hue2rgb(p, q, h) * 1000) * 255) / 1000;
            b = (Math.round(hue2rgb(p, q, h - 1 / 3) * 1000) * 255) / 1000;
        }
        // 如果 a 不存在，则输出 HEX 值
        if (typeof a === 'undefined' || a === null) {
            const hex = (r * 0x10000 + g * 0x100 + b)
                .toString(16)
                .padStart(6, '0');
            return `#${hex}`;
        } else if (a <= 1) {
            // 如果 a 存在且小于等于 1，则输出 RGBA 值
            const rgba = `rgba(${r}, ${g}, ${b}, ${a})`;
            return rgba;
        }
        // else {
        //     throw new Error('Invalid alpha value');
        // }
    }

    /**
     * 获取cookie
     * @param {string} name cookie名称
     * @return {string} cookie值 | ''
     */
    getCookie(name) {
        const cookieString = decodeURIComponent(document.cookie);
        const cookieArray = cookieString.split('; ');

        for (const cookie of cookieArray) {
            const [cookieName, cookieValue] = cookie.split('=');
            if (cookieName === name) {
                return cookieValue;
            }
        }

        return '';
    }

    /**
     * 判断cookie是否存在
     * @param {string} cookieName cookie名称
     * @return {boolean} true | false
     */
    hasCookie(cookieName) {
        return document.cookie.split(';').some((item) => item.trim().startsWith(cookieName + '='));
    }

    /**
     * 设置cookie
     * @param {string} name cookie名称
     * @param {string} value cookie值
     * @param {number} maxAge cookie有效期 单位秒
     * @param {string} path cookie路径
     */
    setCookie(name, value, maxAge = 60 * 60 * 24 * 365, path = '/') {
        document.cookie = `${name}=${value}; path=${path}; max-age=${maxAge}`;
    }

    /**
     * 判断是否支持ES6
     * @returns {boolean} true | false
     */
    isES6Supported() {
        try {
            eval('() => {}');
            eval('() => ({ foo: 1 })'); //目前国产浏览器大多版本依旧不支持
            eval('[1, 2, 3].includes(2)');
            eval('Object.assign({},{foo:1})');
        } catch (err) {
            console.log('ES6 not supported:', err);
            window.location.href(
                `//${window.location.host}/old-dangerous-browser-warning-with-patience-and-love.html`
            );
            return false;
        }
        return true;
    }

    /**
     * 获取用户代理信息
     * @returns {object} {ua, os, browser, version}
     */
    userAgent() {
        const ua = navigator.userAgent;
        let os = '';
        let browser = '';
        let version = '';
        // 操作系统
        if (!!ua.match(/compatible/i) || ua.match(/Windows/i)) {
            os = 'windows';
        } else if (!!ua.match(/Macintosh/i) || ua.match(/MacIntel/i)) {
            os = 'macOS';
        } else if (!!ua.match(/Linux/i)) {
            os = 'Linux';
        } else if (!!ua.match(/iphone/i) || ua.match(/Ipad/i)) {
            os = 'ios';
        } else if (!!ua.match(/android/i)) {
            os = 'android';
        } else {
            os = 'Unknown';
        }
        // 浏览器
        if (!!ua.match(/Trident/i) || ua.match(/MSIE/i)) {
            browser = 'IE';
        } else if (!!ua.match(/Edg/i)) {
            browser = 'Edge';
        } else if (!!ua.match(/Opera/i) || !!ua.match(/OPR/i)) {
            browser = 'Opera';
        } else if (!!ua.match(/Firefox/i)) {
            browser = 'Firefox';
        } else if (!!ua.match(/Chrome/i)) {
            browser = 'Chrome';
        } else if (!!ua.match(/Safari/i)) {
            browser = 'Safari';
        } else {
            browser = 'Unknown';
        }
        // 浏览器版本
        if (browser === 'IE') {
            version = ua.match(/MSIE\s\d+\.?\d*/i)[0].match(/\d+\.?\d*/i)[0];
        } else if (browser === 'Edge') {
            version = ua.match(/Edg\/\d+\.?\d*/i)[0].match(/\d+\.?\d*/i)[0];
        } else if (browser === 'Opera') {
            version = ua.match(/OPR\/\d+\.?\d*/i)[0].match(/\d+\.?\d*/i)[0];
        } else if (browser === 'Firefox') {
            version = ua.match(/Firefox\/\d+\.?\d*/i)[0].match(/\d+\.?\d*/i)[0];
        } else if (browser === 'Chrome') {
            version = ua.match(/Chrome\/\d+\.?\d*/i)[0].match(/\d+\.?\d*/i)[0];
        } else if (browser === 'Safari') {
            version = ua.match(/Safari\/\d+\.?\d*/i)[0].match(/\d+\.?\d*/i)[0];
        } else {
            version = 'Unknown';
        }
        return {
            ua,
            os,
            browser,
            version,
        };
    }

    /**
     * 判断是否是移动端，为html元素添加class
     * @returns {void}
     */
    isMobile() {
        const ua = navigator.userAgent;
        const isMobile =
            /Mobile|Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
                ua
            );
        const isTablet = /Tablet|iPad/i.test(ua);
        if (isMobile) {
            document.documentElement.classList.add('is-mobile');
        } else if (isTablet) {
            document.documentElement.classList.add('is-mobile', 'is-tablet');
        } else {
            document.documentElement.classList.add('is-desktop');
        }
    }

    /**
     * 主题切换
     */
    initThemeToggle(hasCookie) {
        const checkboxes = document.querySelectorAll(this.ThemeSwitchSelector);
        if (checkboxes.length > 0) {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = hasCookie;
                checkbox.addEventListener('change', () => {
                    const isChecked = checkbox.checked;
                    document.documentElement.classList.toggle(
                        this.DarkModeClass,
                        isChecked
                    );
                    this.setCookie(
                        this.DarkModeCookie,
                        isChecked,
                    );

                    checkboxStates[checkbox.id] = checkbox.checked;
                });
            });
        }

        //checkbox状态代理
        const checkboxStates = new Proxy({}, {
            set(target, key, value) {
                if (target[key] !== value) {
                    target[key] = value;
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = value;
                    });
                }
                return true;
            }
        });
    }
    initDarkMode() {
        const darkModeCookie = this.getCookie(this.DarkModeCookie);
        const prefersDarkMode =
            window.matchMedia &&
            window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (document.body.classList.contains('wp-admin')) {
            return;
        }

        if (this.hasCookie(this.DarkModeCookie)) {
            if (darkModeCookie === 'true') {
                this.initThemeToggle(true);
                document.documentElement.classList.add(this.DarkModeClass);
            } else {
                this.initThemeToggle(false);
                document.documentElement.classList.remove(this.DarkModeClass);
            }
        } else {
            if (prefersDarkMode) {
                this.initThemeToggle(true);
                document.documentElement.classList.add(this.DarkModeClass);
                this.setCookie(
                    this.DarkModeCookie,
                    prefersDarkMode,
                );
            } else {
                this.initThemeToggle(false);
                document.documentElement.classList.remove(this.DarkModeClass);
                this.setCookie(
                    this.DarkModeCookie,
                    prefersDarkMode,
                );
            }
        }
    }

    a() {
        const a = document.querySelectorAll(".j-content a[target='_blank']");
        if (a) {
            a.forEach((element) => {
                element.rel = 'noopener noreferrer';
                // element.style.textDecoration = 'none';
                const link = this.createElement('i', {
                    class: 'ri-external-link-line',
                    style: 'padding-left: 4px;',
                });
                element.appendChild(link);
            });
        }
    }

    color() {
        const addedColors = {};
        let color = document.querySelectorAll('[data-j-color]');
        if (color) {
            color.forEach((element) => {
                // let selector = element.getAttribute('data-selector');
                let selector = element.dataset.selector;
                let tag = element.tagName.toLowerCase();
                let temp = element.getAttribute('data-j-color');
                if (temp && !addedColors[temp]) {
                    let obj = this.parseColorString(temp);
                    let light, dark;
                    switch (obj.colorName1) {
                        case 'primary':
                            light = this.hslConvert(
                                220,
                                80,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'secondary':
                            light = this.hslConvert(
                                240,
                                20,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'success':
                            light = this.hslConvert(
                                130,
                                80,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'warning':
                            light = this.hslConvert(
                                30,
                                80,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'error':
                            light = this.hslConvert(
                                350,
                                80,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'neutral':
                            light = this.hslConvert(
                                0,
                                0,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        default:
                            break;
                    }
                    let lightcolor = `${tag}[data-selector="${selector}"] { color: ${light};}`;
                    let style = document.createElement('style');
                    style.innerHTML += lightcolor;
                    if (obj.colorName2) {
                        switch (obj.colorName2) {
                            case 'primary':
                                dark = this.hslConvert(
                                    220,
                                    80,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'secondary':
                                dark = this.hslConvert(
                                    240,
                                    20,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'success':
                                dark = this.hslConvert(
                                    130,
                                    80,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'warning':
                                dark = this.hslConvert(
                                    30,
                                    80,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'error':
                                dark = this.hslConvert(
                                    350,
                                    80,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'neutral':
                                dark = this.hslConvert(
                                    0,
                                    0,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            default:
                                break;
                        }
                        const darkcolor = `.is-dark ${tag}[data-selector="${selector}"] { color: ${dark};}`;
                        style.innerHTML += darkcolor;
                    }
                    addedColors[temp] = true;
                    document.body.appendChild(style);
                }
            });
        }
    }

    backgroundColor() {
        const addedColors = {};
        let bgColor = document.querySelectorAll('[data-j-bgcolor]');
        if (bgColor) {
            bgColor.forEach((element) => {
                let selector = element.getAttribute('data-selector');
                let tag = element.tagName.toLowerCase();
                let temp = element.getAttribute('data-j-bgcolor');
                if (temp && !addedColors[temp]) {
                    let obj = this.parseColorString(temp);
                    let light;
                    switch (obj.colorName1) {
                        case 'primary':
                            light = this.hslConvert(
                                220,
                                80,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'secondary':
                            light = this.hslConvert(
                                240,
                                20,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'success':
                            light = this.hslConvert(
                                130,
                                80,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'warning':
                            light = this.hslConvert(
                                30,
                                80,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'error':
                            light = this.hslConvert(
                                350,
                                80,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        case 'neutral':
                            light = this.hslConvert(
                                0,
                                0,
                                obj.lightness1,
                                obj.opacity1
                            );
                            break;
                        default:
                            break;
                    }
                    let lightcolor = `${tag}[data-selector="${selector}"] { background-color: ${light};}`;
                    let style = document.createElement('style');
                    style.innerHTML += lightcolor;
                    if (obj.colorName2) {
                        let dark;
                        switch (obj.colorName2) {
                            case 'primary':
                                dark = this.hslConvert(
                                    220,
                                    80,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'secondary':
                                dark = this.hslConvert(
                                    240,
                                    20,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'success':
                                dark = this.hslConvert(
                                    130,
                                    80,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'warning':
                                dark = this.hslConvert(
                                    30,
                                    80,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'error':
                                dark = this.hslConvert(
                                    350,
                                    80,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            case 'neutral':
                                dark = this.hslConvert(
                                    0,
                                    0,
                                    obj.lightness2,
                                    obj.opacity2
                                );
                                break;
                            default:
                                break;
                        }
                        const darkcolor = `.is-dark ${tag}[data-selector="${selector}"] { background-color: ${dark};}`;
                        style.innerHTML += darkcolor;
                    }
                    addedColors[temp] = true;
                    document.body.appendChild(style);
                }
            });
        }
    }

    backgroundImage() {
        const bg = document.querySelectorAll('[data-j-bgimg]');
        if (bg) {
            bg.forEach((element) => {
                const url = element.getAttribute('data-j-bgimg');
                if (url) {
                    element.style.backgroundImage = `url(${url})`;
                }
            });
        }
    }

    badge() {
        const badges = document.querySelectorAll('.j-badge');
        if (badges) {
            badges.forEach((element) => {
                const badgeContent = element.querySelector('.badge-content');
                function updateBadgeCount() {
                    const count = badgeContent.innerHTML;
                    if (
                        count.length > 0 ||
                        element.classList.contains('is-dot')
                    ) {
                        badgeContent.style.display = 'flex';
                        if (count === '0') {
                            badgeContent.style.display = 'none';
                        } else if (!isNaN(count) && count > 0) {
                            badgeContent.innerHTML = count > 99 ? '99+' : count;
                        }
                    } else {
                        badgeContent.style.display = 'none';
                    }
                }
                updateBadgeCount();
            });
        }
    }

    input() {
        let input = document.querySelectorAll('.j-input');
        if (input) {
            input.forEach((element) => {
                let inputElement = element.querySelector('input');
                if (inputElement.disabled) {
                    element.setAttribute('disabled', true);
                }
                // 兼容默认display:none的.j-modal祖先容器
                let modalAncestor = inputElement.closest('.j-modal');
                if (!modalAncestor) {
                    let inputElementWidth = inputElement.offsetWidth;
                    inputElement.style.width = inputElementWidth + 'px';
                }

                let tempPre = element.getAttribute('j-prefix');
                let tempSuf = element.getAttribute('j-suffix');

                if (tempPre) {
                    let prefix = this.createElement('span', {
                        class: 'el-prefix',
                    });
                    element.insertBefore(prefix, element.firstChild);

                    let prefixWidth = prefix.offsetWidth;
                    inputElement.style.paddingLeft = `${prefixWidth}px`;
                    let observer = new MutationObserver(() => {
                        prefixWidth = prefix.offsetWidth;
                        inputElement.style.paddingLeft = `${prefixWidth}px`;
                    });
                    observer.observe(prefix, {
                        attributes: true,
                        childList: true,
                        subtree: true,
                    });

                    let prefixData = tempPre.split(' ').map((item) => {
                        switch (item) {
                            case 'close':
                                let closeElement =
                                    document.createElement('span');
                                closeElement.className +=
                                    ' ri-close-line el-close';
                                prefix.append(closeElement);
                                closeElement.style.display = 'none';
                                inputElement.addEventListener(
                                    'input',
                                    function () {
                                        if (inputElement.value) {
                                            closeElement.style.display =
                                                'block';
                                        } else {
                                            closeElement.style.display = 'none';
                                        }
                                    }
                                );
                                closeElement.addEventListener(
                                    'click',
                                    function () {
                                        inputElement.value = '';
                                        let countElement =
                                            element.querySelector('.el-count');
                                        if (countElement) {
                                            countElement.innerHTML = '';
                                        }
                                        closeElement.style.display = 'none';
                                        inputElement.style.paddingLeft = '';
                                        let password =
                                            element.querySelector(
                                                '.el-password'
                                            );
                                        if (password) {
                                            password.style.display = 'none';
                                        }
                                    }
                                );
                                return closeElement;
                            case 'count':
                                let countElement =
                                    document.createElement('span');
                                countElement.className += ' el-count';
                                prefix.append(countElement);
                                let maxLength =
                                    inputElement.getAttribute('maxlength');
                                inputElement.oninput = function () {
                                    let currentLength =
                                        inputElement.value.length;
                                    countElement.innerHTML = maxLength
                                        ? currentLength
                                            ? `${currentLength}/${maxLength}`
                                            : ''
                                        : currentLength
                                        ? currentLength
                                        : '';
                                };
                                return countElement;
                            case 'password':
                                let passwordElement =
                                    document.createElement('span');
                                passwordElement.className +=
                                    ' el-password ri-eye-line';
                                passwordElement.style.display = 'none';
                                prefix.append(passwordElement);
                                inputElement.addEventListener(
                                    'input',
                                    function () {
                                        if (inputElement.value) {
                                            passwordElement.style.display =
                                                'block';
                                        } else {
                                            passwordElement.style.display =
                                                'none';
                                        }
                                    }
                                );
                                passwordElement.addEventListener(
                                    'click',
                                    function () {
                                        if (inputElement.type === 'password') {
                                            inputElement.type = 'text';
                                            passwordElement.classList.remove(
                                                'ri-eye-line'
                                            );
                                            passwordElement.classList.add(
                                                'ri-eye-off-line'
                                            );
                                        } else {
                                            inputElement.type = 'password';
                                            passwordElement.classList.remove(
                                                'ri-eye-off-line'
                                            );
                                            passwordElement.classList.add(
                                                'ri-eye-line'
                                            );
                                        }
                                    }
                                );
                                return passwordElement;
                            default:
                                let otherElement =
                                    document.createElement('span');
                                otherElement.className += ` ${item}`;
                                prefix.append(otherElement);
                                return otherElement;
                        }
                    });
                }
                if (tempSuf) {
                    let suffix = this.createElement('span', {
                        class: 'el-suffix',
                    });
                    element.insertBefore(suffix, element.lastChild);
                    let suffixData = tempSuf.split(' ').map((item) => {
                        switch (item) {
                            case 'close':
                                let closeElement =
                                    document.createElement('span');
                                closeElement.className +=
                                    ' ri-close-line el-close';
                                suffix.append(closeElement);
                                closeElement.style.display = 'none';
                                inputElement.addEventListener(
                                    'input',
                                    function () {
                                        if (inputElement.value) {
                                            closeElement.style.display =
                                                'block';
                                        } else {
                                            closeElement.style.display = 'none';
                                        }
                                    }
                                );
                                closeElement.addEventListener(
                                    'click',
                                    function () {
                                        inputElement.value = '';
                                        let countElement =
                                            element.querySelector('.el-count');
                                        if (countElement) {
                                            countElement.innerHTML = '';
                                        }
                                        closeElement.style.display = 'none';
                                        inputElement.style.paddingRight = '';
                                        let password =
                                            element.querySelector(
                                                '.el-password'
                                            );
                                        if (password) {
                                            password.style.display = 'none';
                                        }
                                    }
                                );
                                return closeElement;
                            case 'count':
                                let countElement = this.createElement('span', {
                                    class: 'el-count',
                                });
                                suffix.append(countElement);
                                let maxLength =
                                    inputElement.getAttribute('maxlength');
                                inputElement.oninput = function () {
                                    let currentLength =
                                        inputElement.value.length;
                                    countElement.innerHTML = maxLength
                                        ? currentLength
                                            ? `${currentLength}/${maxLength}`
                                            : ''
                                        : currentLength
                                        ? currentLength
                                        : '';
                                };
                                return countElement;
                            case 'password':
                                let passwordElement =
                                    document.createElement('span');
                                passwordElement.className +=
                                    ' el-password ri-eye-line';
                                passwordElement.style.display = 'none';
                                suffix.append(passwordElement);
                                inputElement.addEventListener(
                                    'input',
                                    function () {
                                        if (inputElement.value) {
                                            passwordElement.style.display =
                                                'block';
                                        } else {
                                            passwordElement.style.display =
                                                'none';
                                        }
                                    }
                                );
                                passwordElement.addEventListener(
                                    'click',
                                    function () {
                                        if (inputElement.type === 'password') {
                                            inputElement.type = 'text';
                                            passwordElement.classList.remove(
                                                'ri-eye-line'
                                            );
                                            passwordElement.classList.add(
                                                'ri-eye-off-line'
                                            );
                                        } else {
                                            inputElement.type = 'password';
                                            passwordElement.classList.remove(
                                                'ri-eye-off-line'
                                            );
                                            passwordElement.classList.add(
                                                'ri-eye-line'
                                            );
                                        }
                                    }
                                );
                                return passwordElement;
                            default:
                                let otherElement =
                                    document.createElement('span');
                                otherElement.className += ` ${item}`;
                                suffix.append(otherElement);
                                return otherElement;
                        }
                    });
                    let suffixWidth = suffix.offsetWidth;
                    inputElement.style.paddingRight = suffixWidth + 'px';
                    let observer = new MutationObserver(() => {
                        suffixWidth = suffix.offsetWidth;
                        inputElement.style.paddingRight = `${suffixWidth}px`;
                    });
                    observer.observe(suffix, {
                        attributes: true,
                        childList: true,
                        subtree: true,
                    });
                }
            });
        }
    }

    textarea() {
        let textarea = document.querySelectorAll('.j-textarea');
        if (textarea) {
            textarea.forEach((element) => {
                let textareaElement = element.querySelector('textarea');
                if (textareaElement.disabled) {
                    element.setAttribute('disabled', true);
                }
                let tempSuf = element.getAttribute('j-suffix');
                if (tempSuf) {
                    let suffix = this.createElement('span', {
                        class: 'el-suffix',
                    });
                    element.insertBefore(suffix, element.lastChild);
                    let suffixData = tempSuf.split(' ').map((item) => {
                        switch (item) {
                            case 'count':
                                let countElement = this.createElement('span', {
                                    class: 'el-count',
                                });
                                suffix.append(countElement);
                                let maxLength =
                                    textareaElement.getAttribute('maxlength');
                                textareaElement.oninput = function () {
                                    let currentLength =
                                        textareaElement.value.length;
                                    countElement.innerHTML = maxLength
                                        ? currentLength
                                            ? `${currentLength}/${maxLength}`
                                            : ''
                                        : currentLength
                                        ? currentLength
                                        : '';
                                };
                                return countElement;
                            case 'close':
                                let closeElement = document.createElement('i');
                                closeElement.className +=
                                    ' ri-close-line el-close';
                                suffix.append(closeElement);
                                closeElement.style.display = 'none';
                                textareaElement.addEventListener(
                                    'input',
                                    function () {
                                        if (textareaElement.value) {
                                            closeElement.style.display =
                                                'block';
                                        } else {
                                            closeElement.style.display = 'none';
                                        }
                                    }
                                );
                                closeElement.addEventListener(
                                    'click',
                                    function () {
                                        textareaElement.value = '';
                                        let countElement =
                                            element.querySelector('.el-count');
                                        if (countElement) {
                                            countElement.innerHTML = '';
                                        }
                                        closeElement.style.display = 'none';
                                    }
                                );
                                return closeElement;
                            default:
                                let otherElement = document.createElement('i');
                                otherElement.className += ` ${item}`;
                                suffix.append(otherElement);
                                return otherElement;
                        }
                    });
                }
            });
        }
    }

    radio() {
        let radio = document.querySelectorAll('.j-radio');
        if (radio) {
            radio.forEach((element) => {
                let radioElement = element.querySelector("input[type='radio']");
                if (radioElement.disabled) {
                    element.setAttribute('disabled', true);
                }
            });
        }
    }

    checkbox() {
        let checkbox = document.querySelectorAll('.j-checkbox');
        if (checkbox) {
            checkbox.forEach((element) => {
                let checkboxElement = element.querySelector(
                    "input[type='checkbox']"
                );
                if (checkboxElement.disabled) {
                    element.setAttribute('disabled', true);
                }
            });
        }
    }

    // 计数器组件
    counter() {
        // 创建处理器
        const handler = createCounterHandler();

        // 初始化处理器
        handler.init();

        /**
         * 创建处理器
         * @return {Object} 处理器
         */
        function createCounterHandler() {
            const counters = [];

            function init() {
                // 初始化计数器
                document.querySelectorAll('.j-counter').forEach((el) => {
                    const counter = createCounter(el);
                    counters.push(counter);
                });
            }

            function update() {
                // 更新计数器
                counters.forEach((c) => c.update());
            }

            return {
                init,
                update,
            };
        }

        // 计数器
        function createCounter(el) {
            const decreaseBtn = el.querySelector('.counter-decrease');
            const increaseBtn = el.querySelector('.counter-increase');
            const input = el.querySelector('.is-input');

            const options = {
                min: +input.getAttribute('minlength') || 0,
                max: +input.getAttribute('maxlength') || Infinity,
                step: +input.getAttribute('step') || 1,
                value: +input.value || 0,
            };

            const state = {
                value: options.value,
            };

            const proxy = new Proxy(state, {
                set(target, key, newValue) {
                    if (key === 'value') {
                        target[key] = Math.min(
                            Math.max(newValue, options.min),
                            options.max
                        );
                        handler.update();
                    }
                    return true;
                },
            });

            function increase() {
                proxy.value += options.step;
            }

            function decrease() {
                proxy.value -= options.step;
            }

            function update() {
                if (proxy.value <= options.min) {
                    decreaseBtn.setAttribute('disabled', '');
                } else {
                    decreaseBtn.removeAttribute('disabled');
                }

                if (proxy.value >= options.max) {
                    increaseBtn.setAttribute('disabled', '');
                } else {
                    increaseBtn.removeAttribute('disabled');
                }

                input.value = proxy.value;
            }

            decreaseBtn.addEventListener('click', decrease);
            increaseBtn.addEventListener('click', increase);
            input.addEventListener('input', (e) => {
                proxy.value = +e.target.value;
            });

            return { update };
        }
    }
    /**
     * 获取计数器
     * @param {HTMLElement} el 计数器元素
     * @return {Object} counter 计数器数据对象，包含 value, min, max, step的值 和 increatse, decrease是否禁用的状态
     */
    getCounter(el) {}

    tab() {
        let tab = document.querySelectorAll('.j-tab');
        if (tab) {
            tab.forEach((element) => {
                element.addEventListener('click', function (e) {
                    const target = e.target;
                    const tabItem = target.closest('.tab-item');
                    if (tabItem && !tabItem.closest('[disabled]')) {
                        const index = Array.from(
                            tabItem.parentNode.children
                        ).indexOf(tabItem);
                        tabItem.classList.add('is-active');
                        const siblings = Array.from(
                            tabItem.parentNode.children
                        ).filter((item) => item !== tabItem);
                        siblings.forEach((sibling) => {
                            sibling.classList.remove('is-active');
                        });
                        const contentItems = Array.from(
                            tabItem
                                .closest('.j-tab')
                                .querySelector('.tab-content').children
                        );
                        contentItems.forEach((item) => {
                            item.classList.remove('is-active');
                        });
                        contentItems[index].classList.add('is-active');
                    }
                });
            });
        }
    }

    logo() {
        const logo = document.querySelectorAll('[data-selector="theme-logo"]');
        const lightLogo = decodeURIComponent(this.getCookie('jl:lightLogo'));
        const darkLogo = decodeURIComponent(this.getCookie('jl:darkLogo'));
        if (logo) {
            logo.forEach((element) => {
                const observer = new MutationObserver((mutationsList) => {
                    mutationsList.forEach((mutation) => {
                        if (mutation.attributeName === 'class') {
                            element.src =
                                document.documentElement.classList.contains(
                                    'is-dark'
                                )
                                    ? darkLogo
                                    : lightLogo;
                        }
                    });
                });
                observer.observe(document.documentElement, {
                    attributes: true,
                });
            });
        }
    }

    sizing() {

        const size = [
            'width',
            'height',
            'min-width',
            'min-height',
            'max-width',
            'max-height',
        ];

        size.forEach(key => {
            const camelCaseKey = key.replace(/-([a-z])/g, function (g) { return g[1].toUpperCase(); });
            const elements = document.querySelectorAll(`[data-${key}]`);

            elements.forEach(element => {
                const value = element.dataset[camelCaseKey];
                if (value !== undefined) {
                    element.style[camelCaseKey] = value;
                }
            });
        });
    }

    mobileMenu() {
        const mobileMenu = '.j-menu.is-mobile .menu-item.menu-item-has-children';
        if (document.querySelectorAll(mobileMenu).length > 0) {
            document.querySelectorAll(mobileMenu).forEach((item) => {
                let isActive = false;
                item.addEventListener('touchend', (e) => {
                    if (e.target === item.children[0]) {
                        e.preventDefault();
                    }
                    // Check for sub-menu elements
                    const subMenu = item.querySelector('.sub-menu');
                    if (isActive) {
                        jui.fade(subMenu, false);
                    } else {
                        jui.fade(subMenu);
                    }
                    isActive = !isActive;
                });
                document.addEventListener('touchend', (e) => {
                    if (isActive && !item.contains(e.target)) {
                        jui.fade(item.querySelector('.sub-menu'), false);
                        isActive = false;
                    }
                });
            });
        }
    }

    dropDown() {
        const call = document.querySelectorAll('[data-dropdown]');
        if (call.length > 0) {
            call.forEach((item) => {
                let isActive = false;
                const target = item.getAttribute('data-dropdown');
                const dropDown = item.querySelector('#' + target);

                // 阻止默认行为只在 item 被点击时执行
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (isActive) {
                        this.fade(dropDown, false);
                    } else {
                        this.fade(dropDown);
                    }
                    isActive = !isActive;
                });

                // 阻止事件冒泡，以免关闭 dropdown
                dropDown.addEventListener('click', (e) => {
                    e.stopPropagation();
                });

                // 在 document 上添加事件监听，用于关闭 dropdown
                document.addEventListener('click', (e) => {
                    if (isActive && !item.contains(e.target)) {
                        this.fade(dropDown, false);
                        isActive = false;
                    }
                });

                this.initDropdownPosition(item, dropDown);
            });
        }
    }

    /**
     * 初始化DropDown位置
     * @param {object} targetElement
     * @param {object} dropdownElement
     * @return {void}
     */
    initDropdownPosition(targetElement, dropdownElement) {
        // 获取目标元素的位置信息
        const targetRect = targetElement.getBoundingClientRect();

        // 计算下拉菜单的宽度和高度
        const dropdownWidth = dropdownElement.offsetWidth;
        const dropdownHeight = dropdownElement.offsetHeight;

        // 获取窗口的宽度和高度
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;

        // 默认的定位属性
        let position = null;

        // 判断下拉菜单在目标元素上方还是下方
        if (targetRect.top > windowHeight - targetRect.bottom) {
            position = 'top-left';
        } else {
            position = 'bottom-left';
        }

        // 判断下拉菜单在目标元素的左侧还是右侧
        if (targetRect.left > windowWidth - targetRect.right) {
            position = position === 'top-left' ? 'top-right' : 'bottom-right';
        }

        // 为目标元素和下拉菜单设置 position 属性
        targetElement.style.position = 'relative';
        dropdownElement.style.position = 'absolute';

        // 根据计算得到的位置应用样式
        switch (position) {
            case 'top-left':
                dropdownElement.style.bottom = (targetRect.height + 5) + 'px';
                dropdownElement.style.left = '0';
                break;
            case 'top-right':
                dropdownElement.style.bottom = (targetRect.height + 5) + 'px';
                dropdownElement.style.right = '0';
                break;
            case 'bottom-left':
                dropdownElement.style.top = (targetRect.height + 5) + 'px';
                dropdownElement.style.left = '0';
                break;
            case 'bottom-right':
                dropdownElement.style.top = (targetRect.height + 5) + 'px';
                dropdownElement.style.right = '0';
                break;
            default:
                // 默认位置：显示在屏幕中间
                dropdownElement.style.top = '50%';
                dropdownElement.style.left = '50%';
                dropdownElement.style.transform = 'translate(-50%, -50%)';
                break;
        }
    }

}
let juiInstance = null;
window.addEventListener('load', () => {
    if (!juiInstance) {
        juiInstance = new JUI();
        window.jui = juiInstance;
    }
});


// class JTip extends HTMLElement {
//     constructor() {
//         super();

//         // 创建 Shadow DOM
//         this.attachShadow({ mode: 'open' });

//         // 获取组件属性
//         this.title = this.getAttribute('title') || '';
//         this.icon = this.getAttribute('icon') || '';
//         this.theme = this.getAttribute('theme') || '';
//         this.type = this.getAttribute('type') || '';
//         this.close = this.getAttribute('close');

//         // 创建模板
//         const render = this.render();
//         this.shadowRoot.appendChild(render);

//         // 关闭按钮点击事件
//         if (this.close) {
//             this.closeAction();
//         }

//         // 添加样式
//         const styles = this.addStyles();
//         this.shadowRoot.appendChild(styles);

//         // 创建外部样式
//         const iconLink = document.createElement('link');
//         iconLink.setAttribute('rel', 'stylesheet');
//         iconLink.setAttribute('href', 'https://cdn.bootcdn.net/ajax/libs/remixicon/3.3.0/remixicon.min.css');
//         // this.shadowRoot.appendChild(iconLink);
//     }

//     render(){
//         const tip = document.createElement('div');
//         tip.classList.add('j-tip');
//         if (this.theme) {
//             tip.classList.add('is-' + this.theme);
//         }
//         const content = document.createElement('div');
//         content.classList.add('tip-content');
//         const slot = document.createElement('slot');
//         if (this.title) {
//             const title = `<div class="tip-content-title">${this.title}</div>`;
//             content.insertAdjacentHTML('beforeend', title);

//             const _content = document.createElement('div');
//             _content.classList.add('tip-content-info');
//             _content.appendChild(slot);
//             content.appendChild(_content);
//         } else {
//             content.appendChild(slot);
//         }
//         if (this.icon) {
//             const icon = document.createElement('i');
//             icon.classList.add('el-prefix', this.icon);
//             tip.appendChild(icon);
//         }
//         if (this.type) {
//             const type = document.createElement('span');
//             type.classList.add('el-prefix');
//             let _type;
//             const _style = `width:16px;height:16px;color:inherit;`
//             switch (this.type) {
//                 case 'info':
//                     _type = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="'+_style+'"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM11 15H13V17H11V15ZM11 7H13V13H11V7Z"></path></svg>';
//                     break;
//             }
//             type.insertAdjacentHTML('beforeend', _type);
//             tip.appendChild(type);
//         }
//         tip.appendChild(content);
//         if (this.close) {
//             const close = document.createElement('i');
//             close.classList.add('el-suffix','action-close','ri-close-line');
//             tip.appendChild(close);
//         }
//         return tip;
//     }

//     closeAction() {
//         const close = this.shadowRoot.querySelector('.action-close');
//         close.addEventListener('click', () => {
//             this.remove();
//         });
//     }

//     addStyles() {
//         const styles = `
// .j-tip {
//     display: flex;
//     justify-content: space-between;
//     align-items: flex-start;
//     gap: var(--gap-s);
//     margin-bottom: var(--gap-m);
//     padding: var(--gap-m);
//     font-size: var(--text-n);
//     font-weight: 400;
//     border-left: var(--border-4) solid;
//     border-top-left-radius: 4px;
//     border-bottom-left-radius: 4px;
//     border-color: inherit;
//     color: inherit;
//     background-color: inherit;
//     transition:
//         color .3s,
//         border-color .3s,
//         background-color .3s;
// }

// .j-tip .tip-content {
//     flex: 1;
// }

// .j-tip .el-suffix {
//     cursor: pointer;
// }

// .j-tip .tip-content .tip-content-title {
//     font-weight: 600;
//     font-size: var(--text-n);
//     margin-bottom: var(--gap-s);
// }

// .j-tip .tip-content .tip-content-info > *:not(:last-child) {
//     margin-bottom: var(--gap-xs);
// }

// .j-tip.is-info {
//     color: var(--neutral20);
//     background-color: var(--neutral95);
//     border-left-color: var(--neutral20);
// }

// .j-tip.is-primary {
//     color: white;
//     background-color: var(--primary50);
//     border-left-color: var(--primary40);
// }

// .j-tip.is-secondary {
//     color: white;
//     background-color: var(--secondary40);
//     border-left-color: var(--secondary30);
// }

// .j-tip.is-success {
//     color: white;
//     background-color: var(--success40);
//     border-left-color: var(--success30);
// }

// .j-tip.is-warning {
//     color: white;
//     background-color: var(--warning50);
//     border-left-color: var(--warning40);
// }

// .j-tip.is-error {
//     color: white;
//     background-color: var(--error50);
//     border-left-color: var(--error40);
// }

// .j-tip.is-neutral {
//     color: white;
//     background-color: var(--neutral20);
//     border-left-color: var(--neutral10);
// }

// .is-dark .j-tip {
//     color: inherit;
//     border-left-color: inherit;
//     background-color: inherit;
// }

// .is-dark .j-tip.is-info {
//     background-color: var(--neutral20);
//     border-left-color: var(--neutral15);
// }

// .is-dark .j-tip.is-primary {
//     background-color: var(--primary60);
//     border-left-color: var(--primary50);
// }

// .is-dark .j-tip.is-secondary {
//     background-color: var(--secondary40);
//     border-left-color: var(--secondary50);
// }

// .is-dark .j-tip.is-success {
//     background-color: var(--success40);
//     border-left-color: var(--success30);
// }

// .is-dark .j-tip.is-warning {
//     background-color: var(--warning55);
//     border-left-color: var(--warning50);
// }

// .is-dark .j-tip.is-error {
//     background-color: var(--error55);
//     border-left-color: var(--error50);
// }

// .is-dark .j-tip.is-neutral {
//     color: var(--neutral10);
//     background-color: var(--neutral95);
//     border-left-color: var(--neutral15);
// }
//         `;

//         const styleElement = document.createElement('style');
//         styleElement.textContent = styles;
//         return styleElement;
//     }
// }

// // 定义自定义元素
// customElements.define('j-tip', JTip);