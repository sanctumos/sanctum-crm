<?php
/**
 * Sanctum CRM
 * 
 * This file is part of Sanctum CRM.
 * 
 * Copyright (C) 2025 Sanctum OS
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * CSV Import Page
 * Sanctum CRM - Contact Import Interface
 */

// Get database instance
$db = Database::getInstance();

// Handle import actions
$action = $_GET['action'] ?? 'form';
$import_id = $_GET['id'] ?? null;

// Render the page using the template system
renderHeader('Import Contacts');
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Import Contacts</h1>
                <a href="/?page=contacts" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Contacts
                </a>
            </div>

            <!-- Import Steps -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="step-number active" id="step1-number">1</div>
                                <h6>Upload CSV</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="step-number" id="step2-number">2</div>
                                <h6>Map Fields</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="step-number" id="step3-number">3</div>
                                <h6>Set Source</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="step-number" id="step4-number">4</div>
                                <h6>Import</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 1: Upload CSV -->
            <div class="card" id="step1">
                <div class="card-header">
                    <h5 class="mb-0">Step 1: Upload CSV File</h5>
                </div>
                <div class="card-body">
                    <form id="csvUploadForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="csvFile" class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" id="csvFile" name="csvFile" accept=".csv" required>
                            <div class="form-text">Maximum file size: 10MB. First row should contain column headers.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload CSV
                        </button>
                    </form>
                </div>
            </div>

            <!-- Step 2: Map Fields -->
            <div class="card d-none" id="step2">
                <div class="card-header">
                    <h5 class="mb-0">Step 2: Map CSV Columns to Contact Fields</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>CSV Columns</h6>
                            <div id="csvColumns" class="list-group">
                                <!-- CSV columns will be populated here -->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Contact Fields</h6>
                            <div id="contactFields" class="list-group">
                                <!-- Contact fields will be populated here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Name Splitting Section -->
                    <div class="mt-4" id="nameSplitSection" style="display: none;">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-cut me-2"></i>Split Full Name
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">If you have a "Full Name" column, you can split it into First Name and Last Name:</p>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label for="fullNameColumn" class="form-label">Full Name Column</label>
                                        <select class="form-select" id="fullNameColumn">
                                            <option value="">Select a column...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="splitDelimiter" class="form-label">Split By</label>
                                        <select class="form-select" id="splitDelimiter">
                                            <option value=" ">Space (First Last)</option>
                                            <option value=",">Comma (Last, First)</option>
                                            <option value="|">Pipe (First|Last)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Preview</label>
                                        <div class="form-control-plaintext" id="nameSplitPreview">
                                            <small class="text-muted">Select a column to see preview</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-info" id="applyNameSplit">
                                        <i class="fas fa-cut me-1"></i> Apply Name Split
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="clearNameSplit">
                                        <i class="fas fa-times me-1"></i> Clear Split
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-success" id="nextToStep3">
                            <i class="fas fa-arrow-right"></i> Next: Set Source
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Set Source -->
            <div class="card d-none" id="step3">
                <div class="card-header">
                    <h5 class="mb-0">Step 3: Set Source for Imported Contacts</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="importSource" class="form-label">Source Name</label>
                        <input type="text" class="form-control" id="importSource" name="importSource" 
                               placeholder="e.g., LinkedIn Export, Event Attendees, Website Leads" required>
                        <div class="form-text">This will help you filter contacts by their source later.</div>
                    </div>
                    <div class="mb-3">
                        <label for="importNotes" class="form-label">Import Notes (Optional)</label>
                        <textarea class="form-control" id="importNotes" name="importNotes" rows="3" 
                                  placeholder="Add any notes about this import..."></textarea>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-success" id="nextToStep4">
                            <i class="fas fa-arrow-right"></i> Next: Review & Import
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 4: Review & Import -->
            <div class="card d-none" id="step4">
                <div class="card-header">
                    <h5 class="mb-0">Step 4: Review & Import Contacts</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Import Summary</h6>
                            <div id="importSummary" class="alert alert-info">
                                <!-- Import summary will be populated here -->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Field Mapping</h6>
                            <div id="fieldMappingSummary" class="alert alert-light">
                                <!-- Field mapping summary will be populated here -->
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" id="startImport">
                            <i class="fas fa-download"></i> Import Contacts
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="backToStep1">
                            <i class="fas fa-arrow-left"></i> Start Over
                        </button>
                    </div>
                </div>
            </div>

            <!-- Import Results -->
            <div class="card d-none" id="importResults">
                <div class="card-header">
                    <h5 class="mb-0">Import Results</h5>
                </div>
                <div class="card-body">
                    <div id="importResultsContent">
                        <!-- Import results will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-weight: bold;
}

.step-number.active {
    background-color: #007bff;
    color: white;
}

.step-number.completed {
    background-color: #28a745;
    color: white;
}

.draggable {
    cursor: move;
}

.droppable {
    min-height: 50px;
    border: 2px dashed #dee2e6;
    border-radius: 0.375rem;
    padding: 10px;
}

.droppable.drag-over {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.field-mapping {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 0.375rem;
}

.field-mapping .csv-column {
    flex: 1;
    margin-right: 10px;
}

.field-mapping .mapping-arrow {
    margin: 0 10px;
    color: #6c757d;
}

.field-mapping .contact-field {
    flex: 1;
    margin-left: 10px;
}
</style>

<script>
let csvData = [];
let fieldMapping = {};
let importSource = '';
let importNotes = '';
let nameSplitConfig = null; // { column: 'full_name', delimiter: ' ', firstPart: 0, lastPart: 1 }

// Step 1: Upload CSV
document.getElementById('csvUploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a CSV file');
        return;
    }
    
    const formData = new FormData();
    formData.append('csvFile', file);
    
    fetch('/api/v1/contacts/import', {
        method: 'POST',
        credentials: 'include',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            return response.json();
        } else {
            return response.json().then(error => {
                throw new Error(error.error || 'Failed to upload CSV');
            });
        }
    })
    .then(data => {
        if (data.success) {
            csvData = data.data;
            populateCSVColumns();
            showStep(2);
        } else {
            alert('Error uploading CSV: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error: ' + error.message);
    });
});

function populateCSVColumns() {
    const csvColumnsDiv = document.getElementById('csvColumns');
    csvColumnsDiv.innerHTML = '';
    
    if (csvData.length > 0) {
        const headers = Object.keys(csvData[0]);
        headers.forEach(header => {
            const div = document.createElement('div');
            div.className = 'list-group-item draggable';
            div.draggable = true;
            div.dataset.column = header;
            div.innerHTML = `<strong>${header}</strong><br><small class="text-muted">Sample: ${csvData[0][header] || 'N/A'}</small>`;
            csvColumnsDiv.appendChild(div);
        });
        
        // Populate name split dropdown
        populateNameSplitDropdown(headers);
    }
    
    populateContactFields();
}

function populateNameSplitDropdown(headers) {
    const dropdown = document.getElementById('fullNameColumn');
    dropdown.innerHTML = '<option value="">Select a column...</option>';
    
    headers.forEach(header => {
        const option = document.createElement('option');
        option.value = header;
        option.textContent = header;
        dropdown.appendChild(option);
    });
    
    // Show name split section if there are columns
    if (headers.length > 0) {
        document.getElementById('nameSplitSection').style.display = 'block';
    }
}

function populateContactFields() {
    const contactFieldsDiv = document.getElementById('contactFields');
    contactFieldsDiv.innerHTML = '';
    
    const fields = [
        { name: 'first_name', label: 'First Name', required: true },
        { name: 'last_name', label: 'Last Name', required: true },
        { name: 'email', label: 'Email', required: false },
        { name: 'phone', label: 'Phone' },
        { name: 'company', label: 'Company' },
        { name: 'job_title', label: 'Job Title' },
        { name: 'address', label: 'Address' },
        { name: 'city', label: 'City' },
        { name: 'state', label: 'State' },
        { name: 'zip', label: 'ZIP Code' },
        { name: 'country', label: 'Country' },
        { name: 'notes', label: 'Notes' }
    ];
    
    fields.forEach(field => {
        const div = document.createElement('div');
        div.className = 'list-group-item droppable';
        div.dataset.field = field.name;
        div.innerHTML = `<strong>${field.label}</strong> ${field.required ? '<span class="text-danger">*</span>' : ''}<br><small class="text-muted">Drop CSV column here</small>`;
        contactFieldsDiv.appendChild(div);
    });
    
    setupDragAndDrop();
    setupNameSplitHandlers();
}

function setupNameSplitHandlers() {
    // Name split column change
    document.getElementById('fullNameColumn').addEventListener('change', function() {
        updateNameSplitPreview();
    });
    
    // Split delimiter change
    document.getElementById('splitDelimiter').addEventListener('change', function() {
        updateNameSplitPreview();
    });
    
    // Apply name split
    document.getElementById('applyNameSplit').addEventListener('click', function() {
        applyNameSplit();
    });
    
    // Clear name split
    document.getElementById('clearNameSplit').addEventListener('click', function() {
        clearNameSplit();
    });
}

function updateNameSplitPreview() {
    const column = document.getElementById('fullNameColumn').value;
    const delimiter = document.getElementById('splitDelimiter').value;
    const preview = document.getElementById('nameSplitPreview');
    
    if (!column || !csvData.length) {
        preview.innerHTML = '<small class="text-muted">Select a column to see preview</small>';
        return;
    }
    
    const sampleValue = csvData[0][column] || '';
    if (!sampleValue) {
        preview.innerHTML = '<small class="text-muted">No sample data available</small>';
        return;
    }
    
    const parts = sampleValue.split(delimiter);
    if (parts.length >= 2) {
        const firstPart = parts[0].trim();
        const lastPart = parts[1].trim();
        preview.innerHTML = `<strong>First:</strong> "${firstPart}"<br><strong>Last:</strong> "${lastPart}"`;
    } else {
        preview.innerHTML = '<small class="text-warning">Not enough parts to split</small>';
    }
}

function applyNameSplit() {
    const column = document.getElementById('fullNameColumn').value;
    const delimiter = document.getElementById('splitDelimiter').value;
    
    if (!column) {
        alert('Please select a column to split');
        return;
    }
    
    // Determine which part is first and last based on delimiter
    let firstPartIndex = 0;
    let lastPartIndex = 1;
    
    if (delimiter === ',') {
        // For "Last, First" format
        firstPartIndex = 1;
        lastPartIndex = 0;
    }
    
    nameSplitConfig = {
        column: column,
        delimiter: delimiter,
        firstPart: firstPartIndex,
        lastPart: lastPartIndex
    };
    
    // Auto-map the split fields to the original column
    // The actual splitting will be handled by the API using nameSplitConfig
    fieldMapping['first_name'] = column;
    fieldMapping['last_name'] = column;
    
    // Update the UI to show the mapping
    updateFieldMappingDisplay();
    
    // Show success message
    alert('Name split applied! First Name and Last Name have been automatically mapped.');
}

function clearNameSplit() {
    nameSplitConfig = null;
    
    // Clear the auto-mapped fields if they were set by name split
    if (fieldMapping['first_name'] && fieldMapping['first_name'].includes('_split_first')) {
        delete fieldMapping['first_name'];
    }
    if (fieldMapping['last_name'] && fieldMapping['last_name'].includes('_split_last')) {
        delete fieldMapping['last_name'];
    }
    
    // Reset the dropdowns
    document.getElementById('fullNameColumn').value = '';
    document.getElementById('splitDelimiter').value = ' ';
    document.getElementById('nameSplitPreview').innerHTML = '<small class="text-muted">Select a column to see preview</small>';
    
    // Update the UI
    updateFieldMappingDisplay();
}

function updateFieldMappingDisplay() {
    // Update the contact fields display to show current mappings
    const contactFields = document.querySelectorAll('#contactFields .droppable');
    contactFields.forEach(field => {
        const fieldName = field.dataset.field;
        if (fieldMapping[fieldName]) {
            const mapping = fieldMapping[fieldName];
            // Check if this is a name split field
            if (nameSplitConfig && (fieldName === 'first_name' || fieldName === 'last_name') && mapping === nameSplitConfig.column) {
                // This is a split field
                field.innerHTML = `<strong>${field.innerHTML.split('<br>')[0]}</strong><br><small class="text-info">Split from: ${mapping}</small>`;
            } else {
                // Regular mapping
                field.innerHTML = `<strong>${field.innerHTML.split('<br>')[0]}</strong><br><small class="text-success">Mapped to: ${mapping}</small>`;
            }
        } else {
            // Reset to original state
            const fieldLabel = field.innerHTML.split('<br>')[0];
            const required = fieldLabel.includes('*') ? ' <span class="text-danger">*</span>' : '';
            field.innerHTML = `<strong>${fieldLabel}</strong>${required}<br><small class="text-muted">Drop CSV column here</small>`;
        }
    });
}

function setupDragAndDrop() {
    const draggables = document.querySelectorAll('.draggable');
    const droppables = document.querySelectorAll('.droppable');
    
    draggables.forEach(draggable => {
        draggable.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', this.dataset.column);
            this.style.opacity = '0.5';
        });
        
        draggable.addEventListener('dragend', function(e) {
            this.style.opacity = '1';
        });
    });
    
    droppables.forEach(droppable => {
        droppable.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        droppable.addEventListener('dragleave', function(e) {
            this.classList.remove('drag-over');
        });
        
        droppable.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const column = e.dataTransfer.getData('text/plain');
            const field = this.dataset.field;
            
            // Update field mapping
            fieldMapping[field] = column;
            
            // Update UI
            updateFieldMappingDisplay();
        });
    });
}

// Step 2 to Step 3
document.getElementById('nextToStep3').addEventListener('click', function() {
    // Validate required fields are mapped
    const requiredFields = ['first_name', 'last_name'];
    const missingFields = requiredFields.filter(field => !fieldMapping[field]);
    
    if (missingFields.length > 0) {
        alert('Please map the following required fields: ' + missingFields.join(', '));
        return;
    }
    
    showStep(3);
});

// Step 3 to Step 4
document.getElementById('nextToStep4').addEventListener('click', function() {
    importSource = document.getElementById('importSource').value.trim();
    importNotes = document.getElementById('importNotes').value.trim();
    
    if (!importSource) {
        alert('Please enter a source name');
        return;
    }
    
    populateImportSummary();
    showStep(4);
});

function populateImportSummary() {
    const summaryDiv = document.getElementById('importSummary');
    const mappingDiv = document.getElementById('fieldMappingSummary');
    
    summaryDiv.innerHTML = `
        <strong>Total Records:</strong> ${csvData.length}<br>
        <strong>Source:</strong> ${importSource}<br>
        <strong>Notes:</strong> ${importNotes || 'None'}
    `;
    
    let mappingHtml = '';
    Object.entries(fieldMapping).forEach(([field, column]) => {
        mappingHtml += `<div><strong>${field}:</strong> ${column}</div>`;
    });
    mappingDiv.innerHTML = mappingHtml;
}

// Step 4: Start Import
document.getElementById('startImport').addEventListener('click', function() {
    const importData = {
        csvData: csvData,
        fieldMapping: fieldMapping,
        source: importSource,
        notes: importNotes,
        nameSplitConfig: nameSplitConfig
    };
    
    fetch('/api/v1/contacts/import', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(importData)
    })
    .then(response => {
        if (response.ok) {
            return response.json();
        } else {
            return response.json().then(error => {
                throw new Error(error.error || 'Failed to process import');
            });
        }
    })
    .then(data => {
        if (data.success) {
            showImportResults(data);
        } else {
            alert('Import failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error: ' + error.message);
    });
});

function showImportResults(data) {
    const resultsDiv = document.getElementById('importResultsContent');
    resultsDiv.innerHTML = `
        <div class="alert alert-success">
            <h6>Import Completed Successfully!</h6>
            <p><strong>Total Processed:</strong> ${data.totalProcessed}</p>
            <p><strong>Successfully Imported:</strong> ${data.successCount}</p>
            <p><strong>Failed:</strong> ${data.errorCount}</p>
        </div>
        ${data.errors.length > 0 ? `
            <div class="alert alert-warning">
                <h6>Errors:</h6>
                <ul class="mb-0">
                    ${data.errors.map(error => `<li>Row ${error.row}: ${error.message}</li>`).join('')}
                </ul>
            </div>
        ` : ''}
    `;
    
    document.getElementById('importResults').classList.remove('d-none');
    document.getElementById('step4').classList.add('d-none');
}

// Back to Step 1
document.getElementById('backToStep1').addEventListener('click', function() {
    // Reset everything
    csvData = [];
    fieldMapping = {};
    importSource = '';
    importNotes = '';
    
    document.getElementById('csvUploadForm').reset();
    document.getElementById('importSource').value = '';
    document.getElementById('importNotes').value = '';
    
    showStep(1);
    document.getElementById('importResults').classList.add('d-none');
});

function showStep(stepNumber) {
    // Hide all steps
    for (let i = 1; i <= 4; i++) {
        document.getElementById(`step${i}`).classList.add('d-none');
        document.getElementById(`step${i}-number`).classList.remove('active', 'completed');
    }
    
    // Show current step
    document.getElementById(`step${stepNumber}`).classList.remove('d-none');
    document.getElementById(`step${stepNumber}-number`).classList.add('active');
    
    // Mark previous steps as completed
    for (let i = 1; i < stepNumber; i++) {
        document.getElementById(`step${i}-number`).classList.add('completed');
    }
}
</script>

<?php renderFooter(); ?>