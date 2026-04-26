(() => {
  function initScanlines() {
    if (!document.body) return;

    const existingCanvas = document.getElementById('scanline-canvas');
    const canvas = existingCanvas || document.createElement('canvas');
    canvas.id = 'scanline-canvas';
    canvas.style.cssText =
      'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9998;';
    if (!existingCanvas) document.body.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const scanColor = getComputedStyle(document.body).color;
    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let scrollY = 0;
    let lastTouchX = 0,
      lastTouchY = 0;
    let isTouch = false;
    let devicePixelRatio = window.devicePixelRatio || 1;

    const isMobile = /iPhone|iPad|Android|Mobile/i.test(navigator.userAgent);

    const PARAMS = isMobile
      ? {
          gaussianSigma: 60,
          maxGaussian: 80,
          glitchChance: 0.08,
          opacityBase: 0.25,
          lineHeight: 3,
        }
      : {
          gaussianSigma: 100,
          maxGaussian: 60,
          glitchChance: 0.03,
          opacityBase: 0.15,
          lineHeight: 4,
        };

    function resizeCanvas() {
      devicePixelRatio = window.devicePixelRatio || 1;
      canvas.width = window.innerWidth * devicePixelRatio;
      canvas.height = window.innerHeight * devicePixelRatio;
      // Reset transform so repeated resizes don't compound scaling.
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.scale(devicePixelRatio, devicePixelRatio);
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function gaussian(x, mu, sigma) {
      const a = 1 / (sigma * Math.sqrt(2 * Math.PI));
      return a * Math.exp(-0.5 * Math.pow((x - mu) / sigma, 2));
    }

    document.addEventListener('mousemove', (e) => {
      mouseX = e.clientX;
      mouseY = e.clientY;
      isTouch = false;
    });

    window.addEventListener('scroll', () => {
      scrollY = window.scrollY;
    });

    document.addEventListener(
      'touchmove',
      (e) => {
        if (e.touches.length > 0) {
          lastTouchX = e.touches[0].clientX;
          lastTouchY = e.touches[0].clientY;
          isTouch = true;
        }
      },
      { passive: true }
    );

    let frameCount = 0;
    function drawScanlines() {
      ctx.clearRect(0, 0, canvas.width / devicePixelRatio, canvas.height / devicePixelRatio);

      const numLines = Math.ceil(canvas.height / devicePixelRatio / PARAMS.lineHeight);
      const influenceY = (lastTouchY || mouseY) + scrollY;

      for (let i = 0; i < numLines; i++) {
        const y = i * PARAMS.lineHeight;
        const distanceFromPointer = Math.abs(y - influenceY);
        const gaussianInfluence = gaussian(distanceFromPointer, 0, PARAMS.gaussianSigma) * PARAMS.maxGaussian;
        const noiseMultiplier = isTouch ? 2 : 1.5;
        const noiseOffset = Math.sin(frameCount * 0.08 + i * 0.15) * gaussianInfluence * noiseMultiplier;

        const baseOpacity = PARAMS.opacityBase;
        const dynamicOpacity = baseOpacity + gaussianInfluence * 0.03;

        ctx.strokeStyle = scanColor;
        ctx.lineWidth = 2;
        ctx.globalAlpha = Math.min(dynamicOpacity, 0.6);

        ctx.beginPath();
        ctx.moveTo(0 + noiseOffset, y);
        ctx.lineTo(window.innerWidth + noiseOffset, y);
        ctx.stroke();

        if (gaussianInfluence > 5 && Math.random() < PARAMS.glitchChance) {
          const glitchHeight = Math.random() * 3 + 1;
          const glitchWidth = Math.random() * 200 + 100;
          const glitchX = Math.random() * window.innerWidth;

          ctx.fillStyle = scanColor;
          ctx.globalAlpha = Math.min(0.2 + gaussianInfluence * 0.01, 0.5);
          ctx.fillRect(glitchX, y, glitchWidth, glitchHeight);
        }
      }

      ctx.globalAlpha = 1;
      frameCount++;
      requestAnimationFrame(drawScanlines);
    }

    drawScanlines();
    console.log('✓ Scanlines dynamiques activées');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScanlines, { once: true });
  } else {
    initScanlines();
  }
})();
