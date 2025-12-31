<?php
/**
 * Scanner View
 */
?>
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Project Scanner</h1>
        <p class="page-subtitle">
            Scanned <?= $stats['articles_scanned'] ?> articles, <?= $stats['images_scanned'] ?> images, <?= $stats['data_files_scanned'] ?> data files
        </p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="help.php" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            View Guidelines
        </a>
        <a href="scanner.php" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <path d="M23 4v6h-6"/>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
            Re-scan
        </a>
    </div>
</div>

<!-- Rules Reference -->
<div class="card" style="margin-bottom: 24px; background: var(--bg-tertiary);">
    <div style="display: flex; gap: 24px; flex-wrap: wrap; font-size: 12px;">
        <div>
            <span style="color: var(--text-muted);">Valid Sections:</span>
            <span style="color: var(--text-primary);"><?= implode(', ', $RULES['valid_sections']) ?></span>
        </div>
        <div>
            <span style="color: var(--text-muted);">Max Image Size:</span>
            <span style="color: var(--text-primary);"><?= format_size($RULES['max_image_size']) ?></span>
        </div>
        <div>
            <span style="color: var(--text-muted);">Max Description:</span>
            <span style="color: var(--text-primary);"><?= $RULES['max_description_length'] ?> chars</span>
        </div>
        <div>
            <span style="color: var(--text-muted);">Image Formats:</span>
            <span style="color: var(--text-primary);"><?= implode(', ', $RULES['valid_image_extensions']) ?></span>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-3" style="gap: 20px; margin-bottom: 32px;">
    <div class="stat-card" style="border-left: 4px solid <?= $total_issues > 0 ? '#e11d48' : '#10b981' ?>;">
        <div class="stat-icon" style="background: <?= $total_issues > 0 ? 'linear-gradient(135deg, #e11d48, #be123c)' : 'linear-gradient(135deg, #10b981, #059669)' ?>;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <?php if ($total_issues > 0): ?>
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
                <?php else: ?>
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
                <?php endif; ?>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= $total_issues ?></div>
            <div class="stat-label">Errors</div>
        </div>
    </div>
    
    <div class="stat-card" style="border-left: 4px solid <?= $total_warnings > 0 ? '#f59e0b' : '#10b981' ?>;">
        <div class="stat-icon" style="background: <?= $total_warnings > 0 ? 'linear-gradient(135deg, #f59e0b, #d97706)' : 'linear-gradient(135deg, #10b981, #059669)' ?>;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= $total_warnings ?></div>
            <div class="stat-label">Warnings</div>
        </div>
    </div>
    
    <div class="stat-card" style="border-left: 4px solid #3b82f6;">
        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= $total_info ?></div>
            <div class="stat-label">Info</div>
        </div>
    </div>
</div>

<?php if ($total_issues === 0 && $total_warnings === 0): ?>
<!-- All Good -->
<div class="card" style="text-align: center; padding: 60px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="width: 64px; height: 64px; margin: 0 auto 20px;">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
    <h2 style="color: #10b981; margin-bottom: 8px;">All Clear!</h2>
    <p style="color: var(--text-secondary);">No errors or warnings. Your project structure is looking great!</p>
</div>
<?php else: ?>

<!-- Errors -->
<?php if ($total_issues > 0): ?>
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px; color: #e11d48;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline; vertical-align: middle; margin-right: 8px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        Errors (<?= $total_issues ?>) — Must Fix
    </h2>
    
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($issues as $issue): ?>
        <div style="background: rgba(225, 29, 72, 0.05); border: 1px solid rgba(225, 29, 72, 0.2); border-radius: 8px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 16px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($issue['message']) ?></div>
                    <div style="font-size: 12px; font-family: monospace; color: var(--text-secondary);">
                        <?= htmlspecialchars($issue['path']) ?>
                        <?php if (!empty($issue['detail'])): ?>
                        <br><span style="color: #e11d48;"><?= htmlspecialchars($issue['detail']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">
                    <?= htmlspecialchars($issue['type']) ?>
                </div>
            </div>
            <?php if (!empty($issue['fix'])): ?>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(225, 29, 72, 0.1); font-size: 12px;">
                <strong style="color: #10b981;">✓ Fix:</strong> <?= htmlspecialchars($issue['fix']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Warnings -->
<?php if ($total_warnings > 0): ?>
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px; color: #f59e0b;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline; vertical-align: middle; margin-right: 8px;">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Warnings (<?= $total_warnings ?>) — Should Fix
    </h2>
    
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($warnings as $warning): ?>
        <div style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 8px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 16px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($warning['message']) ?></div>
                    <div style="font-size: 12px; font-family: monospace; color: var(--text-secondary);">
                        <?= htmlspecialchars($warning['path']) ?>
                        <?php if (!empty($warning['detail'])): ?>
                        <br><span style="color: #f59e0b;"><?= htmlspecialchars($warning['detail']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">
                    <?= htmlspecialchars($warning['type']) ?>
                </div>
            </div>
            <?php if (!empty($warning['fix'])): ?>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(245, 158, 11, 0.1); font-size: 12px;">
                <strong style="color: #10b981;">✓ Fix:</strong> <?= htmlspecialchars($warning['fix']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Info -->
<?php if ($total_info > 0): ?>
<div class="card">
    <h2 class="card-title" style="margin-bottom: 20px; color: #3b82f6;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline; vertical-align: middle; margin-right: 8px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        Info (<?= $total_info ?>)
    </h2>
    
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($info as $item): ?>
        <div style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 16px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($item['message']) ?></div>
                    <div style="font-size: 12px; font-family: monospace; color: var(--text-secondary);">
                        <?= htmlspecialchars($item['path']) ?>
                    </div>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">
                    <?= htmlspecialchars($item['type']) ?>
                </div>
            </div>
            <?php if (!empty($item['fix'])): ?>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(59, 130, 246, 0.1); font-size: 12px;">
                <strong style="color: #3b82f6;">ℹ</strong> <?= htmlspecialchars($item['fix']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

