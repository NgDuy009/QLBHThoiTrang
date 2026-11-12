/* Shop listing swatch preview; product detail variant handling lives on its page. */
(()=>{
 document.querySelectorAll('.store-product').forEach(card=>{
  const image=card.querySelector('.shop-card-image');
  const colors=card.querySelectorAll('.shop-card-color');
  if(!image||!colors.length)return;
  let seq=0;
  colors.forEach(button=>button.addEventListener('click',()=>{
   if(button.disabled)return;
   const color=button.dataset.color||'';
   const target=button.dataset.image||'';
   colors.forEach(c=>{c.classList.toggle('is-active',c===button);c.setAttribute('aria-pressed',String(c===button));});
   card.querySelectorAll('.shop-card-link,.product-name').forEach(link=>{
    const url=new URL(link.href,location.href);url.searchParams.set('color',color);link.href=url.toString();
   });
   if(!target||target===image.currentSrc||target===image.src)return;
   const token=++seq;
   image.classList.add('wing-image-loading');
   const preload=new Image();
   preload.onload=()=>{if(token!==seq)return;image.src=target;image.classList.remove('wing-image-loading');};
   preload.onerror=()=>{if(token===seq)image.classList.remove('wing-image-loading');};
   preload.src=target;
  }));
 });
})();
