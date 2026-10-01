import { startStimulusApp } from '@symfony/stimulus-bridge';

export const app = startStimulusApp();

const controllersContext = require.context('./controllers', true, /_controller\.js$/);

controllersContext.keys().forEach((filePath) => {
    // filePath example: './contractor_autocomplete_controller.js' or './nested/foo_controller.js'
    const module = controllersContext(filePath);
    const controller = module.default;

    if (!controller) {
        // Skip files without a default export
        return;
    }

    // Extract file name without directory and extension
    let fileName = filePath.replace(/^\.\//, '').replace(/\.js$/, ''); // e.g. 'contractor_autocomplete_controller'
    // Strip the '_controller' suffix if present
    fileName = fileName.replace(/_controller$/, '');
    // Replace '/' with '-' (for subfolders) and '_' with '-'
    const controllerName = fileName.replace(/\//g, '-').replace(/_/g, '-');

    // Register under name e.g. 'contractor-autocomplete'
    app.register(controllerName, controller);
});
