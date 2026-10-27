<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SecureAccess - Portal</title>
    <style>
        body, html { margin: 0; padding: 0; overflow: hidden; background-color: #f3f4f6; }
        /* Usamos 100vw (ancho de ventana) y 100vh (alto de ventana) */
        iframe { width: 100vw; height: 100vh; border: none; display: block; }
    </style>
</head>
<body>
    <iframe src="{{ route('dashboard') }}"></iframe>
</body>
</html>