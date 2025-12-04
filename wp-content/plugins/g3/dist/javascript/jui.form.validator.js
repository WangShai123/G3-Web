class JFormValidator {

    constructor(options) {
        this.form = options.form || '#j-form',
        this.rules = options.rules || {},
        this.messages = options.messages || {},
        // submit callback
        this.submit = options.submit || function () { },
        this.init();
    }

    init() {
        this.formElem = document.querySelector(this.form);
        this.formElem.addEventListener('submit', (e) => {
            e.preventDefault();
            this.validate();
        });
        this.formElem.addEventListener('reset', (e) => {
            this.reset();
        });
    }

    validate() {
        let valid = true;
        for (let input of this.formElem.elements) {
            let inputName = input.name;
            if (this.rules[inputName]) {
                valid = this.validateRule(input, inputName);
                if (!valid) break;
            }
        }
        if (valid && this.submit) {
            this.submit();
        }
    }

    validateRule(input, inputName) {
        let valid = true;
        let rules = this.rules[inputName];

        // 检查是否自定义了浏览器默认的验证规则
        if (input.hasAttribute('required')
            || input.hasAttribute('minlength')
            || input.hasAttribute('maxlength')
            || input.hasAttribute('pattern')
            || input.hasAttribute('min')
            || input.hasAttribute('max')
            || input.hasAttribute('step')) {
            return valid;
        }
        // 如果没有，则使用下面规则进行验证
        for (let rule in rules) {
            switch (rule) {
                // 验证项1：必填
                case 'required':
                    valid = this.validateRequired(input, rules[rule]);
                    break;
                // 验证项2：最短长度
                case 'minLength':
                    valid = this.validateMinLength(input, rules[rule]);
                    break;
                // 验证项3：最长长度
                case 'maxLength':
                    valid = this.validateMaxLength(input, rules[rule]);
                    break;
                // 验证项4：密码是否一致
                case 'equalTo':
                    valid = this.validateEqualTo(input, rules[rule]);
                    break;
                // 验证项5：邮箱合法性
                case 'email':
                    valid = this.validateEmail(input);
                    break;
                // 验证项6：复选框是否选中
                case 'checked':
                    valid = this.validateCheck(input, rules[rule]);
                    break;
                /**
                 * 验证项7：是否包含空格
                 * @since 1.0.0
                 */
                case 'noSpace':
                    valid = this.validateNoSpace(input, rules[rule]);
                    break;
                /**
                 * 验证项8: 不支持中文
                 * @since 1.0.0
                 */
                case 'noChinese':
                    valid = !/[\u4e00-\u9fa5]/.test(input.value);
                    break;
                /**
                 * 验证项9: 不支持特殊字符
                 * @since 1.0.0
                 */
                case 'noSpecial':
                    valid = !/[@#\$%\^&\*]+/g.test(input.value);
                    break;
                /**
                 * 验证项10: 自定义正则表达式
                 * @since 1.0.0
                 */
                case 'pattern':
                    valid = new RegExp(rules[rule]).test(input.value);
                    break;
                // 更多验证项待补充
            }
            if (!valid) {
                /**
                 * 废弃原有的messages配置规则
                 */
                // this.showMsg(input, `${inputName} ${this.messages[rule]}`);

                /**
                 * 修改messages配置规则，保持与rules配置规则一致
                 * @since 1.0.0
                 */
                const errorMessage = this.messages[inputName] && this.messages[inputName][rule];
                if (errorMessage) {
                    this.showMsg(input, errorMessage);
                } else {
                    this.showMsg(input, `${inputName} ${rule}`);
                }
                break;
            } else {
                this.showSuccess(input);
            }
        }
        return valid;
    }

    // 验证必填项
    validateRequired(input, required) {
        if (required && input.value.trim() === '') {
            return false;
        }
        return true;
    }

    // 验证最短长度
    validateMinLength(input, minLength) {
        if (input.value.length < minLength) {
            return false;
        }
        return true;
    }

    // 验证最长长度
    validateMaxLength(input, maxLength) {
        if (input.value.length > maxLength) {
            return false;
        }
        return true;
    }

    // 验证邮箱
    validateEmail(input) {
        let emailPattern = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
        if (!emailPattern.test(input.value)) {
            return false;
        }
        return true;
    }

    // 验证密码是否一致
    validateEqualTo(input, targetName) {
        let targetInput = this.formElem.querySelector('[name="' + targetName + '"]');
        if (input.value !== targetInput.value) {
            return false;
        }
        return true;
    }

    // 验证复选框
    validateCheck(input, checked) {
        if (input.type === 'checkbox') {
            if (checked && !input.checked) {
                return false;
            }
            return true;
        }
    }

    // 验证是否包含空格
    validateNoSpace(input, noSpace) {
        if (noSpace && /\s/.test(input.value)) {
            return false;
        }
        return true;
    }

    // 显示错误信息
    showMsg(input, msg) {
        let formItem = input.closest('.form-item');
        let formItemContent = formItem.querySelector('.form-item-content');
        let errorTip = formItemContent.querySelector('.error-tip');
        if (!errorTip) {
            errorTip = document.createElement('div');
            errorTip.className = 'error-tip';
            formItemContent.appendChild(errorTip);
        }
        errorTip.innerText = msg;
        formItem.classList.remove('is-success');
        formItem.classList.add('is-error');
    }

    // 验证通过
    showSuccess(input) {
        let formItem = input.closest('.form-item');
        let errorTip = formItem.querySelector('.error-tip');
        if (errorTip) {
            errorTip.remove();
        }
        formItem.classList.remove('is-error');
        formItem.classList.add('is-success');
    }

    reset() {
        let formItems = this.formElem.querySelectorAll('.form-item');
        let formCheckboxes = this.formElem.querySelectorAll('.j-checkbox');
        formItems.forEach(item => {
            item.classList.remove('is-error');
            item.classList.remove('is-success');
            let errorTip = item.querySelector('.error-tip');
            if (errorTip) {
                errorTip.remove();
            }
        });
        formCheckboxes.forEach(item => {
            item.classList.remove('is-checked');
        });
    }
}
export default JFormValidator;