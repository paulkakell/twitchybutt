(function () {
  const year = new Date().getFullYear();
  const html = `
    <footer class="site-footer">
      <nav class="footer-nav">
        <a href="index.html">Home</a>
        <a href="Remote.html">Remote</a>
        <a href="Surveys.html">Surveys</a>
        <a href="Casinos.html">Casinos</a>
        <a href="Reddit.html">Reddit</a>
      </nav>
      <p>
      <p class="fineprint">
        <a href="https://www.facebook.com/profile.php?id=61580647524082"><img src="https://cdn.brandfetch.io/idpKX136kp/w/400/h/400/theme/dark/icon.jpeg?c=1bxid64Mup7aczewSAYMX&t=1717150449175" alt="Facebook" width="25" height="25"></a>
        Powered By <a href="https://meatmutts.com">MeatMutts.com</a>.<br>
        © ${year} TwitchyButt. Some links may be referral or affiliate links.
      </p>
    </footer>`;
  function mount() {
    const host = document.getElementById('site-footer') || document.body;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    host.appendChild(wrapper.firstElementChild);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();