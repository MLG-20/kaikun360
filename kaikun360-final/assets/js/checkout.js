const fmt = new Intl.NumberFormat('fr-FR');
const params = new URLSearchParams(location.search);
const title = params.get('title') || 'Acompte villa Saly';
const amount = Number(params.get('amount') || 85000);
let method = 'Wave';
const ref = `K360-${new Date().getFullYear()}-${Math.floor(100000+Math.random()*899999)}`;

document.getElementById('checkoutTitle').textContent = title;
document.getElementById('serviceAmount').textContent = `${fmt.format(amount)} FCFA`;
document.getElementById('totalAmount').textContent = `${fmt.format(amount)} FCFA`;
document.getElementById('checkoutReference').value = ref;

document.querySelectorAll('.method-card').forEach(card=>card.addEventListener('click',()=>{
  document.querySelectorAll('.method-card').forEach(x=>x.classList.remove('active'));
  card.classList.add('active');
  method = card.dataset.method;
  document.getElementById('checkoutPay').textContent = `Continuer avec ${method}`;
}));

document.getElementById('checkoutPay').addEventListener('click',()=>{
  const btn=document.getElementById('checkoutPay');
  const alert=document.getElementById('checkoutAlert');
  btn.disabled=true;btn.textContent='Création de la session sécurisée…';
  alert.className='checkout-alert';
  setTimeout(()=>{
    document.querySelectorAll('.checkout-step')[1].classList.remove('active');
    document.querySelectorAll('.checkout-step')[1].classList.add('done');
    document.querySelectorAll('.checkout-step')[2].classList.add('active');
    alert.className='checkout-alert show';
    alert.innerHTML=`<strong>Simulation réussie.</strong><br>Référence : ${ref}<br>Moyen : ${method}<br>Montant : ${fmt.format(amount)} FCFA<br><br>En production, l'utilisateur serait redirigé vers le canal du PSP, puis Kaikun attendrait le webhook signé avant de passer la commande au statut <strong>confirmed</strong>.`;
    btn.textContent='Paiement simulé confirmé ✓';
  },1100);
});
