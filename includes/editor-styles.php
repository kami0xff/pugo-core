<?php
/**
 * Shared Editor Styles
 * Used by both article editor (edit.php) and page editor (page-edit.php)
 */
?>
<style>
/* ============================================
   EDITOR TOOLBAR
   ============================================ */
.editor-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-color);
    flex-wrap: wrap;
}

.toolbar-group {
    display: flex;
    align-items: center;
    gap: 4px;
}

.toolbar-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-right: 6px;
    font-weight: 600;
}

.toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 6px 8px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 4px;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.15s ease;
    font-family: inherit;
}

.toolbar-btn svg {
    width: 16px;
    height: 16px;
}

.toolbar-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
    border-color: var(--border-color);
}

.toolbar-btn:active {
    background: var(--bg-secondary);
}

.toolbar-divider {
    width: 1px;
    height: 24px;
    background: var(--border-color);
    margin: 0 4px;
}

.toolbar-spacer {
    flex: 1;
}

.toolbar-hugo-btn {
    background: rgba(225, 29, 72, 0.1);
    color: var(--accent-primary);
    border-color: rgba(225, 29, 72, 0.2);
}

.toolbar-hugo-btn:hover {
    background: rgba(225, 29, 72, 0.2);
    color: var(--accent-primary);
    border-color: rgba(225, 29, 72, 0.3);
}

.toolbar-help {
    text-decoration: none;
    color: var(--text-muted);
}

/* ============================================
   SPLIT EDITOR WITH PREVIEW
   ============================================ */
.editor-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    min-height: 500px;
}

.editor-pane, .preview-pane {
    display: flex;
    flex-direction: column;
}

.editor-pane {
    border-right: 1px solid var(--border-color);
}

.editor-pane textarea {
    flex: 1;
    min-height: 450px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    line-height: 1.6;
    resize: none;
    border: none;
    border-radius: 0;
    padding: 20px;
    background: var(--bg-secondary);
}

.preview-pane {
    background: var(--bg-tertiary);
}

.preview-header {
    background: var(--bg-secondary);
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
}

.preview-header svg {
    width: 16px;
    height: 16px;
}

.preview-content {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
    color: var(--text-primary);
    line-height: 1.8;
}

/* Preview typography */
.preview-content h1 { font-size: 28px; margin: 0 0 16px; }
.preview-content h2 { font-size: 22px; margin: 24px 0 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; }
.preview-content h3 { font-size: 18px; margin: 20px 0 10px; }
.preview-content p { margin: 0 0 16px; }
.preview-content ul, .preview-content ol { margin: 0 0 16px; padding-left: 24px; }
.preview-content li { margin-bottom: 8px; }
.preview-content code { background: var(--bg-secondary); padding: 2px 6px; border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-size: 0.9em; }
.preview-content pre { background: var(--bg-secondary); padding: 16px; border-radius: 8px; overflow-x: auto; margin: 0 0 16px; }
.preview-content pre code { background: none; padding: 0; }
.preview-content blockquote { border-left: 4px solid var(--accent-primary); padding-left: 16px; margin: 0 0 16px; color: var(--text-secondary); }
.preview-content a { color: var(--accent-primary); }
.preview-content img { max-width: 100%; border-radius: 8px; margin: 16px 0; }
.preview-content hr { border: none; border-top: 1px solid var(--border-color); margin: 24px 0; }
.preview-content strong { color: var(--text-primary); }

/* ============================================
   SHORTCODE MODALS
   ============================================ */
.shortcode-modal .modal {
    max-width: 600px;
}

.shortcode-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.shortcode-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.shortcode-field label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
}

.shortcode-field label .required {
    color: var(--accent-primary);
}

.shortcode-field .field-hint {
    font-size: 11px;
    color: var(--text-muted);
}

.shortcode-field input,
.shortcode-field select,
.shortcode-field textarea {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 14px;
}

.shortcode-field input:focus,
.shortcode-field select:focus,
.shortcode-field textarea:focus {
    outline: none;
    border-color: var(--accent-primary);
}

.shortcode-preview {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 12px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: var(--accent-primary);
    word-break: break-all;
    margin-top: 16px;
}

.shortcode-preview-label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 8px;
    font-weight: 600;
}

.shortcode-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 8px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.media-picker-btn {
    padding: 8px 12px;
    background: var(--bg-tertiary);
    border: 1px dashed var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}

.media-picker-btn:hover {
    border-color: var(--accent-primary);
    color: var(--accent-primary);
}

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-weight: normal;
}

.checkbox-group input[type="checkbox"] {
    width: 16px;
    height: 16px;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1200px) {
    .editor-container {
        grid-template-columns: 1fr;
    }
    .preview-pane {
        display: none;
    }
}

@media (max-width: 900px) {
    .editor-toolbar {
        gap: 4px;
        padding: 8px 12px;
    }
    .toolbar-label {
        display: none;
    }
    .toolbar-hugo-btn span:last-child {
        display: none;
    }
}
</style>

