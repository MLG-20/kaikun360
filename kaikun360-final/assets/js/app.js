const money = new Intl.NumberFormat('fr-FR');
const fallback = () => 'assets/img/fallback.svg';

const categories = [
  {name:'Immobilier',count:42,image:'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1200&q=82',desc:'Maisons, appartements, immeubles, bureaux et terrains.'},
  {name:'Tourisme',count:28,image:'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=82',desc:'Hébergements, circuits, excursions et expériences locales.'},
  {name:'Transport',count:31,image:'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=1200&q=82',desc:'Berlines, 4×4, minibus, navettes et pirogues.'},
  {name:'Construction',count:16,image:'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1200&q=82',desc:'Pré-devis, plans, chantier, rénovation et suivi filmé.'},
  {name:'Gestion locative',count:19,image:'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=82',desc:'Loyers, états des lieux, maintenance et reporting.'},
  {name:'Team building',count:12,image:'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=1200&q=82',desc:'Cohésion, séminaires et sorties d’entreprise.'},
  {name:'Colonies',count:9,image:'https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?auto=format&fit=crop&w=1200&q=82',desc:'Séjours éducatifs, sportifs et culturels encadrés.'},
  {name:'Services',count:24,image:'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?auto=format&fit=crop&w=1200&q=82',desc:'Conciergerie, livraison et réseau de prestataires.'}
];

const listings = [
  {title:'Villa contemporaine avec piscine',city:'Saly',type:'Immobilier',price:95000000,unit:'à la vente',image:'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1200&q=82',meta:['4 chambres','Piscine','Titre vérifié']},
  {title:'Terrain 300 m² à vocation résidentielle',city:'Diamniadio',type:'Immobilier',price:18000000,unit:'à la vente',image:'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1200&q=82',meta:['300 m²','Bornage','Dossier foncier']},
  {title:'Appartement meublé centre-ville',city:'Dakar',type:'Hébergement',price:45000,unit:'par nuit',image:'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=82',meta:['2 chambres','Wi-Fi','Check-in assisté']},
  {title:'Écolodge au bord du bolong',city:'Toubacouta',type:'Tourisme',price:55000,unit:'par nuit',image:'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=82',meta:['Petit-déjeuner','Nature','Guide disponible']},
  {title:'Circuit Sine-Saloum avec guide',city:'Sine-Saloum',type:'Tourisme',price:65000,unit:'par personne',image:'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=1200&q=82',meta:['2 jours','Guide','Repas inclus']},
  {title:'Pirogue motorisée avec équipage',city:'Toubacouta',type:'Pirogue',price:80000,unit:'demi-journée',image:'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=1200&q=82',meta:['12 places','Gilets','Équipage vérifié']},
  {title:'4×4 touristique avec chauffeur',city:'Dakar',type:'4x4',price:70000,unit:'par jour',image:'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=1200&q=82',meta:['Climatisé','7 places','Assurance']},
  {title:'Berline confort pour missions',city:'Thiès',type:'Berline',price:35000,unit:'par jour',image:'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?auto=format&fit=crop&w=1200&q=82',meta:['5 places','Chauffeur optionnel','Kilométrage défini']},
  {title:'Navette AIBD – Dakar',city:'AIBD',type:'Navette',price:25000,unit:'par trajet',image:'https://images.unsplash.com/photo-1515569067071-ec3b51335dd0?auto=format&fit=crop&w=1200&q=82',meta:['Suivi de vol','Bagages','Accueil pancarte']},
  {title:'Minibus groupe 25 places',city:'Dakar',type:'Transport',price:120000,unit:'par jour',image:'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=1200&q=82',meta:['25 places','Chauffeur','Climatisation']},
  {title:'Pré-devis maison R+1',city:'Thiès',type:'Construction',price:180000,unit:'à partir de / m²',image:'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1200&q=82',meta:['Plans','Quantitatif','Suivi jalons']},
  {title:'Rénovation complète d’appartement',city:'Dakar',type:'Construction',price:0,unit:'sur devis',image:'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1200&q=82',meta:['Diagnostic','Planning','Finitions']},
  {title:'Team building mangrove & cohésion',city:'Toubacouta',type:'Team building',price:95000,unit:'par personne',image:'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=1200&q=82',meta:['2 jours','Transport','Animation']},
  {title:'Journée entreprise à Saly',city:'Saly',type:'Team building',price:45000,unit:'par personne',image:'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1200&q=82',meta:['1 journée','Déjeuner','Activités']},
  {title:'Colonie nature & patrimoine',city:'Sine-Saloum',type:'Colonies',price:120000,unit:'par séjour',image:'https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?auto=format&fit=crop&w=1200&q=82',meta:['7 jours','Encadrement','Assurance']},
  {title:'Conciergerie diaspora mensuelle',city:'Dakar',type:'Services',price:45000,unit:'par mois',image:'https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=1200&q=82',meta:['Courses','Remise de clés','Compte rendu']},
  {title:'Livraison de colis suivie',city:'Dakar',type:'Services',price:2500,unit:'à partir de',image:'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?auto=format&fit=crop&w=1200&q=82',meta:['Preuve de remise','Suivi','Assistance']},
  {title:'Gestion locative intégrale',city:'Saly',type:'Gestion locative',price:8,unit:'% des loyers encaissés',image:'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1200&q=82',meta:['Mandat','Maintenance','Reporting']}
];

const needMap = {
  Immobilier:['Acheter un bien','Louer au mois','Vendre un bien','Confier un bien en gestion'],
  Tourisme:['Réserver un hébergement','Créer un circuit','Organiser un team building','Réserver une colonie'],
  Transport:['Louer une berline','Réserver un 4×4','Navette AIBD','Bus / minibus','Pirogue'],
  Construction:['Construire une maison','Construire un immeuble','Rénover','Obtenir un pré-devis']
};

const state = {type:'',city:'',query:''};

function imageWithFallback(img, seed){
  img.onerror = () => { img.onerror = null; img.src = fallback(seed); };
}

function renderCategories(){
  const root = document.getElementById('categoryGrid');
  root.innerHTML = categories.map((c,i)=>`<a class="category-card" href="#catalogue" data-category="${c.name}"><img src="${c.image}" alt="${c.name}" loading="lazy"><span class="category-count">${c.count} offres</span><div class="category-content"><span class="eyebrow">Univers ${String(i+1).padStart(2,'0')}</span><h3>${c.name}</h3><p>${c.desc}</p></div></a>`).join('');
  root.querySelectorAll('img').forEach((img,i)=>imageWithFallback(img,`category-${i}`));
  root.querySelectorAll('[data-category]').forEach(card=>card.addEventListener('click',()=>{
    const val = card.dataset.category;
    state.type = val === 'Tourisme' ? 'Tourisme' : val;
    const typeSelect = document.getElementById('catalogType');
    if([...typeSelect.options].some(o=>o.value===state.type)) typeSelect.value = state.type;
    renderListings();
  }));
}

function populateFilters(){
  const types = [...new Set(listings.map(x=>x.type))].sort((a,b)=>a.localeCompare(b,'fr'));
  const cities = [...new Set(listings.map(x=>x.city))].sort((a,b)=>a.localeCompare(b,'fr'));
  document.getElementById('catalogType').insertAdjacentHTML('beforeend',types.map(x=>`<option>${x}</option>`).join(''));
  document.getElementById('catalogCity').insertAdjacentHTML('beforeend',cities.map(x=>`<option>${x}</option>`).join(''));
}

function formatPrice(item){
  if(item.price===0) return item.unit;
  if(item.type==='Gestion locative') return `${item.price} ${item.unit}`;
  return `${money.format(item.price)} FCFA`;
}

function renderListings(){
  const q = state.query.toLowerCase().trim();
  const filtered = listings.filter(item=>{
    const matchesQuery = !q || `${item.title} ${item.city} ${item.type} ${item.meta.join(' ')}`.toLowerCase().includes(q);
    const matchesType = !state.type || item.type === state.type || (state.type==='Tourisme' && ['Tourisme','Hébergement','Team building','Colonies'].includes(item.type)) || (state.type==='Transport' && ['Transport','Berline','4x4','Navette','Pirogue'].includes(item.type));
    const matchesCity = !state.city || item.city === state.city;
    return matchesQuery && matchesType && matchesCity;
  });
  document.getElementById('resultCount').textContent = `${filtered.length} offre${filtered.length>1?'s':''}`;
  const root = document.getElementById('listingGrid');
  if(!filtered.length){root.innerHTML='<div class="empty-state"><strong>Aucune offre ne correspond à ces critères.</strong><br>Réinitialisez les filtres ou demandez une recherche personnalisée.</div>';return;}
  root.innerHTML = filtered.map((item,i)=>`<article class="listing-card"><div class="listing-image"><img src="${item.image}" alt="${item.title}" loading="lazy"><span class="listing-label">${item.type}</span><span class="verified">✓ Vérifié</span><button class="favorite" type="button" aria-label="Ajouter aux favoris">♡</button></div><div class="listing-body"><h3>${item.title}</h3><div class="location">● ${item.city}, Sénégal</div><div class="listing-meta">${item.meta.map(m=>`<span>${m}</span>`).join('')}</div><div class="listing-foot"><div class="price"><strong>${formatPrice(item)}</strong><small>${item.price && item.type!=='Gestion locative'?item.unit:''}</small></div><button class="btn btn-blue btn-sm reserve-btn" data-title="${item.title}" data-price="${item.price}">Détails</button></div></div></article>`).join('');
  root.querySelectorAll('img').forEach((img,i)=>imageWithFallback(img,`listing-${i}`));
  root.querySelectorAll('.favorite').forEach(btn=>btn.addEventListener('click',()=>{btn.textContent=btn.textContent==='♡'?'♥':'♡';btn.style.color=btn.textContent==='♥'?'#c73b4d':'';}));
  root.querySelectorAll('.reserve-btn').forEach(btn=>btn.addEventListener('click',()=>{
    const title = btn.dataset.title;
    const price = Number(btn.dataset.price||0);
    if(price>0){location.href=`checkout.html?title=${encodeURIComponent(title)}&amount=${price}`;}
    else{showToast(`Demande de devis ouverte pour : ${title}`);document.getElementById('contact').scrollIntoView({behavior:'smooth'});}
  }));
}

function setHeroNeeds(universe){
  const select = document.getElementById('heroNeed');
  select.innerHTML = needMap[universe].map(x=>`<option>${x}</option>`).join('');
}

function showToast(message){
  const t = document.getElementById('toast');
  t.textContent=message;t.classList.add('show');clearTimeout(window.__toastTimer);window.__toastTimer=setTimeout(()=>t.classList.remove('show'),3000);
}

function setupNavigation(){
  document.querySelectorAll('.nav-group').forEach(group=>{
    const trigger=group.querySelector('.nav-trigger');
    trigger.addEventListener('click',e=>{e.stopPropagation();document.querySelectorAll('.nav-group').forEach(g=>{if(g!==group)g.classList.remove('open')});group.classList.toggle('open');});
  });
  document.addEventListener('click',()=>document.querySelectorAll('.nav-group').forEach(g=>g.classList.remove('open')));
  document.querySelectorAll('.mega-menu').forEach(m=>m.addEventListener('click',e=>e.stopPropagation()));
  document.getElementById('mobileToggle').addEventListener('click',()=>document.getElementById('mobileMenu').classList.toggle('open'));
  document.querySelectorAll('[data-type-link]').forEach(link=>link.addEventListener('click',()=>{
    const t=link.dataset.typeLink;state.type=t;document.getElementById('catalogType').value=t;renderListings();
  }));
}

function setupSearch(){
  let universe='Immobilier';setHeroNeeds(universe);
  document.querySelectorAll('.search-tab').forEach(btn=>btn.addEventListener('click',()=>{
    document.querySelectorAll('.search-tab').forEach(b=>b.classList.remove('active'));btn.classList.add('active');universe=btn.dataset.universe;setHeroNeeds(universe);
  }));
  document.getElementById('heroSearch').addEventListener('submit',e=>{
    e.preventDefault();
    state.type = universe;
    const city=document.getElementById('heroCity').value;state.city=city==='Toutes les zones'?'':city;
    document.getElementById('catalogType').value = [...document.getElementById('catalogType').options].some(o=>o.value===universe)?universe:'';
    document.getElementById('catalogCity').value = state.city;
    renderListings();document.getElementById('catalogue').scrollIntoView({behavior:'smooth'});
  });
  document.getElementById('catalogSearch').addEventListener('input',e=>{state.query=e.target.value;renderListings()});
  document.getElementById('catalogType').addEventListener('change',e=>{state.type=e.target.value;renderListings()});
  document.getElementById('catalogCity').addEventListener('change',e=>{state.city=e.target.value;renderListings()});
  document.getElementById('catalogReset').addEventListener('click',()=>{state.type='';state.city='';state.query='';document.getElementById('catalogSearch').value='';document.getElementById('catalogType').value='';document.getElementById('catalogCity').value='';renderListings()});
}

function setupPayment(){
  let method='Wave';
  const amount=document.getElementById('payAmount');
  const refresh=()=>{document.getElementById('summaryMethod').textContent=method;document.getElementById('summaryAmount').textContent=`${money.format(Number(amount.value||0))} FCFA`;};
  document.querySelectorAll('.pay-method').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.pay-method').forEach(b=>b.classList.remove('active'));btn.classList.add('active');method=btn.dataset.method;refresh()}));
  amount.addEventListener('input',refresh);
  document.getElementById('simulatePayment').addEventListener('click',()=>{
    const result=document.getElementById('paymentResult');const val=Number(amount.value||0);if(val<100){result.style.color='#c73b4d';result.textContent='Veuillez saisir un montant valide.';return;}
    result.style.color='#66738b';result.textContent='Création de la session sécurisée…';
    setTimeout(()=>{const ref=`K360-${new Date().getFullYear()}-${Math.floor(100000+Math.random()*899999)}`;result.style.color='#198754';result.innerHTML=`✓ Paiement simulé confirmé par ${method}. Référence : <strong>${ref}</strong>`;},900);
  });
}

function setupAI(){
  const panel=document.getElementById('aiPanel'),body=document.getElementById('aiBody');
  document.getElementById('aiToggle').addEventListener('click',()=>panel.classList.add('open'));
  document.getElementById('aiClose').addEventListener('click',()=>panel.classList.remove('open'));
  document.getElementById('aiForm').addEventListener('submit',e=>{
    e.preventDefault();const input=document.getElementById('aiInput');const q=input.value.trim();if(!q)return;
    body.insertAdjacentHTML('beforeend',`<div class="bubble user">${q.replace(/[<>]/g,'')}</div>`);input.value='';
    const t=q.toLowerCase();let answer='Je peux transmettre cette demande à un conseiller et préparer un devis consolidé.';
    if(/terrain|maison|appartement|villa|louer|acheter/.test(t))answer='Je vous oriente vers le catalogue Immobilier. Indiquez la ville, le budget et s’il s’agit d’un achat ou d’une location.';
    else if(/pirogue|4x4|berline|voiture|navette|aibd|bus|transport/.test(t))answer='Le catalogue Transport propose berlines, 4×4, navettes AIBD, bus, minibus et pirogues. Précisez le trajet, la date et le nombre de passagers.';
    else if(/tourisme|circuit|hôtel|hotel|nuit|hébergement|vacance/.test(t))answer='Je peux rechercher un hébergement, un circuit, un team building ou une colonie de vacances. Précisez la destination et les dates.';
    else if(/construction|chantier|devis|rénov/.test(t))answer='Pour la construction, Kaikun 360 prépare un pré-devis, puis organise les plans, jalons, preuves et validations. Indiquez la surface et la ville.';
    else if(/paiement|wave|orange|free money|carte/.test(t))answer='Les moyens prévus sont Wave, Orange Money, Free Money et carte. La confirmation réelle doit venir du PSP par webhook signé.';
    setTimeout(()=>{body.insertAdjacentHTML('beforeend',`<div class="bubble bot">${answer}</div>`);body.scrollTop=body.scrollHeight;},450);body.scrollTop=body.scrollHeight;
  });
}

document.getElementById('openContact').addEventListener('click',()=>showToast('Formulaire rapide : envoyez votre nom, téléphone et besoin via WhatsApp ou votre futur espace client.'));

renderCategories();populateFilters();renderListings();setupNavigation();setupSearch();setupPayment();setupAI();
