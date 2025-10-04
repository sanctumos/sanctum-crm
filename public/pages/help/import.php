<?php
/**
 * CSV Import Help Page
 * Best Jobs in TA - CSV import documentation
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-file-import me-2"></i>CSV Import Guide</h4>
    <span class="badge bg-warning">Step-by-Step</span>
</div>

<div class="alert alert-success">
    <i class="fas fa-check-circle me-2"></i>
    <strong>CSV Import</strong> allows you to bulk import contacts with flexible field mapping and automatic name splitting.
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list-ol me-2"></i>Import Process</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Step 1: Upload CSV</h6>
                        <ol>
                            <li>Click "Choose File" button</li>
                            <li>Select your CSV file</li>
                            <li>Click "Upload CSV"</li>
                            <li>Wait for file processing</li>
                        </ol>
                        
                        <h6 class="mt-4">Step 2: Field Mapping</h6>
                        <ol>
                            <li>Review CSV columns on the left</li>
                            <li>Drag columns to contact fields on the right</li>
                            <li>Map required fields (First Name, Last Name)</li>
                            <li>Map optional fields as needed</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>Step 3: Name Splitting</h6>
                        <ol>
                            <li>Select the column containing full names</li>
                            <li>Choose delimiter (space, comma, etc.)</li>
                            <li>Preview the split results</li>
                            <li>Apply name splitting</li>
                        </ol>
                        
                        <h6 class="mt-4">Step 4: Import</h6>
                        <ol>
                            <li>Review import summary</li>
                            <li>Add source and notes</li>
                            <li>Click "Start Import"</li>
                            <li>Review results</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check me-2"></i>Required Fields</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-star text-warning me-2"></i><strong>First Name</strong></li>
                    <li class="mb-2"><i class="fas fa-star text-warning me-2"></i><strong>Last Name</strong></li>
                    <li class="mb-2"><i class="fas fa-circle text-muted me-2"></i>Email</li>
                    <li class="mb-2"><i class="fas fa-circle text-muted me-2"></i>Phone</li>
                    <li class="mb-2"><i class="fas fa-circle text-muted me-2"></i>Company</li>
                    <li class="mb-2"><i class="fas fa-circle text-muted me-2"></i>Job Title</li>
                    <li class="mb-2"><i class="fas fa-circle text-muted me-2"></i>Address</li>
                    <li class="mb-2"><i class="fas fa-circle text-muted me-2"></i>City</li>
                    <li class="mb-2"><i class="fas fa-circle text-muted me-2"></i>State</li>
                    <li class="mb-2"><i class="fas fa-circle text-muted me-2"></i>ZIP Code</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-magic me-2"></i>Name Splitting Feature</h5>
            </div>
            <div class="card-body">
                <p>The name splitting feature automatically separates full names into first and last names:</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Supported Formats:</h6>
                        <ul>
                            <li><strong>First Last:</strong> "John Doe" → John, Doe</li>
                            <li><strong>Last, First:</strong> "Doe, John" → John, Doe</li>
                            <li><strong>First Middle Last:</strong> "John Michael Doe" → John, Doe</li>
                            <li><strong>Custom Delimiters:</strong> Space, comma, semicolon, etc.</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Configuration Options:</h6>
                        <ul>
                            <li><strong>Column:</strong> Select which column contains names</li>
                            <li><strong>Delimiter:</strong> Choose how names are separated</li>
                            <li><strong>First Part:</strong> Which part is the first name</li>
                            <li><strong>Last Part:</strong> Which part is the last name</li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Tip:</strong> Use the preview feature to see how names will be split before applying.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Common Issues</h5>
            </div>
            <div class="card-body">
                <h6>File Upload Errors</h6>
                <ul>
                    <li><strong>File too large:</strong> Split into smaller files</li>
                    <li><strong>Invalid format:</strong> Ensure file is CSV</li>
                    <li><strong>Encoding issues:</strong> Save as UTF-8</li>
                    <li><strong>Empty file:</strong> Check file has data</li>
                </ul>
                
                <h6 class="mt-3">Mapping Errors</h6>
                <ul>
                    <li><strong>Missing required fields:</strong> Map First Name and Last Name</li>
                    <li><strong>Invalid email format:</strong> Check email addresses</li>
                    <li><strong>Duplicate emails:</strong> System will skip duplicates</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Best Practices</h5>
            </div>
            <div class="card-body">
                <h6>File Preparation</h6>
                <ul>
                    <li>Use UTF-8 encoding</li>
                    <li>Include column headers</li>
                    <li>Remove empty rows</li>
                    <li>Validate email addresses</li>
                </ul>
                
                <h6 class="mt-3">Import Strategy</h6>
                <ul>
                    <li>Test with small files first</li>
                    <li>Use consistent naming</li>
                    <li>Add source information</li>
                    <li>Review results carefully</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>CSV Format Examples</h5>
            </div>
            <div class="card-body">
                <h6>Basic Contact Import</h6>
                <pre class="bg-light p-3"><code>Name,Email,Company,Phone
John Doe,john@example.com,Acme Corp,555-0123
Jane Smith,jane@example.com,Beta Inc,555-0124</code></pre>
                
                <h6 class="mt-3">Advanced Contact Import</h6>
                <pre class="bg-light p-3"><code>Full Name,Email,Company,Job Title,Phone,City,State
John Michael Doe,john@example.com,Acme Corp,CEO,555-0123,New York,NY
Jane Elizabeth Smith,jane@example.com,Beta Inc,CTO,555-0124,San Francisco,CA</code></pre>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> The system will automatically detect and handle different column names and formats.
                </div>
            </div>
        </div>
    </div>
</div>
