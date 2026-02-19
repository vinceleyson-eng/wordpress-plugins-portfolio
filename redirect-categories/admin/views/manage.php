<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows      = RC_Database::get_all();
$edit_id   = isset( $_GET['edit'] ) ? ( 'new' === $_GET['edit'] ? 'new' : absint( $_GET['edit'] ) ) : 0;
$edit_row  = ( $edit_id && 'new' !== $edit_id ) ? RC_Database::get_by_id( $edit_id ) : null;
$status    = sanitize_text_field( wp_unslash( $_GET['rc_status']  ?? '' ) );
$message   = sanitize_text_field( rawurldecode( wp_unslash( $_GET['rc_message'] ?? '' ) ) );
$base_url  = admin_url( 'admin.php?page=rc-redirects' );

// Count rows by match_status for the summary bar.
$counts = array( 'pending' => 0, 'suggested' => 0, 'auto_matched' => 0, 'manual' => 0 );
foreach ( $rows as $r ) {
	if ( isset( $counts[ $r->match_status ] ) ) {
		$counts[ $r->match_status ]++;
	}
}
?>
<div class="wrap rc-wrap">
	<h1 class="wp-heading-inline">Category Redirects</h1>
	<a href="<?php echo esc_url( add_query_arg( 'edit', 'new', $base_url ) ); ?>" class="page-title-action">Add New</a>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
		<input type="hidden" name="action" value="rc_sync">
		<?php wp_nonce_field( 'rc_sync' ); ?>
		<button type="submit" class="page-title-action rc-sync-btn">&#8635; Sync Categories</button>
	</form>

	<hr class="wp-header-end">

	<?php if ( $status && $message ) : ?>
		<div class="notice notice-<?php echo 'success' === $status ? 'success' : 'error'; ?> is-dismissible">
			<p><?php echo wp_kses( $message, array( 'strong' => array() ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php /* Summary bar */ ?>
	<?php if ( ! empty( $rows ) ) : ?>
	<div class="rc-summary-bar">
		<span class="rc-sum-item"><span class="rc-badge rc-badge-auto-matched">Auto-Matched</span> <?php echo (int) $counts['auto_matched']; ?></span>
		<span class="rc-sum-item"><span class="rc-badge rc-badge-suggested">Suggested</span> <?php echo (int) $counts['suggested']; ?></span>
		<span class="rc-sum-item"><span class="rc-badge rc-badge-manual">Manual</span> <?php echo (int) $counts['manual']; ?></span>
		<?php if ( $counts['pending'] ) : ?>
		<span class="rc-sum-item"><span class="rc-badge rc-badge-pending">Needs URL</span> <?php echo (int) $counts['pending']; ?></span>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php /* ----------------------------------------------------------------
		   Add / Edit form
	   ---------------------------------------------------------------- */ ?>
	<?php if ( $edit_id ) :
		$is_new       = 'new' === $_GET['edit'];
		$form_action  = $is_new ? 'rc_add' : 'rc_update';
		$nonce_action = $is_new ? 'rc_add_redirect' : 'rc_update_redirect';
		$slug         = $edit_row ? esc_attr( $edit_row->category_slug )   : '';
		$url          = $edit_row ? esc_attr( $edit_row->destination_url ) : '';
		$code         = $edit_row ? (int) $edit_row->redirect_code         : 301;
		$enabled      = $edit_row ? (bool) $edit_row->enabled              : true;
	?>
	<div class="rc-form-box">
		<h2><?php echo $is_new ? 'Add New Redirect' : 'Edit Redirect'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $form_action ); ?>">
			<?php if ( ! $is_new && $edit_row ) : ?>
				<input type="hidden" name="id" value="<?php echo absint( $edit_row->id ); ?>">
			<?php endif; ?>
			<?php wp_nonce_field( $nonce_action ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rc-slug">Category Slug</label></th>
					<td>
						<input type="text" id="rc-slug" name="category_slug"
							value="<?php echo $slug; ?>"
							placeholder="e.g. hearing"
							class="regular-text"
							required>
						<p class="description">The WP category slug (e.g. <code>hearing</code> for <code>/category/hearing/</code>).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rc-url">Destination URL</label></th>
					<td>
						<input type="text" id="rc-url" name="destination_url"
							value="<?php echo $url; ?>"
							placeholder="e.g. /hearing-services/"
							class="regular-text">
						<p class="description">Relative (<code>/page/</code>) or absolute URL to redirect to.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rc-code">Redirect Type</label></th>
					<td>
						<select id="rc-code" name="redirect_code">
							<option value="301" <?php selected( $code, 301 ); ?>>301 — Permanent (recommended)</option>
							<option value="302" <?php selected( $code, 302 ); ?>>302 — Temporary</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Status</th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( $enabled ); ?>>
							Active — redirect is live
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php echo $is_new ? 'Add Redirect' : 'Save Changes'; ?>
				</button>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button">Cancel</a>
			</p>
		</form>
	</div>
	<?php endif; ?>

	<?php /* ----------------------------------------------------------------
		   Redirects table
	   ---------------------------------------------------------------- */ ?>
	<table class="wp-list-table widefat fixed striped rc-table">
		<thead>
			<tr>
				<th style="width:18%">Category Slug</th>
				<th style="width:38%">Destination URL</th>
				<th style="width:8%">Type</th>
				<th style="width:16%">Match</th>
				<th style="width:8%">Active</th>
				<th style="width:12%">Actions</th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $rows ) ) : ?>
			<tr>
				<td colspan="6">No redirects yet. Click <strong>Sync Categories</strong> to auto-detect and match your categories.</td>
			</tr>
		<?php else : ?>
			<?php foreach ( $rows as $row ) :
				$pending    = empty( $row->destination_url );
				$is_active  = ! $pending && (bool) $row->enabled;
				$row_class  = $pending ? 'rc-row-pending' : '';

				$delete_url = wp_nonce_url(
					add_query_arg( array( 'action' => 'rc_delete', 'id' => $row->id ), admin_url( 'admin-post.php' ) ),
					'rc_delete_' . $row->id
				);
				$edit_url   = add_query_arg( 'edit', $row->id, $base_url );

				// Match status badge.
				$badge_map = array(
					'auto_matched' => array( 'class' => 'rc-badge-auto-matched', 'label' => 'Auto-Matched' ),
					'suggested'    => array( 'class' => 'rc-badge-suggested',    'label' => 'Suggested'    ),
					'manual'       => array( 'class' => 'rc-badge-manual',       'label' => 'Manual'       ),
					'pending'      => array( 'class' => 'rc-badge-pending',      'label' => 'Needs URL'    ),
				);
				$ms    = $row->match_status ?? 'pending';
				$badge = $badge_map[ $ms ] ?? $badge_map['pending'];
			?>
			<tr class="<?php echo esc_attr( $row_class ); ?>">
				<td><strong><?php echo esc_html( $row->category_slug ); ?></strong></td>
				<td>
					<?php if ( $pending ) : ?>
						<em class="rc-no-url">— not set —</em>
					<?php else : ?>
						<a href="<?php echo esc_url( $row->destination_url ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $row->destination_url ); ?>
						</a>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $row->redirect_code ); ?></td>
				<td>
					<span class="rc-badge <?php echo esc_attr( $badge['class'] ); ?>">
						<?php echo esc_html( $badge['label'] ); ?>
					</span>
				</td>
				<td>
					<?php if ( $is_active ) : ?>
						<span class="rc-badge rc-badge-active">&#10003; On</span>
					<?php else : ?>
						<span class="rc-badge rc-badge-off">Off</span>
					<?php endif; ?>
				</td>
				<td>
					<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">Edit</a>
					<a href="<?php echo esc_url( $delete_url ); ?>"
					   class="button button-small rc-btn-delete"
					   onclick="return confirm('Delete this redirect?')">Delete</a>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<p class="rc-footer">
		<strong><?php echo count( $rows ); ?></strong> redirect<?php echo count( $rows ) !== 1 ? 's' : ''; ?> total.
		<?php if ( $counts['pending'] ) : ?>
			<strong class="rc-needs-url"><?php echo $counts['pending']; ?> still need<?php echo 1 === $counts['pending'] ? 's' : ''; ?> a destination URL.</strong>
		<?php endif; ?>
	</p>
</div>
