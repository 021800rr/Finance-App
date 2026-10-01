import { startStimulusApp } from '@symfony/stimulus-bridge';

export const app = startStimulusApp();

const controllersContext = require.context('./controllers', true, /_controller\.js$/);

controllersContext.keys().forEach((filePath) => {
    // filePath przykładowo: './contractor_autocomplete_controller.js' lub './nested/foo_controller.js'
    const module = controllersContext(filePath);
    const controller = module.default;

    if (!controller) {
        // Pomijamy pliki bez eksportu default
        return;
    }

    // Wyciągamy nazwę pliku bez katalogu i rozszerzenia
    let fileName = filePath.replace(/^\.\//, '').replace(/\.js$/, ''); // e.g. 'contractor_autocomplete_controller'
    // Usuń końcówkę _controller jeśli występuje
    fileName = fileName.replace(/_controller$/, '');
    // Zamień '/' na '-' (jeśli masz podfoldery) oraz '_' na '-'
    const controllerName = fileName.replace(/\//g, '-').replace(/_/g, '-');

    // Rejestrujemy pod nazwą np. 'contractor-autocomplete'
    app.register(controllerName, controller);
});
