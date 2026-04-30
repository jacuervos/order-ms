<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'date' => 'sometimes|date',
            'state_id' => 'sometimes|exists:states,id',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.numeric' => 'El campo latitud debe ser numérico.',
            'longitude.numeric' => 'El campo longitud debe ser numérico.',
            'date.date' => 'El campo fecha debe ser una fecha válida.',
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
