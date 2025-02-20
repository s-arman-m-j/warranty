# ASG API Documentation

این مستندات به شما کمک می‌کند تا از API موجود در افزونه استفاده کنید. این API به شما امکان می‌دهد تا با استفاده از درخواست‌های HTTP به داده‌های گارانتی دسترسی داشته باشید و عملیات مختلفی را انجام دهید.

## Endpoints

### 1. دریافت داده‌ها

**URL:** `/wp-admin/admin-ajax.php?action=asg_action&action_type=get_data`

**Method:** `POST`

**Parameters:**
- `nonce`: نانس برای اعتبارسنجی درخواست (الزامی)
- `action_type`: نوع عملیات که باید `get_data` باشد (الزامی)

**Response:**
- `success`: اگر درخواست موفقیت‌آمیز باشد، `true` است.
- `data`: داده‌های درخواست شده.

**Example:**
```bash
curl -X POST -d "nonce=YOUR_NONCE&action=asg_action&action_type=get_data" https://example.com/wp-admin/admin-ajax.php
```

**Response Example:**
```json
{
  "success": true,
  "data": {
    "key": "value"
  }
}
```

### 2. ارسال فرم

**URL:** `/wp-admin/admin-ajax.php?action=asg_action&action_type=submit_form`

**Method:** `POST`

**Parameters:**
- `nonce`: نانس برای اعتبارسنجی درخواست (الزامی)
- `action_type`: نوع عملیات که باید `submit_form` باشد (الزامی)
- سایر پارامترهای فرم که ممکن است نیاز باشد (اختیاری)

**Response:**
- `success`: اگر درخواست موفقیت‌آمیز باشد، `true` است.
- `message`: پیام موفقیت‌آمیز.

**Example:**
```bash
curl -X POST -d "nonce=YOUR_NONCE&action=asg_action&action_type=submit_form&param1=value1&param2=value2" https://example.com/wp-admin/admin-ajax.php
```

**Response Example:**
```json
{
  "success": true,
  "message": "Form submitted successfully"
}
```

## Error Handling

در صورت رخ دادن خطا، پاسخ شامل `success: false` و یک پیام خطا (در فیلد `message`) خواهد بود.

**Example Error Response:**
```json
{
  "success": false,
  "message": "Invalid nonce"
}
```

## امنیت

برای اطمینان از امنیت درخواست‌ها، از نانس (nonce) استفاده می‌شود. نانس باید در هر درخواست ارسال شود تا معتبر بودن درخواست تأیید شود.

## پیاده‌سازی در کد

### مثال از استفاده از API در جاوااسکریپت

```javascript
function fetchData() {
    const nonce = asg_params.nonce; // دریافت نانس از پارامترهای موجود
    fetch('/wp-admin/admin-ajax.php?action=asg_action&action_type=get_data', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `nonce=${nonce}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Data:', data.data);
        } else {
            console.error('Error:', data.message);
        }
    })
    .catch(error => console.error('Fetch Error:', error));
}

function submitForm(formData) {
    const nonce = asg_params.nonce; // دریافت نانس از پارامترهای موجود
    fetch('/wp-admin/admin-ajax.php?action=asg_action&action_type=submit_form', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `nonce=${nonce}&${new URLSearchParams(formData).toString()}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Message:', data.message);
        } else {
            console.error('Error:', data.message);
        }
    })
    .catch(error => console.error('Fetch Error:', error));
}
```

### نتیجه‌گیری

این مستندات به شما کمک می‌کند تا از API موجود در افزونه برای دسترسی به داده‌های گارانتی و انجام عملیات مختلف استفاده کنید. اطمینان حاصل کنید که درخواست‌های شما معتبر و امن هستند و از نانس برای اعتبارسنجی استفاده کنید.