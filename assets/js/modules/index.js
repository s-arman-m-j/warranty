// واردسازی ماژول‌ها
import WarrantyForm from './warranty-form';
import Reports from './reports';
import ErrorHandler from './error-handler';

// کلاس اصلی مدیریت اپلیکیشن
class App {
    constructor() {
        this.modules = {
            warrantyForm: new WarrantyForm(),
            reports: new Reports(),
            errorHandler: new ErrorHandler()
        };
    }

    init() {
        // راه‌اندازی مدیریت خطاها
        this.modules.errorHandler.init();

        // راه‌اندازی فرم گارانتی اگر در صفحه وجود داشت
        if (document.querySelector('.asg-form-container')) {
            this.modules.warrantyForm.init();
        }

        // راه‌اندازی گزارشات اگر در صفحه وجود داشت
        if (document.querySelector('.asg-reports-container')) {
            this.modules.reports.init();
        }
    }
}

// راه‌اندازی اپلیکیشن بعد از لود کامل صفحه
document.addEventListener('DOMContentLoaded', () => {
    const app = new App();
    app.init();
});