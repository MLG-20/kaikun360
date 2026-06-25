const operations = [
  {p:'P1',ref:'K360-PAY-8421',desc:'Paiement reçu non visible',client:'M. Diallo',created:'08:37',sla:'8 min',agent:'A. Fall',status:'À rapprocher',chip:'red'},
  {p:'P2',ref:'K360-TR-1184',desc:'Navette AIBD sous 6 h',client:'A. Ndiaye',created:'08:22',sla:'12 min',agent:'M. Sarr',status:'À confirmer',chip:'gold'},
  {p:'P2',ref:'K360-ST-3920',desc:'Hébergement indisponible',client:'S. Kane',created:'08:15',sla:'18 min',agent:'F. Ba',status:'Alternative',chip:'orange'},
  {p:'P3',ref:'K360-IM-7602',desc:'Terrain 300 m²',client:'A. Seck',created:'07:54',sla:'2 h 10',agent:'K. Diop',status:'Contrôle pièces',chip:'blue'},
  {p:'P3',ref:'K360-BD-2007',desc:'Pré-devis maison R+1',client:'Diaspora Faye',created:'07:40',sla:'3 h 40',agent:'N. Gueye',status:'Quantitatif',chip:'gray'},
  {p:'P3',ref:'K360-TB-1911',desc:'Team building 42 personnes',client:'Entreprise N.',created:'07:32',sla:'1 h 30',agent:'M. Sarr',status:'Disponibilités',chip:'blue'}
];

const catalog = [
  {img:'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=300&q=70',title:'Villa contemporaine avec piscine',ref:'OFF-IM-3021',universe:'Immobilier',owner:'Mme Ndiaye',price:'95 000 000 F',docs:'12/12',availability:'Disponible',status:'Publié',chip:'green'},
  {img:'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=300&q=70',title:'Terrain résidentiel 300 m²',ref:'OFF-IM-7602',universe:'Immobilier',owner:'M. Seck',price:'18 000 000 F',docs:'8/10',availability:'À confirmer',status:'À valider',chip:'gold'},
  {img:'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=300&q=70',title:'Pirogue motorisée 12 places',ref:'OFF-TR-1440',universe:'Transport',owner:'GIE Delta Saloum',price:'80 000 F',docs:'11/11',availability:'Disponible',status:'Publié',chip:'green'},
  {img:'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=300&q=70',title:'4×4 touristique avec chauffeur',ref:'OFF-TR-2204',universe:'Transport',owner:'Transport Ndiambour',price:'70 000 F',docs:'9/9',availability:'3 dates bloquées',status:'Publié',chip:'green'},
  {img:'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=300&q=70',title:'Écolodge bord de bolong',ref:'OFF-TO-3920',universe:'Tourisme',owner:'Résidence Baobab',price:'55 000 F',docs:'7/8',availability:'Indisponible 24/06',status:'Pièce manquante',chip:'red'},
  {img:'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=300&q=70',title:'Pack construction R+1',ref:'OFF-BD-2007',universe:'Construction',owner:'Atelier M.',price:'Sur devis',docs:'4/9',availability:'Capacité limitée',status:'Brouillon',chip:'gray'}
];

const payments = [
  {ref:'K360-PAY-8421',client:'M. Diallo',object:'Acompte villa Saly',method:'Wave',amount:'85 000 F',webhook:'Reçu',status:'confirmed',chip:'green',gap:'0 F'},
  {ref:'K360-PAY-8812',client:'A. Ba',object:'Navette AIBD',method:'Orange Money',amount:'25 000 F',webhook:'Doublon',status:'disputed',chip:'red',gap:'+25 000 F'},
  {ref:'K360-PAY-7810',client:'S. Kane',object:'Écolodge Toubacouta',method:'Wave',amount:'110 000 F',webhook:'En retard',status:'pending',chip:'gold',gap:'110 000 F'},
  {ref:'K360-PAY-7604',client:'Groupe UIDT',object:'Pirogue bolongs',method:'Free Money',amount:'80 000 F',webhook:'Reçu',status:'eligible_for_payout',chip:'blue',gap:'0 F'},
  {ref:'K360-PAY-7598',client:'Entreprise N.',object:'Team building',method:'Carte',amount:'450 000 F',webhook:'Reçu',status:'service_in_progress',chip:'orange',gap:'0 F'},
  {ref:'K360-PAY-7422',client:'M. Sow',object:'Location berline',method:'Wave',amount:'70 000 F',webhook:'Reçu',status:'paid_out',chip:'green',gap:'0 F'}
];

const titles = {
  dashboard:'Tableau de bord',operations:'File opérationnelle',reservations:'Réservations',catalog:'Catalogue',kyc:'KYC / Conformité',users:'Utilisateurs',payments:'Paiements',payouts:'Payouts EOD',incidents:'Incidents',reports:'Rapports & journaux',settings:'Paramètres'
};

const permissions = {
  'Super-administrateur':['Super-administrateur','Comptable / paiements','Responsable immobilier','Responsable tourisme & transport','Agent validation','Agent relation client','Conformité','Auditeur'],
  'Responsable immobilier':['Responsable immobilier'],
  'Responsable tourisme & transport':['Responsable tourisme & transport'],
  'Agent validation':['Agent validation'],
  'Agent relation client':['Agent relation client'],
  'Comptable / paiements':['Comptable / paiements'],
  'Conformité':['Conformité'],
  'Auditeur':['Auditeur']
};

function showView(name, push=true){
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  document.querySelectorAll('.side-link[data-view]').forEach(b=>b.classList.toggle('active',b.dataset.view===name));
  const view=document.getElementById(`view-${name}`);
  if(view){view.classList.add('active');document.getElementById('pageTitle').textContent=titles[name]||name;if(push)history.replaceState(null,'',`#${name}`);window.scrollTo({top:0,behavior:'smooth'});}
  document.getElementById('sidebar').classList.remove('open');
}

document.querySelectorAll('.side-link[data-view]').forEach(btn=>btn.addEventListener('click',()=>showView(btn.dataset.view)));
document.querySelectorAll('[data-open-view]').forEach(btn=>btn.addEventListener('click',()=>showView(btn.dataset.openView)));
document.getElementById('adminMenu').addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('open'));

function renderOperations(items=operations){
  document.getElementById('operationsTable').innerHTML=items.map(x=>`<tr><td class="priority ${x.p.toLowerCase()}">${x.p}</td><td><strong>${x.ref}</strong><small>${x.desc}</small></td><td>${x.client}</td><td>${x.created}</td><td>${x.sla}</td><td>${x.agent}</td><td><span class="chip ${x.chip}">${x.status}</span></td><td><button class="btn btn-outline detail-btn" data-kind="operation" data-ref="${x.ref}">Ouvrir</button></td></tr>`).join('');
  bindDetails();
}

function renderCatalog(items=catalog){
  document.getElementById('catalogAdminTable').innerHTML=items.map(x=>`<tr><td><div style="display:flex;gap:10px;align-items:center"><img class="photo-thumb" src="${x.img}" alt=""><span><strong>${x.title}</strong><small>${x.ref}</small></span></div></td><td>${x.universe}</td><td>${x.owner}</td><td class="money">${x.price}</td><td>${x.docs}</td><td>${x.availability}</td><td><span class="chip ${x.chip}">${x.status}</span></td><td><button class="btn btn-outline detail-btn" data-kind="offer" data-ref="${x.ref}">Contrôler</button></td></tr>`).join('');
  document.querySelectorAll('.photo-thumb').forEach((img,i)=>img.onerror=()=>{img.onerror=null;img.src='assets/img/fallback.svg'});
  bindDetails();
}

function renderPayments(items=payments){
  document.getElementById('paymentsTable').innerHTML=items.map(x=>`<tr><td><strong>${x.ref}</strong></td><td>${x.client}</td><td>${x.object}</td><td>${x.method}</td><td class="money">${x.amount}</td><td>${x.webhook}</td><td><span class="chip ${x.chip}">${x.status}</span></td><td class="${x.gap==='0 F'?'':'priority p1'}">${x.gap}</td><td><button class="btn btn-outline detail-btn" data-kind="payment" data-ref="${x.ref}">Ouvrir</button></td></tr>`).join('');
  bindDetails();
}

function openDrawer(kind, ref='Dossier'){
  const drawer=document.getElementById('detailDrawer');
  const title=document.getElementById('drawerTitle');
  const sub=document.getElementById('drawerSubtitle');
  const content=document.getElementById('drawerContent');
  title.textContent=kind==='payment'?'Contrôle du paiement':kind==='offer'?'Validation de l’offre':kind==='kyc'?'Dossier de conformité':kind==='reservation'?'Suivi de la réservation':'Traitement opérationnel';
  sub.textContent=ref;
  const common=`<div class="detail-grid"><div class="detail"><span>Référence</span><strong>${ref}</strong></div><div class="detail"><span>Responsable</span><strong>Agent attribué</strong></div><div class="detail"><span>Création</span><strong>24/06/2026 · 08h37</strong></div><div class="detail"><span>Journal</span><strong>6 actions enregistrées</strong></div></div>`;
  if(kind==='payment') content.innerHTML=common+`<div class="audit-note">Webhook signé à vérifier, référence PSP à rapprocher et écritures du ledger à contrôler avant confirmation.</div><div style="display:grid;gap:9px;margin-top:15px"><button class="btn btn-blue">Vérifier auprès du PSP</button><button class="btn btn-outline">Voir le journal comptable</button><button class="btn btn-red">Ouvrir un litige</button></div>`;
  else if(kind==='offer') content.innerHTML=common+`<div class="detail-grid"><div class="detail"><span>Photos</span><strong>8 reçues</strong></div><div class="detail"><span>Documents</span><strong>8/10</strong></div><div class="detail"><span>Prix</span><strong>Comparaison à faire</strong></div><div class="detail"><span>Disponibilité</span><strong>À confirmer</strong></div></div><div class="audit-note">L’agent prépare l’offre ; seul le responsable métier peut l’approuver dans son univers.</div><div style="display:flex;gap:9px;margin-top:15px"><button class="btn btn-blue">Proposer la validation</button><button class="btn btn-red">Demander des pièces</button></div>`;
  else if(kind==='kyc') content.innerHTML=common+`<div class="detail-grid"><div class="detail"><span>Identité</span><strong>Vérifiée</strong></div><div class="detail"><span>Compte de paiement</span><strong>Nom concordant</strong></div><div class="detail"><span>Contrat</span><strong>Signé</strong></div><div class="detail"><span>Assurance</span><strong>Valide</strong></div></div><div class="audit-note">La conformité peut bloquer ou suspendre, mais ne peut pas déclencher un paiement.</div>`;
  else content.innerHTML=common+`<div class="audit-note">Prioriser selon sécurité, client en déplacement, paiement non confirmé et départ sous 24 heures.</div><div style="display:flex;gap:9px;margin-top:15px"><button class="btn btn-blue">Prendre en charge</button><button class="btn btn-outline">Ajouter une note</button></div>`;
  drawer.classList.add('open');
}

function bindDetails(){document.querySelectorAll('.detail-btn').forEach(btn=>{btn.onclick=()=>openDrawer(btn.dataset.kind,btn.dataset.ref||btn.closest('tr')?.querySelector('strong')?.textContent||'Dossier')})}
document.getElementById('drawerClose').addEventListener('click',()=>document.getElementById('detailDrawer').classList.remove('open'));

function applyRole(){
  const role=document.getElementById('roleSelect').value;
  document.querySelectorAll('.role-sensitive').forEach(btn=>{
    const allowed=(btn.dataset.roles||'').split(',');btn.disabled=!allowed.includes(role);
    btn.title=btn.disabled?`Action non autorisée pour le rôle « ${role} »`:'';
  });
  const audit = role==='Auditeur';
  document.querySelectorAll('button:not(.side-link):not(#adminMenu):not(.drawer-close)').forEach(btn=>{
    if(audit && !btn.classList.contains('detail-btn') && !btn.dataset.openView) btn.disabled=true;
  });
}
document.getElementById('roleSelect').addEventListener('change',applyRole);

const opSearch=document.getElementById('opSearch');
opSearch.addEventListener('input',()=>{const q=opSearch.value.toLowerCase();renderOperations(operations.filter(x=>`${x.ref} ${x.desc} ${x.client}`.toLowerCase().includes(q)))});
const catSearch=document.getElementById('catalogAdminSearch');
catSearch.addEventListener('input',()=>{const q=catSearch.value.toLowerCase();renderCatalog(catalog.filter(x=>`${x.title} ${x.ref} ${x.owner} ${x.universe}`.toLowerCase().includes(q)))});

document.getElementById('assignTasks').addEventListener('click',e=>{e.target.textContent='Attribution effectuée ✓';setTimeout(()=>e.target.textContent='Attribuer automatiquement',1800)});
document.getElementById('reconcileAll').addEventListener('click',e=>{e.target.textContent='Rapprochement en cours…';setTimeout(()=>{e.target.textContent='3 rapprochés · 1 litige';payments[0].gap='0 F';renderPayments();},1000)});
document.getElementById('prepareBatch').addEventListener('click',()=>{document.getElementById('approveBatch').disabled=false;document.getElementById('prepareBatch').textContent='Lot préparé ✓'});
document.getElementById('approveBatch').addEventListener('click',e=>{e.target.textContent='Lot envoyé ✓';e.target.disabled=true});

renderOperations();renderCatalog();renderPayments();bindDetails();applyRole();
const initial=location.hash.replace('#','');if(titles[initial])showView(initial,false);
