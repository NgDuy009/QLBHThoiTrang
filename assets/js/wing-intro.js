(()=> {
 const run=()=>{
  const el=document.getElementById('wingIntro');
  if(!el)return;
  if(window.matchMedia('(prefers-reduced-motion: reduce)').matches){el.remove();return;}
  let seen=false;
  try{seen=sessionStorage.getItem('wing_intro_seen_v2')==='1';}catch(e){}
  if(seen){el.remove();return;}
  try{sessionStorage.setItem('wing_intro_seen_v2','1');}catch(e){}
  document.body.classList.add('intro-active');
  window.setTimeout(()=>{el.classList.add('is-leaving');},2450);
  window.setTimeout(()=>{el.remove();document.body.classList.remove('intro-active');},3050);
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run,{once:true});else run();
})();