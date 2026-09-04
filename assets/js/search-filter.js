document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('tca-search-form');
    if (!searchForm) return;

    // --- AUTOCOMPLETE LOGIC ---
    const keywordInput = document.querySelector('.tca-search-input[name="keyword"]');
    const isCompact = keywordInput && keywordInput.closest('.tca-compact-filter') !== null;
    if (keywordInput) {
        // Create dropdown container
        const dropdown = document.createElement('div');
        dropdown.className = 'tca-autocomplete-dropdown';
        dropdown.style.display = 'none';
        
        // Append it just after the input inside its wrapper
        const wrapper = keywordInput.closest('.tca-search-input-wrapper');
        if (wrapper) {
            wrapper.style.position = 'relative'; // Ensure proper absolute positioning for dropdown
            wrapper.appendChild(dropdown);
        }
 
        let autocompleteTimeout = null;
        let autocompleteController = null;

        const fetchAutocomplete = (query) => {
            if (autocompleteController) autocompleteController.abort();
            autocompleteController = new AbortController();

            const fd = new FormData();
            fd.append('action', 'tca_autocomplete_search');
            fd.append('security', tcaSearchAjax.nonce);
            fd.append('query', query);
            if (isCompact) {
                fd.append('is_compact', '1');
            }

            // Append active base filters to autocomplete query to restrict suggestions
            const resultsContainer = document.getElementById('tca-results-wrapper');
            if (resultsContainer) {
                const baseKeys = ['location', 'developer'];
                baseKeys.forEach(key => {
                    const baseVal = resultsContainer.getAttribute('data-base-' + key);
                    if (baseVal) {
                        fd.append(key, baseVal);
                    }
                });
            }

            fetch(tcaSearchAjax.ajaxurl, {
                method: 'POST',
                body: fd,
                signal: autocompleteController.signal
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    dropdown.innerHTML = '';
                    const ul = document.createElement('ul');
                    ul.className = 'tca-autocomplete-list';
                    
                    data.data.forEach(item => {
                        const li = document.createElement('li');
                        li.className = 'tca-autocomplete-item';
                        // Highlight matched text (basic bolding)
                        const regex = new RegExp(`(${query})`, 'gi');
                        const labelHtml = query ? item.label.replace(regex, '<strong>$1</strong>') : item.label;
                        
                        li.innerHTML = `
                            <span class="tca-ac-label">${labelHtml}</span>
                            <span class="tca-ac-type">${item.type}</span>
                        `;
                        
                        li.addEventListener('click', (e) => {
                            e.stopPropagation();
                            keywordInput.value = item.value;
                            dropdown.style.display = 'none';
                            // Optional: auto-submit the search
                            searchForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                        });
                        ul.appendChild(li);
                    });
                    
                    dropdown.appendChild(ul);
                    dropdown.style.display = 'block';
                } else {
                    dropdown.style.display = 'none';
                }
            })
            .catch(err => { if (err.name !== 'AbortError') dropdown.style.display = 'none'; });
        };

        // Event Listeners
        keywordInput.addEventListener('focus', () => {
            if (keywordInput.value.trim() === '') {
                fetchAutocomplete(''); // Fetch popular locations
            } else if (dropdown.innerHTML !== '') {
                dropdown.style.display = 'block';
            }
        });

        keywordInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(autocompleteTimeout);
            
            if (query.length === 0) {
                fetchAutocomplete('');
                return;
            }
            
            autocompleteTimeout = setTimeout(() => {
                fetchAutocomplete(query);
            }, 300); // 300ms debounce
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!keywordInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    }
    // --- END AUTOCOMPLETE LOGIC ---

    let activeController = null;

    const isMobile = () => window.innerWidth <= 1024;

    // Reinitialise gallery sliders after AJAX load
    const reinitSliders = () => {
        document.querySelectorAll('.tca-cg-image-box, .tca-cl-image-box').forEach(box => {
            const slider = box.querySelector('.tca-gallery-slider');
            const left   = box.querySelector('.ctrl-left');
            const right  = box.querySelector('.ctrl-right');
            if (!slider) return;
            let sw = slider.clientWidth;
            slider.addEventListener('scroll', () => {
                if (right) right.style.display = (slider.scrollLeft + sw >= slider.scrollWidth - 10) ? 'none' : 'flex';
                if (left)  left.style.display  = (slider.scrollLeft <= 10) ? 'none' : 'flex';
            });
            if (left)  left.onclick  = e => { e.preventDefault(); slider.scrollBy({ left: -sw, behavior: 'smooth' }); };
            if (right) right.onclick = e => { e.preventDefault(); slider.scrollBy({ left:  sw, behavior: 'smooth' }); };
        });
    };
 
    // Build FormData, disabling the "wrong" set of fields to prevent duplicates
    const buildFormData = () => {
        const mobile = isMobile();

        // Disable fields we do NOT want to include
        document.querySelectorAll('.tca-desktop-field').forEach(f => { f.disabled = mobile; });
        document.querySelectorAll('.tca-mobile-field').forEach(f  => { f.disabled = !mobile; });

        const fd = new FormData(searchForm);

        // Re-enable everything immediately after
        document.querySelectorAll('.tca-desktop-field, .tca-mobile-field').forEach(f => { f.disabled = false; });

        return fd;
    };

    const fetchResults = (formData) => {
        const resultsContainer = document.getElementById('tca-results-wrapper');
        if (!resultsContainer) return;

        if (activeController) activeController.abort();
        activeController = new AbortController();

        formData.append('action', 'tca_filter_units');
        formData.append('security', tcaSearchAjax.nonce);

        const limit = resultsContainer.getAttribute('data-limit');
        if (limit) formData.append('limit', limit);

        // Pull "base" filters from the container (e.g. location from the shortcode)
        // and add them to the formData if not already set by the user in the search bar.
        const baseKeys = ['purpose', 'location', 'developer', 'project', 'property_type', 'amenity', 'status', 'bedrooms', 'bathrooms'];
        baseKeys.forEach(key => {
            const baseVal = resultsContainer.getAttribute('data-base-' + key);
            if (baseVal && formData.get(key) === null) {
                formData.append(key, baseVal);
            }
        });

        // Sync URL bar
        const urlParams = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (!['action', 'security', 'limit'].includes(key) && value !== '') {
                urlParams.set(key, value);
            }
        }
        window.history.pushState({}, '', window.location.pathname + '?' + urlParams.toString());

        resultsContainer.style.opacity = '0.5';
        const btn = searchForm.querySelector('.tca-search-btn');
        if (btn) btn.innerText = 'SEARCHING...';

        fetch(tcaSearchAjax.ajaxurl, {
            method: 'POST',
            body: formData,
            signal: activeController.signal
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const tmp = document.createElement('div');
                tmp.innerHTML = data.data.html;
                const newWrapper = tmp.firstElementChild;
                if (newWrapper && resultsContainer.parentNode) {
                    resultsContainer.parentNode.replaceChild(newWrapper, resultsContainer);
                }
                bindPaginationListeners();
                reinitSliders();
            } else {
                console.error('TCA Search Error:', data);
                resultsContainer.style.opacity = '1';
            }
        })
        .catch(err => {
            if (err.name === 'AbortError') return;
            console.error('AJAX Fetch Error:', err);
            resultsContainer.style.opacity = '1';
        })
        .finally(() => {
            if (btn) btn.innerText = 'SEARCH';
        });
    };

    // ── Mobile Bottom-Sheet ────────────────────────────────────────────────
    const filterBtn     = document.getElementById('tca-filter-toggle');
    const filterPanel   = document.getElementById('tca-filter-panel');
    const filterOverlay = document.getElementById('tca-filter-overlay');
    const filterClose   = document.getElementById('tca-filter-close');
    const filterApply   = document.getElementById('tca-filter-apply');

    const openSheet = () => {
        if (!filterPanel || !isMobile()) return;
        filterPanel.classList.add('is-open');
        if (filterOverlay) filterOverlay.classList.add('is-visible');
        document.body.style.overflow = 'hidden';
    };
    const closeSheet = () => {
        if (!filterPanel) return;
        filterPanel.classList.remove('is-open');
        if (filterOverlay) filterOverlay.classList.remove('is-visible');
        document.body.style.overflow = '';
    };

    if (filterBtn)     filterBtn.addEventListener('click', openSheet);
    if (filterClose)   filterClose.addEventListener('click', closeSheet);
    if (filterOverlay) filterOverlay.addEventListener('click', closeSheet);
    if (filterApply)   filterApply.addEventListener('click', () => {
        closeSheet();
        fetchResults(buildFormData());
    });

    // ── Form Submit ────────────────────────────────────────────────────────
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        fetchResults(buildFormData());
    });

    // Auto-submit desktop dropdowns on change, and also shared/primary dropdowns on mobile
    searchForm.querySelectorAll('select.tca-desktop-field, select[name="purpose"], select[name="property_type"]').forEach(sel => {
        sel.addEventListener('change', (e) => {
            const isShared = e.target.name === 'purpose' || e.target.name === 'property_type';
            if (!isMobile() || isShared) {
                fetchResults(buildFormData());
            }
        });
    });

    // ── Pagination ─────────────────────────────────────────────────────────
    const bindPaginationListeners = () => {
        document.querySelectorAll('.tca-page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (this.classList.contains('active')) return;
                const fd = buildFormData();
                fd.append('page', this.getAttribute('data-page'));
                const c = document.getElementById('tca-results-wrapper');
                if (c) c.scrollIntoView({ behavior: 'smooth', block: 'start' });
                fetchResults(fd);
            });
        });
    };

    // ── Custom Dropdowns (Beds / Baths) Toggle & Selection ─────────────────
    document.querySelectorAll('.tca-dropdown-filter').forEach(drop => {
        const trigger = drop.querySelector('.tca-dropdown-trigger');
        const panel   = drop.querySelector('.tca-dropdown-panel');

        if (trigger && panel) {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                // Close other panels
                document.querySelectorAll('.tca-dropdown-panel').forEach(p => {
                    if (p !== panel) p.style.display = 'none';
                });
                // Toggle current
                const isOpen = panel.style.display === 'block';
                panel.style.display = isOpen ? 'none' : 'block';
                drop.classList.toggle('is-open', !isOpen);
            });
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.tca-dropdown-filter').forEach(drop => {
            const panel = drop.querySelector('.tca-dropdown-panel');
            if (panel) {
                panel.style.display = 'none';
                drop.classList.remove('is-open');
            }
        });
    });

    // Handle button group selections (both desktop and mobile)
    document.querySelectorAll('.tca-btn-group').forEach(group => {
        const name = group.getAttribute('data-name');
        const parent = group.closest('.tca-dropdown-filter, .tca-inline-filter');
        if (!parent) return;

        const hiddenInput = parent.querySelector('.tca-dropdown-hidden-input');

        group.querySelectorAll('.tca-filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Select current, deselect siblings
                group.querySelectorAll('.tca-filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Set value
                const val = btn.getAttribute('data-value');
                if (hiddenInput) {
                    hiddenInput.value = val;
                }

                // If desktop, update trigger label and close panel
                const dropFilter = group.closest('.tca-dropdown-filter');
                if (dropFilter) {
                    const triggerLabel = dropFilter.querySelector('.tca-trigger-label');
                    if (triggerLabel) {
                        const defaultText = name === 'bedrooms' ? 'Bedrooms' : 'Bathrooms';
                        const singularText = name === 'bedrooms' ? 'Bedroom' : 'Bathroom';
                        if (val === '') {
                            triggerLabel.innerText = defaultText;
                        } else if (val === '0') {
                            triggerLabel.innerText = 'Studio';
                        } else if (val === '1') {
                            triggerLabel.innerText = '1 ' + singularText;
                        } else {
                            triggerLabel.innerText = val + ' ' + defaultText;
                        }
                    }
                    const panel = dropFilter.querySelector('.tca-dropdown-panel');
                    if (panel) {
                        panel.style.display = 'none';
                        dropFilter.classList.remove('is-open');
                    }
                }

                // Auto-submit only if NOT inside mobile bottom sheet
                const isMobileSheet = group.closest('.tca-inline-filter') !== null;
                if (!isMobileSheet && !isMobile()) {
                    fetchResults(buildFormData());
                }
            });
        });
    });

    // Handle Reset Search button clicks
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('tca-reset-search-btn')) {
            e.preventDefault();
            
            const resultsContainer = document.getElementById('tca-results-wrapper');
            
            // 1. Reset all standard selects to page base values if defined, else to first option
            searchForm.querySelectorAll('select').forEach(select => {
                const name = select.name;
                const baseVal = resultsContainer ? resultsContainer.getAttribute('data-base-' + name) : null;
                if (baseVal) {
                    select.value = baseVal;
                } else {
                    select.selectedIndex = 0;
                }
            });
            
            // 2. Clear keyword search input
            const kwInput = searchForm.querySelector('input[name="keyword"]');
            if (kwInput) {
                kwInput.value = '';
            }
            
            // 3. Reset hidden inputs for custom filters to page base values
            searchForm.querySelectorAll('.tca-dropdown-hidden-input').forEach(input => {
                const name = input.name;
                const baseVal = resultsContainer ? resultsContainer.getAttribute('data-base-' + name) : null;
                input.value = baseVal || '';
            });
            
            // 4. Reset custom button active states to page base values or "All"
            document.querySelectorAll('.tca-btn-group').forEach(group => {
                const name = group.getAttribute('data-name');
                const baseVal = resultsContainer ? resultsContainer.getAttribute('data-base-' + name) : '';
                
                group.querySelectorAll('.tca-filter-btn').forEach(btn => {
                    const val = btn.getAttribute('data-value');
                    if (val === baseVal) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });
                
                // Reset trigger labels
                const parent = group.closest('.tca-dropdown-filter');
                if (parent) {
                    const label = parent.querySelector('.tca-trigger-label');
                    if (label) {
                        if (baseVal === '') {
                            label.innerText = name === 'bedrooms' ? 'Bedrooms' : 'Bathrooms';
                        } else if (baseVal === '0') {
                            label.innerText = 'Studio';
                        } else {
                            const defaultText = name === 'bedrooms' ? (baseVal === '1' ? 'Bedroom' : 'Bedrooms') : (baseVal === '1' ? 'Bathroom' : 'Bathrooms');
                            label.innerText = baseVal + ' ' + defaultText;
                        }
                    }
                }
            });
            
            // 5. Trigger AJAX search submit
            fetchResults(buildFormData());
        }
    });

    // Handle inquiry button click on cards to populate Elementor popup form fields
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.tca-enquire-btn');
        if (btn) {
            const title = btn.getAttribute('data-property-title');
            const url = btn.getAttribute('data-property-url');
            const ref = btn.getAttribute('data-property-ref');
            
            if (title || url || ref) {
                // Wait 200ms for Elementor to mount the popup elements in the DOM
                setTimeout(() => {
                    const titleInput = document.querySelector('input[name="form_fields[property_title]"]');
                    const urlInput   = document.querySelector('input[name="form_fields[property_url]"]');
                    const refInput   = document.querySelector('input[name="form_fields[property_ref]"]');
                    
                    if (titleInput && title) titleInput.value = title;
                    if (urlInput && url)   urlInput.value = url;
                    if (refInput && ref)   refInput.value = ref;
                }, 200);
            }
        }
    });

    bindPaginationListeners();
});
