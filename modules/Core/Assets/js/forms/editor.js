/**
 * Rich-text editor component (stub). The editor library is vendored and wired in session 20
 * (Pages); until then the textarea stays a plain textarea.
 */
export function mount(element) {
    element.dataset.editor = 'plain';
}
