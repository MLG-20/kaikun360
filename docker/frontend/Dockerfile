# Construit puis sert le site Angular en SSR (obligatoire depuis F9.1 — un
# `dist/` statique perdrait le rendu serveur et le référencement).
FROM node:24-alpine AS build

WORKDIR /app

# Contexte de build = racine du dépôt (voir docker-compose.yml).
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .
RUN npm run build -- --configuration production

# ---- Image d'exécution : seul le résultat du build voyage jusqu'ici -------
FROM node:24-alpine AS runtime

WORKDIR /app
ENV NODE_ENV=production
COPY --from=build /app/dist/kaikun360 ./dist/kaikun360

# `server.mjs` charge son propre `package.json` (résolution d'`express`) :
# on installe les dépendances de production dans l'image finale plutôt que de
# copier le node_modules complet du stage de build (qui contient aussi les
# outils Angular CLI, inutiles à l'exécution).
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --omit=dev

EXPOSE 4000
USER node

CMD ["node", "dist/kaikun360/server/server.mjs"]
