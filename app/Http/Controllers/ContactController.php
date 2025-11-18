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
    /**
     * Show contacts page + handle AJAX list/filter
     */
    public function index(Request $request)
    {
        // AJAX LIST (pagination + filters)
        if ($request->ajax()) {
            $query = Contact::query();

            // Basic Filters
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }
            if ($request->filled('email')) {
                $query->where('email', 'like', '%' . $request->email . '%');
            }
            if ($request->filled('gender')) {
                $query->where('gender', $request->gender);
            }

            // Filter by Custom Field
            if ($request->filled('custom_field_name') && $request->filled('custom_value')) {
                $cf = CustomField::where('name', $request->custom_field_name)->first();

                if ($cf) {
                    $query->whereHas('customValues', function ($q) use ($cf, $request) {
                        $q->where('custom_field_id', $cf->id)
                            ->where('value', 'like', '%' . $request->custom_value . '%');
                    });
                }
            }

            $contacts = $query->with('customValues.field')->latest()->paginate(10);
            return response()->json($contacts);
        }

        // NON-AJAX → Render Blade with custom fields
        $customFields = CustomField::all();
        return view('contacts.index', compact('customFields'));
    }

    /**
     * Store a new contact
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'gender'         => 'nullable|in:male,female,other',
            'profile_image'  => 'nullable|image|max:5120',   // 5MB
            'additional_file' => 'nullable|file|max:10240',   // 10MB
        ]);

        // Handle file uploads
        $validated['profile_image'] = $this->uploadFile($request, 'profile_image', 'profiles');
        $validated['additional_file'] = $this->uploadFile($request, 'additional_file', 'files');

        $contact = Contact::create($validated);

        // Save Dynamic Custom Fields
        if ($request->has('custom_fields')) {
            foreach ($request->custom_fields as $fieldId => $value) {
                ContactCustomValue::create([
                    'contact_id' => $contact->id,
                    'custom_field_id' => $fieldId,
                    'value' => $value,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact created successfully',
        ]);
    }

    /**
     * Show single contact detail (for edit modal)
     */
    public function show($id)
    {
        $contact = Contact::with('customValues.field')->findOrFail($id);
        return response()->json($contact);
    }

    /**
     * Update contact
     */
    public function update(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'gender'         => 'nullable|in:male,female,other',
            'profile_image'  => 'nullable|image|max:5120',
            'additional_file' => 'nullable|file|max:10240',
        ]);

        // Replace profile image if uploaded
        if ($request->hasFile('profile_image')) {
            $this->deleteFile($contact->profile_image);
            $validated['profile_image'] = $this->uploadFile($request, 'profile_image', 'profiles');
        }

        // Replace additional file if uploaded
        if ($request->hasFile('additional_file')) {
            $this->deleteFile($contact->additional_file);
            $validated['additional_file'] = $this->uploadFile($request, 'additional_file', 'files');
        }

        $contact->update($validated);

        // Update Custom Fields
        if ($request->has('custom_fields')) {
            foreach ($request->custom_fields as $fieldId => $value) {
                ContactCustomValue::updateOrCreate(
                    ['contact_id' => $contact->id, 'custom_field_id' => $fieldId],
                    ['value' => $value]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully',
        ]);
    }

    /**
     * Delete contact
     */
    public function destroy($id)
    {
        $contact = Contact::findOrFail($id);

        // Delete files
        $this->deleteFile($contact->profile_image);
        $this->deleteFile($contact->additional_file);

        // Delete custom field values
        ContactCustomValue::where('contact_id', $id)->delete();

        // Delete contact
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully',
        ]);
    }

    /**
     * FILE UPLOAD HANDLER
     */
    private function uploadFile(Request $request, $field, $folder)
    {
        if (!$request->hasFile($field)) return null;

        return $request->file($field)->store($folder, 'public');
    }

    /**
     * FILE DELETE HANDLER
     */
    private function deleteFile($path)
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
    public function initiateMerge(Request $request)
    {
        $request->validate([
            'primary_id' => 'required|exists:contacts,id',
            'secondary_id' => 'required|exists:contacts,id|different:primary_id',
        ]);

        $master = Contact::with('customValues.field', 'emails', 'phones')->findOrFail($request->primary_id);
        $secondary = Contact::with('customValues.field', 'emails', 'phones')->findOrFail($request->secondary_id);

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

        $master = Contact::with('customValues.field', 'emails', 'phones')->findOrFail($request->master_id);
        $secondary = Contact::with('customValues.field', 'emails', 'phones')->findOrFail($request->secondary_id);

        $changes = [
            'emails' => [],
            'phones' => [],
            'custom_fields' => [],
        ];

        // Emails: collect secondary emails not present in master
        $masterEmails = $master->emails->pluck('email')->map(fn($v) => strtolower($v))->toArray();
        foreach ($secondary->emails as $e) {
            if (!in_array(strtolower($e->email), $masterEmails)) {
                $changes['emails'][] = $e->email;
            }
        }

        // Phones
        $masterPhones = $master->phones->pluck('phone')->map(fn($v) => preg_replace('/\D/', '', $v))->all();
        foreach ($secondary->phones as $p) {
            $normalized = preg_replace('/\D/', '', $p->phone);
            if (!in_array($normalized, $masterPhones)) {
                $changes['phones'][] = $p->phone;
            }
        }

        // Custom fields: check per field
        $masterCustom = $master->customValues->keyBy('custom_field_id');
        $secondaryCustom = $secondary->customValues->keyBy('custom_field_id');

        foreach ($secondaryCustom as $fieldId => $secVal) {
            $masterVal = $masterCustom->has($fieldId) ? $masterCustom[$fieldId]->value : null;
            if (is_null($masterVal) && $secVal->value !== null && $secVal->value !== '') {
                $changes['custom_fields'][] = [
                    'field_id' => $fieldId,
                    'field_label' => $secVal->field->label ?? $fieldId,
                    'action' => 'copy_to_master',
                    'secondary_value' => $secVal->value,
                    'master_value' => null,
                ];
            } elseif ($masterVal !== null && $masterVal !== '' && $masterVal != $secVal->value) {
                // conflict — we'll keep master but log secondary
                $changes['custom_fields'][] = [
                    'field_id' => $fieldId,
                    'field_label' => $secVal->field->label ?? $fieldId,
                    'action' => 'conflict_keep_master',
                    'master_value' => $masterVal,
                    'secondary_value' => $secVal->value,
                ];
            }
        }

        return response()->json(['preview' => $changes]);
    }

    public function performMerge(Request $request)
    {
        $request->validate([
            'master_id' => 'required|exists:contacts,id',
            'secondary_id' => 'required|exists:contacts,id|different:master_id',
        ]);

        $master = Contact::with('customValues', 'emails', 'phones')->findOrFail($request->master_id);
        $secondary = Contact::with('customValues', 'emails', 'phones')->findOrFail($request->secondary_id);

        $log = []; // collect raw logs

        DB::transaction(function () use (&$log, $master, $secondary, $request) {

            $log = [
                'emails' => [],
                'phones' => [],
                'custom_fields' => [],
            ];

            // -------------------------
            // 1) EMAIL MERGE
            // -------------------------
            $masterEmails = $master->emails->pluck('email')->map(fn($v) => strtolower($v))->toArray();

            foreach ($secondary->emails as $e) {
                if (!in_array(strtolower($e->email), $masterEmails)) {
                    $master->emails()->create(['email' => $e->email, 'is_primary' => false]);
                    $log['emails'][] = $e->email;
                } else {
                    // Log that the email was a duplicate and was skipped
                    $log['emails_skipped'][] = $e->email;
                }
            }

            // -------------------------
            // 2) PHONE MERGE
            // -------------------------
            $masterPhones = $master->phones->pluck('phone')->map(fn($v) => preg_replace('/\D/', '', $v))->toArray();

            foreach ($secondary->phones as $p) {
                $norm = preg_replace('/\D/', '', $p->phone);
                if (!in_array($norm, $masterPhones)) {
                    $master->phones()->create(['phone' => $p->phone, 'is_primary' => false]);
                    $log['phones'][] = $p->phone;
                } else {
                    // Log that the phone was a duplicate and was skipped
                    $log['phones_skipped'][] = $p->phone;
                }
            }

            // -------------------------
            // 3) CUSTOM FIELDS MERGE
            // -------------------------
            $masterCustom = $master->customValues->keyBy('custom_field_id');

            foreach ($secondary->customValues as $secVal) {

                $fieldId = $secVal->custom_field_id;
                $masterValRecord = $masterCustom->get($fieldId);

                if (!$masterValRecord || $masterValRecord->value === null || $masterValRecord->value === '') {

                    ContactCustomValue::create([
                        'contact_id' => $master->id,
                        'custom_field_id' => $fieldId,
                        'value' => $secVal->value,
                    ]);

                    $log['custom_fields'][] = [
                        'field_id' => $fieldId,
                        'action' => 'copied',
                        'value' => $secVal->value,
                    ];
                } elseif ($masterValRecord->value != $secVal->value) {

                    $log['custom_fields'][] = [
                        'field_id' => $fieldId,
                        'action' => 'conflict_kept_master',
                        'master_value' => $masterValRecord->value,
                        'secondary_value' => $secVal->value,
                    ];
            } else {
                // Log identical fields for a more complete report
                $log['custom_fields'][] = [
                    'field_id' => $fieldId,
                    'action' => 'kept_master_identical',
                    'value' => $secVal->value,
                ];
                }
            }

            // -------------------------
            // 4) FILES
            // -------------------------
            if ($secondary->profile_image) {
                $log['secondary_profile_image'] = $secondary->profile_image;
            }

            if ($secondary->additional_file) {
                $log['secondary_additional_file'] = $secondary->additional_file;
            }

            // -------------------------
            // 5) MARK SECONDARY AS MERGED
            // -------------------------
            $secondary->update([
                'is_active' => false,
                'merged_to' => $master->id,
            ]);

            // -------------------------
            // 6) SAVE MERGE LOG
            // -------------------------
            ContactMergeLog::create([
                'master_contact_id' => $master->id,
                'secondary_contact_id' => $secondary->id,
                'changes' => $log,
                'performed_by' => auth()->id() ?? null,
            ]);
        });

        $mergedDetails = [];

        // EMAILS
        foreach ($log['emails'] as $email) {
            $mergedDetails[] = [
                'field' => 'Email',
                'master_value' => null,
                'secondary_value' => $email,
                'final_value' => $email,
                'action' => 'Copied from secondary'
            ];
        }

        // PHONES
        foreach ($log['phones'] as $phone) {
            $mergedDetails[] = [
                'field' => 'Phone',
                'master_value' => null,
                'secondary_value' => $phone,
                'final_value' => $phone,
                'action' => 'Copied from secondary'
            ];
        }

        // Log skipped emails and phones for a complete report
        foreach ($log['emails_skipped'] ?? [] as $email) {
            $mergedDetails[] = ['field' => 'Email', 'master_value' => $email, 'secondary_value' => $email, 'final_value' => $email, 'action' => 'Kept Master (Identical)'];
        }
        foreach ($log['phones_skipped'] ?? [] as $phone) {
            $mergedDetails[] = ['field' => 'Phone', 'master_value' => $phone, 'secondary_value' => $phone, 'final_value' => $phone, 'action' => 'Kept Master (Identical)'];
        }

        // CUSTOM FIELDS
        foreach ($log['custom_fields'] as $cf) {

            $mergedDetails[] = [
                'field' => 'Custom Field ' . $cf['field_id'],
                'master_value' => $cf['action'] === 'copied' ? null : ($cf['master_value'] ?? null),
                'secondary_value' => $cf['value'] ?? $cf['secondary_value'] ?? null,
                'final_value' => $cf['action'] === 'copied'
                    ? ($cf['value'] ?? null)
                    : ($cf['master_value'] ?? null),
                'action' => str_replace('_', ' ', Str::title($cf['action']))
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Merge completed successfully!',
            'merged_details' => $mergedDetails,   // <-- 🔥 FRONTEND USES THIS
            'master_name' => $master->name,
            'secondary_name' => $secondary->name,
            'log' => $log, // original raw log (optional)
        ]);
    }
}
