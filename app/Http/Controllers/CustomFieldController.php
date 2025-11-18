<?php

namespace App\Http\Controllers;

use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomFieldController extends Controller
{
    public function index()
    {
        return CustomField::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,textarea,date,number,select,checkbox,radio,file',
        ]);

        $validated['name'] = Str::slug($validated['label'], '_');

        if (CustomField::where('name', $validated['name'])->exists()) {
            return response()->json(['message' => 'A field with a similar label already exists.'], 422);
        }

        $field = CustomField::create($validated);

        return response()->json($field, 201);
    }

    public function destroy(CustomField $customField)
    {
        $customField->delete();
        return response()->json(['message' => 'Field deleted successfully.']);
    }
}