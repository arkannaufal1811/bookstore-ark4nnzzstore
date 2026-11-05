// Struk tampil sebagai POP UP (modal) di atas halaman, tanpa pindah halaman.
// - Link ke struk.php otomatis dibuka sebagai pop up
// - Setelah checkout, halaman membuka struk otomatis lewat ?struk=ID
(function(){
  var script = document.currentScript;
  var base = script ? script.src.split('/assets/js/')[0] : '';
  var overlay, frame;

  function build(){
    overlay = document.createElement('div');
    overlay.style.cssText = 'display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.55); align-items:center; justify-content:center; padding:16px;';
    var box = document.createElement('div');
    box.style.cssText = 'position:relative; width:100%; max-width:540px; height:92vh; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.35);';
    var x = document.createElement('button');
    x.type = 'button'; x.innerHTML = '&times;'; x.title = 'Tutup'; x.setAttribute('aria-label', 'Tutup struk');
    x.style.cssText = 'position:absolute; top:6px; right:10px; z-index:2; width:32px; height:32px; border:none; border-radius:50%; background:rgba(0,0,0,.08); font-size:22px; line-height:1; cursor:pointer; color:#333;';
    x.addEventListener('click', close);
    frame = document.createElement('iframe');
    frame.style.cssText = 'width:100%; height:100%; border:0; background:#fff;';
    frame.setAttribute('title', 'Struk Pesanan');
    box.appendChild(x); box.appendChild(frame); overlay.appendChild(box);
    overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
    document.body.appendChild(overlay);
  }

  function open(url){
    if (!overlay) build();
    frame.src = url + (url.indexOf('?') > -1 ? '&' : '?') + 'popup=1';
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function close(){
    if (!overlay) return;
    overlay.style.display = 'none';
    frame.src = 'about:blank';
    document.body.style.overflow = '';
    if (/[?&]struk=/.test(location.search) && window.history.replaceState){
      window.history.replaceState(null, '', location.pathname);
    }
  }

  // klik link struk -> pop up
  document.addEventListener('click', function(e){
    var a = e.target.closest ? e.target.closest('a[href*="struk.php"]') : null;
    if (!a) return;
    e.preventDefault();
    open(a.href);
  });

  // tombol "Tutup" di dalam struk
  window.addEventListener('message', function(e){
    if (e.data === 'close-struk') close();
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') close();
  });

  // buka otomatis setelah checkout: ?struk=ID
  var m = location.search.match(/[?&]struk=(\d+)/);
  if (m){
    open(base + '/struk.php?id=' + m[1] + (/[?&]success=1/.test(location.search) ? '&success=1' : ''));
  }
})();
