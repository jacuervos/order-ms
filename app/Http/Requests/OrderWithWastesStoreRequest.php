<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderWithWastesStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude'              => 'required|numeric',
            'longitude'             => 'required|numeric',
            'date'                  => 'required|date',
            'state_id'              => 'required|integer',
            'items'                 => 'required|array|min:1',
            'items.*.type_waste_id' => 'required|integer',
            'items.*.weight'        => 'required|numeric|min:0',
            'items.*.points'        => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required'              => 'El campo latitud es obligatorio.',
            'longitude.required'             => 'El campo longitud es obligatorio.',
            'date.required'                  => 'El campo fecha es obligatorio.',
            'state_id.required'              => 'El campo estado es obligatorio.',
            'items.required'                 => 'Debes agregar al menos un residuo.',
            'items.min'                      => 'Debes agregar al menos un residuo.',
            'items.*.type_waste_id.required' => 'El tipo de residuo es obligatorio.',
            'items.*.weight.required'        => 'El peso es obligatorio.',
            'items.*.points.required'        => 'Los puntos son obligatorios.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
