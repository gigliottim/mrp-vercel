<?php

/**
 * Configuración CDN - Migración a CDN Online
 *
 * @author Sistema de Plataforma Web
 * @date 3 de abril de 2026
 * @version 2.0.0
 *
 * Esta configuración utiliza CDN online (jsDelivr, Cloudflare) en lugar de archivos locales.
 * La carpeta public/CDN ha sido eliminada y todos los assets se sirven desde CDN.
 */

return [
    'cdn' => [
        'local_base' => '/CDN/', // Mantenido para compatibilidad, pero ya no se usa (CDN online)
        'versions' => [
            'bootstrap' => '5.3.7', // Última versión estable
            'fontawesome' => '6.7.2', // Última versión estable
            'alpinejs' => '3.14.9', // Última versión estable
            'chartjs' => '4.5.0', // Última versión estable
            'fullcalendar' => '6.1.18', // Última versión estable
            'bootstrap_icons' => '1.13.1' // Última versión estable
        ]
    ],
    'cache_bust' => '2026040301', // Actualizar cuando cambies versiones (CDN online maneja esto automáticamente)

    // Configuración de performance (CDN online maneja esto automáticamente)
    'performance' => [
        'enable_preload' => false, // CDN online ya hace preload
        'enable_cache_headers' => false, // CDN online maneja cache headers
        'gzip_compression' => false, // CDN online comprime automáticamente
        'expires_headers' => 0 // CDN online maneja expires headers
    ],

    // Configuración de fallbacks (CDN online por defecto)
    'fallbacks' => [
        'enable_cdn_fallback' => true, // Usar CDN online por defecto
        'external_cdn_urls' => [
            'bootstrap' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css',
            'bootstrap_js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js',
            'fontawesome' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
            'alpinejs' => 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js',
            'chartjs' => 'https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.min.js',
            'chartjs_adapter' => 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
            'fullcalendar' => 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.18/index.global.min.js',
            'bootstrap_icons' => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css'
        ]
    ],

    // Métricas y monitoreo (CDN online maneja esto automáticamente)
    'monitoring' => [
        'track_load_times' => false, // CDN online maneja esto
        'log_errors' => false, // CDN online maneja logs
        'performance_metrics' => false // CDN online maneja métricas
    ]
];
