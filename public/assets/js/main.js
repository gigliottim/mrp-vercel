document.addEventListener('DOMContentLoaded', () => {
    import('./modules/app.js')
        .then(({ AppShell }) => {
            new AppShell();
        })
        .catch(error => console.error('Error al cargar módulos:', error));
});

