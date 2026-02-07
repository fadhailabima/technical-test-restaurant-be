<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => 'required|exists:tables,id',
            'customer_name' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'table_id.required' => 'Meja harus dipilih',
            'table_id.exists' => 'Meja tidak ditemukan',
            'customer_name.required' => 'Nama customer harus diisi',
            'customer_name.max' => 'Nama customer maksimal 255 karakter',
            'items.required' => 'Item order harus diisi',
            'items.array' => 'Format items tidak valid',
            'items.min' => 'Minimal harus ada 1 item',
            'items.*.menu_id.required' => 'Menu harus dipilih',
            'items.*.menu_id.exists' => 'Menu tidak ditemukan',
            'items.*.quantity.required' => 'Quantity harus diisi',
            'items.*.quantity.integer' => 'Quantity harus berupa angka',
            'items.*.quantity.min' => 'Quantity minimal 1',
            'items.*.notes.max' => 'Catatan maksimal 500 karakter',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422));
    }
}
