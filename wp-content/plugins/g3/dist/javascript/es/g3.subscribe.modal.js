import jui from 'jui'

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
  content:
    '<div id="login-qrcode" style="text-align:center;min-height:200px"></div><p id="login-msg" style="text-align:center"></p>',
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
    const msg = document.querySelector('#login-msg')
    const initLogin = async () => {
      let hash = jui.u.getCookie(subscribeCookie)
      if (!hash) {
        try {
          hash = jui.u.uuid()
          jui.u.setCookie(subscribeCookie, hash, 1800)
        } catch (err) {
          msg.innerHTML = `<span style="color:red">${err.message}</span>`
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
        modal.hideLoading()
        wrap.innerHTML = `<img 
							src="${qrRes.data.url}" 
							alt="${t('Login via WeChat QrCode')}"
							style="width:220px; height:auto; border:1px solid #eee; border-radius:8px;"
						/>`

        // delay polling validate
        setTimeout(() => {
          startPolling(hash)
        }, 2000)
      } catch (err) {
        msg.innerHTML = `<span style="color:red">${err.message}</span>`
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
            msg.textContent = res.message
            msg.style.color = 'green'
            jui.u.deleteCookie(subscribeCookie)
            setTimeout(() => {
              window.location.href = '/'
            }, 500)
          } else {
            if (res.status && res.status === 'expired') {
              msg.textContent = res.message
              msg.style.color = '#d97706'
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
