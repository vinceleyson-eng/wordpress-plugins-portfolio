<?php
if (!defined('ABSPATH')) exit;
$transactions = isset($transactions) ? $transactions : array();
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <?php if (empty($transactions)): ?>
            <p>No transactions found yet.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('M d, Y g:i A', strtotime($transaction->created_at)); ?></td>
                            <td>User #<?php echo $transaction->user_id; ?></td>
                            <td>$<?php echo number_format($transaction->amount, 2); ?></td>
                            <td><?php echo ucfirst($transaction->transaction_type); ?></td>
                            <td><?php echo ucfirst($transaction->status); ?></td>
                            <td><?php echo esc_html($transaction->description); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
