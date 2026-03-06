console.log('🔍 DEBUG maestro.php - Inicio');

// Verificar que SearchClient está cargado
if (typeof SearchClient === 'undefined') {
  console.error('❌ SearchClient no está definido');
} else {
  console.log('✅ SearchClient cargado');
}

// Verificar elementos DOM
document.addEventListener('DOMContentLoaded', () => {
  console.log('📦 DOMContentLoaded disparado');

  const searchInput = document.getElementById('main-search-input');
  const searchResults = document.getElementById('main-search-results');

  console.log('🔍 Elementos encontrados:');
  console.log('  - searchInput:', searchInput);
  console.log('  - searchResults:', searchResults);

  if (searchInput) {
    console.log('  - searchInput.id:', searchInput.id);
    console.log('  - searchInput.className:', searchInput.className);
    console.log('  - searchInput.placeholder:', searchInput.placeholder);
  }

  if (searchResults) {
    console.log('  - searchResults.id:', searchResults.id);
    console.log('  - searchResults.className:', searchResults.className);
  }

  // Monitorear cuando se cree mainSearchInstance
  setTimeout(() => {
    console.log('🔍 mainSearchInstance después de 1 segundo:', typeof mainSearchInstance, mainSearchInstance);

    if (mainSearchInstance) {
      console.log('✅ SearchClient instanciado');
      console.log('  - endpoint:', mainSearchInstance.endpoint);
      console.log('  - minChars:', mainSearchInstance.minChars);
      console.log('  - filters:', mainSearchInstance.filters);
    } else {
      console.error('❌ mainSearchInstance es null');
    }
  }, 1000);
});

// Interceptar errores
window.addEventListener('error', (e) => {
  console.error('💥 Error capturado:', e.message, e.filename, e.lineno);
});

// Interceptar fetch para ver las llamadas a la API
const originalFetch = window.fetch;
window.fetch = function (...args) {
  const url = args[0];
  if (typeof url === 'string' && url.includes('/api/v1/search/variantes')) {
    console.log('🌐 Fetch interceptado:', url);
    console.log('  - args:', args);
  }
  return originalFetch.apply(this, args)
    .then(response => {
      if (typeof url === 'string' && url.includes('/api/v1/search/variantes')) {
        console.log('📡 Response:', response.status, response.statusText);
        return response.clone();
      }
      return response;
    });
};

console.log('✅ Debug script cargado');
