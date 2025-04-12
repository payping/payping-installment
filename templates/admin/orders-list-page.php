<?php
$orders_table = new \PayPingInstallment\admin\OrderList();
$orders_table->prepare_items(); ?>
<div class="wrap">
    <h1 class="wp-heading-inline">گزارش سفارشات</h1>
    
    <?php if(empty(get_option('payping_access_token'))) : ?>
        <div class="notice notice-error">
            <p>لطفا ابتدا توکن دسترسی را از بخش <a href="<?php echo admin_url('admin.php?page=payping-token'); ?>">تنظیمات توکن</a> وارد کنید</p>
        </div>
    <?php else : ?>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>">
            <?php 
            $orders_table->display();
            ?>
        </form>
    <?php endif; ?>
</div>