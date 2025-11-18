<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CustomField;
use App\Models\ContactMergeLog;
use App\Models\ContactCustomValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $contacts = $this->buildContactQuery($request)
                ->with('customValues.field')
                ->latest()
                ->paginate(10);
                
            return response()->json($contacts);
        }

        $customFields = CustomField::all();
        return view('contacts.index', compact('customFields'));
    }

    private function buildContactQuery(Request $request)
    {
        $query = Contact::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }
        
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        $this->applyCustomFieldFilter($query, $request);

        return $query;
    }

    private function applyCustomFieldFilter($query, $request)
    {
        if ($request->filled('custom_field_name') && $request->filled('custom_value')) {
            $customField = CustomField::where('name', $request->custom_field_name)->first();

            if ($customField) {
                $query->whereHas('customValues', function ($q) use ($customField, $request) {
                    $q->where('custom_field_id', $customField->id)
                        ->where('value', 'like', '%' . $request->custom_value . '%');
                });
            }
        }
    }

    public function store(Request $request)
    {
        $validatedData = $this->validateContactData($request);
        
        $validatedData['profile_image'] = $this->handleFileUpload($request, 'profile_image', 'profiles');
        $validatedData['additional_file'] = $this->handleFileUpload($request, 'additional_file', 'files');

        $contact = Contact::create($validatedData);

        $this->saveCustomFields($contact->id, $request->custom_fields);

        return response()->json([
            'success' => true,
            'message' => 'Contact created successfully',
        ]);
    }

    public function show($id)
    {
        $contact = Contact::with('customValues.field')->findOrFail($id);
        return response()->json($contact);
    }

    public function update(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);
        $validatedData = $this->validateContactData($request);

        $this->updateContactFiles($contact, $request, $validatedData);

        $contact->update($validatedData);

        $this->updateCustomFields($contact->id, $request->custom_fields);

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $contact = Contact::findOrFail($id);

        $this->removeContactFiles($contact);
        ContactCustomValue::where('contact_id', $id)->delete();
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully',
        ]);
    }

    private function validateContactData(Request $request)
    {
        return $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'gender'         => 'nullable|in:male,female,other',
            'profile_image'  => 'nullable|image|max:5120',
            'additional_file' => 'nullable|file|max:10240',
        ]);
    }

    private function handleFileUpload(Request $request, $fieldName, $storageFolder)
    {
        if (!$request->hasFile($fieldName)) {
            return null;
        }

        return $request->file($fieldName)->store($storageFolder, 'public');
    }

    private function removeFile($filePath)
    {
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }

    private function updateContactFiles($contact, $request, &$validatedData)
    {
        if ($request->hasFile('profile_image')) {
            $this->removeFile($contact->profile_image);
            $validatedData['profile_image'] = $this->handleFileUpload($request, 'profile_image', 'profiles');
        }

        if ($request->hasFile('additional_file')) {
            $this->removeFile($contact->additional_file);
            $validatedData['additional_file'] = $this->handleFileUpload($request, 'additional_file', 'files');
        }
    }

    private function removeContactFiles($contact)
    {
        $this->removeFile($contact->profile_image);
        $this->removeFile($contact->additional_file);
    }

    private function saveCustomFields($contactId, $customFields)
    {
        if (!$customFields) {
            return;
        }

        foreach ($customFields as $fieldId => $value) {
            ContactCustomValue::create([
                'contact_id' => $contactId,
                'custom_field_id' => $fieldId,
                'value' => $value,
            ]);
        }
    }

    private function updateCustomFields($contactId, $customFields)
    {
        if (!$customFields) {
            return;
        }

        foreach ($customFields as $fieldId => $value) {
            ContactCustomValue::updateOrCreate(
                ['contact_id' => $contactId, 'custom_field_id' => $fieldId],
                ['value' => $value]
            );
        }
    }

    public function initiateMerge(Request $request)
    {
        $request->validate([
            'primary_id' => 'required|exists:contacts,id',
            'secondary_id' => 'required|exists:contacts,id|different:primary_id',
        ]);

        $master = Contact::with('customValues.field', 'emails', 'phones')
            ->findOrFail($request->primary_id);
        $secondary = Contact::with('customValues.field', 'emails', 'phones')
            ->findOrFail($request->secondary_id);

        return response()->json([
            'master' => $master,
            'secondary' => $secondary,
        ]);
    }

    public function previewMerge(Request $request)
    {
        $request->validate([
            'master_id' => 'required|exists:contacts,id',
            'secondary_id' => 'required|exists:contacts,id|different:master_id',
        ]);

        $master = Contact::with('customValues.field', 'emails', 'phones')
            ->findOrFail($request->master_id);
        $secondary = Contact::with('customValues.field', 'emails', 'phones')
            ->findOrFail($request->secondary_id);

        $changes = $this->calculateMergeChanges($master, $secondary);

        return response()->json(['preview' => $changes]);
    }

    private function calculateMergeChanges($master, $secondary)
    {
        $changes = [
            'main_fields' => [],
            'emails' => [],
            'phones' => [],
            'custom_fields' => [],
        ];

        $this->calculateMainFieldChanges($changes, $master, $secondary);
        $this->calculateEmailChanges($changes, $master, $secondary);
        $this->calculatePhoneChanges($changes, $master, $secondary);
        $this->calculateCustomFieldChanges($changes, $master, $secondary);

        return $changes;
    }

    private function calculateMainFieldChanges(&$changes, $master, $secondary)
    {
        // Name field
        if ($master->name !== $secondary->name) {
            $changes['main_fields'][] = [
                'field' => 'name',
                'field_label' => 'Name',
                'action' => 'concatenate',
                'master_value' => $master->name,
                'secondary_value' => $secondary->name,
                'new_value' => $master->name . ', ' . $secondary->name
            ];
        }

        // Email field
        if ($master->email && $secondary->email && $master->email !== $secondary->email) {
            $changes['main_fields'][] = [
                'field' => 'email',
                'field_label' => 'Email',
                'action' => 'concatenate',
                'master_value' => $master->email,
                'secondary_value' => $secondary->email,
                'new_value' => $master->email . ', ' . $secondary->email
            ];
        } elseif (!$master->email && $secondary->email) {
            $changes['main_fields'][] = [
                'field' => 'email',
                'field_label' => 'Email',
                'action' => 'copy',
                'master_value' => null,
                'secondary_value' => $secondary->email,
                'new_value' => $secondary->email
            ];
        }

        // Phone field
        if ($master->phone && $secondary->phone && $master->phone !== $secondary->phone) {
            $changes['main_fields'][] = [
                'field' => 'phone',
                'field_label' => 'Phone',
                'action' => 'concatenate',
                'master_value' => $master->phone,
                'secondary_value' => $secondary->phone,
                'new_value' => $master->phone . ', ' . $secondary->phone
            ];
        } elseif (!$master->phone && $secondary->phone) {
            $changes['main_fields'][] = [
                'field' => 'phone',
                'field_label' => 'Phone',
                'action' => 'copy',
                'master_value' => null,
                'secondary_value' => $secondary->phone,
                'new_value' => $secondary->phone
            ];
        }
    }

    private function calculateEmailChanges(&$changes, $master, $secondary)
    {
        $masterEmails = $master->emails->pluck('email')
            ->map(function ($email) { 
                return strtolower($email); 
            })->toArray();

        foreach ($secondary->emails as $secondaryEmail) {
            $emailLower = strtolower($secondaryEmail->email);
            
            if (!in_array($emailLower, $masterEmails)) {
                $changes['emails'][] = [
                    'email' => $secondaryEmail->email,
                    'action' => 'add_new'
                ];
            } else {
                $existingEmail = $master->emails->first(function ($email) use ($emailLower) {
                    return strtolower($email->email) === $emailLower;
                });

                if ($existingEmail) {
                    $changes['emails'][] = [
                        'email' => $secondaryEmail->email,
                        'action' => 'concatenate',
                        'existing_email' => $existingEmail->email,
                        'new_value' => $existingEmail->email . ', ' . $secondaryEmail->email
                    ];
                }
            }
        }
    }

    private function calculatePhoneChanges(&$changes, $master, $secondary)
    {
        $masterPhones = $master->phones->pluck('phone')
            ->map(function ($phone) { 
                return preg_replace('/\D/', '', $phone); 
            })->toArray();

        foreach ($secondary->phones as $secondaryPhone) {
            $normalizedPhone = preg_replace('/\D/', '', $secondaryPhone->phone);
            
            if (!in_array($normalizedPhone, $masterPhones)) {
                $changes['phones'][] = [
                    'phone' => $secondaryPhone->phone,
                    'action' => 'add_new'
                ];
            } else {
                $existingPhone = $master->phones->first(function ($phone) use ($normalizedPhone) {
                    return preg_replace('/\D/', '', $phone->phone) === $normalizedPhone;
                });

                if ($existingPhone) {
                    $changes['phones'][] = [
                        'phone' => $secondaryPhone->phone,
                        'action' => 'concatenate',
                        'existing_phone' => $existingPhone->phone,
                        'new_value' => $existingPhone->phone . ', ' . $secondaryPhone->phone
                    ];
                }
            }
        }
    }

    // Calculate custom field changes for merge
    private function calculateCustomFieldChanges(&$changes, $master, $secondary)
    {
        $masterCustom = $master->customValues->keyBy('custom_field_id');
        $secondaryCustom = $secondary->customValues->keyBy('custom_field_id');

        foreach ($secondaryCustom as $fieldId => $secondaryCustomValue) {
            $masterValue = $masterCustom->has($fieldId) ? $masterCustom[$fieldId]->value : null;

            if (is_null($masterValue) && $secondaryCustomValue->value) {
                $changes['custom_fields'][] = [
                    'field_id' => $fieldId,
                    'field_label' => $secondaryCustomValue->field->label ?? $fieldId,
                    'action' => 'copy_to_master',
                    'secondary_value' => $secondaryCustomValue->value,
                    'master_value' => null,
                ];
            } elseif ($masterValue && $masterValue != $secondaryCustomValue->value) {
                $changes['custom_fields'][] = [
                    'field_id' => $fieldId,
                    'field_label' => $secondaryCustomValue->field->label ?? $fieldId,
                    'action' => 'concatenate',
                    'master_value' => $masterValue,
                    'secondary_value' => $secondaryCustomValue->value,
                    'new_value' => $masterValue . ', ' . $secondaryCustomValue->value,
                ];
            }
        }
    }

    public function performMerge(Request $request)
    {
        $request->validate([
            'master_id' => 'required|exists:contacts,id',
            'secondary_id' => 'required|exists:contacts,id|different:master_id',
        ]);

        $secondary = Contact::findOrFail($request->secondary_id);
        if (!$secondary->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This contact has already been merged and cannot be merged again.',
            ], 422);
        }

        $master = Contact::with('customValues', 'emails', 'phones')->findOrFail($request->master_id);
        $secondary = Contact::with('customValues', 'emails', 'phones')->findOrFail($request->secondary_id);

        $mergeLog = [];

        DB::transaction(function () use (&$mergeLog, $master, $secondary) {
            $mergeLog = $this->executeMergeOperations($master, $secondary);
        });

        $mergeSummary = $this->formatMergeSummary($mergeLog, $master, $secondary);

        return response()->json([
            'success' => true,
            'message' => 'Merge completed successfully!',
            'merged_details' => $mergeSummary,
            'master_name' => $master->name,
            'secondary_name' => $secondary->name,
            'log' => $mergeLog,
        ]);
    }

    private function executeMergeOperations($master, $secondary)
    {
        $log = [
            'main_fields' => [],
            'emails' => [],
            'emails_concatenated' => [],
            'phones' => [],
            'phones_concatenated' => [],
            'custom_fields' => [],
        ];

        $this->mergeMainFields($master, $secondary, $log);
        $master->save();

        $this->mergeEmails($master, $secondary, $log);
        $this->mergePhones($master, $secondary, $log);
        $this->mergeCustomFields($master, $secondary, $log);
        $this->mergeFiles($master, $secondary, $log);

        $secondary->update([
            'is_active' => false,
            'merged_to' => $master->id,
        ]);

        ContactMergeLog::create([
            'master_contact_id' => $master->id,
            'secondary_contact_id' => $secondary->id,
            'changes' => $log,
            'performed_by' => auth()->id() ?? null,
        ]);

        return $log;
    }

    private function mergeMainFields($master, $secondary, &$log)
    {
        // Name field
        if ($master->name !== $secondary->name) {
            $originalName = $master->name;
            $master->name = $master->name . ', ' . $secondary->name;
            $log['main_fields']['name'] = [
                'action' => 'concatenated',
                'original_value' => $originalName,
                'secondary_value' => $secondary->name,
                'final_value' => $master->name
            ];
        }

        // Email field
        if ($master->email && $secondary->email && $master->email !== $secondary->email) {
            $originalEmail = $master->email;
            $master->email = $master->email . ', ' . $secondary->email;
            $log['main_fields']['email'] = [
                'action' => 'concatenated',
                'original_value' => $originalEmail,
                'secondary_value' => $secondary->email,
                'final_value' => $master->email
            ];
        } elseif (!$master->email && $secondary->email) {
            $master->email = $secondary->email;
            $log['main_fields']['email'] = [
                'action' => 'copied',
                'original_value' => null,
                'secondary_value' => $secondary->email,
                'final_value' => $master->email
            ];
        }

        // Phone field
        if ($master->phone && $secondary->phone && $master->phone !== $secondary->phone) {
            $originalPhone = $master->phone;
            $master->phone = $master->phone . ', ' . $secondary->phone;
            $log['main_fields']['phone'] = [
                'action' => 'concatenated',
                'original_value' => $originalPhone,
                'secondary_value' => $secondary->phone,
                'final_value' => $master->phone
            ];
        } elseif (!$master->phone && $secondary->phone) {
            $master->phone = $secondary->phone;
            $log['main_fields']['phone'] = [
                'action' => 'copied',
                'original_value' => null,
                'secondary_value' => $secondary->phone,
                'final_value' => $master->phone
            ];
        }
    }

    //Merge email records
    private function mergeEmails($master, $secondary, &$log)
    {
        $masterEmails = $master->emails->pluck('email')
            ->map(function ($email) { 
                return strtolower($email); 
            })->toArray();

        foreach ($secondary->emails as $secondaryEmail) {
            $emailLower = strtolower($secondaryEmail->email);
            
            if (!in_array($emailLower, $masterEmails)) {
                $master->emails()->create([
                    'email' => $secondaryEmail->email, 
                    'is_primary' => false
                ]);
                $log['emails'][] = $secondaryEmail->email;
            } else {
                $existingMasterEmail = $master->emails->first(function ($email) use ($emailLower) {
                    return strtolower($email->email) === $emailLower;
                });

                if ($existingMasterEmail) {
                    $originalEmail = $existingMasterEmail->email;
                    $newEmailValue = $existingMasterEmail->email . ', ' . $secondaryEmail->email;
                    $existingMasterEmail->update(['email' => $newEmailValue]);
                    $log['emails_concatenated'][] = [
                        'original' => $originalEmail,
                        'added' => $secondaryEmail->email,
                        'final' => $newEmailValue
                    ];
                }
            }
        }
    }

    //Merge phone records
    private function mergePhones($master, $secondary, &$log)
    {
        $masterPhones = $master->phones->pluck('phone')
            ->map(function ($phone) { 
                return preg_replace('/\D/', '', $phone); 
            })->toArray();

        foreach ($secondary->phones as $secondaryPhone) {
            $normalizedPhone = preg_replace('/\D/', '', $secondaryPhone->phone);
            
            if (!in_array($normalizedPhone, $masterPhones)) {
                $master->phones()->create([
                    'phone' => $secondaryPhone->phone, 
                    'is_primary' => false
                ]);
                $log['phones'][] = $secondaryPhone->phone;
            } else {
                $existingMasterPhone = $master->phones->first(function ($phone) use ($normalizedPhone) {
                    return preg_replace('/\D/', '', $phone->phone) === $normalizedPhone;
                });

                if ($existingMasterPhone) {
                    $originalPhone = $existingMasterPhone->phone;
                    $newPhoneValue = $existingMasterPhone->phone . ', ' . $secondaryPhone->phone;
                    $existingMasterPhone->update(['phone' => $newPhoneValue]);
                    $log['phones_concatenated'][] = [
                        'original' => $originalPhone,
                        'added' => $secondaryPhone->phone,
                        'final' => $newPhoneValue
                    ];
                }
            }
        }
    }

    private function mergeCustomFields($master, $secondary, &$log)
    {
        $masterCustom = $master->customValues->keyBy('custom_field_id');

        foreach ($secondary->customValues as $secondaryCustomValue) {
            $fieldId = $secondaryCustomValue->custom_field_id;
            $masterValueRecord = $masterCustom->get($fieldId);

            if (!$masterValueRecord || !$masterValueRecord->value) {
                ContactCustomValue::create([
                    'contact_id' => $master->id,
                    'custom_field_id' => $fieldId,
                    'value' => $secondaryCustomValue->value,
                ]);

                $log['custom_fields'][] = [
                    'field_id' => $fieldId,
                    'action' => 'copied',
                    'value' => $secondaryCustomValue->value,
                ];
            } elseif ($masterValueRecord->value != $secondaryCustomValue->value) {
                $originalValue = $masterValueRecord->value;
                $newValue = $masterValueRecord->value . ', ' . $secondaryCustomValue->value;
                $masterValueRecord->update(['value' => $newValue]);

                $log['custom_fields'][] = [
                    'field_id' => $fieldId,
                    'action' => 'concatenated',
                    'original_value' => $originalValue,
                    'secondary_value' => $secondaryCustomValue->value,
                    'final_value' => $newValue,
                ];
            } else {
                $log['custom_fields'][] = [
                    'field_id' => $fieldId,
                    'action' => 'identical',
                    'value' => $secondaryCustomValue->value,
                ];
            }
        }
    }

    private function mergeFiles($master, $secondary, &$log)
    {
        if (!$master->profile_image && $secondary->profile_image) {
            $newPath = 'profiles/' . Str::random(40) . '.' . pathinfo($secondary->profile_image, PATHINFO_EXTENSION);
            Storage::disk('public')->copy($secondary->profile_image, $newPath);
            $master->profile_image = $newPath;
            $master->save();
            $log['files']['profile_image'] = 'copied';
        }

        if (!$master->additional_file && $secondary->additional_file) {
            $newPath = 'files/' . Str::random(40) . '.' . pathinfo($secondary->additional_file, PATHINFO_EXTENSION);
            Storage::disk('public')->copy($secondary->additional_file, $newPath);
            $master->additional_file = $newPath;
            $master->save();
            $log['files']['additional_file'] = 'copied';
        }
    }

    private function formatMergeSummary($log, $master, $secondary)
    {
        $summary = [];

        foreach ($log['main_fields'] as $field => $details) {
            $summary[] = [
                'field' => ucfirst($field),
                'master_value' => $details['original_value'] ?? null,
                'secondary_value' => $details['secondary_value'] ?? null,
                'final_value' => $details['final_value'] ?? null,
                'action' => ucfirst($details['action'] ?? '')
            ];
        }

        foreach ($log['emails'] as $email) {
            $summary[] = [
                'field' => 'Related Email',
                'master_value' => null,
                'secondary_value' => $email,
                'final_value' => $email,
                'action' => 'Added from secondary'
            ];
        }

        foreach ($log['emails_concatenated'] as $email) {
            $summary[] = [
                'field' => 'Related Email',
                'master_value' => $email['original'],
                'secondary_value' => $email['added'],
                'final_value' => $email['final'],
                'action' => 'Concatenated'
            ];
        }

        foreach ($log['phones'] as $phone) {
            $summary[] = [
                'field' => 'Related Phone',
                'master_value' => null,
                'secondary_value' => $phone,
                'final_value' => $phone,
                'action' => 'Added from secondary'
            ];
        }

        foreach ($log['phones_concatenated'] as $phone) {
            $summary[] = [
                'field' => 'Related Phone',
                'master_value' => $phone['original'],
                'secondary_value' => $phone['added'],
                'final_value' => $phone['final'],
                'action' => 'Concatenated'
            ];
        }

        foreach ($log['custom_fields'] as $customField) {
            if ($customField['action'] === 'copied') {
                $summary[] = [
                    'field' => 'Custom Field ' . $customField['field_id'],
                    'master_value' => null,
                    'secondary_value' => $customField['value'],
                    'final_value' => $customField['value'],
                    'action' => 'Copied from secondary'
                ];
            } elseif ($customField['action'] === 'concatenated') {
                $summary[] = [
                    'field' => 'Custom Field ' . $customField['field_id'],
                    'master_value' => $customField['original_value'],
                    'secondary_value' => $customField['secondary_value'],
                    'final_value' => $customField['final_value'],
                    'action' => 'Concatenated'
                ];
            } else {
                $summary[] = [
                    'field' => 'Custom Field ' . $customField['field_id'],
                    'master_value' => $customField['value'],
                    'secondary_value' => $customField['value'],
                    'final_value' => $customField['value'],
                    'action' => 'Kept Master (Identical)'
                ];
            }
        }

        foreach ($log['files'] ?? [] as $fileType => $action) {
            $summary[] = [
                'field' => ucfirst(str_replace('_', ' ', $fileType)),
                'master_value' => 'Not present',
                'secondary_value' => 'Present',
                'final_value' => 'Copied to master',
                'action' => 'File copied'
            ];
        }

        return $summary;
    }

    public function getContactsForMerge($excludeId)
    {
        $contacts = Contact::where('id', '!=', $excludeId)
            ->where('is_active', true)
            ->get(['id', 'name', 'email']);

        return response()->json($contacts);
    }
}