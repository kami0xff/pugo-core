        </main>
    </div>
    
    <!-- EasyMDE for Markdown -->
    <script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
    
    <script>
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${type === 'success' ? '#10b981' : '#e11d48'}" stroke-width="2">
                    ${type === 'success' 
                        ? '<polyline points="20 6 9 17 4 12"/>' 
                        : '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'}
                </svg>
                <span>${message}</span>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideIn 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Modal handling
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });
        
        // Tags input functionality with autocomplete
        const allTags = <?= json_encode(get_tag_list($_GET['lang'] ?? 'en')) ?>;
        let activeAutocomplete = null;
        let selectedIndex = -1;
        
        document.querySelectorAll('.tags-container').forEach(container => {
            const input = container.querySelector('.tags-input');
            const hiddenInput = container.querySelector('input[type="hidden"]');
            const containerId = container.id;
            
            if (!input) return;
            
            // Only add autocomplete for tags, not keywords
            const isTagsContainer = containerId === 'tagsContainer';
            
            // Create autocomplete dropdown
            if (isTagsContainer) {
                const autocomplete = document.createElement('div');
                autocomplete.className = 'tags-autocomplete';
                autocomplete.style.cssText = `
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: var(--bg-secondary);
                    border: 1px solid var(--border-color);
                    border-radius: 6px;
                    max-height: 200px;
                    overflow-y: auto;
                    z-index: 100;
                    display: none;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                `;
                container.style.position = 'relative';
                container.appendChild(autocomplete);
                
                input.addEventListener('input', (e) => {
                    const value = e.target.value.toLowerCase().trim();
                    showTagSuggestions(container, autocomplete, value);
                });
                
                input.addEventListener('focus', () => {
                    if (input.value.trim()) {
                        showTagSuggestions(container, autocomplete, input.value.toLowerCase().trim());
                    }
                });
                
                input.addEventListener('blur', () => {
                    // Delay to allow click on suggestion
                    setTimeout(() => {
                        autocomplete.style.display = 'none';
                        selectedIndex = -1;
                    }, 200);
                });
            }
            
            input.addEventListener('keydown', (e) => {
                const autocomplete = container.querySelector('.tags-autocomplete');
                const suggestions = autocomplete ? autocomplete.querySelectorAll('.tag-suggestion') : [];
                
                if (e.key === 'ArrowDown' && suggestions.length > 0) {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, suggestions.length - 1);
                    updateAutocompleteSelection(suggestions);
                } else if (e.key === 'ArrowUp' && suggestions.length > 0) {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, 0);
                    updateAutocompleteSelection(suggestions);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedIndex >= 0 && suggestions[selectedIndex]) {
                        const value = suggestions[selectedIndex].dataset.tag;
                        addTag(container, value);
                        input.value = '';
                        updateHiddenInput(container);
                        if (autocomplete) autocomplete.style.display = 'none';
                        selectedIndex = -1;
                    } else {
                        const value = input.value.trim();
                        if (value) {
                            addTag(container, value);
                            input.value = '';
                            updateHiddenInput(container);
                            if (autocomplete) autocomplete.style.display = 'none';
                        }
                    }
                } else if (e.key === 'Escape') {
                    if (autocomplete) autocomplete.style.display = 'none';
                    selectedIndex = -1;
                } else if (e.key === ',') {
                    e.preventDefault();
                    const value = input.value.trim();
                    if (value) {
                        addTag(container, value);
                        input.value = '';
                        updateHiddenInput(container);
                        if (autocomplete) autocomplete.style.display = 'none';
                    }
                }
            });
        });
        
        function showTagSuggestions(container, autocomplete, query) {
            if (!query) {
                autocomplete.style.display = 'none';
                return;
            }
            
            // Get current tags to exclude
            const currentTags = Array.from(container.querySelectorAll('.tag')).map(t => t.dataset.value.toLowerCase());
            
            // Filter suggestions
            const matches = allTags
                .filter(tag => tag.toLowerCase().includes(query) && !currentTags.includes(tag.toLowerCase()))
                .slice(0, 8);
            
            if (matches.length === 0) {
                autocomplete.style.display = 'none';
                return;
            }
            
            selectedIndex = -1;
            autocomplete.innerHTML = matches.map((tag, i) => `
                <div class="tag-suggestion" data-tag="${tag}" data-index="${i}" style="
                    padding: 8px 12px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    transition: background 0.15s;
                " onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent-primary)" stroke-width="2">
                        <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/>
                        <path d="M7 7h.01"/>
                    </svg>
                    <span style="color: var(--text-primary);">${highlightMatch(tag, query)}</span>
                </div>
            `).join('');
            
            // Add click handlers
            autocomplete.querySelectorAll('.tag-suggestion').forEach(el => {
                el.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    const tag = el.dataset.tag;
                    addTag(container, tag);
                    container.querySelector('.tags-input').value = '';
                    updateHiddenInput(container);
                    autocomplete.style.display = 'none';
                });
            });
            
            autocomplete.style.display = 'block';
        }
        
        function highlightMatch(tag, query) {
            const index = tag.toLowerCase().indexOf(query);
            if (index === -1) return tag;
            return tag.substring(0, index) + 
                   '<strong style="color: var(--accent-primary);">' + 
                   tag.substring(index, index + query.length) + 
                   '</strong>' + 
                   tag.substring(index + query.length);
        }
        
        function updateAutocompleteSelection(suggestions) {
            suggestions.forEach((el, i) => {
                if (i === selectedIndex) {
                    el.style.background = 'var(--bg-hover)';
                } else {
                    el.style.background = '';
                }
            });
        }
        
        function addTag(container, value) {
            // Check if already exists
            const existing = Array.from(container.querySelectorAll('.tag')).map(t => t.dataset.value.toLowerCase());
            if (existing.includes(value.toLowerCase())) return;
            
            const tag = document.createElement('span');
            tag.className = 'tag';
            tag.innerHTML = `
                ${value}
                <button type="button" onclick="removeTag(this)">&times;</button>
            `;
            tag.dataset.value = value;
            container.insertBefore(tag, container.querySelector('.tags-input'));
        }
        
        function removeTag(button) {
            const container = button.closest('.tags-container');
            button.closest('.tag').remove();
            updateHiddenInput(container);
        }
        
        function updateHiddenInput(container) {
            const hiddenInput = container.querySelector('input[type="hidden"]');
            const tags = Array.from(container.querySelectorAll('.tag')).map(t => t.dataset.value);
            hiddenInput.value = JSON.stringify(tags);
        }
        
        // Initialize EasyMDE on markdown editors
        document.querySelectorAll('.markdown-editor').forEach(textarea => {
            new EasyMDE({
                element: textarea,
                spellChecker: false,
                autosave: {
                    enabled: true,
                    uniqueId: textarea.id,
                    delay: 1000,
                },
                toolbar: [
                    'bold', 'italic', 'heading', '|',
                    'quote', 'unordered-list', 'ordered-list', '|',
                    'link', 'image', '|',
                    'preview', 'side-by-side', 'fullscreen', '|',
                    'guide'
                ],
                status: ['autosave', 'lines', 'words'],
                minHeight: '400px',
            });
        });
    </script>
</body>
</html>

