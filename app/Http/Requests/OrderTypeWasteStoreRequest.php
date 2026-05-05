<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderTypeWasteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id'      => 'required|integer|exists:orders,id',
            'type_waste_id' => 'required|integer',
            'weight'        => 'required|numeric|min:0',
            'points'        => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required'      => 'El campo order_id es obligatorio.',
            'order_id.exists'        => 'La orden no existe.',
            'type_waste_id.required' => 'El campo type_waste_id es obligatorio.',
            'weight.required'        => 'El campo weight es obligatorio.',
            'weight.numeric'         => 'El campo weight debe ser numérico.',
            'points.required'        => 'El campo points es obligatorio.',
            'points.integer'         => 'El campo points debe ser entero.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
