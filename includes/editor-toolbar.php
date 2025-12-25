<?php
/**
 * Shared Editor Toolbar
 * Used by both article editor and page editor
 * 
 * Variables (optional):
 * - $show_shortcodes: Whether to show Hugo shortcode buttons (default: true)
 */

$show_shortcodes = $show_shortcodes ?? true;
?>
<div class="editor-toolbar">
    <div class="toolbar-group">
        <span class="toolbar-label">Format</span>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('bold')" title="Bold (Ctrl+B)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg>
        </button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('italic')" title="Italic (Ctrl+I)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
        </button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('strikethrough')" title="Strikethrough">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4H9a3 3 0 0 0 0 6h6"/><line x1="4" y1="12" x2="20" y2="12"/><path d="M8 20h7a3 3 0 0 0 0-6H6"/></svg>
        </button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('code')" title="Inline Code">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
        </button>
    </div>
    
    <div class="toolbar-divider"></div>
    
    <div class="toolbar-group">
        <span class="toolbar-label">Headings</span>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('h1')" title="Heading 1">H1</button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('h2')" title="Heading 2">H2</button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('h3')" title="Heading 3">H3</button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('h4')" title="Heading 4">H4</button>
    </div>
    
    <div class="toolbar-divider"></div>
    
    <div class="toolbar-group">
        <span class="toolbar-label">Lists</span>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('ul')" title="Bullet List">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1" fill="currentColor"/><circle cx="4" cy="12" r="1" fill="currentColor"/><circle cx="4" cy="18" r="1" fill="currentColor"/></svg>
        </button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('ol')" title="Numbered List">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="3" y="8" font-size="8" fill="currentColor">1</text><text x="3" y="14" font-size="8" fill="currentColor">2</text><text x="3" y="20" font-size="8" fill="currentColor">3</text></svg>
        </button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('checklist')" title="Checklist">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="4" height="4" rx="1"/><path d="M4 9l1.5 1.5L8 7"/><line x1="12" y1="7" x2="21" y2="7"/><rect x="3" y="15" width="4" height="4" rx="1"/><line x1="12" y1="17" x2="21" y2="17"/></svg>
        </button>
    </div>
    
    <div class="toolbar-divider"></div>
    
    <div class="toolbar-group">
        <span class="toolbar-label">Insert</span>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('link')" title="Insert Link (Ctrl+K)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        </button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('blockquote')" title="Blockquote">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V21"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3"/></svg>
        </button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('codeblock')" title="Code Block">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 11 7 13 9 15"/><polyline points="15 11 17 13 15 15"/></svg>
        </button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('table')" title="Table">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
        </button>
        <button type="button" class="toolbar-btn" onclick="insertMarkdown('hr')" title="Horizontal Rule">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" stroke-width="3"/></svg>
        </button>
    </div>
    
    <?php if ($show_shortcodes): ?>
    <div class="toolbar-divider"></div>
    
    <div class="toolbar-group toolbar-hugo">
        <span class="toolbar-label">🎨 Hugo</span>
        <button type="button" class="toolbar-btn toolbar-hugo-btn" onclick="openShortcodeModal('screenshot')" title="Insert Screenshot">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <span>Screenshot</span>
        </button>
        <button type="button" class="toolbar-btn toolbar-hugo-btn" onclick="openShortcodeModal('img')" title="Insert Image">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            <span>Image</span>
        </button>
        <button type="button" class="toolbar-btn toolbar-hugo-btn" onclick="openShortcodeModal('video')" title="Insert Video">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <span>Video</span>
        </button>
    </div>
    <?php endif; ?>
    
    <div class="toolbar-spacer"></div>
    
    <a href="https://www.markdownguide.org/cheat-sheet/" target="_blank" class="toolbar-btn toolbar-help" title="Markdown Help">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </a>
</div>

