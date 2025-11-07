/**
 * My Custom GPTs - Admin JavaScript
 *
 * This file handles all interactions on the admin page:
 * - Mounting the template
 * - Fetching, displaying, saving, and deleting GPTs
 * - Handling the Add/Edit form drawer and dynamic prompt fields
 */

// Use an IIFE (Immediately Invoked Function Expression) to avoid global scope pollution
( ($) => {
    'use strict';

    // ssmData (cpgAdminData) object passed from PHP (wp_localize_script)
    const ssmData = window.cpgAdminData || {};
    const strings = ssmData.strings || {};

    // --- 1. Utility Functions ---

    /**
     * Shows a message in the form drawer.
     * @param {string} message The message text.
     * @param {boolean} isError Is it an error message?
     */
    function showFormMessage( message, isError = false ) {
        const msgEl = $( '#cpg-form-message' );
        if ( ! msgEl.length ) return;

        msgEl.text( message )
            .removeClass( 'cpg-error cpg-success' )
            .addClass( isError ? 'cpg-error' : 'cpg-success' )
            .show();
    }

    /**
     * Toggles the main form loader.
     * @param {boolean} show Show or hide the loader.
     */
    function toggleFormLoader( show ) {
        $( '#cpg-form-loader' ).toggle( show );
        $( '#cpg-save-gpt-btn' ).prop( 'disabled', show );
        if ( show ) {
            $( '#cpg-save-gpt-btn' ).text( strings.saving || 'Saving...' );
        } else {
            $( '#cpg-save-gpt-btn' ).text( strings.save_gpt || 'Save GPT' );
        }
    }

    /**
     * Toggles the main list loader.
     * @param {boolean} show Show or hide the loader.
     */
    function toggleListLoader( show ) {
        $( '#cpg-list-loader' ).toggle( show );
        $( '#cpg-gpts-table' ).toggle( ! show );
    }

    // --- 2. State & Rendering ---

    // Simple state to hold fetched data
    let state = {
        gpts: [],
    };

    /**
     * Renders a single row in the GPTs table.
     * @param {object} gpt The GPT object.
     * @return {jQuery} A jQuery object for the <tr> element.
     */
    function renderGptRow( gpt ) {
        // Sanitize data (though it should be sanitized from PHP)
        const name = gpt.name || '';
        const description = gpt.description || '';
        const id = gpt.id || 0;

        // Get prompt field labels
        let fieldLabels = ( gpt.prompt_fields || [] )
            .map( f => f.label )
            .join( ', ' );
        if ( ! fieldLabels ) {
            fieldLabels = '—';
        }

        const $row = $( '<tr>' );
        $row.attr( 'data-gpt-id', id );

        $row.html( `
            <td class="cpg-cell-name">
                <strong>${ $( '<div/>' ).text( name ).html() }</strong>
            </td>
            <td class="cpg-cell-desc">${ $( '<div/>' ).text( description ).html() }</td>
            <td class="cpg-cell-fields">${ $( '<div/>' ).text( fieldLabels ).html() }</td>
            <td class="cpg-cell-actions">
                <button class="button cpg-edit-btn" data-id="${id}">
                    ${ __( 'Edit', ssmData.text_domain ) }
                </button>
                <button class="button button-link-delete cpg-delete-btn" data-id="${id}">
                    ${ __( 'Delete', ssmData.text_domain ) }
                </button>
            </td>
        ` );

        return $row;
    }

    /**
     * Renders the complete list of GPTs in the table.
     * @param {Array} gpts List of GPT objects.
     */
    function renderGptList( gpts ) {
        const $tbody = $( '#cpg-gpts-table-body' );
        const $noGptsRow = $( '#cpg-no-gpts-row' );

        if ( ! $tbody.length ) return;

        $tbody.empty(); // Clear existing rows

        if ( ! gpts || gpts.length === 0 ) {
            $noGptsRow.find( 'td' ).text( strings.no_gpts_found || 'No GPTs found.' );
            $tbody.append( $noGptsRow.show() );
        } else {
            $noGptsRow.hide();
            gpts.forEach( gpt => {
                $tbody.append( renderGptRow( gpt ) );
            } );
        }
    }

    // --- 3. Form Drawer & Prompt Fields ---

    /**
     * Opens the Add/Edit drawer.
     * @param {object|null} gpt The GPT to edit, or null for a new one.
     */
    function openDrawer( gpt = null ) {
        const $drawer = $( '#cpg-form-drawer' );
        const $form = $( '#cpg-gpt-form' );
        const $title = $( '#cpg-form-title' );

        $form[0].reset(); // Reset form fields
        $( '#cpg-form-message' ).hide();
        $( '#cpg-gpt-id' ).val( '0' );
        renderPromptFieldRows( [] ); // Clear prompt fields

        if ( gpt ) {
            // Edit mode
            $title.text( strings.edit_gpt || 'Edit GPT' );
            $( '#cpg-save-gpt-btn' ).text( strings.save_gpt || 'Save GPT' );
            
            // Populate form
            $( '#cpg-gpt-id' ).val( gpt.id );
            $( '#cpg-gpt-name' ).val( gpt.name );
            $( '#cpg-gpt-url' ).val( gpt.gpt_url );
            $( '#cpg-gpt-description' ).val( gpt.description );
            $( '#cpg-prompt-template' ).val( gpt.prompt_template );
            
            // Render existing prompt fields
            if ( gpt.prompt_fields ) {
                renderPromptFieldRows( gpt.prompt_fields );
            }
        } else {
            // Add New mode
            $title.text( strings.add_gpt || 'Add New GPT' );
            $( '#cpg-save-gpt-btn' ).text( strings.save_gpt || 'Save GPT' );
        }

        $drawer.show();
        $( '#cpg-gpt-name' ).focus(); // Focus the first field
    }

    /**
     * Closes the Add/Edit drawer.
     */
    function closeDrawer() {
        $( '#cpg-form-drawer' ).hide();
        $( '#cpg-gpt-form' )[0].reset();
    }

    /**
     * Renders the dynamic "Prompt Fields" in the form.
     * @param {Array} fields Array of field objects.
     */
    function renderPromptFieldRows( fields ) {
        const $container = $( '#cpg-prompt-fields-container' );
        $container.empty();

        if ( ! fields || fields.length === 0 ) {
            // Add one empty row if no fields exist
            $container.append( createPromptFieldRow( null, 0 ) );
        } else {
            fields.forEach( ( field, index ) => {
                $container.append( createPromptFieldRow( field, index ) );
            } );
        }
        
        // Show/hide info text
        $( '#cpg-field-info' ).toggle( $container.children().length > 0 );
    }

    /**
     * Creates a single prompt field row (HTML).
     * @param {object|null} field Field data or null.
     * @param {number} index The row index.
     * @return {string} HTML string for the row.
     */
    function createPromptFieldRow( field, index ) {
        const label = field ? field.label : '';
        const type = field ? field.type : 'text';
        const options = field ? field.options : '';

        // Note: We use index-based names for easy parsing in PHP
        return `
            <div class="cpg-prompt-field-row">
                <input type="text" 
                       name="prompt_fields[${index}][label]" 
                       value="${ $( '<div/>' ).text( label ).html() }" 
                       placeholder="${ strings.field_label_placeholder || 'Field Label' }">
                       
                <input type="text" 
                       name="prompt_fields[${index}][type]" 
                       value="${ $( '<div/>' ).text( type ).html() }"
                       placeholder="${ strings.field_type_placeholder || 'Field Type' }">
                       
                <input type="text" 
                       name="prompt_fields[${index}][options]" 
                       value="${ $( '<div/>' ).text( options ).html() }"
                       placeholder="${ strings.field_options_placeholder || 'Options (comma-separated)' }">
                       
                <button type="button" class="button cpg-remove-field-btn">
                    ${ strings.remove_field || 'Remove' }
                </button>
            </div>
        `;
    }

    /**
     * Adds a new empty prompt field row to the form.
     */
    function addNewFieldRow() {
        const $container = $( '#cpg-prompt-fields-container' );
        const newIndex = $container.children().length;
        $container.append( createPromptFieldRow( null, newIndex ) );
        $( '#cpg-field-info' ).show();
    }

    /**
     * Re-indexes form field names after a row is removed.
     */
    function reindexFieldRows() {
        $( '#cpg-prompt-fields-container .cpg-prompt-field-row' ).each( ( index, row ) => {
            $( row ).find( 'input' ).each( ( i, input ) => {
                const name = $( input ).attr( 'name' );
                if ( name ) {
                    const newName = name.replace( /\[\d+\]/, `[${index}]` );
                    $( input ).attr( 'name', newName );
                }
            } );
        } );
        
        // Hide info if no fields left
        if ($( '#cpg-prompt-fields-container' ).children().length === 0) {
            $( '#cpg-field-info' ).hide();
        }
    }


    // --- 4. AJAX Functions ---

    /**
     * Loads all GPTs from the server.
     */
    function loadGpts() {
        toggleListLoader( true );

        $.ajax( {
            url: ssmData.ajax_url,
            type: 'POST',
            data: {
                action: 'cpg_get_gpts',
                nonce: ssmData.nonce,
            },
            dataType: 'json',
        } )
        .done( ( response ) => {
            if ( response.success && response.data.gpts ) {
                state.gpts = response.data.gpts; // Save to state
                renderGptList( state.gpts );
            } else {
                alert( strings.error_general || 'An error occurred.' );
            }
        } )
        .fail( () => {
            alert( strings.error_general || 'An error occurred.' );
        } )
        .always( () => {
            toggleListLoader( false );
        } );
    }

    /**
     * Saves a GPT (Create or Update).
     * @param {object} gptData The GPT data from the form.
     */
    function saveGpt( gptData ) {
        toggleFormLoader( true );
        $( '#cpg-form-message' ).hide();

        // Note: We send data as JSON
        $.ajax( {
            url: ssmData.ajax_url,
            type: 'POST',
            data: JSON.stringify( {
                action: 'cpg_save_gpt', // This key is not used by PHP's file_get_contents
                nonce: ssmData.nonce,    // This key is not used by PHP's file_get_contents
                ...gptData,
            } ),
            contentType: 'application/json', // Tell server we're sending JSON
            dataType: 'json',
            // We must add action and nonce to the URL for check_ajax_referer
            beforeSend: function ( xhr, settings ) {
                settings.url = settings.url + '?action=cpg_save_gpt&nonce=' + ssmData.nonce;
            }
        } )
        .done( ( response ) => {
            if ( response.success && response.data.gpt ) {
                showFormMessage( response.data.message, false );
                
                // Update state and re-render list
                const savedGpt = response.data.gpt;
                const existingIndex = state.gpts.findIndex( g => g.id === savedGpt.id );
                if ( existingIndex > -1 ) {
                    state.gpts[ existingIndex ] = savedGpt; // Update
                } else {
                    state.gpts.unshift( savedGpt ); // Add new to top
                }
                renderGptList( state.gpts );
                
                // Close drawer after a short delay
                setTimeout( () => {
                    closeDrawer();
                }, 1000 );
                
            } else {
                showFormMessage( response.data.message || strings.error_general, true );
            }
        } )
        .fail( ( xhr ) => {
            let error = strings.error_general;
            if( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
                error = xhr.responseJSON.data.message;
            }
            showFormMessage( error, true );
        } )
        .always( () => {
            toggleFormLoader( false );
        } );
    }

    /**
     * Deletes a GPT by its ID.
     * @param {number} gptId The ID of the GPT to delete.
     */
    function deleteGpt( gptId ) {
        if ( ! confirm( strings.confirm_delete || 'Are you sure?' ) ) {
            return;
        }

        const $row = $( `tr[data-gpt-id="${gptId}"]` );
        $row.css( 'opacity', '0.5' );
        $row.find( '.cpg-delete-btn' ).text( strings.deleting || 'Deleting...' );

        $.ajax( {
            url: ssmData.ajax_url,
            type: 'POST',
            data: {
                action: 'cpg_delete_gpt',
                nonce: ssmData.nonce,
                id: gptId,
            },
            dataType: 'json',
        } )
        .done( ( response ) => {
            if ( response.success ) {
                // Remove from state and DOM
                state.gpts = state.gpts.filter( g => g.id !== gptId );
                $row.fadeOut( 300, () => $row.remove() );
                
                // Show 'no gpts' row if list is now empty
                if ( state.gpts.length === 0 ) {
                    renderGptList( [] );
                }
            } else {
                alert( response.data.message || strings.error_general );
                $row.css( 'opacity', '1' );
                $row.find( '.cpg-delete-btn' ).text( __( 'Delete', ssmData.text_domain ) );
            }
        } )
        .fail( () => {
            alert( strings.error_general );
            $row.css( 'opacity', '1' );
            $row.find( '.cpg-delete-btn' ).text( __( 'Delete', ssmData.text_domain ) );
        } );
    }


    // --- 5. Event Handlers ---

    /**
     * Handles the main form submission.
     * @param {Event} e Submit event.
     */
    function onFormSubmit( e ) {
        e.preventDefault();
        
        const $form = $( this );
        
        // Basic validation
        const name = $( '#cpg-gpt-name' ).val();
        const url = $( '#cpg-gpt-url' ).val();
        if ( ! name || ! url ) {
            showFormMessage( strings.error_fields || 'Please fill required fields.', true );
            return;
        }

        // Serialize form data into a key-value object
        const formData = $form.serializeArray();
        const gptData = {
            id: 0,
            prompt_fields: []
        };
        const fieldMap = {}; // To group prompt_fields

        formData.forEach( item => {
            // Check for prompt_fields[index][key]
            const fieldMatch = item.name.match( /prompt_fields\[(\d+)\]\[(label|type|options)\]/ );
            
            if ( fieldMatch ) {
                const index = fieldMatch[1];
                const key = fieldMatch[2];
                
                if ( ! fieldMap[index] ) {
                    fieldMap[index] = {};
                }
                fieldMap[index][key] = item.value;
                
            } else {
                // Regular field (id, name, gpt_url, etc.)
                gptData[item.name] = item.value;
            }
        } );

        // Convert fieldMap object to array
        gptData.prompt_fields = Object.values( fieldMap ).filter( 
            field => field.label && field.type // Only include fields with a label and type
        );
        
        // Convert ID to number
        gptData.id = parseInt( gptData.id, 10 ) || 0;

        // Pass to AJAX function
        saveGpt( gptData );
    }

    /**
     * Handles clicks on the "Edit" button (uses event delegation).
     */
    function onEditClick() {
        const gptId = $( this ).data( 'id' );
        const gptToEdit = state.gpts.find( g => g.id === gptId );
        
        if ( gptToEdit ) {
            openDrawer( gptToEdit );
        }
    }

    /**
     * Handles clicks on the "Delete" button (uses event delegation).
     */
    function onDeleteClick() {
        const gptId = $( this ).data( 'id' );
        deleteGpt( gptId );
    }

    /**
     * Handles clicks on the "Remove Field" button (uses event delegation).
     */
    function onRemoveFieldClick() {
        $( this ).closest( '.cpg-prompt-field-row' ).remove();
        reindexFieldRows();
    }


    // --- 6. Initialization ---

    /**
     * Main initialization function.
     * Runs when the DOM is ready.
     */
    function init() {
        // Find the root element and template
        const $root = $( '#cpg-admin-root' );
        const $template = $( '#cpg-admin-template' );

        if ( ! $root.length || ! $template.length ) {
            console.error( 'CPG Admin: Root or Template not found.' );
            return;
        }

        // --- 1. Mount Template ---
        // Clone the template's content and replace the root element
        const templateContent = $template.html();
        $root.html( templateContent ).attr( 'data-screen', 'loaded' );

        // --- 2. Bind Event Handlers (using delegation for dynamic content) ---
        
        // Drawer buttons
        $( '#cpg-add-new-btn' ).on( 'click', () => openDrawer( null ) );
        $( '#cpg-close-drawer-btn, .cpg-drawer-overlay' ).on( 'click', closeDrawer );
        
        // Form submission
        $( '#cpg-gpt-form' ).on( 'submit', onFormSubmit );

        // Prompt field buttons
        $( '#cpg-add-field-btn' ).on( 'click', addNewFieldRow );
        $( '#cpg-prompt-fields-container' ).on( 'click', '.cpg-remove-field-btn', onRemoveFieldClick );

        // Table action buttons (delegated from tbody)
        $( '#cpg-gpts-table-body' ).on( 'click', '.cpg-edit-btn', onEditClick );
        $( '#cpg-gpts-table-body' ).on( 'click', '.cpg-delete-btn', onDeleteClick );

        // --- 3. Load Initial Data ---
        loadGpts();
    }

    // Run the app
    $( document ).ready( init );

} )( jQuery );
