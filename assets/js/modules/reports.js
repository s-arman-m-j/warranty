export default class Reports {
    constructor() {
        this.charts = {};
    }

    init() {
        if (!document.querySelector('.asg-reports-container')) return;

        this.loadChartData();
        this.initializeFilters();
        this.initializeExport();
    }

    loadChartData() {
        jQuery.ajax({
            url: asg_params.ajaxurl,
            data: {
                action: 'asg_get_stats',
                nonce: asg_params.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.initializeCharts(response.data);
                }
            },
            error: (xhr, status, error) => {
                console.error('خطا در دریافت داده‌های نمودار:', error);
            }
        });
    }

    initializeCharts(data) {
        // نمودار وضعیت‌ها
        this.charts.status = new Chart(
            document.getElementById('statusChart').getContext('2d'),
            {
                type: 'pie',
                data: {
                    labels: data.status_labels,
                    datasets: [{
                        data: data.status_counts,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            }
        );

        // نمودار ماهانه
        this.charts.monthly = new Chart(
            document.getElementById('monthlyChart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: data.monthly_labels,
                    datasets: [{
                        label: 'تعداد درخواست‌ها',
                        data: data.monthly_counts,
                        borderColor: '#36A2EB',
                        tension: 0.1,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );
    }

    initializeFilters() {
        // راه‌اندازی فیلترهای Select2
        jQuery('.asg-select2-customer, .asg-select2-tamin').select2({
            ajax: {
                url: asg_params.ajaxurl,
                dataType: 'json',
                delay: 250,
                data: (params) => ({
                    action: 'asg_search_users',
                    search: params.term,
                    page: params.page || 1
                }),
                processResults: (data, params) => ({
                    results: data,
                    pagination: {
                        more: (params.page * 30) < data.total_count
                    }
                })
            },
            minimumInputLength: 2,
            placeholder: 'جستجو...'
        });
    }

    initializeExport() {
        // مدیریت دکمه‌های خروجی
        jQuery('.export-excel').on('click', this.handleExcelExport.bind(this));
        jQuery('.print-preview').on('click', () => window.print());
    }

    handleExcelExport(e) {
        e.preventDefault();
        const button = e.currentTarget;
        button.classList.add('updating-message');
        
        // اضافه کردن پارامترهای فیلتر به URL
        const filterParams = new URLSearchParams(window.location.search);
        filterParams.append('export', 'excel');
        
        window.location.href = button.href + '&' + filterParams.toString();
        
        setTimeout(() => {
            button.classList.remove('updating-message');
        }, 2000);
    }
}