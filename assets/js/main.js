/**
 * Main JavaScript for JV Shetty Store
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize cart count
    updateCartCount();
    
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', handleAddToCart);
    });
    
    // Wishlist buttons
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
        btn.addEventListener('click', handleWishlistToggle);
    });
});

/**
 * Show toast notification
 */
function showToast(message, type = 'success') {
    const container = document.querySelector('.toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'warning'}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    container.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

/**
 * Handle add to cart
 */
async function handleAddToCart(e) {
    const btn = e.currentTarget;
    const productId = btn.dataset.productId;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    try {
        const response = await fetch('api/cart.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, quantity: 1 })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Added to cart');
            updateCartCount();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Failed to add to cart', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cart-plus"></i>';
    }
}

/**
 * Update cart count badge
 */
async function updateCartCount() {
    try {
        const response = await fetch('api/cart.php?action=count');
        const data = await response.json();
        
        if (data.success) {
            const badge = document.getElementById('cart-count');
            if (badge) {
                badge.textContent = data.data.count;
                badge.style.display = data.data.count > 0 ? 'block' : 'none';
            }
        }
    } catch (error) {
        console.error('Failed to update cart count:', error);
    }
}

/**
 * Handle wishlist toggle
 */
async function handleWishlistToggle(e) {
    const btn = e.currentTarget;
    const productId = btn.dataset.productId;
    const icon = btn.querySelector('i');
    
    btn.disabled = true;
    
    try {
        const isAdding = !btn.classList.contains('active');
        const action = isAdding ? 'add' : 'remove';
        
        const response = await fetch(`api/wishlist.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (isAdding) {
                btn.classList.add('active');
                icon.classList.replace('bi-heart', 'bi-heart-fill');
                showToast('Added to wishlist');
            } else {
                btn.classList.remove('active');
                icon.classList.replace('bi-heart-fill', 'bi-heart');
                showToast('Removed from wishlist');
                
                // Remove from wishlist page if present
                const card = btn.closest('.wishlist-item');
                if (card) card.remove();
            }
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Failed to update wishlist', 'error');
    } finally {
        btn.disabled = false;
    }
}

/**
 * Update cart item quantity
 */
async function updateCartItem(itemId, quantity) {
    try {
        const response = await fetch('api/cart.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId, quantity: quantity })
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Failed to update cart', 'error');
    }
}

/**
 * Remove cart item
 */
async function removeCartItem(itemId) {
    if (!confirm('Remove this item from cart?')) return;
    
    try {
        const response = await fetch('api/cart.php?action=remove', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Failed to remove item', 'error');
    }
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toFixed(2);
}

/**
 * Format date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
}

/**
 * Format time
 */
function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(hours, minutes);
    return date.toLocaleTimeString('en-IN', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

/**
 * Debounce function
 */
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

/**
 * Validate email (campus only)
 */
function isValidCampusEmail(email) {
    return email.endsWith('@goa.bits-pilani.ac.in');
}

/**
 * Handle form submission with loading state
 */
async function submitForm(form, url, options = {}) {
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '';
    
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    }
    
    try {
        const formData = new FormData(form);
        const body = options.json ? JSON.stringify(Object.fromEntries(formData)) : formData;
        
        const fetchOptions = {
            method: 'POST',
            headers: options.json ? { 'Content-Type': 'application/json' } : {},
            body: body
        };
        
        const response = await fetch(url, fetchOptions);
        const data = await response.json();
        
        if (data.success && options.onSuccess) {
            options.onSuccess(data);
        } else if (!data.success && options.onError) {
            options.onError(data);
        }
        
        return data;
    } catch (error) {
        if (options.onError) {
            options.onError({ message: 'Network error. Please try again.' });
        }
        throw error;
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
}

/**
 * Confirm action
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Copy to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}


// ========================================
// SPOTLIGHT GLOW CARDS - From 21st.dev
// ========================================

function initGlowCards() {
  const cards = document.querySelectorAll('.glow-card');
  
  const syncPointer = (e) => {
    const x = e.clientX;
    const y = e.clientY;
    const xp = x / window.innerWidth;
    const yp = y / window.innerHeight;
    
    cards.forEach(card => {
      card.style.setProperty('--x', `${x}px`);
      card.style.setProperty('--xp', xp);
      card.style.setProperty('--y', `${y}px`);
      card.style.setProperty('--yp', yp);
    });
  };
  
  document.addEventListener('pointermove', syncPointer);
}

// Initialize on page load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initGlowCards);
} else {
  initGlowCards();
}
