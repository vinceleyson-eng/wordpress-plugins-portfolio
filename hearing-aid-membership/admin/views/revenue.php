<?php
if (!defined('ABSPATH')) exit;
$stats = isset($stats) ? $stats : array();
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="ham-revenue-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
        <div class="card" style="padding: 20px; text-align: center;">
            <h2 style="margin: 0; font-size: 36px; color: #2271b1;">$<?php echo isset($stats['mrr']) ? number_format($stats['mrr'], 2) : '0.00'; ?></h2>
            <p style="margin: 10px 0 0 0;">Monthly Recurring Revenue</p>
            <p style="color: #666; font-size: 12px;">From monthly subscriptions</p>
        </div>
        
        <div class="card" style="padding: 20px; text-align: center;">
            <h2 style="margin: 0; font-size: 36px; color: #2c5f5d;">$<?php echo isset($stats['arr']) ? number_format($stats['arr'], 2) : '0.00'; ?></h2>
            <p style="margin: 10px 0 0 0;">Annual Recurring Revenue</p>
            <p style="color: #666; font-size: 12px;">From yearly subscriptions</p>
        </div>
        
        <div class="card" style="padding: 20px; text-align: center;">
            <h2 style="margin: 0; font-size: 36px; color: #00a32a;">$<?php echo isset($stats['total_mrr']) ? number_format($stats['total_mrr'], 2) : '0.00'; ?></h2>
            <p style="margin: 10px 0 0 0;">Total MRR</p>
            <p style="color: #666; font-size: 12px;">MRR + ARR/12</p>
        </div>
    </div>
    
    <div class="card">
        <h2>Revenue Breakdown</h2>
        <table class="wp-list-table widefat">
            <tr>
                <th>30-Day Revenue</th>
                <td><strong>$<?php echo isset($stats['revenue_30d']) ? number_format($stats['revenue_30d'], 2) : '0.00'; ?></strong></td>
            </tr>
            <tr>
                <th>Total Active Memberships</th>
                <td><?php echo isset($stats['memberships']['total']) ? $stats['memberships']['total'] : 0; ?></td>
            </tr>
            <tr>
                <th>Preferred Members</th>
                <td><?php echo isset($stats['memberships']['preferred']) ? $stats['memberships']['preferred'] : 0; ?></td>
            </tr>
            <tr>
                <th>Verified Members</th>
                <td><?php echo isset($stats['memberships']['verified']) ? $stats['memberships']['verified'] : 0; ?></td>
            </tr>
        </table>
    </div>
</div>
