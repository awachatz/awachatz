<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class BoxRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Request $request)
    {
        switch ($request->method()) {
            case 'POST':
                return [
                    'name' => ['required', 'max:255', Rule::unique('boxes')],
                    'min_items' => ['required', 'numeric', 'gt:0'],
                    'max_items' => ['required', 'numeric', 'gt:0'],
                    'weight' => ['required', 'numeric', 'gt:0'],
                    'height' => ['required', 'numeric', 'gt:0'],
                    'width' => ['required', 'numeric', 'gt:0'],
                    'length' => ['required', 'numeric', 'gt:0'],
                ];
                break;

            case 'PATCH':
            case 'PUT':
                return [
                    'name' => ['required', 'max:255', Rule::unique('boxes')->ignore($request->box->id)],
                    'min_items' => ['required', 'numeric', 'gt:0'],
                    'max_items' => ['required', 'numeric', 'gt:0'],
                    'weight' => ['required', 'numeric', 'gt:0'],
                    'height' => ['required', 'numeric', 'gt:0'],
                    'width' => ['required', 'numeric', 'gt:0'],
                    'length' => ['required', 'numeric', 'gt:0'],
                ];
                break;
        }
    }
}
