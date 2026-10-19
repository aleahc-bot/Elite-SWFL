/* index — page behaviour */

const hdr=document.getElementById('hdr');
if(hdr){const onS=()=>hdr.classList.toggle('scrolled',window.scrollY>60);onS();window.addEventListener('scroll',onS,{passive:true});}
const burger=document.getElementById('burger'),menu=document.getElementById('menu');
if(burger&&menu){burger.addEventListener('click',()=>menu.classList.toggle('open'));menu.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>menu.classList.remove('open')));}
const io=new IntersectionObserver((es)=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}}),{threshold:.12});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
const reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const bgv=document.querySelector('.hero .bgvid');
if(bgv){
  bgv.muted=true; bgv.setAttribute('muted','');
  bgv.setAttribute('autoplay','');
  const tp=()=>{const pr=bgv.play();if(pr&&pr.catch)pr.catch(()=>{});};
  if(bgv.readyState>=2){tp();}
  bgv.addEventListener('loadeddata',tp);
  bgv.addEventListener('canplay',tp);
  bgv.addEventListener('canplaythrough',tp);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)tp();});
  ['touchstart','click','scroll','keydown','mousemove'].forEach(ev=>document.addEventListener(ev,tp,{once:true,passive:true}));
  try{bgv.load();}catch(e){}
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
const f=document.getElementById('scheduleForm');
if(f){f.addEventListener('submit',function(ev){ev.preventDefault();
  const name=this.name.value.trim(),phone=this.phone.value.trim(),email=this.email.value.trim();
  const svcs=[...this.querySelectorAll('input[type=checkbox]:checked')].map(c=>c.value);
  const body=`Name: ${name}%0D%0APhone: ${phone}%0D%0AEmail: ${email}%0D%0AServices: ${svcs.join(', ')||'-'}`;
  window.location.href=`mailto:service@elite1314.com?subject=Service%20Request%20-%20${encodeURIComponent(name||'Website')}&body=${body}`;
  document.getElementById('fn').textContent='Opening your email app...';});}

/* footer copyright year — never goes stale */
(function(){
  var y=document.getElementById('yr');
  if(y) y.textContent=new Date().getFullYear();
})();

