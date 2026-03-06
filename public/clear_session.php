<?php

/**
 * Limpiar sesión y forzar recarga
 */

session_start();

echo "<h2>🧹 Limpiando sesión</h2><hr>";

echo "<h3>Sesión ANTES:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Limpiar sesión
session_unset();
session_destroy();

echo "<div style='background:#fff3cd;padding:20px;border-radius:10px;margin-top:20px;'>";
echo "<h3 style='color:#856404;'>✅ Sesión limpiada</h3>";
echo "<p><strong>IMPORTANTE:</strong> Los datos del tenant quedaron en cache.</p>";
echo "<p>Debes <strong>volver a iniciar sesión</strong> para que cargue:</p>";
echo "<ul>";
echo "<li>✅ database_name = 'mrp' (actualizado)</li>";
echo "<li>✅ Tenant con configuración correcta</li>";
echo "</ul>";
echo "<p><a href='http://localhost/mrp/login' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin-top:10px;'>IR AL LOGIN</a></p>";
echo "</div>";

echo "<script>setTimeout(() => { window.location.href = 'http://localhost/mrp/login'; }, 3000);</script>";
