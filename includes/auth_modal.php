<?php
// auth-modal.php — модальное окно входа/регистрации
// Подключается перед </body> в footer.php
$current_user = auth_user();
if ($current_user) return; // не показываем модалку если уже залогинен
?>
<style>
.auth-overlay {
  display: none; position: fixed; inset: 0; z-index: 100;
  background: rgba(18, 30, 43, 0.5); backdrop-filter: blur(4px);
  align-items: center; justify-content: center; padding: 1rem;
}
.auth-overlay.open { display: flex; }
.auth-modal-box {
  background: #fff; border-radius: 0.75rem; width: 100%; max-width: 28rem;
  padding: 2rem; box-shadow: 0 20px 60px -15px rgba(0,0,0,0.3);
  animation: authSlide 0.2s ease-out;
}
@keyframes authSlide { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.auth-modal-box h2 { font-family: var(--font-display); font-size: 1.75rem; text-align: center; margin-bottom: 1.5rem; }
.auth-tabs { display: flex; gap: 0.25rem; background: var(--secondary); border-radius: 0.5rem; padding: 0.25rem; margin-bottom: 1.5rem; }
.auth-tab { flex: 1; padding: 0.5rem; font-size: 0.875rem; font-weight: 500; border-radius: 0.375rem; text-align: center; cursor: pointer; transition: background 0.15s; border: none; background: transparent; }
.auth-tab.active { background: #fff; color: var(--foreground); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.auth-tab:not(.active) { color: var(--muted-fg); }
.auth-form { display: none; }
.auth-form.active { display: block; }
.auth-form .form-group { margin-bottom: 1rem; }
.auth-form label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.375rem; }
.auth-form input { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--border); border-radius: 0.5rem; font-size: 0.9375rem; transition: border-color 0.15s; }
.auth-form input:focus { outline: none; border-color: var(--ring); box-shadow: 0 0 0 3px rgba(27,107,138,0.15); }
.auth-form .cta-btn { width: 100%; margin-top: 0.5rem; }
.auth-switch { text-align: center; margin-top: 1rem; font-size: 0.875rem; color: var(--muted-fg); }
.auth-switch a { color: var(--accent); font-weight: 500; cursor: pointer; }
.auth-close { position: absolute; top: 1rem; right: 1rem; font-size: 1.5rem; cursor: pointer; color: var(--muted-fg); background: none; border: none; padding: 0.25rem 0.5rem; }
.auth-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius: 0.5rem; padding: 0.625rem 0.875rem; font-size: 0.8125rem; margin-bottom: 1rem; display: none; }
.auth-error.show { display: block; }
.auth-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: 0.5rem; padding: 0.625rem 0.875rem; font-size: 0.8125rem; margin-bottom: 1rem; display: none; }
.auth-success.show { display: block; }
</style>

<div id="authOverlay" class="auth-overlay" onclick="if(event.target===this)closeAuth()">
  <div class="auth-modal-box" style="position:relative">
    <button class="auth-close" onclick="closeAuth()">×</button>

    <div class="auth-tabs">
      <button class="auth-tab active" onclick="switchAuthTab('login')">Вход</button>
      <button class="auth-tab" onclick="switchAuthTab('register')">Регистрация</button>
    </div>

    <!-- Login Form -->
    <div id="authLoginForm" class="auth-form active">
      <h2>С возвращением!</h2>
      <div id="loginError" class="auth-error"></div>
      <form onsubmit="return doLogin(event)">
        <div class="form-group">
          <label>Email</label>
          <input type="email" id="loginEmail" name="email" placeholder="you@example.com" required>
        </div>
        <div class="form-group">
          <label>Пароль</label>
          <input type="password" id="loginPass" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="cta-btn">Войти</button>
      </form>
      <div class="auth-switch">
        <a onclick="switchAuthTab('register')">Нет аккаунта? Зарегистрироваться</a>
      </div>
    </div>

    <!-- Register Form -->
    <div id="authRegisterForm" class="auth-form">
      <h2>Создать аккаунт</h2>
      <div id="regError" class="auth-error"></div>
      <div id="regSuccess" class="auth-success"></div>
      <form onsubmit="return doRegister(event)">
        <div class="form-group">
          <label>Имя</label>
          <input type="text" id="regName" name="name" placeholder="Как вас зовут?" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" id="regEmail" name="email" placeholder="you@example.com" required>
        </div>
        <div class="form-group">
          <label>Телефон</label>
          <input type="tel" id="regPhone" name="phone" placeholder="+7 999 123-45-67" oninput="formatPhone(this)">
        </div>
        <div class="form-group">
          <label>Пароль</label>
          <input type="password" id="regPass" name="password" placeholder="Минимум 6 символов" required>
        </div>
        <button type="submit" class="cta-btn">Зарегистрироваться</button>
      </form>
      <div class="auth-switch">
        <a onclick="switchAuthTab('login')">Уже есть аккаунт? Войти</a>
      </div>
    </div>
  </div>
</div>

<script>
function openAuth(mode) {
  document.getElementById('authOverlay').classList.add('open');
  if (mode) switchAuthTab(mode);
  document.body.style.overflow = 'hidden';
}
function closeAuth() {
  document.getElementById('authOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
function switchAuthTab(mode) {
  document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
  if (mode === 'login') {
    document.querySelectorAll('.auth-tab')[0].classList.add('active');
    document.getElementById('authLoginForm').classList.add('active');
  } else {
    document.querySelectorAll('.auth-tab')[1].classList.add('active');
    document.getElementById('authRegisterForm').classList.add('active');
  }
}
function showErr(id, msg) {
  var el = document.getElementById(id);
  el.textContent = msg;
  el.classList.add('show');
}
function hideErr(id) {
  var el = document.getElementById(id);
  el.classList.remove('show');
}
function formatPhone(input) {
  var digits = input.value.replace(/\D/g, '');
  var d = digits.startsWith('8') ? digits.slice(1) : digits;
  if (d.startsWith('7')) d = d.slice(1);
  d = d.slice(0, 10);
  if (d.length === 0) { input.value = '+7 '; return; }
  var r = '+7 ';
  if (d.length <= 3) r += d;
  else if (d.length <= 6) r += d.slice(0,3) + ' ' + d.slice(3);
  else if (d.length <= 8) r += d.slice(0,3) + ' ' + d.slice(3,6) + '-' + d.slice(6);
  else r += d.slice(0,3) + ' ' + d.slice(3,6) + '-' + d.slice(6,8) + '-' + d.slice(8,10);
  input.value = r;
}
function doLogin(e) {
  e.preventDefault();
  hideErr('loginError');
  var email = document.getElementById('loginEmail').value;
  var pass = document.getElementById('loginPass').value;
  fetch('/login', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'email=' + encodeURIComponent(email) + '&password=' + encodeURIComponent(pass)
  }).then(function(r) { return r.text(); }).then(function(html) {
    if (html.includes('flash error')) {
      showErr('loginError', 'Неверный email или пароль');
    } else {
      window.location.reload();
    }
  }).catch(function() {
    showErr('loginError', 'Ошибка сети. Попробуйте ещё раз.');
  });
  return false;
}
function doRegister(e) {
  e.preventDefault();
  hideErr('regError');
  hideErr('regSuccess');
  var name = document.getElementById('regName').value;
  var email = document.getElementById('regEmail').value;
  var phone = document.getElementById('regPhone').value;
  var pass = document.getElementById('regPass').value;
  if (pass.length < 6) { showErr('regError', 'Пароль должен быть не менее 6 символов'); return false; }
  fetch('/register', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'name=' + encodeURIComponent(name) + '&email=' + encodeURIComponent(email) + '&phone=' + encodeURIComponent(phone) + '&password=' + encodeURIComponent(pass) + '&password2=' + encodeURIComponent(pass)
  }).then(function(r) { return r.text(); }).then(function(html) {
    if (html.includes('flash error')) {
      var match = html.match(/flash error">([^<]+)/);
      showErr('regError', match ? match[1] : 'Ошибка регистрации');
    } else {
      window.location.reload();
    }
  }).catch(function() {
    showErr('regError', 'Ошибка сети. Попробуйте ещё раз.');
  });
  return false;
}
// Open on "Войти" / "Регистрация" clicks
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('a[href="/login"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
      e.preventDefault();
      openAuth('login');
    });
  });
  document.querySelectorAll('a[href="/register"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
      e.preventDefault();
      openAuth('register');
    });
  });
  // ESC to close
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAuth();
  });
});
</script>
