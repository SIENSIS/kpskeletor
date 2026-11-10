/**
 * Example component of the {{Name}} module: `data-component="{{name}}/{{name}}"` in
 * Views/index.php. core/components.js imports this file on demand (import map: `{{name}}/…` =
 * modules/{{Name}}/Assets/js/…) and calls mount(element, options). Build the DOM with
 * textContent/createElement, never innerHTML with data; import other modules with bare
 * specifiers (`core/http.js`, `{{name}}/…`), never relative paths.
 */
export function mount(root, options) {
    root.textContent = String(options.message || '');
    root.hidden = false;
}
