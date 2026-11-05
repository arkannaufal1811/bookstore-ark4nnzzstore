// Tombol "lihat password" (ikon mata) otomatis untuk semua kolom password
(function(){
  var EYE = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

  document.querySelectorAll('input[type="password"]').forEach(function(input){
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.innerHTML = EYE;
    btn.title = 'Lihat password';
    btn.setAttribute('aria-label', 'Lihat atau sembunyikan password');
    btn.style.cssText = 'background:none; border:none; cursor:pointer; padding:0; display:flex; align-items:center; color:#8a8a8a;';
    btn.addEventListener('mouseenter', function(){ btn.style.color = '#444'; });
    btn.addEventListener('mouseleave', function(){ btn.style.color = '#8a8a8a'; });
    btn.addEventListener('click', function(){
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show ? EYE_OFF : EYE;
      btn.title = show ? 'Sembunyikan password' : 'Lihat password';
      input.focus();
    });
    input.parentNode.insertBefore(btn, input.nextSibling);
  });
})();
