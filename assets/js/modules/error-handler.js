export default class ErrorHandler {
    constructor() {
        this.errors = [];
    }

    init() {
        window.addEventListener('error', this.handleError.bind(this));
        window.addEventListener('unhandledrejection', this.handlePromiseError.bind(this));
    }

    handleError(event) {
        const error = {
            message: event.message,
            filename: event.filename,
            lineNumber: event.lineno,
            timestamp: new Date().toISOString()
        };

        this.logError(error);
        this.showErrorMessage(error.message);
    }

    handlePromiseError(event) {
        const error = {
            message: event.reason.message || 'خطای ناشناخته در Promise',
            timestamp: new Date().toISOString()
        };

        this.logError(error);
        this.showErrorMessage(error.message);
    }

    logError(error) {
        this.errors.push(error);
        
        // ارسال خطا به سرور برای لاگ
        if (typeof asg_params !== 'undefined') {
            jQuery.post(asg_params.ajaxurl, {
                action: 'asg_log_error',
                nonce: asg_params.nonce,
                error: JSON.stringify(error)
            });
        }

        console.error('ASG Error:', error);
    }

    showErrorMessage(message) {
        // نمایش پیام خطا به کاربر
        const errorDiv = document.createElement('div');
        errorDiv.className = 'asg-error-message';
        errorDiv.innerHTML = `
            <div class="asg-error-content">
                <p>${message}</p>
                <button type="button" class="asg-error-close">&times;</button>
            </div>
        `;

        document.body.appendChild(errorDiv);

        // حذف پیام خطا بعد از 5 ثانیه
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);

        // دکمه بستن
        errorDiv.querySelector('.asg-error-close').addEventListener('click', () => {
            errorDiv.remove();
        });
    }
}