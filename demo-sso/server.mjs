import 'dotenv/config'
import express from 'express'
import session from 'express-session'
import * as oidc from 'openid-client'

const required = [
  'OIDC_ISSUER',
  'OIDC_CLIENT_ID',
  'OIDC_CLIENT_SECRET',
  'OIDC_REDIRECT_URI',
  'OIDC_POST_LOGOUT_REDIRECT_URI',
  'SESSION_SECRET',
]

const missing = required.filter((name) => !process.env[name]?.trim())
if (missing.length > 0) {
  console.error(`Konfigurasi .env belum lengkap: ${missing.join(', ')}`)
  process.exit(1)
}

const port = Number(process.env.PORT || 9000)
if (!Number.isInteger(port) || port < 1 || port > 65535) {
  console.error('PORT harus berupa angka antara 1 dan 65535.')
  process.exit(1)
}

const issuer = new URL(process.env.OIDC_ISSUER)
const redirectUri = process.env.OIDC_REDIRECT_URI
const postLogoutRedirectUri = process.env.OIDC_POST_LOGOUT_REDIRECT_URI

// Discovery membaca authorization endpoint, token endpoint, JWKS, userinfo,
// issuer, dan end-session endpoint langsung dari metadata realm Keycloak.
const config = await oidc.discovery(
  issuer,
  process.env.OIDC_CLIENT_ID,
  process.env.OIDC_CLIENT_SECRET,
)

const app = express()
app.disable('x-powered-by')

app.use(
  session({
    name: 'sso_demo_session',
    secret: process.env.SESSION_SECRET,
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      sameSite: 'lax',
      secure: false, // localhost memakai HTTP; ubah true ketika sudah HTTPS.
      maxAge: 60 * 60 * 1000,
    },
  }),
)

app.get('/', async (req, res) => {
  if (!req.session.user) {
    return beginLogin(req, res)
  }

  const user = req.session.user

  return res.type('html').send(
    page(
      'SSO Berhasil',
      `
        <div class="status">✓ Login melalui Keycloak berhasil</div>
        <p>
          Aplikasi demo membuat sesi lokalnya sendiri setelah Keycloak
          mengautentikasi pengguna. Cocokkan identitas berikut dengan akun yang
          sedang login di portal E-Office.
        </p>

        <dl>
          <dt>preferred_username</dt>
          <dd>${escapeHtml(user.preferred_username)}</dd>

          <dt>name</dt>
          <dd>${escapeHtml(user.name)}</dd>

          <dt>email</dt>
          <dd>${escapeHtml(user.email)}</dd>

          <dt>sub</dt>
          <dd class="mono">${escapeHtml(user.sub)}</dd>
        </dl>

        <form method="post" action="/logout">
          <button type="submit">Logout dari Keycloak</button>
        </form>
      `,
    ),
  )
})

app.get('/login', beginLogin)

app.get('/callback', async (req, res, next) => {
  try {
    const pending = req.session.oidc
    if (!pending?.codeVerifier || !pending?.state || !pending?.nonce) {
      return res.status(400).type('html').send(
        page(
          'Sesi login tidak ditemukan',
          '<p>Mulai ulang proses login dari <a href="/">halaman utama</a>.</p>',
        ),
      )
    }

    // Pakai origin dari redirect URI yang dikonfigurasi agar localhost dan
    // 127.0.0.1 tidak tertukar saat memvalidasi callback.
    const currentUrl = new URL(req.originalUrl, redirectUri)

    // openid-client menukar authorization code ke token secara server-to-server.
    // Pada langkah ini authorization response dan ID token divalidasi, termasuk
    // PKCE, state, nonce, signature/JWKS, issuer, audience, dan expiration.
    const tokens = await oidc.authorizationCodeGrant(config, currentUrl, {
      pkceCodeVerifier: pending.codeVerifier,
      expectedState: pending.state,
      expectedNonce: pending.nonce,
      idTokenExpected: true,
    })

    const claims = tokens.claims()
    if (!claims?.sub || !tokens.id_token) {
      throw new Error('Keycloak tidak mengembalikan ID token yang valid.')
    }

    // profile/email biasanya sudah ada di ID token. UserInfo dipakai sebagai
    // fallback agar empat identitas demo tetap tampil bila claim bersifat tipis.
    const userInfo = await oidc.fetchUserInfo(
      config,
      tokens.access_token,
      claims.sub,
    )

    await regenerateSession(req)

    req.session.user = {
      sub: claims.sub,
      preferred_username:
        claims.preferred_username ?? userInfo.preferred_username ?? '-',
      name: claims.name ?? userInfo.name ?? '-',
      email: claims.email ?? userInfo.email ?? '-',
    }
    req.session.idToken = tokens.id_token

    await saveSession(req)
    return res.redirect('/')
  } catch (error) {
    return next(error)
  }
})

app.post('/logout', async (req, res, next) => {
  try {
    const idToken = req.session.idToken

    const logoutUrl = idToken
      ? oidc.buildEndSessionUrl(config, {
          id_token_hint: idToken,
          post_logout_redirect_uri: postLogoutRedirectUri,
        })
      : new URL('/logged-out', postLogoutRedirectUri)

    await destroySession(req)
    res.clearCookie('sso_demo_session')
    return res.redirect(logoutUrl.href)
  } catch (error) {
    return next(error)
  }
})

app.get('/logged-out', (req, res) => {
  return res.type('html').send(
    page(
      'Logout selesai',
      `
        <p>Sesi aplikasi demo dan sesi SSO Keycloak sudah diakhiri.</p>
        <p><a class="button" href="/">Login kembali</a></p>
      `,
    ),
  )
})

app.use((error, req, res, next) => {
  console.error(error)

  if (res.headersSent) {
    return next(error)
  }

  return res.status(500).type('html').send(
    page(
      'Autentikasi gagal',
      `
        <p>${escapeHtml(error?.message || 'Terjadi kesalahan yang tidak diketahui.')}</p>
        <p><a href="/">Coba lagi</a></p>
      `,
    ),
  )
})

app.listen(port, '127.0.0.1', () => {
  console.log(`SSO demo berjalan di http://127.0.0.1:${port}`)
  console.log(`Issuer: ${issuer.href}`)
})

async function beginLogin(req, res, next) {
  try {
    const codeVerifier = oidc.randomPKCECodeVerifier()
    const codeChallenge = await oidc.calculatePKCECodeChallenge(codeVerifier)
    const state = oidc.randomState()
    const nonce = oidc.randomNonce()

    req.session.oidc = { codeVerifier, state, nonce }
    await saveSession(req)

    const authorizationUrl = oidc.buildAuthorizationUrl(config, {
      redirect_uri: redirectUri,
      response_type: 'code',
      scope: 'openid profile email',
      code_challenge: codeChallenge,
      code_challenge_method: 'S256',
      state,
      nonce,
    })

    return res.redirect(authorizationUrl.href)
  } catch (error) {
    return next(error)
  }
}

function saveSession(req) {
  return new Promise((resolve, reject) => {
    req.session.save((error) => (error ? reject(error) : resolve()))
  })
}

function regenerateSession(req) {
  return new Promise((resolve, reject) => {
    req.session.regenerate((error) => (error ? reject(error) : resolve()))
  })
}

function destroySession(req) {
  return new Promise((resolve, reject) => {
    req.session.destroy((error) => (error ? reject(error) : resolve()))
  })
}

function escapeHtml(value) {
  return String(value ?? '-')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
}

function page(title, content) {
  return `<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${escapeHtml(title)} — Demo SSO</title>
  <style>
    :root { font-family: Arial, sans-serif; color: #172033; background: #f3f5f8; }
    body { margin: 0; padding: 32px 16px; }
    main { max-width: 680px; margin: 0 auto; background: white; padding: 28px; border-radius: 14px; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
    h1 { margin-top: 0; }
    .status { padding: 12px 14px; background: #e8f8ee; border: 1px solid #9fd6af; border-radius: 8px; font-weight: 700; }
    dl { display: grid; grid-template-columns: 180px 1fr; gap: 10px 16px; margin: 24px 0; }
    dt { font-weight: 700; }
    dd { margin: 0; overflow-wrap: anywhere; }
    .mono { font-family: Consolas, monospace; }
    button, .button { display: inline-block; border: 0; border-radius: 8px; padding: 11px 16px; background: #173d74; color: white; text-decoration: none; cursor: pointer; font-size: 15px; }
    a { color: #173d74; }
    @media (max-width: 560px) { dl { grid-template-columns: 1fr; } dt { margin-top: 8px; } }
  </style>
</head>
<body>
  <main>
    <h1>${escapeHtml(title)}</h1>
    ${content}
  </main>
</body>
</html>`
}
