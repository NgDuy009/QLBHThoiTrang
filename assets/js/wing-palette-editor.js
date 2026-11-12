(()=>{
 const wrapper=document.getElementById('productPalette');
 if(!wrapper)return;
 const rows=wrapper.querySelector('#paletteRows'),template=wrapper.querySelector('#paletteRowTemplate');
 let counter=Math.max(0,...Array.from(rows.querySelectorAll('[data-palette-index]'),el=>Number(el.dataset.paletteIndex)+1));
 const colorPreset={'trắng':'#F8F7F2','đen':'#181918','be':'#C9B698','kem':'#E9DCC3','nâu':'#76533D','đỏ':'#A53F45','xanh navy':'#1D324B','xanh lá':'#55755C','hồng':'#DCA9BB','xám':'#919593','vàng':'#D9BC54'};
 const previewUrl=(input)=>{const value=input.value.trim();return /^https?:\/\//i.test(value)?value:/^uploads\/products\/[a-z0-9._-]+$/i.test(value)?`${document.baseURI.split('/index.php')[0]}/${value}`:'';};
 function attach(card){
  const hex=card.querySelector('.palette-hex-input'),picker=card.querySelector('.palette-color-input');
  const swatch=card.querySelector('.palette-swatch-live'),name=card.querySelector('.palette-name-input');
  const photo=card.querySelector('.palette-preview-image'),url=card.querySelector('.palette-url-input'),file=card.querySelector('.palette-file-input');
  const setHex=(value)=>{if(!/^#[A-Fa-f0-9]{6}$/.test(value))return;const safe=value.toUpperCase();hex.value=safe;picker.value=safe;swatch.style.setProperty('--palette-color',safe)};
  picker?.addEventListener('input',()=>setHex(picker.value));
  hex?.addEventListener('input',()=>setHex(hex.value));
  name?.addEventListener('input',()=>{card.querySelector('.palette-color-top b').textContent=name.value||'Màu mới';const suggested=colorPreset[name.value.trim().toLowerCase()];if(suggested&&hex.value.toUpperCase()==='#F8F7F2')setHex(suggested)});
  url?.addEventListener('change',()=>{const candidate=previewUrl(url);if(candidate)photo.src=candidate;});
  file?.addEventListener('change',()=>{const f=file.files?.[0];if(!f)return;if(f.size>5*1024*1024){alert('Ảnh tối đa 5MB.');file.value='';return;}const local=URL.createObjectURL(f);photo.addEventListener('load',()=>URL.revokeObjectURL(local),{once:true});photo.src=local;});
  card.querySelector('.palette-remove')?.addEventListener('click',()=>card.remove());
 }
 rows.querySelectorAll('.palette-color-card').forEach(attach);
 const addButton=wrapper.querySelector('#paletteAddColor');
 addButton?.addEventListener('click',()=>{
   if(rows.querySelectorAll('.palette-color-card').length>=18){alert('Mỗi lần lưu tối đa 18 màu.');return;}
   const content=template.innerHTML.replaceAll('__INDEX__',String(counter++));
   const holder=document.createElement('div');holder.innerHTML=content.trim();const card=holder.firstElementChild;
   rows.append(card);attach(card);card.querySelector('.palette-name-input')?.focus();
 });
 // The user can add a color when needed; an empty card is not required to save.
})();
