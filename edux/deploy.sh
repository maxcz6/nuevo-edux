#!/bin/bash
# ============================================================
# deploy.sh — Script de despliegue a producción
# Uso:  bash deploy.sh [--skip-build] [--skip-migrate]
#
# Prerrequisitos:
#   - Docker Engine >= 24 con el plugin Compose v2
#   - Archivo .env.prod existente y configurado
#   - Ejecutar desde la raíz del proyecto
# ============================================================

set -euo pipefail

# ── Colores ──────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log()   { echo -e "${BLUE}[DEPLOY]${NC} $*"; }
ok()    { echo -e "${GREEN}[  OK  ]${NC} $*"; }
warn()  { echo -e "${YELLOW}[ WARN ]${NC} $*"; }
error() { echo -e "${RED}[ERROR ]${NC} $*"; exit 1; }

COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.prod"
SKIP_BUILD=false
SKIP_MIGRATE=false

# ── Parsear argumentos ────────────────────────────────────────
for arg in "$@"; do
  case $arg in
    --skip-build)   SKIP_BUILD=true ;;
    --skip-migrate) SKIP_MIGRATE=true ;;
    *) warn "Argumento desconocido: $arg" ;;
  esac
done

# ── Validaciones previas ──────────────────────────────────────
log "Verificando prerrequisitos..."

command -v docker >/dev/null 2>&1 || error "Docker no está instalado."
docker compose version >/dev/null 2>&1 || error "Docker Compose v2 no está disponible."

[[ -f "$COMPOSE_FILE" ]] || error "No se encontró $COMPOSE_FILE. Ejecuta desde la raíz del proyecto."
[[ -f "$ENV_FILE"     ]] || error "No se encontró $ENV_FILE. Copia .env.prod.example y configúralo."

ok "Prerrequisitos OK"

# ── Registro de inicio ────────────────────────────────────────
DEPLOY_START=$(date +%s)
log "Inicio del deploy: $(date '+%Y-%m-%d %H:%M:%S')"

# ── 1. Build de imágenes ──────────────────────────────────────
if [[ "$SKIP_BUILD" == false ]]; then
  log "Construyendo imágenes Docker (--no-cache)..."
  docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" build --no-cache --parallel
  ok "Imágenes construidas"
else
  warn "Omitiendo build (--skip-build)"
fi

# ── 2. Levantar servicios de infraestructura primero ──────────
log "Levantando base de datos y Redis..."
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" up -d database redis

log "Esperando que la base de datos esté lista (hasta 60s)..."
timeout 60 bash -c \
  "until docker compose -f $COMPOSE_FILE --env-file $ENV_FILE exec -T database \
     pg_isready -U \"\${POSTGRES_USER:-postgres}\" 2>/dev/null; do
     echo -n '.'; sleep 2;
   done" && echo ""
ok "Base de datos lista"

# ── 3. Levantar todos los servicios ──────────────────────────
log "Levantando todos los servicios..."
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" up -d --remove-orphans
ok "Servicios iniciados"

# ── 4. Esperar que el backend esté listo ──────────────────────
log "Esperando que el backend esté listo (hasta 90s)..."
timeout 90 bash -c \
  "until docker compose -f $COMPOSE_FILE --env-file $ENV_FILE \
     exec -T backend php -r 'echo \"ok\";' 2>/dev/null | grep -q ok; do
     echo -n '.'; sleep 3;
   done" && echo ""
ok "Backend listo"

# ── 5. Migraciones ────────────────────────────────────────────
if [[ "$SKIP_MIGRATE" == false ]]; then
  log "Ejecutando migraciones de base de datos..."
  docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" \
    exec -T backend php artisan migrate --force
  ok "Migraciones completadas"
else
  warn "Omitiendo migraciones (--skip-migrate)"
fi

# ── 6. Caches de Laravel ──────────────────────────────────────
log "Generando caché de configuración..."
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" \
  exec -T backend php artisan config:cache
ok "config:cache"

log "Generando caché de rutas..."
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" \
  exec -T backend php artisan route:cache
ok "route:cache"

log "Generando caché de vistas..."
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" \
  exec -T backend php artisan view:cache
ok "view:cache"

log "Generando caché de eventos..."
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" \
  exec -T backend php artisan event:cache
ok "event:cache"

# ── 7. Crear symlink storage (si no existe) ───────────────────
log "Asegurando symlink de storage..."
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" \
  exec -T backend php artisan storage:link --quiet 2>/dev/null || true
ok "Storage symlink OK"

# ── 8. Estado final ───────────────────────────────────────────
DEPLOY_END=$(date +%s)
ELAPSED=$(( DEPLOY_END - DEPLOY_START ))

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}   ✅  Deploy completo en ${ELAPSED}s${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
log "Estado de los contenedores:"
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps
