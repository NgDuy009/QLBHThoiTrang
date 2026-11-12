document.querySelectorAll('[data-copy-reference]').forEach(button => {
  button.addEventListener('click', async () => {
    const source = document.getElementById(button.dataset.copyReference);
    if (!source) return;
    const code = source.textContent.trim();
    try {
      if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(code);
      else {
        const node=document.createElement('textarea');node.value=code;node.style.position='fixed';node.style.opacity='0';document.body.appendChild(node);node.select();
        if (!document.execCommand('copy')) throw new Error('Clipboard unavailable');node.remove();
      }
      button.textContent='ĐÃ SAO CHÉP ✓';
    } catch { button.textContent='CHỌN & SAO CHÉP MÃ PHÍA TRÊN'; }
  });
});

// Only the bank's VietQR is shown. Desktop can open a compact WinG code-entry popup.
document.querySelectorAll('.wing-mobile-popup-link').forEach(link => {
  link.addEventListener('click', event => {
    if (window.innerWidth < 680) return; // iPhone opens the responsive page normally.
    const popup=window.open(link.href,'wing-code-entry','popup=yes,width=460,height=710,resizable=yes,scrollbars=yes');
    if (popup) { event.preventDefault(); popup.focus(); }
  });
});

// Allow copying the LAN URL to send to iPhone. Clipboard fallback supports HTTP localhost.
document.querySelectorAll('[data-copy-url]').forEach(button => button.addEventListener('click', () => {
  const text=button.dataset.copyUrl;
  if (!text) return;
  const field=document.createElement('textarea');field.value=text;field.style.position='fixed';field.style.opacity='0';
  document.body.appendChild(field);field.select();
  let ok=false;try{ok=document.execCommand('copy');}catch(_){}field.remove();
  button.textContent=ok?'ĐÃ SAO CHÉP LINK ✓':'KHÔNG SAO CHÉP ĐƯỢC – THỬ LẠI';
}));

// A same-origin desktop popup can fill the main order form immediately.
window.addEventListener('message', event => {
  if(event.origin !== window.location.origin || event.data?.type !== 'wing-verification-code') return;
  const field=document.getElementById('wingConfirmationCodeInput');
  if (!field || !/^[A-Z0-9]{8}$/.test(event.data.code)) return;
  const expected=Number(document.querySelector('[data-order-id]')?.dataset.orderId);
  if(expected!==Number(event.data.orderId)) return;
  field.value=event.data.code;
  field.focus();
  field.scrollIntoView({behavior:'smooth',block:'center'});
});

// Automatically refresh the customer's order journey when phone confirmation
// or real admin reconciliation/shipping changes on the server.
const orderWatcher = document.getElementById('wingPaymentOrderWatcher');
if (orderWatcher) {
  const initial = [orderWatcher.dataset.claim, orderWatcher.dataset.pay, orderWatcher.dataset.status].join(':');
  window.setInterval(async () => {
    if (document.hidden) return;
    try {
      const url = `index.php?page=payment_status&id=${encodeURIComponent(orderWatcher.dataset.orderId)}`;
      const res = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
      if (!res.ok) return;
      const data = await res.json();
      const current = [data.claimed ? '1':'0', data.payment_status, data.status].join(':');
      if (current !== initial) window.location.reload();
    } catch (_) { /* Network may be temporarily unavailable. */ }
  }, 10000);
}

// Two QR payload types cannot be merged: VietQR is bank EMV data;
// a regular HTTPS URL QR launches the WinG code-entry form from iPhone Camera.
// Only one is visible at once, and all URL QR rendering occurs locally.
(() => {
  const root=document.getElementById('wingQrMode');
  if(!root) return;
  const url=root.getAttribute('data-mobile-url');
  if(!url) return;
  const container=document.getElementById('wingMobileLinkQr');
  if(!container) return;
  try {
    if(!window.WinGQRLocal) throw new Error('Local QR encoder is unavailable');
    container.innerHTML=window.WinGQRLocal.toSVG(url);
  } catch(err) {
    container.textContent='Không tạo được QR mở form. Hãy sử dụng nút MỞ FORM dưới đây hoặc kiểm tra địa chỉ LAN.';
  }
  const tabs=[...root.querySelectorAll('[data-wing-qr-mode]')];
  const panels=[...root.querySelectorAll('[data-wing-qr-panel]')];
  tabs.forEach(tab => tab.addEventListener('click', () => {
    const mode=tab.getAttribute('data-wing-qr-mode');
    tabs.forEach(item => {
      const active=item===tab;
      item.classList.toggle('is-selected',active);
      item.setAttribute('aria-pressed',active?'true':'false');
    });
    panels.forEach(panel => {panel.hidden=panel.getAttribute('data-wing-qr-panel')!==mode;});
  }));
})();
