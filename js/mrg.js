/* mrg — page behaviour */

(function(){
  var grid=document.getElementById('mrgGrid'); if(!grid) return;
  var cards=Array.prototype.slice.call(grid.querySelectorAll('.mrg-card'));
  var search=document.getElementById('mrgSearch');
  var filters=document.getElementById('mrgFilters');
  var empty=document.getElementById('mrgEmpty');
  var cat='all';
  function apply(){
    var q=(search.value||'').trim().toLowerCase(); var shown=0;
    cards.forEach(function(c){
      var okCat = cat==='all' || c.getAttribute('data-cat')===cat;
      var okQ = !q || c.getAttribute('data-search').indexOf(q)>-1;
      var show = okCat && okQ;
      c.style.display = show ? '' : 'none';
      if(show) shown++;
    });
    empty.style.display = shown ? 'none' : 'block';
  }
  search.addEventListener('input', apply);
  filters.addEventListener('click', function(e){
    var b=e.target.closest('.mrg-filter'); if(!b) return;
    cat=b.getAttribute('data-cat');
    filters.querySelectorAll('.mrg-filter').forEach(function(x){ x.classList.toggle('active', x===b); });
    apply();
  });
})();

