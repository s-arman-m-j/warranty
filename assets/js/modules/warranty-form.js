export default class WarrantyForm {
    constructor() {
        this.form = null;
        this.guaranteeCount = 2; // شمارنده تعداد گارانتی‌ها
        this.maxGuarantees = 10; // حداکثر تعداد گارانتی‌ها
    }

    init() {
        this.form = document.querySelector('.asg-form-container');
        if (!this.form) return;

        this.initializeSelect2();
        this.initializeDatePicker();
        this.initializeImageUpload();
        this.bindEvents();
    }

    initializeSelect2() {
        // راه‌اندازی Select2 برای انتخاب محصول
        jQuery('#product_id').select2({
            ajax: {
                url: asg_params.ajaxurl,
                dataType: 'json',
                delay: 250,
                data: (params) => ({
                    action: 'asg_search_products',
                    search: params.term,
                    page: params.page || 1
                }),
                processResults: (data, params) => ({
                    results: data,
                    pagination: {
                        more: (params.page * 30) < data.total_count
                    }
                }),
                cache: true
            },
            minimumInputLength: 2,
            placeholder: 'جستجوی محصول...',
            language: {
                noResults: () => "محصولی یافت نشد",
                searching: () => "در حال جستجو..."
            }
        });

        // مشابه برای سایر فیلدهای select2
    }

    initializeDatePicker() {
        // راه‌اندازی دیت‌پیکر فارسی
        jQuery('.asg-date-input').persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true
        });
    }

    initializeImageUpload() {
        jQuery('.asg-upload-btn').on('click', (e) => {
            e.preventDefault();
            
            const button = e.currentTarget;
            const frame = wp.media({
                title: 'انتخاب تصویر',
                button: { text: 'انتخاب' },
                multiple: false
            });

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                const previewContainer = button.nextElementSibling;
                const hiddenInput = button.previousElementSibling;

                hiddenInput.value = attachment.id;
                previewContainer.innerHTML = `
                    <div class="asg-preview-item">
                        <img src="${attachment.sizes.thumbnail.url}" alt="پیش‌نمایش">
                        <button type="button" class="button button-small asg-remove-image">حذف</button>
                    </div>
                `;
            });

            frame.open();
        });

        // حذف تصویر
        jQuery(document).on('click', '.asg-remove-image', (e) => {
            const container = e.currentTarget.closest('.asg-upload-wrapper');
            const hiddenInput = container.querySelector('input[type="hidden"]');
            const previewContainer = container.querySelector('.asg-image-preview');

            hiddenInput.value = '';
            previewContainer.innerHTML = '';
        });
    }

    bindEvents() {
        // اضافه کردن گارانتی جدید
        jQuery('#asg-add-guarantee').on('click', () => this.addNewGuarantee());

        // حذف گارانتی
        jQuery(document).on('click', '.asg-remove-guarantee', (e) => {
            const guaranteeId = jQuery(e.currentTarget).data('guarantee-id');
            this.removeGuarantee(guaranteeId);
        });

        // اعتبارسنجی فرم قبل از ارسال
        this.form.addEventListener('submit', (e) => this.validateForm(e));
    }

    addNewGuarantee() {
        if (this.guaranteeCount < this.maxGuarantees) {
            this.guaranteeCount++;
            // کد اضافه کردن HTML گارانتی جدید
            // ...
        } else {
            alert('حداکثر ۱۰ گارانتی می‌توانید ثبت کنید.');
        }
    }

    removeGuarantee(guaranteeId) {
        jQuery(`#asg-guarantee-${guaranteeId}`).remove();
        this.guaranteeCount--;
    }

    validateForm(e) {
        // اعتبارسنجی فیلدهای ضروری
        const requiredFields = this.form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('asg-error');
            } else {
                field.classList.remove('asg-error');
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('لطفاً تمام فیلدهای ضروری را پر کنید.');
        }
    }
}