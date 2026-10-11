// Pay Pro Dynamics — UI interactions + validation + payroll preview. No business logic authority.
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('open'); }

function toast(msg, type){
  const w = document.getElementById('toast-wrap');
  if(!w) return;
  const d = document.createElement('div');
  d.className = 'toast ' + (type === 'success' ? 'ok' : type === 'error' ? 'err' : '');
  d.textContent = msg;
  w.appendChild(d);
  setTimeout(()=>d.remove(), 3500);
}

function confirmAction(msg){
  return confirm(msg || 'Are you sure?');
}

// Wire all [data-confirm] buttons
document.addEventListener('click', (ev)=>{
  const b = ev.target.closest('[data-confirm]');
  if(b && !confirm(b.getAttribute('data-confirm'))) ev.preventDefault();
});

// Show/hide password
function togglePw(id){
  const i = document.getElementById(id);
  if(i) i.type = i.type === 'password' ? 'text' : 'password';
}

// Simple required-field highlight
document.addEventListener('submit', (ev)=>{
  const f = ev.target;
  if(!f.matches('form[data-validate]')) return;
  let ok = true;
  f.querySelectorAll('[required]').forEach((el)=>{
    el.style.borderColor = '';
    const bad = !el.value || (el.type==='email' && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(el.value));
    if(bad){ ok = false; el.style.borderColor = '#DC2626'; }
  });
  if(!ok){ ev.preventDefault(); toast('Please fill in all required fields.', 'error'); }
});

// Attendance: auto-compute hours from time in/out
function attAutoCalc(){
  const ti = document.getElementById('time_in'), to = document.getElementById('time_out');
  if(!ti || !to) return;
  const parse = (v)=>{ const [h,m] = v.split(':').map(Number); return h + (m/60); };
  const upd = ()=>{
    if(!ti.value || !to.value) return;
    let diff = parse(to.value) - parse(ti.value);
    if(diff < 0) diff = 0;
    const reg = Math.min(diff, 8), ot = Math.max(diff - 8, 0);
    const r = document.getElementById('regular_hours'), o = document.getElementById('overtime_hours');
    if(r) r.value = reg.toFixed(2);
    if(o) o.value = ot.toFixed(2);
  };
  ti.addEventListener('change', upd); to.addEventListener('change', upd);
}
document.addEventListener('DOMContentLoaded', attAutoCalc);

// Payroll live preview (mirrors PHP formula; PHP recalculates on submit)
function payrollPreview(){
  const g = (id)=> parseFloat((document.getElementById(id)||{value:0}).value) || 0;
  const set = (id,v)=>{ const el=document.getElementById(id); if(el) el.textContent = '₱' + Number(v).toFixed(2); };
  const upd = ()=>{
    const regPay = g('regular_pay_in'), otH = g('overtime_hours'), otR = g('overtime_rate'),
          ded = g('deductions_in');
    const otPay = otH * otR, gross = regPay + otPay, net = gross - ded;
    set('pv-regular', regPay); set('pv-ot', otPay); set('pv-gross', gross);
    set('pv-ded', ded); set('pv-net', net);
  };
  ['regular_pay_in','overtime_hours','overtime_rate','deductions_in'].forEach((id)=>{
    const el = document.getElementById(id); if(el) el.addEventListener('input', upd);
  });
  upd();
}
document.addEventListener('DOMContentLoaded', ()=>{ if(document.getElementById('pv-net')) payrollPreview(); });
