
// Auto-map visible cards into JSON-LD structure
window.__TB_afterRender = function(){
  const page = document.title || "Listing";
  const cards = Array.from(document.querySelectorAll('[data-card]'));
  const items = cards.map((card, i) => {
    const a = card.querySelector('a[data-primary]');
    const name = a ? (a.textContent || "").trim() : (card.getAttribute('data-name') || ('Item ' + (i+1)));
    const url = a ? a.href : document.location.href;
    const props = {};
    card.querySelectorAll('[data-prop]').forEach(el => {
      const k = el.getAttribute('data-prop');
      const v = (el.textContent || "").trim();
      if (k) props[k] = v;
    });
    return { name, url, props };
  });
  return { page, items };
};
