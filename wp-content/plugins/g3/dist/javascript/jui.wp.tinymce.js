(function () {
    tinymce.PluginManager.add('jl_highlight', function (editor, url) {
        // 注册自定义按钮的点击事件
        editor.addButton('jl_highlight', {
            // text: '代码',
            // icon: false,
            icon: 'wp_code',
            title: '插入代码块',
            onclick: function () {
                editor.windowManager.open({
                    title: '代码块设置',
                    body: [
                        {
                            type: 'label',
                            text: '请输入代码语言,如PHP'
                        },
                        {
                            type: 'textbox',
                            name: 'language',
                        }
                    ],
                    onsubmit: function (e) {
                        var language = e.data.language;
                        var selected_text = editor.selection.getContent();

                        editor.windowManager.open({
                            title: '请输入' + language + '代码',
                            body: [
                                {
                                    type: 'textbox',
                                    name: 'code_content',
                                    multiline: true,
                                    minWidth: 300,
                                    minHeight: 150,
                                }
                            ],
                            onsubmit: function (e) {
                                var code_content = e.data.code_content;

                                if (selected_text === '') {
                                    selected_text = language + ' Code Here.';
                                }

                                var code = '<pre class="j-highlight language-' + language + ' line-numbers"><code class="language-' + language + '">' + code_content + '</code></pre>';
                                editor.insertContent(code);
                            }
                        });
                    }
                });
            },
        });
    });
})();
// (function ($) {
//     $(document).ready(function () {
//         // 给编辑器内容变化事件绑定处理函数
//         $('body.wp-editor').on('input propertychange', function () {
//             // 获取编辑器内的内容
//             var content = tinyMCE.activeEditor.getContent();

//             // 使用 highlight.js 对内容进行高亮处理
//             var highlightedContent = hljs.highlightAuto(content).value;

//             // 更新编辑器内的内容
//             tinyMCE.activeEditor.setContent(highlightedContent);
//         });
//     });
// })(jQuery);