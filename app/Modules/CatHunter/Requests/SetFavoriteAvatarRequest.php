<?php

namespace App\Modules\CatHunter\Requests;

class SetFavoriteAvatarRequest extends CatHunterFormRequest
{
    public function rules(): array
    {
        return [
            'cat_id' => ['required', 'string', 'size:26', 'exists:cats,id'],
        ];
    }
}