<?php

/**
 * Configuración CDN - Versiones Locales v4.1.11
 *
 * @author Sistema de Plataforma Web
 * @date 22 de julio de 2025
 * @version 1.0.0
 */

return [
    'cdn' => [
        'local_base' => '/CDN/',
        'versions' => [
            'bootstrap' => '5.3.7',
            'fontawesome' => '6.7.2',
            'alpinejs' => '3.14.9',
            'chartjs' => '4.5.0',
            'fullcalendar' => '6.1.18',
            'bootstrap_icons' => '1.13.1'
        ]
    ],
    'cache_bust' => '2025072204', // Actualizar cuando cambies versiones

    // Configuración de performance
    'performance' => [
        'enable_preload' => true,
        'enable_cache_headers' => true,
        'gzip_compression' => true,
        'expires_headers' => 2592000 // 30 días en segundos
    ],

    // Configuración de fallbacks
    'fallbacks' => [
        'enable_cdn_fallback' => false, // Solo usar CDN local
        'external_cdn_urls' => [
            'bootstrap' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css',
            'fontawesome' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
            'alpinejs' => 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js',
            'chartjs' => 'https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.min.js',
            'chartjs_adapter' => 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
            'fullcalendar' => 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.18/index.global.min.js',
            'bootstrap_icons' => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css'
        ]
    ],

    // Métricas y monitoreo
    'monitoring' => [
        'track_load_times' => true,
        'log_errors' => true,
        'performance_metrics' => true
    ]
];
