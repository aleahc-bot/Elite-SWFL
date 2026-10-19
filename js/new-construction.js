/* new-construction — page behaviour */

(function(){
  var root=document.querySelector('[data-bf]');if(!root)return;
  var tabs=[].slice.call(root.querySelectorAll('.bf-tab')),
      panels=[].slice.call(root.querySelectorAll('.bf-panel')),
      hits=[].slice.call(root.querySelectorAll('.bf-hit')),
      input=root.querySelector('.bf-search input'),
      meta=root.querySelector('.bf-meta'),
      empty=root.querySelector('.bf-empty'),
      base=root.querySelector('.bf-meta').textContent;
  function show(i){
    tabs.forEach(function(t){t.setAttribute('aria-pressed',t.getAttribute('data-i')===i?'true':'false');});
    panels.forEach(function(p){p.classList[p.getAttribute('data-i')===i?'add':'remove']('is-on');});
  }
  tabs.forEach(function(t){
    t.addEventListener('click',function(){
      if(input.value){input.value='';search();}
      show(t.getAttribute('data-i'));
    });
  });
  function search(){
    var q=(input.value||'').trim().toLowerCase().replace(/&/g,' ');
    if(!q){root.classList.remove('is-search');meta.textContent=base;empty.classList.remove('is-on');return;}
    root.classList.add('is-search');
    var n=0;
    hits.forEach(function(h){
      var ok=h.getAttribute('data-s').indexOf(q)>-1;
      h.classList[ok?'remove':'add']('is-out');if(ok)n++;
    });
    meta.textContent=(n===1?'1 match':n+' matches')+' for "'+input.value.trim()+'"';
    empty.classList[n?'remove':'add']('is-on');
  }
  input.addEventListener('input',search);
  input.addEventListener('keydown',function(e){
    if(e.key==='Escape'||e.keyCode===27){input.value='';search();}
  });
})();

