import jui from 'jui'

document.documentElement.classList.add('j-theme-indigo')
const langs = {
  zh: {
    'Login via WeChat QrCode': '微信扫码登录',
    'Failed to get QrCode': '获取二维码失败',
  },
}
const t = (key) => {
  return jui.u.t(key, langs)
}
const post = (url, data) => {
  return jui.u.postJson(url, data)
}

const modal = new jui.modal({
  title: t('Login via WeChat QrCode'),
  footer: false,
  escClose: true,
  content: '<div id="login-qrcode" style="text-align:center;min-height:280px"></div>',
  onHidden: () => {
    modal.hideLoading()
  },
})

const subscribeCookie = 'g3_wechat_login_hash'

for (const element of document.querySelectorAll('[data-login-element]')) {
  element.addEventListener('click', (e) => {
    e.preventDefault()
    modal.show()
    modal.showLoading()

    const wrap = document.querySelector('#login-qrcode')
    const initLogin = async () => {
      let hash = jui.u.getCookie(subscribeCookie)
      if (!hash) {
        try {
          hash = jui.u.uuid()
          jui.u.setCookie(subscribeCookie, hash, 1800)
        } catch (err) {
          jui.toast.error(err.message)
          return
        }
      }
      try {
        const qrRes = await post('/wp-json/api/v1/auth/wechat/subscribe/qrcode', {
          hash: `login:${hash}`,
        })

        if (qrRes.code !== 200 || !qrRes.data?.url) {
          throw new Error(qrRes.message || t('Failed to get QrCode'))
        }

        // render qrcode
        setTimeout(() => {
          modal.hideLoading()
          wrap.innerHTML = `<img 
							src="${qrRes.data.url}" 
							alt="${t('Login via WeChat QrCode')}"
							style="width:280px; height:auto; outline:1px solid var(--gray-3)"
						/>`
        }, 800)

        // delay polling validate
        setTimeout(() => {
          startPolling(hash)
        }, 2000)
      } catch (err) {
        jui.toast.error(err.message)
      }
    }
    const startPolling = (hash) => {
      const pollInterval = 3000
      const poll = async () => {
        try {
          const res = await post('/wp-json/api/v1/auth/wechat/subscribe/validate', {
            hash: `login:${hash}`,
          })
          if (res.success === true) {
            jui.toast.success(res.message)
            jui.u.deleteCookie(subscribeCookie)
            setTimeout(() => {
              window.location.href = '/'
            }, 500)
          } else {
            if (res.status && res.status === 'expired') {
              jui.toast.warning(res.message)
              jui.u.deleteCookie(subscribeCookie)
              // stop polling
              return
            }
            // pending
            setTimeout(poll, pollInterval)
          }
        } catch {
          setTimeout(poll, pollInterval * 2)
        }
      }
      poll()
    }
    initLogin()
  })
}
