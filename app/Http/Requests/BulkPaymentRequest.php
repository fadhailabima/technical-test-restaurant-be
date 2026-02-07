<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => 'required|exists:order_sessions,id',
            'payment_method' => 'required|in:cash,card,qris,gopay,ovo,dana',
            'amount' => 'required|numeric|min:0',
            'reference_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'session_id.required' => 'Session harus dipilih',
            'session_id.exists' => 'Session tidak ditemukan',
            'payment_method.required' => 'Metode pembayaran harus dipilih',
            'payment_method.in' => 'Metode pembayaran harus salah satu dari: cash, card, qris, gopay, ovo, dana',
            'amount.required' => 'Jumlah pembayaran harus diisi',
            'amount.numeric' => 'Jumlah pembayaran harus berupa angka',
            'amount.min' => 'Jumlah pembayaran tidak boleh negatif',
            'reference_number.max' => 'Nomor referensi maksimal 50 karakter',
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
