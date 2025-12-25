<?php
/**
 * Shared Editor Scripts
 * Used by both article editor (edit.php) and page editor (page-edit.php)
 * 
 * Required variables:
 * - $editor_id: ID of the textarea element (default: 'editor')
 * - $preview_id: ID of the preview container (default: 'previewContent')
 * - $title_input_id: ID of the title input (default: 'titleInput')
 * - $media_path: Path for media picker (optional)
 */

$editor_id = $editor_id ?? 'editor';
$preview_id = $preview_id ?? 'previewContent';
$title_input_id = $title_input_id ?? 'titleInput';
$media_path = $media_path ?? '';
?>

<!-- Include marked.js for Markdown parsing -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<script>
// ============================================
// LIVE MARKDOWN PREVIEW
// ============================================

const editor = document.getElementById('<?= $editor_id ?>');
const preview = document.getElementById('<?= $preview_id ?>');
const titleInput = document.getElementById('<?= $title_input_id ?>');

function updatePreview() {
    if (!editor || !preview) return;
    
    const title = titleInput ? titleInput.value : '';
    let content = editor.value;
    
    // Configure marked
    marked.setOptions({
        breaks: true,
        gfm: true,
        headerIds: false
    });
    
    // Replace Hugo shortcodes with placeholder HTML for preview
    content = content.replace(/\{\{<\s*screenshot\s+([^>]+)\s*>\}\}/g, (match, attrs) => {
        const src = attrs.match(/src="([^"]+)"/)?.[1] || '';
        const alt = attrs.match(/alt="([^"]+)"/)?.[1] || '';
        const step = attrs.match(/step="([^"]+)"/)?.[1] || '';
        return `<div style="background: #1a1a1a; border: 1px dashed #e11d48; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center;">
            <span style="background: #e11d48; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-bottom: 8px; display: inline-block;">📸 Screenshot${step ? ' - Step ' + step : ''}</span>
            <div style="font-family: monospace; font-size: 12px; color: #888; margin-top: 8px;">${src}</div>
            <div style="font-size: 13px; color: #aaa; margin-top: 4px;">${alt}</div>
        </div>`;
    });
    
    content = content.replace(/\{\{<\s*img\s+([^>]+)\s*>\}\}/g, (match, attrs) => {
        const src = attrs.match(/src="([^"]+)"/)?.[1] || '';
        const alt = attrs.match(/alt="([^"]+)"/)?.[1] || '';
        return `<div style="background: #1a1a1a; border: 1px dashed #3b82f6; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center;">
            <span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">🖼️ Image</span>
            <div style="font-family: monospace; font-size: 12px; color: #888; margin-top: 8px;">${src}</div>
            <div style="font-size: 13px; color: #aaa; margin-top: 4px;">${alt}</div>
        </div>`;
    });
    
    content = content.replace(/\{\{<\s*video\s+([^>]+)\s*>\}\}/g, (match, attrs) => {
        const src = attrs.match(/src="([^"]+)"/)?.[1] || '';
        return `<div style="background: #1a1a1a; border: 1px dashed #10b981; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center;">
            <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">🎬 Video</span>
            <div style="font-family: monospace; font-size: 12px; color: #888; margin-top: 8px;">${src}</div>
        </div>`;
    });
    
    let html = '';
    if (title) {
        html += '<h1>' + escapeHtml(title) + '</h1>';
    }
    if (content) {
        html += marked.parse(content);
    }
    
    preview.innerHTML = html || '<p style="color: var(--text-muted);">Start typing to see preview...</p>';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Debounce function for better performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

const debouncedPreview = debounce(updatePreview, 150);

if (editor) {
    editor.addEventListener('input', debouncedPreview);
}
if (titleInput) {
    titleInput.addEventListener('input', debouncedPreview);
}

// Initial preview
updatePreview();

// ============================================
// MARKDOWN TOOLBAR FUNCTIONS
// ============================================

function insertMarkdown(type) {
    if (!editor) return;
    
    const textarea = editor;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    let insertion = '';
    let cursorOffset = 0;
    
    switch(type) {
        case 'bold':
            insertion = `**${selectedText || 'bold text'}**`;
            cursorOffset = selectedText ? insertion.length : 2;
            break;
        case 'italic':
            insertion = `*${selectedText || 'italic text'}*`;
            cursorOffset = selectedText ? insertion.length : 1;
            break;
        case 'strikethrough':
            insertion = `~~${selectedText || 'strikethrough'}~~`;
            cursorOffset = selectedText ? insertion.length : 2;
            break;
        case 'code':
            insertion = `\`${selectedText || 'code'}\``;
            cursorOffset = selectedText ? insertion.length : 1;
            break;
        case 'h1':
            insertion = `# ${selectedText || 'Heading 1'}`;
            cursorOffset = 2;
            break;
        case 'h2':
            insertion = `## ${selectedText || 'Heading 2'}`;
            cursorOffset = 3;
            break;
        case 'h3':
            insertion = `### ${selectedText || 'Heading 3'}`;
            cursorOffset = 4;
            break;
        case 'h4':
            insertion = `#### ${selectedText || 'Heading 4'}`;
            cursorOffset = 5;
            break;
        case 'ul':
            insertion = selectedText 
                ? selectedText.split('\n').map(line => `- ${line}`).join('\n')
                : '- List item\n- Another item\n- Third item';
            cursorOffset = 2;
            break;
        case 'ol':
            insertion = selectedText 
                ? selectedText.split('\n').map((line, i) => `${i+1}. ${line}`).join('\n')
                : '1. First item\n2. Second item\n3. Third item';
            cursorOffset = 3;
            break;
        case 'checklist':
            insertion = selectedText 
                ? selectedText.split('\n').map(line => `- [ ] ${line}`).join('\n')
                : '- [ ] Todo item\n- [ ] Another task\n- [x] Completed task';
            cursorOffset = 6;
            break;
        case 'link':
            if (selectedText) {
                insertion = `[${selectedText}](url)`;
                cursorOffset = insertion.length - 1;
            } else {
                insertion = '[link text](https://example.com)';
                cursorOffset = 1;
            }
            break;
        case 'blockquote':
            insertion = selectedText 
                ? selectedText.split('\n').map(line => `> ${line}`).join('\n')
                : '> Quote text here';
            cursorOffset = 2;
            break;
        case 'codeblock':
            insertion = '```\n' + (selectedText || 'code here') + '\n```';
            cursorOffset = 4;
            break;
        case 'table':
            insertion = '| Header 1 | Header 2 | Header 3 |\n|----------|----------|----------|\n| Cell 1   | Cell 2   | Cell 3   |\n| Cell 4   | Cell 5   | Cell 6   |';
            cursorOffset = 2;
            break;
        case 'hr':
            insertion = '\n---\n';
            cursorOffset = insertion.length;
            break;
    }
    
    // Insert the text
    textarea.value = textarea.value.substring(0, start) + insertion + textarea.value.substring(end);
    
    // Set cursor position
    if (selectedText) {
        textarea.selectionStart = start;
        textarea.selectionEnd = start + insertion.length;
    } else {
        textarea.selectionStart = textarea.selectionEnd = start + cursorOffset;
    }
    
    textarea.focus();
    updatePreview();
}

// Keyboard shortcuts
if (editor) {
    editor.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            switch(e.key.toLowerCase()) {
                case 'b':
                    e.preventDefault();
                    insertMarkdown('bold');
                    break;
                case 'i':
                    e.preventDefault();
                    insertMarkdown('italic');
                    break;
                case 'k':
                    e.preventDefault();
                    insertMarkdown('link');
                    break;
            }
        }
        
        // Tab key support
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = this.selectionStart;
            const end = this.selectionEnd;
            this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
            this.selectionStart = this.selectionEnd = start + 4;
            updatePreview();
        }
    });
}

// ============================================
// SHORTCODE FUNCTIONS
// ============================================

let currentShortcodeTarget = null;

function openShortcodeModal(type) {
    // Clear previous values
    document.querySelectorAll(`#${type}Modal input, #${type}Modal select, #${type}Modal textarea`).forEach(el => {
        if (el.type === 'checkbox') el.checked = false;
        else el.value = '';
    });
    
    // Update preview
    updateShortcodePreview(type);
    
    // Add live preview updates
    document.querySelectorAll(`#${type}Modal input, #${type}Modal select`).forEach(el => {
        el.addEventListener('input', () => updateShortcodePreview(type));
    });
    
    openModal(`${type}Modal`);
}

function updateShortcodePreview(type) {
    let preview = '';
    
    switch(type) {
        case 'screenshot':
            preview = buildScreenshotShortcode();
            break;
        case 'img':
            preview = buildImgShortcode();
            break;
        case 'video':
            preview = buildVideoShortcode();
            break;
    }
    
    const previewEl = document.getElementById(`${type}_preview`);
    if (previewEl) {
        previewEl.textContent = preview;
    }
}

function buildScreenshotShortcode() {
    const src = document.getElementById('screenshot_src')?.value || '';
    const alt = document.getElementById('screenshot_alt')?.value || '';
    const step = document.getElementById('screenshot_step')?.value || '';
    const caption = document.getElementById('screenshot_caption')?.value || '';
    const highlight = document.getElementById('screenshot_highlight')?.value || '';
    
    let code = `{{< screenshot src="${src}" alt="${alt}"`;
    if (step) code += ` step="${step}"`;
    if (caption) code += ` caption="${caption}"`;
    if (highlight) code += ` highlight="${highlight}"`;
    code += ` >}}`;
    
    return code;
}

function buildImgShortcode() {
    const src = document.getElementById('img_src')?.value || '';
    const alt = document.getElementById('img_alt')?.value || '';
    const caption = document.getElementById('img_caption')?.value || '';
    const width = document.getElementById('img_width')?.value || '';
    
    let code = `{{< img src="${src}" alt="${alt}"`;
    if (caption) code += ` caption="${caption}"`;
    if (width) code += ` width="${width}"`;
    code += ` >}}`;
    
    return code;
}

function buildVideoShortcode() {
    const src = document.getElementById('video_src')?.value || '';
    const external = document.getElementById('video_external')?.checked || false;
    const caption = document.getElementById('video_caption')?.value || '';
    const poster = document.getElementById('video_poster')?.value || '';
    const autoplay = document.getElementById('video_autoplay')?.checked || false;
    const loop = document.getElementById('video_loop')?.checked || false;
    const muted = document.getElementById('video_muted')?.checked || false;
    
    let code = `{{< video src="${src}"`;
    if (external) code += ` external="true"`;
    if (caption) code += ` caption="${caption}"`;
    if (poster) code += ` poster="${poster}"`;
    if (autoplay) code += ` autoplay="true"`;
    if (loop) code += ` loop="true"`;
    if (muted) code += ` muted="true"`;
    code += ` >}}`;
    
    return code;
}

function updateVideoPreview() {
    updateShortcodePreview('video');
}

function insertShortcode(type) {
    let code = '';
    
    switch(type) {
        case 'screenshot':
            code = buildScreenshotShortcode();
            break;
        case 'img':
            code = buildImgShortcode();
            break;
        case 'video':
            code = buildVideoShortcode();
            break;
    }
    
    if (!editor) return;
    
    // Insert at cursor position
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    editor.value = editor.value.substring(0, start) + code + editor.value.substring(end);
    editor.selectionStart = editor.selectionEnd = start + code.length;
    
    closeModal(`${type}Modal`);
    editor.focus();
    updatePreview();
}

// Media picker for shortcodes
function openShortcodeMediaPicker(targetInput) {
    currentShortcodeTarget = targetInput;
    openModal('shortcodeMediaModal');
    loadShortcodeMedia('<?= $media_path ?>');
}

function loadShortcodeMedia(path) {
    const content = document.getElementById('shortcodeMediaContent');
    if (!content) return;
    
    content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    
    fetch('api.php?action=media&path=' + encodeURIComponent(path))
        .then(r => r.json())
        .then(data => {
            let html = '<div class="breadcrumb" style="margin-bottom: 16px;">';
            html += '<a href="#" onclick="loadShortcodeMedia(\'\'); return false;">images</a>';
            
            if (path) {
                const parts = path.split('/');
                let currentPath = '';
                parts.forEach((part, i) => {
                    if (!part) return;
                    currentPath += (currentPath ? '/' : '') + part;
                    html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; opacity: 0.5;"><polyline points="9 18 15 12 9 6"/></svg>';
                    html += '<a href="#" onclick="loadShortcodeMedia(\'' + currentPath + '\'); return false;">' + part + '</a>';
                });
            }
            html += '</div>';
            
            html += '<div class="media-grid">';
            
            // Directories
            if (data.directories) {
                data.directories.forEach(dir => {
                    html += `
                        <div class="media-item media-folder" onclick="loadShortcodeMedia('${dir.path}')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                            </svg>
                            <span>${dir.name}</span>
                        </div>
                    `;
                });
            }
            
            // Files
            if (data.files) {
                data.files.forEach(file => {
                    const filename = file.name;
                    html += `
                        <div class="media-item" onclick="selectShortcodeMedia('${filename}')">
                            <img src="${file.path}" alt="${file.name}">
                            <div class="media-item-name">${file.name}</div>
                        </div>
                    `;
                });
            }
            
            html += '</div>';
            
            if ((!data.files || data.files.length === 0) && (!data.directories || data.directories.length === 0)) {
                html += '<div class="empty-state"><p>No media in this folder</p></div>';
            }
            
            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = '<div class="empty-state"><p>Error loading media</p></div>';
        });
}

function selectShortcodeMedia(filename) {
    if (currentShortcodeTarget) {
        const el = document.getElementById(currentShortcodeTarget);
        if (el) {
            el.value = filename;
            el.dispatchEvent(new Event('input'));
        }
    }
    closeModal('shortcodeMediaModal');
}

// ============================================
// NOTIFICATION HELPER
// ============================================

function showNotification(message, type) {
    const existing = document.querySelector('.build-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'build-notification';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 8px;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        max-width: 400px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    `;
    
    if (type === 'success') {
        notification.style.background = 'rgba(16, 185, 129, 0.95)';
        notification.style.color = 'white';
    } else {
        notification.style.background = 'rgba(225, 29, 72, 0.95)';
        notification.style.color = 'white';
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}
</script>

