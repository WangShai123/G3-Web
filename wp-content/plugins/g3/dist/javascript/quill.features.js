/**
 * Zh: 让quill编辑样式和前台样式一致
 */
const theEditor = document.querySelector('.ql-editor');
theEditor.classList.add('j-content');

/**
 * Zh: 为quill增加自定义功能fullscreen
 * @since 1.0.0
 * @author Wang Shai
 */
const ql_editor = document.querySelector('.ql-editor');
const editor_page_header = document.querySelector('.editor-page-header');
const editor_item_title = document.querySelector('.editor-item_post-title');
const editor_item_config = document.querySelector('.editor-item-config');
const fullscreen_button = document.querySelector('.ql-fullscreen');
const toolbar = document.querySelector('.ql-toolbar');
const editor_item_content = document.querySelector('.editor-item_content');
//定义fullscreen svg图标
fullscreen_button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" d="M0 0h24v24H0V0z"/><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
fullscreen_button.addEventListener('click', function () {
    //判断是否全屏
    if (document.fullscreenElement) {
        //退出全屏
        document.exitFullscreen();
        //显示editor_page_header、editor_item_title、editor_item_config
        editor_page_header.style.display = 'block';
        editor_item_title.style.display = 'flex';
        editor_item_config.style.display = 'block';
        //editor_item_content的高度为100vh - editor_page_header - editor_item_title - toolbar
        editor_item_content.style.height = 'calc(100vh - ' + (editor_page_header.offsetHeight + editor_item_title.offsetHeight + toolbar.offsetHeight) + 'px)';
        //ql_editor的高度为100% - editor_item_config
        ql_editor.style.height = 'calc(100% - ' + editor_item_config.offsetHeight + 'px)';
    } else {
        //进入全屏
        document.documentElement.requestFullscreen();
        //隐藏editor_page_header、editor_item_title、editor_item_config
        editor_page_header.style.display = 'none';
        editor_item_title.style.display = 'none';
        editor_item_config.style.display = 'none';
        //editor_item_content的高度为100vh - toolbar
        editor_item_content.style.height = 'calc(100vh - ' + toolbar.offsetHeight + 'px)';
        //为ql_editor高度为100% - toolbar
        ql_editor.style.height = '100%';
    }
});

/**
 * Zh: 为quill增加自定义功能darkMode
 * @since 1.0.0
 * @autho Wang Shai
 */
const darkmode_button = document.querySelector('.ql-darkmode');
darkmode_button.style.position = 'relative';
const darktext = document.createElement('span');
const sun = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M8 12H10V14H4V12H6C6 8.68629 8.68629 6 12 6C15.3137 6 18 8.68629 18 12C18 15.3137 15.3137 18 12 18V16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8C9.79086 8 8 9.79086 8 12ZM6 20H15V22H6V20ZM2 16H10V18H2V16ZM11 1H13V4H11V1ZM3.51472 4.92893L4.92893 3.51472L7.05025 5.63604L5.63604 7.05025L3.51472 4.92893ZM16.9497 18.364L18.364 16.9497L20.4853 19.0711L19.0711 20.4853L16.9497 18.364ZM19.0711 3.51472L20.4853 4.92893L18.364 7.05025L16.9497 5.63604L19.0711 3.51472ZM23 11V13H20V11H23Z"></path></svg>';
const moon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16 20.3346V18.1351C17.6993 17.2368 19.012 15.7048 19.6233 13.8538C19.0927 13.9506 18.5498 14.0001 18 14.0001C13.0294 14.0001 9 9.97071 9 5.00015C9 4.95455 9.00034 4.909 9.00102 4.86349C7.04146 5.89887 5.60285 7.77593 5.15045 10.0001H3.11775C3.79375 5.73838 7.30375 2.42018 11.6562 2.03711C11.2352 2.93693 11 3.94108 11 5.00015C11 8.86614 14.134 12.0001 18 12.0001C19.475 12.0001 20.8435 11.5439 21.972 10.7649C21.9905 11.0076 22 11.2527 22 11.5001C22 15.5108 19.5146 18.9411 16 20.3346ZM7 20.0001H14V22.0001H7V20.0001ZM4 12.0001H10V14.0001H4V12.0001ZM2 16.0001H12V18.0001H2V16.0001Z" fill="rgba(255,255,255,1)"></path></svg>';
darktext.innerHTML = document.body.classList.contains('is-dark') ? moon : sun;
darkmode_button.appendChild(darktext);
const theme_toggle = document.createElement('input');
theme_toggle.type = 'checkbox';
theme_toggle.style = `
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    `;
theme_toggle.setAttribute('j-selector', 'theme-switch');
darkmode_button.appendChild(theme_toggle);
theme_toggle.checked = document.body.classList.contains('is-dark');
theme_toggle.addEventListener('change', () => {
    darktext.innerHTML = theme_toggle.checked ? moon : sun;
});

/**
 * Zh: 为quill增加自定义功能paywall插入
 * @since 1.0.0
 * @autho Wang Shai
 */
const ql_paywall = document.querySelector('.ql-paywall');
ql_paywall.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path class="ql-fill" d="M12.0049 22.0029C6.48204 22.0029 2.00488 17.5258 2.00488 12.0029C2.00488 6.48008 6.48204 2.00293 12.0049 2.00293C17.5277 2.00293 22.0049 6.48008 22.0049 12.0029C22.0049 17.5258 17.5277 22.0029 12.0049 22.0029ZM12.0049 20.0029C16.4232 20.0029 20.0049 16.4212 20.0049 12.0029C20.0049 7.58465 16.4232 4.00293 12.0049 4.00293C7.5866 4.00293 4.00488 7.58465 4.00488 12.0029C4.00488 16.4212 7.5866 20.0029 12.0049 20.0029ZM8.50488 14.0029H14.0049C14.281 14.0029 14.5049 13.7791 14.5049 13.5029C14.5049 13.2268 14.281 13.0029 14.0049 13.0029H10.0049C8.62417 13.0029 7.50488 11.8836 7.50488 10.5029C7.50488 9.12222 8.62417 8.00293 10.0049 8.00293H11.0049V6.00293H13.0049V8.00293H15.5049V10.0029H10.0049C9.72874 10.0029 9.50488 10.2268 9.50488 10.5029C9.50488 10.7791 9.72874 11.0029 10.0049 11.0029H14.0049C15.3856 11.0029 16.5049 12.1222 16.5049 13.5029C16.5049 14.8836 15.3856 16.0029 14.0049 16.0029H13.0049V18.0029H11.0049V16.0029H8.50488V14.0029Z"></path></svg>';
ql_paywall.addEventListener('click', function () {
    const range = editor.getSelection(true);
    editor.insertText(range.index, '\n[paywall]\n请把需要用户知识付费才能访问的内容写在这里\n[/paywall]');
    editor.setSelection(range.index + 9, 6);
});
