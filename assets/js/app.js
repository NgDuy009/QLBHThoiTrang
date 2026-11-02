document.addEventListener('DOMContentLoaded',()=>{
 const toggle=document.getElementById('menuToggle'), nav=document.getElementById('mainNav');
 if(toggle&&nav){
  toggle.setAttribute('aria-controls','mainNav');
  toggle.setAttribute('aria-expanded','false');
  const close=()=>{nav.classList.remove('open');toggle.setAttribute('aria-expanded','false');};
  toggle.addEventListener('click',()=>{
   const open=nav.classList.toggle('open');
   toggle.setAttribute('aria-expanded',String(open));
  });
  document.addEventListener('keydown',event=>{if(event.key==='Escape')close();});
  document.addEventListener('click',event=>{
   if(nav.classList.contains('open')&&!nav.contains(event.target)&&!toggle.contains(event.target))close();
  });
 }
 const list=document.getElementById('orderItems'),add=document.getElementById('addOrderItem'),total=document.getElementById('orderTotal');
 function calc(){if(!list||!total)return;let sum=0;list.querySelectorAll('.order-item').forEach(row=>{const sel=row.querySelector('select'),qty=row.querySelector('input'),opt=sel?.selectedOptions[0],price=Number(opt?.dataset.price||0),stock=Number(opt?.dataset.stock||0),count=Number(qty?.value||0);if(qty&&stock>0)qty.max=String(stock);sum+=price*count;});total.textContent=new Intl.NumberFormat('vi-VN').format(sum)+' ₫';}
 function bind(row){row.querySelectorAll('select,input').forEach(el=>el.addEventListener('change',calc));row.querySelector('.remove-item')?.addEventListener('click',()=>{if(list.querySelectorAll('.order-item').length>1)row.remove();calc();});}
 if(list){list.querySelectorAll('.order-item').forEach(bind);list.addEventListener('input',calc);}
 if(add&&list)add.addEventListener('click',()=>{const clone=list.querySelector('.order-item').cloneNode(true);clone.querySelector('select').selectedIndex=0;clone.querySelector('input').value=1;list.appendChild(clone);bind(clone);calc();});calc();
});
document.addEventListener('DOMContentLoaded',()=>{
 const items=document.querySelectorAll('.reveal');
 if(!items.length)return;
 if(!('IntersectionObserver' in window)){items.forEach(el=>el.classList.add('is-visible'));return;}
 const io=new IntersectionObserver(entries=>{
   entries.forEach(entry=>{
     if(entry.isIntersecting){entry.target.classList.add('is-visible');io.unobserve(entry.target);}
   });
 },{threshold:.12,rootMargin:'0px 0px -35px 0px'});
 items.forEach((el,index)=>{el.style.transitionDelay=Math.min(index%4,3)*75+'ms';io.observe(el);});
});
document.addEventListener('DOMContentLoaded',()=>{
 const header=document.querySelector('.store-header');
 const toggle=document.getElementById('storeMenuToggle');
 const backdrop=document.getElementById('storeMenuBackdrop');
 if(!header||!toggle||!backdrop)return;
 const setOpen=open=>{
  header.classList.toggle('store-menu-open',open);
  document.body.classList.toggle('store-menu-active',open);
  toggle.setAttribute('aria-expanded',String(open));
  toggle.setAttribute('aria-label',open?'Đóng menu':'Mở menu');
  backdrop.hidden=!open;
  if(!open)header.querySelector('.collection-nav')?.classList.remove('menu-open');
 };
 toggle.addEventListener('click',()=>setOpen(toggle.getAttribute('aria-expanded')!=='true'));
 backdrop.addEventListener('click',()=>{setOpen(false);toggle.focus();});
 document.addEventListener('keydown',event=>{
  if(event.key==='Escape'&&toggle.getAttribute('aria-expanded')==='true'){setOpen(false);toggle.focus();}
 });
 header.querySelectorAll('#storeNav a,#storeTools a').forEach(link=>{
  link.addEventListener('click',()=>{
   if(link.classList.contains('collection-nav-trigger'))return;
   if(window.matchMedia('(max-width: 1100px)').matches)setOpen(false);
  });
 });
});
