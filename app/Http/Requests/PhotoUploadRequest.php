<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhotoUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']];
    }

    public function messages(): array
    {
        return [
            'photo.mimes' => 'Envie a foto em JPG, PNG ou WebP.',
            'photo.max' => 'A foto passa de 8 MB.',
            'photo.uploaded' => 'A foto não chegou ao servidor. Tente de novo.',
        ];
    }
}
