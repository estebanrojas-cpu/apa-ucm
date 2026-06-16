<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Credenciales de acceso — UCM</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6; background: #f4f6fb;">
    <div style="max-width: 580px; margin: 40px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">

        <!-- Header -->
        <div style="background: #1B2D6B; padding: 28px 32px;">
            <h1 style="color: #fff; font-size: 18px; margin: 0;">Universidad Católica del Maule</h1>
            <p style="color: #a8c0e8; font-size: 13px; margin: 4px 0 0;">Sistema de Gestión de Calificación Académica</p>
        </div>

        <!-- Body -->
        <div style="padding: 32px;">
            <p style="margin-top: 0;">Estimado/a <strong>{{ $nombreAcademico }}</strong>,</p>

            <p>
                Has sido incorporado/a al proceso de <strong>Calificación Académica Docente (APA)</strong>
                de la Universidad Católica del Maule. A continuación encontrarás tus credenciales
                de acceso al sistema:
            </p>

            <!-- Credentials box -->
            <div style="background: #f0f4ff; border-left: 4px solid #1B2D6B; border-radius: 0 8px 8px 0; padding: 20px 24px; margin: 24px 0;">
                <table style="width: 100%; font-size: 15px;">
                    <tr>
                        <td style="color: #555; padding-bottom: 10px; width: 130px;">Correo de acceso:</td>
                        <td style="font-weight: bold; color: #1B2D6B; padding-bottom: 10px;">{{ $emailAcceso }}</td>
                    </tr>
                    <tr>
                        <td style="color: #555;">Contraseña inicial:</td>
                        <td style="font-weight: bold; color: #1B2D6B; font-size: 18px; letter-spacing: 2px;">{{ $passwordInicial }}</td>
                    </tr>
                </table>
            </div>

            <p>
                <a href="{{ config('app.url') }}"
                   style="display: inline-block; background: #1B2D6B; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px;">
                    Ingresar al sistema →
                </a>
            </p>

            <p style="font-size: 13px; color: #888; border-top: 1px solid #eee; padding-top: 16px; margin-top: 24px;">
                Por seguridad, te recomendamos cambiar tu contraseña después del primer ingreso.<br>
                Si tienes problemas para acceder, contacta a la secretaría de tu facultad.
            </p>
        </div>

        <!-- Footer -->
        <div style="background: #f9fafb; border-top: 1px solid #eee; padding: 16px 32px; font-size: 11px; color: #aaa;">
            Vicerrectoría Académica — UCM &nbsp;·&nbsp; Este es un mensaje automático, por favor no responda.
        </div>
    </div>
</body>
</html>
