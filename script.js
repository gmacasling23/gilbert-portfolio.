const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const signin=$('#signin'),desktop=$('#desktop'),start=$('#start-menu'),searchPanel=$('#search-panel');
$('#enter').addEventListener('click',()=>{$('#enter').classList.add('hidden');$('#signin-message').classList.add('hidden');$('#loading').classList.remove('hidden');setTimeout(()=>{signin.classList.add('hidden');desktop.classList.remove('hidden');updateClock()},1700)});
let z=20;const titles={welcome:'This PC',profile:'Profile',projects:'Projects',contact:'Contact',viewer:'ProjectChrome',personalize:'Personalization',admin:'Admin Login'};
const appIcons={welcome:'icon-this-pc.svg',profile:'icon-profile.svg',projects:'icon-folder.png',contact:'icon-message.svg',viewer:'icon-chrome.svg',personalize:'icon-this-pc.svg',admin:'icon-profile.svg'};
function openWindow(id){if(id==='personalize'){const p=$('#win-personalize .os-personalize');if(p)p.scrollTop=0;}if(id==='viewer' && !pcTabs.length){pcNewTab();}if(id==='admin'){const frame=$('#admin-frame');if(frame&&!frame.getAttribute('src'))frame.src='admin/login.php?reauth=1';}let w=$('#win-'+id);if(!w)return;w.classList.remove('hidden');w.style.display='';w.style.zIndex=++z;start.classList.add('hidden');searchPanel.classList.add('hidden');renderTasks()}
// The four permanent taskbar shortcuts are already pinned. Only browser project
// windows get a separate running-app button.
const pinnedApps=new Set(['welcome','profile','projects','contact']);
function renderTasks(){
 const task=$('#task-apps');task.replaceChildren();
 Object.keys(titles).forEach(id=>{
  if(pinnedApps.has(id)||id==='admin')return;
  const w=$('#win-'+id);if(!w||w.classList.contains('hidden'))return;
  const b=document.createElement('button');b.className='task-icon active';
  const img=document.createElement('img');img.className='task-svg-icon';img.src='assets/'+appIcons[id];img.alt='';b.append(img);
  b.title=titles[id];b.setAttribute('aria-label',titles[id]);
  b.onclick=()=>{if(w.style.display==='none'){w.style.display='';w.style.zIndex=++z}else if(Number(w.style.zIndex)===z){w.style.display='none'}else{w.style.zIndex=++z}renderTasks()};task.append(b);
 });
 // Pinned buttons show a subtle running indicator, but never create duplicates.
 document.querySelectorAll('.taskbar [data-open]').forEach(b=>{
  const w=$('#win-'+b.dataset.open);
  b.classList.toggle('running',!!w&&!w.classList.contains('hidden'));
 });
}
$$('[data-open]').forEach(b=>{b.addEventListener('click',()=>openWindow(b.dataset.open));if(b.classList.contains('desktop-icon'))b.addEventListener('dblclick',()=>openWindow(b.dataset.open))});
$$('[data-close]').forEach(b=>b.onclick=()=>{const w=$('#win-'+b.dataset.close);w.classList.add('hidden');w.style.display='';if(b.dataset.close==='admin'){$('#admin-frame').removeAttribute('src');}if(b.dataset.close==='viewer'){pcTabs=[];pcActive=-1;browserFrame.removeAttribute('src');renderPcTabs()}renderTasks()});
$$('[data-min]').forEach(b=>b.onclick=()=>{$('#win-'+b.dataset.min).style.display='none';renderTasks()});
$$('[data-max]').forEach(b=>b.onclick=()=>{$('#win-'+b.dataset.max).classList.toggle('maximized')});
$$('.app-window').forEach(w=>{w.addEventListener('pointerdown',()=>{w.style.zIndex=++z});const bar=w.querySelector('.window-titlebar');let dragging=false,sx,sy,l,t;bar.addEventListener('pointerdown',e=>{if(e.target.closest('button')||w.classList.contains('maximized'))return;dragging=true;sx=e.clientX;sy=e.clientY;l=w.offsetLeft;t=w.offsetTop;bar.setPointerCapture(e.pointerId);w.style.zIndex=++z});bar.addEventListener('pointermove',e=>{if(!dragging)return;w.style.left=Math.max(0,Math.min(innerWidth-90,l+e.clientX-sx))+'px';w.style.top=Math.max(0,Math.min(innerHeight-90,t+e.clientY-sy))+'px'});bar.addEventListener('pointerup',()=>dragging=false);bar.addEventListener('pointercancel',()=>dragging=false)});
$('#start-btn').onclick=()=>{start.classList.toggle('hidden');searchPanel.classList.add('hidden');if(!start.classList.contains('hidden'))$('#start-search-input').focus()};$('#power').onclick=()=>{document.getElementById('compose-panel')?.classList.add('hidden');document.getElementById('mail-confirm')?.classList.add('hidden');desktop.classList.add('hidden');signin.classList.remove('hidden');$('#loading').classList.add('hidden');$('#enter').classList.remove('hidden');$('#signin-message').classList.remove('hidden');start.classList.add('hidden');$('#admin-frame').removeAttribute('src');$$('.app-window').forEach(w=>{w.style.display='';w.classList.toggle('hidden',w.id!=='win-welcome')});renderTasks()};$('#show-desktop').onclick=()=>{$$('.app-window').forEach(w=>w.style.display='none');renderTasks()};
$('#search').addEventListener('input',e=>{searchPanel.classList.toggle('hidden',!e.target.value.trim());start.classList.add('hidden')});$('#search').addEventListener('keydown',e=>{if(e.key==='Enter'){const v=e.target.value.trim().toLowerCase();if(v==='admin.login'){e.preventDefault();openWindow('admin');e.target.value='';searchPanel.classList.add('hidden');return;}const key=v.includes('skills')||v.includes('about')? 'profile' : v.includes('this pc')||v.includes('computer')?'welcome':Object.keys(titles).find(k=>v.includes(k));if(key)openWindow(key);e.target.value='';searchPanel.classList.add('hidden')}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){start.classList.add('hidden');searchPanel.classList.add('hidden')}});
function updateClock(){const now=new Date();$('#clock').innerHTML=now.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'})+'<br>'+now.toLocaleDateString([],{month:'numeric',day:'numeric',year:'numeric'})}updateClock();setInterval(updateClock,30000);renderTasks();

// Dynamic portfolio: read-only updates from the admin-managed database data.
// Refreshes open windows without resetting the desktop or interrupting navigation.
let currentPortfolio=null;
const projectFilter=$('#project-filter');
const safeExternal=u=>{try{const x=new URL(u);return ['http:','https:'].includes(x.protocol)?x.href:''}catch{return ''}};
function linkNode(label,url){const a=document.createElement('a');a.textContent=label+' →';a.href=url;a.target='_blank';a.rel='noopener noreferrer';return a}
// ProjectChrome: local project home, suggestions and multiple independent tab histories.
const browserFrame=$('#viewer-frame');
let pcTabs=[],pcActive=-1,pcNextId=1,pcLoading=false;
const pcCurrent=()=>pcTabs[pcActive];
const pcProjects=()=>((currentPortfolio&&currentPortfolio.projects)||[]).filter(p=>/^projects\/[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+\.(php|html)$/.test(String(p.url||''))||safeExternal(p.url));
const pcDisplay=url=>{if(!url)return 'projects';try{const u=new URL(url,location.href);if(u.origin===location.origin){const root=new URL('./',location.href).pathname;return decodeURI(u.pathname.startsWith(root)?u.pathname.slice(root.length):u.pathname.replace(/^\//,''))+(u.hash||'')}return u.hostname+u.pathname}catch{return url}};
function renderPcTabs(){const tabs=$('#pc-tabs');tabs.replaceChildren();pcTabs.forEach((t,i)=>{const b=document.createElement('button');b.type='button';b.className='pc-tab'+(i===pcActive?' selected':'');b.setAttribute('role','tab');b.setAttribute('aria-selected',String(i===pcActive));const name=document.createElement('span');name.textContent=t.title||'New tab';const x=document.createElement('span');x.textContent='×';x.className='pc-tab-close';x.title='Close tab';x.onclick=e=>{e.stopPropagation();pcCloseTab(i)};b.append(name,x);b.onclick=()=>pcSwitch(i);tabs.append(b)});$('#viewer-title').textContent='ProjectChrome';}
function pcNewTab(url='',title='New tab'){pcTabs.push({id:pcNextId++,title,history:url?[new URL(url,location.href).href]:[],index:url?0:-1});pcSwitch(pcTabs.length-1)}
function pcCloseTab(i){pcTabs.splice(i,1);if(!pcTabs.length){pcActive=-1;pcNewTab();return}if(pcActive>=i)pcActive=Math.max(0,pcActive-1);pcSwitch(pcActive)}
function pcSwitch(i){pcActive=i;renderPcTabs();pcShowCurrent()}
function pcShowCurrent(){ $('#pc-suggestions').classList.add('hidden');$('#pc-address-suggestions').classList.add('hidden');const t=pcCurrent();if(!t)return;const url=t.history[t.index]||'';$('#pc-home').classList.toggle('hidden',!!url);browserFrame.classList.toggle('hidden',!url);$('#viewer-tab').classList.toggle('hidden',!url);$('#viewer-path').value=pcDisplay(url);$('#viewer-back').disabled=t.index<=0;$('#viewer-forward').disabled=t.index>=t.history.length-1;$('#viewer-home').disabled=!url;if(url){$('#viewer-tab').href=url;if(browserFrame.src!==url){pcLoading=true;browserFrame.src=url}else{pcLoading=false}}else{browserFrame.removeAttribute('src');$('#pc-search').value='';pcRenderHome('')} }
function pcNavigate(url,title){const t=pcCurrent();if(!t)return;const full=new URL(url,location.href).href;t.history=t.history.slice(0,t.index+1);t.history.push(full);t.index++;if(title)t.title=title;renderPcTabs();pcShowCurrent()}
function openLocalProject(path,title){pcNewTab();pcNavigate(path,title);openWindow('viewer')}
function pcSelectProject(p){const url=String(p.url||'');if(/^projects\/[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+\.(php|html)$/.test(url)){pcNavigate(url,p.title)}else if(safeExternal(url)){window.open(url,'_blank','noopener,noreferrer')}}
function pcMatches(q){const term=q.trim().toLowerCase();return pcProjects().filter(p=>!term||String(p.title||'').toLowerCase().startsWith(term)||String(p.title||'').toLowerCase().includes(term)||String(p.description||'').toLowerCase().includes(term)).slice(0,12)}
function pcRenderSuggestions(target,q){target.replaceChildren();if(!q.trim()){target.classList.add('hidden');return}const matches=pcMatches(q);target.classList.toggle('hidden',!matches.length);for(const p of matches.slice(0,7)){const b=document.createElement('button');b.type='button';b.textContent=p.title+'  ·  '+pcDisplay(p.url);b.onclick=()=>{target.classList.add('hidden');pcSelectProject(p)};target.append(b)}}
let pcCarouselIndex=0,pcCarouselQuery='';
let osContextProject=null;
function osShowProjectMenu(e,p){e.preventDefault();osContextProject=p;const menu=$('#os-project-menu');menu.classList.remove('hidden');menu.style.left=Math.min(e.clientX,innerWidth-190)+'px';menu.style.top=Math.min(e.clientY,innerHeight-105)+'px'}
function osProjectProperties(p){if(!p)return;$('#os-project-menu').classList.add('hidden');const body=$('#os-props-body');body.replaceChildren();const rows=[['Project',p.title],['Technologies',p.stack||'Not specified'],['Description',p.description||'Not provided'],['Project type',String(p.url||'').startsWith('projects/')?'Hosted project':'External project'],['Year',p.year||'Not specified']];for(const [key,value] of rows){const row=document.createElement('p');const strong=document.createElement('strong');strong.textContent=key+': ';row.append(strong,document.createTextNode(String(value||'')));body.append(row)}if(safeExternal(p.url)){const link=document.createElement('a');link.href=p.url;link.target='_blank';link.rel='noopener noreferrer';link.textContent='Open project link';body.append(link)}$('#os-properties-backdrop').classList.remove('hidden')}
$('#os-project-properties').onclick=()=>osProjectProperties(osContextProject);
$('#os-project-open').onclick=()=>{if(osContextProject)pcSelectProject(osContextProject);$('#os-project-menu').classList.add('hidden')};
$('#os-props-close').onclick=()=>$('#os-properties-backdrop').classList.add('hidden');
$('#os-properties-backdrop').addEventListener('click',e=>{if(e.target.id==='os-properties-backdrop')e.currentTarget.classList.add('hidden')});
document.addEventListener('click',e=>{if(!e.target.closest('#os-project-menu'))$('#os-project-menu').classList.add('hidden')});
function pcRenderHome(q=''){
 const grid=$('#pc-project-grid');grid.replaceChildren();const projects=pcProjects().filter(p=>!q.trim()||[p.title,p.description,p.stack].some(v=>String(v||'').toLowerCase().includes(q.trim().toLowerCase())));
 if(q!==pcCarouselQuery){pcCarouselIndex=0;pcCarouselQuery=q}
 const count=projects.length;const showArrows=count>5;
 $('#pc-prev').classList.toggle('hidden',!showArrows);$('#pc-next').classList.toggle('hidden',!showArrows);
 $('#pc-carousel-status').textContent=showArrows?`${Math.min(count,5)} of ${count} projects · use arrows to browse`:'';
 if(!count){const msg=document.createElement('p');msg.className='pc-empty';msg.textContent='No matching projects yet.';grid.append(msg);return}
 const visible=showArrows?Array.from({length:5},(_,i)=>projects[(pcCarouselIndex+i)%count]):projects;
 for(const p of visible){
  const b=document.createElement('button');b.type='button';b.className='pc-project-card';b.title='Open '+p.title+' · right-click for properties';
  const img=document.createElement('img');img.alt='';img.loading='lazy';const image=String(p.image||'').trim();
  const local=/^projects\/[a-zA-Z0-9_-]+\/(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp|gif)$/i.test(image);
  img.src=(local||safeExternal(image))?image:'assets/icon-folder.png';
  img.onerror=()=>{img.onerror=null;img.src='assets/icon-folder.png';img.classList.remove('has-preview')};
  if(local||safeExternal(image))img.classList.add('has-preview');
  const name=document.createElement('strong');name.textContent=p.title;
  b.append(img,name);b.onclick=()=>pcSelectProject(p);b.oncontextmenu=e=>osShowProjectMenu(e,p);grid.append(b)
 }
}
function pcMoveCarousel(dir){const n=pcProjects().filter(p=>!pcCarouselQuery.trim()||[p.title,p.description,p.stack].some(v=>String(v||'').toLowerCase().includes(pcCarouselQuery.trim().toLowerCase()))).length;if(n<=5)return;pcCarouselIndex=(pcCarouselIndex+dir+n)%n;pcRenderHome(pcCarouselQuery)}
$('#pc-prev').onclick=()=>pcMoveCarousel(-1);$('#pc-next').onclick=()=>pcMoveCarousel(1);
$('#pc-new-tab').onclick=()=>pcNewTab();
$('#viewer-back').onclick=()=>{const t=pcCurrent();if(t&&t.index>0){t.index--;pcShowCurrent()}};
$('#viewer-forward').onclick=()=>{const t=pcCurrent();if(t&&t.index<t.history.length-1){t.index++;pcShowCurrent()}};
$('#viewer-home').onclick=()=>pcNewTab();
$('#pc-search').addEventListener('input',e=>{pcRenderHome(e.target.value);pcRenderSuggestions($('#pc-suggestions'),e.target.value)});
$('#pc-search').addEventListener('blur',()=>setTimeout(()=>$('#pc-suggestions').classList.add('hidden'),180));
$('#viewer-path').addEventListener('blur',()=>setTimeout(()=>$('#pc-address-suggestions').classList.add('hidden'),180));
$('#pc-search').addEventListener('keydown',e=>{if(e.key==='Enter'){const p=pcMatches(e.target.value)[0];if(p)pcSelectProject(p)}});
$('#viewer-path').addEventListener('input',e=>pcRenderSuggestions($('#pc-address-suggestions'),e.target.value));
$('#viewer-path').addEventListener('keydown',e=>{if(e.key==='Enter'){const q=e.target.value.trim(),p=pcMatches(q)[0];if(p){pcSelectProject(p)}else if(/^projects\/[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+\.(php|html)$/.test(q)){pcNavigate(q)}else if(/^https?:\/\//i.test(q)){window.open(q,'_blank','noopener,noreferrer')}$('#pc-address-suggestions').classList.add('hidden');e.target.blur()}});
browserFrame.addEventListener('load',()=>{
 // Keep in-frame navigation in place. Reassigning iframe.src here reloads
 // the embedded app and can trap sites on their splash/loading screen.
 if(pcLoading){pcLoading=false;return;}
 const t=pcCurrent();if(!t)return;
 try{
  const url=browserFrame.contentWindow.location.href;
  if(url==='about:blank'||url===t.history[t.index]||new URL(url).origin!==location.origin)return;
  t.history=t.history.slice(0,t.index+1);
  t.history.push(url);
  t.index++;
  $('#viewer-path').value=pcDisplay(url);
  $('#viewer-tab').href=url;
  $('#viewer-back').disabled=t.index<=0;
  $('#viewer-forward').disabled=false;
  renderPcTabs();
 }catch(e){/* Cross-origin frames cannot be inspected. */}
});
function renderProjects(){if(!currentPortfolio)return;const list=$('#live-projects');if(!list)return;const q=(projectFilter?.value||'').trim().toLocaleLowerCase();const all=currentPortfolio.projects||[];const visible=all.filter(p=>[p.title,p.description,p.stack].some(v=>String(v||'').toLocaleLowerCase().includes(q)));list.replaceChildren();$('#project-count').textContent=visible.length+' / '+all.length+' projects';if(!visible.length){const empty=document.createElement('p');empty.textContent=q?'No matching projects.':'No projects published yet.';list.append(empty);return}for(const p of visible){const item=document.createElement('div');item.className='project-item';const folder=document.createElement('div');folder.className='project-folder';const img=document.createElement('img');img.src='assets/icon-folder.png';img.className='project-svg-icon';img.alt='';folder.append(img);const content=document.createElement('div');const title=document.createElement('h3');title.textContent=p.title;const desc=document.createElement('p');desc.textContent=p.description;const stack=document.createElement('small');stack.textContent=p.stack;content.append(title,desc,stack);const image=(/^projects\/[a-zA-Z0-9_-]+\/(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp|gif)$/i.test(String(p.image||''))?p.image:safeExternal(p.image));if(image){const wrap=document.createElement('div');const preview=document.createElement('img');preview.className='project-preview';preview.loading='lazy';preview.src=image;preview.alt=p.title+' preview';wrap.append(preview);content.append(wrap)}const url=String(p.url||'');const local=/^projects\/[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+\.(?:php|html)$/.test(url);if(local){const wrap=document.createElement('p');const open=document.createElement('button');open.type='button';open.className='open-local-project';open.textContent='Open inside portfolio';open.addEventListener('click',()=>openLocalProject(url,p.title));wrap.className='project-actions';const external=linkNode('Open in new tab',url);external.classList.add('project-external');wrap.append(open,external);content.append(wrap)}else if(safeExternal(url)){const wrap=document.createElement('p');wrap.append(linkNode('Open project in new tab',safeExternal(url)));content.append(wrap)}if(p.has_archive){const wrap=document.createElement('p');const dl=document.createElement('a');dl.href='download-project.php?id='+encodeURIComponent(p.id);dl.textContent='Download previously imported ZIP';wrap.append(dl);content.append(wrap)}item.append(folder,content);list.append(item)}}
projectFilter?.addEventListener('input',renderProjects);
function applyPortfolio(data){if(!data||!Array.isArray(data.projects)||!Array.isArray(data.skills))return;currentPortfolio=data;document.querySelectorAll('[data-live]').forEach(el=>{const key=el.dataset.live;el.textContent=(key==='about'&&!data.about)?'More about me coming soon.':(data[key]||'')});document.title=(data.name||'Portfolio')+' Portfolio';const skills=$('#live-skills');if(skills){skills.replaceChildren();for(const value of data.skills){const tag=document.createElement('span');tag.textContent=value;skills.append(tag)}}const email=document.querySelector('[data-live-email]');if(email){email.textContent=data.email;email.href='mailto:'+data.email}const socials=$('#live-socials');if(socials){socials.replaceChildren();for(const [key,label] of [['github','GitHub'],['facebook','Facebook']]){const url=safeExternal(data[key]);if(url)socials.append(linkNode(label,url))}}document.querySelectorAll('[data-location-row]').forEach(el=>{el.hidden=!data.location;const value=el.querySelector('[data-live="location"]');if(value)value.textContent=data.location});renderProjects();if(pcActive>=0&&!pcCurrent().history.length)pcRenderHome($('#pc-search').value)}
let previousPayload='';async function refreshPortfolio(){if(document.hidden)return;try{const res=await fetch('api.php',{cache:'no-store'});if(!res.ok)return;const raw=await res.text();if(raw===previousPayload)return;const data=JSON.parse(raw);applyPortfolio(data);previousPayload=raw}catch(err){/* Keep the last valid view if temporarily offline. */}}
refreshPortfolio();setInterval(refreshPortfolio,15000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshPortfolio()});

// v7 contact composer: sends from the portfolio window without navigating away.
(() => {
 const toggle=document.getElementById('compose-toggle');
 const panel=document.getElementById('compose-panel');
 const cancel=document.getElementById('compose-cancel');
 const form=document.getElementById('contact-form');
 const status=document.getElementById('contact-status');
 const send=document.getElementById('send-message');
 const confirm=document.getElementById('mail-confirm');const ok=document.getElementById('mail-confirm-ok');
 if(!toggle||!panel||!form)return;
 // Move dialogs outside the clipped application window so they appear as separate desktop windows.
 desktop.append(panel,confirm);
 toggle.addEventListener('click',()=>{panel.classList.remove('hidden');toggle.classList.add('hidden');document.getElementById('sender-name').focus();});
 const close=()=>{panel.classList.add('hidden');toggle.classList.remove('hidden');status.textContent='';};
 cancel.addEventListener('click',close);document.getElementById('compose-x').addEventListener('click',close);
 ok.addEventListener('click',()=>{confirm.classList.add('hidden');close();toggle.focus()});
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(!form.reportValidity())return;
  send.disabled=true;status.textContent='Sending your message…';
  try{
   const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'Accept':'application/json'},credentials:'same-origin'});
   const result=await response.json();
   status.textContent=result.message||'Unable to send the message.';
   status.className=result.ok?'contact-success':'contact-error';
   if(result.ok){form.reset();panel.classList.add('hidden');confirm.classList.remove('hidden');ok.focus();}
  }catch(e){status.textContent='Unable to contact the server. Please try again.';status.className='contact-error';}
  finally{send.disabled=false;}
 });
})();

// Start menu: filter pinned apps only while typing, preserving the admin.login command.
const startSearchInput=$('#start-search-input');
startSearchInput.addEventListener('input',()=>{const term=startSearchInput.value.trim().toLowerCase();$$('.start-pinned button').forEach(btn=>{btn.classList.toggle('hidden',!!term&&!btn.textContent.toLowerCase().includes(term))});$('.start-recent').classList.toggle('hidden',!!term&&!$('.start-recent').textContent.toLowerCase().includes(term))});
startSearchInput.addEventListener('keydown',e=>{if(e.key!=='Enter')return;const term=startSearchInput.value.trim().toLowerCase();if(term==='admin.login'){openWindow('admin')}else{const match=$$('.start-pinned button').find(btn=>!btn.classList.contains('hidden'));if(match)openWindow(match.dataset.open)}startSearchInput.value='';startSearchInput.dispatchEvent(new Event('input'))});
document.addEventListener('pointerdown',e=>{if(!start.classList.contains('hidden')&&!start.contains(e.target)&&!e.target.closest('#start-btn'))start.classList.add('hidden')});

// v10.2 desktop personalization and global search.
(function(){
 const accent=$('#os-accent'),gallery=$('#os-wallpaper-gallery'),preview=$('#os-desktop-preview');
 const defaults={theme:'default',accent:'#a55fbb',wallpaper:'original'};
 const tiles=[...gallery.querySelectorAll('[data-wallpaper]')];
 const safeColor=c=>/^#[0-9a-f]{6}$/i.test(String(c))?c:defaults.accent;
 const validTheme=t=>'default';
 const validWallpaper=w=>tiles.some(t=>t.dataset.wallpaper===w)?w:defaults.wallpaper;
 function load(){try{return {...defaults,...JSON.parse(localStorage.getItem('os-theme-v102')||'{}')}}catch{return {...defaults}}}
 function normalize(v){return {theme:validTheme(v.theme),accent:safeColor(v.accent),wallpaper:validWallpaper(v.wallpaper)}}
 let draft=normalize(load());
 function backgroundFor(w){return w==='plain'?'linear-gradient(135deg,#e8c8ee,#a4b6e7)':`url("${w==='original'?'assets/cherry-blossom-cat-wallpaper.jpg?v=10-2-r9':w.replace(/"/g,'')+'?v=10-2-r9'}")`}
 function render(){const v=draft; preview.style.backgroundImage=backgroundFor(v.wallpaper);preview.style.backgroundSize=(v.wallpaper==='original'||v.wallpaper==='plain')?'cover':'cover';preview.style.backgroundPosition='center';preview.style.backgroundRepeat='no-repeat';preview.style.backgroundColor='#2d2041';preview.style.filter='none';preview.style.setProperty('--os-preview-accent',v.accent);accent.value=v.accent;$('#os-preview-name').textContent=tiles.find(t=>t.dataset.wallpaper===v.wallpaper)?.dataset.name||'Original cherry blossom';tiles.forEach(t=>{const on=t.dataset.wallpaper===v.wallpaper;t.classList.toggle('selected',on);t.setAttribute('aria-pressed',String(on))});$$('#os-accent-presets [data-accent]').forEach(b=>{const on=b.dataset.accent.toLowerCase()===v.accent.toLowerCase();b.classList.toggle('selected',on);b.setAttribute('aria-pressed',String(on))});$('#os-save-status').textContent='Changes save automatically.'}
 function apply(v){v=normalize(v);document.body.dataset.osTheme=v.theme;document.body.dataset.osWallpaper=v.wallpaper;document.documentElement.style.setProperty('--os-custom-accent',v.accent);const desk=$('#desktop');desk.style.setProperty('--os-wallpaper-image',backgroundFor(v.wallpaper));desk.style.backgroundImage='none';desk.style.backgroundColor=v.theme==='dark'?'#261d36':'#eab3cd';desk.style.backgroundPosition='center center';desk.style.backgroundSize='cover';desk.style.backgroundRepeat='no-repeat';desk.style.setProperty('--os-wallpaper-fit',v.wallpaper==='original'||v.wallpaper==='plain'?'cover':'cover')}
 function persist(v){try{localStorage.setItem('os-theme-v102',JSON.stringify(v))}catch{}}
 function saveNow(){apply(draft);persist(draft);render();}
 gallery.addEventListener('click',e=>{const tile=e.target.closest('[data-wallpaper]');if(!tile)return;draft.wallpaper=tile.dataset.wallpaper;saveNow()});
 
 $$('#os-accent-presets [data-accent]').forEach(b=>b.addEventListener('click',()=>{draft.accent=b.dataset.accent;saveNow()}));
 accent.addEventListener('input',()=>{draft.accent=accent.value;saveNow()});
 let toastTimer;
 function showSettingsToast(message){
   const toast=$('#os-settings-toast');toast.textContent=message;toast.classList.remove('hidden');
   clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.classList.add('hidden'),2300);
 }
 $('#os-reset').addEventListener('click',()=>{
   draft={...defaults};saveNow();showSettingsToast('✓ Original appearance restored');
 });
 apply(draft);render();
 const input=$('#search'),panel=$('#search-panel'),results=$('#os-search-results');
 const apps=[['This PC','welcome'],['Profile','profile'],['My Projects','projects'],['ProjectChrome','viewer'],['Contact','contact'],['Personalization','personalize']];
 function search(){const q=input.value.trim().toLowerCase();panel.classList.toggle('hidden',!q);results.replaceChildren();if(!q)return;
  const matches=apps.filter(([name])=>name.toLowerCase().includes(q)).map(([name,id])=>({name,go:()=>openWindow(id)}));
  if('admin.login'.includes(q))matches.push({name:'Admin Login',go:()=>openWindow('admin')});
  for(const skill of currentPortfolio?.skills||[]){if(String(skill).toLowerCase().includes(q))matches.push({name:'Skill: '+skill,go:()=>openWindow('profile')})}
  for(const p of currentPortfolio?.projects||[]){if([p.title,p.stack,p.description].some(v=>String(v||'').toLowerCase().includes(q)))matches.push({name:'Project: '+p.title,go:()=>{openWindow('viewer');pcSelectProject(p)}})}
  for(const m of matches.slice(0,12)){const b=document.createElement('button');b.type='button';b.textContent=m.name;b.onclick=()=>{m.go();input.value='';panel.classList.add('hidden')};results.append(b)}
  if(!matches.length)results.textContent='No matching apps, projects, or skills.';
 }
 input.addEventListener('input',search);
 input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!panel.classList.contains('hidden')){const b=results.querySelector('button');if(b){e.preventDefault();e.stopImmediatePropagation();b.click()}}},true);
})();
