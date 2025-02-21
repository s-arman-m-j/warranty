import ErrorHandler from './error-handler';

class WarrantyForm {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        this.init();
    }

    init() {
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitForm();
        });
    }

    submitForm() {
        const formData = new FormData(this.form);
        fetch('/api/submit-warranty', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Warranty submitted successfully.');
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => ErrorHandler.handlePromise(Promise.reject(error)));
    }
}

export default WarrantyForm;