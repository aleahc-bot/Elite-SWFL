/* contact — page behaviour */

const hdr=document.getElementById('hdr');
if(hdr){const onS=()=>hdr.classList.toggle('scrolled',window.scrollY>60);onS();window.addEventListener('scroll',onS,{passive:true});}
const burger=document.getElementById('burger'),menu=document.getElementById('menu');
if(burger&&menu){burger.addEventListener('click',()=>menu.classList.toggle('open'));menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>menu.classList.remove('open')));}
const io=new IntersectionObserver((es)=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}}),{threshold:.12});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
const reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const bgv=document.querySelector('.hero .bgvid');
if(bgv){
  if(reduce){bgv.removeAttribute('autoplay');try{bgv.pause();}catch(e){}}
  else{
    bgv.muted=true; bgv.setAttribute('muted','');
    const tp=()=>{const pr=bgv.play();if(pr&&pr.catch)pr.catch(()=>{});};
    tp(); bgv.addEventListener('canplay',tp,{once:true}); bgv.addEventListener('loadeddata',tp,{once:true});
    ['touchstart','click','scroll'].forEach(ev=>document.addEventListener(ev,tp,{once:true,passive:true}));
  }
}
const cio=new IntersectionObserver((es)=>es.forEach(e=>{
  if(!e.isIntersecting) return;
  const el=e.target, target=+el.dataset.count, suf=el.dataset.suffix||'';
  cio.unobserve(el);
  if(reduce){el.textContent=target+suf;return;}
  let t0=null,dur=1400;
  const step=(t)=>{if(!t0)t0=t;const p=Math.min((t-t0)/dur,1);const val=Math.floor((1-Math.pow(1-p,3))*target);el.textContent=val+suf;if(p<1)requestAnimationFrame(step);else el.textContent=target+suf;};
  requestAnimationFrame(step);
}),{threshold:.6});
document.querySelectorAll('[data-count]').forEach(el=>cio.observe(el));
const vc=document.getElementById('videoCard');
if(vc){const v=document.getElementById('story-video'),pb=document.getElementById('playBtn');
 pb.addEventListener('click',()=>{vc.classList.add('playing');v.setAttribute('controls','');v.play();});
 v.addEventListener('ended',()=>vc.classList.remove('playing'));}


(function(){
  var f=document.getElementById('scheduleForm'),
      done=document.getElementById('formSent'),
      err=document.getElementById('formErr'),
      btn=f&&f.querySelector('button[type=submit]');
  if(!f||!done)return;
  function showThanks(){f.hidden=true;done.hidden=false;
    done.setAttribute('role','status');
    try{done.scrollIntoView({block:'center',behavior:'smooth'});}catch(e){done.scrollIntoView();}}
  // plain (no-JS) post comes back as ?sent=1
  var q=new URLSearchParams(location.search);
  if(q.get('sent')==='1'){showThanks();}
  else if(q.get('sent')==='0'&&err){err.textContent=q.get('why')||
    'We could not send that just now — please call 239.561.1314.';err.hidden=false;}
  f.addEventListener('submit',function(ev){
    if(!f.reportValidity())return;                 // let the browser show field errors
    if(!window.fetch||!window.FormData)return;      // very old browser: plain post
    ev.preventDefault();
    if(err)err.hidden=true;
    var label=btn?btn.innerHTML:'';
    if(btn){btn.disabled=true;btn.innerHTML='Sending\u2026';}
    fetch(f.action,{method:'POST',body:new FormData(f),
        headers:{'Accept':'application/json','X-Requested-With':'fetch'}})
      .then(function(r){return r.json().catch(function(){return {ok:r.ok};});})
      .then(function(d){
        if(d&&d.ok){showThanks();return;}
        throw new Error((d&&d.message)||'send failed');
      })
      .catch(function(e){
        if(btn){btn.disabled=false;btn.innerHTML=label;}
        if(err){err.textContent=e.message&&e.message!=='send failed'?e.message:
          'We could not send that just now — please call 239.561.1314 or email '
          +'jennifer@elite1314.com.';err.hidden=false;}
      });
  });
})();

/* footer copyright year — never goes stale */
(function(){
  var y=document.getElementById('yr');
  if(y) y.textContent=new Date().getFullYear();
})();

