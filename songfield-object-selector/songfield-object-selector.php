/**
 * Songfield Object Selector - Interactivity API View
 */

import { store, getContext } from '@wordpress/interactivity';

const { state } = store('songfieldObjectSelector', {
    state: {
        get isLoading() {
            const context = getContext();
            return context.isLoading;
        },
        get isListHidden() {
            const context = getContext();
            return context.isListHidden;
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
            if ( context.isListHidden === true )  context.isListHidden  = false;
            else if ( context.isListHidden === false )  context.isListHidden  = true;
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
                context.isListHidden = true
                
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
                    `${context.apiBaseUrl}`,
                    {
                        method: 'POST',
                        data : {  action: 'my_objects_action' }
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
