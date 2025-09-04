<?php
/**
 * CSV Import Page
 * Best Jobs in TA - Contact Import Interface
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
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            csvData = data.data;
            populateCSVColumns();
            showStep(2);
        } else {
            alert('Error uploading CSV: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error uploading CSV file');
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
    }
    
    populateContactFields();
}

function populateContactFields() {
    const contactFieldsDiv = document.getElementById('contactFields');
    contactFieldsDiv.innerHTML = '';
    
    const fields = [
        { name: 'first_name', label: 'First Name', required: true },
        { name: 'last_name', label: 'Last Name', required: true },
        { name: 'email', label: 'Email', required: true },
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
            this.innerHTML = `<strong>${this.innerHTML.split('<br>')[0]}</strong><br><small class="text-success">Mapped to: ${column}</small>`;
        });
    });
}

// Step 2 to Step 3
document.getElementById('nextToStep3').addEventListener('click', function() {
    // Validate required fields are mapped
    const requiredFields = ['first_name', 'last_name', 'email'];
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
        notes: importNotes
    };
    
    fetch('/api/v1/contacts/import', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(importData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showImportResults(data);
        } else {
            alert('Import failed: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error during import');
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