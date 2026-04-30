<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'date' => 'required|date',
            'state_id' => 'required|exists:states,id',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'El campo latitud es obligatorio.',
            'latitude.numeric' => 'El campo latitud debe ser numérico.',
            'longitude.required' => 'El campo longitud es obligatorio.',
            'longitude.numeric' => 'El campo longitud debe ser numérico.',
            'date.required' => 'El campo fecha es obligatorio.',
            'date.date' => 'El campo fecha debe ser una fecha válida.',
            'state_id.required' => 'El campo estado es obligatorio.',
            'state_id.exists' => 'El estado seleccionado no existe.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
