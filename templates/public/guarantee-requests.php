<?php defined('ABSPATH') || exit; ?>

<div class="asg-guarantee-requests">
    <h2>درخواست‌های گارانتی</h2>

    <?php if (!empty($requests)): ?>
        <div class="asg-guarantee-list">
            <?php foreach ($requests as $request): ?>
                <div class="asg-guarantee-item">
                    <div class="asg-guarantee-header">
                        <h3>
                            محصول: <?php echo esc_html($request->product_name); ?>
                            <span class="asg-guarantee-number">#<?php echo $request->id; ?></span>
                        </h3>
                        <span class="asg-guarantee-status asg-status-<?php echo esc_attr($request->status); ?>">
                            <?php echo esc_html($this->get_status_label($request->status)); ?>
                        </span>
                    </div>
                    <div class="asg-guarantee-content">
                        <p class="asg-guarantee-date">
                            تاریخ ثبت: <?php echo date_i18n('Y/m/d', strtotime($request->created_at)); ?>
                        </p>
                        <div class="asg-guarantee-actions">
                            <a href="<?php echo esc_url(wc_get_account_endpoint_url('view-guarantee') . $request->id); ?>" 
                               class="button">مشاهده جزئیات</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="asg-pagination">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'current' => $page,
                    'total' => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ));
                ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <p class="asg-no-requests">هیچ درخواست گارانتی ثبت نشده است.</p>
    <?php endif; ?>
</div>