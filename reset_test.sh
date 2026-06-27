#!/bin/bash
# ──────────────────────────────────────────────────────────────────
#  Reset rápido del sistema APA-UCM para testing
#  Uso: ./reset_test.sh
# ──────────────────────────────────────────────────────────────────

set -e

echo "🔄 Reseteando sistema APA-UCM para testing..."
echo ""

# 1. Verificar que docker está corriendo
if ! docker compose ps > /dev/null 2>&1; then
    echo "❌ Docker no está corriendo. Inicia Docker Desktop primero."
    exit 1
fi

# 2. Levantar contenedores si están detenidos
echo "📦 Verificando contenedores..."
docker compose up -d
sleep 3

# 3. Esperar a que PostgreSQL esté listo
echo "⏳ Esperando que PostgreSQL esté listo..."
until docker compose exec -T db pg_isready -U apa_user -d apa_ucm > /dev/null 2>&1; do
    sleep 1
done

# 4. Limpiar cachés de Laravel
echo "🧹 Limpiando cachés..."
docker compose exec -T app php artisan cache:clear > /dev/null
docker compose exec -T app php artisan config:clear > /dev/null
docker compose exec -T app php artisan view:clear > /dev/null
docker compose exec -T app php artisan route:clear > /dev/null

# 5. Migrar y poblar BD
echo "🗄️  Reseteando base de datos y aplicando seeders..."
docker compose exec -T app php artisan migrate:fresh --seed --force

# 6. Verificar resultado
echo ""
echo "📄 Generando fixture Excel/CSV de nómina SAPD..."
docker compose exec -T app php fixtures/build_nomina_csv.php 2>/dev/null || true
docker compose exec -T app php artisan nomina:generar-demo-excel 2>/dev/null || true

# 7. Verificar resultado
echo ""
echo "✅ Sistema reseteado correctamente!"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📋 Datos cargados:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

docker compose exec -T app php artisan tinker --execute="
echo 'Usuarios: ' . App\Models\User::count() . PHP_EOL;
echo 'User roles: ' . App\Models\UserRole::count() . PHP_EOL;
echo 'Comisiones CCA: ' . App\Models\ComisionCca::count() . PHP_EOL;
echo 'Facultades: ' . App\Models\Facultad::count() . PHP_EOL;
echo 'Periodos: ' . App\Models\Periodo::count() . PHP_EOL;
echo 'Semestres Académicos: ' . App\Models\SemestreAcademico::count() . PHP_EOL;
echo 'Nominas: ' . App\Models\Nomina::count() . PHP_EOL;
echo 'Nominas sin cuenta: ' . App\Models\Nomina::whereNull('user_id')->count() . PHP_EOL;
echo 'Compromisos APA: ' . App\Models\CompromisoApa::count() . PHP_EOL;
echo 'Evidencias: ' . App\Models\Evidencia::count() . PHP_EOL;
" 2>/dev/null

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "🌐 Accede al sistema en: http://localhost:8080"
echo ""
echo "👥 Institucionales (única contraseña de demo: password):"
echo "  - analista@ucm.cl     → Analista CCDA"
echo "  - vicerrectora@ucm.cl → Vicerrectoría"
echo ""
echo "👥 Personas en nómina — aún SIN cuenta de usuario:"
echo "  FCI:  maria.rodriguez, carlos.fuentes, pedro.alarcon, sandra.munoz, ana.martinez…"
echo "  FCAF: rosa.morales, jorge.silva, patricia.lagos, fernando.munoz…"
echo ""
echo "📊 Flujo de prueba:"
echo "  1. analista@ucm.cl → Comisión CCA: designar y confirmar (FCI: Pedro+Sandra; FCAF: Jorge+Fernando)"
echo "  2. analista@ucm.cl → Nómina → «Comunicar acceso»:"
echo "       · crea la cuenta de cada persona en la nómina"
echo "       · asigna perfiles (académico, secretario, jefe académico…)"
echo "       · envía correo con usuario y contraseña inicial"
echo "  3. Cada persona ingresa con las credenciales recibidas por correo"
echo "  4. Académico/Secretario → compromiso APA por semestre + evidencias"
echo "  5. carlos.fuentes@ucm.cl (Secretario) → validar expedientes"
echo "  6. docker compose exec app php artisan db:seed --class=FlujoEtapa2CcaSeeder"
echo "     (solo marca carga_cerrada quien tenga S1+S2 APA confirmados y evidencias)"
echo "  7. pedro/sandra → perfil Miembro CCA → evaluar (requiere S1+S2 completos)"
echo "  8. docker compose exec app php artisan db:seed --class=FlujoEtapa3CierreSeeder"
echo ""
echo "📧 En local, revisa el correo en Mailpit/logs si no llega al buzón real."
echo ""
echo "📎 Excel SAPD de prueba (mismo cast que el seeder):"
echo "  src/fixtures/nomina_prueba_sapd.csv   ← importar directo"
echo "  src/fixtures/nomina_prueba_sapd.xlsx  ← tras reset o: php artisan nomina:generar-demo-excel"
echo "  Horas: 40 jornada completa · 24 part-time"
echo ""
