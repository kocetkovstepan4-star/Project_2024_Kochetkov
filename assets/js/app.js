(function(){
  const STORAGE_KEYS = {
    user: 'dq_user',
    schedule: 'dq_schedule'
  };

  function getUser(){
    try{ return JSON.parse(localStorage.getItem(STORAGE_KEYS.user) || 'null'); }catch(e){ return null; }
  }
  function setUser(user){
    localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(user));
  }
  async function api(path, method = 'GET', body){
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if(body !== undefined) opts.body = JSON.stringify(body);
    const res = await fetch(path, opts);
    const json = await res.json().catch(()=>({ ok:false, error:'Bad JSON' }));
    return json;
  }
  async function logout(){
    await api('api/auth.php?action=logout', 'POST', {});
    localStorage.removeItem(STORAGE_KEYS.user);
    location.reload();
  }

  function ensureSchedule(){
    // schedule structure: { 'YYYY-WW': { 'YYYY-MM-DD': { morning: true/false, evening: true/false } } }
    let sched = null;
    try{ sched = JSON.parse(localStorage.getItem(STORAGE_KEYS.schedule) || 'null'); }catch(e){ sched = null; }
    if(!sched){
      sched = {};
      saveSchedule(sched);
    }
    return sched;
  }
  function saveSchedule(s){ localStorage.setItem(STORAGE_KEYS.schedule, JSON.stringify(s)); }

  function formatDateISO(d){ return d.toISOString().slice(0,10); }
  function getWeekKey(d){
    // ISO week key: YYYY-WW
    const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    const dayNum = (t.getUTCDay() + 6) % 7; // Mon=0..Sun=6
    t.setUTCDate(t.getUTCDate() - dayNum + 3);
    const firstThursday = new Date(Date.UTC(t.getUTCFullYear(),0,4));
    const week = 1 + Math.round(((t - firstThursday) / 86400000 - 3 + ((firstThursday.getUTCDay()+6)%7)) / 7);
    const year = t.getUTCFullYear();
    return `${year}-${String(week).padStart(2,'0')}`;
  }

  function initAuthUI(){
    const authArea = document.getElementById('authArea');
    if(!authArea) return;
    // mobile menu toggle
    const navToggle = document.getElementById('navToggle');
    const mainMenu = document.getElementById('mainMenu');
    if(navToggle && mainMenu){
      navToggle.addEventListener('click', function(){
        mainMenu.classList.toggle('open');
      });
    }
    const user = getUser();
    const loginForm = document.getElementById('loginForm');
    const userBox = document.getElementById('userBox');
    const userPhone = document.getElementById('userPhone');
    const logoutBtn = document.getElementById('logoutBtn');

    if(user){
      if(loginForm) loginForm.classList.add('hidden');
      if(userBox){
        userBox.classList.remove('hidden');
        if(userPhone) userPhone.textContent = user.phone;
      }
    } else {
      if(loginForm) loginForm.classList.remove('hidden');
      if(userBox) userBox.classList.add('hidden');
    }

    if(loginForm){
      loginForm.addEventListener('submit', async function(e){
        e.preventDefault();
        const phone = (document.getElementById('loginPhone') || {}).value || '';
        const pass = (document.getElementById('loginPassword') || {}).value || '';
        const resp = await api('api/auth.php?action=login', 'POST', { phone, password: pass });
        if(resp && resp.ok){ setUser({ phone: resp.phone }); location.reload(); }
        else { alert((resp && resp.error) || 'Ошибка входа'); }
      });
    }
    if(logoutBtn){ logoutBtn.addEventListener('click', logout); }
  }

  function requireAuth(){
    if(!getUser()){
      alert('Доступ только для авторизованных пользователей');
      location.href = 'register.html';
    }
  }

  function initRegister(){
    const form = document.getElementById('registerForm');
    if(!form) return;
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const phone = (document.getElementById('regPhone') || {}).value || '';
      const pass = (document.getElementById('regPassword') || {}).value || '';
      if(!/^\+?\d{10,15}$/.test(phone.replace(/\s|-/g,''))){ alert('Введите корректный номер телефона'); return; }
      if(pass.length < 4){ alert('Пароль должен быть не менее 4 символов'); return; }
      const resp = await api('api/auth.php?action=register', 'POST', { phone, password: pass });
      if(resp && resp.ok){ setUser({ phone: resp.phone }); location.href = 'index.html'; }
      else { alert((resp && resp.error) || 'Ошибка регистрации'); }
    });
  }

  function buildDays(numDays){
    const days = [];
    const start = new Date();
    for(let i=0;i<numDays;i++){
      const d = new Date(start);
      d.setDate(start.getDate()+i);
      days.push(d);
    }
    return days;
  }

  function initSchedule(){
    const scheduleRoot = document.getElementById('scheduleRoot');
    if(!scheduleRoot) return;
    const dateSelect = document.getElementById('daySelect');
    const weekLabel = document.getElementById('weekLabel');
    const tableBody = document.getElementById('scheduleBody');
    const minMax = document.getElementById('minMax');
    if(minMax) minMax.textContent = 'Минимум: 2 игрока · Максимум: 6 игроков';

    const now = new Date();
    const days = buildDays(14);
    const sched = ensureSchedule();
    const weekKey = getWeekKey(now);
    if(!sched[weekKey]) sched[weekKey] = {};

    // Populate select
    if(dateSelect){
      dateSelect.innerHTML = '';
      days.forEach(d => {
        const opt = document.createElement('option');
        opt.value = formatDateISO(d);
        opt.textContent = d.toLocaleDateString('ru-RU', { weekday:'long', day:'2-digit', month:'2-digit' });
        dateSelect.appendChild(opt);
      });
    }
    if(weekLabel){
      const first = days[0], last = days[days.length-1];
      weekLabel.textContent = `Диапазон: ${first.toLocaleDateString('ru-RU')} — ${last.toLocaleDateString('ru-RU')}`;
    }

    async function renderForDay(dayISO){
      tableBody.innerHTML = '';
      const resp = await api('api/schedule.php', 'GET');
      const items = (resp && resp.ok) ? resp.items : [];
      const list = items.filter(it => it.start.slice(0,10) === dayISO);
      if(list.length === 0){
        const tr = document.createElement('tr');
        const td = document.createElement('td'); td.colSpan = 2; td.textContent = 'Нет слотов на этот день';
        tr.appendChild(td); tableBody.appendChild(tr);
        return;
      }
      list.forEach(it => {
        const tr = document.createElement('tr'); tr.className = 'slot-row';
        const tdLabel = document.createElement('td');
        const dt = new Date(it.start.replace(' ', 'T'));
        const end = new Date(it.end.replace(' ', 'T'));
        const fmt = dt.toLocaleTimeString('ru-RU', { hour:'2-digit', minute:'2-digit' }) + ' – ' + end.toLocaleTimeString('ru-RU', { hour:'2-digit', minute:'2-digit' });
        tdLabel.textContent = fmt;
        const tdSlots = document.createElement('td');
        const slot = document.createElement('span');
        const available = !it.booked;
        slot.className = 'slot ' + (available ? 'available' : 'booked');
        slot.textContent = available ? 'Записаться' : 'Занято';
        if(available){
          slot.addEventListener('click', async function(){
            if(!getUser()){ alert('Чтобы записаться, войдите в аккаунт'); return; }
            if(confirm('Подтвердить запись на ' + fmt + '?')){
              const bookResp = await api('api/schedule.php', 'POST', { timeSlotId: it.id });
              if(bookResp && bookResp.ok){ renderForDay(dayISO); alert('Вы успешно записались!'); }
              else { alert((bookResp && bookResp.error) || 'Ошибка записи'); }
            }
          });
        }
        tdSlots.appendChild(slot);
        tr.appendChild(tdLabel); tr.appendChild(tdSlots);
        tableBody.appendChild(tr);
      });
    }

    const initialDay = formatDateISO(days[0]);
    renderForDay(initialDay);
    if(dateSelect){
      dateSelect.value = initialDay;
      dateSelect.addEventListener('change', function(){ renderForDay(this.value); });
    }

    // Auto-refresh schedule every 15 seconds for the selected day
    setInterval(function(){
      const dayISO = (dateSelect && dateSelect.value) ? dateSelect.value : initialDay;
      renderForDay(dayISO);
    }, 15000);
  }

  function initQuestNav(){
    const btn = document.getElementById('agreeBtn');
    if(btn){ btn.addEventListener('click', function(){ /* link via href */ }); }
  }

  function initReviews(){
    const root = document.getElementById('reviewsRoot');
    if(!root) return;
    // Only auth users can view/add
    if(!getUser()){
      root.innerHTML = '<div class="card"><p>Отзывы доступны только авторизованным пользователям.</p><p class="mt-20"><a class="btn" href="register.html">Войти/Зарегистрироваться</a></p></div>';
      return;
    }
    const list = document.getElementById('reviewsList');
    const form = document.getElementById('reviewForm');
    const ratingInput = document.getElementById('ratingInput');
    const avgStars = document.getElementById('avgStars');
    const avgLabel = document.getElementById('avgLabel');
    async function load(){
      const resp = await api('api/reviews.php', 'GET');
      return (resp && resp.ok) ? resp.items : [];
    }
    function renderStars(container, value){
      if(!container) return;
      container.innerHTML = '';
      const v = Math.round(value);
      for(let i=1;i<=5;i++){
        const s = document.createElement('span');
        s.className = 'star' + (i <= v ? ' filled' : '');
        container.appendChild(s);
      }
    }
    async function render(){
      const items = await load();
      list.innerHTML = '';
      if(items.length === 0){
        list.innerHTML = '<p class="muted">Пока нет отзывов. Будьте первым!</p>';
      } else {
        items.forEach(it => {
          const d = document.createElement('div');
          d.className = 'card mt-20 review-card';
          const avatar = document.createElement('div');
          avatar.className = 'avatar';
          const img = document.createElement('img');
          img.src = 'assets/img/joker.jpg';
          img.alt = 'Аватар';
          avatar.appendChild(img);
          const body = document.createElement('div');
          const header = document.createElement('div'); header.className = 'review-meta';
          const starsEl = document.createElement('div'); starsEl.className = 'stars'; renderStars(starsEl, it.rating || 0);
          const phoneEl = document.createElement('span'); phoneEl.textContent = it.phone;
          const dateEl = document.createElement('span'); dateEl.textContent = new Date(it.ts).toLocaleDateString('ru-RU');
          header.appendChild(starsEl); header.appendChild(phoneEl); header.appendChild(dateEl);
          const text = document.createElement('div'); text.className = 'review-text'; text.textContent = it.text;
          body.appendChild(header); body.appendChild(text);
          d.appendChild(avatar); d.appendChild(body);
          list.appendChild(d);
        });
      }
      // avg
      const sum = items.reduce((acc,it)=> acc + (it.rating || 0), 0);
      const avg = items.length ? (sum/items.length) : 0;
      renderStars(avgStars, avg);
      if(avgLabel) avgLabel.textContent = `${avg.toFixed(1)} (${items.length})`;
    }
    render();
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const txt = (document.getElementById('reviewText') || {}).value || '';
      if(txt.trim().length < 3){ alert('Слишком короткий отзыв'); return; }
      const u = getUser();
      const rating = parseInt((form.getAttribute('data-rating') || '0'), 10) || 0;
      if(rating < 1){ alert('Поставьте оценку от 1 до 5 звёзд'); return; }
      const saveResp = await api('api/reviews.php', 'POST', { text: txt.trim(), rating });
      if(!(saveResp && saveResp.ok)){ alert((saveResp && saveResp.error) || 'Ошибка отправки'); return; }
      (document.getElementById('reviewText') || {}).value = '';
      form.setAttribute('data-rating','0');
      if(ratingInput){ Array.from(ratingInput.querySelectorAll('.star')).forEach(s=> s.classList.remove('filled')); }
      render();
    });
    // interactive rating input
    if(ratingInput){
      const buttons = Array.from(ratingInput.querySelectorAll('[data-val]'));
      function update(val){
        buttons.forEach(btn => {
          const star = btn.querySelector('.star');
          if(star){ star.classList.toggle('filled', parseInt(btn.dataset.val,10) <= val); }
        });
        form.setAttribute('data-rating', String(val));
      }
      buttons.forEach(btn => {
        btn.addEventListener('click', function(){ update(parseInt(this.dataset.val,10)); });
      });
      update(0);
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    initAuthUI();
    initQuestNav();
    initRegister();
    initSchedule();
    initReviews();
  });
})();


