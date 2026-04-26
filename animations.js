(() => {
  function initAnimations() {
    if (!document.body) return;

    const cursorColor = getComputedStyle(document.body).color;

    const existingCursor = document.getElementById('custom-cursor');
    const cursor = existingCursor || document.createElement('div');
    cursor.id = 'custom-cursor';
    cursor.style.cssText = `position:fixed;width:20px;height:20px;border:2px solid ${cursorColor};border-radius:50%;pointer-events:none;z-index:10000;opacity:0;transition:opacity 0.3s;box-shadow:0 0 10px ${cursorColor};`;
    if (!existingCursor) document.body.appendChild(cursor);

    let mouseX = 0,
      mouseY = 0;
    document.addEventListener('mousemove', (e) => {
      mouseX = e.clientX;
      mouseY = e.clientY;
      cursor.style.left = mouseX - 10 + 'px';
      cursor.style.top = mouseY - 10 + 'px';
      cursor.style.opacity = '1';
    });
    document.addEventListener('mouseleave', () => {
      cursor.style.opacity = '0';
    });

    document.querySelectorAll('h1').forEach((h1) => {
      h1.addEventListener('click', function () {
        this.style.animation = 'none';
        setTimeout(() => {
          this.style.animation = 'glitch 0.3s';
        }, 10);
      });
    });

    document.querySelectorAll('form').forEach((form) => {
      form.addEventListener('submit', function () {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.style.animation = 'glowPulse 0.6s';
      });
    });

    document.querySelectorAll('.message').forEach((msg) => {
      msg.style.animation = 'slideUp 0.5s ease-out';
    });

    console.log('✓ Animations chargées');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAnimations, { once: true });
  } else {
    initAnimations();
  }
})();
