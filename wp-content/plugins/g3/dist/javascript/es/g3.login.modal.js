import jui from 'jui'

const langs = {
  zh: {
    login: '登录',
    username: '用户名',
    'Enter Username Please': '请输入用户名',
    password: '密码',
    'Enter Password Please': '请输入密码',
    reset: '重置',
    'Username Required': '用户名不能为空',
    'Password Required': '密码不能为空',
    'Login Success': '登录成功',
  },
}
const t = (key) => {
  return jui.u.t(key, langs)
}
const post = (url, data) => {
  return jui.u.postJson(url, data)
}
const modal = new jui.modal({
  title: t('login'),
  confirmText: t('login'),
  cancelText: t('reset'),
  escClose: true,
  fields: [
    {
      label: t('username'),
      type: 'text',
      name: 'username',
      id: 'username',
      placeholder: t('Enter Username Please'),
      required: true,
    },
    {
      label: t('password'),
      type: 'password',
      name: 'password',
      id: 'password',
      placeholder: t('Enter Password Please'),
      required: true,
    },
  ],
  onSubmit: (data) => {
    if (!data.username.trim()) {
      jui.toast.error(t('Username Required'))
      return
    }
    if (!data.password.trim()) {
      jui.toast.error(t('Password Required'))
    }
    post('/wp-json/api/v1/auth/login', {
      username: data.username,
      password: data.password,
    }).then((data) => {
      if (data.code === 200) {
        jui.toast.success(t('Login Success'))
        setTimeout(() => {
          window.location.href = '/'
        }, 1000)
      } else {
        jui.toast.error(data.message)
      }
    })
  },
})
// Get the login element
for (const element of document.querySelectorAll('[data-login-element]')) {
  element.addEventListener('click', (e) => {
    e.preventDefault()
    modal.show()
  })
}
