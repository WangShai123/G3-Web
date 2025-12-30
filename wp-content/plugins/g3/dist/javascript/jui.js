/**
 * @class JUI
 * @classdesc JUI
 */
class JUI {

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