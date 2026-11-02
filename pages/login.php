<section class="login-panel login-cinema">
  <div class="login-image">
    <div class="login-image-top">
      <a class="login-brand" href="<?= BASE_URL ?>/index.php?page=shop" aria-label="WinG Fashion House">
        <span class="login-brand-mark">W</span>
        <span><b>WinG</b><small>THE FASHION HOUSE</small></span>
      </a>
      <span class="login-edition">PRIVATE ACCESS <i>·</i> MMXXVI</span>
    </div>
    <div class="image-caption">
      <span>THE ART OF PRESENCE</span>
      <h2>Elegance<br>is an <i>attitude.</i></h2>
      <p>Distinctive style. Considered details.<br>A more personal WinG experience.</p>
    </div>
    <div class="login-image-bottom">
      <span>CRAFTED WITH PURPOSE</span>
      <span>01 <i>—</i> WING FASHION HOUSE</span>
    </div>
    <div class="login-image-grain"></div>
  </div>

  <div class="login-form-wrap">
    <div class="login-form">
      <div class="login-form-heading">
        <span class="eyebrow">WELCOME TO WING</span>
        <h1>Đăng nhập<span>.</span></h1>
        <p>Đăng nhập một lần. WinG sẽ tự nhận diện tài khoản và đưa bạn đến đúng không gian.</p>
      </div>

      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

      <form method="post" class="form-stack login-cinema-form">
        <?= csrf_field() ?>
        <label for="loginIdentity">TÀI KHOẢN HOẶC EMAIL
          <input id="loginIdentity" name="identity" required autocomplete="username"
                 placeholder="Username hoặc email của bạn" autofocus>
        </label>
        <label for="loginPassword">MẬT KHẨU
          <span class="password-field">
            <input id="loginPassword" name="password" type="password" required autocomplete="current-password"
                   placeholder="Nhập mật khẩu">
            <button class="password-toggle" type="button" id="togglePassword" aria-label="Hiện mật khẩu">HIỆN</button>
          </span>
        </label>
        <button class="btn primary full login-submit" type="submit">
          <span>ĐĂNG NHẬP</span><i>↗</i>
        </button>
      </form>

      <div class="login-divider"><span></span><small>YOUR PLACE AT WING</small><span></span></div>
      <div class="login-register">
        <span>Chưa có tài khoản khách hàng?</span>
        <a href="<?= BASE_URL ?>/index.php?page=customer_register">ĐĂNG KÝ THÀNH VIÊN <b>↗</b></a>
      </div>
      <a class="login-back-store" href="<?= BASE_URL ?>/index.php?page=shop">← QUAY LẠI CỬA HÀNG</a>
    </div>
    <div class="login-form-footer"><span>© 2026 WING FASHION HOUSE</span><span>PRIVATE & SECURE ACCESS</span></div>
  </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('loginPassword');
  const toggle = document.getElementById('togglePassword');
  if (input && toggle) toggle.addEventListener('click', () => {
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    toggle.textContent = reveal ? 'ẨN' : 'HIỆN';
    toggle.setAttribute('aria-label', reveal ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
  });
});
</script>
