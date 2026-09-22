(() => {
  'use strict';

  const STORAGE_KEY = 'store_products_v1';
  const MASCOT_STORAGE_KEY = 'store_mascot_v1';
  const CAMERA_SCAN_STORAGE_KEY = 'store_camera_scan_v1';

  const MASCOT_TYPES = ['bear', 'cat', 'rabbit'];
  const MASCOT_NAMES = { bear: '곰돌이', cat: '고양이', rabbit: '토끼' };

  /** @type {Record<string, {name: string, price: number, image: string|null}>} */
  let products = loadProducts();

  let mascot = localStorage.getItem(MASCOT_STORAGE_KEY) || null;
  let cameraScanEnabled = localStorage.getItem(CAMERA_SCAN_STORAGE_KEY) === 'true';

  /** @type {Array<{barcode: string, qty: number}>} 결제 전까지만 유지되는 임시 장바구니 */
  let cart = [];

  let registerMode = null; // 'new' | 'edit'
  let scannerCaptureEnabled = true;

  // ---------- DOM ----------
  const scannerInput = document.getElementById('scanner-input');
  const manualToggleBtn = document.getElementById('btn-manual-toggle');
  const manualForm = document.getElementById('manual-form');
  const manualInput = document.getElementById('manual-input');

  const btnCameraScan = document.getElementById('btn-camera-scan');
  const cameraScanOverlay = document.getElementById('camera-scan-overlay');
  const cameraVideo = document.getElementById('camera-video');
  const cameraErrorEl = document.getElementById('camera-error');
  const btnCameraCancel = document.getElementById('btn-camera-cancel');
  const toggleCameraScan = document.getElementById('toggle-camera-scan');

  const cartListEl = document.getElementById('cart-list');
  const cartEmptyEl = document.getElementById('cart-empty');
  const cartTotalEl = document.getElementById('cart-total-amount');
  const btnClearCart = document.getElementById('btn-clear-cart');
  const btnCheckout = document.getElementById('btn-checkout');

  const btnManage = document.getElementById('btn-manage');
  const btnBackFromManage = document.getElementById('btn-back-from-manage');
  const scanView = document.getElementById('scan-view');
  const manageView = document.getElementById('manage-view');
  const productListEl = document.getElementById('product-list');
  const productEmptyEl = document.getElementById('product-empty');

  const registerModal = document.getElementById('register-modal');
  const registerTitle = document.getElementById('register-title');
  const registerPreview = document.getElementById('register-preview');
  const registerPhotoInput = document.getElementById('register-photo-input');
  const registerBarcode = document.getElementById('register-barcode');
  const registerName = document.getElementById('register-name');
  const registerPrice = document.getElementById('register-price');
  const btnRegisterCancel = document.getElementById('btn-register-cancel');
  const btnRegisterDelete = document.getElementById('btn-register-delete');
  const btnRegisterSave = document.getElementById('btn-register-save');

  const checkoutOverlay = document.getElementById('checkout-overlay');
  const checkoutTotalEl = document.getElementById('checkout-total-amount');
  const btnCheckoutClose = document.getElementById('btn-checkout-close');

  const btnMascot = document.getElementById('btn-mascot');
  const mascotBigEl = document.getElementById('mascot-big');
  const mascotCheckoutEl = document.getElementById('mascot-checkout');
  const mascotPickerOverlay = document.getElementById('mascot-picker-overlay');
  const mascotCardListEl = document.getElementById('mascot-card-list');

  let pendingPhotoDataUrl = null;

  // ---------- 저장소 ----------
  function loadProducts() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
    } catch {
      return {};
    }
  }

  function saveProducts() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(products));
  }

  // ---------- 스캐너 입력 캡처 ----------
  function isTextInputFocused() {
    const el = document.activeElement;
    if (!el || el === scannerInput) return false;
    if (el.tagName === 'TEXTAREA' || el.isContentEditable) return true;
    if (el.tagName === 'INPUT') {
      const type = (el.getAttribute('type') || 'text').toLowerCase();
      return ['text', 'number', 'search', 'tel', 'email', 'url', 'password'].includes(type);
    }
    return false;
  }

  function refocusScanner() {
    if (scannerCaptureEnabled && document.activeElement !== scannerInput && !isTextInputFocused()) {
      scannerInput.focus({ preventScroll: true });
    }
  }

  scannerInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const code = scannerInput.value.trim();
      scannerInput.value = '';
      if (code) handleScan(code);
    }
  });

  document.addEventListener('click', refocusScanner);
  document.addEventListener('touchend', refocusScanner);
  window.addEventListener('focus', refocusScanner);
  setInterval(refocusScanner, 1000);
  refocusScanner();

  // ---------- 수동 입력 ----------
  manualToggleBtn.addEventListener('click', () => {
    manualForm.classList.toggle('hidden');
    if (!manualForm.classList.contains('hidden')) {
      manualInput.focus();
    }
  });

  manualForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const code = manualInput.value.trim();
    manualInput.value = '';
    if (code) handleScan(code);
  });

  // ---------- 카메라로 바코드 스캔 (설정에서 켰을 때만 노출) ----------
  let cameraReader = null;
  let cameraControls = null;

  function applyCameraScanVisibility() {
    btnCameraScan.classList.toggle('hidden', !cameraScanEnabled);
    toggleCameraScan.checked = cameraScanEnabled;
  }

  toggleCameraScan.addEventListener('change', () => {
    cameraScanEnabled = toggleCameraScan.checked;
    localStorage.setItem(CAMERA_SCAN_STORAGE_KEY, String(cameraScanEnabled));
    applyCameraScanVisibility();
  });

  btnCameraScan.addEventListener('click', openCameraScan);
  btnCameraCancel.addEventListener('click', closeCameraScan);

  function showCameraError(msg) {
    cameraErrorEl.textContent = msg;
    cameraErrorEl.classList.remove('hidden');
  }

  async function openCameraScan() {
    scannerCaptureEnabled = false;
    cameraErrorEl.classList.add('hidden');
    cameraScanOverlay.classList.remove('hidden');

    if (typeof ZXingBrowser === 'undefined') {
      showCameraError('카메라 스캔 기능을 불러오지 못했어요. 인터넷 연결을 확인해주세요.');
      return;
    }

    try {
      if (!cameraReader) {
        cameraReader = new ZXingBrowser.BrowserMultiFormatReader();
      }
      cameraControls = await cameraReader.decodeFromConstraints(
        { video: { facingMode: { ideal: 'environment' } } },
        cameraVideo,
        (result) => {
          if (result) {
            const code = result.getText();
            closeCameraScan();
            handleScan(code);
          }
          // 바코드를 못 찾은 프레임에서는 계속 error가 들어오는 게 정상이라 무시한다
        }
      );
    } catch (err) {
      if (err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError')) {
        showCameraError('카메라 권한이 필요해요. 브라우저 설정에서 카메라 접근을 허용해주세요.');
      } else if (err && err.name === 'NotFoundError') {
        showCameraError('사용할 수 있는 카메라를 찾지 못했어요.');
      } else {
        showCameraError('카메라를 사용할 수 없어요. 잠시 후 다시 시도해주세요.');
      }
    }
  }

  function closeCameraScan() {
    if (cameraControls) {
      cameraControls.stop();
      cameraControls = null;
    }
    cameraScanOverlay.classList.add('hidden');
    scannerCaptureEnabled = true;
    refocusScanner();
  }

  applyCameraScanVisibility();

  // ---------- 스캔 처리 ----------
  async function handleScan(barcode) {
    if (products[barcode]) {
      addToCart(barcode);
      playBeep();
      return;
    }
    playBeep();
    openRegisterModal('new', barcode);

    if (navigator.onLine) {
      const info = await fetchProductInfo(barcode);
      if (info && registerMode === 'new' && registerBarcode.value === barcode) {
        if (info.name && !registerName.value) registerName.value = info.name;
        if (info.image) {
          registerPreview.src = info.image;
          pendingPhotoDataUrl = info.image;
        }
      }
    }
  }

  async function fetchProductInfo(barcode) {
    try {
      const res = await fetch(
        `https://world.openfoodfacts.org/api/v2/product/${encodeURIComponent(barcode)}.json?fields=product_name,image_front_small_url`
      );
      if (!res.ok) return null;
      const data = await res.json();
      if (data.status === 1 && data.product) {
        return {
          name: data.product.product_name || '',
          image: data.product.image_front_small_url || null,
        };
      }
    } catch {
      // 오프라인이거나 조회 실패 -> 수동 입력으로 진행
    }
    return null;
  }

  // ---------- 장바구니 ----------
  function addToCart(barcode) {
    const existing = cart.find((item) => item.barcode === barcode);
    if (existing) {
      existing.qty += 1;
    } else {
      cart.push({ barcode, qty: 1 });
    }
    renderCart();
    revealCart(barcode);
  }

  // 화면 크기/레이아웃과 상관없이 방금 담은 물건이 항상 눈에 보이도록 스크롤해서 보여준다
  function revealCart(barcode) {
    const row = cartListEl.querySelector(`.cart-item[data-barcode="${CSS.escape(barcode)}"]`);
    const target = row || document.querySelector('.cart-panel');
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    if (row) {
      row.classList.add('cart-item-flash');
      setTimeout(() => row.classList.remove('cart-item-flash'), 900);
    }
  }

  function changeQty(barcode, delta) {
    const item = cart.find((i) => i.barcode === barcode);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) {
      cart = cart.filter((i) => i.barcode !== barcode);
    }
    renderCart();
  }

  function cartTotal() {
    return cart.reduce((sum, item) => {
      const p = products[item.barcode];
      return sum + (p ? p.price * item.qty : 0);
    }, 0);
  }

  function renderCart() {
    cartListEl.querySelectorAll('.cart-item').forEach((el) => el.remove());
    cartEmptyEl.classList.toggle('hidden', cart.length > 0);

    for (const item of cart) {
      const p = products[item.barcode];
      if (!p) continue;
      const li = document.createElement('li');
      li.className = 'cart-item';
      li.dataset.barcode = item.barcode;
      li.innerHTML = `
        <div class="cart-item-photo">${p.image ? `<img src="${p.image}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">` : '📦'}</div>
        <div class="cart-item-info">
          <div class="cart-item-name">${escapeHtml(p.name)}</div>
          <div class="cart-item-price">${p.price.toLocaleString('ko-KR')}원</div>
        </div>
        <div class="qty-control">
          <button class="qty-btn" data-action="dec" data-barcode="${item.barcode}">－</button>
          <span class="qty-num">${item.qty}</span>
          <button class="qty-btn" data-action="inc" data-barcode="${item.barcode}">＋</button>
        </div>
      `;
      cartListEl.appendChild(li);
    }

    const total = cartTotal();
    cartTotalEl.textContent = total.toLocaleString('ko-KR');
    btnCheckout.disabled = cart.length === 0;
  }

  cartListEl.addEventListener('click', (e) => {
    const btn = e.target.closest('.qty-btn');
    if (!btn) return;
    const barcode = btn.dataset.barcode;
    changeQty(barcode, btn.dataset.action === 'inc' ? 1 : -1);
  });

  btnClearCart.addEventListener('click', () => {
    cart = [];
    renderCart();
  });

  // ---------- 결제 ----------
  btnCheckout.addEventListener('click', () => {
    const total = cartTotal();
    if (total <= 0) return;
    checkoutTotalEl.textContent = total.toLocaleString('ko-KR');
    checkoutOverlay.classList.remove('hidden');
    playCheckoutSound();
  });

  btnCheckoutClose.addEventListener('click', () => {
    checkoutOverlay.classList.add('hidden');
    cart = [];
    renderCart();
    refocusScanner();
  });

  // ---------- 상품 등록/수정 모달 ----------
  function openRegisterModal(mode, barcode) {
    registerMode = mode;
    scannerCaptureEnabled = false;
    pendingPhotoDataUrl = null;

    registerBarcode.value = barcode;
    registerPreview.src = '';
    registerPreview.style.background = '#eee2cf';

    if (mode === 'edit' && products[barcode]) {
      registerTitle.textContent = '상품 수정';
      registerName.value = products[barcode].name;
      registerPrice.value = String(products[barcode].price);
      if (products[barcode].image) {
        registerPreview.src = products[barcode].image;
        pendingPhotoDataUrl = products[barcode].image;
      }
      btnRegisterDelete.classList.remove('hidden');
    } else {
      registerTitle.textContent = '새 상품 등록';
      registerName.value = '';
      registerPrice.value = '';
      btnRegisterDelete.classList.add('hidden');
    }

    registerModal.classList.remove('hidden');
    setTimeout(() => registerName.focus(), 50);
  }

  function closeRegisterModal() {
    registerModal.classList.add('hidden');
    registerMode = null;
    scannerCaptureEnabled = true;
    refocusScanner();
  }

  btnRegisterCancel.addEventListener('click', closeRegisterModal);

  registerPhotoInput.addEventListener('change', () => {
    const file = registerPhotoInput.files[0];
    if (!file) return;
    resizeImageToDataUrl(file, 320).then((dataUrl) => {
      pendingPhotoDataUrl = dataUrl;
      registerPreview.src = dataUrl;
    });
  });

  btnRegisterSave.addEventListener('click', () => {
    const barcode = registerBarcode.value.trim();
    const name = registerName.value.trim();
    const price = parseInt(registerPrice.value, 10);

    if (!name) {
      registerName.focus();
      return;
    }
    if (!Number.isFinite(price) || price < 0) {
      registerPrice.focus();
      return;
    }

    const wasNew = registerMode === 'new';
    products[barcode] = { name, price, image: pendingPhotoDataUrl || null };
    saveProducts();

    closeRegisterModal();
    if (wasNew) {
      addToCart(barcode);
    } else {
      renderCart();
      renderProductList();
    }
  });

  btnRegisterDelete.addEventListener('click', () => {
    const barcode = registerBarcode.value.trim();
    if (!confirm('이 상품을 삭제할까요?')) return;
    delete products[barcode];
    saveProducts();
    cart = cart.filter((i) => i.barcode !== barcode);
    closeRegisterModal();
    renderCart();
    renderProductList();
  });

  function resizeImageToDataUrl(file, maxSize) {
    return new Promise((resolve) => {
      const img = new Image();
      const reader = new FileReader();
      reader.onload = () => {
        img.onload = () => {
          const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
          const canvas = document.createElement('canvas');
          canvas.width = img.width * scale;
          canvas.height = img.height * scale;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          resolve(canvas.toDataURL('image/jpeg', 0.8));
        };
        img.src = reader.result;
      };
      reader.readAsDataURL(file);
    });
  }

  // ---------- 상품 관리 화면 ----------
  btnManage.addEventListener('click', () => {
    scanView.classList.add('hidden');
    manageView.classList.remove('hidden');
    scannerCaptureEnabled = false;
    renderProductList();
  });

  btnBackFromManage.addEventListener('click', () => {
    manageView.classList.add('hidden');
    scanView.classList.remove('hidden');
    scannerCaptureEnabled = true;
    refocusScanner();
  });

  function renderProductList() {
    productListEl.innerHTML = '';
    const entries = Object.entries(products);
    productEmptyEl.classList.toggle('hidden', entries.length > 0);

    for (const [barcode, p] of entries) {
      const li = document.createElement('li');
      li.className = 'product-item';
      li.innerHTML = `
        <div class="product-item-photo">${p.image ? `<img src="${p.image}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">` : '📦'}</div>
        <div class="product-item-info">
          <div class="product-item-name">${escapeHtml(p.name)}</div>
          <div class="product-item-price">${p.price.toLocaleString('ko-KR')}원 · ${barcode}</div>
        </div>
      `;
      li.addEventListener('click', () => openRegisterModal('edit', barcode));
      productListEl.appendChild(li);
    }
  }

  // ---------- 마스코트 SVG (직접 그린 벡터 캐릭터) ----------
  function eyesMarkup(mood, dark) {
    if (mood === 'happy') {
      return `
        <path d="M74,84 Q80,76 86,84" stroke="${dark}" stroke-width="3" fill="none" stroke-linecap="round"/>
        <path d="M114,84 Q120,76 126,84" stroke="${dark}" stroke-width="3" fill="none" stroke-linecap="round"/>
      `;
    }
    return `
      <circle cx="80" cy="84" r="6" fill="${dark}"/>
      <circle cx="120" cy="84" r="6" fill="${dark}"/>
      <circle cx="82" cy="81" r="1.6" fill="#fff"/>
      <circle cx="122" cy="81" r="1.6" fill="#fff"/>
    `;
  }

  function mouthMarkup(mood, dark) {
    if (mood === 'happy') {
      return `<path d="M86,108 Q100,126 114,108 Q100,117 86,108 Z" fill="${dark}"/>`;
    }
    return `<path d="M100,104 Q100,112 92,114 M100,104 Q100,112 108,114" stroke="${dark}" stroke-width="2.5" fill="none" stroke-linecap="round"/>`;
  }

  function pawsMarkup(mood, main) {
    if (mood === 'happy') {
      return `<circle cx="38" cy="56" r="15" fill="${main}"/><circle cx="162" cy="56" r="15" fill="${main}"/>`;
    }
    return `<circle cx="166" cy="136" r="15" fill="${main}"/>`;
  }

  function bearSvg(mood) {
    const main = '#D9A873', light = '#F6D9BB', dark = '#6B4A32';
    return `<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
      <path d="M28,200 Q28,138 100,138 Q172,138 172,200 Z" fill="${main}"/>
      <circle cx="52" cy="46" r="22" fill="${main}"/>
      <circle cx="52" cy="48" r="11" fill="${light}"/>
      <circle cx="148" cy="46" r="22" fill="${main}"/>
      <circle cx="148" cy="48" r="11" fill="${light}"/>
      <circle cx="100" cy="92" r="58" fill="${main}"/>
      <ellipse cx="100" cy="110" rx="32" ry="24" fill="${light}"/>
      <ellipse cx="66" cy="104" rx="10" ry="6" fill="#FFB6B9" opacity="0.7"/>
      <ellipse cx="134" cy="104" rx="10" ry="6" fill="#FFB6B9" opacity="0.7"/>
      <g class="mascot-eyes">${eyesMarkup(mood, dark)}</g>
      <ellipse cx="100" cy="100" rx="8" ry="6" fill="${dark}"/>
      ${mouthMarkup(mood, dark)}
      ${pawsMarkup(mood, main)}
    </svg>`;
  }

  function catSvg(mood) {
    const main = '#F6C89F', light = '#FFF1E0', dark = '#7A5A40', nose = '#FF9EB5';
    return `<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
      <path d="M28,200 Q28,138 100,138 Q172,138 172,200 Z" fill="${main}"/>
      <polygon points="34,58 58,10 78,52" fill="${main}"/>
      <polygon points="40,54 56,24 68,50" fill="${light}"/>
      <polygon points="166,58 142,10 122,52" fill="${main}"/>
      <polygon points="160,54 144,24 132,50" fill="${light}"/>
      <circle cx="100" cy="92" r="58" fill="${main}"/>
      <ellipse cx="100" cy="112" rx="30" ry="22" fill="${light}"/>
      <ellipse cx="66" cy="106" rx="9" ry="5" fill="${nose}" opacity="0.6"/>
      <ellipse cx="134" cy="106" rx="9" ry="5" fill="${nose}" opacity="0.6"/>
      <g class="mascot-eyes">${eyesMarkup(mood, dark)}</g>
      <path d="M100,98 L94,106 L106,106 Z" fill="${nose}"/>
      ${mouthMarkup(mood, dark)}
      <g stroke="${dark}" stroke-width="1.5" stroke-linecap="round" opacity="0.5">
        <line x1="40" y1="102" x2="18" y2="98"/>
        <line x1="40" y1="110" x2="18" y2="112"/>
        <line x1="160" y1="102" x2="182" y2="98"/>
        <line x1="160" y1="110" x2="182" y2="112"/>
      </g>
      ${pawsMarkup(mood, main)}
    </svg>`;
  }

  function rabbitSvg(mood) {
    const main = '#FFF8EF', light = '#FFD9E6', dark = '#8A7B6C', nose = '#FF9EB5';
    return `<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
      <path d="M28,200 Q28,138 100,138 Q172,138 172,200 Z" fill="${main}" stroke="#F0E4D4" stroke-width="1"/>
      <ellipse cx="70" cy="26" rx="16" ry="42" fill="${main}" stroke="#F0E4D4" stroke-width="1"/>
      <ellipse cx="70" cy="30" rx="8" ry="30" fill="${light}"/>
      <ellipse cx="130" cy="26" rx="16" ry="42" fill="${main}" stroke="#F0E4D4" stroke-width="1"/>
      <ellipse cx="130" cy="30" rx="8" ry="30" fill="${light}"/>
      <circle cx="100" cy="96" r="56" fill="${main}" stroke="#F0E4D4" stroke-width="1"/>
      <ellipse cx="100" cy="114" rx="28" ry="20" fill="#FFFDF8"/>
      <ellipse cx="68" cy="108" rx="9" ry="5" fill="${light}" opacity="0.8"/>
      <ellipse cx="132" cy="108" rx="9" ry="5" fill="${light}" opacity="0.8"/>
      <g class="mascot-eyes">${eyesMarkup(mood, dark)}</g>
      <path d="M100,102 L94,109 L106,109 Z" fill="${nose}"/>
      ${mouthMarkup(mood, dark)}
      ${pawsMarkup(mood, main)}
    </svg>`;
  }

  function mascotSvg(type, mood) {
    if (type === 'cat') return catSvg(mood);
    if (type === 'rabbit') return rabbitSvg(mood);
    return bearSvg(mood);
  }

  function renderMascotEverywhere() {
    const current = mascot || 'bear';
    btnMascot.innerHTML = mascotSvg(current, 'idle');
    mascotBigEl.innerHTML = mascotSvg(current, 'idle');
    mascotCheckoutEl.innerHTML = mascotSvg(current, 'happy');
  }

  function renderMascotPicker() {
    mascotCardListEl.innerHTML = '';
    for (const type of MASCOT_TYPES) {
      const card = document.createElement('div');
      card.className = 'mascot-card' + (mascot === type ? ' selected' : '');
      card.innerHTML = `${mascotSvg(type, 'idle')}<div class="mascot-card-name">${MASCOT_NAMES[type]}</div>`;
      card.addEventListener('click', () => {
        mascot = type;
        localStorage.setItem(MASCOT_STORAGE_KEY, type);
        renderMascotEverywhere();
        closeMascotPicker();
      });
      mascotCardListEl.appendChild(card);
    }
  }

  function openMascotPicker() {
    scannerCaptureEnabled = false;
    renderMascotPicker();
    mascotPickerOverlay.classList.remove('hidden');
  }

  function closeMascotPicker() {
    mascotPickerOverlay.classList.add('hidden');
    scannerCaptureEnabled = true;
    refocusScanner();
  }

  btnMascot.addEventListener('click', openMascotPicker);

  renderMascotEverywhere();
  if (!mascot) {
    openMascotPicker();
  }

  // ---------- 사운드 (에셋 없이 Web Audio로 생성) ----------
  let audioCtx = null;
  function getAudioCtx() {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    return audioCtx;
  }

  function playTone(freq, duration, delay = 0, gainValue = 0.15) {
    const ctx = getAudioCtx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = freq;
    gain.gain.value = gainValue;
    osc.connect(gain).connect(ctx.destination);
    const start = ctx.currentTime + delay;
    osc.start(start);
    osc.stop(start + duration);
  }

  function playBeep() {
    playTone(1200, 0.09);
  }

  function playCheckoutSound() {
    playTone(880, 0.12, 0);
    playTone(1320, 0.16, 0.12);
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // ---------- 초기화 ----------
  renderCart();
})();
