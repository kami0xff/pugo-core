<?php
/**
 * Shared Editor Modals
 * Shortcode modals for screenshot, image, video
 * 
 * Variables (optional):
 * - $media_hint: Hint text for media paths (e.g., "articles/blog/my-post")
 */

$media_hint = $media_hint ?? '';
?>

<!-- Screenshot Shortcode Modal -->
<div id="screenshotModal" class="modal-overlay shortcode-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">📸 Insert Screenshot</h2>
            <button type="button" class="modal-close" onclick="closeModal('screenshotModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="shortcode-form">
            <div class="shortcode-field">
                <label>Image Filename <span class="required">*</span></label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="screenshot_src" placeholder="step-1-signup.png" style="flex: 1;">
                    <button type="button" class="media-picker-btn" onclick="openShortcodeMediaPicker('screenshot_src')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Browse
                    </button>
                </div>
                <?php if ($media_hint): ?>
                <span class="field-hint">Image is loaded from: /images/<?= htmlspecialchars($media_hint) ?>/</span>
                <?php endif; ?>
            </div>
            <div class="shortcode-field">
                <label>Alt Text <span class="required">*</span></label>
                <input type="text" id="screenshot_alt" placeholder="Describe what the screenshot shows">
            </div>
            <div class="shortcode-field">
                <label>Step Number</label>
                <input type="number" id="screenshot_step" placeholder="1, 2, 3..." min="1">
                <span class="field-hint">Shows a step badge on the screenshot</span>
            </div>
            <div class="shortcode-field">
                <label>Caption</label>
                <input type="text" id="screenshot_caption" placeholder="Optional caption below image">
            </div>
            <div class="shortcode-field">
                <label>Highlight Position</label>
                <select id="screenshot_highlight">
                    <option value="">None</option>
                    <option value="top-left">Top Left</option>
                    <option value="top-right">Top Right</option>
                    <option value="center">Center</option>
                    <option value="bottom-left">Bottom Left</option>
                    <option value="bottom-right">Bottom Right</option>
                </select>
            </div>
            <div class="shortcode-preview">
                <div class="shortcode-preview-label">Preview</div>
                <code id="screenshot_preview">{{&lt; screenshot src="" alt="" &gt;}}</code>
            </div>
            <div class="shortcode-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('screenshotModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="insertShortcode('screenshot')">Insert</button>
            </div>
        </div>
    </div>
</div>

<!-- Image Shortcode Modal -->
<div id="imgModal" class="modal-overlay shortcode-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">🖼️ Insert Image</h2>
            <button type="button" class="modal-close" onclick="closeModal('imgModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="shortcode-form">
            <div class="shortcode-field">
                <label>Image Filename <span class="required">*</span></label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="img_src" placeholder="diagram.png" style="flex: 1;">
                    <button type="button" class="media-picker-btn" onclick="openShortcodeMediaPicker('img_src')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Browse
                    </button>
                </div>
                <?php if ($media_hint): ?>
                <span class="field-hint">Image is loaded from: /images/<?= htmlspecialchars($media_hint) ?>/</span>
                <?php endif; ?>
            </div>
            <div class="shortcode-field">
                <label>Alt Text <span class="required">*</span></label>
                <input type="text" id="img_alt" placeholder="Describe the image">
            </div>
            <div class="shortcode-field">
                <label>Caption</label>
                <input type="text" id="img_caption" placeholder="Optional caption below image">
            </div>
            <div class="shortcode-field">
                <label>Width (px)</label>
                <input type="number" id="img_width" placeholder="e.g. 600">
                <span class="field-hint">Leave empty for full width</span>
            </div>
            <div class="shortcode-preview">
                <div class="shortcode-preview-label">Preview</div>
                <code id="img_preview">{{&lt; img src="" alt="" &gt;}}</code>
            </div>
            <div class="shortcode-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('imgModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="insertShortcode('img')">Insert</button>
            </div>
        </div>
    </div>
</div>

<!-- Video Shortcode Modal -->
<div id="videoModal" class="modal-overlay shortcode-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">🎬 Insert Video</h2>
            <button type="button" class="modal-close" onclick="closeModal('videoModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="shortcode-form">
            <div class="shortcode-field">
                <label>Video Filename or URL <span class="required">*</span></label>
                <input type="text" id="video_src" placeholder="tutorial.mp4 or https://...">
                <?php if ($media_hint): ?>
                <span class="field-hint">Local videos from: /videos/<?= htmlspecialchars($media_hint) ?>/</span>
                <?php endif; ?>
            </div>
            <div class="shortcode-field">
                <label>
                    <input type="checkbox" id="video_external" onchange="updateVideoPreview()">
                    External URL (not hosted locally)
                </label>
            </div>
            <div class="shortcode-field">
                <label>Caption</label>
                <input type="text" id="video_caption" placeholder="Optional caption below video">
            </div>
            <div class="shortcode-field">
                <label>Poster Image</label>
                <input type="text" id="video_poster" placeholder="thumbnail.png">
                <span class="field-hint">Thumbnail shown before video plays</span>
            </div>
            <div class="shortcode-field">
                <label>Playback Options</label>
                <div class="checkbox-group">
                    <label><input type="checkbox" id="video_autoplay" onchange="updateVideoPreview()"> Autoplay</label>
                    <label><input type="checkbox" id="video_loop" onchange="updateVideoPreview()"> Loop</label>
                    <label><input type="checkbox" id="video_muted" onchange="updateVideoPreview()"> Muted</label>
                </div>
            </div>
            <div class="shortcode-preview">
                <div class="shortcode-preview-label">Preview</div>
                <code id="video_preview">{{&lt; video src="" &gt;}}</code>
            </div>
            <div class="shortcode-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('videoModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="insertShortcode('video')">Insert</button>
            </div>
        </div>
    </div>
</div>

<!-- Shortcode Media Picker Modal -->
<div id="shortcodeMediaModal" class="modal-overlay">
    <div class="modal" style="max-width: 900px;">
        <div class="modal-header">
            <h2 class="modal-title">Select Media File</h2>
            <button type="button" class="modal-close" onclick="closeModal('shortcodeMediaModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div id="shortcodeMediaContent" style="min-height: 300px;">
            <div class="loading"><div class="spinner"></div></div>
        </div>
    </div>
</div>

