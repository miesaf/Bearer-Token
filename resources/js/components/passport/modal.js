import { Modal } from 'bootstrap';

/**
 * Show, hide and focus Bootstrap 5 modals by element id
 * (the Passport 9 components did this with jQuery).
 */
export function showModal(id) {
    Modal.getOrCreateInstance(document.getElementById(id)).show();
}

export function hideModal(id) {
    Modal.getOrCreateInstance(document.getElementById(id)).hide();
}

export function focusOnShown(modalId, inputId) {
    document.getElementById(modalId).addEventListener('shown.bs.modal', () => {
        document.getElementById(inputId).focus();
    });
}

/**
 * Turn a failed request into the list of messages the forms show.
 */
export function errorMessages(error) {
    const data = error.response && error.response.data;

    if (data && typeof data === 'object' && data.errors) {
        return Object.values(data.errors).flat();
    }

    return ['Something went wrong. Please try again.'];
}
