'use strict';
const menu=document.querySelector('.menu-toggle');
const sidebar=document.getElementById('app-sidebar');
function closeMenu(){document.body.classList.remove('menu-open');menu?.setAttribute('aria-expanded','false');menu?.setAttribute('aria-label','Open navigation');if(sidebar)sidebar.inert=matchMedia('(max-width:900px)').matches;}
menu?.addEventListener('click',()=>{const open=document.body.classList.toggle('menu-open');if(sidebar)sidebar.inert=!open&&matchMedia('(max-width:900px)').matches;menu.setAttribute('aria-expanded',String(open));menu.setAttribute('aria-label',open?'Close navigation':'Open navigation');});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu();});
document.addEventListener('click',e=>{if(sidebar&&!sidebar.contains(e.target)&&!menu?.contains(e.target))closeMenu();});
document.querySelector('.fullscreen-control')?.addEventListener('click',async()=>{try{if(document.fullscreenElement)await document.exitFullscreen();else await document.documentElement.requestFullscreen();}catch{}});
const search=document.getElementById('question-search'),filter=document.getElementById('category-filter');
function filterQuestions(){const term=(search?.value||'').trim().toLowerCase(),category=filter?.value||'';let visible=0;document.querySelectorAll('[data-question-row]').forEach(row=>{const show=row.textContent.toLowerCase().includes(term)&&(!category||row.dataset.category===category);row.hidden=!show;if(show)visible++;});const count=document.getElementById('result-count');if(count)count.textContent=visible+' questions';const empty=document.getElementById('search-empty');if(empty)empty.hidden=visible>0;}
search?.addEventListener('input',filterQuestions);filter?.addEventListener('change',filterQuestions);
document.getElementById('clear-filters')?.addEventListener('click',()=>{search.value='';filter.value='';filterQuestions();search.focus();});

matchMedia('(max-width:900px)').addEventListener('change',closeMenu);closeMenu();
