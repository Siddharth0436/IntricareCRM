<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Intricare CRM</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-light p-4">
    <div class="container">
        <h2 class="mb-4">Intricare CRM</h2>

        <!-- ADD BUTTON -->
        <div class="d-flex justify-content-end">
            <button class="btn btn-primary mb-3" id="addContactBtn" data-bs-toggle="modal" data-bs-target="#contactModal">
                Add Contact
            </button>
        </div>


        <!-- FILTERS -->
        <div class="card p-3 mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" id="filterName" class="form-control" placeholder="Name">
                </div>
                <div class="col-md-3">
                    <input type="email" id="filterEmail" class="form-control" placeholder="Email">
                </div>
                <div class="col-md-3">
                    <select id="filterGender" class="form-select">
                        <option value="">Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-dark w-100" id="btnFilter">Search</button>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card p-3">
            <table class="table table-bordered text-center">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        {{-- Dynamically add headers for custom fields --}}
                        @foreach($customFields as $field)
                        <th>{{ $field->label }}</th>
                        @endforeach
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="contactsTable"></tbody>
            </table>
            <div id="paginationLinks"></div>
        </div>
    </div>

    <!-- MODAL -->
    <div class="modal fade" id="contactModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="contactForm" enctype="multipart/form-data">
                        <input type="hidden" name="contact_id" id="contact_id">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label>Name</label>
                                <input type="text" name="name" id="name" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label>Email</label>
                                <input type="email" name="email" id="email" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label>Phone</label>
                                <input type="text" name="phone" id="phone" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label>Gender</label>
                                <select name="gender" id="gender" class="form-select">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label>Profile Image</label>
                                <input type="file" name="profile_image" id="profile_image" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label>Additional File</label>
                                <input type="file" name="additional_file" id="additional_file" class="form-control">
                            </div>

                            <h5 class="mt-3">Custom Fields</h5>
                            <div class="d-flex justify-content-end mb-2">
                                <button type="button" class="btn btn-sm btn-secondary" id="manageFieldsBtn">Manage Custom Fields</button>
                            </div>
                            <div id="customFieldsArea" class="row g-3 border-top pt-3">
                                @foreach($customFields as $field)
                                <div class="col-md-6">
                                    <label>{{ $field->label }}</label>
                                    <input type="text" class="form-control custom-field"
                                        name="custom_fields[{{ $field->id }}]"
                                        data-id="{{ $field->id }}">
                                </div>
                                @endforeach
                            </div>

                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button id="saveContactBtn" class="btn btn-primary">Save Contact</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CUSTOM FIELDS MANAGEMENT MODAL -->
    <div class="modal fade" id="customFieldsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Custom Fields</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Form to add new field -->
                    <h5>Add New Field</h5>
                    <form id="newCustomFieldForm" class="row g-2 align-items-end mb-4">
                        <div class="col">
                            <label>Field Label</label>
                            <input type="text" name="label" class="form-control" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-success">Add</button>
                        </div>
                    </form>

                    <!-- List of existing fields -->
                    <h5>Existing Fields</h5>
                    <ul class="list-group" id="existingFieldsList">
                        <!-- Fields will be loaded here by JS -->
                    </ul>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MERGE FIELDS MANAGEMENT MODAL -->
    <div class="modal fade" id="mergeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Merge Contacts</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Select the master contact (the primary record to keep):</p>
                    <div id="mergeCandidates"></div>

                    <hr>
                    <div id="mergePreviewArea" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button id="btnPreviewMerge" class="btn btn-outline-primary">Preview</button>
                    <button id="btnConfirmMerge" class="btn btn-primary" disabled>Confirm Merge</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make custom fields available to JavaScript
        const customFields = @json($customFields);


        function toast(type, message) {
            alert(message);
        }

        function showValidationErrors(errors) {
            $('#contactForm').find('.is-invalid').removeClass('is-invalid');
            $('#contactForm').find('.invalid-feedback').remove();

            $.each(errors, function(field, msgs) {
                let el = $('[name="' + field + '"]');
                if (!el.length && field.startsWith("custom_fields.")) {
                    let id = field.split(".")[1];
                    el = $('[data-id="' + id + '"]');
                }
                if (el.length) {
                    el.addClass('is-invalid');
                    el.after('<div class="invalid-feedback">' + msgs.join('<br>') + '</div>');
                }
            });
        }

        function setLoading(on) {
            if (on) {
                $('body').append(`<div id="spinnerOverlay"
            style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.7);
            display:flex;align-items:center;justify-content:center;z-index:2000;">
            <div class="spinner-border"></div></div>`);
            } else {
                $('#spinnerOverlay').remove();
            }
        }

        function resetModal() {
            $('#modalTitle').text('Add Contact');
            $('#contactForm')[0].reset();
            $('#contact_id').val('');
            $('#profilePreview').remove();
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();
            $('.custom-field').val('');
        }

        $('#profile_image').change(function() {
            const file = this.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                if (!$('#profilePreview').length) {
                    $('#profile_image').after(`<img id="profilePreview" class="img-thumbnail mt-2"
                style="max-width:120px;">`);
                }
                $('#profilePreview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        });

        function loadContacts(url = "{{ url('/contacts') }}") {
            setLoading(true);
            $.ajax({
                url: url,
                method: "GET",
                data: {
                    name: $('#filterName').val(),
                    email: $('#filterEmail').val(),
                    gender: $('#filterGender').val()
                },
                success: function(res) {
                    let rows = "";
                    res.data.forEach(function(c) {
                        // Handle profile image
                        let imageUrl = c.profile_image ? `/storage/${c.profile_image}` : 'https://via.placeholder.com/50';
                        let imageHtml = `<img src="${imageUrl}" class="img-thumbnail" style="width:50px; height:50px; object-fit:cover;">`;

                        // Prepare custom field cells
                        let customFieldCells = '';
                        customFields.forEach(function(fieldSchema) {
                            // Find the value for the current contact that matches the schema's field ID
                            let valueObj = (c.custom_values || []).find(v => v.custom_field_id == fieldSchema.id);
                            let value = valueObj ? valueObj.value : '';
                            customFieldCells += `<td>${value}</td>`;
                        });

                        rows += `
                    <tr>
                        <td>${imageHtml}</td>
                        <td>${c.name}</td>
                        <td>${c.email ?? ""}</td>
                        <td>${c.phone ?? ""}</td>
                        ${customFieldCells}
                        <td>
                        <button class="btn btn-sm btn-info mergeBtn" data-id="${c.id}">Merge</button>
                            <button class="btn btn-warning btn-sm editBtn" data-id="${c.id}">
                                Edit
                            </button>
                            <button class="btn btn-sm btn-danger deleteBtn" data-id="${c.id}">Delete</button>
                        </td>
                    </tr>`;
                    });
                    $('#contactsTable').html(rows);

                    $('#paginationLinks').html(res.links ?? "");
                },
                error: function() {
                    toast('error', 'Failed to load contacts');
                },
                complete: function() {
                    setLoading(false);
                }
            });
        }

        // Delete button was missing from the dynamic row creation, adding it here.
        $(document).on('click', '.deleteBtn', function() {
            let id = $(this).data('id');

            // Using confirm for simplicity, but Swal.fire is better
            if (!confirm('Are you sure you want to delete this contact?')) {
                return;
            }

            setLoading(true);
            $.ajax({
                url: `/contacts/${id}`,
                method: 'POST', // Using POST with _method spoofing
                data: {
                    _method: 'DELETE',
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    toast('success', res.message || 'Contact deleted successfully.');
                    loadContacts(); // Refresh the list
                },
                error: function() {
                    toast('error', 'Failed to delete contact.');
                },
                complete: function() {
                    setLoading(false);
                }
            });
        });


        loadContacts();

        // Use Bootstrap's native events for modal management
        $('#contactModal').on('show.bs.modal', function(event) {
            // If the modal is triggered by the 'Add Contact' button, reset the form.
            const button = event.relatedTarget;
            if (button && button.id === 'addContactBtn') {
                resetModal();
            }
        });


        $(document).on('click', '#btnFilter', function() {
            loadContacts();
        });

        $(document).on('click', '.editBtn', function() {
            let id = $(this).data("id");

            setLoading(true);
            $.ajax({
                url: `/contacts/${id}`,
                method: "GET",
                success: function(c) {
                    // Clear previous validation errors and previews
                    resetModal();
                    $('#modalTitle').text('Edit Contact');
                    $('#contact_id').val(c.id);
                    $('#name').val(c.name);
                    $('#email').val(c.email);
                    $('#phone').val(c.phone);
                    $('#gender').val(c.gender);

                    // Populate custom fields
                    $('.custom-field').each(function() {
                        let fid = $(this).data('id');
                        let found = c.custom_values.find(v => v.custom_field_id == fid);
                        $(this).val(found ? found.value : "");
                    });

                    if (c.profile_image) {
                        $('#profile_image').after(
                            `<img id="profilePreview" class="img-thumbnail mt-2"
                        style="max-width:120px;" src="/storage/${c.profile_image}">`
                        );
                    }

                    // Show the modal
                    const contactModal = document.getElementById('contactModal');
                    bootstrap.Modal.getOrCreateInstance(contactModal).show();
                },
                error: function() {
                    toast('error', 'Failed to load contact');
                },
                complete: function() {
                    setLoading(false);
                }
            });
        });


        /* ===================== SAVE CONTACT ===================== */

        $(document).on('click', '#saveContactBtn', function(e) {
            e.preventDefault();

            let id = $('#contact_id').val();
            let url = id ? `/contacts/${id}` : `{{ route('contacts.store') }}`;
            let formData = new FormData(document.getElementById('contactForm'));

            if (id) formData.append('_method', 'PUT');

            setLoading(true);

            $.ajax({
                url: url,
                method: "POST",
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(res) {
                    toast('success', res.message || "Saved!");
                    // Correctly hide the modal instance
                    const contactModal = document.getElementById('contactModal');
                    const modalInstance = bootstrap.Modal.getInstance(contactModal);
                    if (modalInstance) modalInstance.hide();
                    loadContacts();
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        showValidationErrors(xhr.responseJSON.errors);
                    } else {
                        toast('error', 'Failed to save contact');
                    }
                },
                complete: function() {
                    setLoading(false);
                }
            });
        });

        /* ===================== CUSTOM FIELD MANAGEMENT ===================== */

        function getCustomFieldsModal() {
            // Always get the latest instance to avoid backdrop issues
            return bootstrap.Modal.getOrCreateInstance(document.getElementById('customFieldsModal'));
        }

        // Open management modal
        $('#manageFieldsBtn').click(function() {
            setLoading(true);
            $.ajax({
                url: '/custom-fields',
                method: 'GET',
                success: function(fields) {
                    let listHtml = '<li class="list-group-item">No custom fields yet.</li>';
                    if (fields.length) {
                        listHtml = fields.map(field => `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        ${field.label}
                        <button class="btn btn-danger btn-sm delete-field-btn" data-id="${field.id}">Delete</button>
                    </li>
                `).join('');
                    }
                    $('#existingFieldsList').html(listHtml);
                    getCustomFieldsModal().show();
                },
                error: function() {
                    toast('error', 'Could not load fields.');
                },
                complete: function() {
                    setLoading(false);
                }
            });
        });

        // Add a new custom field
        $('#newCustomFieldForm').submit(function(e) {
            e.preventDefault();
            setLoading(true);
            $.ajax({
                url: '/custom-fields',
                method: 'POST',
                data: {
                    label: $(this).find('[name="label"]').val(),
                    type: 'text', // Defaulting to text for simplicity
                    _token: '{{ csrf_token() }}'
                },
                success: function(newField) {
                    toast('success', 'Field added successfully!');

                    // Dynamically add to the management list
                    if ($('#existingFieldsList').find('.list-group-item-info').length) {
                        $('#existingFieldsList').html(''); // Clear "No fields" message
                    }
                    $('#existingFieldsList').append(`
                <li class="list-group-item d-flex justify-content-between align-items-center" data-field-id="${newField.id}">
                    ${newField.label}
                    <button class="btn btn-danger btn-sm delete-field-btn" data-id="${newField.id}">Delete</button>
                </li>
            `);

                    // Dynamically add to the contact form
                    $('#customFieldsArea').append(`
                <div class="col-md-6" data-field-id="${newField.id}">
                    <label>${newField.label}</label>
                    <input type="text" class="form-control custom-field" name="custom_fields[${newField.id}]" data-id="${newField.id}">
                </div>
            `);

                    $('#newCustomFieldForm')[0].reset();
                    setLoading(false);
                },
                error: function(xhr) {
                    toast('error', xhr.responseJSON?.message || 'Failed to add field.');
                    setLoading(false);
                }
            });
        });

        // Delete a custom field
        $(document).on('click', '.delete-field-btn', function() {
            if (!confirm('Are you sure? Deleting this field will remove all associated data for all contacts.')) return;

            setLoading(true);
            const fieldId = $(this).data('id');
            $.ajax({
                url: `/custom-fields/${fieldId}`,
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    toast('success', res.message || 'Field deleted!');
                    // Remove from both the management list and the contact form
                    $(`[data-field-id="${fieldId}"]`).fadeOut(300, function() {
                        $(this).remove();
                    });
                    setLoading(false);
                },
                error: function() {
                    toast('error', 'Failed to delete field.');
                    setLoading(false);
                }
            });
        });
        let mergeSecondaryId = null;

        $(document).on('click', '.mergeBtn', function() {
            mergeSecondaryId = $(this).data('id');

            // Open a small modal to select master contact:
            // For simplicity, fetch a list of active contacts (excluding secondary)
            $.get('/contacts', {
                ajax_list: 1,
                exclude: mergeSecondaryId
            }, function(res) {
                // res should be JSON of active contacts (implement route or use existing ajax listing)
                // Build a radio list for selection inside modal and then show modal
                // (I'll provide HTML/JS below)
            });
        });
        // Open merge modal: get candidates
        $(document).on('click', '.mergeBtn', function() {
            mergeSecondaryId = $(this).data('id');
            $('#mergePreviewArea').hide().html('');
            $('#btnConfirmMerge').prop('disabled', true);

            $.get('/contacts', {
                ajax_list: 1,
                exclude: mergeSecondaryId
            }, function(res) {
                // expect res.data array like earlier loadContacts; build list
                let html = '<div class="list-group">';
                res.data.forEach(c => {
                    html += `<label class="list-group-item">
                        <input type="radio" name="master_id" value="${c.id}" /> ${c.name} (${c.email || ''})
                     </label>`;
                });
                html += '</div>';
                $('#mergeCandidates').html(html);
                new bootstrap.Modal(document.getElementById('mergeModal')).show();
            });
        });

        // Preview
        $(document).on('click', '#btnPreviewMerge', function() {
            let masterId = $('#mergeCandidates input[name="master_id"]:checked').val();
            if (!masterId) return alert('Please select a master contact');

            $.post('{{ route("contacts.merge.preview") }}', {
                master_id: masterId,
                secondary_id: mergeSecondaryId,
                _token: $('meta[name="csrf-token"]').attr('content')
            }, function(res) {
                // show res.preview in friendly way
                let html = '<h6>Emails to copy:</h6><ul>';
                res.preview.emails.forEach(e => html += `<li>${e}</li>`);
                html += '</ul><h6>Phones to copy:</h6><ul>';
                res.preview.phones.forEach(p => html += `<li>${p}</li>`);
                html += '</ul><h6>Custom fields:</h6><ul>';
                res.preview.custom_fields.forEach(cf => {
                    html += `<li>${cf.field_label} - ${cf.action} (${cf.secondary_value ?? ''})</li>`;
                });
                html += '</ul>';
                $('#mergePreviewArea').html(html).show();
                $('#btnConfirmMerge').prop('disabled', false);
            }).fail(function(xhr) {
                alert('Preview failed');
            });
        });

        // Confirm perform merge
        $(document).on('click', '#btnConfirmMerge', function() {
            let masterId = $('#mergeCandidates input[name="master_id"]:checked').val();
            $.post('{{ route("contacts.merge.perform") }}', {
                master_id: masterId,
                secondary_id: mergeSecondaryId,
                _token: $('meta[name="csrf-token"]').attr('content')
            }, function(res) {
                alert(res.message || 'Merged');
                loadContacts();
                bootstrap.Modal.getInstance(document.getElementById('mergeModal')).hide();
            }).fail(function(xhr) {
                alert('Merge failed');
            });
        });
    </script>

</body>

</html>