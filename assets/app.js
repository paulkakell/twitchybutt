(()=>{
  const SHEET_CSV = "https://docs.google.com/spreadsheets/d/e/2PACX-1vS70bLP-VN0QH8NHNheE1OfI3H18-jxEOr4RF4aqFnW4HNBhAHlomnDc6ySHz_gNC5PYuNaKL6Ja5hd/pub?gid=0&single=true&output=csv";

  function parseCSV(text){
    const rows=[]; let i=0, field='', row=[], inQ=false;
    while(i<text.length){
      const c=text[i];
      if(inQ){
        if(c=='"'){
          if(text[i+1]=='"'){ field+='"'; i++; }
          else inQ=false;
        } else field+=c;
      } else {
        if(c=='"') inQ=true;
        else if(c==','){ row.push(field); field=''; }
        else if(c=='\n'){ row.push(field); rows.push(row); row=[]; field=''; }
        else if(c=='\r'){}
        else field+=c;
      }
      i++;
    }
    row.push(field); rows.push(row);
    const [hdr,...data]=rows; if(!hdr) return [];
    return data.filter(r=>r.length && r.some(v=>v!==''))
      .map(r=>Object.fromEntries(hdr.map((h,idx)=>[h.trim().toLowerCase(), (r[idx]??'').trim()])));
  }

  const by = (k, dir='asc', map=x=>x) => (a,b)=>{
    const va = map(a[k]); const vb = map(b[k]);
    if(va==null && vb!=null) return 1; if(vb==null && va!=null) return -1; if(va==null && vb==null) return 0;
    if(va<vb) return dir==='asc'? -1: 1;
    if(va>vb) return dir==='asc'? 1: -1;
    return 0;
  };
  const toMoney = v => v? Number(String(v).replace(/[^0-9.\-]/g,'')) : NaN;
  const toDate  = v => v? new Date(v) : new Date(0);
  const safeURL = v => v && /^https?:\/\//i.test(v) ? v : '';

  function compileQuery(q){
    if(!q) return ()=>true;
    const tokens = q.match(/(?:\w+\:\w+)|(?:\S+)/g) || [];
    const parts=[]; let mode='AND';
    for(const t of tokens){
      const up=t.toUpperCase();
      if(up==='AND'){ mode='AND'; continue; }
      if(up==='OR'){ mode='OR'; continue; }
      if(up==='NOT'){ parts.push({op:'NOT', term:null}); continue; }
      const last=parts[parts.length-1];
      if(last && last.op==='NOT' && last.term===null){ last.term=t; }
      else parts.push({op:mode, term:t});
    }
    return (obj)=>{
      const hay = Object.values(obj).join(' ').toLowerCase();
      let acc = true;
      for(const p of parts){
        const has = hay.includes(String(p.term).toLowerCase());
        if(p.op==='NOT') acc = acc && !has;
        else if(p.op==='AND') acc = acc && has;
        else if(p.op==='OR') acc = acc || has;
      }
      return acc;
    };
  }

  const ICONS = {
    x: '<svg class="icon" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4l16 16M20 4L4 20"/></svg>',
    twitter: '<svg class="icon" viewBox="0 0 24 24" fill="currentColor"><path d="M22 5.8c-.7.3-1.5.6-2.3.7.8-.5 1.4-1.3 1.7-2.2-.8.5-1.7.9-2.7 1.1A4 4 0 0012 8c0 .3 0 .6.1.9-3.3-.2-6.2-1.8-8.2-4.2-.4.7-.6 1.3-.6 2.1 0 1.4.7 2.6 1.7 3.3-.6 0-1.3-.2-1.8-.5 0 2 1.5 3.7 3.4 4.1-.4.1-.8.2-1.2.2-.3 0-.6 0-.9-.1.6 1.8 2.3 3.1 4.3 3.1A8.1 8.1 0 012 18.6 11.4 11.4 0 008.2 20c7 0 10.8-5.8 10.8-10.8v-.5c.7-.5 1.3-1.2 1.8-1.9z"/></svg>',
    youtube: '<svg class="icon" viewBox="0 0 24 24" fill="currentColor"><path d="M10 15l5.2-3L10 9v6zm12-3c0-2.5-.2-4.2-.5-5.3-.3-1-1-1.8-2-2-1.7-.5-8.5-.5-8.5-.5s-6.8 0-8.5.5c-1 .3-1.7 1-2 2C.2 7.8 0 9.5 0 12s.2 4.2.5 5.3c.3 1 1 1.8 2 2 1.7.5 8.5.5 8.5.5s6.8 0 8.5-.5c1-.3 1.7-1 2-2 .3-1.1.5-2.8.5-5.3z"/></svg>',
    reddit: '<svg class="icon" viewBox="0 0 24 24" fill="currentColor"><path d="M14.5 15.5a.9.9 0 11-1.8 0 .9.9 0 011.8 0zm-4.2 0a.9.9 0 11-1.8 0 .9.9 0 011.8 0zM12 18.7c-1.2 0-2.3-.3-3.2-.8a.5.5 0 01.6-.8c.7.4 1.7.7 2.6.7.9 0 1.8-.2 2.6-.7a.5.5 0 11.6.8c-.9.5-2 .8-3.2.8z"/></svg>',
    link: '<svg class="icon" viewBox="0 0 24 24" fill="currentColor"><path d="M3.9 12a4.1 4.1 0 014.1-4.1h3v1.8h-3a2.3 2.3 0 000 4.6h3v1.8h-3A4.1 4.1 0 013.9 12zm12.1-4.1h-3v1.8h3a2.3 2.3 0 010 4.6h-3v1.8h3a4.1 4.1 0 000-8.2z"/></svg>'
  };

  document.addEventListener('DOMContentLoaded', async () => {
    const page = document.body.getAttribute('data-page');
    const yr = document.getElementById('yr'); if(yr) yr.textContent = new Date().getFullYear();

    if(page==='casinos') return renderCasinos();
    if(page==='surveys') return renderSurveys();
    if(page==='remote')  return renderRemote();
    if(page==='reddit')  return renderReddit();
  });

  async function loadSheet(){
    const res = await fetch(SHEET_CSV, {mode:'cors', cache:'no-store'});
    if(!res.ok) throw new Error('Failed to load data');
    const text = await res.text();
    return parseCSV(text);
  }

  function pickLink(row){
    const pref = safeURL(row.referrallink);
    const alt = safeURL(row.loginlink);
    return pref || alt || '#';
  }

function socialIconsFor(name, allRows){
  const socials = allRows.filter(r =>
    (r.type||'').toLowerCase()==='social' &&
    (r.displayname||'').toLowerCase()===String(name||'').toLowerCase()
  );
  if(!socials.length) return '';
  const cap = s => s ? s.charAt(0).toUpperCase() + s.slice(1).toLowerCase() : 'Link';
  return '<div class="socials">' + socials.map(s=>{
    const label = cap(String(s.subtype||'Link'));
    const url = (s.loginlink||'').match(/^https?:\/\//i) ? s.loginlink : '';
    return url ? `<a href="${url}" target="_blank" rel="nofollow noopener">${label}</a>` : '';
  }).join(' ') + '</div>';
}


  function setStatus(msg){ const el=document.getElementById('status'); if(el) el.textContent = msg||''; }

  // --- Casinos ---
  async function renderCasinos(){
    const grid = document.getElementById('grid');
    const q    = document.getElementById('q');
    const sort = document.getElementById('sort');
    setStatus('Loading…');
    try{
      const rows = await loadSheet();
      const all = rows.filter(r => (r.type||'').toLowerCase()==='sweeps' && (r.subtype||'').toLowerCase()==='casino');
      const searchFn = ()=>compileQuery(q.value);
      const draw = ()=>{
        const pred = searchFn();
        let list = all.filter(pred);
        const s = sort.value;
        if(s==='alpha_asc') list.sort(by('displayname','asc', v=>String(v||'').toLowerCase()));
        if(s==='alpha_desc') list.sort(by('displayname','desc', v=>String(v||'').toLowerCase()));
        if(s==='lastcheck_asc') list.sort(by('lastcheck','asc', toDate));
        if(s==='lastcheck_desc') list.sort(by('lastcheck','desc', toDate));
        if(s==='tier_asc') list.sort(by('tier','asc', n=>Number(n||99)));
        if(s==='tier_desc') list.sort(by('tier','desc', n=>Number(n||-99)));
        if(s==='cash_min') list.sort(by('cashredemption','asc', toMoney));
        grid.innerHTML = list.map(row=>cardCasino(row, rows)).join('');
        setStatus(`${list.length} result(s)`);
      };
      q.addEventListener('input', draw); sort.addEventListener('change', draw);
      draw();
    }catch(e){ setStatus('Failed to load listings'); }
  }

  function cardCasino(r, all){
    const fields = [];
    const push = (k, v)=>{ if(v!==undefined && v!==null && String(v).trim()!=='') fields.push(`<div class="row"><div class="k">${k}</div><div class="v">${v}</div></div>`); };
    const url = pickLink(r);
    const logo = safeURL(r.logo);
    const vip = String(r.vip||'').toLowerCase()==='true' ? '<span class="badge">VIP</span>' : '';
    const farm = String(r.farmvip||'').toLowerCase()==='true' ? '<span class="badge">Farm VIP with Gold</span>' : '';

    push('Tier', r.tier);
    if(String(r.dailysc||'').toLowerCase()==='true') push('Daily SC Bonus', r.dailyamount||'Yes');
    push('Min Cash Redemption', r.cashredemption);
    push('Min Gift Card Redemption', r.giftcardredemption);
    push('Min Crypto Redemption', r.cryptoredemption);
    push('Affiliations', r.affiliated);
    push('Location', r.loc);
    push('Notes', r.publicnotes);
    push('Last Checked', r.lastcheck);

    return `
    <article class="card" role="listitem">
      <div class="top">
        ${logo? `<img src="${logo}" alt="${r.displayname||'Logo'}" style="width:66px;height:64px;object-fit:contain">` : ''}
        <a class="name-btn" href="${url}" target="_blank" rel="nofollow noopener">${r.displayname||'Visit'}</a>
        ${vip}${farm}
      </div>
      ${fields.join('')}
      ${socialIconsFor(r.displayname, all)}
    </article>`;
  }

  // --- Surveys ---
  async function renderSurveys(){
    const grid = document.getElementById('grid');
    const q    = document.getElementById('q');
    const sort = document.getElementById('sort');
    setStatus('Loading…');
    try{
      const rows = await loadSheet();
      const all = rows.filter(r => (r.type||'').toLowerCase()==='surveys');
      const searchFn = ()=>compileQuery(q.value);
      const draw = ()=>{
        const pred = searchFn();
        let list = all.filter(pred);
        const s = sort.value;
        if(s==='alpha_asc') list.sort(by('displayname','asc', v=>String(v||'').toLowerCase()));
        if(s==='alpha_desc') list.sort(by('displayname','desc', v=>String(v||'').toLowerCase()));
        if(s==='lastcheck_asc') list.sort(by('lastcheck','asc', toDate));
        if(s==='lastcheck_desc') list.sort(by('lastcheck','desc', toDate));
        if(s==='cash_min') list.sort(by('cashredemption','asc', toMoney));
        grid.innerHTML = list.map(row=>cardSurvey(row, rows)).join('');
        setStatus(`${list.length} result(s)`);
      };
      q.addEventListener('input', draw); sort.addEventListener('change', draw);
      draw();
    }catch(e){ setStatus('Failed to load listings'); }    
  }

  function cardSurvey(r, all){
    const fields = [];
    const push = (k, v)=>{ if(v!==undefined && v!==null && String(v).trim()!=='') fields.push(`<div class=\"row\"><div class=\"k\">${k}</div><div class=\"v\">${v}</div></div>`); };
    const url = pickLink(r);
    const logo = safeURL(r.logo);

    push('Min Cash Redemption', r.cashredemption);
    push('Min Gift Card Redemption', r.giftcardredemption);
    push('Min Crypto Redemption', r.cryptoredemption);
    push('Location', r.loc);
    push('Notes', r.publicnotes);
    push('Last Checked', r.lastcheck);

    return `
    <article class="card" role="listitem">
      <div class="top">
        ${logo? `<img src="${logo}" alt="${r.displayname||'Logo'}" style="width:44px;height:44px;object-fit:contain">` : ''}
        <a class="name-btn" href="${url}" target="_blank" rel="nofollow noopener">${r.displayname||'Visit'}</a>
      </div>
      ${fields.join('')}
    
      ${socialIconsFor(r.displayname, all)}</article>`;
  }

  // --- Remote ---
  async function renderRemote(){
    const grid = document.getElementById('grid');
    const q    = document.getElementById('q');
    const sort = document.getElementById('sort');
    setStatus('Loading…');
    try{
      const rows = await loadSheet();
      const all = rows.filter(r => (r.type||'').toLowerCase()==='remote');
      const searchFn = ()=>compileQuery(q.value);
      const draw = ()=>{
        const pred = searchFn();
        let list = all.filter(pred);
        const s = sort.value;
        if(s==='alpha_asc') list.sort(by('displayname','asc', v=>String(v||'').toLowerCase()));
        if(s==='alpha_desc') list.sort(by('displayname','desc', v=>String(v||'').toLowerCase()));
        if(s==='lastcheck_asc') list.sort(by('lastcheck','asc', toDate));
        if(s==='lastcheck_desc') list.sort(by('lastcheck','desc', toDate));
        grid.innerHTML = list.map(row=>cardRemote(row, rows)).join('');
        setStatus(`${list.length} result(s)`);
      };
      q.addEventListener('input', draw); sort.addEventListener('change', draw);
      draw();
    }catch(e){ setStatus('Failed to load listings'); }
  }

  function cardRemote(r, all){
    const fields = [];
    const push = (k, v)=>{ if(v!==undefined && v!==null && String(v).trim()!=='') fields.push(`<div class=\"row\"><div class=\"k\">${k}</div><div class=\"v\">${v}</div></div>`); };
    const url = pickLink(r);
    const logo = safeURL(r.logo);

    push('Notes', r.publicnotes);
    push('Last Checked', r.lastcheck);

    return `
    <article class="card" role="listitem">
      <div class="top">
        ${logo? `<img src="${logo}" alt="${r.displayname||'Logo'}" style="width:44px;height:44px;object-fit:contain">` : ''}
        <a class="name-btn" href="${url}" target="_blank" rel="nofollow noopener">${r.displayname||'Visit'}</a>
      </div>
      ${fields.join('')}
    
      ${socialIconsFor(r.displayname, all)}</article>`;
  }

  // --- Reddit (RSS aggregate) ---
  async function renderReddit(){
    const feed = document.getElementById('feed');
    const q    = document.getElementById('q');
    const sort = document.getElementById('sort');
    setStatus('Loading…');
    try{
      const rows = await loadSheet();
      const subs = rows.filter(r => (r.type||'').toLowerCase()==='subreddit');
      const draw = async ()=>{
        const mode = sort.value || 'new';
        setStatus('Loading…');
        const items = (await Promise.all(subs.map(s=>loadSubreddit(s, mode)))).flat();
        const pred = compileQuery(q.value);
        const filtered = items.filter(pred);
        filtered.sort((a,b)=> new Date(b.date) - new Date(a.date));
        feed.innerHTML = filtered.map(renderRedditCard).join('');
        setStatus(`${filtered.length} post(s)`);
      };
      q.addEventListener('input', draw); sort.addEventListener('change', draw);
      await draw();
    }catch(e){ setStatus('Failed to load feed'); }
  }

  function renderRedditCard(it){
    const subUrl = it.subreddit_url || '#';
    const authUrl = it.author_url || '#';
    const comments = typeof it.comments === 'number' ? it.comments : '';
    return `
    <article class="reddit-card" role="listitem">
      <div class="reddit-top">
        <div class="reddit-date">${new Date(it.date).toLocaleString()}</div>
        <div class="reddit-title"><a href="${it.link}" target="_blank" rel="nofollow noopener">${it.title}</a></div>
      </div>
      <div class="reddit-body">${it.summary}</div>
      <div class="reddit-bottom">
        <a href="${subUrl}" target="_blank" rel="nofollow noopener">${it.subreddit}</a>
        <a style="text-align:center" href="${authUrl}" target="_blank" rel="nofollow noopener">${it.author}</a>
        <div style="text-align:right">${comments!==''? comments+ ' comments':''}</div>
      </div>
    </article>`;
  }

async function loadSubreddit(row, mode){
  const base = (row.loginlink||'').replace(/\/+$/,'')
    .replace(/^https?:\/\/reddit\.com/i,'https://www.reddit.com'); // normalize
  const url = `${base}/${mode}/.rss`; // <-- note the "/.rss"

  const tries = [
    url,
    `https://r.jina.ai/${url}` // keep as last-ditch, may not return XML
  ];

  for (const u of tries) {
    try {
      const xmlText = await (await fetch(u, {mode:'cors'})).text();
      const items = parseRedditRSS(xmlText, row);
      if (items.length) return items;
    } catch {}
  }
  return [];
}


  function parseRedditRSS(xmlText, row){
    try{
      const doc = new DOMParser().parseFromString(xmlText, 'application/xml');
      const es = [...doc.querySelectorAll('entry, item')];
      return es.map(e=>{
        const title = txt(e, 'title');
        const link = attr(e, 'link', 'href') || txt(e, 'link');
        const author = txt(e, 'author > name') || txt(e, 'dc\\:creator') || 'u/unknown';
        const author_url = attr(e, 'author > uri', 'textContent') || '';
        const updated = txt(e, 'updated') || txt(e, 'pubDate') || new Date().toISOString();
        const summary = (txt(e, 'content') || txt(e, 'summary') || '').replace(/<[^>]+>/g,'').slice(0,800);
        const cmts = num(txt(e, 'comments'));
        return {
          title,
          link,
          author,
          author_url,
          date: updated,
          summary,
          subreddit: row.displayname || '',
          subreddit_url: row.loginlink || '' ,
          comments: isNaN(cmts)? '': cmts
        };
      });
    }catch(_){ return []; }
  }
  function txt(el, sel){ const n = el.querySelector(sel); return n? (n.textContent||'').trim():''; }
  function attr(el, sel, a){ const n = el.querySelector(sel); if(!n) return ''; return a==='textContent'? (n.textContent||'').trim() : (n.getAttribute(a)||''); }
  function num(s){ const n = Number(String(s||'').replace(/[^0-9.-]/g,'')); return isNaN(n)? NaN: n; }

})();

// SEO: emit JSON-LD ItemList for crawlers after cards render
function emitJsonLdItemList(pageName, items) {
  try {
    const list = {
      "@context": "https://schema.org",
      "@type": "ItemList",
      "name": pageName + " Listing",
      "itemListElement": items.map((it, idx) => ({
        "@type": "ListItem",
        "position": idx + 1,
        "url": it.url,
        "name": it.name,
        "additionalProperty": Object.entries(it.props || {}).map(([k, v]) => ({
          "@type": "PropertyValue",
          "name": k,
          "value": v == null ? "" : String(v)
        }))
      }))
    };
    const node = document.getElementById("jsonld-listing");
    if (node) node.textContent = JSON.stringify(list);
  } catch(e) {
    // silent
  }
}

// Generic hook: if window.__TB_afterRender exists, let it map DOM -> items and emit JSON-LD.
(function(){
  try {
    if (typeof window.__TB_afterRender === "function") {
      const info = window.__TB_afterRender();
      if (info && info.page && Array.isArray(info.items)) {
        emitJsonLdItemList(info.page, info.items);
      }
    }
  } catch(e) {}
})();
