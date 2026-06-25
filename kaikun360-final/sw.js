const CACHE='kaikun360-v1';
const ASSETS=['./','./index.html','./admin.html','./checkout.html','./assets/css/app.css','./assets/css/admin.css','./assets/js/app.js','./assets/js/admin.js','./assets/js/checkout.js','./assets/img/icon.svg'];
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(c=>c.addAll(ASSETS))));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k))))));
self.addEventListener('fetch',event=>{if(event.request.method!=='GET')return;event.respondWith(caches.match(event.request).then(r=>r||fetch(event.request).catch(()=>caches.match('./index.html'))));});
