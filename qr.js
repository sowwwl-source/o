// Minimal QR code renderer — byte mode, version 1-10, error correction L
// Renders [data-qr="URL"] canvas elements automatically on load.
// Based on public domain QR algorithm. No external dependencies.

(function () {
  'use strict';

  // ── Reed-Solomon GF(256) ───────────────────────────────────────────────────

  const GF_EXP = new Uint8Array(512);
  const GF_LOG = new Uint8Array(256);
  (function () {
    let x = 1;
    for (let i = 0; i < 255; i++) {
      GF_EXP[i] = x;
      GF_LOG[x] = i;
      x = x << 1;
      if (x & 0x100) x ^= 0x11d;
    }
    for (let i = 255; i < 512; i++) GF_EXP[i] = GF_EXP[i - 255];
  })();

  function gfMul(a, b) {
    if (a === 0 || b === 0) return 0;
    return GF_EXP[(GF_LOG[a] + GF_LOG[b]) % 255];
  }

  function rsGeneratorPoly(n) {
    let g = [1];
    for (let i = 0; i < n; i++) {
      const ng = new Array(g.length + 1).fill(0);
      for (let j = 0; j < g.length; j++) {
        ng[j] ^= gfMul(g[j], GF_EXP[i]);
        ng[j + 1] ^= g[j];
      }
      g = ng;
    }
    return g;
  }

  function rsEncode(data, nEcc) {
    const gen = rsGeneratorPoly(nEcc);
    const msg = [...data, ...new Array(nEcc).fill(0)];
    for (let i = 0; i < data.length; i++) {
      const coef = msg[i];
      if (coef !== 0) {
        for (let j = 1; j < gen.length; j++) {
          msg[i + j] ^= gfMul(gen[j], coef);
        }
      }
    }
    return msg.slice(data.length);
  }

  // ── QR version / capacity table (byte mode, error correction L) ───────────

  const VERSION_INFO = [
    // [totalModules, dataCodewords, eccCodewords, alignmentPositions]
    null, // index 0 unused
    [21,  19,  7,  []],
    [25,  34,  10, [6, 18]],
    [29,  55,  15, [6, 22]],
    [33,  80,  20, [6, 26]],
    [37,  108, 26, [6, 30]],
    [41,  136, 36, [6, 34]],
    [45,  156, 40, [6, 22, 38]],
    [49,  194, 48, [6, 24, 42]],
    [53,  232, 60, [6, 28, 46]],
    [57,  274, 72, [6, 32, 50]],
  ];

  function selectVersion(byteLen) {
    for (let v = 1; v <= 10; v++) {
      if (VERSION_INFO[v][1] >= byteLen + 2) return v; // +2 for mode+length indicators
    }
    return null;
  }

  // ── Bit buffer ─────────────────────────────────────────────────────────────

  function BitBuffer() {
    const bits = [];
    return {
      put(num, len) {
        for (let i = len - 1; i >= 0; i--) bits.push((num >> i) & 1);
      },
      bits,
    };
  }

  // ── QR matrix builder ──────────────────────────────────────────────────────

  function makeMatrix(size) {
    return Array.from({ length: size }, () => new Array(size).fill(-1));
  }

  function placeFinderPattern(mat, row, col) {
    for (let r = -1; r <= 7; r++) {
      for (let c = -1; c <= 7; c++) {
        if (row + r < 0 || mat.length <= row + r || col + c < 0 || mat.length <= col + c) continue;
        const onBorder = r === -1 || r === 7 || c === -1 || c === 7;
        const inner    = (r >= 2 && r <= 4) && (c >= 2 && c <= 4);
        mat[row + r][col + c] = (onBorder || inner) ? 1 : 0;
      }
    }
  }

  function placeAlignmentPattern(mat, row, col) {
    for (let r = -2; r <= 2; r++) {
      for (let c = -2; c <= 2; c++) {
        mat[row + r][col + c] = (Math.abs(r) === 2 || Math.abs(c) === 2 || (r === 0 && c === 0)) ? 1 : 0;
      }
    }
  }

  function placeTiming(mat, size) {
    for (let i = 8; i < size - 8; i++) {
      const v = i % 2 === 0 ? 1 : 0;
      if (mat[6][i] === -1) mat[6][i] = v;
      if (mat[i][6] === -1) mat[i][6] = v;
    }
  }

  function placeFormatInfo(mat, size, maskPattern) {
    // Format info for ECC level L (01) + mask pattern
    const formatData = [0x77c4, 0x72f3, 0x7daa, 0x789d, 0x662f, 0x6318, 0x6c41, 0x6976];
    const fmt = formatData[maskPattern];
    const positions = [
      [8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],
      [7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8],
    ];
    for (let i = 0; i < 15; i++) {
      const bit = (fmt >> (14 - i)) & 1;
      mat[positions[i][0]][positions[i][1]] = bit;
    }
    // Mirror
    for (let i = 0; i < 7; i++) {
      mat[8][size - 1 - i] = (fmt >> i) & 1;
    }
    for (let i = 7; i < 15; i++) {
      mat[size - 15 + i][8] = (fmt >> i) & 1;
    }
    mat[size - 8][8] = 1; // dark module
  }

  function isFunction(mat, size, r, c) {
    // Already placed (not -1)
    return mat[r][c] !== -1;
  }

  function placeData(mat, size, dataBits) {
    let bitIdx = 0;
    for (let right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (let vert = 0; vert < size; vert++) {
        for (let j = 0; j < 2; j++) {
          const upward = ((right + 1) & 2) === 0;
          const col = right - j;
          const row = upward ? size - 1 - vert : vert;
          if (mat[row][col] !== -1) continue;
          mat[row][col] = bitIdx < dataBits.length ? dataBits[bitIdx++] : 0;
        }
      }
    }
  }

  function applyMask(mat, size, mask) {
    for (let r = 0; r < size; r++) {
      for (let c = 0; c < size; c++) {
        const cond = [
          (r + c) % 2 === 0,
          r % 2 === 0,
          c % 3 === 0,
          (r + c) % 3 === 0,
          (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0,
          ((r * c) % 2 + (r * c) % 3) === 0,
          ((r * c) % 2 + (r * c) % 3) % 2 === 0,
          ((r + c) % 2 + (r * c) % 3) % 2 === 0,
        ][mask];
        if (cond && mat[r][c] < 2) mat[r][c] ^= 1;
      }
    }
  }

  // ── Main encode ───────────────────────────────────────────────────────────

  function encode(text) {
    const bytes = Array.from(new TextEncoder().encode(text));
    const version = selectVersion(bytes.length);
    if (!version) return null;

    const [size, dataWords, eccWords, alignPos] = VERSION_INFO[version];
    const totalWords = dataWords + eccWords;

    // Build data codewords
    const buf = BitBuffer();
    buf.put(0b0100, 4); // byte mode
    buf.put(bytes.length, 8);
    bytes.forEach(b => buf.put(b, 8));
    // Terminator
    while (buf.bits.length < dataWords * 8 - 4) buf.put(0, 1);
    buf.put(0, Math.min(4, dataWords * 8 - buf.bits.length));
    while (buf.bits.length % 8 !== 0) buf.put(0, 1);
    // Pad bytes
    const padBytes = [0xec, 0x11];
    let pi = 0;
    while (buf.bits.length < dataWords * 8) {
      buf.put(padBytes[pi++ % 2], 8);
    }

    // Pack bits into bytes
    const dataCodewords = [];
    for (let i = 0; i < dataWords; i++) {
      let val = 0;
      for (let b = 0; b < 8; b++) val = (val << 1) | buf.bits[i * 8 + b];
      dataCodewords.push(val);
    }

    const eccCodewords = rsEncode(dataCodewords, eccWords);
    const allBits = [...dataCodewords, ...eccCodewords]
      .flatMap(byte => Array.from({ length: 8 }, (_, i) => (byte >> (7 - i)) & 1));

    // Build matrix
    const mat = makeMatrix(size);

    placeFinderPattern(mat, 0, 0);
    placeFinderPattern(mat, size - 7, 0);
    placeFinderPattern(mat, 0, size - 7);

    // Separators already handled by finder pattern border

    if (version >= 2 && alignPos.length >= 2) {
      const ap = alignPos;
      for (let i = 0; i < ap.length; i++) {
        for (let j = 0; j < ap.length; j++) {
          const r = ap[i], c = ap[j];
          if (mat[r][c] === -1) placeAlignmentPattern(mat, r, c);
        }
      }
    }

    placeTiming(mat, size);
    // Reserve format info areas
    for (let i = 0; i < 9; i++) { if (mat[8][i] === -1) mat[8][i] = 0; }
    for (let i = 0; i < 8; i++) { if (mat[i][8] === -1) mat[i][8] = 0; }
    for (let i = size - 8; i < size; i++) { if (mat[8][i] === -1) mat[8][i] = 0; }
    for (let i = size - 7; i < size; i++) { if (mat[i][8] === -1) mat[i][8] = 0; }

    // Place data with mask 0
    placeData(mat, size, allBits);
    applyMask(mat, size, 0);
    placeFormatInfo(mat, size, 0);

    return { matrix: mat, size };
  }

  // ── Canvas renderer ────────────────────────────────────────────────────────

  function renderQR(canvas, text) {
    const qr = encode(text);
    if (!qr) return;

    const { matrix, size } = qr;
    const canvasSize = canvas.width || 180;
    const moduleSize = Math.floor(canvasSize / (size + 8));
    const offset = Math.floor((canvasSize - moduleSize * size) / 2);

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvasSize, canvasSize);

    ctx.fillStyle = '#09090b';
    for (let r = 0; r < size; r++) {
      for (let c = 0; c < size; c++) {
        if (matrix[r][c] === 1) {
          ctx.fillRect(offset + c * moduleSize, offset + r * moduleSize, moduleSize, moduleSize);
        }
      }
    }
  }

  // ── Auto-init ──────────────────────────────────────────────────────────────

  function initQRCanvases() {
    document.querySelectorAll('canvas[data-qr]').forEach(canvas => {
      const url = canvas.dataset.qr;
      if (url) renderQR(canvas, url);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initQRCanvases);
  } else {
    initQRCanvases();
  }
})();
