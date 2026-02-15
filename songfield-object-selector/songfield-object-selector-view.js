
/**
 * Songfield Object Selector - Interactivity API View
 */

import { store, getContext } from '@wordpress/interactivity';

const { state } = store('songfield-object-selector', {
    state: {
        get isLoading() {
            const context = getContext();
            return context.isLoading;
        },
        get isListVisible() {
            const context = getContext();
            return context.isListVisible;
        },
        get isUpdating() {
            const context = getContext();
            return context.isUpdating || false;
        },
        get error() {
            const context = getContext();
            return context.error;
        },
        get currentObjectId() {
            const context = getContext();
            return context.currentObjectId;
        },
        get availableObjects() {
            const context = getContext();
            return context.availableObjects || [];
        },
        get successMessage() {
            const context = getContext();
            return context.successMessage || '';
        }
    },
    actions: {
        
        showList: (event) =>  {
            const context = getContext();
            context.isListVisible = true;
        },
        async handleObjectChange(event) {
            const context = getContext();
            const selectedObjectId = event.target.value;
            const selectedObjectTitle = event.target.textContent;
            
            if (!selectedObjectId) {
                return;
            }
            
            // Set updating state
            context.isUpdating = true;
            context.successMessage = '';
            context.error = '';
            
            try {
                const response = await fetch(
                    `${context.apiBaseUrl}/set-object`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${context.jwtToken}`
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            object_id: selectedObjectId
                        })
                    }
                );
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Update current object ID
                context.currentObjectId = selectedObjectId;
                context.currentObjectTitle = selectedObjectTitle;
                
                context.successMessage = 'Object updated successfully!';
                
                // Clear success message after 3 seconds
                setTimeout(() => {
                    context.successMessage = '';
                }, 3000);
                
            } catch (error) {
                console.error('Error updating object:', error);
                context.error = `Failed to update object: ${error.message}`;
                
                // Reset dropdown to current value
                event.target.value = context.currentObjectId;
            } finally {
                context.isUpdating = false;
            }
        }
    },
    callbacks: {
        async init() {
            const context = getContext();
            
            try {
                const response = await fetch(
                    `${context.apiBaseUrl}/current-objects`,
                    {
                        method: 'GET',
                        headers: {
                            'Authorization': `Bearer ${context.jwtToken}`,
                            'Content-Type': 'application/json'
                        },
                        credentials: 'include'
                    }
                );
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Update context with fetched data
                context.currentObjectId = data.current_object_id || '';
                context.availableObjects = data.available_objects || [];
                context.isLoading = false;
                
            } catch (error) {
                console.error('Error fetching objects:', error);
                context.error = `Failed to load objects: ${error.message}`;
                context.isLoading = false;
            }
        }
    }
});
