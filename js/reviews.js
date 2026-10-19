/* reviews — page behaviour */

(function(){var s=document.querySelector(".tslider");if(!s)return;var track=s.querySelector(".tgrid"),prev=s.querySelector(".tnav.prev"),next=s.querySelector(".tnav.next");function step(){var c=track.querySelector(".tc");return c?c.getBoundingClientRect().width+22:360;}prev&&prev.addEventListener("click",function(){track.scrollBy({left:-step(),behavior:"smooth"});});next&&next.addEventListener("click",function(){track.scrollBy({left:step(),behavior:"smooth"});});function upd(){var max=track.scrollWidth-track.clientWidth-2;if(prev)prev.style.opacity=track.scrollLeft<=2?".4":"1";if(next)next.style.opacity=track.scrollLeft>=max?".4":"1";}track.addEventListener("scroll",upd);window.addEventListener("resize",upd);setTimeout(upd,60);})();

