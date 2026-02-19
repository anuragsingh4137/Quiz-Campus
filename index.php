<?php
// index.php
require 'db.php';

// fetch active ads
$ads = [];
$q = $conn->query("SELECT id, title, caption, image FROM ads WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
if ($q) { $ads = $q->fetch_all(MYSQLI_ASSOC); }
$hasAds = count($ads) > 0;
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Quiz Campus - Home</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php if ($hasAds): ?>
  <!-- Ads header shows only when there are active ads -->
  <header class="hero-ads" aria-label="Promotions">
    <button class="nav prev" aria-label="Previous slide">‹</button>

    <div class="track">
      <?php foreach ($ads as $i => $ad): ?>
        <figure class="slide <?= $i === 0 ? 'is-active' : '' ?>">
          <img src="<?= htmlspecialchars($ad['image']) ?>" alt="<?= htmlspecialchars($ad['title'] ?: 'Ad') ?>">
          <?php if (!empty($ad['title']) || !empty($ad['caption'])): ?>
            <figcaption>
              <?php if (!empty($ad['title'])): ?>
                <h3><?= htmlspecialchars($ad['title']) ?></h3>
              <?php endif; ?>
              <?php if (!empty($ad['caption'])): ?>
                <p><?= htmlspecialchars($ad['caption']) ?></p>
              <?php endif; ?>
            </figcaption>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>

    <button class="nav next" aria-label="Next slide">›</button>
    <div class="dots" role="tablist" aria-label="Choose slide"></div>
  </header>
<?php endif; ?>

  <div class="index-container">
    <div class="header-logo">
      <img src="css/Quiz Campus  logo.png" alt="Quiz Campus logo">
      <div class="title">Quiz Campus</div>
    </div>

    <div class="box">
      <h2>Welcome to Quiz Campus</h2>
      <p style="margin-bottom:12px;">Choose an action</p>
      <div style="display:flex; gap:10px; justify-content:center;">
        <a class="btn btn-primary" href="login.php">Login</a>
        <a class="btn btn-secondary" href="register.php">Register</a>
      </div>
    </div>
  </div>
  <footer class="main-footer">
  <div class="footer-container">

    <div class="footer-section">
      <h4>Quiz Campus</h4>
      <p>Your smart platform for quizzes, exams, and learning.</p>
    </div>

    <div class="footer-section">
      <h4>Links</h4>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="login.php">Login</a></li>
        <li><a href="register.php">Register</a></li>
      </ul>
    </div>

     <div class="footer-section">
  <h4>Contact</h4>

  <p>
    📧 <a href="mailto:quizcampus944@gmail.com">quizcampus944@gmail.com</a>
  </p>

  <p>
    📞 <a href="tel:+9779840126117">+977-9840126117</a>
  </p>

  <p>
    📍 <a href="https://maps.google.com/?q=Nepal" target="_blank">
      Nepal
    </a>
  </p>
</div>
  </div>

  <div class="footer-bottom">
    © <?= date('Y') ?> Quiz Campus. All rights reserved.
  </div>
</footer>


<script>
(function () {
  const hero = document.querySelector('.hero-ads');
  if (!hero) return;                         // no ads => nothing to run

  const slides = Array.from(hero.querySelectorAll('.slide'));
  if (!slides.length) return;

  const prevBtn = hero.querySelector('.prev');
  const nextBtn = hero.querySelector('.next');
  const dotsWrap = hero.querySelector('.dots');

  let i = slides.findIndex(s => s.classList.contains('is-active'));
  if (i < 0) i = 0;

  // dots
  dotsWrap.innerHTML = '';
  slides.forEach((_, idx) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('role','tab');
    b.setAttribute('aria-label', 'Go to slide ' + (idx + 1));
    if (idx === i) b.setAttribute('aria-selected','true');
    b.addEventListener('click', () => go(idx, true));
    dotsWrap.appendChild(b);
  });

  function setActive(idx) {
    slides.forEach(s => s.classList.remove('is-active'));
    slides[idx].classList.add('is-active');
    dotsWrap.querySelectorAll('button').forEach((d, di) => {
      d.toggleAttribute('aria-selected', di === idx);
    });
  }

  function go(nextIndex, fromUser=false) {
    i = (nextIndex + slides.length) % slides.length;
    setActive(i);
    if (fromUser) restart();
  }

  function next() { go(i + 1); }
  function prev() { go(i - 1); }

  if (nextBtn) nextBtn.addEventListener('click', next);
  if (prevBtn) prevBtn.addEventListener('click', prev);

  // autoplay only if more than one slide
  let timer = null;
  function start() { if (slides.length > 1) timer = setInterval(next, 5000); }
  function stop()  { if (timer) clearInterval(timer); }
  function restart(){ stop(); start(); }

  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', start);
  start();

  // simple swipe
  let sx = 0, dx = 0;
  hero.addEventListener('touchstart', e => { sx = e.changedTouches[0].clientX; dx = 0; }, {passive:true});
  hero.addEventListener('touchmove',  e => { dx = e.changedTouches[0].clientX - sx; },   {passive:true});
  hero.addEventListener('touchend',   () => {
    if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); }
    restart();
  });
})();
</script>
</body>
</html>
