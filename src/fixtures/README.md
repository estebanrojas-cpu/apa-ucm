# Nómina SAPD de prueba

Mismo cast que `PeriodoBaseSeeder` (18 personas: 12 FCI + 6 FCAF).

## Archivos

| Archivo | Uso |
|---------|-----|
| `nomina_prueba_sapd.csv` | Importar ya (también abre en Excel) |
| `nomina_prueba_sapd.xlsx` | Generar con artisan (formato nativo Excel) |

## Regenerar

```bash
docker compose exec app php fixtures/build_nomina_csv.php
docker compose exec app php artisan nomina:generar-demo-excel
```

`reset_test.sh` ejecuta ambos al final del reset.

## Importar

1. Ingresar como `analista@ucm.cl` (password: `password`).
2. Período activo → **Nómina** → subir `.csv` o `.xlsx`.
3. Confirmar auto-mapeo SAPD (`Categoría 2026`, calificaciones 2024/2025).
4. Mapear **email_ucm** como columna adicional (conserva el correo demo al comunicar acceso).
5. Seleccionar facultad **Ciencias de la Ingeniería** (FCI) o **Ciencias Agrarias y Forestales** (FCAF), o importar por separado.

Para probar solo la subida: crea un período vacío (sin seed de nómina) e importa el archivo completo.

## Horas de contrato

| Tipo en Excel | Horas semestre |
|---------------|----------------|
| Académico Jornada Completa | **40** |
| Académico Media Jornada / Hora | **24** |

El seeder aplica la misma regla vía `CastHorasContrato`.
